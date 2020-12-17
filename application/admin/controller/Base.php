<?php

namespace app\admin\controller;

require_once COMMON_PATH. 'MyMemcache.class.php';
require_once COMMON_PATH. 'Mysql.class.php';


use think\Controller;
use think\Exception;
use think\Request;
use app\common\MyMemcache;
use app\common\Mysql;


class Base extends Controller
{
    protected $mysql;
    protected $params;
    protected $memcache;
    protected $pageSize = 20;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function _initialize()
    {
        $this->mysql = new Mysql(0);
        $this->memcache = new MyMemcache(0);
        $this->params = $this->param();
    }

    protected function param($key = '')
    {
        return getParam($key);
    }
}