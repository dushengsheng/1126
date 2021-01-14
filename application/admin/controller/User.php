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

    public function _initialize()
    {
        parent::_initialize();
        debugLog('params = ' . var_export($this->params, true));
    }

    /**
     * 渲染代理首页
     */
    public function agent()
    {
        $user_agent = new UserAgent();
        return $user_agent->agent();
    }

    /**
     * 获取代理列表
     */
    public function agentList()
    {
        $user_agent = new UserAgent();
        return $user_agent->agentList();
    }


    /* -----------------------------------------------------
     * 分割线，上部分是码商功能，下部分是商户功能
     * -----------------------------------------------------*/


    public function merchant()
    {
        $user_merchant = new UserMerchant();
        return $user_merchant->merchant();
    }

    public function merchantList()
    {
        $user_merchant = new UserMerchant();
        return $user_merchant->merchantList();
    }


    /* -----------------------------------------------------
     * 分割线，上部分是商户功能，下部分是通用功能
     * -----------------------------------------------------*/


    /*
     * 增加或更新用户
     */
    public function userUpdate()
    {
        $user_agent = new UserAgent();
        return $user_agent->userUpdate();
    }

    /**
     * 删除用户及其名下收款码
     */
    public function userDelete()
    {
        $user_agent = new UserAgent();
        return $user_agent->userDelete();
    }

    /**
     * 禁用用户
     */
    public function forbiddenStatus()
    {
        $user_agent = new UserAgent();
        return $user_agent->forbiddenStatus();
    }

    /**
     * 踢掉用户/允许用户接单
     */
    public function onlineStatus()
    {
        $user_agent = new UserAgent();
        return $user_agent->onlineStatus();
    }


    /**
     * 查询自己旗下的码商/商户代理(包括自己)
     */
    public function agentQuery()
    {
        $user_agent = new UserAgent();
        return $user_agent->queryGroupAndAgent();
    }

    public function merchantQuery()
    {
        $user_merchant = new UserMerchant();
        return $user_merchant->queryGroupAndAgent();
    }

    /**
     * 查询用户通道开关状态及费率
     */
    public function channelQuery()
    {
        $params = $this->params;
        $user = getUserinfo($params['id'], true, $this->mysql);
        if (!$user) {
            jReturn('-1', '用户不存在');
        }
        $gid = $user['gid'];
        if (in_array($gid, [61, 71])) {
            $user_agent = new UserAgent();
            return $user_agent->channelQuery();
        } else if (in_array($gid, [81, 91])) {
            $user_merchant = new UserMerchant();
            return $user_merchant->channelQuery();
        }

        jReturn('-1', '请不要花样作死');
    }

    /**
     * 用户通道开关，以及费率调整
     */
    public function channelRate()
    {
        $params = $this->params;
        $user = getUserinfo($params['id'], true, $this->mysql);
        if (!$user) {
            jReturn('-1', '用户不存在');
        }
        $gid = $user['gid'];
        if (in_array($gid, [61, 71])) {
            $user_agent = new UserAgent();
            return $user_agent->channelRateUpdate();
        } else if (in_array($gid, [81, 91])) {
            $user_merchant = new UserMerchant();
            return $user_merchant->channelRateUpdate();
        }

        jReturn('-1', '请不要花样作死');
    }

}
