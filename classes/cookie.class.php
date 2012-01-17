<?php
namespace Core;

/**
 * Cookie核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Cookie
{

    /**
     * cookie的配置
     * @var array
     */
    protected static $config = array();

    public function __construct()
    {

    }

    protected function init()
    {
        static $run = null;
        if ($run)return;

        $run = true;

        static::$config = \Core::config('cookie');
    }

    public static function get($name)
    {
        static::init();

        if ( isset(static::$config['prefix']) && static::$config['prefix'] ) $name = static::$config['prefix'] . $name;

        return $_COOKIE[$name];
    }

    /**
     * 创建cookie 详细请参考setcookie函数参数
     *
     * @param string/array $name
     * @param string $value
     * @param number $expire
     * @param string $path
     * @param string $domain
     * @param boolean $secure
     * @param boolean $httponly
     * @return boolean true/false
     */
    public static function set($name, $value = null, $expire = null, $path = null, $domain = null, $secure = null, $httponly = null)
    {
        if (\headers_sent())return false;

        static::init();

        // If the name param is an array, we import it
        \is_array($name) && \extract($name, \EXTR_OVERWRITE);

        // Fetch default options


        foreach ( array('value', 'expire', 'domain', 'path', 'secure', 'httponly', 'prefix') as $item )
        {
            if ( $$item===null && isset(static::$config[$item]) )
            {
                $$item = static::$config[$item];
            }
        }

        static::$config['prefix'] && $name = static::$config['prefix'] . $name;

        // Expiration timestamp
        $expire = ($expire == 0) ? 0 : $_SERVER['REQUEST_TIME'] + (int)$expire;

        return \setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * 删除cookie
     *
     * @param string $name cookie名称
     * @param string $path cookie路径
     * @param string $domain cookie作用域
     * @return boolean true/false
     */
    public static function delete($name, $path = null, $domain = null)
    {
        return static::set($name, '', -864000, $path, $domain, false, false);
    }
}