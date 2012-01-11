<?php
namespace Core\Database\Driver\MySQL;

/**
 * 数据库MySQL返回对象
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
            \mysql_free_result($this->_result);
        }
    }

    protected function total_count()
    {
        $count = @\mysql_num_rows($this->_result);
        if (!$count>0)$count = 0;

        return $count;
    }

    public function seek($offset)
    {
        if ( $this->offsetExists($offset) && \mysql_data_seek($this->_result, $offset) )
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
        return mysql_fetch_assoc($this->_result);
    }
}