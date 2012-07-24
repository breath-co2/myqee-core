<?php
namespace Core;

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
class Database_Driver_Mongo_Result extends \Database_Result
{
    protected $_data = array();

    public function __destruct()
    {

    }

    protected function total_count()
    {
        if ($this->_result instanceof \ArrayObject)
        {
            $count = $this->_result->count();
        }
        else
        {
            $count = $this->_result->count(true);
        }

        if (!$count>0)$count = 0;

        return $count;
    }

    public function seek($offset)
    {
        if ( $this->offsetExists($offset) )
        {
            if ( !($this->_result instanceof \ArrayObject) )
            {
                if ($this->_internal_row < $this->_current_row)
                {
                    $c = $this->_internal_row - $this->_current_row;
                    for( $i=0;$i<$c;$i++ )
                    {
                        $this->_result->next();
                    }
                }
                else
                {
                    // 小于当前指针，则回退重新来过，因为目前 MongoCursor 还没有回退的功能
                    $this->_result->rewind();
                    $c = $this->_current_row - $this->_internal_row;
                    for( $i=0;$i<$c;$i++ )
                    {
                        $this->_result->next();
                    }
                }
            }

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
        if ($this->_result instanceof \ArrayObject)
        {
            return \current($this->_result);
        }

        // 当有缓存数据
        if (isset($this->_data[$this->_current_row]))
        {
            return $this->_data[$this->_current_row];
        }

        $data = $this->_result->getNext();
        if ( isset($data['_id']) && \is_object($data['_id']) && $data['_id'] instanceof \MongoId )
        {
            $data['_id'] = (string)$data['_id'];
        }

        if ( isset($this->_query['select_as']) )foreach ($this->_query['select_as'] as $key=>$value)
        {
            // 对查询出的数据做select as转换
            if ( isset($data[$key]) )
            {
                $data[$value] = $data[$key];
                unset($data[$key]);
            }
        }

        if ( \count($this->_data[$this->_current_row])>1000 )
        {
            // 释放内存
            $this->_data[$this->_current_row] = array();
        }

        // 记录
        $this->_data[$this->_current_row] = $data;

        return $data;
    }

    /**
     * 使查询结果集不动态变化
     *
     * @return $this
     */
    public function snapshot()
    {
        if ($this->_result instanceof \ArrayObject)
        {

        }
        else
        {
            $this->_result->snapshot();
        }

        return $this;
    }
}