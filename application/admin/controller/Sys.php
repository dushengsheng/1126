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
        $sys_group = getConfig('sys_group');
        $user['gname'] = $sys_group[$user['gid']];
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

    public function userUpdate()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $referer = $params['referer'];

        $data = [];
        // 更新基本信息
        if ($referer == 'userinfo') {
            $data['nickname'] = $params['nickname'];;
        } else {
            if (!isset($params['oldPassword']) || $params['oldPassword'] == md5('')) {
                jReturn('-1', '请输入当前密码');
            }
            if (isset($params['password'])) {
                $data['password'] = getPassword($params['password']);
            }
            if (isset($params['password2'])) {
                $data['password2'] = getPassword($params['password2']);
            }
            if (!isset($data['password']) && !isset($data['password2'])) {
                jReturn('-1', '没有任何改变');
            }

            $user = getUserinfo($pageuser['id'], true, $this->mysql);
            $password = getPassword($params['oldPassword']);
            if ($user['password'] != $password) {
                jReturn('-1', '密码不正确');
            }
        }

        $res = $this->mysql->update($data, "id={$pageuser['id']}", 'sys_user');
        if (!$res) {
            jReturn('-1', '系统繁忙请稍后再试');
        } else {
            jReturn('0', '更新成功');
        }
    }
}