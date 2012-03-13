<?php
namespace
{
    exit;

    class Arr extends \Core\Arr{}
    class Auth extends \Core\Auth{}

    class Cache extends \Core\Cache{}
    class Captcha extends \Core\Captcha{}
    class Controller extends \Core\Controller{}
    class Cookie extends \Core\Cookie{}

    class Database extends \Core\Database{}

    class File extends \Core\File{}
    class Form extends \Core\Form{}

    class html extends \Core\html{}
    class HttpGet extends \Core\HttpGet{}
    class HttpIO extends \Core\HttpIO{}

    class I18n extends \Core\I18n{}
    class IpSource extends \Core\IpSource{}

    class Member extends \Core\Member{}
    class Model extends \Core\Model{}

    class Pagination extends \Core\Pagination{}
    class Permission extends \Core\Permission{}
    class PinYin extends \Core\PinYin{}

    class Session extends \Core\Session{}

    class Text extends \Core\Text{}

    class utf8 extends \Core\utf8{}

    class View extends \Core\View{}
}

namespace Cache
{
    class Database extends \Core\Cache\Database{}
    class File extends \Core\Cache\File{}
    class Memcache extends \Core\Cache\Memcache{}
}


namespace Database
{
    class Driver extends \Core\Database\Driver{}
    class Result extends \Core\Database\Result{}
    class Transaction extends \Core\Database\Transaction{}
    class QueryBuilder extends \Core\Database\QueryBuilder{}
    class Expression extends \Core\Database\Expression{}
}

namespace Database\Driver\MySQLI
{
    class Result extends \Core\Database\Driver\MySQLI\Result{}
}

namespace HttpGet
{
    class Result extends \Core\HttpGet\Result{}
}

namespace HttpGet\Driver
{
    class Curl extends \Core\HttpGet\Driver\Curl{}
    class Fsock extends \Core\HttpGet\Driver\Fsock{}
}

namespace OOP
{
    class ORM extends \Core\OOP\ORM{}
}

namespace OOP\ORM
{
    class Data extends \Core\OOP\ORM\Data{}
    class Parse extends \Core\OOP\ORM\Parse{}
    class Result extends \Core\OOP\ORM\Result{}
    class Index extends \Core\OOP\ORM\Index{}
}

namespace OOP\ORM\Finder
{
    class DB extends \Core\OOP\ORM\Finder\DB{}
    class HttpGet extends \Core\OOP\ORM\Finder\HttpGet{}
}

namespace Session
{
    class Cache extends \Core\Session\Cache{}
    class Default_Driver extends \Core\Session\Default_Driver{}
}



