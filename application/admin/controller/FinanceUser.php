<?php
namespace app\admin\controller;

use think\Request;

class FinanceUser extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function user()
    {
        $pageuser = checkPower();
        $sys_group = getConfig('sys_group');
        $sys_group_arr = [];
        foreach ($sys_group as $key => $value) {
            if ($pageuser['gid'] > $key) {
                continue;
            }
            if (!in_array($key, [61, 71, 81, 91])) {
                continue;
            }
            $sys_group_arr[$key] = $value;
        }
        $sys_power = [];
        $sys_power['recharge'] = hasPower($pageuser, 'Finance_UserRecharge') ? 1 : 0;
        $data = [
            'sys_user' => $pageuser,
            'sys_group' => $sys_group_arr,
            'sys_power' => $sys_power
        ];

        return $this->fetch("Finance/user", $data);
    }

    public function userList()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $where = "where log.status<99 and log.gid >= 61";
        if ($pageuser['gid'] >= 61) {
            $agent_arr = getDownUser($pageuser['id']);
            $agent_str = implode(',', $agent_arr);
            $agent_arr[] = $pageuser['id'];
            $appoint_str = implode(',', $agent_arr);

            if (strlen($agent_str)) {
                $where .= " and (log.appoint_agent in ({$appoint_str}) or log.id in ({$agent_str}))";
            } else {
                $where .= " and log.appoint_agent in ({$appoint_str})";
            }
        }

        if (isset($params['s_gid']) && $params['s_gid']) {
            $where .= " and log.gid={$params['s_gid']}";
        }
        if (isset($params['s_keyword']) && $params['s_keyword']) {
            $where .= " and (log.id='{$params['s_keyword']}' or log.account like '%{$params['s_keyword']}%' or log.nickname like '%{$params['s_keyword']}%')";
        }

        $sql_cnt = "select count(1) as cnt,sum(balance) as balance,sum(sx_balance) as sx_balance,
		sum(fz_balance) as fz_balance from sys_user log {$where}";
        $count_item = $this->mysql->fetchRow($sql_cnt);

        $sql = "select log.* from sys_user log {$where} order by log.gid,log.id";
        $list = $this->mysql->fetchRows($sql, $params['page'], $params['limit']);
        $sys_group = getConfig('sys_group');
        $yes_or_no = getConfig('yes_or_no');
        $account_status = getConfig('account_status');

        foreach ($list as &$item) {
            unset($item['password'], $item['password2']);
            $item['gname'] = $sys_group[$item['gid']];
            $item['status_flag'] = $account_status[$item['status']];
            $item['is_online_flag'] = $yes_or_no[$item['is_online']];
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

    public function userRecharge()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $uid = $params['id'];
        $user = getUserinfo($uid, true, $this->mysql);
        $myself = getUserinfo($pageuser['id'], true, $this->mysql);
        $money = floatval($params['money']);
        $remark = $params['remark'];
        $passwd2 = getPassword($params['password2']);
        $exchange = false;

        if (!$user) {
            jReturn('-1', '用户不存在');
        }
        if ($money == 0) {
            jReturn('-1', '充值额度不能为0');
        }
        if ($myself['password2'] != $passwd2) {
            jReturn('-1', '二级密码错误');
        }
        if ($myself['gid'] >= 61) {
            $agent_arr = getDownUser($myself['id']);
            $appoint_arr[] = $myself['id'];

            // 不是你的下级
            if (!in_array($uid, $agent_arr)) {
                if (isset($user['appoint_agent']) && $user['appoint_agent']) {
                    if (!in_array($user['appoint_agent'], $appoint_arr)) {
                        jReturn('-1', '操作失败, 您无权为该用户充值');
                    }
                } else {
                    jReturn('-1', '操作失败, 您无权为该用户充值');
                }
            }

            // 二级以下代理, 充值会扣除自身额度
            if ($myself['pid'] > 1) {
                if ($money < 0) {
                    jReturn('-1', '操作失败, 充值额度不能为负数');
                }
                if ($myself['balance'] < $money) {
                    jReturn('-1', '操作失败, 您的额度不足, 请先为自己充值');
                }
            }
        }

        $user_active = [];
        $user_passive = [];
        $user_active['balance'] = $myself['balance'] - $money;
        $user_passive['balance'] = $user['balance'] + $money;
        if($user_passive['balance'] < 0) {
            jReturn('-1', '用户可用余额不足');
        }
        if ($myself['pid'] > 1) {
            // 码商充值
            if (in_array($user['gid'], [61,71])) {
                $exchange = true;
                if($user_active['balance'] < 0) {
                    jReturn('-1', '您的额度不足, 请先为自己充值');
                }
            }
        }

        $res2 = true;
        $res4 = true;
        $this->mysql->startTrans();

        $res1 = $this->mysql->update($user_passive, "id={$user['id']}", 'sys_user');
        if ($exchange) {
            $res2 = $this->mysql->update($user_active, "id={$myself['id']}", 'sys_user');
        }

        $res3 = balanceLog($user, 1, 50, $money, $user['id'], $remark, $this->mysql);
        if ($exchange) {
            $res4 = balanceLog($myself, 1, 50, 0-$money, $myself['id'], $remark, $this->mysql);
        }

        if (!$res1 || !$res2 || !$res3 || !$res4) {
            $this->mysql->rollback();
            jReturn('-1', '系统繁忙请稍后再试');
        }

        $this->mysql->commit();
        jReturn('0', '操作成功');
    }
}