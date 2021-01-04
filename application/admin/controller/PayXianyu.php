<?php
namespace app\admin\controller;

use think\Request;

class PayXianyu extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function _initialize()
    {
        parent::_initialize();
    }

    public function xianyu()
    {
        $pageuser = checkPower();
        $mtype_arr = $this->mysql->fetchRows("select * from sk_mtype where id = 601");
        $mstatus_arr = getConfig('cnf_skma_status');
        $data = [
            'sys_user' => $pageuser,
            'sys_ma_type' => $mtype_arr,
            'sys_ma_status' => $mstatus_arr
        ];

        //return '<script language="javascript;">window.open("https://pay.duowan.com/userDepositDWAction.action", "newwindow")</script>';
        //return '<a href ="https://pay.duowan.com/userDepositDWAction.action" target="_blank"></a>';
        //header('Location:https://pay.duowan.com/userDepositDWAction.action');
        //return $this->testXianyu();
        //TODO
        return $this->fetch("Pay/xianyu", $data);
    }

    public function xianyuList()
    {
        $pageuser = checkLogin();
        $params = $this->params;
        $where = "where log.status<99";
        if ($pageuser['gid'] >= 61) {
            $uid_arr = getDownUser($pageuser['id']);
            $uid_arr[] = $pageuser['id'];
            $uid_str = implode(',', $uid_arr);
            $where .= " and log.uid in({$uid_str})";
        }
        if (isset($params['s_ma_type']) && $params['s_ma_type']) {
            $params['s_ma_type'] = intval($params['s_ma_type']);
            $where .= " and log.mtype_id={$params['s_ma_type']}";
        }
        if (isset($params['s_ma_status']) && $params['s_ma_status'] != 'all') {
            $params['s_ma_status'] = intval($params['s_ma_status']);
            $where .= " and log.status={$params['s_ma_status']}";
        }
        if (isset($params['s_keyword']) && $params['s_keyword']) {
            $where .= " and (u.account like '%{$params['s_keyword']}%' or u.nickname like '%{$params['s_keyword']}%')";
        }

        $sql_cnt = "select count(1) as cnt,sum(log.max_money) as money
		from sk_ma log 
		left join sk_mtype mt on log.mtype_id=mt.id 
		left join sys_user u on log.uid=u.id {$where}";
        $count_item = $this->mysql->fetchRow($sql_cnt);

        $sql = "select log.*,
		mt.name as mtype_name,mt.type as mtype_type,
		u.account,u.nickname 
		from sk_ma log 
		left join sk_mtype mt on log.mtype_id=mt.id 
		left join sys_user u on log.uid=u.id 
		{$where} order by log.id desc";
        $list = $this->mysql->fetchRows($sql, $params['page'], $params['limit']);

        $cnf_skma_status = getConfig('cnf_skma_status');
        //$nowday = date('Ymd', NOW_TIME);
        //$yestoday = date("Ymd", strtotime("-1 day"));
        //$weekday = date("Ymd", strtotime("-7 day"));
        foreach ($list as &$item) {
            $item['create_time'] = date('m-d H:i:s', $item['create_time']);
            $item['status_flag'] = $cnf_skma_status[$item['status']];
            /*
            $oitem=$this->mysql->fetchRow("select count(1)as cnt from sk_order where ma_id={$item['id']}");
            $oitem2=$this->mysql->fetchRow("select count(1)as cnt from sk_order where ma_id={$item['id']} and pay_status=9");
            $item['order_num']=intval($oitem['cnt']);
            $item['order_num2']=intval($oitem2['cnt']);
            $jt_item = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']} and create_day={$nowday} and pay_status=9");
            $jt_item2 = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']} and create_day={$nowday}");
            $zt_item = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']} and create_day={$yestoday} and pay_status=9");
            $zt_item2 = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']} and create_day={$yestoday}");
            $wt_item = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']} and create_day>={$weekday} and pay_status=9");
            $wt_item2 = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']} and create_day>={$weekday}");
            $item['jt_cnt'] = intval($jt_item['cnt']);
            $item['jt_cnt2'] = intval($jt_item2['cnt']);
            if ($item['jt_cnt2'] > 0) {
                $item['jt_percent'] = round(($item['jt_cnt'] / $item['jt_cnt2']) * 100, 2) . '%';
            } else {
                $item['jt_percent'] = '0%';
            }

            $item['jt_money'] = floatval($jt_item['money']);
            $item['zt_cnt'] = intval($zt_item['cnt']);
            $item['zt_cnt2'] = intval($zt_item2['cnt']);
            if ($item['zt_cnt2'] > 0) {
                $item['zt_percent'] = round(($item['zt_cnt'] / $item['zt_cnt2']) * 100, 2) . '%';
            } else {
                $item['zt_percent'] = '0%';
            }
            $item['zt_money'] = floatval($zt_item['money']);
            $item['wt_cnt'] = intval($wt_item['cnt']);
            $item['wt_cnt2'] = intval($wt_item2['cnt']);
            if ($item['wt_cnt2'] > 0) {
                $item['wt_percent'] = round(($item['wt_cnt'] / $item['wt_cnt2']) * 100, 2) . '%';
            } else {
                $item['wt_percent'] = '0%';
            }
            $item['wt_money'] = floatval($wt_item['money']);
            */

            $item['edit'] = hasPower($pageuser, 'Pay_skma_update') ? 1 : 0;
            $item['delete'] = hasPower($pageuser, 'Pay_skma_delete') ? 1 : 0;
        }
        $data = array(
            'list' => $list,
            'count' => $count_item['cnt'],
            'money' => (float)$count_item['money']
        );

        $this->testXianyu();
        jReturn('0', 'ok', $data);
    }

    /**
     * @param string $host - $host of socket server
     * @param string $message - 发送的消息
     * @param string $address - 地址
     * @return bool
     */
    protected function testXianyu()
    {
        $url = 'https://pay.duowan.com/userDepositDWAction.action';
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, false);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $referer = urlencode($url);
        $headers = [
            "Content-Type: application/json;charset=UTF-8",
            'user-agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.80 Safari/537.36',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); //设置header
        $result = curl_exec($ch);
        debugLog('testXianyu: result =' . $result);
        return $result;
    }
}