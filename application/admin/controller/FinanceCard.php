<?php
namespace app\admin\controller;

use think\Request;

class FinanceCard extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    public function card()
    {
        $pageuser = checkPower();
        $bank_arr = $this->mysql->fetchRows("select * from cnf_bank where status = 1");
        $data = [
            'sys_user' => $pageuser,
            'sys_bank' => $bank_arr
        ];

        return $this->fetch("Finance/card", $data);
    }

    public function cardList()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $where = "where 1";
        if ($pageuser['gid'] >= 61) {
            $where .= " and log.uid={$pageuser['id']}";
        }
        if (isset($params['s_bank_id'])) {
            $s_bank_id = intval($params['s_bank_id']);
            if ($s_bank_id) {
                $where .= " and log.bank_id={$s_bank_id}";
            }
        }
        if (isset($params['s_keyword']) && $params['s_keyword']) {
            $s_keyword = $params['s_keyword'];
            $where .= " and (log.bank_account like '%{$s_keyword}%' or log.bank_realname like '%{$s_keyword}%')";
        }

        $count = $this->mysql->fetchResult("select count(1) from cnf_card log {$where}");
        $sql = "select log.*,b.bank_name,b.bank_code,u.nickname as user_nickname,u.account as user_account from cnf_card log 
		left join cnf_bank b on log.bank_id=b.id 
		left join sys_user u on log.uid=u.id {$where} order by log.id desc";
        $list = $this->mysql->fetchRows($sql, $params['page'], $params['limit']);
        foreach ($list as &$item) {
            $item['create_time'] = date('m-d H:i', $item['create_time']);
        }
        $data = array(
            'list' => $list,
            'count' => $count ? $count : 0,
        );
        jReturn('0', 'ok', $data);
    }

    public function cardAdd()
    {
        jReturn('-1', 'cardAdd');
    }

    public function cardUpdate()
    {
        $pageuser = checkPower();
        $params = $this->params;
        $data = [
            'bank_account' => $params['bank_account'],
            'bank_realname' => $params['bank_realname'],
            'bank_id' => intval($params['bank_id']),
        ];
        $id = 0;
        $bank_id = $data['bank_id'];
        if (isset($params['id']) && intval($params['id'])) {
            $id = intval($params['id']);
        }
        $bank = $this->mysql->fetchRow("select * from cnf_bank where id={$bank_id}");
        if (!$bank || !$bank['status']) {
            jReturn('-1', '不存在该银行或者未启用');
        }
        $res = true;
        if ($id) {
            if ($pageuser['gid'] >= 61) {
                $card = $this->mysql->fetchRow("select * from cnf_card where id={$id}");
                if ($card['uid'] != $pageuser['id']) {
                    jReturn('-1', '您没有权限操作该记录');
                }
            }
            $res = $this->mysql->update($data, "id={$id}", 'cnf_card');
        } else {
            $data['uid'] = $pageuser['id'];
            $data['create_time'] = NOW_TIME;
            $res = $this->mysql->insert($data, 'cnf_card');
        }
        if (!$res) {
            jReturn('-1', '系统繁忙请稍后再试');
        }
        jReturn('0', '操作成功');
    }

    public function cardDelete()
    {
        $pageuser = checkLogin();
        $params = $this->params;
        $id = intval($params['id']);
        $card = $this->mysql->fetchRow("select * from cnf_card where id={$id}");
        if (!$card) {
            jReturn('-1', '不存在该记录');
        }
        if ($pageuser['gid'] >= 61) {
            if ($card['uid'] != $pageuser['id']) {
                jReturn('-1', '您没有权限操作该记录');
            }
        }
        $res = $this->mysql->delete("id={$id}", 'cnf_card');
        if (!$res) {
            jReturn('-1', '系统繁忙请稍后再试');
        }
        jReturn('0', '操作成功');
    }

}