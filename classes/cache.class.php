<?php
namespace Core;

/**
 * 缓存核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Cache
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Cache
{
    /**
     * 驱动类型为memcache
     *
     * @var string
     */
    const DRIVER_MEMCACHE = 'Memcache';

    /**
     * 驱动类型为文件
     *
     * @var string
     */
    const DRIVER_FILE = 'File';

    /**
     * 最大时效类型
     *
     * @var string
     */
    const TYPE_MAX_AGE = 'age';

    /**
     * 最大命中数类型
     *
     * @var string
     */
    const TYPE_MAX_HIT = 'hit';

    /**
     * 高级时效缓存类型
     *
     * 缓存类型为在有效时间范围内介于某个时间随机更新
     * @example $this->set('key','value','200~250,1/100',static::TYPE_RENEW_AGE); 表示介于200～250秒之间时命中率为1/100，若命中则更新缓存
     * @var string
     */
    const TYPE_ADV_AGE = 'renew_age';

    /**
     * 高级命中数缓存类型
     *
     * 缓存类型为介于某个数值内的命中更新方式
     * @var string
     */
    const TYPE_ADV_HIT = 'renew_hit';

    /**
     * 实例化对象
     *
     * @var array
     */
    protected static $instances = array();

    /**
     * 当前缓存的配置
     *
     * @var string
     */
    protected $config;

    /**
     * 缓存驱动对象
     *
     * @var \Cache\Driver\Memcache
     */
    protected $driver;

    /**
     * 是否为session模式
     *
     * @var boolean
     */
    protected $session_mode = false;

    /**
     * @return \Cache
     */
    public static function instance($name = 'default')
    {
        if ( \is_array($name) )
        {
            $config_name = '_tmp_' . \md5(\serialize($name));
        }
        else
        {
            $config_name = $name;
        }
        if ( ! isset(static::$instances[$config_name]) )
        {
            static::$instances[$config_name] = new Cache($name);
        }
        return static::$instances[$config_name];
    }

    public function __construct($name = 'default')
    {
        if ( \is_array($name) )
        {
            $this->config = $name;
        }
        else
        {
            $this->config = \Core::config('cache.' . $name);
        }
        if ( ! isset($this->config['prefix']) )
        {
            $this->config['prefix'] = '';
        }

        if (strtolower($this->config['driver'])=='default')$this->config['driver'] = 'Default_Driver';

        $driver = '\\Cache\\Driver\\' . $this->config['driver'];
        if (!\class_exists($driver,true))
        {
            throw new \Exception('指定的缓存驱动' . $driver . '不存在！');
        }

        $this->driver = new $driver($this->config['driver_config']);
    }

    /**
     * 获取指定KEY的缓存数据
     *
     * get('a');
     * get('a','b','c');
     * get(array('a','b','c'));
     *
     * @param string $key 指定key
     * @return mixed
     */
    public function get($key)
    {
        static $is_no_cache = null;
        if ( null === $is_no_cache )
        {
            $is_no_cache = true === \Core::debug()->profiler('nocached')->is_open();
        }
        if ( $is_no_cache && !$this->session_mode )
        {
            return false;
        }

        $columns = \func_get_args();
        if ( \count($columns) > 1 )
        {
            $key = $columns;
        }
        if ( null === $key )
        {
            return false;
        }
        if ( $this->config['prefix'] )
        {
            # 加前缀
            if (\is_array($key))
            {
                foreach ($key as &$item)
                {
                    $item = $this->config['prefix'] . $item;
                }
            }
            else
            {
                $key = $this->config['prefix'] . $key;
            }
        }

        try
        {
            $data = $this->driver->get($key);

            if ( \is_array($data) )
            {
                foreach ( $data as &$item )
                {
                    if ( \is_string($item) )
                    {
                        $this->_get_adv_data($item);
                    }
                }
            }
            elseif ( \is_string($data) )
            {
                $this->_get_adv_data($data);
            }

            return $data;
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * 设置指定key的缓存数据
     *
     * $expire_type默认有4种类型，分别为：
     *
     * * $expire_type = \Cacah::TYPE_MAX_AGE 最长时间，当指定的$expire达到时间后，缓存失效，默认方式
     * * $expire_type = \Cacah::TYPE_MAX_HIT 最大命中数，当get()请求数量达到$expire值后，缓存失效
     * * $expire_type = \Cacah::TYPE_ADV_AGE 高级时效类型，此类型时，传入的$expire可以类似：200~250,1/100，其中200~250表示介于这个时间（单位秒）内时，在1/100请求几率下会失效，其它99/100请求不会失效，并且250为临界时间，超过这个时间将等同TYPE_MAX_AGE方式处理。它的主要用途是在高并发的情况下，避免因缓存失效而集中需要更新导致重复加载。
     * * $expire_type = \Cacah::TYPE_ADV_HIT 高级命中类型，此类型基本同上，只是$expire前的数值表示为请求数
     *
     * @example $this->set('key','value','200~250,1/100',static::TYPE_RENEW_AGE); 表示介于200～250秒之间时命中率为1/100，若命中则更新缓存
     *
     * @param string/array $key 可以同时设置多个
     * @param fixed $value
     * @param int/string $expire 失效时间或命中数，0表示最大有效时间
     * @param string $expire_type 失效类型
     * @return boolean 是否成功
     */
    public function set($key, $value = null, $expire = 3600, $expire_type = null)
    {
        if ( $this->config['prefix'] )
        {
            # 加前缀
            if ( \is_array($key) )
            {
                $tmpcopy = array();
                foreach ( $key as $k => $v )
                {
                    $tmpcopy[$this->config['prefix'] . $k] = $v;
                }
                $key = $tmpcopy;
            }
            else
            {
                $key = $this->config['prefix'] . $key;
            }
        }

        if ( $expire_type && $expire_type!=static::TYPE_MAX_AGE )
        {
            $this->_check_adv_data($key, $value, $expire, $expire_type);
        }
        elseif ( \strpos($expire, '~') && \preg_match('#^([0-9]+)~([0-9]+),([0-9]+)/([0-9]+)$#', $expire, $match_exp) )
        {
            $expire = (int)$match_exp[1];
        }
        else
        {
            $expire = (int)$expire;
        }

        try
        {
            return $this->driver->set($key, $value, $expire);
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * 删除指定key的缓存数据
     * @param string $key
     * @param fiexd $value
     */
    public function delete($key)
    {
        if ( true !== $key && $this->config['prefix'] )
        {
            # 加前缀
            if ( \is_array($key) )
            {
                foreach ( $key as &$item )
                {
                    $item = $this->config['prefix'] . $item;
                }
            }
            else
            {
                $key = $this->config['prefix'] . $key;
            }
        }
        try
        {
            return $this->driver->delete($key);
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * 删除全部缓存
     *
     * delete_all()的别名
     *
     * @return boolean
     */
    public function clean()
    {
        return $this->delete_all();
    }

    /**
     * 删除全部缓存
     *
     * @return boolean
     */
    public function delete_all()
    {
        try
        {
            return $this->driver->delete_all();
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * 删除过期数据
     * @return boolean
     */
    public function delete_expired()
    {
        try
        {
            return $this->driver->delete_expired();
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * 递减
     *
     * 与原始decrement方法区别的是若memcache不存指定KEY时返回false，这个会自动递减
     *
     * @param string $key
     * @param int $offset
     * @param int $lifetime 当递减失则时当作set使用
     */
    public function decrement($key, $offset = 1, $lifetime = 60)
    {
        try
        {
            $key = $this->config['prefix'] . $key;
            return $this->driver->decrement($key, $offset, $lifetime);
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    /**
     * 递增
     *
     * 与原始increment方法区别的是若memcache不存指定KEY时返回false，这个会自动递增
     *
     * @param string $key
     * @param int $offset
     * @param int $lifetime 当递减失则时当作set使用
     */
    public function increment($key, $offset = 1, $lifetime = 60)
    {
        try
        {
            $key = $this->config['prefix'] . $key;
            return $this->driver->increment($key, $offset, $lifetime);
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    public function __unset($key)
    {
        $this->delete($key);
    }

    public function __call($method, $params)
    {
        return \call_user_func_array($method, $this->driver, $params);
    }

    /**
     * 格式化set数据
     *
     * @param string/array $key
     * @param mixed $value
     * @param string $type
     * @return boolean/string $exp_key
     */
    protected function _check_adv_data(& $key, & $value, & $expire, $type)
    {
        if ( \is_array($key) )
        {
            foreach ( $key as $k => &$v )
            {
                $exp_key = $this->_check_adv_data($k, $v, $expire, $type);
                if ( \is_string($exp_key) && \is_array($k) )
                {
                    # 原来的$k是字符串，若变成了数组则表示需要增加一个计数器
                    $key[$exp_key] = 0;
                }
            }
        }
        else
        {
            # 产生一个随机key
            $exp_key = \md5(\microtime(true) . \mt_rand(100000000, 999999999));

            # 修正
            if ( \preg_match('#^([0-9]+)~([0-9]+),([0-9]+)/([0-9]+)$#', $expire, $match_exp) )
            {
                $lifestr = $expire;
            }
            else
            {
                $lifestr = ((int)$expire / 2) . '~' . $expire . ',1/100';
            }
            $value = '__::foRMat_Cache::Type=' . $type . ',ExpKey=' . $exp_key . ',Exp=' . $lifestr . ',SaveTime=' . \TIME . ',Value=' . \serialize($value);

            if ( $type == static::TYPE_ADV_HIT || $type == static::TYPE_MAX_HIT )
            {
                # 此类型需要增加计数器
                if ( \is_array($key) )
                {
                    $key[$exp_key] = 0;
                }
                else
                {
                    $key = array($key => $value, $exp_key => 1);
                }
            }

            $expire = $match_exp[1];

            return $exp_key;
        }
    }

    protected function _get_adv_data(&$value)
    {
        if ( \substr($value, 0, 18) == '__::foRMat_Cache::' && \preg_match('#^__::foRMat_Cache::Type=(?P<type>[a-z0-9_]+),ExpKey=(?P<expkey>[a-f0-9]{32}),Exp=(?P<exp>[0-9,~/]+),SaveTime=(?P<savetime>[0-9]+),Value=(?P<value>.*)$#', $value, $match) )
        {
            #200~250,1/100
            if ( ! \preg_match('#^([0-9]+)~([0-9]+),([0-9]+)/([0-9]+)$#', $match['exp'], $match_exp) )
            {
                return true;
            }

            switch ( $match['type'] )
            {
                case static::TYPE_ADV_HIT :
                case static::TYPE_MAX_HIT :
                    # 获取命中统计数
                    $exp = $this->driver->get($match['expkey']);
                    break;
                case static::TYPE_ADV_AGE :
                case static::TYPE_MAX_AGE :
                default :
                    $exp = \TIME - $match['savetime'];
                    break;
            }

            if ( $exp >= $match_exp[0] && $exp <= $match_exp[1] )
            {
                # 在指定范围内按比例更新
                $rand = \mt_rand(1, $match_exp[3]);
                if ( $rand <= $match_exp[2] )
                {
                    # 命中，则清除数据，让程序可主动更新
                    $value = null;
                    return;
                }
            }
            elseif ( $exp > $match_exp[1] )
            {
                # 强制认为没有获取数据
                $value = null;
                return;
            }

            if ( $match['type'] == static::TYPE_ADV_HIT || $match['type'] == static::TYPE_MAX_HIT )
            {
                # 计数器增加
                $this->driver->increment($match['expkey'], 1, $match_exp[1]);
            }

            $value = @\unserialize($match['value']);
        }
    }

    /**
     * 设置当前为是否为Session获取模式
     *
     * 设置为session模式后，在开启debug情况下访问无缓存状态将不受影响
     *
     * @param boolean $open
     * @return Cache
     */
    public function session_mode($open)
    {
        $this->session_mode = (boolean)$open;
    }
}