<?php
namespace Core\OOP\ORM;

/**
 * ORM 索引核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage OOP
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
abstract class Index
{

    /**
     * 根据主键获取数据
     *
     * 支持如下格式：
     * get_by_primary($p1)
     * get_by_primary($p1,$p2,$p3...)
     * get_by_primary(array($p1,$p2,$p3...))
     *
     * @param int/array $primary_id 主键，支持数组
     * @return mixed
     */
    public function get_by_primary($primary_id)
    {
        $columns = \func_get_args();
    }

    public function build()
    {

    }

    /**
     * 重建索引
     */
    public function rebuild()
    {

    }
}