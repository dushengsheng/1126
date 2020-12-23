<?php

// 应用公共文件
use think\Cookie;
use think\Config;
use think\Request;
use think\Response;
use think\Log;
use app\common\Mysql;
use app\common\MyMemcache;


//将$data数组中的key/val对，按key升序排列(sign除外)
//并返回md5编码
function sysSign($data)
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
function getRsn($seed = '', $length = 16)
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
function getPassword($passwd, $domd5 = false)
{
    if ($domd5) {
        $result = sha1(md5($passwd) . SYS_KEY . '_sqyzt');
    } else {
        $result = sha1($passwd . SYS_KEY . '_sqyzt');
    }
    return $result;
}

//从多维数据组获取到指定key的值
function getParam($key = '')
{
    if (!empty($key)) {
        if (isset($_REQUEST[$key])) {
            $result = filterParam($_REQUEST[$key]);
            return $result;
        } else {
            return '';
        }
    }
    $not_match = filterParam($_REQUEST);
    return $not_match;
}

//将数组中每个value值过滤特殊字符
function filterParam($data)
{
    if (is_array($data)) {
        $result = array();
        foreach ($data as $key => $val) {
            $result[$key] = filterParam($val);
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
function actionLog($data = array(), $mysql = '')
{
    if (isset($data['logUid'])) {
        $uid = $data['logUid'];
        unset($data['logUid']);
    } else {
        $user = isLogin();
        if (!$user) {
            return false;
        }
        $uid = $user['id'];
    }
    $userinfo = array('uid' => $uid, 'create_time' => NOW_TIME, 'create_ip' => getClientIp());
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

//获取用户ip
function getClientIp($idx = 0)
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
function jReturn($code, $msg, $data = array())
{
    $arr = array('code' => $code, 'msg' => $msg);
    if ($data) {
        $arr['data'] = $data;
    }
    echo json_encode($arr, 256);
    exit();
}

//获取配置信息
function getConfig($key, $mysql = '')
{
    if (!$key) {
        return false;
    }
    $mem_key = $_ENV['CONFIG']['MEMCACHE']['PREFIX'] . 'sys_config_' . $key;
    $memcache = new MyMemcache(0);
    $mem_arr = $memcache->get($mem_key);
    if (!$mem_arr) {
        $to_free_mysql = false;
        if (!$mysql) {
            $mysql = new Mysql(0);
            $to_free_mysql = true;
        }
        $result_nodes = $mysql->fetchRow("select * from sys_config where skey='{$key}'");
        if ($to_free_mysql) {
            $mysql->close();
            unset($mysql);
        }
        if (!$result_nodes) {
            $memcache->close();
            unset($memcache);
            return false;
        }
        if ($result_nodes['single']) {
            $mem_arr = $result_nodes['config'];
        } else {
            $config_slice = explode(',', $result_nodes['config']);
            $result_arr = [];
            foreach ($config_slice as $config_item) {
                $config_pair = explode('=', $config_item);
                $config_key = trim($config_pair[0]);
                if ($config_key === '') {
                    continue;
                }
                $result_arr[$config_key] = trim($config_pair[1]);
            }
            $mem_arr = $result_arr;
        }
        $memcache->set($mem_key, $mem_arr, 7200);
    }
    $memcache->close();
    unset($memcache);
    return $mem_arr;
}

//设置cookie, 参数为json格式
function setUserCookie($cookie_json)
{
    //admin or home
    $who = Request::instance()->module();

    $cookie_config = Config::get('cookie');
    $cookie_key = 'default_cookie_key';
    if (isset($cookie_config['key'])) {
        $cookie_key = $cookie_config['key'] . '_' . $who;
    }
    Cookie::set($cookie_key, $cookie_json);
}

//获取json, 返回值为json格式
function getUserCookie()
{
    //admin or home
    $who = Request::instance()->module();

    $cookie_config = Config::get('cookie');
    $cookie_key = 'default_cookie_key';
    if (isset($cookie_config['key'])) {
        $cookie_key = $cookie_config['key'] . '_' . $who;
    }
    return Cookie::get($cookie_key);
}

//清除cookie
function deleteCookie()
{
    //admin or home
    $who = Request::instance()->module();

    $cookie_config = Config::get('cookie');
    $cookie_key = 'default_cookie_key';
    if (isset($cookie_config['key'])) {
        $cookie_key = $cookie_config['key'] . '_' . $who;
    }
    Cookie::delete($cookie_key);
}