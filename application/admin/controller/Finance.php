<?php
namespace app\admin\controller;

use think\Request;

class Finance extends Base
{
    protected $finance_user = null;
    protected $finance_card = null;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function _initialize()
    {
        $this->finance_user = new FinanceUser();
        $this->finance_card = new FinanceCard();

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

    public function card()
    {
        return $this->finance_card->card();
    }

    public function cardList()
    {
        return $this->finance_card->cardList();
    }

    public function cardAdd()
    {
        return $this->finance_card->cardAdd();
    }

    public function cardUpdate()
    {
        return $this->finance_card->cardUpdate();
    }

    public function cardDelete()
    {
        return $this->finance_card->cardDelete();
    }

}