<?php

namespace app\admin\controller;

use app\common\Mysql;
use think\Config;
use think\Cookie;
use think\Exception;
use think\Request;
use think\captcha\Captcha;
use think\Response;
use think\Log;
use app\common\PHPGangsta_GoogleAuthenticator;


class Login extends Base
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
    }

    //登录界面
    public function index()
    {
        $this->checkLogin();
        $data = [
            'server' => ADMIN_URL,
            'data' => $this->params
        ];

        return $this->fetch('Login/login', $data);
    }

    //检查是否已登录
    private function checkLogin()
    {
        $user = checkUserToken();
        if ($user) {
            $server = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
            header('Location:' . $server . '/' . Request::instance()->module());
            exit();
        }
    }

    //登录操作
    public function loginAct()
    {
        $params = $this->params;
        Log::write(var_export($params, true));

        $password = $params['passwd'];
        $account_name = $params['username'];
        $varify_code = strtolower($params['vercode']);
        /*
        if(strlen($account_name)<4||strlen($account_name)>15){
            jReturn('-1','请输入4-15个字符的帐号');
        }
        if($f&&!isPhone($account_name)){
            jReturn('-1','请输入正确的手机账号');
        }
        */
        if (!$password) {
            jReturn('-1', '请输入密码');
        }
        //校验验证码
        $captcha = new Captcha();
        if (!$captcha->check($varify_code)) {
            jReturn('-1', '图形验证码不正确');
        }

        $user = $this->mysql->fetchRow("select * from sys_user where (account='{$account_name}' or phone='{$account_name}') and status=2");
        //Log::write("loginAct: mysql query result: " . var_export($user, true));

        $login_status = 0;
        if (!$user || !$user['status']) {
            $login_status = 1;
        } else {
            $password = getPassword($password);
            if ($user['is_google']) {
                if (!$this->params['gcode']) {
                    jReturn('-1', '请填写谷歌验证码');
                }
                $ga = new PHPGangsta_GoogleAuthenticator();
                $checkResult = $ga->verifyCode($user['google_secret'], $this->params['gcode'], 2);
                if (!$checkResult) {
                    jReturn('-1', '谷歌验证失败');
                }
            }
            if ($password != $user['password']) {
                $login_status = 2;
            } else {
                if ($user['status'] != 2) {
                    jReturn('-1', '该账号被禁止登录');
                }
            }
        }
        if ($login_status) {
            //$_SESSION['varify_time_1']++;//登录次数
            jReturn('-1', '账号或密码错误');
        } else {
            /*
            if(!$f){
                if($user['gid']<=41){
                    jReturn('-1','超管登录地址不正确');
                }
            }else{
                if($user['gid']>41){
                    jReturn('-1','商户或代理登录地址不正确');
                }
            }

            //最后再校验短信验证码
            if($f&&$params['smscode']!='111222'){
                $checkSms=checkPhoneCode(['stype'=>2,'phone'=>$params['acname'],'code'=>$params['smscode']]);
                if($checkSms['code']!=1){
                    exit(json_encode($checkSms));
                }
            }*/

            $login_data = array(
                'login_ip' => getClientIp(),
                'login_time' => NOW_TIME
            );
            $this->mysql->update($login_data, "id={$user['id']}", 'sys_user');

            $sys_user_token = [
                'id' => $user['id'],
                'account' => $user['account'],
                'token' => getSerialNumber(),
                'create_time' => NOW_TIME,
                'update_time' => NOW_TIME
            ];

            //生成token
            $rsa_result = generateToken($sys_user_token);
            if ($rsa_result['code'] != '0') {
                jReturn($rsa_result);
            }
            $token = $rsa_result['data'];

            $return_data = [
                'account' => $user['account'],
                'token' => $token
            ];

            //保存cookie
            $cookie_json = json_encode($return_data, 256);
            setUserCookie($cookie_json);

            actionLog(['opt_name' => '登录', 'sql_str' => '', 'logUid' => $user['id']], $this->mysql);
            jReturn('0', '登录成功', $return_data);
        }
    }

    //登出
    public function logoutAct()
    {
        actionLog(['opt_name' => '退出', 'sql_str' => ''], $this->mysql);
        doLogout();

        if (Request::instance()->isAjax()) {
            jReturn('0', '退出成功');
        } else {
            header('Location:' . ADMIN_URL);
        }
    }

    //生成验证码
    public function captcha()
    {
        $captcha = new Captcha();
        $captcha->fontSize = 40;
        $captcha->length = 4;
        return $captcha->entry();
    }

}