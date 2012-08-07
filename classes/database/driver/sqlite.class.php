<?php
namespace Core;

/**
 * 数据库SQLite返回类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage Database
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Database_Driver_SQLite extends Database_Driver
{
    /**
     * MySQL使用反引号标识符
     *
     * @var string
     */
    protected $_identifier = '`';

    /**
     * 记录当前数据库所对应的页面编码
     *
     * @var array
     */
    protected static $_current_charset = array();

    /**
     * 链接寄存器
     *
     * @var array
     */
    protected static $_connection_instance = array();

    /**
     * 记录connection id所对应的DB
     *
     * @var array
     */
    protected static $_current_connection_id_to_db = array();

    protected $_connection_type = 'master';

    /**
     * 连接数据库
     *
     * $use_connection_type 默认不传为自动判断，可传true/false,若传字符串(只支持a-z0-9的字符串)，则可以切换到另外一个连接，比如传other,则可以连接到$this->_connection_other_id所对应的ID的连接
     *
     * @param boolean $use_connection_type 是否使用主数据库
     */
    public function connect()
    {
        $connection_id = $this->connection_id();

        if ( !$connection_id || !isset(static::$_connection_instance[$connection_id]) )
        {
            $this->_connect();
        }

        # 设置编码
        $this->set_charset($this->config['charset']);
    }

    /**
     * 获取当前连接
     *
     * @return sqlite
     */
    public function connection()
    {
        # 尝试连接数据库
        $this->connect();

        # 获取连接ID
        $connection_id = $this->connection_id();

        if ( $connection_id && isset(static::$_connection_instance[$connection_id]) )
        {
            return static::$_connection_instance[$connection_id];
        }
        else
        {
            throw new \Exception('数据库连接异常');
        }
    }

    protected function _connect()
    {
        $db = $persistent = null;

        \extract($this->config['connection']);

        # 检查下是否已经有连接连上去了
        if ( static::$_connection_instance )
        {
            $_connection_id = $this->_get_connection_hash($db);

            if ( isset(static::$_connection_instance[$_connection_id]) )
            {
                $this->_connection_ids[$this->_connection_type] = $_connection_id;

                return;
            }
        }

        # 错误服务器
        static $error_host = array();

        $last_error = null;

        for ($i=1; $i<=2; $i++)
        {
            # 尝试重连
            try
            {
                $_connection_id = $this->_get_connection_hash($db);
                static::$_current_connection_id_to_db[$_connection_id] = \Core::debug_path($db);

                $time = \microtime(true);
                if ($persistent)
                {
                    $tmplink = \sqlite_popen($db);
                }
                else
                {
                    $tmplink = \sqlite_open($db);
                }

                if (\IS_DEBUG)\Core::debug()->info('sqlite '.\Core::debug_path($db).' connection time:' . (\microtime(true) - $time));

                # 连接ID
                $this->_connection_ids[$this->_connection_type] = $_connection_id;
                static::$_connection_instance[$_connection_id] = $tmplink;

                unset($tmplink);

                break;
            }
            catch ( \Exception $e )
            {
                if ($i==2)
                {
                    throw $e;
                }

                $last_error = $e;

                # 3毫秒后重新连接
                \usleep(3000);
            }
        }
    }

    /**
     * 获取链接唯一hash
     *
     * @param string $file
     * @return string
     */
    protected function _get_connection_hash($file)
    {
        $hash = \sha1(\get_class($this).$file);
        \Database_Driver::$_hash_to_hostname[$hash] = \Core::debug_path($file);

        return $hash;
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
                \Core::debug()->info('close '.$key.' sqlite '.static::$_current_connection_id_to_db[$connection_id].' connection.');
                @\sqlite_close(static::$_connection_instance[$connection_id]);

                unset(static::$_connection_instance[$connection_id]);
                unset(static::$_current_charset[$connection_id]);
                unset(static::$_current_connection_id_to_db[$connection_id]);
            }
            else
            {
                \Core::debug()->info($key.' sqlite '.static::$_current_connection_id_to_db[$connection_id].' connection has closed.');
            }

            $this->_connection_ids[$key] = null;
        }
    }

    /**
     * 构建SQL语句
     */
    public function compile($builder, $type = 'selete')
    {
        if ( $type == 'selete' )
        {
            return $this->_compile_selete($builder);
        }
        else if ( $type == 'insert' )
        {
            return $this->_compile_insert($builder);
        }
        elseif ( $type == 'replace' )
        {
            return $this->_compile_insert($builder, 'REPLACE');
        }
        elseif ( $type == 'update' )
        {
            return $this->_compile_update($builder);
        }
        elseif ( $type == 'delete' )
        {
            return $this->_compile_delete($builder);
        }
        else
        {
            return $this->_compile_selete($builder);
        }
    }

    public function set_charset($charset)
    {
        if (!$charset)return;

        $connection_id = $this->connection_id();
        $connection = static::$_connection_instance[$connection_id];

        if (!$connection_id || !$connection)
        {
            $this->connect();
            $this->set_charset($charset);
            return;
        }

        if ( isset(static::$_current_charset[$connection_id]) && $charset==static::$_current_charset[$connection_id] )
        {
            return true;
        }

        $status = (bool)\sqlite_query('SET NAMES ' . $this->quote($charset), $connection);
        if ( $status === false )
        {
            throw new \Exception('Error:' . \sqlite_error_string($connection), \sqlite_last_error($connection));
        }

        # 记录当前设置的编码
        static::$_current_charset[$connection_id] = $charset;
    }

    public function escape($value)
    {
        $value = \sqlite_escape_string($value);
        return "'$value'";
    }

    /**
     * 查询
     *
     * $use_connection_type 默认不传为自动判断，可传true/false,若传字符串(只支持a-z0-9的字符串)，则可以切换到另外一个连接，比如传other,则可以连接到$this->_connection_other_id所对应的ID的连接
     *
     * @param string $sql 查询语句
     * @param string $as_object 是否返回对象
     * @return Database_Driver_SQLite_Result
     */
    public function query($sql, $as_object=null)
    {
        $sql = \trim($sql);

        if ( \preg_match('#^([a-z]+)(:? |\n|\r)#i',$sql,$m) )
        {
            $type = \strtoupper($m[1]);
        }

        # 连接数据库
        $connection = $this->connection();

        # 记录调试
        if( \IS_DEBUG )
        {
            \Core::debug()->info($sql,'SQLite');

            static $is_sql_debug = null;

            if ( null === $is_sql_debug ) $is_sql_debug = (bool)\Core::debug()->profiler('sql')->is_open();

            if ( $is_sql_debug )
            {
                $db = $this->_get_hostname_by_connection_hash($this->connection_id());
                $benchmark = \Core::debug()->profiler('sql')->start('Database', 'sqlite://'.$db);
            }
        }

        static $is_no_cache = null;
        if ( null === $is_no_cache ) $is_no_cache = (bool)Core::debug()->profiler('nocached')->is_open();
        //显示无缓存数据
        if ( $is_no_cache && \strtoupper(\substr($sql, 0, 6)) == 'SELECT' )
        {
            $sql = 'SELECT SQL_NO_CACHE' . \substr($sql, 6);
        }

        // Execute the query
        if ( ($result = \sqlite_query($sql, $connection)) === false )
        {
            if ( isset($benchmark) )
            {
                // This benchmark is worthless
                $benchmark->delete();
            }

            if ( \IS_DEBUG )
            {
                $err = 'Error:' . \sqlite_error_string($connection) . '. SQL:' . $sql;
            }
            else
            {
                $err = \sqlite_error_string($connection);
            }
            throw new \Exception($err, \sqlite_last_error($connection));
        }

        if ( isset($benchmark) )
        {
            # 在线查看SQL情况
            if ( $is_sql_debug )
            {
                $data = array();
                $data[0]['db']            = $db;
                $data[0]['select_type']   = '';
                $data[0]['table']         = '';
                $data[0]['key']           = '';
                $data[0]['key_len']       = '';
                $data[0]['Extra']         = '';
                $data[0]['query']         = '';
                $data[0]['type']          = '';
                $data[0]['id']            = '';
                $data[0]['row']           = \count($result);
                $data[0]['ref']           = '';
                $data[0]['all rows']      = '';
                $data[0]['possible_keys'] = '';

                if ( \strtoupper(\substr($sql,0,6))=='SELECT' )
                {
                    $re = \sqlite_query('EXPLAIN ' . $sql, $connection );
                    $i = 0;
                    while ( true == ($row = \sqlite_fetch_array($re , \SQLITE_NUM)) )
                    {
                        $data[$i]['select_type']      = (string)$row[1];
                        $data[$i]['table']            = (string)$row[2];
                        $data[$i]['key']              = (string)$row[5];
                        $data[$i]['key_len']          = (string)$row[6];
                        $data[$i]['Extra']            = (string)$row[9];
                        if ($i==0) $data[$i]['query'] = '';
                        $data[$i]['type']             = (string)$row[3];
                        $data[$i]['id']               = (string)$row[0];
                        $data[$i]['ref']              = (string)$row[7];
                        $data[$i]['all rows']         = (string)$row[8];
                        $data[$i]['possible_keys']    = (string)$row[4];
                        $i ++;
                    }
                }

                $data[0]['query'] = $sql;
            }
            else
            {
                $data = null;
            }

            \Core::debug()->profiler('sql')->stop($data);
        }

        // Set the last query
        $this->last_query = $sql;

        if ( $type === 'INSERT' || $type === 'REPLACE' )
        {
            // Return a list of insert id and rows created
            return array
            (
                \sqlite_last_insert_rowid($connection),
                \sqlite_changes($connection)
            );
        }
        elseif ( $type === 'UPDATE' || $type === 'DELETE' )
        {
            // Return the number of rows affected
            return \sqlite_changes($connection);
        }
        else
        {
            // Return an iterator of results
            return new \Database_Driver_SQLite_Result( $result, $sql, $as_object ,$this->config );
        }
    }

    public function quote($value)
    {
        if ( $value === null )
        {
            return 'NULL';
        }
        elseif ( $value === true )
        {
            return "'1'";
        }
        elseif ( $value === false )
        {
            return "'0'";
        }
        elseif ( \is_object($value) )
        {
            if ( $value instanceof \Database )
            {
                // Create a sub-query
                return '(' . $value->compile() . ')';
            }
            elseif ( $value instanceof \Database_Expression )
            {
                // Use a raw expression
                return $value->value();
            }
            else
            {
                // Convert the object to a string
                return $this->quote((string)$value);
            }
        }
        elseif ( \is_array($value) )
        {
            return '(' . \implode(', ', \array_map(array($this, __FUNCTION__), $value)) . ')';
        }
        elseif ( \is_int($value) )
        {
            return "'".(int)$value."'";
        }
        elseif ( \is_float($value) )
        {
            // Convert to non-locale aware float to prevent possible commas
            return \sprintf('%F', $value);
        }

        return $this->escape($value);
    }

    /**
     * Quote a database table name and adds the table prefix if needed.
     *
     * $table = $db->quote_table($table);
     *
     * @param   mixed   table name or array(table, alias)
     * @return  string
     * @uses    Database::_quote_identifier
     * @uses    Database::table_prefix
     */
    public function quote_table($value,$auto_as_table=false)
    {
        // Assign the table by reference from the value
        if ( \is_array($value) )
        {
            $table = & $value[0];
        }
        else
        {
            $table = & $value;
        }

        if ( $this->config['table_prefix'] && \is_string($table) && \strpos($table, '.') === false )
        {
            if ( \stripos($table,' AS ')!==false )
            {
                $table = $this->config['table_prefix'] . $table;
            }
            else
            {
                $table = $this->config['table_prefix'] . $table . ($auto_as_table?' AS '.$table:'');
            }
        }


        return $this->_quote_identifier($value);
    }

    protected function _quote_identifier($column)
    {
        if (\is_array($column))
        {
            list($column, $alias) = $column;
        }

        if ( \is_object($column) )
        {
            if ( $column instanceof \Database )
            {
                // Create a sub-query
                $column = '(' . $column->compile() . ')';
            }
            elseif ( $column instanceof \Database_Expression )
            {
                // Use a raw expression
                $column = $column->value();
            }
            else
            {
                // Convert the object to a string
                $column = $this->_quote_identifier((string)$column);
            }
        }
        else
        {
			# 转换为字符串
            $column = \trim((string)$column);

            if ( \preg_match('#^(.*) AS (.*)$#i',$column,$m) )
            {
                $column = $m[1];
                $alias  = $m[2];
            }

            if ($column === '*')
            {
                return $column;
            }
            elseif (\strpos($column, '"') !== false)
            {
                // Quote the column in FUNC("column") identifiers
                $column = \preg_replace('/"(.+?)"/e', '$this->_quote_identifier("$1")', $column);
            }
            elseif (\strpos($column, '.') !== false)
            {
                $parts = \explode('.', $column);

                $prefix = $this->config['table_prefix'];
                if ($prefix)
                {
                    // Get the offset of the table name, 2nd-to-last part
                    $offset = \count($parts) - 2;

                    if ( !$this->_as_table || !\in_array($parts[$offset],$this->_as_table) )
                    {
                        $parts[$offset] = $prefix . $parts[$offset];
                    }
                }

                foreach ($parts as & $part)
                {
                    if ($part !== '*')
                    {
                        // Quote each of the parts
                        $part = $this->_identifier.$part.$this->_identifier;
                    }
                }

                $column = \implode('.', $parts);
            }
            else
            {
                $column = $this->_identifier.$column.$this->_identifier;
            }
        }

        if ( isset($alias) )
        {
            $column .= ' AS '.$this->_identifier.$alias.$this->_identifier;
        }

		# 切换编码
		$this->_change_charset($column);

        return $column;
    }

    protected function _compile_selete($builder)
    {
        // Callback to quote identifiers
        $quote_ident = array($this, '_quote_identifier');

        // Callback to quote tables
        $quote_table = array($this, 'quote_table');

        // Start a selection query
        $query = 'SELECT ';

        if ( $builder['distinct'] === true )
        {
            // Select only unique results
            $query .= 'DISTINCT ';
        }

        $this->_init_as_table($builder);

        if ( empty($builder['select']) )
        {
            // Select all columns
            $query .= '*';
        }
        else
        {
            // Select all columns
            $query .= \implode(', ', \array_unique(\array_map($quote_ident, $builder['select'])));
        }

        if ( !empty($builder['from']) )
        {
            // Set tables to select from
            $query .= ' FROM ' . \implode(', ', \array_unique(\array_map($quote_table, $builder['from'],array(true))));
        }

        if ( !empty($builder['index']) )
        {
            foreach ( $builder['index'] as $item )
            {
                $query .= ' '.\strtoupper($item[1]).' INDEX('.$this->_quote_identifier($item[0]).')';
            }
        }

        if ( !empty($builder['join']) )
        {
            // Add tables to join
            $query .= ' ' . $this->_compile_join($builder['join']);
        }

        if ( !empty($builder['where']) )
        {
            // Add selection conditions
            $query .= ' WHERE ' . $this->_compile_conditions($builder['where'], $builder['parameters']);
        }

        if ( !empty($builder['group_by']) )
        {
            // Add sorting
            $query .= ' GROUP BY ' . \implode(', ', \array_map($quote_ident, $builder['group_by']));
        }

        if ( !empty($builder['having']) )
        {
            // Add filtering conditions
            $query .= ' HAVING ' . $this->_compile_conditions($builder['having'], $builder['parameters']);
        }

        if ( !empty($builder['order_by']) )
        {
            // Add sorting
            $query .= ' ' . $this->_compile_order_by($builder['order_by']);
        }
        elseif ( $builder['where'] )
        {
            # 如果查询中有in查询，采用自动排序方式
            $in_query = null;
            foreach ( $builder['where'] as $item )
            {
                if ( isset($item['AND']) && $item['AND'][1] == 'in' )
                {
                    if ( \count($item['AND'][1]) > 1 )
                    {
                        # 大于1项才需要排序
                        $in_query = $item['AND'];
                    }
                    break;
                }
            }
            if ( $in_query )
            {
                $query .= ' ORDER BY FIELD(' . $this->_quote_identifier($in_query[0]) . ', ' . \implode(', ', $this->quote($in_query[2])) . ')';
            }
        }

        if ( $builder['limit'] !== null )
        {
            // Add limiting
            $query .= ' LIMIT ' . $builder['limit'];
        }

        if ( $builder['offset'] !== null )
        {
            // Add offsets
            $query .= ' OFFSET ' . $builder['offset'];
        }

        return $query;
    }

    /**
     * Compile the SQL query and return it.
     *
     * @return  string
     */
    protected function _compile_insert($builder, $type = 'INSERT')
    {
        if ( $type != 'REPLACE' )
        {
            $type = 'INSERT';
        }
        // Start an insertion query
        $query = $type . ' INTO ' . $this->quote_table($builder['table'],false);

        // Add the column names
        $query .= ' (' . \implode(', ', \array_map(array($this, '_quote_identifier'), $builder['columns'])) . ') ';

        if ( \is_array($builder['values']) )
        {
            // Callback for quoting values
            $quote = array($this, 'quote');

            $groups = array();
            foreach ( $builder['values'] as $group )
            {
                foreach ( $group as $i => $value )
                {
                    if ( \is_string($value) && isset($builder['parameters'][$value]) )
                    {
                        // Use the parameter value
                        $group[$i] = $builder['parameters'][$value];
                    }
                }

                $groups[] = '(' . \implode(', ', \array_map($quote, $group)) . ')';
            }

            // Add the values
            $query .= 'VALUES ' . \implode(', ', $groups);
        }
        else
        {
            // Add the sub-query
            $query .= (string)$builder['values'];
        }

        if ( $type == 'REPLACE' )
        {
            //where
            if ( !empty($builder['where']) )
            {
                // Add selection conditions
                $query .= ' WHERE ' . $this->_compile_conditions($builder['where'], $builder['parameters']);
            }
        }

        return $query;
    }

    protected function _compile_update($builder)
    {
        // Start an update query
        $query = 'UPDATE ' . $this->quote_table($builder['table'],false);

        // Add the columns to update
        $query .= ' SET ' . $this->_compile_set($builder['set'], $builder['parameters']);

        if ( !empty($builder['where']) )
        {
            // Add selection conditions
            $query .= ' WHERE ' . $this->_compile_conditions($builder['where'], $builder['parameters']);
        }

        if ( !empty($builder['order_by']) )
        {
            // Add sorting
            $query .= ' ' . $this->_compile_order_by($builder['order_by']);
        }

        if ( $builder['limit'] !== null )
        {
            // Add limiting
            $query .= ' LIMIT ' . $builder['limit'];
        }

        if ( $builder['offset'] !== null )
        {
            // Add offsets
            $query .= ' OFFSET ' . $builder['offset'];
        }

        return $query;
    }

    protected function _compile_delete($builder)
    {
        // Start an update query
        $query = 'DELETE FROM' . $this->quote_table($builder['table'],false);

        if ( !empty($builder['where']) )
        {
            $this->_init_as_table($builder);

            // Add selection conditions
            $query .= ' WHERE ' . $this->_compile_conditions($builder['where'], $builder['parameters']);
        }

        return $query;
    }

    /**
     * Compiles an array of ORDER BY statements into an SQL partial.
     *
     * @param   object  Database instance
     * @param   array   sorting columns
     * @return  string
     */
    protected function _compile_order_by(array $columns)
    {
        $sort = array();
        foreach ( $columns as $group )
        {
            list ( $column, $direction ) = $group;

            if ( !empty($direction) )
            {
                // Make the direction uppercase
                $direction = ' ' . \strtoupper($direction);
            }

            $sort[] = $this->_quote_identifier($column) . $direction;
        }

        return 'ORDER BY ' . \implode(', ', $sort);
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

        $sql = '';
        foreach ( $conditions as $group )
        {
            // Process groups of conditions
            foreach ( $group as $logic => $condition )
            {
                if ( $condition === '(' )
                {
                    if ( !empty($sql) && $last_condition !== '(' )
                    {
                        // Include logic operator
                        $sql .= ' ' . $logic . ' ';
                    }

                    $sql .= '(';
                }
                elseif ( $condition === ')' )
                {
                    $sql .= ')';
                }
                else
                {
                    if ( !empty($sql) && $last_condition !== '(' )
                    {
                        // Add the logic operator
                        $sql .= ' ' . $logic . ' ';
                    }

                    // Split the condition
                    list ( $column, $op, $value ) = $condition;

                    if ( $value === null )
                    {
                        if ( $op === '=' )
                        {
                            // Convert "val = NULL" to "val IS NULL"
                            $op = 'IS';
                        }
                        elseif ( $op === '!=' )
                        {
                            // Convert "val != NULL" to "valu IS NOT NULL"
                            $op = 'IS NOT';
                        }
                    }

                    // Database operators are always uppercase
                    $op = \strtoupper($op);

                    if ( \is_array($value) && \count($value)<=1 )
                    {
                        # 将in条件下只有1条数据的改为where方式
                        if ( $op == 'IN' )
                        {
                            $op = '=';
                            $value = \current($value);
                        }
                        elseif ( $op == 'NOT IN' )
                        {
                            $op = '!=';
                            $value = \current($value);
                        }
                    }

                    if ( $op === 'BETWEEN' && \is_array($value) )
                    {
                        // BETWEEN always has exactly two arguments
                        list ( $min, $max ) = $value;

                        if ( \is_string($min) && \array_key_exists($min, $parameters) )
                        {
                            // Set the parameter as the minimum
                            $min = $parameters[$min];
                        }

                        if ( \is_string($max) && \array_key_exists($max, $parameters) )
                        {
                            // Set the parameter as the maximum
                            $max = $parameters[$max];
                        }

                        // Quote the min and max value
                        $value = $this->quote($min) . ' AND ' . $this->quote($max);
                    }
                    elseif ($op == 'MOD')
                    {
                        $value = $this->quote($value[0]) .' '.\strtoupper($value[2]).' '. $this->quote($value[1]);
                    }
                    else
                    {
                        if ( \is_string($value) && \array_key_exists($value, $parameters) )
                        {
                            // Set the parameter as the value
                            $value = $parameters[$value];
                        }

                        // Quote the entire value normally
                        $value = $this->quote($value);
                    }

                    // Append the statement to the query
                    $sql .= $this->_quote_identifier($column) . ' ' . $op . ' ' . $value;
                }

                $last_condition = $condition;
            }
        }

        return $sql;
    }

    /**
     * Compiles an array of JOIN statements into an SQL partial.
     *
     * @param   object  Database instance
     * @param   array   join statements
     * @return  string
     */
    protected function _compile_join(array $joins)
    {
        $statements = array();

        foreach ( $joins as $join )
        {
            $statements[] = $this->_compile_join_on($join);
        }

        return \implode(' ', $statements);
    }

    protected function _compile_join_on($join)
    {
        if ( $join['type'] )
        {
            $sql = \strtoupper($join['type']) . ' JOIN';
        }
        else
        {
            $sql = 'JOIN';
        }

        // Quote the table name that is being joined
        $sql .= ' ' . $this->quote_table($join['table'],true) . ' ON ';

        $conditions = array();
        foreach ( $join['on'] as $condition )
        {
            // Split the condition
            list ( $c1, $op, $c2 ) = $condition;

            if ( $op )
            {
                // Make the operator uppercase and spaced
                $op = ' ' . \strtoupper($op);
            }

            // Quote each of the identifiers used for the condition
            $conditions[] = $this->_quote_identifier($c1) . $op . ' ' . $this->_quote_identifier($c2);
        }

        // Concat the conditions "... AND ..."
        $sql .= '(' . \implode(' AND ', $conditions) . ')';

        return $sql;
    }

    /**
     * Compiles an array of set values into an SQL partial. Used for UPDATE.
     *
     * @param   object  Database instance
     * @param   array   updated values
     * @return  string
     */
    protected function _compile_set(array $values, $parameters)
    {
        $set = array();
        foreach ( $values as $group )
        {
            // Split the set
            list ( $column, $value , $op ) = $group;

            if ( $op=='+' || $op=='-' )
            {
                $w_type = $op;
            }
            else
            {
                $w_type = '';
            }

            // Quote the column name
            $column = $this->_quote_identifier($column);

            if ( \is_string($value) && \array_key_exists($value, $parameters) )
            {
                // Use the parameter value
                $value = $parameters[$value];
            }

            if ( $w_type )
            {
                $set[$column] = $column . ' = ' . $column . ' ' . $w_type . ' ' . $this->quote($value);
            }
            else
            {
                $set[$column] = $column . ' = ' . $this->quote($value);
            }
        }

        return \implode(', ', $set);
    }


    /**
     * 初始化所有的as_table
     */
    protected function _init_as_table( $builder )
    {
        $this->_as_table = array();

        if ( $builder['from'] )
        {
            foreach ( $builder['from'] as $item )
            {
                $this->_do_init_as_table($item);
            }
        }

        if ( $builder['join'] )
        {
            foreach ( $builder['join'] as $item )
            {
                $this->_do_init_as_table($item['table']);
            }
        }
    }

    protected function _do_init_as_table($value)
    {
        if ( \is_array($value) )
        {
            list ( $value, $alias ) = $value;
        }
        elseif ( \is_object($value) )
        {
            if ( $value instanceof \Database )
            {
                $value = $value->compile();
            }
            elseif ( $value instanceof \Database_Expression )
            {
                $value = $value->value();
            }
            else
            {
                $value = (string)$value;
            }
        }
        $value = \trim($value);

        if ( \preg_match('#^(.*) AS ([a-z0-9`_]+)$#i', $value , $m) )
        {
            $alias = $m[2];
        }
        elseif ( $this->config['table_prefix'] && \strpos($value, '.') === false )
        {
            $alias = $value;
        }

        if ($alias)
        {
            $this->_as_table[] = $alias;
        }
    }
}