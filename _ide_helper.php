<?php
namespace
{
    exit;

    class Controller extends \Core\Controller{}

    class Database extends \Core\Database{}

    class Session extends \Core\Session{}

    class HttpIO extends \Core\HttpIO{}

    class I18n extends \Core\I18n{}

    class View extends \Core\View{}
}


namespace Database
{
    class Driver extends \Core\Database\Driver{}

    class Result extends \Core\Database\Result{}

    class Transaction extends \Core\Database\Transaction{}

    class QueryBuilder extends \Core\Database\QueryBuilder{}

    class Expression extends \Core\Database\Expression{}
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