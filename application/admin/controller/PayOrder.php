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
        $channel_arr = $this->mysql->fetchRows("select * from sk_mtype where is_open=1 and id = 204");
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
            if (in_array($s_channel, [204, 205])) {
                $where .= " and log.ptype in (204,205)";
            } else {
                $where .= " and log.ptype={$s_channel}";
            }
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
		left join sk_mtype mt on log.ptype=mt.id
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
		left join sk_mtype mt on log.ptype=mt.id 
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

    public function skmaDelete()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $id = intval($params['id']);
        $uid = intval($params['uid']);
        if (!$id || !$uid) {
            jReturn('-1', '缺少参数');
        }
        if ($pageuser['gid'] >= 61) {
            $uid_arr = getDownUser($pageuser['id']);
            $uid_arr[] = $pageuser['id'];
            if (!in_array($uid, $uid_arr)) {
                jReturn('-1', '操作失败! 该用户不是您的下级');
            }
        }

        $data = ['status' => 99];
        $res = $this->mysql->update($data, "id={$id}", 'sk_ma');
        if (!$res) {
            jReturn('-1', '系统繁忙请稍后再试');
        }

        actionLog(['opt_name' => '删除收款码', 'sql_str' => $this->mysql->lastSql], $this->mysql);
        jReturn('0', '操作成功');
    }

    public function skmaUpdate()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $id = 0;
        if (isset($params['id'])) {
            $id = intval($params['id']);
        }
        if (!isset($params['uid'])) {
            jReturn('-1', '用户id错误');
        }
        if (!isset($params['mtype_id'])) {
            jReturn('-1', '请选择支付类型');
        }
        if (!isset($params['ma_account'])) {
            jReturn('-1', '请填写通道账号');
        }
        if (!isset($params['ma_cookie'])) {
            jReturn('-1', '请输入cookie');
        }
        if (!isset($params['mt_money'])) {
            $params['mt_money'] = 0;
        }

        $uid = intval($params['uid']);
        $channel = intval($params['mtype_id']);

        if (!$channel) {
            jReturn('-1', '请选择支付类型');
        } else {
            $mtype = $this->mysql->fetchRow("select * from sk_mtype where id={$channel}");
            if (!$mtype) {
                jReturn('-1', '支付类型不正确');
            } elseif (!$mtype['is_open']) {
                jReturn('-1', '该支付类型暂未开放');
            }
        }

        $user = getUserinfo($uid, true, $this->mysql);
        if (!$user) {
            jReturn('-1', '无法为该用户创建收款码');
        }
        $td_switch = json_decode($user['td_switch'], true);
        if (!array_key_exists($channel, $td_switch)) {
            jReturn('-1', '用户暂未开放该支付类型');
        } elseif (!$td_switch[$channel]) {
            jReturn('-1', '用户暂未开放该支付类型');
        }

        if (!isset($params['min_money']) || !$params['min_money']) {
            $params['min_money'] = floatval(getConfig('cnf_skm_min_money'));;
        }
        if (!isset($params['max_money']) || !$params['max_money']) {
            $params['max_money'] = floatval(getConfig('cnf_skm_max_money'));;
        }
        if ($params['max_money'] < $params['min_money']) {
            jReturn('-1', '最大收款不能小于最小收款');
        }

        $skma = [
            'uid' => $uid,
            'mtype_id' => $channel,
            'mt_money' => floatval($params['mt_money']),
            'ma_cookie' => $params['ma_cookie'],
            'ma_account' => $params['ma_account'],
            'min_money' => $params['min_money'],
            'max_money' => $params['max_money'],
        ];

        if ($id) {
            $item = $this->mysql->fetchRow("select uid from sk_ma where id={$id} and status<99");
            if (!$item) {
                jReturn('-1', '不存在相应的收款码');
            }
            if ($pageuser['gid'] >= 61) {
                if ($item['uid'] != $pageuser['id']) {
                    $down_arr = getDownUser($pageuser['id']);
                    if (!in_array($item['uid'], $down_arr)) {
                        jReturn('-1', '您没有权限操作该收款码');
                    }
                }
            }
        }

        $res = true;
        $this->mysql->startTrans();
        if (!$id) {
            $skma['status'] = 1;
            $skma['create_time'] = NOW_TIME;
            $skma['fz_time'] = NOW_TIME + 90 * 86400;
            $res = $this->mysql->insert($skma, 'sk_ma');
            $id = $res;
        } else {
            $res = $this->mysql->update($skma, "id={$id}", 'sk_ma');
        }

        if (!$res) {
            $this->mysql->rollback();
            jReturn('-1', '系统繁忙请稍后再试');
        } else {
            $this->mysql->commit();
        }

        $skma['id'] = $id;
        actionLog(['opt_name' => '更新收款码', 'sql_str' => json_encode($skma, JSON_UNESCAPED_UNICODE)], $this->mysql);
        jReturn('0', '操作成功');
    }

    public function skmaOnline()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $status = $params['status'];
        $maid = intval($params['id']);
        $uid = intval($params['uid']);
        if ($pageuser['gid'] >= 61) {
            $uid_arr = getDownUser($pageuser['id']);
            $uid_arr[] = $pageuser['id'];
            if (!in_array($uid, $uid_arr)) {
                jReturn('-1', '操作失败! 该用户不是您的下级');
            }
        }

        $log_str = '收款码下线';
        if ($status == 1) {
            $status = 2;
            $log_str = '收款码上线';
        } else {
            $status = 1;
        }
        $data = [
            'status' => $status
        ];
        if ($status == 2) {
            $data['fz_time'] = 0;
        }
        $res = $this->mysql->update($data, "id={$maid}", 'sk_ma');
        if (!$res) {
            jReturn('-1', '系统繁忙请稍后再试');
        }

        actionLog(['opt_name' => $log_str, 'sql_str' => $this->mysql->lastSql], $this->mysql);
        jReturn('0', '操作成功');
    }

    public function skmaTest()
    {
        jReturn('-1', 'skmaTest');
    }
}