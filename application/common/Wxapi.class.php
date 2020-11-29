<?php
//微信接口
class Wxapi{
	
	protected $appid='';
	protected $memcached=null;
	protected $wx_account=array();
	protected $mem_key='';
	
	//构造函数
	public function __construct($appid=''){
		$this->now_time=time();
		$this->appid=$appid?$appid:APP_ID;
		$this->mem_key='wxaccount_'.$this->appid;
	}
	
	//析构函数
	public function __destruct(){
		unset($this->memcached);
		unset($this->wx_account);
	}
	
	/////////////////////////获取用户信息相关////////////////////////////
	
	//网页授权 根据code和state(显示或隐式判断条件)获取相应数据
	public function getUserInfo2($code,$scope='base'){
		$access_token=$this->getToken();
		if(!$access_token){
			return false;
		}
		$api_url=sprintf('https://api.weixin.qq.com/sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code',$this->wx_account['appid'],$this->wx_account['appsecret'],$code);
		$result=curl_get($api_url);
		$resultArr=json_decode($result['output'],1);
		$openid=$resultArr['openid']; //包涵网页access_token和openid
		if(!$openid){
			return false;
		}else{
			//不需要获取用户昵称等信息
			if($scope=='base'){
				return array('openid'=>$openid);
			}
		}
		$user=$this->getUserInfo($openid);//尝试使用已关注的方式来获取用户信息;
		if($user['openid']){
			$user['is_gz']=1;
			return $user;
		}
		$web_access_token=$resultArr['access_token'];
		$api_url='https://api.weixin.qq.com/sns/userinfo?access_token='.$web_access_token.'&openid='.$openid.'&lang=zh_CN';
		$result=curl_get($api_url);
		$resultArr=json_decode($result['output'],1);
		if(!$resultArr['openid']){
			return false;
		}
		return $resultArr;
	}
	
	//根据openid获取用户信息，只能获取已经关注的用户
	public function getUserInfo($openid){
		$access_token=$this->getToken();
		if(!$access_token){
			return false;
		}
		$user=array();
		$api_url=sprintf('https://api.weixin.qq.com/cgi-bin/user/info?access_token=%s&openid=%s&lang=zh_CN',$access_token,$openid);
		$result=curl_get($api_url);
		$user=json_decode($result['output'],1);
		//未关注时取不到用户信息
		if(!$user['openid']||!$user['nickname']){
			return false;
		}
		return $user;
	}
	
	//获取摇周边的设备及用户信息
	public function getShakeInfo($ticket){
		if(!is_object($this->memcached)){
			$this->memcached=new MyMemcache(0);
		}
		$mem_key='shake_ticket_'.$ticket;
		$data=$this->memcached->get($mem_key);
		if(!$data){
			$access_token=$this->getToken();
			$api_url="https://api.weixin.qq.com/shakearound/user/getshakeinfo?access_token={$access_token}";
			$data=json_encode(array('ticket'=>$ticket,'need_poi'=>1));
			$result=curl_post($api_url,$data);
			$resultArr=json_decode($result['output'],1);
			if($resultArr['errcode']){
				return false;
			}
			$data=$resultArr['data'];
			$data['shake_ticket']=$ticket;
			$this->memcached->set($mem_key,$data,86400);//缓存1天
		}
		return $data;
	}
	
	
	/////////////////////////微信基础接口方法////////////////////////////
	//获取token
	public function getToken(){
		if(!is_object($this->memcached)){
			$this->memcached=new MyMemcache(0);
		}
		$wx_account=$this->memcached->get($this->mem_key);
		if($wx_account&&$this->now_time-$wx_account['access_token_time']<7000){
			//内存中的access_token有效
			$this->wx_account=$wx_account;
			return $wx_account['access_token'];
		}
		$mysql=new Mysql(0);
		if($wx_account['appsecret']){
			$appsecret=$wx_account['appsecret'];
		}else{
			//从DB中取公众号信息
			$wx_account=$mysql->fetchRow("select * from sys_gzh where appid='{$this->appid}'");
			$appsecret=$wx_account['appsecret'];
			if(!$appsecret){
				return false;
			}
		}
		//直接调接口
		$url_tpl="https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s";
		$url=sprintf($url_tpl,$this->appid,$appsecret);
		$result=http_fget($url);
		$resultArr=json_decode($result['output'],1);
		$access_token=$resultArr['access_token'];
		if($access_token){
			$wx_account['access_token']=$access_token;
			$wx_account['access_token_time']=$this->now_time;
			$this->memcached->set($this->mem_key,$wx_account,0);//更新缓存
			$this->wx_account=$wx_account;
			//更新到DB
			$wx_account_data=array('access_token'=>$access_token,'access_token_time'=>$this->now_time);
			$mysql->update($wx_account_data,"appid='{$this->appid}'",'sys_gzh');
			unset($mysql);
			return $access_token;
		}else{
			//echo $this->appid;
			//p($resultArr);exit;
		}
		return false;
	}
	
