<?php

namespace app\admin\controller;

use think\Exception;
use think\Request;


class User extends Base
{
    protected $user_agent = null;
    protected $user_merchant = null;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function _initialize()
    {
        $this->user_agent = new UserAgent();
        $this->user_merchant = new UserMerchant();

        parent::_initialize();
        debugLog('params = ' . var_export($this->params, true));
    }

    /**
     * 渲染代理首页
     */
    public function agent()
    {
        return $this->user_agent->agent();
    }

    /**
     * 获取代理列表
     */
    public function agentList()
    {
        return $this->getUserList('agent');
    }


    /* -----------------------------------------------------
     * 分割线，上部分是码商功能，下部分是商户功能
     * -----------------------------------------------------*/


    public function merchant()
    {
        return $this->user_merchant->merchant();
    }

    public function merchantList()
    {
        return $this->getUserList('merchant');
    }


    /* -----------------------------------------------------
     * 分割线，上部分是商户功能，下部分是通用功能
     * -----------------------------------------------------*/


    /*
     * 增加或更新用户
     */
    public function updateUser()
    {
        return $this->user_agent->updateUser();
    }

    /**
     * 删除用户及其名下收款码
     */
    public function deleteUser()
    {
        return $this->user_agent->deleteUser();
    }

    /**
     * 禁用用户
     */
    public function forbiddenStatus()
    {
        return $this->user_agent->forbiddenStatus();
    }

    /**
     * 踢掉用户/允许用户接单
     */
    public function onlineStatus()
    {
        return $this->user_agent->onlineStatus();
    }

    /**
     * 用户通道开关，以及费率调整
     */
    public function channelRate()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $uid = $params['id'];
        $channel_rate = $params['channel_rate'];
        $channel_switch = $params['channel_switch'];
        $user = getUserinfo($uid, true, $this->mysql);
        $puser = getUserinfo($user['pid'], true, $this->mysql);
        $gid = $user['gid'];

        if (!$user) {
            jReturn('-1', '用户不存在');
        }
        if (!$puser) {
            jReturn('-1', '请先为该用户指定上级代理');
        }
        if ($gid < 61) {
            jReturn('-1', '请不要花样作死');
        }
        if ($pageuser['gid'] > 41) {
            $uid_arr = getDownUser($pageuser['id']);
            if (!in_array($uid, $uid_arr)) {
                jReturn('-1', '操作失败! 该用户不是您的下级');
            }
        }

        $is_agent = true;
        $prate_arr = null;
        $keyword = 'fy_rate';
        if (in_array($gid, [81, 91])) {
            $keyword = 'td_rate';
            $is_agent = false;
        }
        if ($user['pid'] >= 61) {
            $prate_json = $puser[$keyword];
            $prate_arr = json_decode($prate_json, JSON_UNESCAPED_UNICODE);
        }

        foreach ($channel_rate as $key => $val) {
            $rate = floatval($val);
            if ($prate_arr) {
                $prate = floatval($prate_arr[$key]);
                if ($is_agent) {
                    if ($prate < $rate) {
                        jReturn('-1', '下级码商通道费率不能高于上级');
                    }
                } else {
                    if ($prate > $rate) {
                        jReturn('-1', '下级商户通道费率不能低于上级');
                    }
                }
            }
            $channel_rate[$key] = $rate;
        }
        foreach ($channel_switch as $key => $val) {
            $channel_switch[$key] = intval($val);
        }

        $channel_rate_json = json_encode($channel_rate, JSON_UNESCAPED_UNICODE);
        $channel_switch_json = json_encode($channel_switch, JSON_UNESCAPED_UNICODE);
        $data = [
            $keyword => $channel_rate_json,
            'td_switch' => $channel_switch_json,
        ];

