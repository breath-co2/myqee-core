<?php
namespace Core\Controller\Test;

class A extends \Controller
{
    public function action_b($b='')
    {
        echo 'hello world.'.$b.'<br>';

        \Core::test();


    }
}