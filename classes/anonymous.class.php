<?php
namespace Core;

/**
 * 匿名对象
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Anonymous extends \stdClass
{
    public function __call($method,$args)
    {
        if ( $this->$method )
        {
            $function = $this->$method;

            $count = count($args);
            switch ($count)
            {
                case 0:
                    return $function();
                case 1:
                    return $function($args[0]);
                case 2:
                    return $function($args[0], $args[1]);
                case 3:
                    return $function($args[0], $args[1], $args[2]);
                case 4:
                    return $function($args[0], $args[1], $args[2], $args[3]);
                default:
                    return call_user_func_array($function, $args);
            }
        }
    }
}