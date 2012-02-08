<?php
namespace Core;

/**
 * 语言包处理核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Database
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class I18n
{
    protected static $is_setup = false;

    private static $_cache = array();

    protected static $lang = array();

    public static function setup()
    {
        if (!\IS_CLI)
        {
            # 客户端语言包
            $accept_language = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

            # 匹配语言设置
            if (\preg_match_all('#,([a-z]+\-[a-z]+);#i',$accept_language,$matches))
            {
                $accept_language = $matches[1];
                $accept_language =  \array_slice($accept_language,0,2);    //只取前3个语言设置
                \array_map('\\strtolower',$accept_language);

                if (isset(\Bootstrap::$config['core']['lang']) && !\in_array(\Bootstrap::$config['core']['lang'],$accept_language))
                {
                    $accept_language[] = \Bootstrap::$config['core']['lang'];
                }
            }
            elseif (isset(\Bootstrap::$config['core']['lang']))
            {
                $accept_language = array(\Bootstrap::$config['core']['lang']);
            }
            else
            {
                $accept_language = array('zh-cn');
            }

            # 包含目录
            $include_path = \Bootstrap::$include_path;

            # 逆向排序，调整优先级
            \krsort($include_path);

            $lang_key = \implode(';',$accept_language);
            $cache_file = \DIR_CACHE.'lang_serialized_cache_for_'.$lang_key;

            if (\is_file($cache_file))
            {
                $changed = false;
                $last_mtime = \filemtime($cache_file);
                if ($last_mtime)
                {
                    foreach($accept_language as $lang)
                    {
                        foreach ($include_path as $path)
                        {
                            $file = $path.'i18n'.\DS.$lang.'.lang';
                            if (\is_file($file))
                            {
                                if ($last_mtime<\filemtime($file))
                                {
                                    $changed = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }

                # 没有修改过
                if (!$changed)
                {
                    static::$lang = (array)@\unserialize(\file_get_contents($cache_file));
                    return;
                }
            }

            # 获取语言文件
            $lang = array();
            foreach($accept_language as $l)
            {
                foreach ($include_path as $path)
                {
                    $file = $path.'i18n'.\DS.$l.'.lang';
                    if (\is_file($file))
                    {
                        $tmp_arr = @\parse_ini_file($file);
                        if ($tmp_arr)
                        {
                            $lang = \array_merge($lang,$tmp_arr);
                        }
                    }
                }
            }

            if (!\is_file($cache_file))
            {
                @\file_put_contents($cache_file, \serialize($lang));
            }

            static::$lang = $lang;
        }
    }

    /**
     * 返回一个语言包语句
     *
     * @param string $string
     * @return string
     */
    public static function get($string)
    {
        if (isset(self::$lang[$string]))
        {
            return self::$lang[$string];
        }

        # 初始化
        if (!static::$is_setup)
        {
            static::setup();
        }

        return isset(self::$lang[$string])?self::$lang[$string]:$string;
    }
}