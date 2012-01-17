<?php
namespace Core\HttpGet\Driver;

/**
 * Http请求Fsock驱动核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage HttpGet
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class Fsock extends \Snoopy
{

    protected $http_data = array();

    /**
     * 设置$cookie
     *
     * @param $agent
     * @return HttpGet_Driver_Fsock
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
     * @return HttpGet_Driver_Fsock
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
     * @return \HttpGet\Driver\Fsock
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
     * @return \HttpGet\Driver\Fsock
     */
    public function set_ip($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    public function set_option($key, $value)
    {
        //TODO 暂时不支持
    }

    /**
     * 用POST方式提交
     *
     * @param $url
     * @param string/array $vars
     * @param $timeout 超时时间，默认120秒
     * @return string, false on failure
     */
    public function post($url, $vars, $timeout = 30)
    {
        if ( false===\strpos($url, '://') )
        {
            \preg_match('#^(http(s)?\://[^/]+/)#', $_SERVER["SCRIPT_URI"] , $m);
            $url = $m[1].\ltrim($url,'/');
        }
        $this->read_timeout = $timeout;
        $time = \microtime(true);
        $this->submit($url, $vars);
        $time = \microtime(true) - $time;

        $this->http_data = $this->get_data($time);

        return $this->results;
    }

    /**
     * GET方式获取数据
     *
     * @param string/array $url
     * @param int $timeout
     * @return string, false on failure
     */
    public function get($url, $timeout = 10)
    {
        if ( \is_array($url) )
        {
            return $this->get_urls($url, $timeout);
        }

        if ( false===\strpos($url, '://') )
        {
            \preg_match('#^(http(s)?\://[^/]+/)#', $_SERVER["SCRIPT_URI"] , $m);
            $url = $m[1].\ltrim($url,'/');
        }

        $this->read_timeout = $timeout;
        $time = \microtime(true);
        $this->fetch($url,$this->ip);
        $time = \microtime(true) - $time;

        $this->http_data = $this->get_data($time);
        return $this->response_code == 200 ? $this->http_data['data'] : false;
    }

    /**
     * 支持多URL请求
     * TODO 目前只是单进程
     *
     * @param array $urls
     * @param int $timeout
     */
    protected function get_urls($urls, $timeout = 1)
    {
        $http_data = array();
        foreach ( $urls as $url )
        {
            $result[$url] = $this->get($url, $timeout);
            $http_data[$url] = $this->http_data;
        }
        $this->http_data = $http_data;

        return $result;
    }

    public function get_resut_data()
    {
        return $this->http_data;
    }

    protected function get_data($time)
    {
        return array
        (
        	'data'    => $this->results,
        	'cookies' => $this->cookies,
        	'headers' => $this->headers,
        	'code'    => $this->response_code,
        	'time'    => $time,
        );
    }
}