<?php
namespace app\admin\controller;

use think\Request;

class Api extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function index()
    {
        $pageuser = checkLogin();
        $data = [
            //'sys_user' => $pageuser,
            'server' => ADMIN_URL
        ];
        return $this->fetch("Api/index", $data);
    }
}