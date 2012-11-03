<?php
namespace Core;

/**
 * Redis缓存驱动器
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Cache
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Cache_Redis
{

    /**
     * Redis链接对象
     *
     * @var array
     */
    protected static $redis = array();

    /**
     * 记录$redis对象被引用数
     * @var array
     */
    protected static $redis_num = array();

    /**
     * Redis对象
     *
     * @var Redis
     */
    protected $_redis;

    protected $servers = array();

    /**
     * 当前配置名
     *
     * @var string
     */
    protected $config_name;

    /**
     * Redis缓存驱动器
     *
     * @param $config_name 配置名或数组
     */
    public function __construct($config_name = 'default')
    {
        if ( \is_array($config_name) )
        {
            $this->servers = $config_name;
            $config_name = \md5(\serialize($config_name));
        }
        else
        {
            $this->servers = \Core::config('cache/redis.' . $config_name);
        }

        if ( !\is_array($this->servers) )
        {
            throw new \Exception('指定的' . $config_name . 'Memcache缓存配置不存在.');
        }
        $this->config_name = $config_name;

        $this->_connect();

        # 增加自动关闭连接列队
        \Core::add_close_connect_class('Cache_Driver_Redis');
    }

    public function __destruct()
    {
        $this->close_connect();
    }

    /**
     * 连接服务器
     */
    protected function _connect()
    {
        if ( $this->_redis )return;
        if ( !$this->config_name )return;

        $config_name = $this->config_name;

        if ( !isset(static::$redis[$config_name]) )
        {
            $class = 'Redis';
            static::$redis[$config_name] = new $class();
            static::$redis_num[$config_name] = 0;

            foreach ( $this->servers as $server )
            {
                $server += array
                (
                    'host'       => '127.0.0.1',
                    'port'       => 6379,
                    'persistent' => true,
                    'timeout'    => 2,
                );

                if ( $server['persistent'] )
                {
                    $action = 'pconnect';
                }
                else
                {
                    $action = 'connect';
                }

                $time = \microtime(1);
                $status = static::$redis[$config_name]->$action($server['host'], $server['port'],$server['timeout']);
                $time = \microtime(1)-$time;
                if ($status)
                {
                    if (\IS_DEBUG)\Core::debug()->info('connect redis server '.$server['host'].':'.$server['port'] . ' time:'.$time);
                    break;
                }
                else
                {
                    if (\IS_DEBUG)\Core::debug()->error('error connect redis server '.$server['host'].':'.$server['port'] . ' time:'.$time);
                }
            }
        }

        # 断开引用关系
        unset($this->_redis);

        # 设置对象
        $this->_redis = & static::$redis[$config_name];

        static::$redis_num[$config_name]++;
    }

    /**
     * 关闭连接
     */
    public function close_connect()
    {
        if ( $this->config_name && $this->_redis )
        {
            unset($this->_redis);
            static::$redis_num[$this->config_name]--;

            if ( 0 == static::$redis_num[$this->config_name] )
            {
                @static::$redis[$this->config_name]->close();

                if (\IS_DEBUG)Core::debug()->info('close redis server.');

                static::$redis[$this->config_name] = null;
                unset(static::$redis[$this->config_name]);
                unset(static::$redis_num[$this->config_name]);
            }
        }
    }

    /**
     * 取得数据，支持批量取
     *
     * @param string/array $key
     * @return mixed
     */
    public function get($key)
    {
        $this->_connect();

        $time = \microtime(1);
        if ( \is_array($key) )
        {
            # redis多取
            $return = $this->_redis->mget($key);

            foreach ( $return as &$item )
            {
                static::_de_format_data($item);
            }
        }
        else
        {
            $return = $this->_redis->get($key);
            static::_de_format_data($return);
        }
        $time = \microtime(1) - $time;

        if ( false===$return )
        {
            \Core::debug()->error($key,'redis mis key');
            \Core::debug()->info($time,'use time');

            return false;
        }
        else
        {
            \Core::debug()->info($key,'redis hit key');
            \Core::debug()->info($time,'use time');
        }

        return $return;
    }

    /**
     * 存数据，支持多存
     *
     * @param string/array $key
     * @param $data Value 多存时此项可空
     * @param $lifetime 有效期，默认3600，即1小时，0表示最大值30天（2592000）
     * @return boolean
     */
    public function set($key, $value = null, $lifetime = 3600)
    {
        $this->_connect();
        \Core::debug()->info($key,'redis set key');

        if ( \is_array($key) )
        {
            foreach ($key as & $item)
            {
                static::_format_data($item);
            }
            return $this->_redis->mset($key);
        }
        else
        {
            static::_format_data($value);
            return $this->_redis->set($key, $value, $lifetime);
        }
    }

    /**
     * 删除指定key的缓存，若$key===true则表示删除全部
     *
     * @param string $key
     */
    public function delete($key)
    {
        $this->_connect();
        if ( $key === true )
        {

            return $this->_redis->flushAll();
        }
        else
        {
            $keys = func_get_args();
            return $this->_redis->delete($keys);
        }
    }

    /**
     * 删除全部
     */
    public function delete_all()
    {
        return $this->delete(true);
    }

    /**
     * 过期数据会自动清除
     *
     */
    public function delete_expired()
    {
        return true;
    }

    /**
     * 递减
     * 与原始decrement方法区别的是若不存指定KEY时返回false，这个会自动递减
     *
     * @param string $key
     * @param int $offset
     */
    public function decrement($key, $offset = 1)
    {
        return $this->_redis->decrBy($key, $offset);
    }

    /**
     * 递增
     * 与原始increment方法区别的是若不存指定KEY时返回false，这个会自动递增
     *
     * @param string $key
     * @param int $offset
     */
    public function increment($key, $offset = 1)
    {
        return $this->_redis->incrBy($key, $offset);
    }

    public function __call($method, $params)
    {
        $this->_connect();

        if ( \method_exists($this->_redis, $method) )
        {
            return \call_user_func_array(array($this->_redis,$method), $params);
        }
    }

    protected static function _de_format_data( &$data )
    {
        if ( null===$data || \is_bool($data) )
        {
            # bool类型不处理
        }
        elseif ( !\is_numeric($data) )
        {
            $data = @\unserialize($data);
        }
    }

    protected static function _format_data( &$data )
    {
        if ( !\is_numeric($data) )
        {
            $data = \serialize($data);
        }
    }

    /**
     * 关闭所有链接
     */
    public static function close_all_connect()
    {
        foreach ( static::$redis as $config_name=>$obj )
        {
            try
            {
                $obj->close();
            }
            catch (\Exception $e)
            {
                \Core::debug()->error('close redis connect error:'.$e);
            }

            static::$redis[$config_name] = null;
        }

        # 重置全部数据
        static::$redis = array();
        static::$redis_num = array();

        if (\IS_DEBUG)\Core::debug()->info('close all redis server.');
    }
}