        $res = $this->mysql->update($data, "id={$uid}", 'sys_user');
        if (!$res) {
            jReturn('-1', '系统繁忙, 请稍后再试');
        }
        jReturn('0', '通道费率更新成功');
    }


    /**
     * 查询自己旗下的码商/商户代理(包括自己)
     */
    public function agentQuery()
    {
        return $this->user_agent->agentQuery();
    }

    public function merchantQuery()
    {
        return $this->user_merchant->merchantQuery();
    }

    /**
     * 查询用户通道开关状态及费率
     */
    public function channelQuery()
    {
        $pageuser = checkLogin();
        $params = $this->params;
        $uid = $params['id'];

        if ($pageuser['gid'] > 41) {
            $down_user_arr = getDownUser($pageuser['id']);
            if ($down_user_arr) {
                if (!in_array($uid, $down_user_arr)) {
                    jReturn('-1', '操作失败! 该用户不是您的下级');
                }
            }
        }

        $user = getUserinfo($uid, true, $this->mysql);
        if (!$user) {
            jReturn('-1', '操作失败! 用户不存在');
        }
        $puser = null;
        if ($user['pid']) {
            $puser = getUserinfo($user['pid'], true, $this->mysql);
        }

        $ptd_switch = array();
        $ptd_rate = array();
        $pfy_rate = array();
        $paccount = null;
        if ($puser) {
            $ptd_switch = json_decode($puser['td_switch'], true);
            $ptd_rate = json_decode($puser['td_rate'], true);
            $pfy_rate = json_decode($puser['fy_rate'], true);
            $paccount = $puser['account'];
        }

        $channel_arr = rows2arr($this->mysql->fetchRows("select id,is_open,name from sk_mtype"));
        $data = [
            'id' => $user['id'],
            'gid' => $user['gid'],
            'pid' => $user['pid'],
            'pgid' => $puser ? $puser['gid'] : 1,
            'account' => $user['account'],
            'paccount' => $paccount,
            'sys_channel' => $channel_arr,
            'td_switch' => json_decode($user['td_switch'], true),
            'td_rate' => json_decode($user['td_rate'], true),
            'fy_rate' => json_decode($user['fy_rate'], true),
            'ptd_switch' => $ptd_switch,
            'ptd_rate' => $ptd_rate,
            'pfy_rate' => $pfy_rate,
        ];

        jReturn('0', '查询通道信息成功', $data);
    }

    protected function getUserList($group)
    {
        $pageuser = checkPower();
        $params = $this->params;

        $where = "where log.status<99";
        if ($group == 'agent') {
            $where .= " and log.gid in (61,71)";
        } else if ($group == 'merchant') {
            $where .= " and log.gid in (81,91)";
        }
        if ($pageuser['gid'] > 41) {
            $uid_arr = getDownUser($pageuser['id']);
            if ($uid_arr) {
                $uid_str = implode(',', $uid_arr);
                $where .= " and log.id in ({$uid_str})";
            } else {
                $empty_data = [
                    'list' => [],
                    'count' => 0,
                    'balance' => 0,
                    'sx_balance' => 0,
                    'fz_balance' => 0,
                    'kb_balance' => 0
                ];
                jReturn('0', '您还没有下级, 赶快来创建吧', $empty_data);
            }
        }

        if (isset($params['s_gid']) && $params['s_gid']) {
            $where .= " and log.gid={$params['s_gid']}";
        }
        if (isset($params['s_is_online']) && $params['s_is_online'] != 'all') {
            $params['s_is_online'] = intval($params['s_is_online']);
            $where .= " and log.is_online={$params['s_is_online']}";
        }
        if (isset($params['s_keyword']) && $params['s_keyword']) {
            $where .= " and (log.id='{$params['s_keyword']}' or log.account like '%{$params['s_keyword']}%' or log.nickname like '%{$params['s_keyword']}%')";
        }

        $sql_cnt = "select count(1) as cnt,sum(balance) as balance,sum(sx_balance) as sx_balance,
		sum(fz_balance) as fz_balance,sum(kb_balance) as kb_balance from sys_user log {$where}";
        $count_item = $this->mysql->fetchRow($sql_cnt);
        $sql = "select log.*,p.account as paccount,p.nickname as pnickname,p.status as pstatus,p.td_switch as ptd_switch,p.td_rate as ptd_rate,p.fy_rate as pfy_rate 
                from sys_user log left join sys_user p on log.pid=p.id {$where} order by log.id";
        $list = $this->mysql->fetchRows($sql, $params['page'], $params['limit']);
        $sys_group = getConfig('sys_group');
        $account_status = getConfig('account_status');
        $yes_or_no = getConfig('yes_or_no');
        $now_day = date('Ymd');
        $has_power_checked = false;
        $power_arr = [];

        foreach ($list as &$item) {
            unset($item['password'], $item['password2']);
            $item['gname'] = $sys_group[$item['gid']];
            $item['status_flag'] = $account_status[$item['status']];
            $item['pstatus_flag'] = $account_status[$item['pstatus']];
            $item['is_online_flag'] = $yes_or_no[$item['is_online']];
            $item['reg_time'] = date('Y-m-d H:i:s', $item['reg_time']);
            if ($item['login_time']) {
                $item['login_time'] = date('Y-m-d H:i:s', $item['login_time']);
            }

            //统计码商今日/累计收款
            $all_sql = "select count(1) as cnt,sum(log.money) as money from sk_order log where 1";
            if (in_array($item['gid'], [61, 71])) {
                $all_sql .= " and log.muid={$item['id']}";

                //统计一下码商或码商代理的佣金
                $yong_sql = "select sum(money) as money from sk_yong where uid={$item['id']} and type=1 and level>0";
                $yong_item = $this->mysql->fetchRow($yong_sql);
                $item['yong_money'] = floatval($yong_item['money']);

            } elseif (in_array($item['gid'], [81, 91])) {
                $all_sql .= " and log.suid={$item['id']}";

                //统计一下商户或商户代理的佣金
                $yong_sql = "select sum(money) as money from sk_yong where uid={$item['id']} and type=2 and level>0";
                $yong_item = $this->mysql->fetchRow($yong_sql);
                $item['yong_money'] = floatval($yong_item['money']);
            }

            if ($item['gid'] >= 61) {
                $all_item = $this->mysql->fetchRow($all_sql);
                $td_sql = $all_sql . " and log.create_day={$now_day}";
                $td_item = $this->mysql->fetchRow($td_sql);

                $all_sql_ok = $all_sql . " and log.pay_status=9";
                $all_item_ok = $this->mysql->fetchRow($all_sql_ok);
                $td_sql_ok = $all_sql_ok . " and log.create_day={$now_day}";
                $td_item_ok = $this->mysql->fetchRow($td_sql_ok);

                $item['all_money'] = floatval($all_item['money']);
                $item['all_cnt'] = intval($all_item['cnt']);
                $item['td_money'] = floatval($td_item['money']);
                $item['td_cnt'] = intval($td_item['cnt']);

                $item['all_money_ok'] = floatval($all_item_ok['money']);
                $item['all_cnt_ok'] = intval($all_item_ok['cnt']);
                $item['td_money_ok'] = floatval($td_item_ok['money']);
                $item['td_cnt_ok'] = intval($td_item_ok['cnt']);

                $all_percent = '0%';
                if ($item['all_cnt'] > 0) {
                    $all_percent = round(($item['all_cnt_ok'] / $item['all_cnt']) * 100, 2) . '%';
                }
                $td_percent = '0%';
                if ($item['td_cnt'] > 0) {
                    $td_percent = round(($item['td_cnt_ok'] / $item['td_cnt']) * 100, 2) . '%';
                }
                $item['all_percent'] = $all_percent;
                $item['td_percent'] = $td_percent;
            }

            // 只检查一次权限
            if (!$has_power_checked) {
                $has_power_checked = true;
                $power_arr['del'] = hasPower($pageuser, 'User_DeleteUser') ? 1 : 0;
                $power_arr['kick'] = hasPower($pageuser, 'User_OnlineStatus') ? 1 : 0;
                $power_arr['edit'] = hasPower($pageuser, 'User_UpdateUser') ? 1 : 0;
                $power_arr['channel'] = hasPower($pageuser, 'User_ChannelRate') ? 1 : 0;
                $power_arr['recharge'] = hasPower($pageuser, 'Finance_Recharge') ? 1 : 0;
            }
            $item['power_del'] = $power_arr['del'];
            $item['power_kick'] = $power_arr['kick'];
            $item['power_edit'] = $power_arr['edit'];
            $item['power_channel'] = $power_arr['channel'];
            $item['power_recharge'] = $power_arr['recharge'];
        }

        $data = array(
            'list' => $list,
            'count' => intval($count_item['cnt']),
            'balance' => (float)$count_item['balance'],
            'sx_balance' => (float)$count_item['sx_balance'],
            'fz_balance' => (float)$count_item['fz_balance'],
            'kb_balance' => (float)$count_item['kb_balance']
        );
        jReturn('0', 'ok', $data);
    }


}
