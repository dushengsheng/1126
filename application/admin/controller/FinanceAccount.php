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
        jReturn('-1', 'overview');
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
            'count' => count($list)
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
        jReturn('-1', 'withdrawal');
    }
}