<?php
namespace app\admin\controller;

use think\Request;

class PayOrder extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function order()
    {
        $pageuser = checkPower();
        $channel_arr = $this->mysql->fetchRows("select * from sk_channel where is_open=1");
        $pay_status_arr = getConfig('cnf_pay_status');

        // 检查权限
        $sys_power = [];
        $sys_power['add'] = hasPower($pageuser, 'Pay_OrderAdd') ? 1 : 0;
        $sys_power['patch'] = hasPower($pageuser, 'Pay_OrderPatch') ? 1 : 0;
        $sys_power['callback'] = hasPower($pageuser, 'Pay_OrderCallback') ? 1 : 0;

        $data = [
            'sys_user' => $pageuser,
            'sys_power' => $sys_power,
            'sys_channel' => $channel_arr,
            'sys_pay_status' => $pay_status_arr
        ];

        return $this->fetch("Pay/order", $data);
    }

    public function orderList()
    {
        $pageuser = checkLogin();
        $params = $this->params;

        $where = "where log.pay_status<99";
        if ($params['s_start_date'] && $params['s_end_date'] && $params['s_start_date'] <= $params['s_end_date']) {
            $s_start_date = strtotime($params['s_start_date'] . ' 00:00:00');
            $s_end_date = strtotime($params['s_end_date'] . ' 23:59:59');
            $where .= " and log.create_time between {$s_start_date} and {$s_end_date}";
        }
        if ($pageuser['gid'] >= 61) {
            $uid_arr = getDownUser($pageuser['id']);
            $uid_arr[] = $pageuser['id'];
            $uid_str = implode(',', $uid_arr);
            $where .= " and (log.suid in({$uid_str}) or log.muid in({$uid_str}))";
        }
        $s_keyword = $params['s_keyword'];
        $s_channel = intval($params['s_channel']);
        $s_pay_status = intval($params['s_pay_status']);
        if ($s_channel) {
            $where .= " and log.ptype={$s_channel}";
        }
        if ($s_pay_status) {
            $where .= " and log.pay_status={$s_pay_status}";
        }
        if ($s_keyword) {
            $where .= " and (log.order_sn like '%{$s_keyword}%' or log.out_order_sn like '%{$s_keyword}%' or su.account like '%{$s_keyword}%' or su.nickname like '%{$s_keyword}%' or mu.account like '%{$s_keyword}%' or mu.nickname like '%{$s_keyword}%')";
        }

        $sql_cnt = "select count(1) as cnt,sum(log.money) as sum_money,sum(log.fee) as sum_fee,sum(real_money) as sum_real_money  
		from sk_order log 
		left join sys_user su on log.suid=su.id 
		left join sk_channel mt on log.ptype=mt.id
		left join sys_user mu on log.muid=mu.id {$where}";
        $count_item = $this->mysql->fetchRow($sql_cnt);

        $sql_cnt_succeed = $sql_cnt . " and log.pay_status = 9";
        $count_item_succeed = $this->mysql->fetchRow($sql_cnt_succeed);
        $count_percent = '0%';
        if ($count_item['cnt'] > 0) {
            $count_percent = round(($count_item_succeed['cnt'] / $count_item['cnt']) * 100, 2) . '%';
        }

        $sql = "select log.*,su.account as su_account,su.nickname as su_nickname,
		mu.account as mu_account,mu.nickname as mu_nickname,mt.name as mtype_name
		from sk_order log 
		left join sys_user su on log.suid=su.id 
		left join sk_channel mt on log.ptype=mt.id 
		left join sys_user mu on log.muid=mu.id 
		{$where} order by log.id desc";

        $list = $this->mysql->fetchRows($sql, $params['page'], $params['limit']);
        $cnf_pay_status = getConfig('cnf_pay_status');
        $cnf_notice_status = getConfig('cnf_notice_status');
        foreach ($list as &$item) {
            $item['create_time'] = date('m-d H:i:s', $item['create_time']);
            if ($item['pay_time']) {
                $item['pay_time'] = date('m-d H:i:s', $item['pay_time']);
            } else {
                $item['pay_time'] = '';
            }
            if ($item['js_time']) {
                $item['js_time'] = date('m-d H:i:s', $item['js_time']);
            } else {
                $item['js_time'] = '';
            }
            if ($item['pay_status']) {
                $item['pay_status_flag'] = $cnf_pay_status[$item['pay_status']];
            }
            if ($item['notice_status']) {
                $item['notice_status_flag'] = $cnf_notice_status[$item['notice_status']];
            }
            $item['fee'] = floatval($item['fee']);
            $item['money'] = floatval($item['money']);
            $item['real_money'] = floatval($item['real_money']);

            $up_user = getUpUser($item['muid'], true);
            $up_arr = [];
            foreach ($up_user as $uuv) {
                if ($uuv['gid'] == 61) {
                    $up_arr[] = [
                        'account' => $uuv['account'],
                        'nickname' => $uuv['nickname']
                    ];
                }
            }
            $item['up_arr'] = $up_arr;

            $item['callback'] = hasPower($pageuser, 'Pay_OrderCallback') ? 1 : 0;
            $item['patch'] = hasPower($pageuser, 'Pay_OrderPatch') ? 1 : 0;
        }

        $data = [
            'list' => $list,
            'count' => $count_item['cnt'],
            'count_succeed' => $count_item_succeed['cnt'],
            'count_percent' => $count_percent,
            'sum_money' => floatval($count_item['sum_money']),
            'sum_money_succeed' => floatval($count_item_succeed['sum_money']),
            'sum_fee' => floatval($count_item['sum_fee']),
            'sum_real_money' => floatval($count_item['sum_real_money'])
        ];
        jReturn('0', 'ok', $data);
    }

    // 确认收款/超时补单
    public function orderCheck()
    {
        /*
        $cnf_pay_status=getConfig('cnf_pay_status');
        $return_data=[
            'pay_time'=>date('Y-m-d H:i'),
            'pay_status'=>3,
            'pay_status_flag'=>$cnf_pay_status[3]

        ];
        jReturn('1','Ceshi',$return_data);*/

        $pageuser = checkLogin();
        $order_sn = $this->params['osn'];
        $money = $this->params['money'];
        $sql = "select * from sk_order where order_sn='{$order_sn}' and money={$money} and pay_status<9";
        $order = $this->mysql->fetchRow($sql);
        if (!$order) {
            jReturn('-1', '不存在相应的订单');
        }
        if ($order['muid'] != $pageuser['id']) {
            jReturn('-1', '您没有权限操作该订单');
        }
        if ($order['pay_status'] == 9) {
            jReturn('-1', '该订单已确认，请不要重复确认');
        }
        if ($order['money'] != $money) {
            jReturn('-1', '订单金额不正确');
        }
        $user = getUserinfo($pageuser['id'], true, $this->mysql);
        if ($order['pay_status'] == 3) {
            $password2 = getPassword($this->params['password2']);
            if ($password2 != $user['password2']) {
                jReturn('-1', '二级密码不正确');
            }
        }

        $res1 = true;
        $res2 = true;
        $res3 = true;
        $this->mysql->startTrans();
        if ($order['pay_status'] == 3) {
            if ($user['balance'] < $money) {
                jReturn('-1', '您的接单余额不足，无法补单');
            }
            $sys_user = [
                'balance' => $user['balance'] - $money
            ];
            $res1 = $this->mysql->update($sys_user, "id={$user['id']}", 'sys_user');
            $res2 = balanceLog($user, 0, 1, 16, -$money, $order['id'], $order['order_sn'], $this->mysql);
        } elseif (in_array($order['pay_status'], [1, 2])) {
            if ($user['fz_balance'] < $money) {
                jReturn('-1', '您的冻结余额不足，无法确认');
            }
            $sys_user = [
                'fz_balance' => $user['fz_balance'] - $money
            ];
            $res1 = $this->mysql->update($sys_user, "id={$user['id']}", 'sys_user');
            $res2 = balanceLog($user, 0, 2, 14, -$money, $order['id'], $order['order_sn'], $this->mysql);
        } else {
            jReturn('-1', '该订单当前状态不可操作');
        }
        $sk_order = [
            'remark' => $order_sn,
            'pay_status' => 9,
            'pay_time' => NOW_TIME,
            'pay_day' => date('Ymd', NOW_TIME)
        ];

        $res4 = $this->mysql->update($sk_order, "id={$order['id']}", 'sk_order');
        if (!$res1 || !$res2 || !$res3 || !$res4) {
            $this->mysql->rollback();
            jReturn('-1', '系统繁忙请稍后再试');
        }
        $this->mysql->commit();

        //写入异步通知记录
        $cnf_notice = [
            'type' => 2,
            'fkey' => $order['order_sn'],
            'create_time' => NOW_TIME,
            'remark' => '确认成功通知支付用户'
        ];
        $this->mysql->insert($cnf_notice, 'cnf_notice');

        /*
        echo json_encode([
            'code' => '0',
            'msg' => '操作成功',
            ['pay_status' => 9]
        ], JSON_UNESCAPED_UNICODE);
        */
        //发起回调给商户
        orderNotify($order['id'], $this->mysql);
        jReturn('0', '操作成功', ['pay_status' => 9]);
    }
}