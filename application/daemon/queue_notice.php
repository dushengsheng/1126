<?php
include 'daemon.ini.php';
while(true){
	if(!$mysql){
		$mysql=new Mysql(0);
    }
    $list=$mysql->fetchRows("select * from cnf_notice where status=0",1,5);
	//p($list);exit;
    //echo $mysql->lastSql;exit;
    if(!$list){
        echo "没有数据暂停3秒\n";
		$mysql->close();
		unset($mysql);
        sleep(3);
        continue;
    }
    foreach($list as $item){
		$cnf_notice=[
			'send_time'=>time(),
			'status'=>2
		];
		if($item['type']==1){
			$osn=$item['fkey'];
			//新订单通知码商
			$url="{$_ENV['SOCKET']['HTTP_URL']}/?a=notice&osn={$osn}";
			$result=curl_get($url);
		}elseif($item['type']==2){
			$osn=$item['fkey'];
			//通知前台支付用户
			$url="{$_ENV['SOCKET']['HTTP_URL']}/?a=orderNotice&osn={$osn}";
			$result=curl_get($url);
		}else{
			$result['output']='未知通知类型';
		}
		$cnf_notice['send_msg']=$result['output'];
		$mysql->update($cnf_notice,"id={$item['id']}",'cnf_notice');
    }
	
	$mysql->close();
    unset($mysql);
    echo "处理完一批，暂停1秒\n";
    sleep(1);
}
?>