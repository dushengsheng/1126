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
        return $this->user_agent->agentList();
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
        return $this->user_merchant->merchantList();
    }

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
        $pageuser = checkLogin();
        $params = $this->params;
        $group = intval($params['group']);
        $down_agents = getDownAgent($pageuser['id']);
        $sys_group = getConfig('sys_group');
        $sys_group_arr = [];
        $group_to_query = [];
        if ($group == 61) {
            $group_to_query = [61, 71];
        } else if ($group == 81) {
            $group_to_query = [81, 91];
        } else {
            jReturn('-1', '请选择合适的分组');
        }
        foreach ($sys_group as $key => $value) {
            if ($pageuser['gid'] > $key) {
                continue;
            }
            if (!in_array($key, $group_to_query)) {
                continue;
            }
            if ($key >= $pageuser['gid']) {
                $sys_group_arr[$key] = $value;
            }
        }
        $data = [
            'sys_agent' => $down_agents,
            'sys_group' => $sys_group_arr,
        ];

        jReturn('0', '查询代理信息成功', $data);
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


}
