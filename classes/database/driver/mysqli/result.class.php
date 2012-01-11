<?php
namespace Core\Database\Driver\MySQLI;

/**
 * 数据库MySQLI返回对象
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Database
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Result extends \Database\Result
{

    protected $_internal_row = 0;

    public function __destruct()
    {
        if ( \is_resource($this->_result) )
        {
            \mysqli_free_result($this->_result);
        }
    }

    protected function total_count()
    {
        $count = @\mysqli_num_rows($this->_result);
        if (!$count>0)$count = 0;

        return $count;
    }

    public function seek($offset)
    {
        if ( $this->offsetExists($offset) && \mysqli_data_seek($this->_result, $offset) )
        {
            // Set the current row to the offset
            $this->_current_row = $this->_internal_row = $offset;

            return true;
        }
        else
        {
            return false;
        }
    }

    protected function fetch_assoc()
    {
        return \mysqli_fetch_assoc($this->_result);
    }
}