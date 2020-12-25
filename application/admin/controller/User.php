<?php

namespace app\admin\controller;

use think\Exception;
use think\Request;


class User extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function agent()
    {
        $pageuser = checkPower();
        $up_agents = getDownAgent($pageuser['id']);
        $sys_group = getConfig('sys_group');
        $sys_group_arr = [];
        foreach ($sys_group as $key => $value) {
            if (!in_array($key, [81, 91])) {
                continue;
            }
            if ($key >= $pageuser['gid']) {
                $sys_group_arr[$key] = $value;
            }
        }
        $data = [
            'user' => $pageuser,
            'sys_group' => $sys_group_arr,
            'sys_agent' => $up_agents
        ];

        return $this->fetch("User/agent", $data);
    }

    public function agentList()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $where = "where status<99";
        if ($pageuser['gid'] > 41) {
            $uid_arr = getDownUser($pageuser['id']);
            if ($uid_arr) {
                $uid_str = implode(',', $uid_arr);
                $where .= " and id in ({$uid_str})";
            } else {
                $where = 'where 0';
            }
        } else {
            $where .= " and gid in (81,91)";
        }
        if (isset($params['s_gid']) && $params['s_gid']) {
            $where .= " and gid={$params['s_gid']}";
        }
        if (isset($params['s_is_online']) && $params['s_is_online'] != 'all') {
            $params['s_is_online'] = intval($params['s_is_online']);
            $where .= " and is_online={$params['s_is_online']}";
        }
        if (isset($params['s_keyword']) && $params['s_keyword']) {
            $where .= " and (id='{$params['s_keyword']}' or phone='{$params['s_keyword']}' or account like '%{$params['s_keyword']}%' or realname like '%{$params['s_keyword']}%' or nickname like '%{$params['s_keyword']}%')";
        }

        $sql_cnt = "select count(1) as cnt,sum(balance) as balance,sum(sx_balance) as sx_balance,
		sum(fz_balance) as fz_balance,sum(kb_balance) as kb_balance from sys_user {$where}";
        $count_item = $this->mysql->fetchRow($sql_cnt);
        $sql = "select * from sys_user {$where} order by id";
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
            $item['is_online_flag'] = $yes_or_no[$item['is_online']];
            $item['reg_time'] = date('Y-m-d H:i:s', $item['reg_time']);
            if ($item['login_time']) {
                $item['login_time'] = date('Y-m-d H:i:s', $item['login_time']);
            }
            if ($item['pid']) {
                $p_user = $this->mysql->fetchRow("select account,nickname,realname from sys_user where id={$item['pid']}");
                $item['paccount'] = $p_user['account'];
                $item['prealname'] = $p_user['realname'] ? $p_user['realname'] : $p_user['nickname'];
            }

            //统计码商今日/累计收款
            $all_sql = "select count(1) as cnt,sum(log.money) as money from sk_order log where 1";
            if (in_array($item['gid'], [81, 91])) {
                $all_sql .= " and log.muid={$item['id']}";

                //统计一下码商或码商代理的佣金
                $yong_sql = "select sum(money) as money from sk_yong where uid={$item['id']} and type=1 and level>0";
                $yong_item = $this->mysql->fetchRow($yong_sql);
                $item['yong_money'] = floatval($yong_item['money']);

            } elseif (in_array($item['gid'], [61, 71])) {
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
                $power_arr['kick'] = hasPower($pageuser, 'User_Offline') ? 1 : 0;
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
            'limit' => $this->pageSize,
            'balance' => (float)$count_item['balance'],
            'sx_balance' => (float)$count_item['sx_balance'],
            'fz_balance' => (float)$count_item['fz_balance'],
            'kb_balance' => (float)$count_item['kb_balance']
        );
        jReturn('0', 'ok', $data);
    }

    /*
     * 增加或更新用户
     */
    public function updateUser()
    {
        $pageuser = checkPower();
        $params = $this->params;

        debugLog("updateuser: params = " . var_export($params, true));
        jReturn('0', '操作成功');

        $item_id = intval($params['item_id']);
        if (!$params['realname']) {
            jReturn('-1', '请填写姓名');
        }
        if (!$params['nickname']) {
            jReturn('-1', '请填写昵称');
        }
        $data = array(
            'realname' => $params['realname'],
            'nickname' => $params['nickname']
        );

        if ($params['phone']) {
            if (!isPhone($params['phone'])) {
                jReturn('-1', '请填写正确的手机号');
            }
            $check_phone = $this->mysql->fetchRow("select * from sys_user where phone='{$params['phone']}'");
            if ($check_phone) {
                if (!$item_id || ($item_id && $item_id != $check_phone['id'])) {
                    jReturn('-1', '手机号已存在请更换');
                }
            }
        }
        if ($pageuser['gid'] == 1) {
            $is_google = intval($params['is_google']);
            if ($is_google > 1) {
                $data['is_online'] = 0;
                $data['google_hide'] = 0;
                $data['google_secret'] = '';
            } else {
                $data['is_google'] = $is_google;
            }
        }

        $data['is_online'] = intval($params['is_online']);
        $data['status'] = intval($params['status']);
        if (!$params['forbid_time_flag']) {
            $params['forbid_time_flag'] = 'max';
        }
        if ($params['forbid_time_flag'] == 'max') {
            $data['forbid_time'] = NOW_TIME * 2;
        } else {
            $data['forbid_time'] = NOW_TIME + $params['forbid_time_flag'] * 60;
        }
        $data['forbid_time_flag'] = $params['forbid_time_flag'];
        $data['forbid_msg'] = $params['forbid_msg'];

        $data['phone'] = $params['phone'];
        $data['gid'] = intval($params['gid']);
        if ($pageuser['gid'] != 1) {
            if ($data['gid'] < $pageuser['gid']) {
                jReturn('-1', '您的级别不足以设置该所属分组');
            }
        }

        if ($params['password']) {
            $data['password'] = getPassword($params['password']);
        }
        if ($params['password2']) {
            $data['password2'] = getPassword($params['password2']);
        }

        //邀请人判断
        if ($pageuser['gid'] == 1) {
            if ($params['paccount']) {
                $p_user = $this->mysql->fetchRow("select id,account,realname from sys_user where account='{$params['paccount']}' or phone='{$params['paccount']}'");
                if ($p_user['id']) {
                    //被编辑者的下级
                    if ($item_id) {
                        $down_ids = getDownUser($item_id);
                        if (in_array($p_user['id'], $down_ids)) {
                            jReturn('-1', '邀请人不能是该用户的下级');
                        }
                    }
                    $data['pid'] = $p_user['id'];
                } else {
                    jReturn('-1', '不存在该邀请人账号：' . $params['paccount']);
                }
            }
        } else {
            if (!$item_id) {
                $data['pid'] = $pageuser['id'];
            }
        }

        if (!$item_id) {
            if (!$params['account']) {
                jReturn('-1', '请填写账号');
            }
            if (utf8_strlen($params['account']) < 3 || utf8_strlen($params['account']) > 15) {
                jReturn('-1', '请输入3-15个字符的账号');
            }
            //检查帐号是否已经存在
            $account = $this->mysql->fetchRow("select id from sys_user where account='{$params['account']}'");
            if ($account['id']) {
                jReturn('-1', "账号{$params['account']}已经存在");
            }
            $data['icode'] = genIcode($this->mysql);
            $data['account'] = $params['account'];
            $data['openid'] = $params['account'];
            $data['reg_time'] = NOW_TIME;
            $data['reg_ip'] = CLIENT_IP;
            $data['headimgurl'] = 'public/images/head.png';
        } else {
            if ($pageuser['gid'] > 41) {
                $uid_arr = getDownUser($pageuser['id']);
                if (!in_array($item_id, $uid_arr)) {
                    jReturn('-1', '不是自己的用户无法编辑');
                }
            }
            if ($item_id == 1) {
                $data['gid'] = 1;
                $data['status'] = 2;
            } else {
                //用户被禁用同时踢下线
                if ($data['status'] == 1) {
                    kickUser($item_id, $this->mysql);
                }
            }
        }

        if ($item_id) {
            $res = $this->mysql->update($data, "id={$item_id}", 'sys_user');
            $user = $this->mysql->fetchRow("select * from sys_user where id={$item_id}");
            $data['account'] = $user['account'];
        } else {
            $res = $this->mysql->insert($data, 'sys_user');
            $data['id'] = $res;
        }
        if ($res === false) {
            jReturn('-1', '系统繁忙请稍后再试');
        }
        actionLog(['opt_name' => '更新用户', 'sql_str' => json_encode($data, 256)], $this->mysql);
        $return_data = [];
        if ($p_user) {
            $return_data['paccount'] = $p_user['account'];
            $return_data['prealname'] = $p_user['realname'];
        }
        jReturn('1', '操作成功', $return_data);
    }
	
	public function merchant()
	{
		echo 'this is test merchant';
	}
}
