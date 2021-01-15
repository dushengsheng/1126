<?php
namespace app\admin\controller;

use think\Request;
use app\admin\channel\BankToAlipay;

class Pay extends Base
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

    public function skma()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skma();
    }

    public function skmaList()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skmaList();
    }

    public function skmaDelete()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skmaDelete();
    }

    public function skmaUpdate()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skmaUpdate();
    }

    public function skmaOnline()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skmaOnline();
    }

    public function skmaTest()
    {
        $pay_skma = new PaySkma();
        return $pay_skma->skmaTest();
    }


    /**
     ********************************
     */
    public function order()
    {
        $pay_order = new PayOrder();
        return $pay_order->order();
    }

    public function orderList()
    {
        $pay_order = new PayOrder();
        return $pay_order->orderList();
    }


    /**
     *******************************
     */
    protected function getChannel($title)
    {
        $channel = null;
        if ($title == 'bank_to_alipay') {
            $channel = new BankToAlipay();
        }
        // TODO
        // more channels
        return $channel;
    }

    public function index()
    {
        $channel = $this->getChannel('bank_to_alipay');
        return $channel->index();
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