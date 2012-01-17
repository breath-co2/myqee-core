<?php
namespace Core\Cache;

/**
 * Memcache缓存驱动器核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Cache
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Memcache
{

    /**
     * Memcache链接对象
     * @var array
     */
    protected static $memcaches = array();

    /**
     * 记录$memcaches对象被引用数
     * @var array
     */
    protected static $memcaches_num = array();

    /**
     * Memcache对象
     * @var Memcache
     */
    protected $_memcache;

    protected $servers = array();

    /**
     * 当前配置名
     * @var string
     */
    protected $config_name;

    protected static $_memcached_mode = null;

    /**
     * Memcache缓存驱动器
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
            $this->servers = \Core::config('cache.memcache.' . $config_name);
        }
        if ( ! \is_array($this->servers) )
        {
            throw new \Exception('指定的' . $config_name . 'Memcache缓存配置不存在.');
        }
        $this->config_name = $config_name;

        $this->_connect();

        # 增加自动关闭连接列队
        \Core::add_close_connect_class('\\Cache\\Memcache');
    }

    public function __destruct()
    {
        $this->close_connect();
    }

    /**
     * 连接memcache服务器
     */
    protected function _connect()
    {
        if ( $this->_memcache )return;
        if ( !$this->config_name )return;

        $config_name = $this->config_name;

        if ( !isset(static::$memcaches[$config_name]) )
        {

            if ( null === static::$_memcached_mode )
            {
                # 优先采用memcached扩展
                if ( \extension_loaded('memcached') )
                {
                    static::$_memcached_mode = true;
                }
                elseif ( \extension_loaded('memcache') )
                {
                    static::$_memcached_mode = false;
                }
                else
                {
                    throw new \Exception('系统没有加载Memcached或Memcache扩展.');
                }
            }
            if ( static::$_memcached_mode )
            {
                $memcache = 'memcached';
            }
            else
            {
                $memcache = 'memcache';
            }

            static::$memcaches[$config_name] = new $memcache();
            static::$memcaches_num[$config_name] = 0;

            if ( static::$_memcached_mode )
            {
                static::$memcaches[$config_name]->addServers($this->servers);
            }
            else
            {
                $failure_addserver = function($host, $port, $udp, $info, $code)
                {
                    \Core::debug()->error('memcached server failover:' . ' host: ' . $host . ' port: ' . $port . ' udp: ' . $udp . ' info: ' . $info . ' code: ' . $code);
                };

                foreach ( $this->servers as $server )
                {
                    $server += array('host' => '127.0.0.1', 'port' => 11211, 'persistent' => true);

                    static::$memcaches[$config_name]->addServer($server['host'], $server['port'], (bool)$server['persistent'], $server['weight'], 1, 15, true, $failure_addserver);

                    if (\IS_DEBUG)\Core::debug()->info('add memcached server '.$server['host'].':'.$server['port']);
                }
            }
        }

        # 断开引用关系
        unset($this->_memcache);

        # 设置memcache
        $this->_memcache = &static::$memcaches[$config_name];

        static::$memcaches_num[$config_name]++;
    }

    /**
     * 关闭memcache连接
     */
    public function close_connect()
    {
        if ( $this->config_name && $this->_memcache )
        {
            unset($this->_memcache);
            static::$memcaches_num[$this->config_name]--;

            if ( 0 == static::$memcaches_num[$this->config_name] )
            {
                @static::$memcaches[$this->config_name]->close();

                if (\IS_DEBUG)\Core::debug()->info('close memcached server.');

                static::$memcaches[$this->config_name] = null;
                unset(static::$memcaches[$this->config_name]);
                unset(static::$memcaches_num[$this->config_name]);
            }
        }
    }

    /**
     * 取得memcache数据，支持批量取
     * @param string/array $key
     * @return mixed
     */
    public function get($key)
    {
        $this->_connect();
        if ( static::$_memcached_mode && \is_array($key) )
        {
            # memcached多取
            $return = $this->_memcache->getMulti($key);
        }
        else
        {
            $return = $this->_memcache->get($key);
        }

        if ( $return === false )
        {
            \Core::debug()->error('memcached mis key=' . $key);
            return false;
        }
        else
        {
            \Core::debug()->info('memcached hit key=' . $key);
        }
        return $return;
    }

    /**
     * 给memcache存数据
     *
     * @param string/array $key 支持多存
     * @param $data Value 多存时此项可空
     * @param $lifetime 有效期，默认3600，即1小时，0表示最大值30天（2592000）
     * @return boolean
     */
    public function set($key, $value = null, $lifetime = 3600)
    {
        $this->_connect();
        \Core::debug()->info('memcached set key=' . $key);

        if ( static::$_memcached_mode )
        {
            // memcached
            if ( \is_array($key) )
            {
                return $this->_memcache->setMulti($key, $lifetime);
            }
            return $this->_memcache->set($key, $value, $lifetime);
        }
        else
        {
            // memcache
            if ( \is_array($key) )
            {
                $return = true;
                foreach ( $key as $k => $v )
                {
                    $s = $this->set($k, $v, $lifetime);
                    if ( false === $s )
                    {
                        $return = false;
                    }
                }
                return $return;
            }

            return $this->_memcache->set($key, $value, $this->_get_flag($value), $lifetime);
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
            if ( static::$_memcached_mode )
            {
                $status = $this->_memcache->flush(1);
            }
            else
            {
                $status = $this->_memcache->flush();
                if ( $status )
                {
                    // We must sleep after flushing, or overwriting will not work!
                    // @see http://php.net/manual/en/function.memcache-flush.php#81420
                    \sleep(1);
                }
            }

            return $status;
        }
        else
        {
            return $this->_memcache->delete($key);
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
     * 与原始decrement方法区别的是若memcache不存指定KEY时返回false，这个会自动递减
     *
     * @param string $key
     * @param int $offset
     * @param int $lifetime 当递减失则时当作set使用
     */
    public function decrement($key, $offset = 1, $lifetime = 60)
    {
        if ( $this->_memcache->decrement($key, $offset) )
        {
            return true;
        }
        elseif ( $this->get($key) === null && $this->set($key, $offset, $lifetime) )
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 递增
     * 与原始increment方法区别的是若memcache不存指定KEY时返回false，这个会自动递增
     *
     * @param string $key
     * @param int $offset
     * @param int $lifetime 当递减失则时当作set使用
     */
    public function increment($key, $offset = 1, $lifetime = 60)
    {
        if ( $this->_memcache->increment($key, $offset) )
        {
            return true;
        }
        elseif ( $this->get($key) === null && $this->set($key, $offset, $lifetime) )
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    protected function _get_flag($data)
    {
        if ( \is_int($data) || \is_float($data) )
        {
            return false;
        }
        else if ( \is_string($data) || \is_array($data) || \is_object($data) )
        {
            return \MEMCACHE_COMPRESSED;
        }
        else
        {
            return false;
        }
    }

    public function __call($method, $params)
    {
        $this->_connect();

        if ( \method_exists($this->_memcache, $method) )
        {
            return \call_user_func_array($method, $this->_memcache, $params);
        }
    }

    /**
     * 关闭所有memcache链接
     */
    public static function close_all_connect()
    {
        foreach ( static::$memcaches as $config_name=>$memcache )
        {
            try
            {
                $memcache->close();
            }
            catch (\Exception $e)
            {
                \Core::debug()->error('close memcached connect error:'.$e);
            }

            static::$memcaches[$config_name] = null;
        }

        # 重置全部数据
        static::$memcaches = array();
        static::$memcaches_num = array();

        if (\IS_DEBUG)\Core::debug()->info('close all memcached server.');
    }
}