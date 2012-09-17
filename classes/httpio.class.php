<?php
namespace Core;

if (!\defined('_HTTPIO_METHOD'))
{
    $tmp_fun = function()
    {
        \define('_HTTPIO_METHOD',$_SERVER["REQUEST_METHOD"]);

        if ( isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'xmlhttprequest'===\strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) )
        {
            $is_ajax = true;
        }
        elseif ( isset($_GET['_ajax']) && $_GET['_ajax']=='true' )
        {
            $is_ajax = true;
        }
        else
        {
            $is_ajax = false;
        }
        \define('_HTTPIO_IS_AJAX',$is_ajax);

        $https_key = \Core::config('core.server_httpson_key');
        if ($https_key)
        {
            $https_key = strtoupper($https_key);
        }
        else
        {
            $https_key = 'HTTPS';
        }
        if ( !empty($_SERVER[$https_key]) && \filter_var($_SERVER[$https_key], \FILTER_VALIDATE_BOOLEAN) )
        {
            $protocol = 'https';
        }
        else
        {
            $protocol = 'http';
        }
        \define('_HTTPIO_PROTOCOL',$protocol);

        $get_ip =function ()
        {
            if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) )
            {
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
            elseif ( isset($_SERVER['HTTP_CLIENT_IP']) )
            {
                return $_SERVER['HTTP_CLIENT_IP'];
            }
            elseif ( isset($_SERVER['REMOTE_ADDR']) )
            {
                return $_SERVER['REMOTE_ADDR'];
            }
        };
        $ip = $get_ip();
        \define('_HTTPIO_IP',$ip);

        if ( isset($_SERVER['HTTP_REFERER']) )
        {
            $referrer = $_SERVER['HTTP_REFERER'];
        }
        else
        {
            $referrer = null;
        }
        \define('_HTTPIO_REFERRER',$referrer);

        if ( isset($_SERVER['HTTP_USER_AGENT']) )
        {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
        }
        else
        {
            $user_agent = '';
        }

        \define('_HTTPIO_USER_AGENT',$user_agent);
    };

    $tmp_fun();
    unset($tmp_fun);
}


