<?php

use think\Request;
use app\common\Mysql;


/**
 * 退出并清理缓存
 * @return bool
 */
function doLogout()
{
    $user = checkUserToken();
    if (!$user) {
        return true;
    }
    //清理节点缓存
    memcacheDelete('user_' . $user['id']);
    memcacheDelete('access_ids_' . $user['id']);
    //清理cookie
    deleteUserCookie();
    return true;
}

/**
 * 从缓存或数据库中获取用户信息
 * @param $uid 用户id
 * @param false $fresh 是否刷新(从数据库中获取)
 * @param null $mysql
 * @return array|false
 */
function getUserinfo($uid, $fresh = false, $mysql = null)
{
    $uid = intval($uid);
    if (!$uid) {
        return false;
    }
    $mem_key = 'user_' . $uid;
    $user = memcacheGet($mem_key);

    if (!$user || $fresh) {
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
        if ($user) {
            memcacheSet($mem_key, $user);
        }
    }

    return $user;
}

/**
 * 检查用户是否已登录，若未登录，则跳转到登录界面
 * @return false|mixed|string
 */
function checkLogin()
{
    $user = checkUserToken();
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
}

/**
 * 检测token, 验证用户登录状态
 * admin/index/index 可以通过cookie验证
 * @return array|false
 */
function checkUserToken()
{
    $token = getParam('token');
    if (!$token && cookieAuthenticate()) {
        $cookie_json = getUserCookie();
        if ($cookie_json) {
            $cookie_arr = json_decode($cookie_json, true);
            $token = $cookie_arr['token'];
        }
    }

    $check_result = checkTokenValid($token);
    if ($check_result['code'] != '0') {
        return false;
    }

    $user = $check_result['data'];
    return getUserinfo($user['id']);
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

/**
 * 获取旗下所有代理人，包括管理员
 * @param $uid
 * @return array|false
 */
function getDownAgent($uid)
{
    $myself = getUserinfo($uid, true);
    if (!$myself) {
        return false;
    }
    $children = [];
    if ($myself['gid'] < 41) {
        $mysql = new Mysql(0);
        $children = $mysql->fetchRows("select * from sys_user where gid in (1, 61, 81) and status < 99");
        $mysql->close();
        unset($mysql);
    } else {
        $temp_children = getDownUser($uid, true);
        foreach ($temp_children as $child) {
            if ($child['status'] < 99 && in_array($child['gid'], [61, 81])) {
                $children[] = $child;
            }
        }
        $children[] = $myself;
    }

    return $children;
}

