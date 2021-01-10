<?php
namespace app\admin\controller;

use think\Request;

class FinanceAccount extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }


    public function account()
    {
        $pageuser = checkPower();

        // 检查权限
        $sys_power = [];
        $sys_power['withdrawal'] = hasPower($pageuser, 'Finance_Withdrawal') ? 1 : 0;

        $data = [
            'sys_user' => $pageuser,
            'sys_power' => $sys_power,
        ];

        return $this->fetch("Finance/account", $data);
    }

    public function overview()
    {
        $pageuser = checkPower();
        $user = getUserinfo($pageuser['id'], true, $this->mysql);
        $user['djs_balance'] = 0;

        if (in_array($user['gid'], [81,91])) {
            $sql = "select sum(real_money) as money from sk_order where suid={$user['id']} and pay_status=9 and js_status=1";
            $djs_item = $this->mysql->fetchRow($sql);
            if ($djs_item) {
                $user['djs_balance'] = floatval($djs_item['money']);
            }
        }

        $cardlist = $this->mysql->fetchRows("select log.*,b.bank_name from cnf_card log left join cnf_bank b on log.bank_id=b.id where log.uid={$user['id']}");
        if ($cardlist) {
            $data['card_list'] = $cardlist;
        } else {
            $cardlist = [];
        }

        $data = [
            'user' => $user,
            'card_list' => $cardlist,
        ];

        //检测最低最高提现金额，可提现时间
        $cash_cnf = getConfig('cash_cnf');
        $day_time_arr = explode('-', $cash_cnf['day_time']);
        $cash_time_str = "提现时间：周一至周日 {$day_time_arr[0]} - {$day_time_arr[1]}";
        if (!$cash_cnf['weekend']) {
            $cash_time_str = "提现时间：周一至周五 {$day_time_arr[0]} - {$day_time_arr[1]}";
        }

        $min_cash_money = getConfig('min_cash_money');
        $max_cash_money = getConfig('max_cash_money');
        $day_cash_money = getConfig('max_day_cash_money');
        $cash_limit_str = "单笔最小：￥{$min_cash_money} ，单笔最大：￥{$max_cash_money} ，单日累计：￥{$day_cash_money}";

        $now_day = date('Ymd');
        $day_sum_money = $this->mysql->fetchResult("select sum(money) from cnf_cash_log where uid={$pageuser['id']} and create_day={$now_day} and status in(1,2)");
        if ($day_sum_money) {
            $day_cash_money -= $day_sum_money;
        }

        $cash_fee_config = getConfig('cash_shcharge_money');
        $cash_fee_str = "提现手续费 = 提现金额 × {$cash_fee_config[1]} + ￥{$cash_fee_config[2]}";

        $data['cash_fee_str'] = $cash_fee_str;
        $data['cash_time_str'] = $cash_time_str;
        $data['cash_limit_str'] = $cash_limit_str;
        $data['day_cash_money'] = $day_cash_money;

        jReturn('0', 'ok', $data);
    }

    public function detail()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $where = "where 1";
        if ($pageuser['gid'] >= 61) {
            $where .= " and log.uid={$pageuser['id']}";
        }
        if ($params['s_start_date'] && $params['s_end_date'] && $params['s_start_date'] <= $params['s_end_date']) {
            $s_start_date = strtotime($params['s_start_date'] . ' 00:00:00');
            $s_end_date = strtotime($params['s_end_date'] . ' 23:59:59');
            $where .= " and log.create_time between {$s_start_date} and {$s_end_date}";
        }
        if (isset($params['s_keyword']) && $params['s_keyword']) {
            $s_keyword = $params['s_keyword'];
            $where .= " and (u.account like '%{$s_keyword}%' or u.nickname like '%{$s_keyword}%')";
        }

        $sql_cnt = "select count(1) as cnt from cnf_balance_log log left join sys_user u on log.uid=u.id {$where}";
        $count_item = $this->mysql->fetchRow($sql_cnt);

        $sql = "select log.*,u.nickname,u.account,u.gid from cnf_balance_log log 
		left join sys_user u on log.uid=u.id {$where} order by log.id desc";

        $list = $this->mysql->fetchRows($sql, $params['page'], $params['limit']);
        $sys_group = getConfig('sys_group');
        $cnf_balance_type = getConfig('cnf_balance_type');
        foreach ($list as &$item) {
            if (isset($item['gid']) && $item['gid']) {
                $item['gname'] = $sys_group[$item['gid']];
            } else {
                $item['gname'] = '';
            }

            $item['type_flag'] = $cnf_balance_type[$item['type']];
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
        }
        $data = [
            'list' => $list,
            'count' => $count_item['cnt']
        ];

        $user = getUserinfo($pageuser['id'], true, $this->mysql);
        $data['balance'] = $user['balance'];
        $data['fz_balance'] = $user['fz_balance'];
        $data['djs_balance'] = 0;

        if (in_array($user['gid'], [81,91])) {
            $sql = "select sum(real_money) as money from sk_order where suid={$user['id']} and pay_status=9 and js_status=1";
            $djs_item = $this->mysql->fetchRow($sql);
            if ($djs_item) {
                $data['djs_balance'] = floatval($djs_item['money']);
            }
        }

        jReturn('0', 'ok', $data);
    }

    public function withdrawal()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $money = floatval($params['money']);
        $card_id = intval($params['card_id']);
        if (!$money || $money <= 0) {
            jReturn('-1', '金额不正确');
        }
        //检测最低最高提现金额，可提现时间
        $cash_cnf = getConfig('cash_cnf');
        $day_time_arr = explode('-', $cash_cnf['day_time']);

        $min_cash_money = getConfig('min_cash_money');
        $max_cash_money = getConfig('max_cash_money');
        $max_day_cash_money = getConfig('max_day_cash_money');
        $start_time = date('Y-m-d ') . $day_time_arr[0] . ':00';
        $end_time = date('Y-m-d ') . $day_time_arr[1] . ':59';
        if (NOW_DATE < $start_time || NOW_DATE > $end_time) {
            jReturn('-1', '当前时间不可提现');
        }
        if (!$cash_cnf['weekend']) {
            $date_w = date('w', NOW_TIME);
            if ($date_w == 6 || $date_w == 0) {
                jReturn('-1', '抱歉周末不可提现');
            }
        }

        if ($money < $min_cash_money) {
            jReturn('-1', "单笔最小可提现金额{$min_cash_money}");
        }
        if ($money > $max_cash_money) {
            jReturn('-1', "单笔最大可提现金额{$max_cash_money}");
        }
        $now_day = date('Ymd');
        $day_sum_money = $this->mysql->fetchResult("select sum(money) from cnf_cash_log where uid={$pageuser['id']} and create_day={$now_day} and status in(1,2)");
        if ($day_sum_money + $money > $max_day_cash_money) {
            jReturn('-1', '每天累计可提现金额' . $max_day_cash_money);
        }

        $card = $this->mysql->fetchRow("select log.*,b.bank_name,b.bank_code from cnf_card log left join cnf_bank b on log.bank_id=b.id where log.id={$card_id}");
        if (!$card || $card['uid'] != $pageuser['id']) {
            jReturn('-1', '未知提现银行卡');
        }
        $this->mysql->startTrans();
        $user = $this->mysql->fetchRow("select * from sys_user where id={$card['uid']} for update");
        if (!$user || $user['status'] != 2) {
            jReturn('-1', '账号被禁用，暂时无法提现');
        } else {
            $password2 = getPassword($params['password2']);
            if ($user['password2'] != $password2) {
                jReturn('-1', '二级密码不正确');
            }
        }
        $new_balance = $user['balance'] - $money;
        if ($new_balance < 0) {
            jReturn('-1', '可提现余额不足');
        }
        $user_data = ['balance' => $new_balance];
        $cash_shcharge_money = getConfig('cash_shcharge_money');
        $fee = $money * $cash_shcharge_money[1] + $cash_shcharge_money[2];
        $cash_log = [
            'uid' => $user['id'],
            'csn' => 'C' . date('YmdHis') . mt_rand(1000, 9999),
            'card_id' => $card['id'],
            'bank_account' => $card['bank_account'],
            'bank_realname' => $card['bank_realname'],
            'money' => $money,
            'fee' => $fee,
            'real_money' => $money - $fee,
            'ori_balance' => $user['balance'],
            'new_balance' => $new_balance,
            'create_time' => NOW_TIME,
            'create_day' => date('Ymd', NOW_TIME),
            'card_info' => json_encode($card, JSON_UNESCAPED_UNICODE)
        ];
        if ($cash_log['real_money'] < 0.01) {
            jReturn('-1', '扣除手续费后实际到账金额不足0.01');
        }
        $res1 = $this->mysql->update($user_data, "id={$user['id']}", 'sys_user');
        $res2 = $this->mysql->insert($cash_log, 'cnf_cash_log');
        $res3 = balanceLog($user, 1, 11, -$money, $res2, $cash_log['csn'], $this->mysql);
        if (!$res1 || !$res2 || !$res3) {
            $this->mysql->rollback();
            jReturn('-1', '系统繁忙请稍后再试');
        }

        //$url="{$_ENV['SOCKET']['HTTP_URL']}/?c=Admin&a=noticeCash&csn={$cash_log['csn']}";
        //curl_get($url);

        $this->mysql->commit();
        jReturn('0', '提交申请成功，请耐心等待审核');
    }
}