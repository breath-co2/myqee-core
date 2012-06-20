<?php
namespace
{
    exit;

    class Anonymous extends \Core\Anonymous{}
    class App extends \Core\App{}
    class Arr extends \Core\Arr{}
    class Auth extends \Core\Auth{}

    class Cache extends \Core\Cache{}
    class Captcha extends \Core\Captcha{}
    abstract class Core extends \Core\Core{}
    class Controller extends \Core\Controller{}
    class Cookie extends \Core\Cookie{}

    class Database extends \Core\Database{}
    abstract class Database_Driver extends \Core\Database_Driver{}
    abstract class Database_Result extends \Core\Database_Result{}
    abstract class Database_Transaction extends \Core\Database_Transaction{}
    class Database_Expression extends \Core\Database_Expression{}
    class Database_Driver_MySQLI_Result extends \Core\Database_Driver_MySQLI_Result{}
    class Database_Driver_MySQL_Result extends \Core\Database_Driver_MySQL_Result{}

    class File extends \Core\File{}
    class Form extends \Core\Form{}

    class html extends \Core\html{}
    class HttpClient extends \Core\HttpClient{}
    class HttpIO extends \Core\HttpIO{}
    class HttpCall extends \Core\HttpCall{}

    class I18n extends \Core\I18n{}
    class IpSource extends \Core\IpSource{}

    class Member extends \Core\Member{}
    class Model extends \Core\Model{}

    abstract class OOP_ORM extends \Core\OOP_ORM{}

    class OOP_ORM_Data extends \Core\OOP_ORM_Data{}
    class OOP_ORM_Parse extends \Core\OOP_ORM_Parse{}
    class OOP_ORM_Result extends \Core\OOP_ORM_Result{}
    class OOP_ORM_Index extends \Core\OOP_ORM_Index{}

    class OOP_ORM_Finder_DB extends \Core\OOP_ORM_Finder_DB{}
    class OOP_ORM_Finder_REST extends \Core\OOP_ORM_Finder_REST{}

    class Pagination extends \Core\Pagination{}
    class Permission extends \Core\Permission{}
    class PinYin extends \Core\PinYin{}

    class QueryBuilder extends \Core\QueryBuilder{}

    class Session extends \Core\Session{}
    class Session_Cache extends \Core\Session_Cache{}
    class Session_Default extends \Core\Session_Default{}
    class Storage extends \Core\Storage{}

    class Text extends \Core\Text{}

    class utf8 extends \Core\utf8{}

    class View extends \Core\View{}

    class Cache_Database extends \Core\Cache_Database{}
    class Cache_File extends \Core\Cache_File{}
    class Cache_Memcache extends \Core\Cache_Memcache{}

    class Controller_File extends \Core\Controller_File{}

    class HttpClient_Result extends \Core\HttpClient_Result{}

    class HttpClient_Driver_Curl extends \Core\HttpClient_Driver_Curl{}
    class HttpClient_Driver_Fsock extends \Core\HttpClient_Driver_Fsock{}
}

namespace AnonymousClass
{
    class Cookie
    {
        /**
         * 获取一个Cookie
         *
         * @param string $name
         */
        public static function get($name){}

        /**
         * 创建cookie 详细请参考setcookie函数参数
         *
         * @param string/array $name
         * @param string $value
         * @param number $expire
         * @param string $path
         * @param string $domain
         * @param boolean $secure
         * @param boolean $httponly
         * @return boolean true/false
         */
        public static function set($name, $value = null, $expire = null, $path = null, $domain = null, $secure = null, $httponly = null){}

        /**
         * 删除cookie
         *
         * @param string $name cookie名称
         * @param string $path cookie路径
         * @param string $domain cookie作用域
         * @return boolean true/false
         */
        public static function delete($name, $path = null, $domain = null){}
    }
}
