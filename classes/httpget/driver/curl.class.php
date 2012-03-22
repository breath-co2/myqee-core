<?php
namespace Core\HttpGet\Driver;

/**
 * Http请求Curl驱动核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage HttpGet
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Curl
{

    protected $http_data = array();

    protected $agent;

    protected $cookies;

    protected $referer;

    protected $ip;

    protected $header = array();

    protected $_option = array();

    /**
     * 多列队任务进程数，0表示不限制
     *
     * @var int
     */
    protected $multi_exec_num = 100;

    function __construct()
    {

    }

    /**
     * 设置$cookie
     *
     * @param $agent
     * @return \HttpGet\Driver\Curl
     */
    public function set_agent($agent)
    {
        $this->agent = $agent;

        return $this;
    }

    /**
     * 设置$cookie
     *
     * @param string $cookie
     * @return \HttpGet\Driver\Curl
     */
    public function set_cookies($cookies)
    {
        $this->cookies = $cookies;
        return $this;
    }

    /**
     * 设置$referer
     *
     * @param string $referer
     * @return \HttpGet\Driver\Curl
     */
    public function set_referer($referer)
    {
        $this->referer = $referer;
        return $this;
    }

    /**
     * 设置IP
     *
     * @param string $ip
     * @return \HttpGet\Driver\Curl
     */
    public function set_ip($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * 设置curl参数
     *
     * @param string $key
     * @param value $value
     * @return \HttpGet\Driver\Curl
     */
    public function set_option($key, $value)
    {
        if ( $key===\CURLOPT_HTTPHEADER )
        {
            $this->header = \array_merge($this->header,$value);
        }
        else
        {
            $this->_option[$key] = $value;
        }
        return $this;
    }

    /**
     * 设置多个列队默认排队数上限
     *
     * @param int $num
     * @return HttpGet_Driver_Curl
     */
    public function set_multi_exec_num($num=0)
    {
        $this->multi_exec_num = (int)$num;
        return $this;
    }

    /**
     * 用POST方式提交，支持多个URL
     *
     * @param $url
     * @param string/array $vars
     * @param $timeout 超时时间，默认120秒
     * @return string, false on failure
     */
    public function post($url, $vars, $timeout = 60)
    {
        if ( \is_array($vars) )
        {
            $vars = \http_build_query($vars);
        }

        # POST模式
        $this->set_option( \CURLOPT_HTTPHEADER, array('Expect:') );
        $this->set_option( \CURLOPT_POST, true );
        $this->set_option( \CURLOPT_POSTFIELDS, $vars );

        return $this->get($url,$timeout);
    }

    /**
     * GET方式获取数据，支持多个URL
     *
     * @param string/array $url
     * @param $timeout
     * @return string, false on failure
     */
    public function get($url, $timeout = 10)
    {
        if ( \is_array($url) )
        {
            $getone = false;
            $urls = $url;
        }
        else
        {
            $getone = true;
            $urls = array($url);
        }

        $data = $this->request_urls($urls, $timeout);

        $this->clear_set();

        if ( $getone )
        {
            $this->http_data = $this->http_data[$url];

            return $data[$url];
        }
        else
        {
            return $data;
        }
    }

    /**
     * 创建一个CURL对象
     *
     * @param string $url URL地址
     * @param int $timeout 超时时间
     * @return curl_init()
     */
    protected function _create($url,$timeout)
    {
        if ( false===\strpos($url, '://') )
        {
            \preg_match('#^(http(?:s)?\://[^/]+/)#', $_SERVER["SCRIPT_URI"] , $m);
            $url = $m[1].\ltrim($url,'/');
        }

        if ($this->ip)
        {
            # 如果设置了IP，则把URL替换，然后设置Host的头即可
            if ( \preg_match('#^(http(?:s)?)\://([^/\:]+)(\:[0-9]+)?/#', $url.'/',$m) )
            {
                $this->header[] = 'Host: '.$m[2];
                $url = $m[1].'://'.$this->ip.$m[3].'/'.\substr($url,\strlen($m[0]));
            }
        }

        $ch = \curl_init();
        \curl_setopt($ch, \CURLOPT_URL, $url);
        \curl_setopt($ch, \CURLOPT_HEADER, true);
        \curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_TIMEOUT, $timeout);

        if ( \preg_match('#^https://#i', $url) )
        {
            \curl_setopt($ch, \CURLOPT_SSL_VERIFYHOST, false);
            \curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, false);
        }

        if ( $this->cookies )
        {
            \curl_setopt($ch, \CURLOPT_COOKIE, \http_build_query($this->cookies, '', ';'));
        }

        if ( $this->referer )
        {
            \curl_setopt($ch, \CURLOPT_REFERER, $this->referer);
        }

        if ( $this->agent )
        {
            \curl_setopt($ch, \CURLOPT_USERAGENT, $this->agent);
        }
        elseif ( \array_key_exists('HTTP_USER_AGENT', $_SERVER) )
        {
            \curl_setopt($ch, \CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        }

        foreach ( $this->_option as $k => $v )
        {
            \curl_setopt($ch, $k, $v);
        }

        if ( $this->header )
        {
            $header = array();
            foreach ($this->header as $item)
            {
                # 防止有重复的header
                if (\preg_match('#(^[^:]*):.*$#', $item,$m))
                {
                    $header[$m[1]] = $item;
                }
            }
            \curl_setopt($ch, \CURLOPT_HTTPHEADER, \array_values($header));
        }

        return $ch;
    }

    /**
     * 支持多线程获取网页
     *
     * @see http://cn.php.net/manual/en/function.curl-multi-exec.php#88453
     * @param Array/string $urls
     * @param Int $timeout
     * @return Array
     */
    protected function request_urls($urls, $timeout = 10)
    {
        if (!$urls)return array();

        //create the multiple cURL handle
        $mh = \curl_multi_init();

        # 监听列表
        $listener_list = array();
        # 排队列表
        $multi_list = array();
        foreach ( $urls as $url )
        {
            # 排除重复URL
            if (isset($listener_list[$url]))continue;

            # 创建一个curl对象
            $current = $this->_create($url, $timeout);

            if ( $this->multi_exec_num>0 && \count($listener_list)>=$this->multi_exec_num )
            {
                # 加入排队列表
                $multi_list[] = $url;
                # 设置一个空的信息，带列队加入后再更新
                $listener_list[$url] = array();
            }
            else
            {
                # 列队数控制
                \curl_multi_add_handle($mh, $current);

                $listener_list[$url] = array
                (
                	'handle'     => $current,
                    'start_time' => \microtime(1),
                );
            }

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
            # Exec until there's no more data in this iteration.
            # This function has a bug, it
            while ( ($execrun = \curl_multi_exec($mh, $running)) == \CURLM_CALL_MULTI_PERFORM );
            if ( $execrun != \CURLM_OK ) break; # This should never happen. Optional line.

            # Get information about the handle that just finished the work.
            while ( true==($done = \curl_multi_info_read($mh)) )
            {
                # Call the associated listener
                foreach ( $listener_list as $done_url=>$listener )
                {
                    # Strict compare handles.
                    if ( $listener['handle'] === $done['handle'] )
                    {
                        # 获取内容
                        $this->http_data[$done_url] = $this->get_data(\curl_multi_getcontent($done['handle']), $done['handle'] , $listener['start_time']);

                        if ( $this->http_data[$done_url]['code'] != 200 )
                        {
                            \Core::debug()->error('URL:'.$done_url.' ERROR,TIME:' . $this->http_data[$done_url]['time'] . ',CODE:' . $this->http_data[$done_url]['code'] );
                            $result[$done_url] = false;
                        }
                        else
                        {
                            # 返回内容
                            $result[$done_url] = $this->http_data[$done_url]['data'];
                            \Core::debug()->info('URL:'.$done_url.' OK.TIME:' . $this->http_data[$done_url]['time'] );
                        }

                        \curl_close($done['handle']);

                        unset($listener_list[$done_url],$listener);

                        # Remove unnecesary handle (optional, script works without it).
                        \curl_multi_remove_handle($mh, $done['handle']);

                        if ( $multi_list )
                        {
                            # 获取列队中的一条URL
                            $current_url = \array_shift($multi_list);
                            # 创建CURL对象
                            $current = $this->_create($current_url, $timeout);;
                            # 更新监听列队信息
                            $listener_list[$current_url] = array
                            (
                                'handle' => $current,
                                'start_time' => \microtime(1),
                            );
                            # 加入到列队
                            \curl_multi_add_handle($mh, $current);
                        }

                        $done_num++;

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

    public function get_resut_data()
    {
        return $this->http_data;
    }

    protected function get_data($data, $ch , $start_time)
    {
        $header_size      = \curl_getinfo($ch, \CURLINFO_HEADER_SIZE);
        $result['code']   = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $result['data']   = \substr($data, $header_size);
        $result['header'] = \explode("\r\n", \substr($data, 0, $header_size));
        $result['time']   = \microtime(true) - $start_time;

        return $result;
    }

    /**
     * 清理设置
     */
    protected function clear_set()
    {
        $this->_option = array();
        $this->header  = array();
        $this->ip      = null;
        $this->cookies = null;
        $this->referer = null;
    }
}