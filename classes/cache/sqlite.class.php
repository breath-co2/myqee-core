<?php
namespace Core;

/**
 * SQLite缓存驱动器
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Cache
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Cache_SQLite extends \Cache_Database
{
    /**
     * 默认缓存时间
     *
     * @var int
     */
    const DEFAULT_CACHE_TIME = 3600;

    /**
     * Memcache缓存驱动器
     * @param $config_name 配置名或数组
     */
    public function __construct($config_name = 'default')
    {
        $connection = array
        (
            'db'         => ':memory:',
            'table'      => 'sharedmemory',
            'expire'     => static::DEFAULT_CACHE_TIME,
            'persistent' => false,
            'length'     => 0,
        );

        if ( \is_array($config_name) )
        {
            $connection += $config_name;
            $config_name = \md5(\serialize($config_name));
        }
        else
        {
            $connection += \Core::config('cache/sqlite.' . $config_name);
        }

        if ( static::DATA_COMPRESS && \function_exists('gzcompress') )
        {
            $this->_compress = true;
        }

        $this->_handler = new \Database(array('type'=>'SQLite','connection'=>$connection));

        $this->tablename = $connection['tablename'];
    }
}