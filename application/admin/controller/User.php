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
        return $this->user_agent->channelRate();
    }


    /*
     * 分割线，上部分是码商功能，下部分是商户功能
     * -----------------------------------------------------*/


    public function merchant()
    {
        echo 'this is test merchant';
    }


    /*
     * 分割线，上部分是商户功能，下部分是ajax查询接口
     * -----------------------------------------------------*/
    public function agentQuery()
    {
        return $this->user_agent->agentQuery();
    }

    public function channelQuery()
    {
        return $this->user_agent->channelQuery();
    }


}
