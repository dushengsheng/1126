<?php
namespace app\admin\controller;

use think\Request;

class Finance extends Base
{
    protected $finance_user = null;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function _initialize()
    {
        $this->finance_user = new FinanceUser();

        parent::_initialize();
        debugLog('params = ' . var_export($this->params, true));
    }

    public function user()
    {
        return $this->finance_user->user();
    }

    public function userList()
    {
        return $this->finance_user->userList();
    }

    public function userRecharge()
    {
        return $this->finance_user->userRecharge();
    }
}