	//获取jssdk-ticket
	public function getJssdkTicket(){
		$access_token=$this->getToken();
		if(!$access_token){
			return false;
		}
		if($this->wx_account['jsapi_ticket']&&$this->now_time-$this->wx_account['jsapi_ticket_time']<7000){
			return $this->wx_account['jsapi_ticket'];
		}
		$url="https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token={$access_token}";
		$result=curl_get($url);
		$resultArr=json_decode($result['output'],1);
		if($resultArr['errcode']){
			return false;
		}
		$this->wx_account['jsapi_ticket']=$resultArr['ticket'];
		$this->wx_account['jsapi_ticket_time']=$this->now_time;
		$this->memcached->set($this->mem_key,$this->wx_account,0);//更新缓存
		return $this->wx_account['jsapi_ticket'];
	}
	
	
	//生成二维码 (临时和永久)
	public function getQrcode($scene_id='',$type='tmp'){
		$access_token=$this->getToken();
		if(!$access_token){
			return false;
		}
		$scene_id=empty($scene_id)?time()+rand(1000000,9999999):$scene_id;
		//{"expire_seconds": 1800, "action_name": "QR_SCENE", "action_info": {"scene": {"scene_id": 123}}}
		//$scene_id=time()+rand(1000000,9999999);
		if($type=='tmp'){
			$data=array(
				'expire_seconds'=>604800,
				'action_name'=>'QR_SCENE',
				'action_info'=>array(
					'scene'=>array('scene_id'=>$scene_id)
				)
			);	
		}else{
			$data=array(
				'action_name'=>'QR_LIMIT_STR_SCENE',
				'action_info'=>array(
					'scene'=>array('scene_str'=>$scene_id)
				)
			);
		}
		$data_str=json_encode($data);
		$tiket_url='http://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$access_token;
		$result=curl_post($tiket_url,$data_str);
		$resultArr=json_decode($result['output'],1);
		$ticket=$resultArr['ticket'];
		if(!$ticket){
			return false;
		}
		$qrcode_url='https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$ticket;
		return $qrcode_url;
	}
	
	//获取api创建的自定义菜单
	public function getMenu(){
		$access_token=$this->getToken();
		$url='https://api.weixin.qq.com/cgi-bin/get_current_selfmenu_info?access_token='.$access_token;
		$result=curl_get($url);
		$resultArr=json_decode($result['output'],1);
		return $resultArr;
	}

	//自定义菜单 传入json字符串
	public function setMenu($menu_arr=array()){
		$access_token=$this->getToken();
		$menu_str=jsonEncode($menu_arr);//特殊处理的json方法
		$url='http://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$access_token;
		$result=curl_post($url,$menu_str);
		$resultArr=json_decode($result['output'],1);
		return $resultArr;
	}
	

	////////////////////////////微信支付相关////////////////////////////////
	//发放现金红包
	public function sendHongbao($openid,$money,$act_name='',$sub_mch_id=0){
		$result=$this->sendRedPack($openid,$money,$act_name,$sub_mch_id);
		return $result;
	}
	
	//现金红包接口
	private function sendRedPack($openid,$money,$act_name='',$sub_mch_id=0){
		$money=$money*100;//单位为分
		if($money<100||$money>20000){
			return false;//红包的金额范围 1-200元
		}
		$access_token=$this->getToken();
		$api_url='https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack';
		$rand=mt_rand(10000,99999);
		$pay_config=$this->wx_account;
		$act_name=empty($act_name)?$pay_config['wx_name'].'的活动':$act_name;
		$param_arr=array(
			'nonce_str'=>'nonce_str'.$rand,
			'mch_billno'=>'HB'.date('YmdHis').$rand,
			'mch_id'=>$pay_config['mch_id'],
			'wxappid'=>$this->appid,
			'nick_name'=>$pay_config['wx_name'],
			'send_name'=>$pay_config['wx_name'],
			're_openid'=>$openid,
			'total_amount'=>$money,
			'min_value'=>$money,
			'max_value'=>$money,
			'total_num'=>1,
			'wishing'=>'恭喜您获得红包',
			'client_ip'=>CLIENT_IP,
			'act_name'=>$act_name,
			'remark'=>"更多精彩敬请关注{$pay_config['wx_name']}公众号！"
		);
		if($sub_mch_id){
			$param_arr['sub_mch_id']=$sub_mch_id;//子商户号
		}
		ksort($param_arr);
		$param_str='';
		$xml='<xml>';
		foreach($param_arr as $key=>$val){
			$xml.="<{$key}>{$val}</{$key}>";
			$param_str.="{$key}={$val}&";
		}
		$param_str.="key={$pay_config['pay_key']}";
		$sign=md5($param_str);
		$xml.="<sign>{$sign}</sign>";
		$xml.='</xml>';
		$result=$this->postXmlSSLCurl($api_url,$xml);
		$postObj=simplexml_load_string($result['output'],'SimpleXMLElement',LIBXML_NOCDATA);
		if($postObj->return_code=='SUCCESS'&&$postObj->result_code=='SUCCESS'){
			return true;
		}
		//写日志
		$log_path=ROOT_PATH."logs/hongbao/{$openid}.txt";
		if(!is_dir(dirname($log_path))){
			mkdir(dirname($log_path),0755,true);
		}
		file_put_contents($log_path,date('Y-m-d H:i:s')."\r\n".$postObj->return_msg.$postObj->err_code_des."\r\n\r\n",FILE_APPEND);
		return false;
	}
	
