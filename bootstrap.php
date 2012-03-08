<?php
/**
 * 当前系统启动时间
 *
 * @var int
 */
define('START_TIME', microtime(1));

/**
 * 启动内存
 *
 * @var int 启动所用内存
 */
define('START_MEMORY',memory_get_usage());

/**
 * 系统当前时间
 *
 * @var int
 */
define('TIME', time());

/**
 * 目录分隔符简写
 *
 * @var string
 */
define('DS', DIRECTORY_SEPARATOR);

/**
 * 站点目录
 *
 * @var string
 */
define('DIR_SYSTEM', realpath(dirname(__FILE__).'/../').DS);

/**
 * Application目录
 *
 * @var string
 */
define('DIR_APPLICATION', DIR_SYSTEM.'application'.DS);

/**
 * APP应用目录
 *
 * @var string
 */
define('DIR_APPS', DIR_SYSTEM.'apps'.DS);

/**
 * Data目录
 *
 * @var string
 */
define('DIR_DATA', DIR_SYSTEM.'data'.DS);

/**
 * 项目目录
 *
 * @var string
 */
define('DIR_PROJECT', DIR_SYSTEM.'projects'.DS);

/**
 * Cache目录
 *
 * @var string
 */
define('DIR_CACHE', DIR_DATA.'cache'.DS);

/**
 * Temp目录
 *
 * @var string
 */
define('DIR_TEMP', DIR_DATA.'temp'.DS);

/**
 * Log目录
 *
 * @var string
 */
define('DIR_LOG', DIR_DATA.'log'.DS);

/**
 * 系统类库目录
 *
 * @var string
 */
define('DIR_CORE', DIR_SYSTEM.'core'.DS);

/**
 * 模块目录
 *
 * @var string
 */
define('DIR_LIBRARY', DIR_SYSTEM.'libraries'.DS);

/**
 * WWW目录
 *
 * @var string
 */
define('DIR_WWWROOT', DIR_SYSTEM.'wwwroot'.DS);

/**
 * 是否命令行执行
 *
 * @var boolean
 */
define('IS_CLI',(PHP_SAPI==='cli'));

/**
 * 是否命令行执行
 *
 * @var boolean
 */
define('IS_WIN',(DS==='\\'));

/**
 * PHP后缀
 *
 * @var string
 */
define('EXT', '.php');

/**
 * CRLF换行符
 *
 * @var string
 */
define('CRLF', "\r\n");

/**
 * 服务器是否支持mbstring
 *
 * @var boolean
 */
define('IS_MBSTRING',extension_loaded('mbstring')?true:false);

/**
 * 输出语言包
 *
 * [strtr](http://php.net/strtr) is used for replacing parameters.
 *
 * __('Welcome back, :user', array(':user' => $username));
 *
 * @uses	I18n::get
 * @param	string  text to translate
 * @param	array   values to replace in the translated text
 * @param	string  target language
 * @return	string
 */
function __( $string, array $values = null )
{
    static $have_i18n_class = false;

    if ( false===$have_i18n_class )
    {
        $have_i18n_class = (boolean)class_exists('I18n',true);
    }

    if ($have_i18n_class)
    {
        $string = I18n::get($string);
    }

    return empty($values)?$string:strtr($string,$values);
}


