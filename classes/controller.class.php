<?php
namespace Core;

/**
 * 控制器核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Controller
{
    /**
     * 请求参数
     *
     * @var array
     */
    public $arguments;

    /**
     * 控制器
     *
     * @var string
     */
    public $controller;

    /**
     * 方法
     *
     * @var string
     */
    public $action;

    public function __construct()
    {

    }

    /**
     * 用于给系统调用设置对象变量，系统内部调用
     *
     * @param array $data
     */
    public function _callback_set_vars($data)
    {
        # 将路由信息传入到控制器变量中
        foreach ( $data as $key => $value )
        {
            $this->$key = $value;
        }
    }

    /**
     * 输出信息
     *
     * @param string $msg
     * @param array $data
     * @param int $code
     */
    protected static function show_message($msg,$data=array(),$code=0)
    {
        if (\HttpIO::IS_AJAX)
        {
            # AJAX 模式

        }
        else
        {

        }
    }

    /**
     * Session对象
     *
     * @return \Session
     */
    protected static function session()
    {
        return \Session::instance();
    }

    /**
     * 页面跳转
     *
     * @param   string   redirect location
     * @param   integer  status code: 301, 302, etc
     * @return  void
     * @uses    \Core_url::site
     * @uses    \Request::send_headers
     */
    protected function redirect($url, $code = 302)
    {
        \HttpIO::redirect($url, $code);
    }
}
