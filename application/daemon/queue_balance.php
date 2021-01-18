<?php
include_once __DIR__ . '/daemon.ini.php';

use \app\common\Mysql;
use \app\common\MyMemcache;

// nohup php ./queue_balance.php>queue_balance.log 2>&1 &

//ignore_user_abort(); // 后台运行
//set_time_limit(0);   // 取消脚本运行时间的超时上限


//$daemon = new Daemon();
//$daemon->init();


while (true) {
    $mysql = new Mysql(0);
    $list = $mysql->fetchRows("select * from sk_order where pay_status=9 and js_status=1", 1, 5);
    if (!$list) {
        //echo "没有数据暂停5秒\n";
        $mysql->close();
        unset($mysql);
        sleep(5);
        continue;
    }
    //先结算商户订单，再结算分成
    foreach ($list as $item) {
        $mysql->startTrans();
        $item = $mysql->fetchRow("select * from sk_order where id={$item['id']} for update");
        $user = $mysql->fetchRow("select * from sys_user where id={$item['suid']} for update");
        $sk_order = [
            'js_status' => 2,
            'js_time' => time()
        ];
        $sys_user = [
            'balance' => $user['balance'] + $item['real_money']
        ];
        $res1 = $mysql->update($sk_order, "id={$item['id']}", 'sk_order');
        $res2 = $mysql->update($sys_user, "id={$user['id']}", 'sys_user');
        $res3 = balanceLog($user, 0, 1, 3, $item['real_money'], $item['id'], $item['order_sn'], $mysql);
        if (!$res1 || !$res2 || !$res3) {
            $mysql->rollback();
            continue;
        }
        $mysql->commit();
    }

    $mysql->close();
    unset($mysql);
    echo "处理完一批，暂停1秒\n";
    sleep(1);
}
?>