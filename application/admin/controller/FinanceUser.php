<?php
namespace app\admin\controller;

use think\Request;

class FinanceUser extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function user()
    {
        //TODO
        jReturn('-1', 'user');
    }

    public function userList()
    {
        //TODO
        jReturn('-1', 'userList');
    }

    public function userRecharge()
    {
        //TODO
        jReturn('-1', 'userRecharge');
    }
}