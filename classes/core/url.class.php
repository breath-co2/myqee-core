<?php
namespace Core;

/**
 * URL核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Core
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Core_Url
{
    public function __construct()
    {

    }

    /**
     * 获取站点URL
     *
     * @return string
     */
    public function site($uri='')
    {
        return $this->base() . \ltrim($uri, '/') . (\Bootstrap::$config['core']['url_suffix']?\Bootstrap::$config['core']['url_suffix']:'');
    }

    /**
     * 返回当前URL的BASE路径
     *
     * @param string $index
     * @return string
     */
    public function base()
    {
        return \Bootstrap::$base_url;
    }
}