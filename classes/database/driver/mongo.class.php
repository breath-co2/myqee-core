<?php
namespace Core;

/**
 * 数据库Mongo驱动
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Database
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Database_Driver_Mongo extends Database_Driver
{
    /**
     * 记录当前连接所对应的数据库
     * @var array
     */
    protected static $_current_databases = array();

    /**
     * 记录当前数据库所对应的页面编码
     * @var array
     */
    protected static $_current_charset = array();

    /**
     * 链接寄存器
     * @var array
     */
    protected static $_connection_instance = array();

    /**
     * DB链接寄存器
     *
     * @var array
     */
    protected static $_connection_instance_db = array();

    /**
     * 记录connection id所对应的hostname
     * @var array
     */
    protected static $_current_connection_id_to_hostname = array();

    /**
     * 连接数据库
     *
     * $use_connection_type 默认不传为自动判断，可传true/false,若传字符串(只支持a-z0-9的字符串)，则可以切换到另外一个连接，比如传other,则可以连接到$this->_connection_other_id所对应的ID的连接
     *
     * @param boolean $use_connection_type 是否使用主数据库
     */
    public function connect($use_connection_type = null)
    {
        if (null!==$use_connection_type)
        {
            $this->_set_connection_type($use_connection_type);
        }

        $connection_id = $this->connection_id();

        # 最后检查连接时间
        static $last_check_connect_time = 0;

        if ( !$connection_id || !isset(static::$_connection_instance[$connection_id]) )
        {
            $this->_connect();
        }

        # 设置编码
        $this->set_charset($this->config['charset']);

        # 切换表
        $this->_select_db($this->config['connection']['database']);

        $last_check_connect_time = \time();
    }

    /**
     * 获取当前连接
     *
     * @return MongoDB
     */
    public function connection()
    {
        # 尝试连接数据库
        $this->connect();

        # 获取连接ID
        $connection_id = $this->connection_id();

        if ( $connection_id && isset(static::$_connection_instance_db[$connection_id]) )
        {
            return static::$_connection_instance_db[$connection_id];
        }
        else
        {
            throw new \Exception('数据库连接异常');
        }
    }

    protected function _connect()
    {
        $database = $hostname = $port = $socket = $username = $password = $persistent = null;
        \extract($this->config['connection']);

        if (!$port>0)
        {
            $port = 27017;
        }

        # 检查下是否已经有连接连上去了
        if ( static::$_connection_instance )
        {
            if (\is_array($hostname))
            {
                $hostconfig = $hostname[$this->_connection_type];
                if (!$hostconfig)
                {
                    throw new \Exception('指定的数据库连接主从配置中('.$this->_connection_type.')不存在，请检查配置');
                }
                if (!\is_array($hostconfig))
                {
                    $hostconfig = array($hostconfig);
                }
            }
            else
            {
                $hostconfig = array(
                    $hostname
                );
            }

            # 先检查是否已经有相同的连接连上了数据库
            foreach ( $hostconfig as $host )
            {
                $_connection_id = $this->_get_connection_hash($host, $port, $username);

                if ( isset(static::$_connection_instance[$_connection_id]) )
                {
                    $this->_connection_ids[$this->_connection_type] = $_connection_id;

                    return;
                }
            }

        }

        # 错误服务器
        static $error_host = array();

        while (true)
        {
            $hostname = $this->_get_rand_host($error_host);
            if (false===$hostname)
            {
                \Core::debug()->error($error_host,'error_host');

                throw new \Exception('数据库链接失败');
            }

            $_connection_id = $this->_get_connection_hash($hostname, $port, $username);
            static::$_current_connection_id_to_hostname[$_connection_id] = $hostname.':'.$port;

            for ($i=1; $i<=2; $i++)
            {
                # 尝试重连
                try
                {
                    $time = \microtime(true);

                    if ($username)
                    {
                        $tmplink = new \Mongo("mongodb://{$username}:{$password}@{$hostname}:{$port}/");
                    }
                    else
                    {
                        $tmplink = new \Mongo("mongodb://{$hostname}:{$port}/");
                    }

                    Core::debug()->info('MongoDB '.$hostname.':'.$port.' connection time:' . (\microtime(true) - $time));

                    # 连接ID
                    $this->_connection_ids[$this->_connection_type] = $_connection_id;
                    static::$_connection_instance[$_connection_id] = $tmplink;

                    unset($tmplink);

                    break 2;
                }
                catch ( \Exception $e )
                {
                    if (2==$i && !\in_array($hostname, $error_host))
                    {
                        $error_host[] = $hostname;
                    }

                    # 3毫秒后重新连接
                    \usleep(3000);
                }
            }
        }
    }

    /**
     * 关闭链接
     */
    public function close_connect()
    {
        if ($this->_connection_ids)foreach ($this->_connection_ids as $key=>$connection_id)
        {
            if ($connection_id && static::$_connection_instance[$connection_id])
            {
                \Core::debug()->info('close '.$key.' mongo '.static::$_current_connection_id_to_hostname[$connection_id].' connection.');
                static::$_connection_instance[$connection_id]->close();

                # 销毁对象
                static::$_connection_instance[$connection_id] = null;
                static::$_connection_instance_db[$connection_id] = null;

                unset(static::$_connection_instance[$connection_id]);
                unset(static::$_connection_instance_db[$connection_id]);
                unset(static::$_current_databases[$connection_id]);
                unset(static::$_current_charset[$connection_id]);
                unset(static::$_current_connection_id_to_hostname[$connection_id]);
            }
            else
            {
                \Core::debug()->info($key.' mongo '.static::$_current_connection_id_to_hostname[$connection_id].' connection has closed.');
            }

            $this->_connection_ids[$key] = null;
        }
    }

    /**
     * 切换表
     *
     * @param string Database
     * @return void
     */
    protected function _select_db($database)
    {
        if (!$database)return;

        $connection_id = $this->connection_id();

        if (!$connection_id || !isset(static::$_current_databases[$connection_id]) || $database!=static::$_current_databases[$connection_id])
        {
            if (!static::$_connection_instance[$connection_id])
            {
                $this->connect();
                $this->_select_db($database);
                return;
            }

            $connection = static::$_connection_instance[$connection_id]->selectDB($database);
            if (!$connection)
            {
                throw new \Exception('选择Mongo数据表错误');
            }
            else
            {
                static::$_connection_instance_db[$connection_id] = $connection;
            }

            if ( \IS_DEBUG )
            {
                \Core::debug()->log('mongodb change to database:'.$database);
            }

            # 记录当前已选中的数据库
            static::$_current_databases[$connection_id] = $database;
        }
    }

    public function compile($builder, $type = 'selete')
    {
        $where = array();
        if ( ! empty($builder['where']) )
        {
            $where = $this->_compile_conditions($builder['where'], $builder['parameters']);
        }

        if ( $type=='insert' )
        {
            $sql = array
            (
                'type'    => 'insert',
                'table'   => $builder['table'],
                'options' => array
                (
                    'safe' => true,
                ),
            );

            if ( \count($builder['values'])>1 )
            {
                foreach ($builder['columns'] as $key=>$field)
                {
                    foreach ($builder['values'] as $k=>$v)
                    {
                        $data[$k][$field] = $builder['values'][$k][$key];
                    }
                }
                $sql['datas'] = $data;
            }
            else
            {
                foreach ($builder['columns'] as $key=>$field)
                {
                    $data[$field] = $builder['values'][0][$key];
                }
                $sql['data'] = $data;
            }
        }
        elseif ( $type == 'update' )
        {
            $sql = array
            (
                'type'    => 'update',
                'table'   => $builder['table'],
                'where'   => $where,
                'options' => array
                (
                    'multiple' => true,
                    'safe'     => true,
                ),
            );
            foreach ($builder['set'] as $item)
            {
                if ( $item[2]=='+' )
                {
                    $op = '$inc';
                }
                elseif  ( $item[2]=='-' )
                {
                    $item[1] = - $item[1];
                    $op = '$inc';
                }
                else
                {
                    $op = '$set';
                }
                $sql['data'][$op][$item[0]] = $item[1];
            }
        }
        else
        {
            $sql = array
            (
                'type'  => $type,
                'table' => $builder['from'][0],
                'where' => $where,
                'limit' => $builder['limit'],
                'skip'  => $builder['offset'],
            );

            // 查询
            if ( $builder['select'] )
            {
                foreach ($builder['select'] as $item)
                {
                    if ( \is_string($item) )
                    {
                        $item = \trim($item);
                        if ( \preg_match('#^(.*) as (.*)$#', $item , $m) )
                        {
                            $s[$m[1]] = $m[2];
                            $sql['select_as'][$m[1]] = $m[2];
                        }
                        else
                        {
                            $s[$item] = 1;
                        }
                    }
                    elseif (\is_object($item))
                    {
                        if ($item instanceof \Database_Expression)
                        {
                            $s[$item->value()] = 1;
                        }
                        elseif ($item instanceof \MongoCode)
                        {
                            $sql['code'] = $item;
                        }
                        elseif (\method_exists($item, '__toString'))
                        {
                            $s[(string)$item] = 1;
                        }
                    }
                }

                $sql['select'] = $s;
            }

            // 排序
            if ( $builder['order_by'] )
            {
                foreach ($builder['order_by'] as $item)
                {
                    $sql['sort'][$item[0]] = $item[1]=='DESC'?-1:1;
                }
            }

            // group by
            if ( $builder['group_by'] )
            {
                foreach ($builder['group_by'] as $item)
                {
                    $sql['group']['keys'][$item] = true;
                }

                if ($sql['code'] && $sql['code'] instanceof \MongoCode)
                {
                    $reduce = '('.(string)$sql['code'].')(obj,prve);';
                }
                else
                {
                    $reduce = '';
                }
                $reduce = new \MongoCode('function(obj,prve){prve._count++;'.$reduce.'}');

                $sql['group']['reduce'] = $reduce;


                $sql['group']['option'] = array();

                if ($sql['where'])
                {
                    if (\is_object($sql['where']) && $sql['where'] instanceof \MongoCode)
                    {
                        $sql['group']['option']['finalize'] = $sql['where'];
                    }
                    else
                    {
                        $sql['group']['option']['condition'] = $sql['where'];
                    }
                }
            }
        }

        return $sql;
    }

    public function set_charset($charset)
    {

    }

    public function escape($value)
    {
        return $value;
    }

    public function quote_table($value)
    {
        return $value;
    }

    public function quote($value)
    {
        return $value;
    }

    /**
     * 执行查询
     *
     * 目前支持插入、修改、保存（类似mysql的replace）查询
     *
     * $use_connection_type 默认不传为自动判断，可传true/false,若传字符串(只支持a-z0-9的字符串)，则可以切换到另外一个连接，比如传other,则可以连接到$this->_connection_other_id所对应的ID的连接
     *
     * @param array $options
     * @param string $as_object 是否返回对象
     * @param boolean $use_master 是否使用主数据库，不设置则自动判断
     * @return Database_Driver_Mongo_Result
     */
    public function query($options, $as_object = null, $use_connection_type = null)
    {
        if (\IS_DEBUG)\Core::debug()->log($options);

        if (\is_string($options))
        {
            # 设置连接类型
            $this->_set_connection_type($use_connection_type);

            // 必需数组
            if (!\is_array($as_object))$as_object = array();
            return $this->connection()->execute($options,$as_object);
        }

        $type = \strtoupper($options['type']);

        $typeArr = array
        (
            'SELECT',
            'SHOW',     //显示表
            'EXPLAIN',  //分析
            'DESCRIBE', //显示结结构
            'INSERT',
            'REPLACE',
            'SAVE',
            'UPDATE',
            'REMOVE',
        );

        if (!\in_array($type, $typeArr))
        {
            $type = 'MASTER';
        }
        $slaverType = array('SELECT', 'SHOW', 'EXPLAIN');
        if ( $type!='MASTER' && \in_array($type, $slaverType) )
        {
            if ( true===$use_connection_type )
            {
                $use_connection_type = 'master';
            }
            else if (\is_string($use_connection_type))
            {
                if (!\preg_match('#^[a-z0-9_]+$#i',$use_connection_type))$use_connection_type = 'master';
            }
            else
            {
                $use_connection_type = 'slaver';
            }
        }
        else
        {
            $use_connection_type = 'master';
        }

        # 设置连接类型
        $this->_set_connection_type($use_connection_type);

        # 连接数据库
        $connection = $this->connection();

        $tablename = $this->config['table_prefix'] . $options['table'];

        switch ( $type )
        {
            case 'SELECT':
                if ($options['group'])
                {
                    # group by
                    $result = $connection->selectCollection($tablename)->group($options['group']['keys'],array('_count'=>0),$options['group']['reduce'],$options['group']['option']);

                    if ($result && $result['ok']==1)
                    {
                        return new \Database_Driver_Mongo_Result(new \ArrayIterator($result['retval']), $options, $as_object ,$this->config );
                    }
                    else
                    {
                        throw new \Exception($result['errmsg']);
                    }
                }
                else
                {
                    $last_query = 'db.'.$tablename.'.find(';
                    $last_query .= $options['where']?\json_encode($options['where']):'{}';
                    $last_query .= $options['select']?','.\json_encode($options['select']):'';
                    $last_query .= ')';

                    if( \IS_DEBUG )
                    {
                        static $is_sql_debug = null;

                        if ( null === $is_sql_debug ) $is_sql_debug = (bool)\Core::debug()->profiler('sql')->is_open();

                        if ( $is_sql_debug )
                        {
                            $host = $this->_get_hostname_by_connection_hash($this->connection_id());
                            $benchmark = \Core::debug()->profiler('sql')->start('Database','mongodb://'.($host['username']?$host['username'].'@':'') . $host['hostname'] . ($host['port'] && $host['port'] != '27017' ? ':' . $host['port'] : ''));
                        }
                    }

                    $result = $connection->selectCollection($tablename)->find($options['where'],(array)$options['select']);

                    if ( $options['limit'] )
                    {
                        $last_query .= '.limit('.\json_encode($options['limit']).')';
                        $result = $result->limit($options['limit']);
                    }

                    if ( $options['skip'] )
                    {
                        $last_query .= '.skip('.\json_encode($options['skip']).')';
                        $result = $result->skip($options['skip']);
                    }
                    if ( $options['sort'] )
                    {
                        $last_query .= '.sort('.\json_encode($options['sort']).')';
                        $result = $result->sort($options['sort']);
                    }

                    $this->last_query = $last_query;

                    # 记录调试
                    if( \IS_DEBUG )
                    {
                        \Core::debug()->info($last_query,'MongoDB');

                        if ( isset($benchmark) )
                        {
                            if ( $is_sql_debug )
                            {
                                $data = array();
                                $data[0]['db']              = $host['hostname'] . '/' . $this->config['connection']['database'] . '/';
                                $data[0]['cursor']          = '';
                                $data[0]['nscanned']        = '';
                                $data[0]['nscannedObjects'] = '';
                                $data[0]['n']               = '';
                                $data[0]['millis']          = '';
                                $data[0]['row']             = \count($result);
                                $data[0]['query']           = '';
                                $data[0]['nYields']         = '';
                                $data[0]['nChunkSkips']     = '';
                                $data[0]['isMultiKey']      = '';
                                $data[0]['indexOnly']       = '';
                                $data[0]['indexBounds']     = '';

                                if ( $type=='SELECT' )
                                {
                                    $re = $result->explain();
                                    foreach ($re as $k=>$v)
                                    {
                                        $data[0][$k] = $v;
                                    }
                                }

                                $data[0]['query'] = $last_query;
                            }
                            else
                            {
                                $data = null;
                            }

                            \Core::debug()->profiler('sql')->stop($data);
                        }
                    }

                    return new \Database_Driver_Mongo_Result($result, $options, $as_object ,$this->config );
                }
            case 'UPDATE':
                $result = $connection->selectCollection($tablename)->update($options['where'] , $options['data'] , $options['options']);
                return $result['n'];
            case 'SAVE':
            case 'INSERT':
                $fun = \strtolower($type);
                $result = $connection->selectCollection($tablename)->$fun($options['data'] , $options['options']);
                if ( isset($options['data']['_id']) && $options['data']['_id'] instanceof \MongoId )
                    return array
                    (
                        (string)$options['data']['_id'] ,
                        1 ,
                    );
                else
                {
                    return array
                    (
                        '',
                        0,
                    );
                }
            case 'REMOVE':
                $result = $connection->selectCollection($tablename)->remove($options['data'] , $options['options']);
                return $result['n'];
            default:
                throw new \Exception('不支持的操作类型');
        }
    }

    /**
     * Compiles an array of conditions into an SQL partial. Used for WHERE
     * and HAVING.
     *
     * @param   object  Database instance
     * @param   array   condition statements
     * @return  string
     */
    protected function _compile_conditions(array $conditions, $parameters)
    {
        $last_condition = null;
        $sql = array();
        $query = array();
        $open_q = false;

        foreach ( $conditions as $group )
        {
            // Process groups of conditions
            foreach ( $group as $logic => $condition )
            {
                if ( $condition === '(' )
                {
                    if (!$query)
                    {
                        $query = $sql;
                        unset($sql);
                        $sql = array();
                    }

                    if ( $last_condition !== '(' )
                    {
                        $query['$'.\strtolower($logic)] = & $sql;
                        $open_q = true;
                    }
                }
                elseif ( $condition === ')' )
                {
                    // 删除引用关系
                    unset($sql);
                    $sql = array();
                    $open_q = false;
                }
                else
                {
                    if ('OR'==$logic)
                    {
                        // 增加引用关系
                        $tmp_sql =& $sql;            //首先将$sql变量给$tmp_sql
                        unset($sql);                 //删除$sql变量引用
                        if (!isset($tmp_sql['$or']))$tmp_sql['$or'] = array();
                        $sql =& $tmp_sql['$or'];     //下面的$sql都放在$tmp_sql['$or']下
                    }

                    list ( $column, $op, $value ) = $condition;
                    $op = \strtolower($op);

                    if ( $op === 'between' && \is_array($value) )
                    {
                        list ( $min, $max ) = $value;

                        if ( \is_string($min) && \array_key_exists($min, $parameters) )
                        {
                            $min = $parameters[$min];
                        }
                        $sql[$column]['$gte'] = $min;

                        if ( \is_string($max) && \array_key_exists($max, $parameters) )
                        {
                            $max = $parameters[$max];
                        }
                        $sql[$column]['$lte'] = $max;
                    }
                    elseif ($op==='=')
                    {
                        if (\is_object($value))
                        {
                            if ($value instanceof \MongoCode)
                            {
                                $sql[$column]['$where'] = $value;
                            }
                            else
                            {
                                $sql[$column] = $value;
                            }
                        }
                        else
                        {
                            $sql[$column] = $value;
                        }
                    }
                    elseif ($op==='in')
                    {
                        $sql[$column] = array('$in'=>$value);
                    }
                    elseif ($op==='not in')
                    {
                        $sql[$column] = array('$nin'=>$value);
                    }
                    elseif ($op==='like')
                    {
                        // 将like转换成正则处理
                        $value = \preg_quote($value,'/');

                        if ( \substr($value,0,1)=='%' )
                        {
                            $value = '/' . \substr($value,1);
                        }
                        else
                        {
                            $value = '/^'.$value;
                        }

                        if (\substr($value,-1)=='%')
                        {
                            $value = \substr($value,0,-1) . '/';
                        }
                        else
                        {
                            $value = $value.'$/';
                        }

                        $value = \str_replace('%','*',$value);

                        $sql[$column] = new \MongoRegex($value);
                    }
                    else
                    {
                        $op_arr = array
                        (
                            '>'  => 'gt',
                            '>=' => 'gte',
                            '<'  => 'lt',
                            '<=' => 'lte',
                            '!=' => 'ne',
                            '<>' => 'ne',
                            '%'  => 'mod',
                        );
                        if ( isset($op_arr[$op]) )
                        {
                            $sql[$column]['$'.$op_arr[$op]] = $value;
                        }
                    }

                    if ('OR'==$logic)
                    {
                        # 解除引用关系
                        unset($sql);        //删除$sql
                        $sql =& $tmp_sql;   //将引用关系重新给$sql
                        unset($tmp_sql);    //删除$tmp_sql
                    }
                }

                $last_condition = $condition;
            }
        }

        if ( $sql && !$open_q )
        {
            $query = \array_merge($query,$sql);
        }

        return $query;
    }

}