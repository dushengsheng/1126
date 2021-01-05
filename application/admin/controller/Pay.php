<?php
namespace app\admin\controller;

use think\Request;

class Pay extends Base
{
    protected $pay_skma = null;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function _initialize()
    {
        $this->pay_skma = new PaySkma();

        parent::_initialize();
        debugLog('params = ' . var_export($this->params, true));
    }

    public function skma()
    {
        return $this->pay_skma->skma();
    }

    public function skmaList()
    {
        return $this->pay_skma->skmaList();
    }

    public function skmaDelete()
    {
        return $this->pay_skma->skmaDelete();
    }

    public function skmaUpdate()
    {
        return $this->pay_skma->skmaUpdate();
    }

    public function skmaOnline()
    {
        return $this->pay_skma->skmaOnline();
    }

    public function skmaTest()
    {
        return $this->pay_skma->skmaTest();
    }

}