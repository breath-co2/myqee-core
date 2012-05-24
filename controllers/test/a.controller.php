<?php
namespace Core;

class Controller_Test__A extends \Controller
{
    public function action_b($b='')
    {
        echo 'hello world.'.$b.'<br>';

        \Core::test();


    }
}