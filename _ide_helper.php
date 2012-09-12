<?php

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
