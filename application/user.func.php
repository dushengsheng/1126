<?php

use think\Request;
use app\common\Mysql;
use app\common\MyMemcache;


function clearToken($uid, $mysql = null)
{
    $to_free_mysql = false;
    if (!$mysql) {
        $mysql = new Mysql(0);
        $to_free_mysql = true;
    }
    $memcache = new MyMemcache(0);
    $token_arr = $mysql->fetchRows("select * from sys_user_token where uid={$uid}", 1, 1000);
    foreach ($token_arr as $tk) {
        $mem_key = 'token_' . $tk['token'];
        $memcache->delete($mem_key);
    }
    $mysql->delete("uid={$uid}", 'sys_user_token');
    if ($to_free_mysql) {
        $mysql->close();
        unset($mysql);
    }
    $memcache->close();
    unset($memcache);
    return true;
}

//执行退出清理
function doLogout()
{
    $user = isLogin();
    if (!$user) {
        return true;
    }
    //清理cookie
    deleteCookie();
    //清理token
    clearToken($user['id']);
    //清理节点缓存
    $memcache = new MyMemcache(0);
    $mem_key = $_ENV['CONFIG']['MEMCACHE']['PREFIX'] . 'access_ids_' . $user['id'];
    $memcache->delete($mem_key);
    unset($memcache);
    return true;
}

//获取用户信息
function getUserinfo($uid, $mysql = null)
{
    $uid = intval($uid);
    if (!$uid) {
        return false;
    }
    $to_free_mysql = false;
    if (!$mysql) {
        $mysql = new Mysql(0);
        $to_free_mysql = true;
    }
    $user = $mysql->fetchRow("select * from sys_user where id={$uid}");
    if ($to_free_mysql) {
        $mysql->close();
        unset($mysql);
    }
    return $user;
}

//检查登录
function checkLogin()
{
    $user = isLogin();
    if ($user) {
        return $user;
    }
    if (Request::instance()->controller() == 'Login') {
        return false;
    }

    if (Request::instance()->isAjax()) {
        jReturn('-98', '请先登录');
    } else {
        $url = SERVER_URL . "/" . Request::instance()->module() . "/login/index";
        header("Location:{$url}");
        exit();
    }

    return null;
}

//检查登录
function isLogin()
{
    $token = getParam('token');
    $user = null;
    if ($token) {
        $user = getUserByToken($token);
    }
    if (!$user || !is_array($user)) {
        $cookie_json = getUserCookie();
        $cookie_arr = json_decode($cookie_json,true);
        $sign = sysSign($cookie_arr);
        if ($sign == $cookie_arr['sign']) {
            $token = $cookie_arr['token'];
            $user = getUserByToken($token);
            if (!$user || !is_array($user)) {
                return false;
            }
        } else {
            return false;
        }
    }

    return $user;
}

//根据token获取用户信息
function getUserByToken($token, $mysql = null)
{
    if (!$token) {
        return false;
    }
    $memcache = new MyMemcache(0);
    $mem_key = 'token_' . $token;
    $user = $memcache->get($mem_key);
    $to_free_mysql = false;

    do {
        if ($user) {
            break;
        }
        if (!$mysql) {
            $mysql = new Mysql(0);
            $to_free_mysql = true;
        }

        $sys_user_token = $mysql->fetchRow("select * from sys_user_token where token='{$token}' and status=0");
        if (!$sys_user_token) {
            break;
        } else {
            //token有效期检测
            //...
        }
        $user = getUserinfo($sys_user_token['uid']);
        if (!$user) {
            break;
        }
        if ($user['phone']) {
            $user['phone_flag'] = substr($user['phone'], 0, 3) . '***' . substr($user['phone'], 8);
        } else {
            $user['phone_flag'] = '';
        }
    } while (0);

    if ($to_free_mysql) {
        $mysql->close();
        unset($mysql);
    }
    if ($user) {
        $memcache->set($mem_key, $user, 3600);
    }

    $memcache->close();
    unset($memcache);
    return $user;
}

/*
 * @uid 指定id, 查找该id下级用户
 * @return_user_array true=返回user数组, false=返回id数组
 * @level 当前查询等级层数
 * @level_limit 查询最高层数, 0表示不限制层数
 */
function getDownUser($uid, $return_user_array = false, $level = 1, $level_limit = 0, &$result = array())
{
    if ($level_limit && $level > $level_limit) {
        return $result;
    }
    $mysql = new Mysql(0);
    if ($uid) {
        $children = $mysql->fetchRows("select * from sys_user where pid={$uid}");
        foreach ($children as $child) {
            if ($child['id'] && $child['id'] != $uid) {
                if ($return_user_array) {
                    $child['agent_level'] = $level;
                    $result[] = $child;
                    //array_push($result, $child);
                } else {
                    if (!in_array($child['id'], $result)) {
                        $result[] = $child['id'];
                        //array_push($result, $child['id']);
                    }
                }
                getDownUser($child['id'], $return_user_array, $level + 1, $level_limit, $result);
                //$grandChildren = getDownUser($child['id'], $return_user_array, $level + 1, $level_limit);
                //$result = array_merge_recursive($result, $grandChildren);
            }
        }
    }
    $mysql->close();
    unset($mysql);
    return $result;
}

/*
 * 查找上级用户, 原理与getDownUser相似
*/
function getUpUser($uid, $return_user_array = false, $level = 1, $level_limit = 0, &$result = array())
{
    if ($level_limit && $level > $level_limit + 1) {
        return $result;
    }
    $mysql = new Mysql(0);
    $myself = $mysql->fetchRow("select * from sys_user where id={$uid}");
    if ($myself) {
        if ($level > 1) {
            if ($return_user_array) {
                $myself['agent_level'] = $level - 1;
                array_push($result, $myself);
            } else {
                if (!in_array($myself['id'], $result)) {
                    array_push($result, $myself['id']);
                }
            }
        }
        if ($myself['pid'] && $myself['id'] != $myself['pid']) {
            getUpUser($myself['pid'], $return_user_array, $level + 1, $level_limit, $result);
        }
    }
    $mysql->close();
    unset($mysql);
    return $result;
}

