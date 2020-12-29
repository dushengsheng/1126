<?php

namespace app\admin\controller;

use think\Exception;
use think\Request;


class UserAgent extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    /**
     * 渲染代理首页
     * @return mixed
     */
    public function agent()
    {
        $pageuser = checkPower();
        $sys_group = getConfig('sys_group');
        $sys_group_arr = [];
        foreach ($sys_group as $key => $value) {
            if ($pageuser['gid'] > $key) {
                continue;
            }
            if (!in_array($key, [61, 71])) {
                continue;
            }
            $sys_group_arr[$key] = $value;
        }
        $data = [
            'sys_user' => $pageuser,
            'sys_group' => $sys_group_arr
        ];

        return $this->fetch("User/agent", $data);
    }

    /*
     * 增加或更新用户
     */
    public function updateUser()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $uid = 0;
        $data = array();
        if (isset($params['id'])) {
            $uid = intval($params['id']);
        }
        if (!isset($params['account'])) {
            jReturn('-1', '请填写账号');
        }
        if (!isset($params['nickname'])) {
            jReturn('-1', '请填写昵称');
        }
        if (!isset($params['gid'])) {
            jReturn('-1', '请选择分组');
        }
        if (!isset($params['pid'])) {
            jReturn('-1', '请选择上级代理');
        }
        if ($uid) {
            // 更新用户信息时，密码不为空表示需要修改密码
            if (isset($params['password'])) {
                $passwd = $params['password'];
                if ($passwd != md5('')) {
                    $data['password'] = getPassword($passwd);
                }
            }
        } else {
            // 新增用户必须填写密码
            if (!isset($params['password'])) {
                jReturn('-1', '请填写密码');
            }
            $passwd = $params['password'];
            if ($passwd == md5('')) {
                jReturn('-1', '请填写密码');
            }
            // 初始2级密码设置为123456
            $data['password'] = getPassword($passwd);
            $data['password2'] = getPassword(md5('123456'));
            $data['reg_time'] = NOW_TIME;
            $data['reg_ip'] = getClientIp();
            $data['apikey'] = getPassword($params['account'] . '_' . getSerialNumber() . SYS_KEY, true);

            // 初始化通道费率
            $channel_arr = rows2arr($this->mysql->fetchRows("select id,is_open,name from sk_mtype"));
            $td_switch_arr = [];
            $td_rate_arr = [];
            $fy_rate_arr = [];
            foreach ($channel_arr as $key => $val) {
                $td_switch_arr[$key] = 0;
                $td_rate_arr[$key] = 0;
                $fy_rate_arr[$key] = 0;
            }
            $data['td_switch'] = json_encode($td_switch_arr, JSON_UNESCAPED_UNICODE);
            $data['td_rate'] = json_encode($td_rate_arr, JSON_UNESCAPED_UNICODE);
            $data['fy_rate'] = json_encode($fy_rate_arr, JSON_UNESCAPED_UNICODE);
        }

        $data['gid'] = intval($params['gid']);
        $data['pid'] = intval($params['pid']);
        $data['account'] = $params['account'];
        $data['nickname'] = $params['nickname'];

        //检查分组合法性
        if ($pageuser['gid'] != 1) {
            if ($data['gid'] < $pageuser['gid']) {
                jReturn('-1', '您的级别不足以设置该所属分组');
            }
        }
        //新增用户时检查帐号是否已经存在
        if (!$uid) {
            $user = $this->mysql->fetchRow("select id from sys_user where account='{$params['account']}'");
            if ($user && $user['id']) {
                jReturn('-1', "账号{$params['account']}已经存在");
            }
        }
        //检查上级代理是否存在
        $user = $this->mysql->fetchRow("select id,account,nickname from sys_user where id='{$params['pid']}' and status < 99");
        if (!$user) {
            jReturn('-1', "上级代理不存在");
        } else {
            if ($uid) {
                //更新时，不能将下级用户设置为上级，形成环
                $down_users = getDownUser($uid);
                if (in_array($user['id'], $down_users)) {
                    jReturn('-1', "{$user['account']}是{$data['account']}的下级");
                }
            }
        }

        if ($uid) {
            $res = $this->mysql->update($data, "id={$uid}", 'sys_user');
        } else {
            $res = $this->mysql->insert($data, 'sys_user');
            $data['id'] = $res;
        }
        if (!$res) {
            jReturn('-1', '系统繁忙请稍后再试');
        }

        $data['paccount'] = $user['account'];
        $data['pnickname'] = $user['nickname'];
        jReturn('0', '操作成功', $data);
    }

    /**
     * 删除用户及其名下收款码
     */
    public function deleteUser()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $uid = intval($params['id']);
        if (!$uid) {
            jReturn('-1', '缺少参数');
        }
        if ($uid == 1) {
            jReturn('-1', '管理员不能删除');
        }
        if ($pageuser['gid'] > 41) {
            $uid_arr = getDownUser($pageuser['id']);
            if (!in_array($uid, $uid_arr)) {
                jReturn('-1', '操作失败! 该用户不是您的下级');
            }
        }

        if (!deleteUser($uid, $this->mysql)) {
            jReturn('-1', '系统繁忙请稍后再试');
        }
        actionLog(['opt_name' => '删除用户', 'sql_str' => $this->mysql->lastSql], $this->mysql);
        jReturn('0', '操作成功');
    }

    public function forbiddenStatus()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $status = $params['status'];
        $uid = intval($params['id']);

        if (!$uid) {
            jReturn('-1', '缺少参数');
        }
        if ($uid == 1) {
            jReturn('-1', '不能禁用管理员');
        }
        if ($pageuser['gid'] > 41) {
            $uid_arr = getDownUser($pageuser['id']);
            if (!in_array($uid, $uid_arr)) {
                jReturn('-1', '操作失败! 该用户不是您的下级');
            }
        }

        if (!setUserForbidden($uid, !$status, $this->mysql)) {
            jReturn('-1', '系统繁忙请稍后再试');
        }
        $opt_name = '禁用账号';
        if ($status) {
            $opt_name = '启用账号';
        }
        actionLog(['opt_name' => $opt_name, 'sql_str' => $this->mysql->lastSql], $this->mysql);
        jReturn('0', '操作成功');
    }

    public function onlineStatus()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $status = $params['status'];
        $uid = intval($params['id']);
        if (!$uid) {
            jReturn('-1', '缺少参数');
        }
        if ($pageuser['gid'] > 41) {
            $uid_arr = getDownUser($pageuser['id']);
            if (!in_array($uid, $uid_arr)) {
                jReturn('-1', '操作失败! 该用户不是您的下级');
            }
        }
        if ($status) {
            $user = getUserinfo($uid, true, $this->mysql);
            if (!$user || $user['status'] != 2) {
                jReturn('-1', '用户已被删除或被禁用');
            }
        }

        if (!setUserOnline($uid, $status, $this->mysql)) {
            jReturn('-1', '系统繁忙请稍后再试');
        }
        $opt_name = '禁止接单';
        if ($status) {
            $opt_name = '允许接单';
        }
        actionLog(['opt_name' => $opt_name, 'sql_str' => $this->mysql->lastSql], $this->mysql);
        jReturn('0', '操作成功');
    }

    /**
     * 查询自己旗下的码商/商户代理(包括自己)
     */
    public function agentQuery()
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
            if (!in_array($key, [61, 71])) {
                continue;
            }
            $sys_group_arr[$key] = $value;
        }

        $myself_include = false;
        foreach ($sys_agent as $user) {
            if ($pageuser['gid'] > $user['gid']) {
                continue;
            }
            if (!in_array($user['gid'], [1, 61])) {
                continue;
            }
            if ($user['id'] == $pageuser['id']) {
                $myself_include = true;
            }
            $sys_agent_arr[] = $user;
        }
        if (!$myself_include) {
            $sys_agent_arr[] = $pageuser;
        }
        $data = [
            'sys_agent' => $sys_agent_arr,
            'sys_group' => $sys_group_arr,
        ];

        jReturn('0', '查询代理信息成功', $data);
    }
}
