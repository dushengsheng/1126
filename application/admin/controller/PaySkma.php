<?php
namespace app\admin\controller;

use think\Request;

class PaySkma extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function _initialize()
    {
        parent::_initialize();
    }

    public function skma()
    {
        $pageuser = checkPower();
        $mtype_arr = $this->mysql->fetchRows("select * from sk_mtype where id = 204");
        $mstatus_arr = getConfig('cnf_skma_status');

        $data = [
            'sys_user' => $pageuser,
            'sys_ma_type' => $mtype_arr,
            'sys_ma_status' => $mstatus_arr
        ];

        // 检查权限
        $data['add'] = hasPower($pageuser, 'Pay_SkmaAdd') ? 1 : 0;
        $data['del'] = hasPower($pageuser, 'Pay_SkmaDelete') ? 1 : 0;
        $data['edit'] = hasPower($pageuser, 'Pay_SkmaEdit') ? 1 : 0;
        $data['test'] = hasPower($pageuser, 'Pay_SkmaTest') ? 1 : 0;

        return $this->fetch("Pay/skma", $data);
    }

    public function skmaList()
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
            if (in_array($params['s_ma_type'], [204,205])) {
                $where .= " and log.mtype_id in (204,205)";
            } else {
                $where .= " and log.mtype_id={$params['s_ma_type']}";
            }
        }
        if (isset($params['s_ma_status']) && $params['s_ma_status'] != 'all') {
            $params['s_ma_status'] = intval($params['s_ma_status']);
            $where .= " and log.status={$params['s_ma_status']}";
        }
        if (isset($params['s_keyword']) && $params['s_keyword']) {
            $where .= " and (log.ma_account like '%{$params['s_keyword']}%' or u.account like '%{$params['s_keyword']}%' or u.nickname like '%{$params['s_keyword']}%')";
        }

        $sql_cnt = "select count(1) as cnt 
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
        $today = date('Ymd', NOW_TIME);
        $yestoday = date("Ymd", strtotime("-1 day"));
        //$weekday = date("Ymd", strtotime("-7 day"));

        foreach ($list as &$item) {
            $item['create_time'] = date('m-d H:i:s', $item['create_time']);
            $item['status_flag'] = $cnf_skma_status[$item['status']];
            /*
            $oitem=$this->mysql->fetchRow("select count(1)as cnt from sk_order where ma_id={$item['id']}");
            $oitem2=$this->mysql->fetchRow("select count(1)as cnt from sk_order where ma_id={$item['id']} and pay_status=9");
            $item['order_num']=intval($oitem['cnt']);
            $item['order_num2']=intval($oitem2['cnt']);
            */
            $jt_item   = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']} and create_day={$today} and pay_status=9");
            $jt_item2  = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']} and create_day={$today}");
            $zt_item   = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']} and create_day={$yestoday} and pay_status=9");
            $zt_item2  = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']} and create_day={$yestoday}");
            $all_item  = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']} and pay_status=9");
            $all_item2 = $this->mysql->fetchRow("select count(1)as cnt,sum(money) as money from sk_order where ma_id={$item['id']}");

            $item['jt_cnt'] = intval($jt_item['cnt']);
            $item['jt_cnt2'] = intval($jt_item2['cnt']);
            $item['jt_money'] = floatval($jt_item['money']);
            $item['jt_money2'] = floatval($jt_item2['money']);
            if ($item['jt_cnt2'] > 0) {
                $item['jt_percent'] = round(($item['jt_cnt'] / $item['jt_cnt2']) * 100, 2) . '%';
            } else {
                $item['jt_percent'] = '0%';
            }

            $item['zt_cnt'] = intval($zt_item['cnt']);
            $item['zt_cnt2'] = intval($zt_item2['cnt']);
            $item['zt_money'] = floatval($zt_item['money']);
            $item['zt_money2'] = floatval($zt_item2['money']);
            if ($item['zt_cnt2'] > 0) {
                $item['zt_percent'] = round(($item['zt_cnt'] / $item['zt_cnt2']) * 100, 2) . '%';
            } else {
                $item['zt_percent'] = '0%';
            }

            $item['all_cnt'] = intval($all_item['cnt']);
            $item['all_cnt2'] = intval($all_item2['cnt']);
            $item['all_money'] = floatval($all_item['money']);
            $item['all_money2'] = floatval($all_item2['money']);
            if ($item['all_cnt2'] > 0) {
                $item['all_percent'] = round(($item['all_cnt'] / $item['all_cnt2']) * 100, 2) . '%';
            } else {
                $item['all_percent'] = '0%';
            }
        }
        $data = array(
            'list' => $list,
            'count' => $count_item['cnt']
        );

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