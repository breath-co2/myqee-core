<?php
namespace Core\Cache;

/**
 * 数据库缓存驱动器核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Cache
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Database
{
    /**
     * 数据库配置
     *
     * @var string
     */
    protected $database;

    /**
     * 缓存表名称
     *
     * @var string
     */
    protected $tablename;

    /**
     * 数据库对象
     *
     * @var \Database
     */
    protected $db;

    public function __construct($config_name = 'default')
    {
        if ( \is_array($config_name) )
        {
            $config = $config_name;
        }
        else
        {
            $config = \Core::config('cache.database.'.$config_name);
        }

        $this->database = $config['database'];
        $this->tablename = $config['tablename'];

        if ( !$this->tablename )
        {
            throw new \Exception('数据库缓存配置错误。');
        }
    }

    public function __destruct()
    {

    }

    /**
     * 取得数据，支持批量取
     * @param string/array $key
     * @return mixed
     */
    public function get($key)
    {
        $this->db->from($this->tablename);

        if ( \is_array($key) )
        {
            $key = \array_map('md5', $key);
            $this->db->in('key',$key);
        }
        else
        {
            $key = \md5($key);
            $this->db->where('key',$key);
        }
        $data = $this->db->get()->as_array();

        if ( \is_array($key) )
        {
            $new_data = array();
            foreach ( $data as $item )
            {
                $new_data[$item['key_str']] = $item['value'];
            }
            return $new_data;
        }
        else
        {
            return $data[0];
        }
    }

    /**
     * 存数据
     *
     * @param string/array $key 支持多存
     * @param $data Value 多存时此项可空
     * @param $lifetime 有效期，默认3600，即1小时，0表示最大值30天（2592000）
     * @return boolean
     */
    public function set($key, $value = null, $lifetime = 3600)
    {
        if ( !\is_array($key) )
        {
            $key = array(
                $key => $value,
            );
        }

        foreach ($key as $k=>$item)
        {
            $data = array(
                'key'         => \md5($k),
                'key_str'     => $k,
                'value'       => $value,
                'expire_time' => $lifetime>0?\TIME+$lifetime:0,
            );
            $this->db->values($data);
        }

        try
        {
            $this->db->insert($this->tablename);

            return true;
        }
        catch (\Exception $e)
        {
            \Core::debug()->error($e->getMessage());

            return false;
        }
    }

    /**
     * 删除指定key的缓存，若$key===true则表示删除全部
     *
     * @param string $key
     */
    public function delete($key)
    {
        if ( true!==$key )
        {
            $this->db->where('key',$key);
        }
        elseif (\is_array($key))
        {
            $this->db->in('key',$key);
        }

        try{
            $this->db->delete($this->tablename);

            return true;
        }
        catch (\Exception $e)
        {
            \Core::debug()->error($e->getMessage());

            return false;
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
     * 删除过期数据
     *
     */
    public function delete_expired()
    {
        try{
            $this->db->where('expire_time',0,'>')->where('expire_time',\TIME,'<=')->delete($this->tablename);

            return true;
        }
        catch (\Exception $e)
        {
            \Core::debug()->error($e->getMessage());

            return false;
        }
    }

    /**
     * 递减
     *
     * @param string $key
     * @param int $offset
     * @param int $lifetime 当递减失则时当作set使用
     */
    public function decrement($key, $offset = 1, $lifetime = 60)
    {
        return $this->increment($key, -$offset, $lifetime);
    }

    /**
     * 递增
     *
     * @param string $key
     * @param int $offset
     * @param int $lifetime 当递减失则时当作set使用
     */
    public function increment($key, $offset = 1, $lifetime = 60)
    {
        $k = \md5($key);

        # 首先尝试递增
        $s = $this->db->value_increment('value',$offset)->where('key',$k)->update($this->tablename);
        if ( !$s )
        {
            # 没有更新到数据，尝试插入数据
            return $this->set($key,$offset,$lifetime);
        }

        return false;
    }
}