/**
 * Bootstrap
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
final class Bootstrap
{
    /**
     * 版本号
     *
     * @var float
     */
    const VERSION = '2.0';

    /**
     * 包含目录
     *
     * @var array
     */
    public static $include_path = array
    (
        '\\'       => DIR_APPLICATION,
        '\\core\\' => DIR_CORE,
    );

    /**
     * 配置
     *
     * @var array
     */
    public static $config = array();

    /**
     * 当前URL的根路径
     *
     * @var string
     */
    public static $base_url = null;

    /**
     * 当前URL的PATH_INFO
     *
     * @var string
     */
    public static $path_info = null;

    /**
     * 当前项目
     *
     * @var string
     */
    public static $project = null;

    /**
     * 当前应用
     *
     * @var string
     */
    public static $app = null;

    /**
     * 目录设置
     *
     * @var array
     */
    private static $dir_setting = array
    (
        'classes'    => array('classes'     , '.class'),
        'controller' => array('controllers' , '.controller'),
        'model'      => array('models'      , '.model'),
        'orm'        => array('orm'         , '.orm'),
    );

    /**
     * 系统初始化
     *
     * @param boolean $auto_execute 是否自动运行
     */
    public static function setup($auto_execute=true)
    {
        static $run=null;

        if (!$run)
        {
            $run = true;

            # 注册自动加载类
            spl_autoload_register(array('Bootstrap','auto_load'));

            # 读取配置
            if (!is_file(DIR_SYSTEM.'config'.EXT))
            {
                self::show_error('Please rename the file config.new.php to config.php');
            }

            $include_config_file = function ( & $config, $file )
            {
                include $file;
            };
            $include_config_file(self::$config,DIR_SYSTEM.'config'.EXT);

            # debug config
            if (isset($config['core']['debug_config']) && self::$config['core']['debug_config'] && is_file(self::$config,DIR_SYSTEM.'debug.config'.EXT))
            {
                $include_config_file(self::$config,DIR_SYSTEM.'debug.config'.EXT);
            }

            # 请求模式
            $request_mode = '';

            if (IS_CLI)
            {
                if (!isset($_SERVER["argv"]))
                {
                    exit('Err Argv');
                }
                $argv = $_SERVER["argv"];

                //$argv[0]为文件名
                if (isset($argv[1]) && $argv[1] && isset(self::$config['core']['projects'][$argv[1]]))
                {
                    self::$project = $argv[1];
                }

                array_shift($argv); //将文件名移除
                array_shift($argv); //将项目名移除

                self::$path_info = trim(implode('/', $argv));

                unset($argv);

                $open_debug = false;
            }
            else
            {
                self::_setup_by_url($request_mode);

                if (isset(self::$config['core']['charset']))
                {
                    # 输出文件头
                    header('Content-Type: text/html;charset='.self::$config['core']['charset']);
                }

                /**
                 * 判断是否开启了在线调试
                 *
                 * @return boolean
                 */
                $is_online_debug = function ()
                {
                    if (!isset($_COOKIE['_debug_open'])) return false;
                    if (!isset(Bootstrap::$config['core']['debug_open_password'])) return false;
                    if (!is_array(Bootstrap::$config['core']['debug_open_password']))return false;

                    foreach ( Bootstrap::$config['core']['debug_open_password'] as $user=>$pass )
                    {
                        if ($_COOKIE['_debug_open'] == Bootstrap::get_debug_hash($user,$pass))
                        {
                            return true;
                        }
                    }

                    return false;
                };

                # DEBUG配置
                if ( $is_online_debug() )
                {
                    $open_debug = true;
                }
                elseif ( isset( self::$config['core']['local_debug_cfg'] ) && self::$config['core']['local_debug_cfg'] )
                {
                    if ( function_exists( 'get_cfg_var' ) )
                    {
                        $open_debug = get_cfg_var( self::$config['core']['local_debug_cfg'] ) ? true : false;
                    }
                    else
                    {
                        $open_debug = false;
                    }
                }
                else
                {
                    $open_debug = false;
                }

                unset($is_online_debug);
            }

            /**
             * 是否开启DEBUG模式
             *
             * @var boolean
             */
            define('IS_DEBUG', $open_debug);

            # 设置页面错误等级
            if (isset(self::$config['core']['error_reporting']))
            {
                error_reporting(self::$config['core']['error_reporting']);
            }

            # 设置时区
            if (isset(self::$config['core']['timezone']) && self::$config['core']['timezone'])
            {
                date_default_timezone_set(self::$config['core']['timezone']);
            }

            /**
             * 加载类库
             * @var array $arr
             */
            $load_library = function($arr)
            {
                foreach ($arr as $library_name)
                {
                    if (!$library_name)continue;

                    try
                    {
                        Bootstrap::import_library($library_name);
                    }
                    catch (Exception $e)
                    {
                        Bootstrap::show_error($e->getMessage());
                    }
                }
            };

            /**
             * 是否后台模式
             *
             * @var boolean
             */
            define('IS_ADMIN_MODE',(!IS_CLI && $request_mode=='admin')?true:false);

            /**
             * 是否系统内部调用模式
             *
             * @var boolean
             */
            define('IS_SYSTEM_MODE',false);

            # 如果有autoload目录，则设置加载目录
            if ( isset(self::$config['core']['libraries']['autoload']) && is_array(self::$config['core']['libraries']['autoload']) && self::$config['core']['libraries']['autoload'] )
            {
                $load_library(self::$config['core']['libraries']['autoload']);
            }

            if (IS_CLI)
            {
                # cli模式
                if ( isset(self::$config['core']['libraries']['cli']) && is_array(self::$config['core']['libraries']['cli']) && self::$config['core']['libraries']['cli'] )
                {
                    $load_library(self::$config['core']['libraries']['cli']);
                }

                # 控制器在[shell]目录下
                self::$dir_setting['controller'][0] .= DS.'[shell]';
            }
            elseif (IS_ADMIN_MODE)
            {
                # 后台模式
                if ( isset(self::$config['core']['libraries']['admin']) && is_array(self::$config['core']['libraries']['admin']) && self::$config['core']['libraries']['admin'] )
                {
                    $load_library(self::$config['core']['libraries']['admin']);
                }

                # 控制器在[admin]目录下
                self::$dir_setting['controller'][0] .= DS.'[admin]';
            }
            elseif ($request_mode=='app')
            {
                # APP模式
                if ( isset(self::$config['core']['libraries']['app']) && is_array(self::$config['core']['libraries']['app']) && self::$config['core']['libraries']['app'] )
                {
                    $load_library(self::$config['core']['libraries']['app']);
                }
            }

            if (IS_DEBUG)
            {
                # 加载debug类库
                if ( isset(self::$config['core']['libraries']['debug']) && is_array(self::$config['core']['libraries']['debug']) && self::$config['core']['libraries']['debug'] )
                {
                    $load_library(self::$config['core']['libraries']['debug']);
                }

                # 输出一些系统信息
                Core::debug()->group( 'include path' );
                foreach ( self::$include_path as $value )
                {
                    Core::debug()->log( Core::debug_path( $value ) );
                }
                Core::debug()->groupEnd();

                if (self::$project)
                {
                    Core::debug()->info('project: '.self::$project);
                }
                elseif ($request_mode=='app')
                {
                    Core::debug()->info('app mode');
                }
                else
                {
                    Core::debug()->info('default application');
                }

                if (IS_ADMIN_MODE)
                {
                    Core::debug()->info('admin mode');
                }
            }


            unset($load_library);

            Core::setup();
        }

        if ($auto_execute)
        {
            if (IS_CLI)
            {
                self::execute(self::$path_info);
            }
            else
            {
                ob_start();
                self::execute(self::$path_info);
                HttpIO::$body = ob_get_clean();
            }

            Core::close_all_connect();
        }
    }

    /**
     * 执行指定URI的控制器
     *
     * @param string $uri
     */
    public static function execute($uri)
    {
        $found = self::find_controller($uri);

        if ($found)
        {
            require $found['file'];

            $class_name = $found['namespace'].'Controller\\'.$found['class'];

            if (class_exists($class_name,false))
            {

                $controller = new $class_name();

                $arguments = $found['args'];
                if ($arguments)
                {
                    $action = current($arguments);
                    if (0===strlen($action))
                    {
                        $action = 'default';
                    }
                }
                else
                {
                    $action = 'index';
                }

                $action_name = 'action_'.$action;

                if (!method_exists($controller,$action_name))
                {
                    if ($action_name!='action_default' && method_exists($controller,'action_default'))
                    {
                        $action_name='action_default';
                    }
                    elseif (method_exists($controller,'__call'))
                    {
                        $controller->__call($action_name,$arguments);
                        return;
                    }
                    else
                    {
                        Core::show_404();
                    }
                }
                else
                {
                    array_shift($arguments);
                }

                $ispublicmethod = new ReflectionMethod($controller,$action_name);
                if (!$ispublicmethod->isPublic())
                {
                    Core::show_500('Request Method Not Allowed.',405);
                }
                unset($ispublicmethod);

                # 将参数传递给控制器
                $controller->action = $action_name;
                $controller->controller = $found['class'];
                $controller->arguments = $arguments;
                $controller->ids = $found['ids'];

                # 前置方法
                if (method_exists($controller,'before'))
                {
                    $controller->before();
                }

                # 执行方法
                $count_arguments = count($arguments);
                switch ($count_arguments)
                {
                    case 0:
                        $controller->$action_name();
                        break;
                    case 1:
                        $controller->$action_name($arguments[0]);
                        break;
                    case 2:
                        $controller->$action_name($arguments[0], $arguments[1]);
                        break;
                    case 3:
                        $controller->$action_name($arguments[0], $arguments[1], $arguments[2]);
                        break;
                    case 4:
                        $controller->$action_name($arguments[0], $arguments[1], $arguments[2], $arguments[3]);
                        break;
                    default:
                        call_user_func_array(array($controller, $action_name), $arguments);
                        break;
                }

                # 后置方法
                if (method_exists($controller,'after'))
                {
                    $controller->after();
                }

            }
            else
            {
                Core::show_404();
            }
        }
        else
        {
            Core::show_404();
        }
    }


    /**
     * 自动加载
     *
     * @param string $class_name
     * @return boolean
     */
    public static function auto_load($class_name)
    {
        if (class_exists($class_name,false))return true;

        # 移除两边的\
        $class_name = strtolower(trim($class_name,'\\'));

        # 用\分割
        $class_arr = explode('\\', $class_name);
        $old_class_arr = $class_arr;

        # 主命名空间
        $top_name_space = '';
        # 子命名空间
        $sub_name_space = '';
        # class的类型
        $class_dir = 'classes';
        # 含\的个数

        if (count($class_arr)>1)
        {
            switch ($class_arr[0])
            {
                case 'core':
                    # 例如 Core\Database
                    $top_name_space = array_shift($class_arr);
                    break;
                case 'library':
                case 'project':
                    if (count($class_arr)<4)
                    {
                        # 扩展类库和APP必须是4位及以上，比如 Library\MyQEE\CMS\Test
                        return false;
                    }
                    $top_name_space = array_shift($class_arr);
                    $sub_name_space = array_shift($class_arr).DS;
                    $sub_name_space.= array_shift($class_arr).DS;
                    break;
                default;
                    break;
            }

            if ( count($class_arr)>1 && in_array($class_arr[0],array('controller','shell','model','orm',)) )
            {
                $class_dir = array_shift($class_arr);

                if ($class_dir==='orm')
                {
                    $class_arr[count($class_arr)-1]  = @preg_replace('#^(.*)_[a-z0-9]+$#i', '$1', $class_arr[count($class_arr)-1] );
                }
            }
        }

        static $dir_array = array
        (
            ''        => DIR_APPLICATION,
            'library' => DIR_LIBRARY,
            'core'    => DIR_CORE,
            'project' => DIR_PROJECT,
        );

        /*
         Controller\Index          DIR_APPLICATION/controller/index.controller.php
         Database\Driver\MySQL     DIR_APPLICATION/classes/database/driver/mysql.class.php
         Model\Test\Abc            DIR_APPLICATION/model/test/abc.model.php
         Library\MyQEE\CMS\Test    DIR_LIBRARY/myqee/cms/classes/test.class.php
         Core\Test\t               DIR_CORE/classes/test/t.class.php
        */
        # 拼接出完整的URL
        $file = $sub_name_space.self::$dir_setting[$class_dir][0].DS.implode(DS, $class_arr).self::$dir_setting[$class_dir][1].EXT;

        if (is_file($dir_array[$top_name_space].$file))
        {
            require $dir_array[$top_name_space].$file;
        }
        elseif ($top_name_space==='')
        {
            # 没有找到文件且为项目类库，尝试在某个命名空间的类库中寻找
            foreach (self::$include_path as $ns=>$path)
            {
                if ($ns=='\\')continue;

                $ns_class_name = $ns.$class_name;

                if (self::auto_load($ns_class_name))
                {
                    if (!class_exists($class_name,false))
                    {
                        $abstract = '';
                        $rf = new ReflectionClass($ns_class_name);
                        if ( $rf->isAbstract() )
                        {
                            $abstract = 'abstract ';
                        }
                        unset($rf);

                        $class_str = array_pop($old_class_arr);
                        $str = 'namespace '.implode('\\', $old_class_arr).'{'.$abstract.'class '.$class_str.' extends '.$ns_class_name.'{}}';

                        eval($str);
                    }

                    break;
                }
            }
        }

        if (class_exists($class_name,false))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 查找文件
     *
     *   //查找一个视图文件
     *   self::find_file('views','test',EXT);
     *
     * @param string $dir 目录
     * @param string $file 文件
     * @param string $ext 后缀 例如：.html
     * @return string
     */
    public static function find_file($dir, $file, $ext='')
    {
        $dir = trim($dir);
        $file = str_replace(array('/','\\'),DS,trim($file,' /\\'));

        if (!$ext)
        {
            if ($dir=='views')
            {
                $ext = '.view'.EXT;
            }
        }

        if ($ext && $ext[0]!='.')$ext='.'.$ext;

        foreach (self::$include_path as $path)
        {
            $tmpfile = $path.$dir.DS.$file.$ext;

            if (is_file($tmpfile))
            {
                return $tmpfile;
            }
        }
    }

    /**
     * 导入指定类库
     *
     * 导入的格式必须是类似 BigClass/SubClass 的形式，否则会抛出异常，例如: MyQEE/CMS , MyQEE/SAE 等等
     *
     * @param string $library_name 指定类库
     * @return boolean
     * @throws Exception
     */
    public static function import_library($library_name)
    {
        if (!$library_name) return false;

        $library_name = strtolower(trim(str_replace('/', '\\', $library_name), ' \\'));

        if (!preg_match('#^[a-z_][a-z0-9_]*\\\[a-z_][a-z0-9_]*$#i', $library_name))
        {
            throw new Exception('指定的类“'.$library_name.'”不符合规范');
        }

        $ns = '\\library\\'.$library_name.'\\';

        if ( !isset(self::$include_path[$ns]) )
        {
            $dir = DIR_LIBRARY.str_replace('\\', DS, $library_name).DS;

            if (is_dir($dir))
            {
                if (self::$project)
                {
                    $appliction = array
                    (
                        '\\project\\'.self::$project.'\\' => array_shift(self::$include_path),
                        '\\'                              => array_shift(self::$include_path),
                    );
                }
                else
                {
                    $appliction = array( '\\' => array_shift(self::$include_path) );
                }

                # 加载配置（初始化）文件
                $config_file = $dir . 'config'.EXT;
                if (is_file($config_file))
                {
                    $include_file = function (&$config,$_file_)
                    {
                        include $_file_;
                    };
                    $include_file(self::$config , $config_file);
                }

                # 合并目录
                self::$include_path = array_merge($appliction, array($ns=>$dir), self::$include_path);

                return true;
            }
            else
            {
                return false;
            }
        }
        else
        {
            return true;
        }
    }

    /**
     * 根据用户名和密码获取一个hash
     *
     * @param string $username
     * @param string $password
     * @return string
     */
    public static function get_debug_hash( $username , $password )
    {
        $config_str = var_export(self::$config['core']['debug_open_password'], true);
        return md5($config_str . '_open$&*@debug' . $password . '_' . $username );
    }

    /**
     * 寻找控制器
     *
     * @return array
     */
    private function find_controller($uri)
    {
        $uri = strtolower('/' . trim($uri, ' /'));

        if (self::$config['core']['url_suffix'] && substr($uri,-strlen(self::$config['core']['url_suffix']))==self::$config['core']['url_suffix'])
        {
            $uri = substr($uri,0,-strlen(self::$config['core']['url_suffix']));
        }

        if ($uri != '/')
        {
            $uri_arr = explode('/', $uri);
        }
        else
        {
            $uri_arr = array('');
        }
        if (IS_DEBUG)
        {
            Core::debug()->log($uri,'find controller uri');
        }

        $include_path = self::$include_path;

        if (self::$project || self::$app)
        {
            # 如果是某个项目或是app模式，则不在application里寻找控制器
            unset($include_path['\\']);
        }

        # log
        $find_log = $find_path_log = array();

        # 控制器目录
        $controller_dir = 'controllers';

        /**
         * PHP 保留关键字
         *
         * @var array
         */
        $reserved_keywords = array(
            'and',
            'or',
            'xor',
            '__file__',
            'exception',
            '__line__',
            'array',
            'as',
            'break',
            'case',
            'class',
            'const',
            'continue',
            'declare',
            'default',
            'die',
            'do',
            'echo',
            'else',
            'elseif',
            'empty',
            'enddeclare',
            'endfor',
            'endforeach',
            'endif',
            'endswitch',
            'endwhile',
            'eval',
            'exit',
            'extends',
            'for',
            'foreach',
            'function',
            'global',
            'if',
            'include',
            'include_once',
            'isset',
            'list',
            'new',
            'print',
            'require',
            'require_once',
            'return',
            'static',
            'switch',
            'unset',
            'use',
            'var',
            'while',
            '__function__',
            '__class__',
            '__method__',
            'final',
            'php_user_filter',
            'interface',
            'implements',
            'extends',
            'public',
            'private',
            'protected',
            'abstract',
            'clone',
            'try',
            'catch',
            'throw',
            'cfunction',
            'this',
        );

        # 首先找到存在的目录
        $found_path = array();
        foreach ( $include_path as $ns => $path )
        {
            $tmp_str = $real_path = $real_class = '';
            $tmp_path = $path . self::$dir_setting['controller'][0];
            $ids = array();
            foreach ( $uri_arr as $uri_path )
            {
                if (is_numeric($uri_path))
                {
                    $real_uri_path = '_id';
                    $ids[] = $uri_path;
                }
                elseif ($uri_path == '_id')
                {
                    # 不允许直接在URL中使用_id
                    break;
                }
                elseif (preg_match('#[^a-z0-9_]#i', $uri_path))
                {
                    # 不允许非a-z0-9_的字符在控制中
                    break;
                }
                else
                {
                    $real_uri_path = $uri_path;
                }

                if (in_array($real_uri_path, $reserved_keywords))
                {
                    $real_uri_class = '_' . $real_uri_path;
                }
                else
                {
                    $real_uri_class = $real_uri_path;
                }

                $tmpdir = $tmp_path . $real_path . $real_uri_path . DS;
                if (IS_DEBUG)
                {
                    $find_path_log[] = Core::debug_path($tmpdir);
                }
                $real_path .= $real_uri_path . DS;
                $real_class .= $real_uri_class . DS;
                $tmp_str .= $uri_path . DS;

                if (is_dir($tmpdir))
                {
                    $found_path[$tmp_str][] = array(
                        $ns,
                        $tmpdir,
                        $real_class,
                        $ids
                    );
                }
                else
                {
                    break;
                }
            }
        }

        unset($ids);
        $found = null;

        # 寻找可能的文件
        if ($found_path)
        {
            # 调整优先级
            krsort($found_path);

            foreach ( $found_path as $path => $all_path )
            {
                $tmp_p = substr($uri, strlen($path));
                if ($tmp_p)
                {
                    $args = explode('/', substr($uri, strlen($path)));
                }
                else
                {
                    $args = array();
                }

                $the_id = array();
                $tmp_class = array_shift($args);

                if (0 === strlen($tmp_class))
                {
                    $tmp_class = 'index';
                }
                elseif (is_numeric($tmp_class))
                {
                    $the_id = array(
                        $tmp_class
                    );
                    $tmp_class = '_id';
                }
                elseif ($tmp_class == '_id')
                {
                    continue;
                }

                # 检查是否PHP保留关键字
                if (in_array($tmp_class, $reserved_keywords))
                {
                    $real_class = '_' . $tmp_class;
                }
                else
                {
                    $real_class = $tmp_class;
                }

                foreach ( $all_path as $tmp_arr )
                {
                    list($ns, $tmp_path, $real_path, $ids) = $tmp_arr;
                    $path_str = str_replace('/', '\\', ltrim($real_path, '/'));
                    $tmpfile = $tmp_path . $tmp_class . self::$dir_setting['controller'][1] . EXT;
                    if (IS_DEBUG)
                    {
                        $find_log[] = Core::debug_path($tmpfile);
                    }

                    if (is_file($tmpfile))
                    {
                        if ($the_id)
                        {
                            $ids = array_merge($ids, $the_id);
                        }
                        $found = array
                        (
                            'file'      => $tmpfile,
                            'namespace' => $ns,
                            'class'     => $path_str . $real_class,
                            'args'      => $args,
                            'ids'       => $ids,
                        );

                        break 2;
                    }
                }
            }
        }

        if (IS_DEBUG)
        {
            Core::debug()->group('find controller path');
            foreach ( $find_path_log as $value )
            {
                Core::debug()->log($value);
            }
            Core::debug()->groupEnd();

            Core::debug()->group('find controller file');
            foreach ( $find_log as $value )
            {
                Core::debug()->log($value);
            }
            Core::debug()->groupEnd();

            if ($found)
            {
                $found2 = $found;
                $found2['file'] = Core::debug_path($found2['file']);
                Core::debug()->log($found2,'found contoller');
            }
            else
            {
                Core::debug()->log($uri,'not found contoller');
            }
        }

        return $found;
    }


    private static function show_error($msg, array $values = null)
    {
        echo __($msg,$values);
        exit;
    }

    /**
     * 根据URL初始化
     */
    private static function _setup_by_url( & $request_mode )
    {
        # 处理base_url
        if (null === Bootstrap::$base_url && isset($_SERVER["SCRIPT_NAME"]) && $_SERVER["SCRIPT_NAME"])
        {
            $base_url_len = strrpos($_SERVER["SCRIPT_NAME"], '/');
            if ($base_url_len)
            {
                $base_url = substr($_SERVER["SCRIPT_NAME"], 0, $base_url_len);
                if (preg_match('#^(.*)/wwwroot$#', $base_url, $m))
                {
                    # 特殊处理wwwroot目录
                    $base_url = $m[1];
                    $base_url_len = strlen($base_url);
                }

                if (strtolower(substr($_SERVER['REQUEST_URI'], 0, $base_url_len)) == strtolower($base_url))
                {
                    Bootstrap::$base_url = $base_url;
                }
            }
        }

        if (isset($_SERVER['PATH_INFO']))
        {
            $pathinfo = $_SERVER["PATH_INFO"];
        }
        else
        {
            if (isset($_SERVER["PATH_TRANSLATED"]))
            {
                list($null, $pathinfo) = explode('index' . EXT, $_SERVER["PATH_TRANSLATED"], 2);
            }
            elseif (isset($_SERVER['REQUEST_URI']))
            {
                $request_uri = $_SERVER['REQUEST_URI'];

                if (Bootstrap::$base_url)
                {
                    $request_uri = substr($request_uri, strlen(Bootstrap::$base_url));
                }
                // 移除查询参数
                list($pathinfo) = explode('?', $request_uri, 2);
            }
            elseif (isset($_SERVER['PHP_SELF']))
            {
                $pathinfo = $_SERVER['PHP_SELF'];
            }
            elseif (isset($_SERVER['REDIRECT_URL']))
            {
                $pathinfo = $_SERVER['REDIRECT_URL'];
            }
            else
            {
                $pathinfo = false;
            }
        }

        # 过滤pathinfo传入进来的服务器默认页
        if (false !== $pathinfo && ($indexpagelen = strlen(Bootstrap::$config['core']['server_index_page'])) && substr($pathinfo, -1 - $indexpagelen) == '/' . Bootstrap::$config['core']['server_index_page'])
        {
            $pathinfo = substr($pathinfo, 0, -$indexpagelen);
        }
        $pathinfo = trim($pathinfo);

        if (!isset($_SERVER["PATH_INFO"]))
        {
            $_SERVER["PATH_INFO"] = $pathinfo;
        }

        Bootstrap::$path_info = $pathinfo;


        $get_path_info = function (& $url)
        {
            static $protocol = null,$protocol_len=0;
            if (null===$protocol)
            {
                if (!empty($_SERVER['HTTPS']) && filter_var($_SERVER['HTTPS'], FILTER_VALIDATE_BOOLEAN))
                {
                    $protocol = 'https://';
                    $protocol_len = 8;
                }
                else
                {
                    $protocol = 'http://';
                    $protocol_len = 7;
                }
            }

            $url = strtolower($url);

            # 结尾补/
            if (substr($url, -1) != '/') $url .= '/';

            if (substr($url, 0, $protocol_len) == $protocol)
            {
                $len = strlen($url);
                if (strtolower(substr($_SERVER["SCRIPT_URI"], 0, $len)) == $url)
                {
                    # 匹配到项目
                    return '/' . substr($_SERVER["SCRIPT_URI"], $len);
                }
            }
            else
            {
                # 开头补/
                if (substr($url, 0, 1) != '/') $url = '/' . $url;
                $len = strlen($url);

                if (strtolower(substr(Bootstrap::$path_info, 0, $len)) == $url)
                {
                    # 匹配到项目
                    return '/' . substr(Bootstrap::$path_info, $len);
                }
            }

            return false;
        };

        # 项目相关设置
        if (isset(self::$config['core']['projects']) && is_array(self::$config['core']['projects']) && self::$config['core']['projects'])
        {
            # 处理项目
            foreach ( self::$config['core']['projects'] as $project => $item )
            {
                if (!preg_match('#^[a-z0-9_]+$#i', $project)) continue;

                $admin_url = array();
                if (isset($item['admin_url']) && $item['admin_url'])
                {
                    if (!is_array($item['admin_url'])) $item['admin_url'] = array
                    (
                        $item['admin_url']
                    );

                    foreach ( $item['admin_url'] as $admin_url )
                    {
                        if (preg_match('#^http(s)?\://#i', $admin_url))
                        {
                            if (($path_info_admin = $get_path_info($admin_url)) != false)
                            {
                                self::$project   = $project;
                                self::$path_info = $path_info_admin;
                                self::$base_url  = $admin_url;
                                $request_mode    = 'admin';

                                break 2;
                            }
                        }
                        else
                        {
                            # /开头的后台URL
                            $admin_url[] = $admin_url;
                        }
                    }
                }

                if ($item['url'])
                {
                    if (!is_array($item['url'])) $item['url'] = array
                    (
                        $item['url']
                    );

                    foreach ( $item['url'] as $url )
                    {
                        if (($path_info = $get_path_info($url)) != false)
                        {
                            self::$project = $project;
                            self::$path_info = $path_info;
                            self::$base_url = $url;

                            if ($admin_url)
                            {
                                foreach ( $admin_url as $url2 )
                                {
                                    # 处理后台URL不是 http:// 或 https:// 开头的形式
                                    if (($path_info_admin = $get_path_info($url2)) != false)
                                    {
                                        self::$path_info = $path_info_admin;
                                        self::$base_url .= ltrim($url2, '/');
                                        $request_mode = 'admin';

                                        break 3;
                                    }
                                }
                            }

                            break 2;
                        }
                    }
                }
            }
        }

        if (self::$project)
        {
            $project_dir = DIR_PROJECT . self::$project . DS;
            if (!is_dir($project_dir))
            {
                self::show_error('not found the project: :project', array(
                    ':project' => self::$project
                ));
            }

            # 根据URL寻找到了项目
            self::$include_path = array_merge(array('\\project\\' . self::$project . '\\' => $project_dir), self::$include_path);
        }
        else
        {
            if (isset(self::$config['core']['url']['admin']) && self::$config['core']['url']['admin'] && ($path_info = $get_path_info(self::$config['core']['url']['admin'])) != false)
            {
                self::$path_info = $path_info;
                self::$base_url = self::$config['core']['url']['admin'];
                $request_mode = 'admin';
            }
            else
            {
                if (isset(self::$config['core']['apps_url']) && is_array(self::$config['core']['apps_url']) && self::$config['core']['apps_url'])
                {
                    foreach ( self::$config['core']['apps_url'] as $app => $urls )
                    {
                        if (!$urls) continue;
                        if (!preg_match('#^[a-z0-9_]+//[a-z0-9]+$#i', $app)) continue;

                        if (!is_array($urls)) $urls = array(
                            $urls
                        );
                        foreach ( $urls as $url )
                        {
                            if (($path_info = $get_path_info($url)) != false)
                            {
                                self::$app = $app;
                                self::$path_info = $path_info;
                                self::$base_url = $url;

                                break 2;
                            }
                        }
                    }
                }

                if (null===self::$app)
                {

                    # 没有相关应用
                    if (isset(self::$config['core']['url']['apps']) && self::$config['core']['url']['apps'])
                    {
                        if (($path_info = $get_path_info(self::$config['core']['url']['apps'])) != false)
                        {
                            # 匹配到应用默认目录
                            $path_info = trim($path_info, '/');

                            self::$app = true;
                            if ($path_info)
                            {
                                $path_info_arr = explode('/', $path_info);

                                if (count($path_info_arr) >= 2)
                                {
                                    $app = array_shift($path_info_arr) . '/' . array_shift($path_info_arr);
                                    if (preg_match('#^[a-z0-9_]+//[a-z0-9]+$#i', $app))
                                    {
                                        $path_info = '/' . implode('/', $path_info_arr);
                                        self::$app = $app;
                                    }
                                }
                            }
                            self::$path_info = $path_info;
                            self::$base_url = self::$config['core']['url']['apps'];

                            $request_mode = 'app';
                        }
                    }
                }

                if (self::$app && true!==self::$app)
                {
                    # 已获取到APP
                    $app_dir = DIR_APPS . self::$app . DS;

                    if (!is_dir($app_dir))
                    {
                        self::show_error('can not found the app: :app', array(':app' => self::$app));
                    }

                    $request_mode = 'app';
                }
            }
        }
    }
}