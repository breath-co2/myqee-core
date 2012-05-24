<?php
namespace Core;

/**
 * 存储处理核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Storage
{
    /**
     * 当前配置
     *
     * @var array
     */
    protected $config;

    /**
     * @var array Storage instances
     */
    protected static $instances = array();

    public function __construct($config_name='default')
    {

    }

    /**
     * 返回数据库实例化对象
     *
     * @param string $config_name
     * @return \Database
     */
    public static function instance($config_name = 'default')
    {
        if (\is_string($config_name))
        {
            $i_name = $config_name;
        }
        else
        {
            $i_name = '.config_'.\md5(\serialize($config_name));
        }

        if (!isset(static::$instances[$i_name]))
        {
            static::$instances[$i_name] = new \Storage($config_name);
        }
        return static::$instances[$i_name];
    }


}