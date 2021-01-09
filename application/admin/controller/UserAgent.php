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

        // 检查权限
        $sys_power = [];
        $sys_power['add'] = hasPower($pageuser, 'User_UserAdd') ? 1 : 0;
        $sys_power['del'] = hasPower($pageuser, 'User_UserDelete') ? 1 : 0;
        $sys_power['edit'] = hasPower($pageuser, 'User_UserUpdate') ? 1 : 0;
        $sys_power['channel'] = hasPower($pageuser, 'User_ChannelRate') ? 1 : 0;
        //$sys_power['kick'] = hasPower($pageuser, 'User_OnlineStatus') ? 1 : 0;
        //$sys_power['recharge'] = hasPower($pageuser, 'Finance_Recharge') ? 1 : 0;

        $data = [
            'sys_user' => $pageuser,
            'sys_group' => $sys_group_arr,
            'sys_power' => $sys_power
        ];

        return $this->fetch("User/agent", $data);
    }

    public function agentList()
    {
        $pageuser = checkPower();
        $params = $this->params;

        $where = "where log.status<99 and log.gid in (61,71)";
        if ($pageuser['gid'] >= 61) {
            $uid_arr = getDownUser($pageuser['id']);
            $uid_arr[] = $pageuser['id'];
            if ($uid_arr) {
                $uid_str = implode(',', $uid_arr);
                $where .= " and log.id in ({$uid_str})";
            }
        }

        if (isset($params['s_gid']) && $params['s_gid']) {
            $where .= " and log.gid={$params['s_gid']}";
        }
        if (isset($params['s_is_online']) && $params['s_is_online'] != 'all') {
            $params['s_is_online'] = intval($params['s_is_online']);
            $where .= " and log.is_online={$params['s_is_online']}";
        }
        if (isset($params['s_keyword']) && $params['s_keyword']) {
            $where .= " and (log.id='{$params['s_keyword']}' or log.account like '%{$params['s_keyword']}%' or log.nickname like '%{$params['s_keyword']}%')";
        }

        $sql_cnt = "select count(1) as cnt,sum(balance) as balance,sum(sx_balance) as sx_balance,
		sum(fz_balance) as fz_balance,sum(kb_balance) as kb_balance from sys_user log {$where}";
        $count_item = $this->mysql->fetchRow($sql_cnt);

        $sql = "select log.*,p.account as paccount,p.nickname as pnickname,p.status as pstatus,p.td_switch as ptd_switch,p.td_rate as ptd_rate,p.fy_rate as pfy_rate 
                from sys_user log left join sys_user p on log.pid=p.id {$where} order by log.id";
        $list = $this->mysql->fetchRows($sql, $params['page'], $params['limit']);
        $sys_group = getConfig('sys_group');
        $yes_or_no = getConfig('yes_or_no');
        $account_status = getConfig('account_status');
        $now_day = date('Ymd');

        foreach ($list as &$item) {
            unset($item['password'], $item['password2']);
            $item['gname'] = $sys_group[$item['gid']];
            $item['status_flag'] = $account_status[$item['status']];
            $item['pstatus_flag'] = $account_status[$item['pstatus']];
            $item['is_online_flag'] = $yes_or_no[$item['is_online']];
            $item['reg_time'] = date('Y-m-d H:i:s', $item['reg_time']);
            if ($item['login_time']) {
                $item['login_time'] = date('Y-m-d H:i:s', $item['login_time']);
            }

            //统计码商今日/累计收款
            $all_sql = "select count(1) as cnt,sum(log.money) as money from sk_order log where 1";
            if (in_array($item['gid'], [61, 71])) {
                $all_sql .= " and log.muid={$item['id']}";

                //统计一下码商或码商代理的佣金
                $yong_sql = "select sum(money) as money from sk_yong where uid={$item['id']} and type=1 and level>0";
                $yong_item = $this->mysql->fetchRow($yong_sql);
                $item['yong_money'] = floatval($yong_item['money']);

            } elseif (in_array($item['gid'], [81, 91])) {
                $all_sql .= " and log.suid={$item['id']}";

                //统计一下商户或商户代理的佣金
                $yong_sql = "select sum(money) as money from sk_yong where uid={$item['id']} and type=2 and level>0";
                $yong_item = $this->mysql->fetchRow($yong_sql);
                $item['yong_money'] = floatval($yong_item['money']);
            }

            if ($item['gid'] >= 61) {
                $all_item = $this->mysql->fetchRow($all_sql);
                $td_sql = $all_sql . " and log.create_day={$now_day}";
                $td_item = $this->mysql->fetchRow($td_sql);

                $all_sql_ok = $all_sql . " and log.pay_status=9";
                $all_item_ok = $this->mysql->fetchRow($all_sql_ok);
                $td_sql_ok = $all_sql_ok . " and log.create_day={$now_day}";
                $td_item_ok = $this->mysql->fetchRow($td_sql_ok);

                $item['all_money'] = floatval($all_item['money']);
                $item['all_cnt'] = intval($all_item['cnt']);
                $item['td_money'] = floatval($td_item['money']);
                $item['td_cnt'] = intval($td_item['cnt']);

                $item['all_money_ok'] = floatval($all_item_ok['money']);
                $item['all_cnt_ok'] = intval($all_item_ok['cnt']);
                $item['td_money_ok'] = floatval($td_item_ok['money']);
                $item['td_cnt_ok'] = intval($td_item_ok['cnt']);

                $all_percent = '0%';
                if ($item['all_cnt'] > 0) {
                    $all_percent = round(($item['all_cnt_ok'] / $item['all_cnt']) * 100, 2) . '%';
                }
                $td_percent = '0%';
                if ($item['td_cnt'] > 0) {
                    $td_percent = round(($item['td_cnt_ok'] / $item['td_cnt']) * 100, 2) . '%';
                }
                $item['all_percent'] = $all_percent;
                $item['td_percent'] = $td_percent;
            }
        }

        $data = array(
            'list' => $list,
            'count' => intval($count_item['cnt']),
            'balance' => (float)$count_item['balance'],
            'sx_balance' => (float)$count_item['sx_balance'],
            'fz_balance' => (float)$count_item['fz_balance']
        );
        jReturn('0', 'ok', $data);
    }

    /*
     * 增加或更新用户
     */
    public function userUpdate()
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

        if (isset($params['appoint_agent'])) {
            $data['appoint_agent'] = intval($params['appoint_agent']);
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
    public function userDelete()
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
        if ($pageuser['gid'] >= 61) {
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
        if ($pageuser['gid'] >= 61) {
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
        if ($pageuser['gid'] >= 61) {
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
    public function queryGroupAndAgent()
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

        foreach ($sys_agent as $user) {
            if ($pageuser['gid'] > $user['gid']) {
                continue;
            }
            if (!in_array($user['gid'], [1, 61])) {
                continue;
            }
            $sys_agent_arr[] = $user;
        }
        if ($pageuser['gid'] >= 61) {
            $sys_agent_arr[] = getUserinfo(1, true, $this->mysql);
            $sys_agent_arr[] = getUserinfo($pageuser['id'], true, $this->mysql);
        }

        $data = [
            'sys_agent' => $sys_agent_arr,
            'sys_group' => $sys_group_arr,
        ];

        jReturn('0', '查询代理信息成功', $data);
    }

    public function channelRateUpdate()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $uid = $params['id'];
        $channel_rate = $params['channel_rate'];
        $channel_switch = $params['channel_switch'];
        $user = getUserinfo($uid, true, $this->mysql);
        $puser = getUserinfo($user['pid'], true, $this->mysql);

        if (!$user) {
            jReturn('-1', '用户不存在');
        }
        if (!$puser) {
            jReturn('-1', '请先为该用户指定上级');
        }
        if ($pageuser['gid'] >= 61) {
            $uid_arr = getDownUser($pageuser['id']);
            $uid_arr[] = $pageuser['id'];
            if (!in_array($uid, $uid_arr)) {
                jReturn('-1', '操作失败! 该用户不是您的下级');
            }
        }

        $prate_arr = null;
        if ($puser['gid'] >= 61) {
            $prate_json = $puser['fy_rate'];
            $prate_arr = json_decode($prate_json, JSON_UNESCAPED_UNICODE);
        }

        foreach ($channel_rate as $key => $val) {
            $rate = floatval($val);
            if ($prate_arr && isset($prate_arr[$key])) {
                $prate = floatval($prate_arr[$key]);
                if ($prate < $rate) {
                    jReturn('-1', '下级码商通道费率不能高于上级');
                }
            }
            $channel_rate[$key] = $rate;
        }
        foreach ($channel_switch as $key => $val) {
            $channel_switch[$key] = intval($val);
        }

        $channel_rate_json = json_encode($channel_rate, JSON_UNESCAPED_UNICODE);
        $channel_switch_json = json_encode($channel_switch, JSON_UNESCAPED_UNICODE);
        $data = [
            'fy_rate' => $channel_rate_json,
            'td_switch' => $channel_switch_json,
        ];

        $res = $this->mysql->update($data, "id={$uid}", 'sys_user');
        if (!$res) {
            jReturn('-1', '系统繁忙, 请稍后再试');
        }
        jReturn('0', '通道费率更新成功');
    }

    public function channelQuery()
    {
        $pageuser = checkLogin();
        $params = $this->params;
        $uid = $params['id'];
        $user = getUserinfo($uid, true, $this->mysql);
        if (!$user) {
            jReturn('-1', '操作失败! 用户不存在');
        }

        if ($pageuser['gid'] >= 61) {
            $down_user_arr = getDownUser($pageuser['id']);
            $down_user_arr[] = $pageuser['id'];
            if (!in_array($uid, $down_user_arr)) {
                jReturn('-1', '操作失败! 该用户不是您的下级');
            }
        }

        $puser = null;
        if ($user['pid']) {
            $puser = getUserinfo($user['pid'], true, $this->mysql);
        }

        $ptd_switch = array();
        $ptd_rate = array();
        $pfy_rate = array();
        $paccount = null;
        if ($puser) {
            $ptd_switch = json_decode($puser['td_switch'], true);
            $ptd_rate = json_decode($puser['td_rate'], true);
            $pfy_rate = json_decode($puser['fy_rate'], true);
            $paccount = $puser['account'];
        }

        $channel_arr = rows2arr($this->mysql->fetchRows("select id,is_open,name from sk_mtype"));
        $data = [
            'id' => $user['id'],
            'gid' => $user['gid'],
            'pid' => $user['pid'],
            'pgid' => $puser ? $puser['gid'] : 1,
            'account' => $user['account'],
            'paccount' => $paccount,
            'sys_channel' => $channel_arr,
            'td_switch' => json_decode($user['td_switch'], true),
            'td_rate' => json_decode($user['td_rate'], true),
            'fy_rate' => json_decode($user['fy_rate'], true),
            'ptd_switch' => $ptd_switch,
            'ptd_rate' => $ptd_rate,
            'pfy_rate' => $pfy_rate,
        ];

        jReturn('0', '查询通道信息成功', $data);
    }
}
