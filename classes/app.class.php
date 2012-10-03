<?php
namespace Core;

/**
 * APP操作核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class App
{
    /**
     * 根据URI获取APP控制器
     *
     * @param string $app 指定APP
     * @param string $uri
     */
    public static function find_controller($app,$uri)
    {
        $uri = \strtolower('/' . \trim($uri, ' /'));

        if ($uri != '/')
        {
            $uri_arr = \explode('/', $uri);
        }
        else
        {
            $uri_arr = array('');
        }
    }
}