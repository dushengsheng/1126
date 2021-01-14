<?php
namespace app\admin\controller;

use think\Request;

class Finance extends Base
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

    /**
     * user
     */
    public function user()
    {
        $finance_user = new FinanceUser();
        return $finance_user->user();
    }

    public function userList()
    {
        $finance_user = new FinanceUser();
        return $finance_user->userList();
    }

    public function userRecharge()
    {
        $finance_user = new FinanceUser();
        return $finance_user->userRecharge();
    }

    /**
     * card
     */
    public function card()
    {
        $finance_card = new FinanceCard();
        return $finance_card->card();
    }

    public function cardList()
    {
        $finance_card = new FinanceCard();
        return $finance_card->cardList();
    }

    public function cardAdd()
    {
        $finance_card = new FinanceCard();
        return $finance_card->cardAdd();
    }

    public function cardUpdate()
    {
        $finance_card = new FinanceCard();
        return $finance_card->cardUpdate();
    }

    public function cardDelete()
    {
        $finance_card = new FinanceCard();
        return $finance_card->cardDelete();
    }

    /**
     * account
     */
    public function account()
    {
        $finance_account = new FinanceAccount();
        return $finance_account->account();
    }

    public function overview()
    {
        $finance_account = new FinanceAccount();
        return $finance_account->overview();
    }

    public function detail()
    {
        $finance_account = new FinanceAccount();
        return $finance_account->detail();
    }

    public function withdrawal()
    {
        $finance_account = new FinanceAccount();
        return $finance_account->withdrawal();
    }


    /**
     * cashlog
     */
    public function cashlog()
    {
        $finance_cashlog = new FinanceCashlog();
        return $finance_cashlog->cashlog();
    }

    public function cashlogList()
    {
        $finance_cashlog = new FinanceCashlog();
        return $finance_cashlog->cashlogList();
    }

    public function cashlogRollback()
    {
        $finance_cashlog = new FinanceCashlog();
        return $finance_cashlog->cashlogRollback();
    }

    public function cashlogPass()
    {
        $finance_cashlog = new FinanceCashlog();
        return $finance_cashlog->cashlogPass();
    }

    public function cashlogDeny()
    {
        $finance_cashlog = new FinanceCashlog();
        return $finance_cashlog->cashlogDeny();
    }
}