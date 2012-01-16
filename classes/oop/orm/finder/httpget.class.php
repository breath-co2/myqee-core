<?php
namespace Core\OOP\ORM\Finder;

/**
 * ORM WGET核心类
 *
 * @author     jonwang(jonwang@myqee.com)
 * @category   Core
 * @package    Classes
 * @subpackage OOP
 * @copyright  Copyright (c) 2008-2012 myqee.com
 * @license    http://www.myqee.com/license.html
 */
class HttpGet extends \OOP\ORM
{

    /**
     * API接口地址
     *
     * @var string
     */
    protected $api_url;

    /**
     * @var \HttpGet
     */
    protected $_driver = null;

    function __construct()
    {
        if ( null === $this->api_url )
        {
            throw new \Exception(__('orm api url is not declared.'));
        }
        parent::__construct();
    }

    /**
     * 获取数据
     *
     * @param $query SQL OR Query_Builder
     * @return \OOP\ORM\Result
     */
    public function find($query = null)
    {
        if ( \is_array($query) )
        {
            $query = \http_build_query($query, '', '&');
        }
        $url = $this->api_url . (\strpos($this->api_url, '?') !== false ? '?' : '&') . $query;
        try
        {
            $data = (string)$this->driver()->get($url);
        }
        catch ( \Exception $e )
        {
            \Core::debug()->error('ORM获取数据失败,URL:' . $url);
            $data = '[]';
        }
        $this->last_query = $url;
        $data = @\json_decode($data, true);

        return $this->create_group_data($data, true);
    }

    /**
     * HttpGet对象
     * @return HttpGet
     */
    public function driver()
    {
        if ( null === $this->_driver ) $this->_driver = \HttpGet::factory();
        return $this->_driver;
    }
}