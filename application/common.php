<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace app;

// 应用公共文件
use think\Cookie;
use think\Config;
use think\Request;
use think\Response;
use think\Log;
use app\common\Mysql;
use app\common\MyMemcache;

class Common
{
    //检查登录
    public static function checkLogin()
    {
        $user = Common::isLogin();
        if ($user) {
            return $user;
        }
        if (Request::instance()->controller() == 'Login') {
            return false;
        }

        if (Request::instance()->isAjax()) {
            Common::jReturn('-98', '请先登录');
        } else {
            //$callback = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $f = isset($_GET['f']) ? intval($_GET['f']) : 0;
            $url = "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}/" . Request::instance()->module() . "/login/index?f={$f}";;//&callback= . urlencode($callback);
            header("Location:{$url}");
            exit;
        }
    }

    public static function isLogin()
    {
        $token = Common::getParam('token');
        $user = null;
        if ($token) {
            $user = Common::getUserByToken($token);
        }
        if (!$user || !is_array($user)) {
            $cookie_config = Config::get('cookie');
            $cookie_json = Cookie::get($cookie_config['key']);
            $cookie_arr = json_decode($cookie_json,true);
            $sign = Common::sysSign($cookie_arr);
            if ($sign == $cookie_arr['sign']) {
                $token = $cookie_arr['token'];
                $user = Common::getUserByToken($token);
                if (!$user || !is_array($user)) {
                    return false;
                }
            } else {
                return false;
            }
        }

        Log::write("isLogin: getUserByToken = ". var_export($user));
        return $user;
    }

    //将$data数组中的key/val对，按key升序排列(sign除外)
    //并返回md5编码
    public static function sysSign($data)
    {
        $result = '';
        if ($data) {
            ksort($data);
            foreach ($data as $key => $val) {
                if ($key == 'sign') {
                    continue;
                }
                $result .= "{$key}={$val}&";
            }
        }
        $result .= 'key=' . SYS_KEY;
        return md5($result);
    }

    //产生随机数，作为token
    public static function getRsn($seed = '', $length = 16)
    {
        if (!$seed) {
            $seed_ms = microtime();
            $seed = md5($seed_ms . SYS_KEY . mt_rand(100000, 999999));
        } else {
            $seed = md5($seed);
        }
        if ($length == 16) {
            return substr($seed, 8, 16);
        }
        return $seed;
    }

    //对密码加盐做hash
    public static function getPassword($passwd, $domd5 = false)
    {
        if ($domd5) {
            $result = sha1(md5($passwd) . SYS_KEY . '_sqyzt');
        } else {
            $result = sha1($passwd . SYS_KEY . '_sqyzt');
        }
        return $result;
    }

    //从多维数据组获取到指定key的值
    public static function getParam($key = '')
    {
        if (!empty($key)) {
            if (isset($_REQUEST[$key])) {
                $result = Common::filterParam($_REQUEST[$key]);
                return $result;
            } else {
                return '';
            }
        }
        $not_match = Common::filterParam($_REQUEST);
        return $not_match;
    }

    //将数组中每个value值过滤特殊字符
    public static function filterParam($data)
    {
        if (is_array($data)) {
            $result = array();
            foreach ($data as $key => $val) {
                $result[$key] = Common::filterParam($val);
            }
            return $result;
        } else {
            $data = trim($data);
            if ($data !== '') {
                if (!get_magic_quotes_gpc()) {
                    $data = addslashes($data);
                }
                $data = str_replace('%', '\\%', $data);
                $data = htmlspecialchars($data, ENT_QUOTES);
            }

            return $data;
        }
    }

    //打印操作日志
    public static function actionLog($data = array(), $mysql = '')
    {
        if ($data['logUid']) {
            $uid = $data['logUid'];
            unset($data['logUid']);
        } else {
            $user = Common::isLogin();
            if (!$user) {
                return false;
            }
            $uid = $user['id'];
        }
        $userinfo = array('uid' => $uid, 'create_time' => NOW_TIME, 'create_ip' => Common::getClientIp());
        $data = array_merge($data, $userinfo);
        $data['sql_str'] = addslashes($data['sql_str']);
        $to_free = false;
        if (!$mysql) {
            $mysql = new Mysql(0);
            $to_free = true;
        }
        $result = $mysql->insert($data, 'sys_log');
        if ($to_free) {
            $mysql->close();
            unset($mysql);
        }
        return $result;
    }

    //根据token获取用户信息
    public static function getUserByToken($token, $mysql = null)
    {
        if (!$token) {
            return -1;
        }
        $memcache = new MyMemcache(0);
        $mem_key = 'token_' . $token;
        $user = $memcache->get($mem_key);
        if ($user) {
            $memcache->close();
            unset($memcache);
            return $user;
        }

        if (!$mysql) {
            $mysql = new Mysql(0);
        }
        $sys_user_token = $mysql->fetchRow("select * from sys_user_token where token='{$token}' and status=0");
        if (!$sys_user_token) {
            return -2;
        } else {
            //token有效期检测
            //...
        }
        $user = $mysql->fetchRow("select * from sys_user where id={$sys_user_token['uid']}");
        if (!$user) {
            return -4;
        }
        if ($user['phone']) {
            $user['phone_flag'] = substr($user['phone'], 0, 3) . '***' . substr($user['phone'], 8);
        } else {
            $user['phone_flag'] = '';
        }

        $memcache->set($mem_key, $user, 3600);
        $memcache->close();
        unset($memcache);
        return $user;
    }

    //获取用户ip
    public static function getClientIp($idx = 0)
    {
        $idx = $idx ? 1 : 0;
        static $arr_ip = NULL;
        if ($arr_ip !== NULL) {
            return $arr_ip[$idx];
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip_unknown = array_search('unknown', $ip_list);
            if (false !== $ip_unknown) {
                unset($ip_list[$ip_unknown]);
            }
            $arr_ip = trim($ip_list[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $arr_ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $arr_ip = $_SERVER['REMOTE_ADDR'];
        }
        $ip_long = ip2long($arr_ip);
        $arr_ip = $ip_long ? array($arr_ip, $ip_long) : array('0.0.0.0', 0);
        return $arr_ip[$idx];
    }

    //组织返回数据
    public static function jReturn($code, $msg, $data = array())
    {
        $arr = array('code' => $code, 'msg' => $msg);
        if ($data) {
            $arr['data'] = $data;
        }
        echo json_encode($arr, 256);
        exit();
    }

    //获取用户信息
    function getUserinfo($uid,$mysql=null){
        $uid=intval($uid);
        if(!$uid){
            return false;
        }
        if(!$mysql){
            $mysql=new Mysql(0);
        }
        $user=$mysql->fetchRow("select * from sys_user where id={$uid}");
        return $user;
    }
}
