<?php
namespace Core;

/**
 * 系统内部调用核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class HttpHost
{
    protected $group;

    protected $hosts = array();

    public function __construct($group=null)
    {
        if (!$group)$group = 'default';

        $this->group = $group;
        $this->hosts = \Core::config('core.web_server_list.'.$group);

        if (!$this->hosts)
        {
            $this->hosts = array
            (
                $_SERVER["REMOTE_ADDR"].':'.$_SERVER["SERVER_PORT"],
            );
        }
    }

    /**
     * 返回HttpServer实例化对象
     *
     * @param string $group 分组，不传则为默认default
     * @return \HttpHost
     */
    public function factory($group = null)
    {
        return new \HttpHost($group);
    }

    /**
     * 调用系统内部请求
     *
     * \HttpHost::sync_exec('uri');
     * \HttpHost::sync_exec('test/abc','arg1','arg2','arg3');
     *
     * @param string $uri
     * @param mixed $arg1
     * @param mixed $arg2
     */
    public function sync_exec($uri,$arg1=null,$arg2=null)
    {
        # 参数
        $param_arr = \func_get_args();
        \array_shift($param_arr);

        return $this->exec($uri,$this->hosts,$param_arr);
    }

    /**
     * 调用系统内部请求主服务器
     *
     * \HttpHost::master_exec('uri');
     * \HttpHost::master_exec('test/abc','arg1','arg2','arg3');
     *
     * @param string $uri
     * @param mixed $arg1
     * @param mixed $arg2
     */
    public function master_exec($uri,$arg1=null,$arg2=null)
    {
        # 参数
        $param_arr = \func_get_args();
        \array_shift($param_arr);

        return $this->exec($uri,current($this->hosts),$param_arr);
    }

    /**
     * 指定Server执行系统内部调用
     *
     *     //指定多个服务器执行
     *     \HttpServer::exec('test/abc',array('192.168.1.11:8080','192.168.1.12:80'),array('a','b','c'));
     *
     *     //指定一个服务器执行
     *     \HttpServer::exec('test/abc','192.168.1.11:8080'array('a','b','c'));
     *
     * @param string $uri
     * @param array $hosts
     * @param array $param_arr
     * @return array
     */
    public static function exec( $uri, $hosts , array $param_arr = array() )
    {
        $one = false;

        if (\is_string($hosts))
        {
            $hosts = array($hosts);
            $one = true;
        }

        # 是否支持CURL
        static $curl_supper = null;
        if (null===$curl_supper)$curl_supper = \function_exists('\\curl_init');

        $time = \microtime(1);
        if ($curl_supper)
        {
            # 调用CURL请求
            $result = static::exec_by_curl($hosts,$uri,array('data'=>\serialize($param_arr)));
        }
        else
        {
            #
        }

        # 单条记录
        if ($one)$result = current($result);

        \Core::debug()->log('system exec time:'.(\microtime(1)-$time));

        return $result;
    }

    /**
     * 通过CURL执行
     *
     * @param string $hosts
     * @param string $url
     * @param array $param_arr
     * @return array
     */
    protected static function exec_by_curl($hosts,$url,array $param_arr = null)
    {
        $mh = \curl_multi_init();

        $url = \Core::url($url);
        if ( false===\strpos($url, '://') )
        {
            \preg_match('#^(http(?:s)?\://[^/]+/)#', $_SERVER["SCRIPT_URI"] , $m);
            $url = $m[1].\ltrim($url,'/');
        }

        # 监听列表
        $listener_list = array();

        $vars = \http_build_query($param_arr);

        # 创建列队
        foreach ( $hosts as $h )
        {
            # 排除重复HOST
            if (isset($listener_list[$h]))continue;

            list($host,$port) = \explode(':',$h,2);
            if (!$port)
            {
                if (\substr($url,0,8)=='https://')
                {
                    $port = 443;
                }
                else
                {
                    $port = 80;
                }
            }

            # 一个mictime
            $mictime = microtime(1);

            # 生成一个HASH
            $hash = self::get_hash($vars,$host,$port,$mictime);

            # 创建一个curl对象
            $current = static::_create_curl($host, $port, $url, 60 , $hash ,$vars,$mictime);

            # 列队数控制
            \curl_multi_add_handle($mh, $current);

            $listener_list[$h] = $current;
        }
        unset($current);

        $running = null;

        $result = array();

        # 已完成数
        $done_num = 0;

        # 待处理数
        $list_num = \count($listener_list);

        do
        {
            while ( ($execrun = \curl_multi_exec($mh, $running)) == \CURLM_CALL_MULTI_PERFORM );
            if ( $execrun!=\CURLM_OK ) break;

            while ( true==($done = \curl_multi_info_read($mh)) )
            {
                foreach ( $listener_list as $done_host=>$listener )
                {
                    if ( $listener === $done['handle'] )
                    {
                        # 获取内容
                        $result[$done_host] = \curl_multi_getcontent($done['handle']);

                        $code = \curl_getinfo($done['handle'], \CURLINFO_HTTP_CODE);

                        if ( $code!=200 )
                        {
                            \Core::debug()->error('system exec:'.$done_host.' ERROR,CODE:' . $code );
//                             $result[$done_host] = false;
                        }
                        else
                        {
                            # 返回内容
                            \Core::debug()->info('system exec:'.$done_host.' OK.');
                        }

                        \curl_close($done['handle']);

                        \curl_multi_remove_handle($mh, $done['handle']);

                        unset($listener_list[$done_host],$listener);

                        $done_num++;

                        $time = \microtime(1);

                        break;
                    }
                }

            }

            if ($done_num>=$list_num) break;

            if (!$running) break;

        } while (true);


        # 关闭列队
        \curl_multi_close($mh);

        return $result;
    }

    /**
     * 创建一个CURL对象
     *
     * @param string $url URL地址
     * @param int $timeout 超时时间
     * @return curl_init()
     */
    protected static function _create_curl($host, $port, $url, $timeout , $hash ,$vars,$mictime)
    {
        if (\preg_match('#^(http(?:s)?)\://([^/\:]+)(\:[0-9]+)?/#', $url.'/',$m))
        {
            $url = $m[1].'://'.$host.$m[3].'/'.\substr($url,\strlen($m[0]));
        }

        $ch = \curl_init();
        \curl_setopt($ch, \CURLOPT_URL, $url);
        if ($port)\curl_setopt($ch, \CURLOPT_PORT, $port);
        \curl_setopt($ch, \CURLOPT_HEADER, false);
        \curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_TIMEOUT, $timeout);
        \curl_setopt($ch, \CURLOPT_POST, true );
        \curl_setopt($ch, \CURLOPT_POSTFIELDS, $vars );
        \curl_setopt($ch, \CURLOPT_DNS_CACHE_TIMEOUT, 86400 );

        if ( preg_match('#^https://#i', $url) )
        {
            \curl_setopt($ch, \CURLOPT_SSL_VERIFYHOST, false);
            \curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, false);
        }

        \curl_setopt($ch, \CURLOPT_USERAGENT, 'MyQEE System Call');
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, array('Expect:','Host: '.$m[2],'X-Myqee-System-Hash: '.$hash,'X-Myqee-System-Time: '.$mictime));

        return $ch;
    }

    /**
     * 根据参数获取内部请求的HASH
     *
     * @param string $vars
     * @param string $host
     * @param int $port
     * @return string
     */
    private static function get_hash($vars,$host,$port,$mictime)
    {
        # 系统调用密钥
        $system_exec_pass = \Core::config('core.system_exec_key');

        if ($system_exec_pass)
        {
            # 如果有则使用系统调用密钥
            $hash = \sha1($vars.$mictime.$system_exec_pass.$host.':'.$port);
        }
        else
        {
            # 没有，则用系统配置和数据库加密
            $hash = \sha1($vars.$mictime.serialize(\Core::config('core')).\serialize(\Core::config('database')).$host.':'.$port);
        }

        return $hash;
    }
}