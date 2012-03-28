<?php
namespace Core;

/**
 * Http请求核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage HttpGet
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class HttpGet
{

    /**
     * curl操作类型
     *
     * @var string
     */
    const TYPE_CURL = 'Curl';

    /**
     * fsockopen操作类型
     *
     * @var string
     */
    const TYPE_FSOCK = 'Fsock';

    /**
     * 默认操作类型
     *
     * @var string $default_type
     */
    protected static $default_type = 'Curl';

    /**
     * 当前使用操作类型
     *
     * @var string
     */
    protected $type;

    /**
     * 驱动
     *
     * @var \HttpGet\Driver\Curl
     */
    protected $driver;

    /**
     * 客户端信息
     *
     * @var string
     */
    protected static $agent = "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; en-US; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2";

    function __construct($type = null)
    {
        $this->type = $type ? $type : static::$default_type;

        $this->set_agent();
    }

    /**
     * 获取实例化对象
     *
     * @param string $type
     * @return \HttpGet
     */
    public static function factory($type = null)
    {
        return new \HttpGet($type);
    }

    /**
     * 设置$agent
     *
     * @param string $agent
     * @return \HttpGet
     */
    public function set_agent($agent = null)
    {
        $agent or $agent = static::$agent;
        $this->driver()->set_agent($agent);

        return $this;
    }

    /**
     * 设置$cookie
     *
     * @param string $cookie
     * @return \HttpGet
     */
    public function set_cookies($cookies)
    {
        $this->driver()->set_cookies($cookies);

        return $this;
    }

    /**
     * 设置$referer
     *
     * @param string $referer
     * @return \HttpGet
     */
    public function set_referer($referer)
    {
        $this->driver()->set_referer($referer);

        return $this;
    }

    /**
     * 设置请求页面的IP地址
     *
     * @param string $ip
     * @return \HttpGet
     */
    public function set_ip($ip)
    {
        $this->driver()->set_ip($ip);

        return $this;
    }

    /**
     * 设置参数
     *
     * @param $key
     * @param $value
     * @return \HttpGet
     */
    public function set_option($key, $value)
    {
        $this->driver()->set_option($key, $value);

        return $this;
    }

    /**
     * HTTP GET方式请求
     *
     * curl 方式支持多并发进程，这样可以大大缩短批量URL请求时间
     *
     * @param string/array $url 支持多个URL
     * @param array $data
     * @param $timeout
     * @return string
     * @return \HttpGet\Result 但个URL返回当然内容对象
     */
    public function get($url, $timeout = 10)
    {
        $this->driver()->get($url, $timeout);
        $data = $this->driver()->get_resut_data();

        if ( \is_array($url) )
        {
            # 如果是多个URL
            $result = new \Arr();
            foreach ( $data as $key => $item )
            {
                $result[$key] = new \HttpGet\Result($item);
            }
        }
        else
        {
            $result = new \HttpGet\Result($data);
        }

        return $result;
    }

    /**
     * POST方式请求
     * @param $url
     * @param $data
     * @param $timeout
     * @return \HttpGet\Result
     */
    public function post($url, $data, $timeout = 30)
    {
        $time = \microtime(true);
        $this->driver()->post($url, $data, $timeout);
        $time = \microtime(true) - $time;
        $data = $this->driver()->get_resut_data();
        $data['total_time'] = $time;

        return new \HttpGet\Result($data);
    }

    public function __call($method, $params)
    {
        if ( \method_exists($this->driver(), $method) )
        {
            return \call_user_func_array(array($this->driver(), $method), $params);
        }
    }

    /**
     * 获取当前驱动
     *
     * @return \HttpGet\Driver\Curl
     */
    public function driver()
    {
        if ( null === $this->driver )
        {
            if (\strtolower($this->driver)=='default')$this->driver = 'Default_Driver';
            $f = '\\HttpGet\\Driver\\' . $this->type;
            $this->driver = new $f();
        }

        return $this->driver;
    }
}