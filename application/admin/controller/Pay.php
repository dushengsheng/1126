<?php
namespace app\admin\controller;

use think\Request;
use app\admin\channel\BankToAlipay;

class Pay extends Base
{
    protected $getMaNum = 0;
    protected $checkMaArr = [];

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function _initialize()
    {
        parent::_initialize();
        //debugLog('params = ' . var_export($this->params, true));
    }

    public function skma()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skma();
    }

    public function skmaList()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skmaList();
    }

    public function skmaDelete()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skmaDelete();
    }

    public function skmaUpdate()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skmaUpdate();
    }

    public function skmaOnline()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skmaOnline();
    }

    public function skmaTest()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skmaTest();
    }


    /**
     ********************************
     */
    public function order()
    {
        $pay_order = new PayOrder();
        return $pay_order->order();
    }

    public function orderList()
    {
        $pay_order = new PayOrder();
        return $pay_order->orderList();
    }

    public function orderCheck()
    {
        $pay_order = new PayOrder();
        return $pay_order->orderCheck();
    }


    /**
     *******************************
     */
    protected function getChannel($title)
    {
        $channel = null;
        if ($title == 'bank_to_alipay') {
            $channel = new BankToAlipay();
        }
        // TODO
        // more channels
        return $channel;
    }

    /*
    public function index()
    {
        $channel = $this->getChannel('bank_to_alipay');
        return $channel->index();
    }*/

    //订单提交接口
    public function index()
    {
        $params = $this->params;
        $paramsForLog = $params;
        $paramsForLog['systime'] = date('Y-m-d H:i:s', NOW_TIME);
        file_put_contents(ROOT_PATH . 'logs/order.txt', var_export($paramsForLog, true) . "\n\n", FILE_APPEND);

        if (isset($_REQUEST['crypted'])) {
            $rsa_pt_private = getConfig('rsa_pt_private');
            $resultArr = decryptRsa($_REQUEST['crypted'], $rsa_pt_private);
            if ($resultArr['code'] != '0') {
                jReturn('-1', $resultArr['msg']);
            }
            $params = $resultArr['data'];
        }
        if (!isset($params['account']) ||
            !isset($params['bank']) ||
            !isset($params['channel']) ||
            !isset($params['client_ip']) ||
            !isset($params['format']) ||
            !isset($params['money']) ||
            !isset($params['notify_url']) ||
            !isset($params['order_sn']) ||
            !isset($params['timestamp']) ||
            !isset($params['sign'])) {
            jReturn('-1', '缺少参数');
        }

        if (abs(NOW_TIME - $params['timestamp']) > 300) {
            jReturn('-1', '请求已超时，请重新提交');
        }
        $p_data = [
            'account' => $params['account'],
            'bank' => $params['bank'],
            'channel' => $params['channel'],
            'client_ip' => $params['client_ip'],
            'format' => $params['format'],
            'money' => $params['money'],
            'notify_url' => urldecode(htmlspecialchars_decode($params['notify_url'])),
            'order_sn' => $params['order_sn'],
            'timestamp' => $params['timestamp'],
            'sign' => $params['sign']
        ];
        if ($p_data['money'] < 0.01) {
            jReturn('-1', '金额不正确');
        }

        $mysql = $this->mysql;
        $bank = $mysql->fetchRow("select * from cnf_bank where bank_code='{$p_data['bank']}' and status=1");
        if (!$bank) {
            jReturn('-1', '银行不存在或已被禁用');
        }
        $merchant = $mysql->fetchRow("select * from sys_user where account='{$p_data['account']}' and status=2");
        if (!$merchant) {
            jReturn('-1', '商户不存在或已被禁用');
        }
        if ($merchant['is_rsa']) {
            if (!isset($_REQUEST['crypted'])) {
                jReturn('-1', '商户已开启RSA接口加密，请传入密文参数');
            }
        } else {
            if (isset($_REQUEST['crypted'])) {
                jReturn('-1', '商户未开启RSA接口加密，请传入明文参数');
            }
        }

        $ptype = intval($p_data['channel']);
        $merchant_rate = getUserChannelRate($merchant, $ptype);
        if (!isUserChannelOpen($merchant, $ptype)) {
            jReturn('-1', '商户未开通通道:' . $ptype);
        }
        if ($merchant_rate <= 0.0) {
            jReturn('-1', '商户号未设置费率激活');
        }
        if (!$merchant['apikey']) {
            jReturn('-1', '商户未生成签名密钥');
        }
        $sign = md5Sign($p_data, $merchant['apikey']);
        if ($sign != $params['sign']) {
            $p_data['sign'] = $params['sign'];
            $p_data['pt_sign'] = $sign;
            file_put_contents(ROOT_PATH . 'logs/pay_sign.txt', var_export($p_data, true) . "\n\n", FILE_APPEND);
            jReturn('-1', '签名错误');
        }

        $check_mc_order = $mysql->fetchRow("select id from sk_order where out_order_sn='{$p_data['order_sn']}'");
        if ($check_mc_order['id']) {
            jReturn('-1', "商户单号已存在，请勿重复提交 {$p_data['order_sn']}");
        }

        $channel = $mysql->fetchRow("select * from sk_channel where id={$ptype} and is_open=1");
        if (!$channel) {
            jReturn('-1', '不存在该支付类型或未开放');
        } else {
            if ($p_data['money'] < $channel['min_money']) {
                jReturn('-1', "该通道最小订单金额为{$channel['min_money']}");
            }
            if ($p_data['money'] > $channel['max_money']) {
                jReturn('-1', "该通道最大订单金额为{$channel['max_money']}");
            }
        }

        //##########指定代理转换成指定码商##########
        $appoint_ms_arr = [];
        if ($merchant['appoint_agent']) {
            $appoint_agent_arr = explode(',', $merchant['appoint_agent']);
            foreach ($appoint_agent_arr as $aid) {
                $children = getDownUser($aid, true);
                foreach ($children as $child) {
                    if (!in_array($child['gid'], [61,71])) {
                        continue;
                    }
                    $appoint_ms_arr[] = $child['id'];
                }
                $appoint_ms_arr[] = $aid;
            }
        }
        if ($merchant['appoint_ms']) {
            $appoint_ms_arr_tmp = explode(',', $merchant['appoint_ms']);
            if (!$appoint_ms_arr_tmp) {
                $appoint_ms_arr_tmp = [];
            }
            $appoint_ms_arr = array_merge($appoint_ms_arr, $appoint_ms_arr_tmp);
        }
        $p_data['appoint_ms'] = $appoint_ms_arr;
        //##########指定代理转换成指定码商##########

        $sk_ma = $this->getSkma($p_data, $mysql);
        if (!$sk_ma) {
            jReturn('-1', '未匹配到在线的收款码，请更换金额再次尝试');
        }
        $mysql->startTrans();
        $ma_user = $mysql->fetchRow("select id,gid,balance,fz_balance,fy_rate from sys_user where id={$sk_ma['uid']} for update");
        $ma_rate = getUserChannelRate($ma_user, $ptype);
        $merchant_fee = $p_data['money'] * $merchant_rate;
        $ma_fee = $p_data['money'] * $ma_rate;
        $over_time = getConfig('skorder_over_time');
        $sk_order = [
            'muid' => $sk_ma['uid'],//码商id
            'suid' => $merchant['id'],//商户id
            'ptype' => $ptype,
            'order_sn' => 'SYS' . date('YmdHis', NOW_TIME) . mt_rand(10000, 99999),
            'out_order_sn' => $p_data['order_sn'],
            'money' => $p_data['money'],
            'real_money' => $p_data['money'] - $merchant_fee,
            'rate' => $merchant_rate,
            'fee' => $ma_fee,
            'ma_id' => $sk_ma['id'],//码id
            'ma_account' => $sk_ma['ma_account'],
            'ma_qrcode' => $sk_ma['ma_qrcode'],
            //'ma_bank_id' => $sk_ma['bank_id'],
            'ma_bank_id' => $bank['id'],
            'order_ip' => $p_data['client_ip'],
            'notify_url' => $p_data['notify_url'],
            'create_time' => NOW_TIME,
            'create_day' => date('Ymd', NOW_TIME),
            'over_time' => NOW_TIME + $over_time,
            'device' => Request::instance()->isMobile() ? 'mobile' : 'pc',
            'reffer_url' => $_SERVER['HTTP_REFERER']
        ];

        $ma_sys_user = [
            'balance' => $ma_user['balance'] - $p_data['money'],
            'fz_balance' => $ma_user['fz_balance'] + $p_data['money'],
            'queue_time' => NOW_TIME
        ];
        if ($ma_sys_user['balance'] < 1000) {
            jReturn('-1', '已匹配到的码商接单余额不足，请重新尝试');
        }

        //更新收款码的排队时间
        $sk_ma_data = [
            'queue_time' => NOW_TIME
        ];

        $res1 = $mysql->insert($sk_order, 'sk_order');
        $res2 = $mysql->update($ma_sys_user, "id={$ma_user['id']}", 'sys_user');
        $res3 = balanceLog($ma_user, $merchant['id'], 1, 13, -$p_data['money'], $res1, $sk_order['order_sn'], $mysql);
        $res4 = balanceLog($ma_user, $merchant['id'], 2, 13, $p_data['money'], $res1, $sk_order['order_sn'], $mysql);
        $res5 = $mysql->update($sk_ma_data, "id={$sk_ma['id']}", 'sk_ma');
        if (!$res1 || !$res2 || !$res3 || !$res4 || !$res5) {
            $mysql->rollback();
            jReturn('-1', '系统繁忙请稍后再试');
        }
        $mysql->commit();

        //写入异步通知记录
        $cnf_notice = [
            'type' => 1,
            'fkey' => $sk_order['order_sn'],
            'create_time' => NOW_TIME,
            'remark' => '新订单通知码商'
        ];
        $mysql->insert($cnf_notice, 'cnf_notice');

        $return_data = [
            'account' => $p_data['account'],
            'order_sn' => $p_data['order_sn'],
            'system_sn' => $sk_order['order_sn']
        ];

        if (empty($bank)) {
            $pay_url = GATEWAY_URL . "/api.php?c=cashier&order_no=" . $sk_order['order_sn'];
            $return_data['pay_url'] = $pay_url;
        } else {
            $pay_url = GATEWAY_URL . "/qrcode.php?order_no=" . $sk_order['order_sn'] . "&step=1";
            $return_data['pay_url'] = $pay_url;
        }

        jReturn('0', '下单成功', $return_data);
    }

    //匹配码
    private function getSkma($p_data, $mysql)
    {
        $min_match_money = floatval(getConfig('min_match_money'));
        $ptype = intval($p_data['channel']);
        $limit_balance = $min_match_money + $p_data['money'];

        /*
        $order_arr=$this->mysql->fetchRows("select ma_id from sk_order where ptype={$ptype} and pay_status in(1,2) and money='{$p_data['money']}'");
        foreach($order_arr as $ev){
            $this->checkMaArr[]=$ev['ma_id'];
        }
        */

        $sql = "select log.* from sk_ma log left join sys_user u on log.uid=u.id 
		where log.mtype_id={$ptype} and log.status=2 
		and (log.min_money<={$p_data['money']} and log.max_money>={$p_data['money']}) 
		and (u.status=2 and u.is_online=1 and u.balance>={$limit_balance})";

        //###########指定代理/码商###########
        if ($p_data['appoint_ms']) {
            $appoint_ms_str = implode(',', $p_data['appoint_ms']);
            $sql .= " and log.uid in ({$appoint_ms_str})";
        }
        //###########指定代理/码商###########
        if ($this->checkMaArr) {
            $exp_skmids = implode(',', $this->checkMaArr);
            $sql .= " and log.id not in ({$exp_skmids})";
        }

        $sql .= " order by u.queue_time asc,log.queue_time asc";
        $sk_ma = $mysql->fetchRow($sql);
        file_put_contents(ROOT_PATH . 'logs/ma_sql.txt', $sql . "\n\n", FILE_APPEND);

        while ($sk_ma) {
            $ma_user = getUserinfo($sk_ma['uid'], true, $this->mysql);
            if (!$ma_user) {
                $this->checkMaArr[] = $sk_ma['id'];
                $sk_ma = $this->getSkma($p_data, $mysql);
                break;
            }
            if (!isUserChannelOpen($ma_user, $ptype)) {
                $this->checkMaArr[] = $sk_ma['id'];
                $sk_ma = $this->getSkma($p_data, $mysql);
                break;
            }
            break;
        }

        // 10分钟之内有3单相同金额
        $now_time = NOW_TIME;
        //检测该码商是否有相同金额订单
        if ($sk_ma) {
            $check_order = $mysql->fetchRow("select id from sk_order where ma_id={$sk_ma['id']} and pay_status<=2 and money={$p_data['money']} and over_time>{$now_time}");
            if ($check_order['id']) {
                $this->checkMaArr[] = $sk_ma['id'];
                $this->getMaNum++;
                if ($this->getMaNum <= 5) {
                    $sk_ma = $this->getSkma($p_data, $mysql);
                } else {
                    $sk_ma = null;
                }
            }
        } else {
            $cnf_msappoint_other = getConfig('cnf_msappoint_other');
            if ($p_data['appoint_ms'] && $cnf_msappoint_other == '是') {
                $p_data['appoint_ms'] = null;
                $sk_ma = $this->getSkma($p_data, $mysql);
            }
        }
        return $sk_ma;
    }

    public function query()
    {
        jReturn('-1', '暂时没有实现查询接口');
    }

    public function notify()
    {
        $params = $this->params;
        $money = floatval($params['money']);
        $ma_id = intval($params['landid']);
        $trade_time = intval($params['tradeTime']) / 1000;
        $remark = $params['tradeNo'];

        $sql = "select * from sk_order where 
            ma_id={$ma_id} and money={$money} and pay_status<9 and over_time>{$trade_time} and (isnull(remark) or remark!='{$remark}')";

        $order_list = $this->mysql->fetchRows($sql);
        if (!$order_list) {
            jReturn('-1', '订单不存在');
        }
        if (count($order_list) > 1) {
            jReturn('-1', '订单重复');
        }

        $order = $order_list[0];
        $user = $this->mysql->fetchRow("select * from sys_user where id={$order['muid']}");
        if (!$user) {
            jReturn('-1', '码商不存在');
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
            'remark' => $remark,
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

        //发起回调给商户
        orderNotify($order['id'], $this->mysql);

        //写入异步通知记录
        $cnf_notice = [
            'type' => 2,
            'fkey' => $order['order_sn'],
            'create_time' => NOW_TIME,
            'remark' => '确认成功通知支付用户'
        ];
        $this->mysql->insert($cnf_notice, 'cnf_notice');

        /*
        //如果是超时补单下线该码
        if ($order['pay_status'] == 3) {
            if (!$order['is_test']) {
                $sk_ma = [
                    'status' => 1,
                    'fz_time' => NOW_TIME + 86400 * 90
                ];
                $this->mysql->update($sk_ma, "id={$order['ma_id']}", 'sk_ma');
            }
        } elseif (in_array($order['pay_status'], [1, 2])) {
            if ($order['is_test']) {
                $sk_ma = [
                    'fz_time' => 0
                ];
                $this->mysql->update($sk_ma, "id={$order['ma_id']}", 'sk_ma');
            }
        }

        $cnf_pay_status = getConfig('cnf_pay_status');
        $return_data = [
            'pay_time' => date('Y-m-d H:i', $sk_order['pay_time']),
            'pay_status' => $sk_order['pay_status'],
            'pay_status_flag' => $cnf_pay_status[$sk_order['pay_status']]

        ];*/
        jReturn('200', '回调成功');
    }

    public function test()
    {
        $isajax = Request::instance()->isAjax();
        $params = $this->params;

        if ($isajax) {
            $merchant = $this->mysql->fetchRow("select * from sys_user where account='{$params['account']}' and gid in (81,91)");
            if (!$merchant) {
                jReturn('-1', '商户不存在');
            }
            $sign_data = [
                'account' => $params['account'],
                'bank' => $params['bank'],
                'channel' => $params['channel'],
                'client_ip' => getClientIp(),
                'format' => $params['format'],
                'money' => $params['money'],
                'notify_url' => $params['notify_url'],
                'order_sn' => 'TEST' . date('YmdHis', NOW_TIME) . mt_rand(10000, 99999),
                'timestamp' => NOW_TIME
            ];
            $sign = md5Sign($sign_data, $merchant['apikey']);
            $sign_data['sign'] = $sign;
            $http_response = curl_post(ADMIN_URL . '/pay/index', $sign_data);
            $response_json = $http_response['output'];
            $response_arr = json_decode($response_json, true);
            jReturn($response_arr);
        } else {
            $pageuser = checkLogin();
            $children = getDownUser($pageuser['id'], true);
            $merchant_arr = [];
            foreach ($children as $child) {
                if (!in_array($child['gid'], [81,91])) {
                    continue;
                }
                $merchant_arr[] = $child;
            }
            if (in_array($pageuser['gid'], [81,91])) {
                $merchant_arr[] = $pageuser;
            }
            $bank_arr = $this->mysql->fetchRows("select * from cnf_bank where status = 1");
            $data = [
                'bank_list' => $bank_arr,
                'merchant_list' => $merchant_arr
            ];
            return $this->fetch("Pay/test", $data);
        }
    }

    public function notifyTest()
    {
        /*
        $data = [
            'pay_status' => '9',
            'money' => '400.00',
            'order_sn' => 'TEST2021011814464973745',
            'pay_time' => '1610952431',
        ];*/
        jReturn('0', 'success');
    }
}