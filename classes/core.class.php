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
     * 执行Core::close_all_connect()方法时会关闭链接的类和方法名的列队，可通过Core::add_close_connect_class()方法进行设置增加
     *
     *   array(
     *       'Database' => 'close_all_connect',
     *   );
     *
     * @var array
     */
    protected static $close_connect_class_list = array();

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
                    if ($_COOKIE['_debug_open']==static::get_debug_hash($item))
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
                if ( \function_exists('get_cfg_var') )
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

            static::$charset = \Bootstrap::$config['core']['charset'];

            # 检查\Bootstrap版本
            if ( \version_compare(\Bootstrap::VERSION, '2.0' ,'<') )
            {
                static::show_500('系统\Bootstrap版本太低，请先升级\Bootstrap。');
                exit();
            }

            if ( (\IS_CLI || \IS_DEBUG) && \class_exists('\\Dev_Exception',true) )
            {
                # 注册脚本
                \register_shutdown_function(array('\\Dev_Exception', 'shutdown_handler'));
                # 捕获错误
                \set_exception_handler(array('\\Dev_Exception', 'exception_handler'));
                \set_error_handler(array('\\Dev_Exception', 'error_handler'), \error_reporting());
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

        }

        if ( \IS_DEBUG && isset($_REQUEST['debug']) && \class_exists('\\Debug\\Profiler',true) )
        {
            \Debug\Profiler::setup();
        }

        \register_shutdown_function(
            function()
            {
                if (!\IS_CLI)
                {
                    \HttpIO::send_headers();
                }

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

                if (!\IS_CLI)
                {
                    # 输出内容
                    echo \HttpIO::$body;
                }
            }
        );
    }

    /**
     * 获取指定key的配置
     *
     * 若不传key，则返回Core_Config对象，可获取动态配置，例如Core::config()->get();
     *
     * @param string $key
     * @param mixed $default 在没有获取到config时返回的值,例如 \Core::config('test','abc'); 时当尝试获取test的config时没有，则返回abc
     * @return mixed|\Core\Config
     */
    public static function config($key = null,$default=null)
    {
        if ( null===$key )
        {
            return static::factory('\\Core\\Config');
        }

        $key_array = explode('.', $key);
        $key = array_shift($key_array);

        if (!isset(\Bootstrap::$config[$key]))return $default;

        $v = \Bootstrap::$config[$key];
        foreach ($key_array as $i)
        {
            if (!isset($v[$i]))return $default;
            $v = $v[$i];
        }

        return $v;
    }

    /**
     * 导入指定类库
     *
     * 导入的格式必须是类似 BigClass/SubClass 的形式，否则会抛出异常，例如: MyQEE/CMS , MyQEE/SAE 等等
     *
     * @param string $library_name 指定类库
     * @return boolean
     * @throws \Exception
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
        \HttpIO::status(404);
        \HttpIO::send_headers();

        if ( null===$msg )
        {
            $msg = \__('Page Not Found');
        }

        if ( \IS_DEBUG && \class_exists('\\Dev_Exception',false) )
        {
            if ( $msg instanceof \Exception )
            {
                throw $msg;
            }
            else
            {
                throw new \Exception($msg, 43);
            }
        }

        if (\IS_CLI)
        {
            echo $msg.\N;
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
            \N . '<html>' .
            \N . '<head>' .
            \N . '<title>'.\__('Page Not Found').'</title>' .
            \N . '</head>'.
            \N . '<body>' .
            \N . '<h1>'.\__('Page Not Found').'</h1>' .
            \N . '<p>The requested URL ' . $REQUEST_URI . ' was not found on this server.</p>' .
            \N . '<hr>' .
            \N . $_SERVER['SERVER_SIGNATURE'] .
            \N . '</body>' .
            \N . '</html>';
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
        \HttpIO::status($error_code);
        \HttpIO::send_headers();

        if ( null === $msg )
        {
            $msg = \__('Internal Server Error');
        }

        if ( \IS_DEBUG && \class_exists('\\Dev_Exception',false) )
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
            echo $msg . \N;
            exit();
        }

        try
        {
            $view = new \View('error/500');
            $error = '';
            if ( $msg instanceof \Exception )
            {
                $error .= 'Msg :' . $msg->getMessage() . \N . "Line:" . $msg->getLine() . \N . "File:" . static::debug_path($msg->getFile());
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

            \N . '<html>' .
            \N . '<head>' .
            \N . '<title>'.\__('Internal Server Error').'</title>' .
            \N . '</head>' .
            \N . '<body>' .
            \N . '<h1>'.\__('Internal Server Error').'</h1>' .
            \N . '<p>The requested URL ' . $REQUEST_URI . ' was error on this server.</p>' .
            \N . '<hr>' .
            \N . $_SERVER['SERVER_SIGNATURE'] .
            \N . '</body>' .
            \N . '</html>';
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
        return \Bootstrap::find_file('views', $file,'.view.php');
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
            if ( !\IS_CLI && \IS_DEBUG && \class_exists('\\Debug',true) )
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
        if (!is_array($arr))return null;
        $keyArr = explode('.', $key);
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
                $error['file'] = static::debug_path($error['file']);
                static::show_500(\var_export($error, true));
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

    public static function get_debug_hash($password)
    {
        static $config_str = null;
        if (null===$config_str)$config_str=\var_export(\Bootstrap::$config['core']['debug_open_password'],true);

        return \md5($config_str.'_open$&*@debug'.$password);
    }

    public static function error_handler($code, $error, $file = null, $line = null)
    {
        if ( (\error_reporting() & $code)!==0 )
        {
            throw new \ErrorException( $error, $code, 0, $file, $line );
        }
        return true;
    }

    public static function test()
    {
        \var_dump(static::$charset);
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