<?php
namespace Core\Session;

/**
 * Session默认驱动器核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Session
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Default_Driver
{

    public function __construct()
    {
        // Configure garbage collection
        @\ini_set('session.gc_probability', (int)\Session::$config['gc_probability']);
        @\ini_set('session.gc_divisor', 100);
        @\ini_set('session.gc_maxlifetime', (\Session::$config['expiration'] == 0) ? 86400 : \Session::$config['expiration']);

        $this->create();
    }

    /**
     * 创建Session
     *
     * @param   array  variables to set after creation
     * @return  void
     */
    public function create()
    {
        if ( \preg_match('#^(?=.*[a-z])[a-z0-9_]++$#iD', \Session::$config['name']) )
        {
            // Name the session, this will also be the name of the cookie
            \session_name(\Session::$config['name']);
        }
        $this->destroy();

        $cookieconfig = \Core::config('cookie');
        // Set the session cookie parameters
        \session_set_cookie_params(\Session::$config['expiration'], $cookieconfig['path'], $cookieconfig['domain'], $cookieconfig['secure'], $cookieconfig['httponly']);

        // Start the session!
        \session_start();
    }

    /**
     * 获取SESSION ID
     */
    public function session_id()
    {
        return \session_id();
    }

    /**
     * 回收当前Session
     *
     * @return  void
     */
    public function destroy()
    {
        if ( \session_id()!=='' )
        {
            // Get the session name
            $name = \session_name();

            // Destroy the session
            \session_destroy();

            // Re-initialize the array
            $_SESSION = array();

            // Delete the session cookie
            \Cookie::delete($name,'/');
        }
    }

    /**
     * 保存Session数据
     *
     * @return  void
     */
    public function write_close()
    {
        \session_write_close();
    }
}