<?php
namespace Core;

/**
 * Session核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Session
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Session
{
    /**
     * @var Session
     */
    protected static $instance;

    // Protected key names (cannot be set by the user)
    protected static $protect = array(
    	'SID' => 1,
    	'_flash_session_' => 1
    );

    public static $config;

    protected static $flash;

    /**
     * Session驱动
     * @var Session_Driver_Default
     */
    protected $driver;

    /**
     * @var Member
     */
    protected static $member;

    /**
     * @return Session
     */
    public static function instance()
    {
        if ( static::$instance == null )
        {
            // Create a new instance
            new \Session();
        }
        return static::$instance;
    }

    /**
     * On first session instance creation, sets up the driver and creates session.
     */
    public function __construct($vars = null)
    {
        // This part only needs to be run once
        if ( static::$instance === null )
        {
            // Load config
            static::$config = \Core::config('session');

            if ( ! isset(static::$config['name']) || ! \preg_match('#^(?=.*[a-z])[a-z0-9_]++$#iD', static::$config['name']) )
            {
                // Name the session, this will also be the name of the cookie
                static::$config['name'] = 'PHPSESSINID';
            }

            if (\strtolower(static::$config['driver'])=='default')static::$config['driver'] = 'Default_Driver';

            if ( isset(static::$config['driver']) && \class_exists('\\Session\\' . static::$config['driver'], true) )
            {
                $driver_name = '\\Session\\' . static::$config['driver'];
                if ( isset(static::$config['driver_config']) )
                {
                    $this->driver = new $driver_name(static::$config['driver_config']);
                }
                else
                {
                    $this->driver = new $driver_name();
                }
            }
            else
            {
                $this->driver = new \Session\Default_Driver();
            }

            if ( $vars )
            {
                // Set the new data
                $this->set($vars);
            }

            if ( ! isset($_SESSION['_flash_session_']) )
            {
                $_SESSION['_flash_session_'] = array();
            }
            static::$flash = & $_SESSION['_flash_session_'];

            # 清理Flash Session
            $this->expire_flash();

            $_SESSION['SID'] = $this->driver->session_id();

            # 确保关闭前执行保存
            \Core::register_shutdown_function(array('Session', 'write_close'));

            static::$instance = $this;

            if ( null===static::$member && isset($_SESSION['member']) )
            {
                static::$member = new \Member($_SESSION['member']);
            }
        }
    }

    /**
     * 开启SESSION
     * @return Session
     */
    public function start()
    {
        return $this;
    }

    /**
     * Get the session id.
     *
     * @return  string
     */
    public function id()
    {
        return $_SESSION['SID'];
    }

    /**
     * 銷毀當前Session
     *
     * @return  void
     */
    public function destroy()
    {
        $this->driver->destroy();
    }

    /**
     * 设置用户
     *
     * @param Member $member
     * @return Session
     */
    public function set_member(Member $member)
    {
        static::$member = $member;
        if ( $member->id>0 )
        {
            # 设置用户数据
            $member_data = $member->get_field_data();
            $_SESSION['member'] = $member_data;
        }
        else
        {
            # 游客数据则清空
            unset($_SESSION['member']);
        }
        return $this;
    }

    /**
     * 返回当前用户id
     *
     * @return int
     */
    public function member_id()
    {
        return $_SESSION['member']['id'];
    }

    /**
     * 获取用户对象
     *
     * @return Member
     */
    public function member()
    {
        if ( null===static::$member )
        {
            # 创建一个空的用户对象
            static::$member = new \Member();
        }
        return static::$member;
    }

    public function last_actived_time()
    {
        if ( !isset($_SESSION['_last_actived_time_']) )
        {
            $_SESSION['_last_actived_time_'] = \TIME;
        }
        return $_SESSION['_last_actived_time_'];
    }

    /**
     * 此方法用于保存session数据
     * 只执行一次，系统在关闭前会执行
     *
     * @return  void
     */
    public static function write_close()
    {
        if ( null === static::$instance )
        {
            return false;
        }
        static $run = null;
        if ( $run === null )
        {
            $run = true;

            if ( ! $_SESSION['_flash_session_'] )
            {
                unset($_SESSION['_flash_session_']);
            }

            if ( static::$member )
            {
                # 设置用户数据
                $member_data = static::$member->get_field_data();

                $_SESSION['member'] = $member_data;
            }

            if ( !isset($_SESSION['_last_actived_time_']) || \TIME - 600 > $_SESSION['_last_actived_time_'] )
            {
                # 更新最后活动时间 10分钟更新一次
                $_SESSION['_last_actived_time_'] = \TIME;
            }

            static::$instance->driver->write_close();
        }
    }

    /**
     * Set a session variable.
     *
     * @param   string|array  key, or array of values
     * @param   mixed		 value (if keys is not an array)
     * @return  void
     */
    public function set($keys, $val = false)
    {
        if ( empty($keys) ) return false;

        if ( ! \is_array($keys) )
        {
            $keys = array($keys => $val);
        }

        foreach ( $keys as $key => $val )
        {
            if ( isset(static::$protect[$key]) ) continue;

            // Set the key
            $_SESSION[$key] = $val;
        }
    }

    /**
     * Set a flash variable.
     *
     * @param   string|array  key, or array of values
     * @param   mixed		 value (if keys is not an array)
     * @return  void
     */
    public function set_flash($keys, $val = false)
    {
        if ( empty($keys) ) return false;

        if ( !\is_array($keys) )
        {
            $keys = array($keys => $val);
        }

        foreach ( $keys as $key => $val )
        {
            if ( $key == false ) continue;

            static::$flash[$key] = 'new';
            $this->set($key, $val);
        }
    }

    /**
     * Freshen one, multiple or all flash variables.
     *
     * @param   string  variable key(s)
     * @return  void
     */
    public function keep_flash($keys = null)
    {
        $keys = ($keys === null) ? \array_keys(static::$flash) : \func_get_args();

        foreach ( $keys as $key )
        {
            if ( isset(static::$flash[$key]) )
            {
                static::$flash[$key] = 'new';
            }
        }
    }

    /**
     * Expires old flash data and removes it from the session.
     *
     * @return  void
     */
    protected function expire_flash()
    {
        if ( ! empty(static::$flash) )
        {
            foreach ( static::$flash as $key => $state )
            {
                if ( $state === 'old' )
                {
                    // Flash has expired
                    unset(static::$flash[$key], $_SESSION[$key]);
                }
                else
                {
                    // Flash will expire
                    static::$flash[$key] = 'old';
                }
            }
        }
    }

    /**
     * Get a variable. Access to sub-arrays is supported with key.subkey.
     *
     * @param   string  variable key
     * @param   mixed   default value returned if variable does not exist
     * @return  mixed   Variable data if key specified, otherwise array containing all session data.
     */
    public function get($key = false, $default = false)
    {
        if ( empty($key) ) return $_SESSION;

        $result = isset($_SESSION[$key]) ? $_SESSION[$key] : \Core::key_string($_SESSION, $key);

        return ($result === null) ? $default : $result;
    }

    /**
     * Get a variable, and delete it.
     *
     * @param   string  variable key
     * @param   mixed   default value returned if variable does not exist
     * @return  mixed
     */
    public function get_once($key, $default = false)
    {
        $return = $this->get($key, $default);
        $this->delete($key);

        return $return;
    }

    /**
     * Delete one or more variables.
     *
     * @param   string  variable key(s)
     * @return  void
     */
    public function delete()
    {
        $args = \func_get_args();

        foreach ( $args as $key )
        {
            if (isset(static::$protect[$key])) continue;

            // Unset the key
            unset($_SESSION[$key]);
        }
    }

    public static function session_name()
    {
        return static::$config['name'];
    }
}