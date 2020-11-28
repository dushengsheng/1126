<?php

namespace app\admin\controller;

use think\Db;
use think\Request;

class Index extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    //后台框架首页
    public function index()
    {
        $pageuser = checkPower();
        if ($pageuser['gid'] >= 91) {
            exit('抱歉，没有权限访问');
        }
        $mysql_version = DB::query('select version()');
        dump($mysql_version);

        $menu = getUserMenu($pageuser['id'], $this->mysql);
        $data = array(
            'user' => $pageuser,
            'menu_json' => json_encode(array_values($menu)),
            'sys_group' => getConfig('sys_group'),
            'mysql_version' => $mysql_version
        );
        display('Default/index.html', $data);
    }

    //默认内容界面
    /*public function default()
    {
        $pageuser = checkLogin();
        $data = [
            'user' => $pageuser
        ];
        display('Default/default.html', $data);
    }*/

    public function tj()
    {
        checkPower();
        $data = [
            'tj' => getConfig('cnf_default_tj')
        ];
        display('Default/tj.html', $data);
    }
}
