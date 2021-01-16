<?php
namespace app\admin\controller;

use think\Request;
use app\admin\channel\BankToAlipay;

class Pay extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function _initialize()
    {
        parent::_initialize();
        debugLog('params = ' . var_export($this->params, true));
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

        if ($_REQUEST['crypted']) {
            $rsa_pt_private = getConfig('rsa_pt_private');
            $resultArr = decryptRsa($_REQUEST['crypted'], $rsa_pt_private);
            if ($resultArr['code'] != '0') {
                jReturn('-1', $resultArr['msg']);
            }
            $params = $resultArr['data'];
        }

        if (!isset($params['account']) ||
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
        $p_data = array(
            'account' => $params['account'],
            'channel' => $params['channel'],
            'client_ip' => $params['client_ip'],
            'format' => $params['format'],
            'money' => $params['money'],
            'notify_url' => urldecode(htmlspecialchars_decode($params['notify_url'])),
            'order_sn' => $params['order_sn'],
            'timestamp' => $params['timestamp'],
            'sign' => $params['sign']
        );
        if ($p_data['money'] < 0.01) {
            jReturn('-1', '金额不正确');
        }

        $mysql = $this->mysql;
        $user = $mysql->fetchRow("select * from sys_user where account='{$p_data['account']}' and status=2");
        if (!$user) {
            jReturn('-1', '商户不存在或已被禁用');
        }
        if ($user['is_rsa']) {
            if (!isset($_REQUEST['crypted'])) {
                jReturn('-1', '商户已开启RSA接口加密，请传入密文参数');
            }
        } else {
            if (isset($_REQUEST['crypted'])) {
                jReturn('-1', '商户未开启RSA接口加密，请传入明文参数');
            }
        }
        $ptype = intval($p_data['channel']);
        $user['td_rate'] = json_decode($user['td_rate'], true);
        $user['td_switch'] = json_decode($user['td_switch'], true);
        if (!array_key_exists($ptype, $user['td_switch'])) {
            jReturn('-1', '商户未开通通道:' . $ptype);
        }
        if (!array_key_exists($ptype, $user['td_rate'])) {
            jReturn('-1', '商户号未设置费率激活');
        }
        if (!$user['apikey']) {
            jReturn('-1', '商户未生成签名密钥');
        }
        $sign = md5Sign($p_data, $user['apikey']);
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
        if ($user['appoint_agent']) {
            $appoint_agent_arr = explode(',', $user['appoint_agent']);
            foreach ($appoint_agent_arr as $aid) {
                $down_ms = getDownUser($aid);
                if (!$down_ms) {
                    $down_ms = [];
                }
                $appoint_ms_arr = array_merge($down_ms, [$aid]);
            }
        }
        if ($user['appoint_ms']) {
            $appoint_ms_arr_tmp = explode(',', $user['appoint_ms']);
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
        $ma_user = $mysql->fetchRow("select id,balance,fz_balance from sys_user where id={$sk_ma['uid']} for update");
        $rate = $user['td_rate'][$ptype];
        $fee = $p_data['money'] * $rate;
        $sk_order = [
            'muid' => $sk_ma['uid'],//码商id
            'suid' => $user['id'],//商户id
            'ptype' => $ptype,
            'order_sn' => 'MS' . date('YmdHis', NOW_TIME) . mt_rand(10000, 99999),
            'out_order_sn' => $p_data['order_sn'],
            'money' => $p_data['money'],
            'real_money' => $p_data['money'] - $fee,
            'rate' => $rate,
            'fee' => $fee,
            'ma_id' => $sk_ma['id'],//码id
            'ma_account' => $sk_ma['ma_account'],
            'ma_realname' => $sk_ma['ma_realname'],
            'ma_qrcode' => $sk_ma['ma_qrcode'],
            'ma_bank_id' => $sk_ma['bank_id'],
            'ma_branch_name' => $sk_ma['branch_name'],
            'order_ip' => $p_data['client_ip'],
            'notify_url' => $p_data['notify_url'],
            'create_time' => NOW_TIME,
            'create_day' => date('Ymd', NOW_TIME),
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
        $res4 = $mysql->update($sk_ma_data, "id={$sk_ma['id']}", 'sk_ma');
        if (!$res1 || !$res2 || !$res3 || !$res4) {
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

        $payurl = ADMIN_URL . "/pay/info?osn={$sk_order['order_sn']}";
        if ($p_data['format'] != 'json') {
            echo "<script>window.open({$payurl}, '');</script>";
            exit();
        }

        $return_data = [
            'account' => $p_data['account'],
            'order_sn' => $p_data['order_sn'],
            'system_sn' => $sk_order['order_sn'],
            'pay_url' => $payurl,
            'qrcode' => ''
        ];
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

        $sk_ma = [];
        //根据ip匹配一个合适的
        if (!$sk_ma) {
            $sql .= " order by u.queue_time asc,log.queue_time asc";
            $sk_ma = $mysql->fetchRow($sql);
            file_put_contents(ROOT_PATH . 'logs/ma_sql.txt', $sql . "\n\n", FILE_APPEND);
        }

        // 10分钟之内有3单相同金额
        $limit_time = NOW_TIME - 10 * 60;
        //检测该码商是否有相同金额订单
        if ($sk_ma) {
            $check_order = $mysql->fetchRow("select id from sk_order where ma_id={$sk_ma['id']} and pay_status<=2 and money={$p_data['money']} and create_time>{$limit_time}");
            if ($check_order['id']) {
                $this->checkMaArr[] = $sk_ma['id'];
                $this->getMaNum++;
                if ($this->getMaNum <= 3) {
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
        //TODO
    }

    public function notify()
    {
        //TODO
    }
}