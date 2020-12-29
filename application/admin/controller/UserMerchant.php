<?php

namespace app\admin\controller;

use think\Exception;
use think\Request;


class UserMerchant extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    /**
     * 渲染代理首页
     * @return mixed
     */
    public function merchant()
    {
        $pageuser = checkPower();
        $sys_group = getConfig('sys_group');
        $sys_group_arr = [];
        foreach ($sys_group as $key => $value) {
            if ($pageuser['gid'] > $key) {
                continue;
            }
            if (!in_array($key, [81, 91])) {
                continue;
            }
            $sys_group_arr[$key] = $value;
        }
        $data = [
            'sys_user' => $pageuser,
            'sys_group' => $sys_group_arr
        ];

        return $this->fetch("User/merchant", $data);
    }

    /**
     * 查询自己旗下的码商/商户代理(包括自己)
     */
    public function merchantQuery()
    {
        $pageuser = checkLogin();
        $sys_agent = getDownAgent($pageuser);
        $sys_group = getConfig('sys_group');
        $sys_agent_arr = [];
        $sys_group_arr = [];

        foreach ($sys_group as $key => $value) {
            if ($pageuser['gid'] > $key) {
                continue;
            }
            if (!in_array($key, [81, 91])) {
                continue;
            }
            $sys_group_arr[$key] = $value;
        }

        $admin_include = false;
        $myself_include = false;
        foreach ($sys_agent as $user) {
            if ($pageuser['gid'] > $user['gid']) {
                continue;
            }
            if (!in_array($user['gid'], [1, 81])) {
                continue;
            }
            if ($user['id'] == $pageuser['id']) {
                $myself_include = true;
            }
            if ($user['id'] == 1) {
                $admin_include = true;
            }
            $sys_agent_arr[] = $user;
        }
        if (!$myself_include && $pageuser['gid'] == 81) {
            $sys_agent_arr[] = $pageuser;
        }
        if (!$admin_include) {
            $sys_agent_arr[] = getUserinfo(1);
        }
        $data = [
            'sys_agent' => $sys_agent_arr,
            'sys_group' => $sys_group_arr,
        ];

        jReturn('0', '查询代理信息成功', $data);
    }
}
