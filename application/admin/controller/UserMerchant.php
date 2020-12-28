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
            if ($key >= $pageuser['gid']) {
                $sys_group_arr[$key] = $value;
            }
        }
        $data = [
            'sys_user' => $pageuser,
            'sys_group' => $sys_group_arr
        ];

        return $this->fetch("User/merchant", $data);
    }

    public function merchantList()
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
            $where .= " and log.gid in (81,91)";
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
        $account_status = getConfig('account_status');
        $yes_or_no = getConfig('yes_or_no');
        $now_day = date('Ymd');
        $has_power_checked = false;
        $power_arr = [];

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
            'balance' => (float)$count_item['balance'],
            'sx_balance' => (float)$count_item['sx_balance'],
            'fz_balance' => (float)$count_item['fz_balance'],
            'kb_balance' => (float)$count_item['kb_balance']
        );
        jReturn('0', 'ok', $data);
    }

}
