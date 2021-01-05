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
        $mtype_arr = $this->mysql->fetchRows("select * from sk_mtype where is_open=1 and id = 204");
        $mstatus_arr = getConfig('cnf_skma_status');

        // 检查权限
        $sys_power = [];
        $sys_power['add'] = hasPower($pageuser, 'Pay_SkmaAdd') ? 1 : 0;
        $sys_power['del'] = hasPower($pageuser, 'Pay_SkmaDelete') ? 1 : 0;
        $sys_power['edit'] = hasPower($pageuser, 'Pay_SkmaUpdate') ? 1 : 0;
        $sys_power['test'] = hasPower($pageuser, 'Pay_SkmaTest') ? 1 : 0;

        $data = [
            'sys_user' => $pageuser,
            'sys_power' => $sys_power,
            'sys_ma_type' => $mtype_arr,
            'sys_ma_status' => $mstatus_arr
        ];

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