	//预付下单
	public function prepayOrder($openid,$money,$notify_url,$trade_type='JSAPI',$sub_mch_id=0){
		$money=$money*100;//将金额转换成分
		if($money<1){
			return false;
		}
		$access_token=$this->getToken();
		//$notify_url='http://pay.xx.cn/wechat/notify_url.php';//后台回调地址不能携带参数
		$rand=mt_rand(100000,999999);
		$order_id='DD'.date('YmdHis').$rand;//本地统一订单号
		
		$prepay_url='https://api.mch.weixin.qq.com/pay/unifiedorder';
		$pay_config=$this->wx_account;
		$param_arr=array(
			'appid'=>$this->appid,
			'mch_id'=>$pay_config['mch_id'],
			'openid'=>$openid,
			'nonce_str'=>'nonce_str'.$rand,
			'body'=>'实时付款',
			'out_trade_no'=>$order_id,
			'total_fee'=>$money,
			'spbill_create_ip'=>CLIENT_IP,
			'notify_url'=>$notify_url,
			'trade_type'=>$trade_type,//JSAPI/NATIVE
			'attach'=>'S'
		);
		if($sub_mch_id){
			$param_arr['sub_mch_id']=$sub_mch_id;
		}
		ksort($param_arr);
		$param_str='';
		$xml='<xml>';
		foreach($param_arr as $key=>$val){
			$xml.="<{$key}>{$val}</{$key}>";
			$param_str.="{$key}={$val}&";
		}
		$param_str=$param_str."key={$pay_config['pay_key']}";
		$sign=strtoupper(md5($param_str));
		$xml.="<sign>{$sign}</sign>";
		$xml.='</xml>';
		$result=curl_post($prepay_url,$xml);
		$prepay_id='';
		$return_msg='';
		if($result['response_code']===200){
			$preOrder=simplexml_load_string($result['output'],'SimpleXMLElement',LIBXML_NOCDATA);
			if($preOrder->return_code=='SUCCESS'&&$preOrder->result_code=='SUCCESS'){
				$prepay_id=(string)$preOrder->prepay_id;
			}else{
				$return_msg=(string)$preOrder->return_msg;
				//写日志
				$log_path=ROOT_PATH."logs/preorder/{$openid}.txt";
				if(!is_dir(dirname($log_path))){
					mkdir(dirname($log_path),0755,true);
				}
				file_put_contents($log_path,date('Y-m-d H:i:s')."\r\n".var_export($preOrder,true)."\r\n\r\n",FILE_APPEND);
			}
		}
		if($prepay_id){
			$order=array('trade_type'=>$trade_type);
			if($trade_type=='JSAPI'){
				$order['paySign']=$this->paySign($prepay_id);
			}
			$order['prepay_id']=$prepay_id;
			$order['order_id']=$order_id;
			//trade_type为NATIVE有返回code_url，可将该参数值生成二维码展示出来进行扫码支付
			return $order;
		}
		return $return_msg;
	}
	
	//jsapi支付签名
	public function paySign($prepay_id){
		$access_token=$this->getToken();
		$param_arr=array(
			'appId'=>$this->appid,
			'timeStamp'=>time(),
			'nonceStr'=>'nonce_str2'.mt_rand(10000,99999),
			'package'=>'prepay_id='.$prepay_id,
			'signType'=>'MD5'
		);
		ksort($param_arr);
		$param_str='';
		foreach($param_arr as $key=>$val){
			$param_str.="{$key}={$val}&";
		}
		$param_str=$param_str."key={$this->wx_account['pay_key']}";
		$paySign=strtoupper(md5($param_str));
		$param_arr['paySign']=$paySign;
		return $param_arr;
	}
	
	//xml提交数据
	private function postXmlSSLCurl($url,$xml,$second=30){
		$sslcert_path=GLOBAL_PATH.'config/cert/'.$this->appid.'/apiclient_cert.pem';
		$sslkey_path=GLOBAL_PATH.'config/cert/'.$this->appid.'/apiclient_key.pem';
		$ch = curl_init();
		//超时时间
		curl_setopt($ch,CURLOPT_TIMEOUT,$second);
		//这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
		//设置header
		curl_setopt($ch,CURLOPT_HEADER,FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
		//设置证书
		//使用证书：cert 与 key 分别属于两个.pem文件
		//默认格式为PEM，可以注释
		curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
		curl_setopt($ch,CURLOPT_SSLCERT,$sslcert_path);
		//默认格式为PEM，可以注释
		curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
		curl_setopt($ch,CURLOPT_SSLKEY,$sslkey_path);
		//post提交方式
		curl_setopt($ch,CURLOPT_POST, true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
		$output = curl_exec($ch);
		$responseCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
		$arrCurlResult['output'] = $output;//返回结果
		$arrCurlResult['response_code'] = $responseCode;//返回http状态
		curl_close($ch);
		unset($ch);
		return $arrCurlResult;
	}
	

}

?>