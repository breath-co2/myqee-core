<?php
namespace Core;

/**
 * 视图核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class View
{

    protected $_file;

    protected $_data = array();

    protected static $_global_data = array();

    function __construct($file = null, array $data = null)
    {
        $this->set_filename($file);
        if ( \is_array($data) )
        {
            $this->_data = $data;
        }
    }

    /**
     * 设置视图文件名
     *
     * @param string $file
     */
    public function set_filename($file)
    {
        $realfile = \Core::find_file('views', $file);

        if ($realfile)
        {
            $this->_file = $realfile;
        }
        else
        {
            throw new \Exception('指定的视图“' . $file . '”不存在');
        }
    }

    /**
     * 构造视图
     *
     * @param string $file
     * @param array $data
     * @return \View
     */
    public static function factory($file = NULL, array $data = NULL)
    {
        return new \View($file, $data);
    }

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    public function & __get($key)
    {
        return $this->_data[$key];
    }

    /**
     * 设置一个引用变量
     *
     * @param string $key
     * @param mixed $value
     * @return \View
     */
    public function bind($key, & $value)
    {
        $this->_data[$key] = & $value;

        return $this;
    }

    /**
     * 设置变量
     *
     * @param string $key
     * @param mixed $value
     * @return \View
     */
    public function set($key, $value = NULL)
    {
        if ( \is_array($key) )
        {
            foreach ( $key as $name => $value )
            {
                $this->_data[$name] = $value;
            }
        }
        else
        {
            $this->_data[$key] = $value;
        }

        return $this;
    }

    /**
     * 设置全局视图变量
     *
     * @param string/array $key
     * @param mixed $value
     */
    public static function set_global($key, $value = null)
    {
        if ( \is_array($key) )
        {
            foreach ( $key as $k => $v )
            {
                static::$_global_data[$k] = $v;
            }
        }
        else
        {
            static::$_global_data[$key] = $value;
        }
    }

    /**
     * 执行视图
     *
     * @param boolean $print 是否直接输出
     * @throws \Exception
     */
    public function render($print = true)
    {

        if ( empty($this->_file) )
        {
            throw new \Exception('You must set the file to use within your view before rendering');
        }

        // Combine local and global data and capture the output
        $output = static::capture($this->_file, $this->_data);

        if ( $print )
        {
            echo $output;
        }
        else
        {
            return $output;
        }
    }

    public function __toString()
    {
        try
        {
            return $this->render(false);
        }
        catch ( \Exception $e )
        {
            return '';
        }
    }

    /**
     * 输出tag支持
     *
     * View::tag('test');
     * View::tag('test','arg1','arg2');
     *
     * @param string $keystr
     */
    public static function tag($keystr,$arg1=null,$arg2=null,$arg3=null)
    {
        // 获取当前的Tag
        $tags = static::get_tag($keystr);

        if ( $tags )
        {
            $args = \func_get_args();    // 获取所有参数
            \array_shift($args);         // 将第一个参数移除

            foreach ($tags as $tag)
            {
                $tag->run($args);
            }
        }
    }

    /**
     * 获取指定key的Tag
     *
     * @param string $keystr
     * @return array
     */
    public static function get_tag($keystr)
    {
        # 判断是否调试输出
        if ( null===_view_tag_current::$debug )
        {
            _view_tag_current::$debug = ( \Core::$local_debug && \Core::debug()->profiler('view_tag')->is_open() )?true:false;
        }

        if ( _view_tag_current::$debug )
        {
            # 如果是调试输出，则直接构造出对象
            $data = array(
                new _view_tag_current($keystr,null),
            );
            return $data;
        }

        # 获取所有视图TAG
        static $tags = null;
        if ( null===$tags )
        {
            $tags = \Core::config('view.tags');
            if (!$tags || !\is_array($tags))$tags = array();
            foreach ($tags as & $tag)
            {
                if (!\is_array($tag))$tag = array($tag);
            }
        }

        if ( isset($tags[$keystr]) )
        {
            $data = array();
            foreach ($tags[$keystr] as $item)
            {
                $data[] = new _view_tag_current($keystr,$item);
            }

            return $data;
        }
        else
        {
            return array();
        }
    }

    protected static function capture($myqee_view_filename, array $myqee_view_data)
    {
        // Import the view variables to local namespace
        \extract($myqee_view_data, \EXTR_SKIP);

        if ( static::$_global_data )
        {
            // Import the global view variables to local namespace and maintain references
            \extract(static::$_global_data, \EXTR_REFS);
        }

        // Capture the view output
        \ob_start();

        try
        {
            // Load the view within the current scope
            require $myqee_view_filename;
        }
        catch ( \Exception $e )
        {
            // Delete the output buffer
            \ob_end_clean();

            // Re-throw the exception
            throw $e;
        }

        // Get the captured output and close the buffer
        return \ob_get_clean();
    }
}

class _view_tag_current
{
    private $tag_name;

    private $tag;

    public static $debug = null;

    public function __construct($tag_name,$tag)
    {
        $this->tag_name = $tag_name;
        $this->tag = $tag;
    }

    /**
     * 执行
     *
     * $this->run();
     * $this->run('arg1','arg2');
     */
    public function run($c = null)
    {
        if ( \is_array($c) && \func_num_args()==1 )
        {
            $args = $c;
        }
        else
        {
            $args = \func_get_args();    // 获取所有参数
        }

        if (self::$debug)
        {
            echo '{{tag:'.$this->tag_name.'('.\implode(',', $args).')}}';
            return ;
        }

        try
        {
            if ( \is_callable($this->tag) )
            {
                \call_user_func_array($this->tag, $args);
            }
            else
            {
                \Core::debug()->error(\__('Function :s can not callable.',array(':s'=>$this->tag)));
            }
        }
        catch (\Exception $e)
        {
            \Core::debug()->error(\__('Run function :s error.',array(':s'=>$this->tag)));
        }
    }
}