<?php

namespace app\admin\controller;

use think\Exception;
use think\Request;


class User extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function agent()
    {
        $pageuser = checkPower();
        $channel_arr = rows2arr($this->mysql->fetchRows("select id,is_open,name from sk_mtype"));
        $down_agents = getDownAgent($pageuser['id']);
        $sys_group = getConfig('sys_group');
        $sys_group_arr = [];
        foreach ($sys_group as $key => $value) {
            /*
            if ($pageuser['id'] == 1 || $pageuser['gid'] <= 41) {
                $sys_group_arr[$key] = $value;
                continue;
            }
            */
            if ($pageuser['gid'] > $key) {
                continue;
            }
            if (!in_array($key, [61, 71])) {
                continue;
            }
            if ($key >= $pageuser['gid']) {
                $sys_group_arr[$key] = $value;
            }
        }
        $data = [
            'user' => $pageuser,
            'sys_group' => $sys_group_arr,
            'sys_agent' => $down_agents,
            'sys_channel' => $channel_arr
        ];

        return $this->fetch("User/agent", $data);
    }

    public function agentList()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $where = "where log.status<99";
        if ($pageuser['gid'] > 41) {
            $uid_arr = getDownUser($pageuser['id']);
            if ($uid_arr) {
                $uid_str = implode(',', $uid_arr);
                $where .= " and log.id in ({$uid_str})";
            } else {
                $ret_data = [
                    'list' => [],
                    'count' => 0,
                    'balance' => 0,
                    'sx_balance' => 0,
                    'fz_balance' => 0,
                    'kb_balance' => 0
                ];
                jReturn('0', '您还没有下级代理, 赶快来创建吧', $ret_data);
            }
        } else {
            $where .= " and log.gid in (61,71)";
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
        $sql = "select log.*,p.account as paccount,p.nickname as pnickname,p.td_switch as ptd_switch,p.td_rate as ptd_rate,p.fy_rate as pfy_rate 
                from sys_user log left join sys_user p on log.pid=p.id {$where} order by log.id";
        $list = $this->mysql->fetchRows($sql, $params['page'], $params['limit']);
        $sys_group = getConfig('sys_group');
        $account_status = getConfig('account_status');
        $yes_or_no = getConfig('yes_or_no');
        $now_day = date('Ymd');
        $has_power_checked = false;
        $power_arr = [];

        foreach ($list as &$item) {
            unset($item['password'], $item['password2']);
            $item['gname'] = $sys_group[$item['gid']];
            $item['status_flag'] = $account_status[$item['status']];
            $item['is_online_flag'] = $yes_or_no[$item['is_online']];
            $item['reg_time'] = date('Y-m-d H:i:s', $item['reg_time']);
            if ($item['login_time']) {
                $item['login_time'] = date('Y-m-d H:i:s', $item['login_time']);
            }
            /*
            if ($item['pid']) {
                $p_user = $this->mysql->fetchRow("select account,nickname,realname from sys_user where id={$item['pid']}");
                $item['paccount'] = $p_user['account'];
                $item['pnickname'] = $p_user['nickname'];
            }
            */

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

            // 只检查一次权限
            if (!$has_power_checked) {
                $has_power_checked = true;
                $power_arr['del'] = hasPower($pageuser, 'User_DeleteUser') ? 1 : 0;
                $power_arr['kick'] = hasPower($pageuser, 'User_OnlineStatus') ? 1 : 0;
                $power_arr['edit'] = hasPower($pageuser, 'User_UpdateUser') ? 1 : 0;
                $power_arr['channel'] = hasPower($pageuser, 'User_ChannelRate') ? 1 : 0;
                $power_arr['recharge'] = hasPower($pageuser, 'Finance_Recharge') ? 1 : 0;
            }
            $item['power_del'] = $power_arr['del'];
            $item['power_kick'] = $power_arr['kick'];
            $item['power_edit'] = $power_arr['edit'];
            $item['power_channel'] = $power_arr['channel'];
            $item['power_recharge'] = $power_arr['recharge'];
        }

        $data = array(
            'list' => $list,
            'count' => intval($count_item['cnt']),
            'limit' => $this->pageSize,
            'balance' => (float)$count_item['balance'],
            'sx_balance' => (float)$count_item['sx_balance'],
            'fz_balance' => (float)$count_item['fz_balance'],
            'kb_balance' => (float)$count_item['kb_balance']
        );
        debugLog('agent list: ' . var_export($data, true));
        jReturn('0', 'ok', $data);
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
                if(in_array($user['id'], $down_users)) {
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
                jReturn('-1', '不是自己的用户无法删除');
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
                jReturn('-1', '不是自己的用户无法禁用');
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
                jReturn('-1', '不是自己的用户无法踢下线');
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

    public function channelRate()
    {

    }


    /*
     * 分割线，上半部分是码商功能，下半分部是商户功能
     * -----------------------------------------------------*/


	public function merchant()
	{
		echo 'this is test merchant';
	}
}