/**
 * 输入输出核心类 Request,Response
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class HttpIO
{

    const PARAM_TYPE_URL = 'url';

    const PARAM_TYPE_OLDDATA = 'old';

    // HTTP status codes and messages
    protected static $messages = array
    (
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',

        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',

        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',

        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',

        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        507 => 'Insufficient Storage',
        509 => 'Bandwidth Limit Exceeded'
    );

    /**
     * 请求类型
     *
     * @var  string  method: GET, POST, PUT, DELETE, etc
     */
    const METHOD = _HTTPIO_METHOD;

    /**
     * 协议类型
     *
     * @var  string  protocol: http, https, ftp, cli, etc
     */
    const PROTOCOL = _HTTPIO_PROTOCOL;

    /**
     * 引用页
     *
     * @var  string  referring URL
     */
    const REFERRER = _HTTPIO_REFERRER;

    /**
     * 用户浏览器信息
     *
     * @var  string  client user agent
     */
    const USER_AGENT = _HTTPIO_USER_AGENT;

    /**
     * 用户IP
     *
     * @var  string  client IP address
     */
    const IP = _HTTPIO_IP;

    /**
     * 是否AJAX请求
     *
     * @var  boolean  AJAX-generated request
     */
    const IS_AJAX = _HTTPIO_IS_AJAX;

    /**
     * 将输出的header列表
     *
     * @var array
     */
    protected static $headers = array();

    /**
     * 页码状态
     *
     * @var int
     */
    protected static $status = 200;

    /**
     * 当前页面URI
     *
     * @var string
     */
    public static $uri;

    /**
     * 输出到页面的正文
     *
     * @var string
     */
    public static $body;

    /**
     * 当前页码参数
     *
     * @var array
     */
    public static $params;

    protected static $_GET;

    protected static $_POST;

    protected static $_REQUEST;

    protected static $_COOKIE;

    protected static $_GET_OLD;

    protected static $_POST_OLD;

    protected static $_REQUEST_OLD;

    protected static $_COOKIE_OLD;

    /**
     * 执行初始化，只执行一次
     */
    public static function setup()
    {
        static $run = null;

        if ( null===$run )
        {
            $run = true;

            if (!\IS_CLI)
            {
                # 记录一个正真的原始拷贝数据
                static::$_GET_OLD = $_GET;
                static::$_POST_OLD = $_POST;
                static::$_COOKIE_OLD = $_COOKIE;
                static::$_REQUEST_OLD = $_REQUEST;

                # XSS安全处理
                $_GET = static::sanitize($_GET);
                $_POST = static::sanitize($_POST);
                $_COOKIE = static::sanitize($_COOKIE);
                $_REQUEST = static::sanitize($_REQUEST);

                # 隐射
                static::$_GET = & $_GET;
                static::$_POST = & $_POST;
                static::$_COOKIE = & $_COOKIE;
                static::$_REQUEST = & $_REQUEST;

                static::$uri = \Bootstrap::$path_info;
            }
        }
    }

    /**
     * 获取$_GET数据
     *
     * 		// 获取原始数据
     * 		$get_array = Request::GET(null,Request::PARAM_TYPE_OLDDATA);
     *
     * 		// 获取原始数据为URL格式
     * 		$url = Request::GET('url',Request::PARAM_TYPE_URL);
     *
     * @param string $key
     * @param string 返回类型，false或不传，则返回原始数据 例：Request::PARAM_TYPE_URL
     */
    public static function GET($key = null, $type = null)
    {
        return static::_get_format_data('_GET', $key, $type);
    }

    /**
     * 获取$_POST数据
     *
     * @param string $key
     * @param string 返回类型，false或不传，则返回原始数据 例：Request::PARAM_TYPE_URL
     */
    public static function POST($key = null, $type = null)
    {
        return static::_get_format_data('_POST', $key, $type);
    }

    /**
     * 获取$_COOKIE数据
     *
     * @param string $key
     * @param string 返回类型，false或不传，则返回原始数据 例：Request::PARAM_TYPE_URL
     */
    public static function COOKIE($key = null, $type = null)
    {
        return static::_get_format_data('_COOKIE', $key, $type);
    }

    /**
     * 获取$_REQUEST数据
     *
     * @param string $key
     * @param string 返回类型，false或不传，则返回原始数据 例：Request::PARAM_TYPE_URL
     */
    public static function REQUEST($key = null, $type = null)
    {
        return static::_get_format_data('_REQUEST', $key, $type);
    }

    protected static function _get_format_data($datatype, $key, $type)
    {
        if ( $type == static::PARAM_TYPE_OLDDATA )
        {
            # 如果是要拿原始拷贝，则加后缀
            $datatype .= '_OLD';
        }
        $data = static::_key_string(static::$$datatype, $key);
        if ( null === $data ) return null;

        if ( !$type )
        {
            # 未安全过滤的数据
            $data = static::sanitize_decode($data);
        }
        elseif ( $type == static::PARAM_TYPE_URL )
        {
            # URL 格式数据
            $data = static::sanitize_decode($data);
            $data = \str_replace(array('<', '>', '\'', "\"", '\''), array('%3C', '%3E', '%27', '%22', '%5C'), $data);
        }
        return $data;
    }

    /**
     * 对字符串进行安全处理
     *
     * @param $str
     */
    public static function sanitize($str)
    {
        if ( null === $str ) return null;
        if ( \is_array($str) || \is_object($str) )
        {
            $data = array();
            foreach ( $str as $k => $v )
            {
                $data[$k] = static::sanitize($v);
            }
        }
        else
        {
            $str = \trim($str);
            if ( \strpos($str, "\r") !== false )
            {
                $str = \str_replace(array("\r\n", "\r"), "\n", $str);
            }

            $data = \htmlspecialchars($str);
        }
        return $data;
    }

    /**
     * 对字符串进行反向安全处理
     *
     * @param $str
     */
    public static function sanitize_decode($str)
    {
        if ( null === $str ) return null;
        if ( \is_array($str) || \is_object($str) )
        {
            foreach ( $str as $k => $v )
            {
                $str[$k] = static::sanitize_decode($v);
            }
        }
        else
        {
            $str = \htmlspecialchars_decode($str);
        }
        return $str;
    }

    /**
     * 页面跳转
     *
     * @param   string   redirect location
     * @param   integer  status code: 301, 302, etc
     * @return  void
     * @uses    Core_url::site
     * @uses    Request::send_headers
     */
    public static function redirect($url, $code = 302)
    {
        if ( \strpos('://',$url) !== false )
        {
            $url = \Core::url($url);
        }

        static::$status = $code;

        static::$headers['Location'] = $url;

        static::send_headers();

        exit();
    }

    /**
     * 页面输出header缓存
     *
     * 0表示不缓存
     *
     * @param int $time 缓存时间，单位秒
     */
    public static function set_cache_header($time = 86400)
    {
        $time = (int)$time;

        if ($time>0)
        {
            @\header('Cache-Control: max-age='.$time);
            @\header('Last-Modified: ' . \date( 'D, d M Y H:i:s \G\M\T' ));
            @\header('Expires: ' . \date('D, d M Y H:i:s \G\M\T', \TIME + $time));
            @\header('Pragma: cache');
        }
        else
        {
            @\header('Cache-Control: private, no-cache, must-revalidate');
            @\header('Cache-Control: post-check=0, pre-check=0', false);
            @\header('Pragma: no-cache');
            @\header("Expires: 0");
        }
    }

    protected static function _key_string($arr, $key)
    {
        if ( ! \is_array($arr) ) return null;
        if ( $key === null || $key === false || ! \strlen($key) > 0 )
        {
            return $arr;
        }
        $keyArr = \explode('.', $key);
        foreach ( $keyArr as $key )
        {
            if ( \is_array($arr) && isset($arr[$key]) )
            {
                $arr = $arr[$key];
            }
            else
            {
                return null;
            }
        }
        return $arr;
    }

    /**
     * 发送header数据
     */
    public static function send_headers()
    {
        if ( !\headers_sent() )
        {
            if ( isset($_SERVER['SERVER_PROTOCOL']) )
            {
                // Use the default server protocol
                $protocol = $_SERVER['SERVER_PROTOCOL'];
            }
            else
            {
                // Default to using newer protocol
                $protocol = 'HTTP/1.1';
            }

            if ( static::$status != 200 )
            {
                // HTTP status line
                @\header($protocol . ' ' . static::$status . ' ' . static::$messages[static::$status]);
            }

            foreach ( static::$headers as $name => $value )
            {
                if ( \is_string($name) )
                {
                    $value = "{$name}: {$value}";
                }

                @\header($value, true);
            }
        }
    }

    /**
     * 添加header头信息
     *
     * @param string $key
     * @param string $value
     */
    public static function add_header($key, $value)
    {
        static::$headers[$key] = $value;
    }

    /**
     * 设置HTTP的状态
     *
     * @param int $status
     */
    public static function status($status=null)
    {
        if (static::$status)
        {
            static::$status = $status;
        }

        return static::$status;
    }

    /**
     * 获取当前页指定参数
     *
     * $id = $request->param('id');
     *
     * @param   string key of the value
     * @param   mixed default value if the key is not set
     * @return  mixed
     */
    public static function param($key = null, $default = null)
    {
        if ( $key === null )
        {
            return static::$params;
        }

        return isset(static::$params[$key])?static::$params[$key]:$default;
    }

    public static function query($arr)
    {

    }
}