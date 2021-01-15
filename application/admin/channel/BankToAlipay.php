<?php
namespace app\admin\channel;

use \app\admin\controller\Base;
use think\Request;

class BankToAlipay extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    /**
     *******************************
     */
    public function index()
    {
        jReturn('-1', 'BankToAlipay/index');
    }

    public function query()
    {
        //TODO
    }

    public function notify()
    {
        //TODO
    }
}