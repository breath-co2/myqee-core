<?php

namespace ORM
{
    if ( !class_exists('\ORM\Member_Data',true) )
    {
        class Member_Data extends \OOP\ORM\Data
        {

        }
    }
}

namespace Core
{
    /**
     * Member核心类
     *
     * @author     jonwang(jonwang@myqee.com)
     * @category   Core
     * @package    Classes
     * @copyright  Copyright (c) 2008-2012 myqee.com
     * @license    http://www.myqee.com/license.html
     */
    class Member extends \ORM\Member_Data
    {
        /**
         * 定义此对象的ORM基础名称为Member
         *
         * @var string
         */
        protected $_orm_name = 'Member';

        /**
         * 用户权限对象
         *
         * @var \Permission
         */
        protected $_permission;

        /**
         * 用户ID
         *
         * @var int
         */
        public $id = array(
            'field_name' => 'id',
            'is_id_field' => true,
        );

        /**
         * 用户名
         *
         * @var string
         */
        public $username;

        /**
         * 当前用户密码（通常都是加密后的内容）
         *
         * @var string
         */
        public $password;

        /**
         * 当前用户随机码
         *
         * @var string
         */
        public $rand_code;

        /**
         * 电子邮件
         *
         * @var string
         */
        public $email;

        /**
         * 用户自定义权限
         *
         * 请使用$this->perm()方法获取对象
         *
         * @var array
         */
        public $perm_setting = array
        (
            'field_name' => 'perm',
            'format' => array(
                'serialize',
            ),
        );

        /**
         * 所有组
         *
         * @var \Member\Group_Result
         */
        protected $_groups = null;

        /**
         * 返回所有组的对象集
         *
         * @return \Member\Group_Result
         */
        public function groups()
        {
            if ( null!==$this->_groups )return $this->_groups;

            $orm_group = new \Member\Group();

            $this->_groups = $orm_group->get_all_groups_by_member($this);

            return $this->_groups;
        }

        /**
         * 设置管理组
         *
         * @param \Member\Group_Result $member_group_result
         * @return \ORM\Member
         */
        public function set_groups(\Member\Group_Result $member_group_result)
        {
            $this->_groups = $member_group_result;

            return $this;
        }

        /**
         * 插入用户数据
         *
         * @see \OOP\ORM\Data::insert()
         */
        public function insert()
        {
            # 生成一个加密随机码
            $this->rand_code = $this->_get_password_rand_code();

            # 加密密码
            $this->password = $this->_get_password_hash($this->password);

            return parent::insert();
        }

        /**
         * 检查密码是否正确
         *
         * @param string $password
         */
        public function check_password( $password )
        {
            if ( $this->_get_password_hash($password) == $this->password )
            {
                return true;
            }
            else
            {
                return false;
            }
        }

        /**
         * 获取一个新的密码hash值
         *
         * @param string $password
         * @return string
         */
        protected function _get_password_hash( $password )
        {
            return \md5($this->username . $this->rand_code . $password);
        }

        /**
         * 设置新密码
         *
         * @param string $new_password
         * @return $this
         */
        public function set_password( $new_password )
        {
            # 更新随机码
            $this->rand_code = $this->_get_password_rand_code();

            # 修改密码
            $this->password = $this->_get_password_hash($new_password);

            return $this;
        }

        /**
         * 修改密码，此方法会更新数据库
         *
         * @param string $new_password
         * @return array 失败返回false
         */
        public function change_password( $new_password )
        {
            $this->set_password($new_password);

            return $this->update();
        }

        /**
         * 获取一个随机的密码加密码
         *
         * @return string
         */
        protected function _get_password_rand_code()
        {
            # 重新生成一个随机rand_code
            $str = '~!@#$%^&*()_+`1234567890-=QWERTYUIOP{}|ASDFGHJKL:"ZXCVBNM<>?qwertyuiop[]\\asdfghjkl;\'zxcvbnm,./';
            $count = \strlen($str)-1;
            $rand_code = '';
            for ( $i=0;$i<16;$i++ )
            {
                $rand_code .= \substr($str,\mt_rand(0,$count),1);
            }

            return $rand_code;
        }

        /**
         * 返回用户权限对象
         *
         * @return Permission
         */
        public function perm()
        {
            if ( null===$this->_permission )
            {
                $this->_permission = new \Permission($this->perm_setting);
            }

            return $this->_permission;
        }
    }
}