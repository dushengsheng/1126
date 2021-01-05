<?php
namespace app\admin\controller;

use think\Request;

class Sys extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function _initialize()
    {
        parent::_initialize();
        debugLog('params = ' . var_export($this->params, true));
    }

    public function userinfo()
    {
        $pageuser = checkPower();
        $user = getUserinfo($pageuser['id'], true, $this->mysql);
        unset($user['password']);
        unset($user['password2']);
        $data = [
            'sys_user' => $user,
        ];

        return $this->fetch("Sys/userinfo", $data);
    }

    public function password()
    {
        $pageuser = checkPower();
        $user = getUserinfo($pageuser['id'], true, $this->mysql);
        unset($user['password']);
        unset($user['password2']);
        $data = [
            'sys_user' => $user,
        ];

        return $this->fetch("Sys/password", $data);
    }
}