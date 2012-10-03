<?php
namespace Core;

/**
 * 核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
abstract class Core
{
    /**
     * 版本号
     *
     * @var string
     */
    const VERSION = '3.0 Alpha1';

    /**
     * 项目开发者
     *
     * @var string
     */
    const CODER = 'jonwang(jonwang@myqee.com)';

    /**
     * 页面编码
     *
     * @var string
     */
    public static $charset;

    /**
     * 当前项目
     *
     * @var string
     */
    public static $project;

    /**
     * 系统包含目录
     *
     * @var array
     */
    public static $include_puth = array();

    /**
     * 执行Core::close_all_connect()方法时会关闭链接的类和方法名的列队，可通过Core::add_close_connect_class()方法进行设置增加
     *
     *   array(
     *       'Database' => 'close_all_connect',
     *   );
     *
     * @var array
     */
    protected static $close_connect_class_list = array();

    public function __construct()
    {
        static::show_500('Core class can not instantiated');
    }

    public static function setup()
    {
        static $run=null;

        if (!$run)
        {
            /**
             * 是否管理模式
             *
             * @var boolean
             */
            \define('IS_ADMIN_MODE', false);

            $is_online_debug = function()
            {
                if (!isset($_COOKIE['_debug_open'])) return false;

                if (!isset(\Bootstrap::$config['core']['debug_open_password'])) return false;

                if (!\is_array(\Bootstrap::$config['core']['debug_open_password']))
                {
                    \Bootstrap::$config['core']['debug_open_password'] = array((string)\Bootstrap::$config['core']['debug_open_password']);
                }

                foreach ( \Bootstrap::$config['core']['debug_open_password'] as $item )
                {
                    if ($_COOKIE['_debug_open']==\Bootstrap::get_debug_hash($item))
                    {
                        return true;
                    }
                }

                return false;
            };

            if ( $is_online_debug() )
            {
                $local_debug = true;
            }
            elseif ( isset(\Bootstrap::$config['core']['local_debug_cfg']) && \Bootstrap::$config['core']['local_debug_cfg'] )
            {
                if ( \function_exists('\\get_cfg_var') )
                {
                    $local_debug = \get_cfg_var(\Bootstrap::$config['core']['local_debug_cfg'])?true:false;
                }
                else
                {
                    $local_debug = false;
                }
            }
            else
            {
                $local_debug = false;
            }

            /**
             * 是否AJAX请求
             *
             * @var boolean
             */
            \define('IS_DEBUG',$local_debug);

            if (\IS_DEBUG && isset(\Bootstrap::$config['core']['libraries']['debug']) && \is_array(\Bootstrap::$config['core']['libraries']['debug']) && \Bootstrap::$config['core']['libraries']['debug'])
            {
                foreach (\Bootstrap::$config['core']['libraries']['debug'] as $lib)
                {
                    static::import_library($lib);
                }
            }

            static::$project =& \Bootstrap::$project;

            static::$include_puth =& \Bootstrap::$include_path;

            static::$charset = \Bootstrap::$config['core']['charset'];

            # 检查\Bootstrap版本
            if ( \version_compare(\Bootstrap::VERSION, '2.0' ,'<') )
            {
                static::show_500('系统\Bootstrap版本太低，请先升级\Bootstrap。');
                exit();
            }

            if ( (\IS_CLI || \IS_DEBUG) && \class_exists('\\DevException',true) )
            {
                # 注册脚本
                \register_shutdown_function(array('\\DevException', 'shutdown_handler'));
                # 捕获错误
                \set_exception_handler(array('\\DevException', 'exception_handler'));
                \set_error_handler(array('\\DevException', 'error_handler'), \error_reporting());
            }
            else
            {
                # 注册脚本
                \register_shutdown_function(array('\\Core', 'shutdown_handler'));
                # 捕获错误
                \set_exception_handler(array('\\Core', 'exception_handler'));
                \set_error_handler(array('\\Core', 'error_handler'), \error_reporting());
            }

            if (!\IS_CLI)
            {
                \header('X-Powered-By: PHP/' . \PHP_VERSION . ', MyQEE/' . static::VERSION );

                \HttpIO::setup();
            }

            if ( \IS_SYSTEM_MODE && false===static::check_system_request_allow() )
            {
                # 内部请求验证不通过
                static::show_500('system request hash error');
            }

        }

        if ( \IS_DEBUG && isset($_REQUEST['debug']) && \class_exists('\\Debug_Profiler',true) )
        {
            \Debug_Profiler::setup();
        }

        if (!\IS_CLI)
        {
            \register_shutdown_function(
                function()
                {
                    \HttpIO::send_headers();
                    # 输出内容
                    echo \HttpIO::$body;


                    if ($_GET['test'])
                    {
                        //TODO///////TEST
                        echo '<br><pre>';
                        echo \microtime(1)-\START_TIME;

                        echo "\n";
                        echo ((\memory_get_usage()-\START_MEMORY)/1024).'kb';
                        echo "\n";
                        echo (\memory_get_usage()/1024).'kb';
                        echo "\n";

                        echo '<br><hr>include path<br>';
                        \print_r(\Bootstrap::$include_path);

                        \print_r(\get_included_files());

                        echo '</pre>';
                    }
                }
            );
        }
    }

    /**
     * 获取指定key的配置
     *
     * @param string $key
     * @param mixed $default 在没有获取到config时返回的值,例如 \Core::config('test','abc'); 时当尝试获取test的config时没有，则返回abc
     * @return mixed
     */
    public static function config( $key = '' , $default = null )
    {
        $key_array = \explode('.', $key);
        $key = \array_shift($key_array);

        if ( !isset(\Bootstrap::$config[$key]) )
        {
            if ( $key!='core' && isset(\Bootstrap::$config['core'][$key]) )
            {
                $v = \Bootstrap::$config['core'][$key];
            }
            else
            {
                // 没有任何设置，返回默认值
                return $default;
            }
        }
        else
        {
            $v = \Bootstrap::$config[$key];
        }

        foreach ($key_array as $i)
        {
            if ( !isset($v[$i]) )return $default;
            $v = $v[$i];
        }

        return $v;
    }

    /**
     * 导入指定类库
     *
     * 导入的格式必须是类似 com.a.b 的形式，否则会抛出异常，例如: com.myqee.test
     *
     *      //导入myqee.test类库
     *      Bootstrap::import_library('com.myqee.test');
     *
     * @param string $library_name 指定类库
     * @return boolean
     */
    public static function import_library($library_name)
    {
        return \Bootstrap::import_library($library_name);
    }

    /**
     * 404，可直接将Exception对象传给$msg
     *
     * @param string/Exception $msg
     */
    public static function show_404($msg = null)
    {
        # 将输出全部清除
        static::close_buffers(false);

        \HttpIO::status(404);
        \HttpIO::send_headers();

        if ( null===$msg )
        {
            $msg = \__('Page Not Found');
        }

        if ( \IS_DEBUG && \class_exists('\\DevException',false) )
        {
            if ( $msg instanceof \Exception )
            {
                throw $msg;
            }
            else
            {
                throw new \Exception($msg, \E_PAGE_NOT_FOUND);
            }
        }

        if (\IS_CLI)
        {
            echo $msg.\CRLF;
            exit();
        }

        try
        {
            $view = new \View('error/404');
            $view->message = $msg;
            $view->render(true);
        }
        catch ( \Exception $e )
        {
            list ( $REQUEST_URI ) = \explode('?', $_SERVER['REQUEST_URI'], 2);
            $REQUEST_URI = \htmlspecialchars(\rawurldecode($REQUEST_URI));

            echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">' .
            \CRLF . '<html>' .
            \CRLF . '<head>' .
            \CRLF . '<title>'.\__('Page Not Found').'</title>' .
            \CRLF . '</head>'.
            \CRLF . '<body>' .
            \CRLF . '<h1>'.\__('Page Not Found').'</h1>' .
            \CRLF . '<p>The requested URL ' . $REQUEST_URI . ' was not found on this server.</p>' .
            \CRLF . '<hr>' .
            \CRLF . $_SERVER['SERVER_SIGNATURE'] .
            \CRLF . '</body>' .
            \CRLF . '</html>';
        }

        exit();
    }

    /**
     * 系统错误，可直接将Exception对象传给$msg
     *
     * @param string/Exception $msg
     */
    public static function show_500($msg=null,$error_code=500)
    {
        # 将输出全部清除
        static::close_buffers(false);

        \HttpIO::status($error_code);
        \HttpIO::send_headers();

        if ( null === $msg )
        {
            $msg = \__('Internal Server Error');
        }

        if ( \IS_DEBUG && \class_exists('\\DevException',false) )
        {
            if ( $msg instanceof \Exception )
            {
                throw $msg;
            }
            else
            {
                throw new \Exception($msg, 0);
            }
        }

        if (\IS_CLI)
        {
            echo $msg . \CRLF;
            exit();
        }

        try
        {
            $view = new \View('error/500');
            $error = '';
            if ( $msg instanceof \Exception )
            {
                $error .= 'Msg :' . $msg->getMessage() . \CRLF . "Line:" . $msg->getLine() . \CRLF . "File:" . static::debug_path($msg->getFile());
            }
            else
            {
                $error .= $msg;
            }
            $view->error = $error;

            $view->render(true);
        }
        catch ( \Exception $e )
        {
            list ( $REQUEST_URI ) = \explode('?', $_SERVER['REQUEST_URI'], 2);
            $REQUEST_URI = \htmlspecialchars(\rawurldecode($REQUEST_URI));

            echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">' .
            \CRLF . '<html>' .
            \CRLF . '<head>' .
            \CRLF . '<title>'.\__('Internal Server Error').'</title>' .
            \CRLF . '</head>' .
            \CRLF . '<body>' .
            \CRLF . '<h1>'.\__('Internal Server Error').'</h1>' .
            \CRLF . '<p>The requested URL ' . $REQUEST_URI . ' was error on this server.</p>' .
            \CRLF . '<hr>' .
            \CRLF . $_SERVER['SERVER_SIGNATURE'] .
            \CRLF . '<!-- '.\htmlspecialchars($msg).' -->' .
            \CRLF . '</body>' .
            \CRLF . '</html>';
        }

        exit();
    }

    /**
     * 查找文件
     *
     * @param string $dir 目录
     * @param string $file 文件
     * @param string $ext 后缀 例如：.html
     */
    public static function find_file($dir, $file, $ext=null)
    {
        return \Bootstrap::find_file($dir, $file, $ext);
    }

    /**
     * 查找视图文件
     *
     * @param string $file 视图文件
     * @return string
     */
    public static function find_view($file)
    {
        return \Bootstrap::find_file('views', $file);
    }

    /**
     * 关闭所有可能的外部链接，比如Database,Memcache等连接
     */
    public static function close_all_connect()
    {
        foreach ( static::$close_connect_class_list as $class_name=>$fun )
        {
            try
            {
                \call_user_func_array( array($class_name,$fun), array() );
            }
            catch (\Exception $e)
            {
                static::debug()->error( 'close_all_connect error:'.$e->getMessage() );
            }
        }
    }

    /**
     * 关闭所有OB缓冲区
     *
     * @param   boolean 是否输出缓冲区内容
     * @return  void
     */
    public static function close_buffers($flush = true)
    {
        static $buffer_level = 0;

        if ( \ob_get_level() > $buffer_level )
        {
            $close = ($flush === true) ? '\\ob_end_flush':'\\ob_end_clean';
            while ( \ob_get_level() > $buffer_level )
            {
                $close();
            }

            $buffer_level = \ob_get_level();
        }
    }

    /**
     * 增加执行Core::close_all_connect()时会去关闭的类
     *
     *    Core::add_close_connect_class('Database','close_all_connect');
     *    Core::add_close_connect_class('Cache_Driver_Memcache');
     *    Core::add_close_connect_class('TestClass','close');
     *    //当执行 Core::close_all_connect() 时会调用 Database::close_all_connect() 和 Cache_Driver_Memcache::close_all_connect() 和 TestClass::close() 方法
     *
     * @param string $class_name
     * @param string $fun
     */
    public static function add_close_connect_class($class_name,$fun='close_all_connect')
    {
        static::$close_connect_class_list[$class_name] = $fun;
    }

    /**
     * 将真实路径地址输出为调试地址
     *
     * 显示结果类似 SYSPATH/libraries/Database.php
     *
     * @param   string  path to debug
     * @return  string
     */
    public static function debug_path($file)
    {
        $file = \str_replace('\\', \DS, $file);

        if ( \strpos($file, \DIR_DATA) === 0 )
        {
            $file = 'DATA/' . \substr($file, \strlen(\DIR_DATA));
        }
        elseif ( \strpos($file, \DIR_APPLICATION) === 0 )
        {
            $file = 'APPLICATION/' . \substr($file, \strlen(\DIR_APPLICATION));
        }
        elseif ( \strpos($file, \DIR_APPS) === 0 )
        {
            $file = 'APPS/' . \substr($file, \strlen(\DIR_APPS));
        }
        elseif ( \strpos($file, \DIR_LIBRARY) === 0 )
        {
            $file = 'LIBRARY/' . \substr($file, \strlen(\DIR_LIBRARY));
        }
        elseif ( \strpos($file, \DIR_WWWROOT) === 0 )
        {
            $file = 'WWWROOT/' . \substr($file, \strlen(\DIR_WWWROOT));
        }
        elseif ( \strpos($file, \DIR_CORE) === 0 )
        {
            $file = 'CORE/' . \substr($file, \strlen(\DIR_CORE));
        }
        elseif ( \strpos($file, \DIR_SYSTEM) === 0 )
        {
            $file = 'SYSTEM/' . \substr($file, \strlen(\DIR_SYSTEM));
        }
        $file = \str_replace('\\', '/', $file);

        return $file;
    }

    /**
     * 返回DEBUG对象
     *
     * @return \Debug
     */
    public static function debug()
    {
        static $debug = null;
        if ( null===$debug )
        {
            if ( !\IS_CLI && \IS_DEBUG && false!==\strpos($_SERVER["HTTP_USER_AGENT"],'FirePHP') && \class_exists('\\Debug',true) )
            {
                $debug = \Debug::instance();
            }
            else
            {
                $debug = new _NoDebug();
            }
        }

        return $debug;
    }

    /**
     * 返回一个用.表示的字符串的key对应数组的内容
     *
     * 例如
     *
     *    $arr = array(
     *        'a' => array(
     *        	  'b' => 123,
     *            'c' => array(
     *                456,
     *            ),
     *        ),
     *    );
     *    Core::key_string($arr,'a.b');  //返回123
     *
     *    Core::key_string($arr,'a');
     *    // 返回
     *    array(
     *       'b' => 123,
     *       'c' => array(
     *          456,
     *        ),
     *    );
     *
     *    Core::key_string($arr,'a.c.0');  //返回456
     *
     *    Core::key_string($arr,'a.d');  //返回null
     *
     * @param array $arr
     * @param string $key
     * @return fixed
     */
    public static function key_string($arr, $key)
    {
        if (!\is_array($arr))return null;
        $keyArr = \explode('.', $key);
        foreach ( $keyArr as $key )
        {
            if ( isset($arr[$key]) )
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

    public static function shutdown_handler()
    {
        $error = \error_get_last();
        if ( $error )
        {
            static $run = null;

            if ($run===true)return;

            $run = true;

            if ( ((\E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR) & $error['type'])!==0 )
            {
                static::show_500('Msg:'.$error['message']."\n".'File:'.static::debug_path($error['file'])."\n".'Line:'.$error['line'],$error['type']);
                exit();
            }
        }
    }

    public static function exception_handler(\Exception $e)
    {
        $code = $e->getCode();
        if ( $code !== 8 )
        {
            static::show_500($e);
            exit();
        }
    }

    public static function error_handler($code, $error, $file = null, $line = null)
    {
        if ( (\error_reporting() & $code)!==0 )
        {
            throw new \ErrorException( $error, $code, 0, $file, $line );
        }
        return true;
    }

    /**
     * 返回URL对象
     *
     * @param string $url URL
     * @param bool $return_full_url 返回完整的URL，带http(s)://开头
     * @return string
     */
    public static function url($url = null , $return_full_url = false)
    {
        list($url,$query) = \explode('?', $url , 2);

        $url = \Bootstrap::$base_url. \ltrim($url,'/') . ($url!='' && \substr($url,-1)!='/' && false===\strpos($url,'.') && \Bootstrap::$config['core']['url_suffix']?\Bootstrap::$config['core']['url_suffix']:'') . ($query?'?'.$query:'');

        // 返回完整URL
        if ( $return_full_url && !\preg_match('#^http(s)?://#i', $url) )
        {
            $url = \HttpIO::PROTOCOL . '://' . $_SERVER["HTTP_HOST"] . $url;
        }

        return $url;
    }

    /**
     * 记录日志
     *
     * @param string $msg 日志内容
     * @param string $type 类型，例如：log,error,debug 等
     * @return boolean
     */
    public static function log($msg , $type = 'log')
    {
        # log配置
        $log_config = static::config('log');

        # 不记录日志
        if ( isset($log_config['use']) && !$log_config['use'] )return true;

        if ($log_config['file'])
        {
            $file = \date($log_config['file']);
        }
        else
        {
            $file = \date('Y/m/d/');
        }
        $file .= $type.'.log';

        $dir = \trim(\dirname($file),'/');

        # 如果目录不存在，则创建
        if (!\is_dir(\DIR_LOG.$dir))
        {
            $temp = \explode('/', \str_replace('\\', '/', $dir) );
            $cur_dir = '';
            for( $i=0; $i<\count($temp); $i++ )
            {
                $cur_dir .= $temp[$i] . "/";
                if ( !\is_dir(\DIR_LOG.$cur_dir) )
                {
                    @\mkdir(\DIR_LOG.$cur_dir,0755);
                }
            }
        }

        # 内容格式化
        if ($log_config['format'])
        {
            $format = $log_config['format'];
        }
        else
        {
            # 默认格式
            $format = ':time - :host::port - :url - :msg';
        }

        # 获取日志内容
        $data = static::log_format($msg,$type,$format);

        if (\IS_DEBUG)
        {
            # 如果有开启debug模式，输出到浏览器
            static::debug()->log($data,$type);
        }

        # 保存日志
        return static::write_log($file, $data, $type);
    }

    /**
     * 返回协议类型
     *
     * 当在命令行里执行，则返回null
     *
     * @return null/http/https
     */
    public static function protocol()
    {
        return \Bootstrap::protocol();
    }

    /**
     * 写入日志
     *
     * 若有特殊写入需求，可以扩展本方法（比如调用数据库类克写到数据库里）
     *
     * @param string $file
     * @param string $data
     * @param string $type 日志类型
     * @return boolean
     */
    protected static function write_log($file , $data , $type = 'log')
    {
        return @\file_put_contents(\DIR_LOG.$file, $data.\CRLF , \FILE_APPEND)?true:false;
    }

    /**
     * 用于保存日志时格式化内容，如需要特殊格式可以自行扩展
     *
     * @param string $msg
     * @param string $format
     * @return string
     */
    protected static function log_format($msg,$type,$format)
    {
        $value = array
        (
            ':time'    => \date('Y-m-d H:i:s'),            //当前时间
            ':url'     => $_SERVER['SCRIPT_URI'],          //请求的URL
            ':msg'     => $msg,                            //日志信息
            ':type'    => $type,                           //日志类型
            ':host'    => $_SERVER["SERVER_ADDR"],         //服务器
            ':port'    => $_SERVER["SERVER_PORT"],         //端口
            ':ip'      => \HttpIO::IP,                     //请求的IP
            ':agent'   => $_SERVER["HTTP_USER_AGENT"],     //客户端信息
            ':referer' => $_SERVER["HTTP_REFERER"],        //来源页面
        );

        return \strtr($format,$value);
    }

    /**
     * 检查内部调用HASH是否有效
     *
     * @return boolean
     */
    protected static function check_system_request_allow()
    {
        $hash = $_SERVER['HTTP_X_MYQEE_SYSTEM_HASH'];    //请求验证HASH
        $time = $_SERVER['HTTP_X_MYQEE_SYSTEM_TIME'];    //请求验证时间
        $rstr = $_SERVER['HTTP_X_MYQEE_SYSTEM_RSTR'];    //请求时的随机字符串
        if (!$hash||!$time||!$rstr)return false;

        # 请求时效检查
        if ( \microtime(1)-$time>600 )
        {
            static::log('system request timeout','system-request');
            return false;
        }

        # 验证IP
        if ( '127.0.0.1' != \HttpIO::IP && \HttpIO::IP != $_SERVER["SERVER_ADDR"] )
        {
            $allow_ip = static::config('core.system_exec_allow_ip');

            if (\is_array($allow_ip) && $allow_ip)
            {
                $allow = false;
                foreach ($allow_ip as $ip)
                {
                    if (\HttpIO::IP==$ip)
                    {
                        $allow = true;
                        break;
                    }

                    if (\strpos($allow_ip,'*'))
                    {
                        # 对IP进行匹配
                        if (\preg_match('#^'.\str_replace('\\*','[^\.]+',\preg_quote($allow_ip,'#')).'$#',\HttpIO::IP))
                        {
                            $allow = true;
                            break;
                        }
                    }
                }

                if (!$allow)
                {
                    static::log('system request not allow ip:'.\HttpIO::IP,'system-request');
                    return false;
                }
            }
        }

        $body = \http_build_query(\HttpIO::POST(null,\HttpIO::PARAM_TYPE_OLDDATA));

        # 系统调用密钥
        $system_exec_pass = static::config('core.system_exec_key');

        if ($system_exec_pass && \strlen($system_exec_pass)>=10)
        {
            # 如果有则使用系统调用密钥
            $newhash = \sha1($body.$time.$system_exec_pass.$rstr);
        }
        else
        {
            # 没有，则用系统配置和数据库加密
            $newhash = \sha1($body.$time.\serialize(static::config('core')).\serialize(static::config('database')).$rstr);
        }

        if ( $newhash==$hash )
        {
            return true;
        }
        else
        {
            static::log('system request hash error','system-request');
            return false;
        }
    }

    /**
     * 获取一个cookie对象
     *
     * @return \AnonymousClass\Cookie
     */
    public static function cookie()
    {
        static $cookie = null;

        if (null===$cookie)
        {
            $config = (array)Core::config('cookie');

            if ( $config['domain'] )
            {
                # 这里对IP+PORT形式的domain需要特殊处理下，经测试，当这种情况下，设置session id的cookie的话会失败，需要把端口去掉
                if ( \preg_match('#^([0-9]+.[0-9]+.[0-9]+.[0-9]+):[0-9]+$#',$config['domain'],$m) )
                {
                    $config['domain'] = $m[1];    //只保留IP
                }
            }

            // 新建一个匿名对象
            $cookie = new \Anonymous();

            $cookie->get = function($name=null) use ($config)
            {
                if ( isset($config['prefix']) && $config['prefix'] ) $name = $config['prefix'] . $name;

                if ( isset($_COOKIE[$name]) )
                {
                    return $_COOKIE[$name];
                }
                else
                {
                    return null;
                }
            };

            $cookie->set = function ($name, $value = null, $expire = null, $path = null, $domain = null, $secure = null, $httponly = null) use ($config)
            {
                if ( \headers_sent() ) return false;

                \is_array($name) && \extract($name, \EXTR_OVERWRITE);

                foreach ( array('value', 'expire', 'domain', 'path', 'secure', 'httponly', 'prefix') as $item )
                {
                    if ( $$item === null && isset($config[$item]) )
                    {
                        $$item = $config[$item];
                    }
                }

                $config['prefix'] && $name = $config['prefix'] . $name;

                $expire = ($expire == 0) ? 0 : $_SERVER['REQUEST_TIME'] + (int)$expire;

                return \setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
            };

            $cookie->delete = function ($name, $path = null, $domain = null) use ($cookie)
            {
                return $cookie->set($name, '', -864000, $path, $domain, false, false);
            };
        }

        return $cookie;
    }
}


/**
 * 无调试对象
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Core
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class _NoDebug
{
    public function __call($m, $v)
    {
        return $this;
    }

    public function log($i = null)
    {
        return $this;
    }

    public function info($i = null)
    {
        return $this;
    }

    public function error($i = null)
    {
        return $this;
    }

    public function group($i = null)
    {
        return $this;
    }

    public function groupEnd($i = null)
    {
        return $this;
    }

    public function table($Label = null, $Table = null)
    {
        return $this;
    }

    public function profiler($i = null)
    {
        return $this;
    }

    public function is_open()
    {
        return false;
    }
}