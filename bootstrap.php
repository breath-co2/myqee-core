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
     * 目录设置
     *
     * @var array
     */
    private static $dir_setting = array
    (
        'classes'    => array('classes'     , '.class'),
        'controller' => array('controllers' , '.controller'),
        'admin'      => array('admin'       , '.admin'),
        'shell'      => array('shell'       , '.shell'),
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

            # 检查PHP版本
            if ( version_compare(PHP_VERSION, '5.3' ,'<') )
            {
                self::show_error('本系统必须运行在PHP5.3以上，您当前的PHP版本太低，为:'.PHP_VERSION.'，无法运行本系统。');
            }

            # 注册自动加载类
            spl_autoload_register(array('Bootstrap','auto_load'));

            # 读取配置
            if (!is_file(DIR_SYSTEM.'config.php'))
            {
                self::show_error('Please rename the file config.new.php to config.php');
            }
            $config = array();

            $include_config_file = function ( & $config, $file )
            {
                include $file;
            };
            $include_config_file($config,DIR_SYSTEM.'config.php');

            self::$config = $config;

            if (!isset(self::$config['core']['charset']))self::$config['core']['charset'] = 'utf-8';

            if ( !IS_CLI )
            {
                # 输出文件头
                header('Content-Type: text/html;charset='.self::$config['core']['charset']);
            }

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

            # 如果有autoload目录，则设置加载目录
            if ( isset(self::$config['core']['libraries']['autoload']) && is_array(self::$config['core']['libraries']['autoload']) && self::$config['core']['libraries']['autoload'] )
            {
                foreach (self::$config['core']['libraries']['autoload'] as $library_name)
                {
                    if (!$library_name)continue;

                    try
                    {
                        self::import_library($library_name);
                    }
                    catch (Exception $e)
                    {
                        self::show_error($e->getMessage());
                    }
                }
            }

            Core::setup();
        }

        if ($auto_execute)
        {
            $get_pathinfo = function()
            {
                # 处理base_url
                if (null===Bootstrap::$base_url && isset($_SERVER["SCRIPT_NAME"])&&$_SERVER["SCRIPT_NAME"])
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

                        if (strtolower(substr($_SERVER['REQUEST_URI'], 0, $base_url_len))==strtolower($base_url))
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
                        list($null, $pathinfo) = explode('index.php', $_SERVER["PATH_TRANSLATED"], 2);
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
                if ( false!==$pathinfo && ($indexpagelen = strlen(Bootstrap::$config['core']['server_index_page'])) && substr($pathinfo, -1-$indexpagelen) == '/'.Bootstrap::$config['core']['server_index_page'] )
                {
                    $pathinfo = substr($pathinfo, 0, -$indexpagelen);
                }
                $pathinfo = trim($pathinfo);

                if (!isset($_SERVER["PATH_INFO"]))
                {
                    $_SERVER["PATH_INFO"] = $pathinfo;
                }

                return $pathinfo;
            };

            $path_info = $get_pathinfo();
            unset($get_pathinfo);

            if (!IS_CLI)ob_start();

            self::execute($path_info);

            if (!IS_CLI)
            {
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
        $uri = '/'.trim($uri,' /');
        if ($uri!=='/')
        {
            $uri_arr = explode('/',$uri);
        }
        else
        {
            $uri_arr = array('');
        }
        $include_path = self::$include_path;

        # log
        $find_log = array();

        # 首先找到存在的目录
        $found_path = array();
        foreach($include_path as $ns=>$path)
        {
            $tmp_str = '';
            $tmp_path = $path.'controllers';
            foreach ($uri_arr as $uri_path)
            {
                $tmpdir = $tmp_path.$tmp_str.$uri_path.DS;
                $find_log[] = $tmpdir;
                $tmp_str.=$uri_path.DS;

                if (is_dir($tmpdir))
                {
                    $found_path[$tmp_str][] = array($ns,$tmpdir);
                }
                else
                {
                    break;
                }
            }
        }

        $found = null;

        # 寻找可能的文件
        if ($found_path)
        {
            # 调整优先级
            krsort($found_path);

            foreach ($found_path as $path=>$all_path)
            {
                $tmp_p = substr($uri,strlen($path));
                if ($tmp_p)
                {
                    $args = explode('/',substr($uri,strlen($path)));
                }
                else
                {
                    $args = array();
                }

                $id = null;
                $tmp_class = array_shift($args);

                if ( 0===strlen($tmp_class) )
                {
                    $tmp_class = 'index';
                }
                elseif ( is_numeric($tmp_class) )
                {
                    $id = $tmp_class;
                    $tmp_class = '_id';
                }

                $path_str = str_replace('/','\\',ltrim($path,'/'));

                foreach ($all_path as $tmp_arr)
                {
                    list($ns,$tmp_path) = $tmp_arr;
                    $tmpfile = $tmp_path.$tmp_class.'.controller.php';
                    $find_log[] = $tmpfile;

                    if (is_file($tmpfile))
                    {
                        $found = array
                        (
                            'file'      => $tmpfile,
                            'class'     => $path_str.$tmp_class,
                            'args'      => $args,
                            'id'        => $id,
                            'namespace' => $ns,
                        );
                        break 2;
                    }
                }
            }
        }

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
                    $action_name = array_shift($arguments);
                    if (0===strlen($action_name))
                    {
                        $action_name = 'default';
                    }
                }
                else
                {
                    $action_name = 'index';
                }

                $action_name = 'action_'.$action_name;

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

                $ispublicmethod = new ReflectionMethod($controller,$action_name);

                if (!$ispublicmethod->isPublic())
                {
                    Core::show_500('Request Method Not Allowed.',405);
                }

                # 将参数传递给控制器
                $controller->action = $action_name;
                $controller->controller = $found['class'];
                $controller->arguments = $arguments;
                $controller->id = $found['id'];

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
                case 'app':
                    if (count($class_arr)<4)
                    {
                        # 扩展类库和APP必须是4位及以上，比如 Library\MyQEE\CMS\Test
                        return false;
                    }
                    $top_name_space = array_shift($class_arr);
                    $sub_name_space = array_shift($class_arr).DS;
                    $sub_name_space.= array_shift($class_arr).DS;
                default;
                    break;
            }

            if ( count($class_arr)>1 && in_array($class_arr[0],array('controller','shell','model','orm',)) )
            {
                $class_dir = array_shift($class_arr);
            }
        }

        static $dir_array = array
        (
            ''        => DIR_APPLICATION,
            'library' => DIR_LIBRARY,
            'core'    => DIR_CORE,
            'app'     => DIR_APPS,
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
     *   Bootstrap::find_file('views','test',EXT);
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
     * @throws \Exception
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
                # 开发目录
                $appliction = array( '\\' => array_shift(self::$include_path) );

                # 加载配置（初始化）文件
                $config = $dir . 'config'.EXT;
                if (is_file($config))
                {
                    $include_file = function ($file)
                        {
                        include $file;
                    };

                    $include_file($config);
                }

                # 合并目录
                self::$include_path =array_merge($appliction, array($ns=>$dir), self::$include_path);

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

    private static function show_error($msg)
    {
        echo __($msg);
        exit;
    }
}