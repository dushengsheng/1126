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
        $user = getUserinfo($pageuser['id']);
        $sys_group = getConfig('sys_group');
        $sys_group_arr = [];
        foreach ($sys_group as $key => $value) {
            if (!in_array($key, [81, 91])) {
                continue;
            }
            if ($key >= $user['gid']) {
                $sys_group_arr[$key] = $value;
            }
        }
        $data = [
            'user' => $user,
            'sys_group' => $sys_group_arr
        ];

        return $this->fetch("User/agent", $data);
    }

    public function agentlist()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $where = "where status<99";
        if ($pageuser['gid'] > 41) {
            $uid_arr = getDownUser($pageuser['id']);
            $uid_str = implode(',', $uid_arr);
            $where .= " and id in ({$uid_str})";
        } else {
            $where .= " and gid in (81,91)";
        }
        if (isset($params['s_gid']) && $params['s_gid']) {
            $where .= " and gid={$params['s_gid']}";
        }
        if (isset($params['s_is_online']) && $params['s_is_online'] != 'all') {
            $params['s_is_online'] = intval($params['s_is_online']);
            $where .= " and is_online={$params['s_is_online']}";
        }
        if (isset($params['s_keyword']) && $params['s_keyword']) {
            $where .= " and (id='{$params['s_keyword']}' or account='{$params['s_keyword']}' or phone='{$params['s_keyword']}' or realname like '%{$params['s_keyword']}%' or nickname like '%{$params['s_keyword']}%')";
        }

        $sql_cnt = "select count(1) as cnt,sum(balance) as balance,sum(sx_balance) as sx_balance,
		sum(fz_balance) as fz_balance,sum(kb_balance) as kb_balance from sys_user {$where}";
        $count_item = $this->mysql->fetchRow($sql_cnt);
        $sql = "select * from sys_user {$where} order by id desc";
        $list = $this->mysql->fetchRows($sql, $params['page'], $params['limit']);
        $sys_group = getConfig('sys_group');
        $account_status = getConfig('account_status');
        $yes_or_no = getConfig('yes_or_no');
        $now_day = date('Ymd');

        foreach ($list as &$item) {
            unset($item['password'], $item['password2']);
            $item['gname'] = $sys_group[$item['gid']];
            $item['status_flag'] = $account_status[$item['status']];
            $item['is_online_flag'] = $yes_or_no[$item['is_online']];
            $item['reg_time'] = date('Y-m-d H:i:s', $item['reg_time']);
            if ($item['login_time']) {
                $item['login_time'] = date('Y-m-d H:i:s', $item['login_time']);
            }
            if ($item['pid']) {
                $p_user = $this->mysql->fetchRow("select account,nickname,realname from sys_user where id={$item['pid']}");
                $item['paccount'] = $p_user['account'];
                $item['prealname'] = $p_user['realname'] ? $p_user['realname'] : $p_user['nickname'];
            }

            //统计码商今日/累计收款
            $all_sql = "select count(1) as cnt,sum(log.money) as money from sk_order log where 1";
            if (in_array($item['gid'], [81, 91])) {
                $all_sql .= " and log.muid={$item['id']}";

                //统计一下码商或码商代理的佣金
                $yong_sql = "select sum(money) as money from sk_yong where uid={$item['id']} and type=1 and level>0";
                $yong_item = $this->mysql->fetchRow($yong_sql);
                $item['yong_money'] = floatval($yong_item['money']);

            } elseif (in_array($item['gid'], [61, 71])) {
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

            $item['edit'] = hasPower($pageuser, 'User_user_update') ? 1 : 0;
            $item['kick'] = hasPower($pageuser, 'User_user_kick') ? 1 : 0;
            $item['del'] = hasPower($pageuser, 'User_user_delete') ? 1 : 0;
            $item['recharge'] = hasPower($pageuser, 'User_pay_balance') ? 1 : 0;

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
        //file_put_contents(ROOT_PATH. "logs/test.txt", var_export($data, true) . "\n\n", FILE_APPEND);
        jReturn('0', 'ok', $data);
    }
}
