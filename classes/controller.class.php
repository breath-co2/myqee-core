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

    /**
     * 当前控制器信息ID
     *
     * 例如访问地址为 http://localhost/123/test/ 控制器为_id.controller.php ,方法为test，则$this->id=123，系统会在初始化控制器时调用 $this->_callback_set_vars 方法进行设置
     *
     * @var int
     */
    public $id;

    public function __construct()
    {

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
