<?php
namespace Core;

/**
 * 数据库核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Database
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Database
{
    public function test()
    {
        $t = new Database\Driver();

        $t->test();
    }
}