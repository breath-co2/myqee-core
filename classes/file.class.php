<?php
namespace Core;

/**
 * 文件处理核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class File
{
    /**
     * 系统目录
     *
     * @var array
     */
    protected static $SYS_DIR = array(
        \DIR_CORE,
        \DIR_DATA,
        \DIR_LIBRARY,
        \DIR_SHELL,
        \DIR_TEMP,
        \DIR_WWWROOT,
        \DIR_SYSTEM,
    );

    /**
     * 创建一个文件
     *
     * @param string $file
     * @param $data
     * @return boolean
     */
    public static function create_file($file, $data ,$flags = null, $context = null)
    {
        if ( \Core::$system_run_mode )
        {
            $dir = \substr($file,0,(int)\strrpos(\str_replace('\\','/',$file), '/'));
            if ( $dir && !\is_dir($dir) )
            {
                # 没有文件夹先则创建
                static::create_dir($dir);
            }

            if ( @\file_put_contents($file, $data , $flags , $context) )
            {
                @\mkdir($file, 0755);

                return true;
            }
            else
            {
                return false;
            }
        }
        else
        {
            $data = \Sync::exec('\\File::create_file',$file, $data , $flags , $context);

            foreach ($data as $rs)
            {
                if (!$rs->status || $rs->return!==true)return false;
            }

            return true;
        }
    }

    /**
     * 循环建立目录
     *
     * @param string $dir 待创建的文件夹
     * @param boolean $auto_create_default_file 新创建的文件夹，是否自动创建空默认页
     * @return boolean true/false
     */
    public static function create_dir($dir, $auto_create_default_file = true)
    {
        if ( \Core::$system_run_mode )
        {
            if (!\is_dir($dir))
            {
                $temp = \explode('/', \str_replace('\\', '/', $dir) );
                $cur_dir = "";
                for( $i = 0; $i < \count($temp); $i ++ )
                {
                    $cur_dir .= $temp[$i] . "/";
                    if ( !\is_dir($cur_dir) )
                    {
                        if ( @\mkdir($cur_dir, 0755) )
                        {
                            if ($auto_create_default_file)static::create_file($cur_dir.'index.html', ' ');
                        }
                        else
                        {
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        else
        {
            $data = \Sync::exec('\\File::create_dir',$dir, $auto_create_default_file);
            foreach ($data as $rs)
            {
                if (!$rs->status || $rs->return!==true)return false;
            }
            return true;
        }
    }

    /**
     * 删除文件，支持多个文件
     *
     * @param string/array $file
     * @return boolean
     */
    public static function unlink($file)
    {
        if ( \Core::$system_run_mode )
        {
            try
            {
                if (\is_array($file))
                {
                    foreach ($file as $f)
                    {
                        if (\is_file($f))\unlink($f);
                    }
                }
                else
                {
                    if (\is_file($file))\unlink($file);
                }
                return true;
            }
            catch (\Exception $e)
            {
                return false;
            }
        }
        else
        {
            $data = \Sync::exec('\\File::unlink',$file);

            foreach ($data as $rs)
            {
                if (!$rs->status || $rs->return!==true)return false;
            }

            return true;
        }
    }

    /**
     * 循环删除目录下的所有目录和文件
     *
     * @param string $dirName
     * @return boolean
     */
    public static function remove_dir($dirName)
    {
        if (\Core::$system_run_mode)
        {
            if (!\is_dir($dirName))
            {
                return true;
            }

            $realpath = \realpath($dirName);

            if ( !$realpath || \in_array($realpath.\DS, static::$SYS_DIR) )
            {
                return false;
            }

            $handle = \opendir($dirName);
            while ( ($file = \readdir($handle)) !== false )
            {
                if ( $file != '.' && $file != '..' )
                {
                    $dir = $dirName . \DS . $file;
                    \is_dir($dir) ? static::remove_dir($dir) : @\unlink($dir);
                }
            }
            \closedir($handle);

            return @\rmdir($dirName);
        }
        else
        {
            $data = \Sync::exec('\\File::remove_dir',$dirName);

            foreach ($data as $rs)
            {
                if (!$rs->status || $rs->return!==true)return false;
            }
            return true;
        }
    }

    /**
     * 转移目录下的所有目录和文件
     *
     * @param string $fromdir  源文文件目录
     * @param string $todir  目标文件目录
     * @param boolean $autocoverageold 是否覆盖已有文件，true覆盖，false跳过
     * @return array($dook,$doerror)
     */
    public static function move_dir($fromdir, $todir, $autocoverageold = true)
    {
        if ( \Core::$system_run_mode )
        {
            $fromdir = \rtrim($fromdir, '/') . '/';
            $todir = \rtrim($todir, '/') . '/';
            if ( ! \is_dir($fromdir) || $fromdir == $todir ) return false;

            $files = \glob($fromdir . '*');
            $donum = array(0, 0);
            foreach ( $files as $path )
            {
                $tofile = $todir . \basename($path);
                if ( \is_dir($path) )
                {
                    static::create_dir($tofile,false);
                    $donum2 = static::move_dir($path, $tofile, $autocoverageold);
                    if ( $donum2 )
                    {
                        $donum[0] += $donum2[0];
                        $donum[1] += $donum2[1];
                    }
                }
                else
                {
                    if ( $autocoverageold && \file_exists($tofile) )
                    {
                        //覆盖已有文件
                        \unlink($tofile);
                    }
                    if ( \rename($path, $tofile) )
                    {
                        $donum[0]++;
                    }
                    else
                    {
                        $donum[1]++;
                    }
                }
            }
            //移除旧目录
            static::remove_dir($fromdir);

            return $donum;
        }
        else
        {
            $data = \Sync::exec('\\File::move_dir', $fromdir, $todir, $autocoverageold);

            foreach ($data as $rs)
            {
                if (!$rs->status || $rs->return!==true)return false;
            }

            return true;
        }
    }


    /**
     * 复制目录下的所有目录和文件到另外一个目录
     *
     * @param string $fromdir  源文文件目录
     * @param string $todir  目标文件目录
     * @param boolean $autocoverageold 是否覆盖已有文件，true覆盖，false跳过
     * @return array($dook,$doerror)
     */
    public static function copy_dir($fromdir, $todir, $autocoverageold = true)
    {
        if ( \Core::$system_run_mode )
        {
            $fromdir = \rtrim($fromdir, '/') . '/';
            $todir = \rtrim($todir, '/') . '/';
            if (!\is_dir($fromdir) || $fromdir == $todir)return false;

            $files = \glob($fromdir . '*');
            $donum = array(0, 0);
            foreach ( $files as $path )
            {
                $tofile = $todir . \basename($path);
                if (\is_dir($path))
                {
                    static::create_dir($tofile,false);
                    $donum2 = static::copy_dir($path, $tofile, $autocoverageold);
                    if ($donum2)
                    {
                        $donum[0] += $donum2[0];
                        $donum[1] += $donum2[1];
                    }
                }
                else
                {
                    if ( $autocoverageold && \file_exists($tofile) )
                    {
                        //覆盖已有文件
                        \unlink($tofile);
                    }
                    if (\copy($path, $tofile))
                    {
                        $donum[0]++;
                    }
                    else
                    {
                        $donum[1]++;
                    }
                }
            }

            return $donum;
        }
        else
        {
            $data = \Sync::exec('\\File::copy_dir', $fromdir, $todir, $autocoverageold);

            foreach ($data as $rs)
            {
                if (!$rs->status || $rs->return!==true)return false;
            }

            return true;
        }
    }

	/**
	 * 返回指定文件类型
	 *
	 *     $mime = static::mime($file);
	 *
	 * @param   string  file name or path
	 * @return  string  mime type on success
	 * @return  FALSE   on failure
	 */
	public static function mime($filename)
	{
		// Get the complete path to the file
		$filename = \realpath($filename);

		// Get the extension from the filename
		$extension = \strtolower(\pathinfo($filename, \PATHINFO_EXTENSION));

		if (\preg_match('/^(?:jpe?g|png|[gt]if|bmp|swf)$/', $extension))
		{
			// Use getimagesize() to find the mime type on images
			$file = \getimagesize($filename);

			if (isset($file['mime']))
			{
				return $file['mime'];
			}
		}

		if (\class_exists('\\finfo', false))
		{
			if ($info = new \finfo(\defined('FILEINFO_MIME_TYPE') ? \FILEINFO_MIME_TYPE : \FILEINFO_MIME))
			{
				return $info->file($filename);
			}
		}

		if (\ini_get('mime_magic.magicfile') && \function_exists('\\mime_content_type'))
		{
			// The mime_content_type function is only useful with a magic file
			return \mime_content_type($filename);
		}

		if ( !empty($extension))
		{
			return static::mime_by_ext($extension);
		}

		// Unable to find the mime-type
		return false;
	}

	/**
	 * Return the mime type of an extension.
	 *
	 *     $mime = static::mime_by_ext('png'); // "image/png"
	 *
	 * @param   string  extension: php, pdf, txt, etc
	 * @return  string  mime type on success
	 * @return  FALSE   on failure
	 */
	public static function mime_by_ext($extension)
	{
		// Load all of the mime types
		$mimes = \Core::config('mimes');

		return isset($mimes[$extension])?$mimes[$extension][0]:false;
	}

	/**
	 * Lookup MIME types for a file
	 *
	 * @see Kohana_static::mime_by_ext()
	 * @param string $extension Extension to lookup
	 * @return array Array of MIMEs associated with the specified extension
	 */
	public static function mimes_by_ext($extension)
	{
		// Load all of the mime types
		$mimes = \Core::config('mimes');

		return isset($mimes[$extension])?((array)$mimes[$extension]):array();
	}

	/**
	 * Lookup file extensions by MIME type
	 *
	 * @param   string  $type File MIME type
	 * @return  array   File extensions matching MIME type
	 */
	public static function exts_by_mime($type)
	{
		static $types = array();

		// Fill the static array
		if (empty($types))
		{
			foreach (\Core::config('mimes') as $ext => $mimes)
			{
				foreach ($mimes as $mime)
				{
					if ($mime == 'application/octet-stream')
					{
						// octet-stream is a generic binary
						continue;
					}

					if (!isset($types[$mime]))
					{
						$types[$mime] = array( (string)$ext );
					}
					elseif (!\in_array($ext, $types[$mime]))
					{
						$types[$mime][] = (string)$ext;
					}
				}
			}
		}

		return isset($types[$type])?$types[$type]:false;
	}

	/**
	 * Lookup a single file extension by MIME type.
	 *
	 * @param   string  $type  MIME type to lookup
	 * @return  mixed          First file extension matching or false
	 */
	public static function ext_by_mime($type)
	{
		return \current(static::exts_by_mime($type));
	}

	/**
	 * Split a file into pieces matching a specific size. Used when you need to
	 * split large files into smaller pieces for easy transmission.
	 *
	 *     $count = static::split($file);
	 *
	 * @param   string   file to be split
	 * @param   string   directory to output to, defaults to the same directory as the file
	 * @param   integer  size, in MB, for each piece to be
	 * @return  integer  The number of pieces that were created
	 */
	public static function split($filename, $piece_size = 10)
	{
        if ( Core::$system_run_mode )
        {
    		// Open the input file
    		$file = \fopen($filename, 'rb');

    		// Change the piece size to bytes
    		$piece_size = \floor($piece_size * 1024 * 1024);

    		// Write files in 8k blocks
    		$block_size = 1024 * 8;

    		// Total number of peices
    		$peices = 0;

    		while (!\feof($file))
    		{
    			// Create another piece
    			$peices += 1;

    			// Create a new file piece
    			$piece = \str_pad($peices, 3, '0', \STR_PAD_LEFT);
    			$piece = \fopen($filename.'.'.$piece, 'wb+');

    			// Number of bytes read
    			$read = 0;

    			do
    			{
    				// Transfer the data in blocks
    				\fwrite($piece, \fread($file, $block_size));

    				// Another block has been read
    				$read += $block_size;
    			}
    			while ($read < $piece_size);

    			// Close the piece
    			\fclose($piece);
    		}

    		// Close the file
    		\fclose($file);

    		return $peices;
        }
        else
        {
            $data = \Sync::exec('\\File::split',$filename, $piece_size);

            foreach ($data as $rs)
            {
                if (!$rs->status || $rs->return!==true)return false;
            }

            return true;
        }
	}

	/**
	 * Join a split file into a whole file. Does the reverse of [static::split].
	 *
	 *     $count = static::join($file);
	 *
	 * @param   string   split filename, without .000 extension
	 * @param   string   output filename, if different then an the filename
	 * @return  integer  The number of pieces that were joined.
	 */
	public static function join($filename)
	{
        if ( \Core::$system_run_mode )
        {
    		// Open the file
    		$file = \fopen($filename, 'wb+');

    		// Read files in 8k blocks
    		$block_size = 1024 * 8;

    		// Total number of peices
    		$pieces = 0;

    		while (\is_file($piece = $filename.'.'.\str_pad($pieces + 1, 3, '0', \STR_PAD_LEFT)))
    		{
    			// Read another piece
    			$pieces += 1;

    			// Open the piece for reading
    			$piece = \fopen($piece, 'rb');

    			while (!\feof($piece))
    			{
    				// Transfer the data in blocks
    				\fwrite($file, \fread($piece, $block_size));
    			}

    			// Close the peice
    			\fclose($piece);
    		}

    		return $pieces;
        }
        else
        {
            $data = \Sync::exec('\\File::join', $filename);

            foreach ($data as $rs)
            {
                if (!$rs->status || $rs->return!==true)return false;
            }

            return true;
        }
	}
}