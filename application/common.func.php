<?php

// 应用公共文件
use think\Cookie;
use think\Config;
use think\Request;
use think\Response;
use think\Log;
use app\common\Mysql;


//将$data数组中的key/val对，按key升序排列(sign除外)
//并返回md5编码
function md5Sign($data, $key = null)
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
    if (!$key) {
        $key = SYS_KEY;
    }
    $result .= 'key=' . $key;
    return md5($result);
}

/**
 * 产生随机数序列, 用于token, 或者用于订单号
 * @param string $seed
 * @param int $length
 * @return false|string
 */
function getSerialNumber($seed = '', $length = 16)
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
        $user = checkUserToken();
        if (!$user) {
            return false;
        }
        $uid = $user['id'];
    }
    $userinfo = array('uid' => $uid, 'create_time' => NOW_TIME, 'create_ip' => getClientIp());
    $data = array_merge($data, $userinfo);
    $data['sql_str'] = addslashes($data['sql_str']);
    $data['readable_time'] = date('Y-m-d H:i:s', NOW_TIME);
    $to_free_mysql = false;
    if (!$mysql) {
        $mysql = new Mysql(0);
        $to_free_mysql = true;
    }
    $result = $mysql->insert($data, 'sys_log');
    if ($to_free_mysql) {
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
function jReturn($code, $msg = '', $data = array())
{
    if (is_array($code)) {
        $arr = $code;
    } else {
        $arr = array('code' => $code, 'msg' => $msg);
        if ($data) {
            $arr['data'] = $data;
        }
    }

    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit();
}

//获取配置信息
function getConfig($key, $mysql = '')
{
    if (!$key) {
        return false;
    }
    $mem_key = 'sys_config_' . $key;
    $mem_arr = memcacheGet($mem_key);
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
        memcacheSet($mem_key, $mem_arr);
    }

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
function deleteUserCookie()
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

/**
 * 是否使用cookie鉴权
 * @return bool
 */
function cookieAuthenticate()
{
    return true;

    $module = Request::instance()->module();
    $controller = Request::instance()->controller();
    $action = Request::instance()->action();
    $url = $module . '/' . $controller . '/' . $action;
    $url = strtolower($url);

    static $pattern_arr = [
        //'admin/index/index',
        'admin/user/agent',
        'admin/user/merchant',
        'admin/finance/users',
        'admin/finance/detail',
        'admin/finance/card',
        'admin/finance/userwithdrawal',
        'admin/finance/recharge',
        'admin/finance/rechargeerror',
        'admin/finance/withdrawal',
        'admin/pay/order'
    ];

    return in_array($url, $pattern_arr);
}


/**
 * rsa解密
 * @param $base64_src 密文字符串, base64编码, 待解密
 * @param $rsa_private_key rsa私钥
 * @return array|string[]
 */
function decryptRsa($base64_src, $rsa_private_key)
{
    if (!$base64_src) {
        return ['code' => '-1', 'msg' => '缺少解密参数'];
    }
    $base64_src = implode('+', explode(' ', $base64_src));
    if (!$rsa_private_key) {
        return ['code' => '-1', 'msg' => '缺少RSA私钥'];
    }
    $pkey = openssl_pkey_get_private($rsa_private_key);
    if (!$pkey) {
        return ['code' => '-1', 'msg' => 'RSA私钥不可用'];
    }
    $base64_dst = '';
    $src_text = base64_decode($base64_src);
    $length_of_bits = openssl_pkey_get_details($pkey)['bits'];
    $src_byte_arr = str_split($src_text, $length_of_bits / 8);
    foreach ($src_byte_arr as $src_byte) {
        openssl_private_decrypt($src_byte, $dst_byte, $pkey);
        if ($dst_byte) {
            $base64_dst .= $dst_byte;
        }
    }
    /*
    $dst_text = base64_decode($base64_dst);

    $dst_text = json_decode($dst_text, true);
    if (!$dst_text) {
        $dst_text = $base64_dst;
    }
    */

    return ['code' => '0', 'msg' => '解密成功', 'data' => $base64_dst];
}

/**
 * rsa加密
 * @param $base64_src 明文字符串, base64编码, 待加密
 * @param $rsa_public_key rsa公钥
 * @return array|string[]
 */
function encryptRsa($base64_src, $rsa_public_key)
{
    if (!$base64_src) {
        return ['code' => '-1', 'msg' => '缺少加密参数'];
    }
    if (!$rsa_public_key) {
        return ['code' => '-1', 'msg' => '缺少RSA公钥'];
    }
    $pkey = openssl_pkey_get_public($rsa_public_key);
    if (!$pkey) {
        return ['code' => '-1', 'msg' => 'RSA公钥不可用'];
    }
    $dst_text = '';
    $length_of_bits = openssl_pkey_get_details($pkey)['bits'];
    $src_byte_arr = str_split($base64_src, $length_of_bits / 8 - 11);
    foreach ($src_byte_arr as $src_byte) {
        openssl_public_encrypt($src_byte, $dst_byte, $pkey);
        $dst_text .= $dst_byte;
    }
    $base64_dst = base64_encode($dst_text);
    return ['code' => '0', 'msg' => '加密成功', 'data' => $base64_dst];
}

/**
 * 将数组签名, 并通过rsa加密
 * @param $param_arr 待加密数组
 * @return array|string[]
 */
function generateToken($param_arr)
{
    if (!$param_arr || !is_array($param_arr)) {
        return ['code' => '-1', 'msg' => '请输入正确的明文数组'];
    }
    $rsa_public_key = getConfig('rsa_pt_public');
    if (!$rsa_public_key) {
        return ['code' => '-1', 'msg' => '请配置平台rsa公钥'];
    }
    $sign = md5Sign($param_arr);
    $param_arr['sign'] = $sign;
    $param_text = json_encode($param_arr, JSON_UNESCAPED_UNICODE);
    $base64_str = base64_encode($param_text);
    return encryptRsa($base64_str, $rsa_public_key);
}

/**
 * 通过rsa解密字符串, 并验证签名
 * @param $ciphertext 密文
 * @return array|string[]
 */
function checkTokenValid($ciphertext)
{
    if (!$ciphertext || !is_string($ciphertext)) {
        return ['code' => '-1', 'msg' => '请输入正确的密文字符串'];
    }
    $rsa_private_key = getConfig('rsa_pt_private');
    if (!$rsa_private_key) {
        return ['code' => '-1', 'msg' => '请配置平台rsa私钥'];
    }
    $decrypt_arr = decryptRsa($ciphertext, $rsa_private_key);
    if ($decrypt_arr['code'] != '0') {
        return $decrypt_arr;
    }
    $base64_str = $decrypt_arr['data'];
    $param_text = base64_decode($base64_str);
    $param_arr = json_decode($param_text, true);
    $sign = md5Sign($param_arr);
    if ($sign != $param_arr['sign']) {
        return ['code' => '-1', 'msg' => 'token验证失败'];
    }
    return ['code' => '0', 'msg' => '验证成功', 'data' => $param_arr];
}

/**
 * 将数组以字段 $key 作为索引
 * @param $src_arr
 * @param string $key
 * @return array
 */
function rows2arr($src_arr, $key = 'id')
{
    $dst_arr = array();
    foreach ($src_arr as $src_item) {
        $dst_arr[$src_item[$key]] = $src_item;
    }
    return $dst_arr;
}

/**
 * 发送http post请求
 * @param string $url
 * @param string $field
 * @param int $timeout
 * @return array
 */
function curl_post($url, $field = '', $timeout = 30)
{
    $http_req = curl_init();
    curl_setopt($http_req, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($http_req, CURLOPT_URL, $url);
    curl_setopt($http_req, CURLOPT_POST, true);
    curl_setopt($http_req, CURLOPT_POSTFIELDS, $field);
    curl_setopt($http_req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($http_req, CURLOPT_HEADER, false);
    curl_setopt($http_req, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($http_req, CURLOPT_REFERER, "");
    curl_setopt($http_req, CURLOPT_FOLLOWLOCATION, true);

    ob_start();
    $http_res = curl_exec($http_req);
    $http_code = curl_getinfo($http_req, CURLINFO_HTTP_CODE);
    ob_end_clean();
    curl_close($http_req);
    unset($http_req);

    $result = [
        'output' => $http_res,
        'response_code' => $http_code
    ];
    return $result;
}

//订单异步回调，返回最新订单数据
function orderNotify($order_id, $mysql)
{
    $order = $mysql->fetchRow("select * from sk_order where id={$order_id}");
    if (!$order || $order['pay_status'] != 9) {
        return false;
    }
    $merchant = $mysql->fetchRow("select * from sys_user where id={$order['suid']}");
    if (!$merchant) {
        return false;
    }
    $url = urldecode($order['notify_url']);
    $p_data = [
        'pay_status' => 9,
        'money' => $order['money'],
        'order_sn' => $order['out_order_sn'],
        'pay_time' => $order['pay_time']
    ];

    ksort($p_data);
    $sign_str = '';
    foreach ($p_data as $pk => $pv) {
        $sign_str .= "{$pk}={$pv}&";
    }
    $sign_str .= "key={$merchant['apikey']}";
    $p_data['sign'] = md5($sign_str);

    //判断是否需要加密传输
    if ($merchant['is_rsa']) {
        if (!$merchant['rsa_public']) {
            return false;
        }
        $p_json = base64_encode(json_encode($p_data, 256));
        $resultArr = encryptRsa($p_json, $merchant['rsa_public']);

        if ($resultArr['code'] != '0') {
            return false;
        }
        $p_data = [
            'crypted' => $resultArr['data']
        ];
    }

    $result = curl_post($url, $p_data, 30);
    $resultMsg = $result['output'];
    $sk_order = [
        'notice_msg' => htmlspecialchars(addslashes($resultMsg))
    ];
    if (!$resultMsg) {
        $sk_order['notice_status'] = 2;
    } else {
        if ($resultMsg['code'] == 0) {
            $sk_order['notice_status'] = 4;
        } else {
            $sk_order['notice_status'] = 3;
        }
    }
    $res = $mysql->update($sk_order, "id={$order['id']}", 'sk_order');
    if (!$res) {
        return false;
    }
    return true;
    /*
    $order = array_merge($order, $sk_order);
    $cnf_notice_status = getConfig('cnf_notice_status');
    $cnf_pay_status = getConfig('cnf_pay_status');
    $order['pay_status_flag'] = $cnf_pay_status[$order['pay_status']];
    $order['notice_status_flag'] = $cnf_notice_status[$order['notice_status']];
    return $order;*/
}
