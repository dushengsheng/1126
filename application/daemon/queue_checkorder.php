<?php
include_once __DIR__ . '/daemon.ini.php';

use \app\common\Mysql;
use \app\common\MyMemcache;

// nohup php ./queue_checkorder.php>queue_checkorder.log 2>&1 &

//$daemon = new Daemon();
//$daemon->init();

while (true) {
    $mysql = new Mysql(0);
    $now_time = time();
    $list = $mysql->fetchRows("select * from sk_order where pay_status<3 and over_time<{$now_time}", 1, 5);
    if (!$list) {
        echo "没有数据暂停5秒\n";
        $mysql->close();
        unset($mysql);
        sleep(5);
        continue;
    }
    //超时订单，退还冻结金额
    foreach ($list as $item) {
        $mysql->startTrans();
        $user = $mysql->fetchRow("select id,balance,fz_balance from sys_user where id={$item['muid']} for update");
        $sys_user = [
            'balance' => $user['balance'] + $item['money'],
            'fz_balance' => $user['fz_balance'] - $item['money']
        ];
        $res1 = $mysql->update($sys_user, "id={$user['id']}", 'sys_user');
        $res2 = balanceLog($user, 0, 1, 15, $item['money'], $item['id'], $item['order_sn'], $mysql);
        $res3 = balanceLog($user, 0, 2, 15, -$item['money'], $item['id'], $item['order_sn'], $mysql);
        $sk_order = [
            'pay_status' => 3,
            'over_time' => time()
        ];
        $res4 = $mysql->update($sk_order, "id={$item['id']}", 'sk_order');
        if (!$res1 || !$res2 || !$res3 || !$res4) {
            $mysql->rollback();
            continue;
        }
        $mysql->commit();
    }

    $mysql->close();
    unset($mysql);
    echo "处理完一批，暂停5秒\n";
    sleep(5);
}
?>