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
     * 例如访问地址为 http://localhost/123/test/ 控制器为_id.controller.php ,方法为test，则$this->ids=array(123)，系统会在初始化控制器时进行设置
     *
     * @var array
     */
    public $ids = array();

    protected static $message_view = 'show_message';

    /**
     * 控制器实例化化对象
     *
     * @var array
     */
    public static $controllers = array();

    public function __construct()
    {

    }

    /**
     * 返回当前控制器
     *
     * @return \Controller
     */
    public static function current_controller()
    {
        \end(static::$controllers);
        return \current(static::$controllers);
    }

    /**
     * 输出错误信息
     *
     * @param string $msg
     * @param array $data
     * @param int $code
     */
    protected static function show_error( $msg = 'error' )
    {
        static::show_message($msg , -1);
    }

    /**
     * 输出成功信息
     *
     * @param string $msg
     * @param array $data
     * @param int $code
     */
    protected static function show_success( $msg = 'success' , $data = array() )
    {
        static::show_message( $msg , 1 , $data );
    }

    /**
     * 输出信息
     *
     * @param string $msg
     * @param array $data
     * @param int $code
     */
    protected static function show_message( $msg , $code = 0 , $data = array() )
    {
        if (\IS_SYSTEM_MODE)
        {
            # 系统内部调用模式
            echo $msg;
        }
        elseif (isset($_SERVER["HTTP_X_PJAX"]) && $_SERVER["HTTP_X_PJAX"]=='true')
        {
            \View::factory(static::$message_view,array('msg'=>$msg,'data'=>$data,'code'=>$code))->render();
        }
        elseif (\HttpIO::IS_AJAX)
        {
            # AJAX 模式
            $value = array
            (
                'msg'  => $msg,
                'code' => $code,
                'data' => $data,
            );

            @\header('Content-Type: application/json');

            echo \json_encode($value);
        }
        else
        {
            # 输出内容
            \View::factory(static::$message_view,array('msg'=>$msg,'data'=>$data,'code'=>$code))->render();

            # 获取当前实例化控制器对象
            $controller = static::current_controller();

            # 后置方法
            if ( \method_exists($controller,'after') )
            {
                $controller->after();
            }
        }

        # 页面强制结束
        exit;
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
    protected static function redirect($url, $code = 302)
    {
        \HttpIO::redirect($url, $code);
    }
}
