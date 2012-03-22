<?php
namespace Core\Controller;

/**
 * 内部调用文件操作控制器
 *
 * 这个控制器在正常情况下是不可以访问得到的，只有在内部调用时才会访问的到
 * 当执行File::create_file()等方法时，如果存在多个服务器需要进行同步操作，则系统会通过内部调用的方式请求到这个控制器进行操作
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Controller
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class File extends \Controller
{

    /**
     * 内部调用保存文件
     *
     */
    public function action_create_file()
    {
        # 目录
        $dir = $this->arguments[0];

        if ( !isset(\File::$dir[$dir]) )
        {
            # 目录不允许操作
            static::show_error('目录不允许操作');
        }

        if (!$this->arguments[1])
        {
            static::show_error('缺少参数');
        }

        # 文件
        $filename = \File::$dir[$dir].$this->arguments[1];

        # 内容
        $data = $this->arguments[2];

        if ( \File::create_file($filename , $data , $this->arguments[3] , $this->arguments[4]) )
        {
            static::show_message('success',null,1);
        }
        else
        {
            # 记录错误日志
            \Core::log('create file('.$filename.') error.','error');
            static::show_error('执行失败');
        }
    }

    /**
     * 内部调用创建目录
     *
     */
    public function action_create_dir()
    {
        # 目录
        $dir = $this->arguments[0];

        if ( !isset(\File::$dir[$dir]) )
        {
            # 目录不允许操作
            static::show_error('目录不允许操作');
        }

        # 目录
        $the_dir = \File::$dir[$dir].$this->arguments[1];

        if ( \File::create_dir($the_dir,$this->arguments[2]) )
        {
            static::show_message('success',null,1);
        }
        else
        {
            # 记录错误日志
            \Core::log('create dir('.$the_dir.') error.','error');
            static::show_error('执行失败');
        }
    }

    /**
     * 内部调用删除文件
     *
     */
    public function action_unlink()
    {
        # 目录
        $dir = $this->arguments[0];

        if ( !isset(\File::$dir[$dir]) )
        {
            # 目录不允许操作
            static::show_error('目录不允许操作');
        }

        if (!$this->arguments[1])
        {
            static::show_error('缺少参数');
        }

        # 文件
        $file = \File::$dir[$dir].$this->arguments[1];

        if ( \File::unlink($file) )
        {
            static::show_message('success',null,1);
        }
        else
        {
            # 记录错误日志
            \Core::log('delete file('.$file.') error.','error');
            static::show_error('执行失败');
        }
    }

    /**
     * 内部调用删除目录
     *
     */
    public function action_remove_dir()
    {
        # 目录
        $dir = $this->arguments[0];

        if ( !isset(\File::$dir[$dir]) )
        {
            # 目录不允许操作
            static::show_error('目录不允许操作');
        }

        if (!$this->arguments[1])
        {
            static::show_error('缺少参数');
        }

        # 目录
        $the_dir = \File::$dir[$dir].$this->arguments[1];

        if ( \File::remove_dir($the_dir) )
        {
            static::show_message('success',null,1);
        }
        else
        {
            # 记录错误日志
            \Core::log('remove dir('.$the_dir.') error.','error');
            static::show_error('执行失败');
        }
    }

    /**
     * 内部调用移动目录
     *
     */
    public function action_move_dir()
    {
        # 目录
        $from_dir = $this->arguments[0];
        $to_dir   = $this->arguments[2];

        if ( !isset(\File::$dir[$from_dir]) )
        {
            # 目录不允许操作
            static::show_error('目录不允许操作');
        }

        if ( !isset(\File::$dir[$to_dir]) )
        {
            # 目录不允许操作
            static::show_error('目录不允许操作');
        }

        # 目录
        $the_from_dir = \File::$dir[$from_dir].$this->arguments[1];
        $the_to_dir   = \File::$dir[$to_dir]  .$this->arguments[3];

        if ( \File::move_dir($the_from_dir,$the_to_dir,$this->arguments[4]) )
        {
            static::show_message('success',null,1);
        }
        else
        {
            # 记录错误日志
            \Core::log('move dir('.$from_dir.')to dir('.$to_dir.') error.','error');
            static::show_error('执行失败');
        }
    }

    /**
     * 内部调用移动目录
     *
     */
    public function action_copy_dir()
    {
        # 目录
        $from_dir = $this->arguments[0];
        $to_dir   = $this->arguments[2];

        if ( !isset(\File::$dir[$from_dir]) )
        {
            # 目录不允许操作
            static::show_error('目录不允许操作');
        }

        if ( !isset(\File::$dir[$to_dir]) )
        {
            # 目录不允许操作
            static::show_error('目录不允许操作');
        }

        # 目录
        $the_from_dir = \File::$dir[$from_dir].$this->arguments[1];
        $the_to_dir   = \File::$dir[$to_dir]  .$this->arguments[3];

        if ( \File::copy_dir($the_from_dir,$the_to_dir,$this->arguments[4]) )
        {
            static::show_message('success',null,1);
        }
        else
        {
            # 记录错误日志
            \Core::log('copy dir('.$from_dir.')to dir('.$to_dir.') error.','error');
            static::show_error('执行失败');
        }
    }


    /**
     * 内部调用分割文件
     *
     */
    public function action_split()
    {
        # 目录
        $dir = $this->arguments[0];

        if ( !isset(\File::$dir[$dir]) )
        {
            # 目录不允许操作
            static::show_error('目录不允许操作');
        }

        if (!$this->arguments[1])
        {
            static::show_error('缺少参数');
        }

        # 文件
        $filename = \File::$dir[$dir].$this->arguments[1];

        if ( \File::split($filename , $this->arguments[2]) )
        {
            static::show_message('success',null,1);
        }
        else
        {
            # 记录错误日志
            \Core::log('split file('.$filename.') error.','error');
            static::show_error('执行失败');
        }
    }

    /**
     * 内部调用合并文件
     *
     */
    public function action_join()
    {
        # 目录
        $dir = $this->arguments[0];

        if ( !isset(\File::$dir[$dir]) )
        {
            # 目录不允许操作
            static::show_error('目录不允许操作');
        }

        if (!$this->arguments[1])
        {
            static::show_error('缺少参数');
        }

        # 文件
        $filename = \File::$dir[$dir].$this->arguments[1];

        if ( \File::join($filename) )
        {
            static::show_message('success',null,1);
        }
        else
        {
            # 记录错误日志
            \Core::log('join file('.$filename.') error.','error');
            static::show_error('执行失败');
        }
    }

}