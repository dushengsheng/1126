<?php

use think\Request;
use think\Exception;
use app\common\Mysql;


/**
 * 清理缓存并退出
 * @return array|bool
 */
function doLogout()
{
    $user = checkUserToken();
    if (!$user) {
        return false;
    }
    //清理节点缓存
    memcacheDelete('user_' . $user['id']);
    memcacheDelete('menu_arr_' . $user['id']);
    memcacheDelete('access_ids_' . $user['id']);
    //清理cookie
    deleteUserCookie();
    return $user;
}

/**
 * 从缓存或数据库中获取用户信息
 * @param $uid int 用户id
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
        if ($user['status'] != 2) {
            doLogout();

            if (Request::instance()->isAjax()) {
                jReturn('-98', '账号已被禁用或删除, 请联系管理员');
            } else {
                $url = SERVER_URL . "/" . Request::instance()->module() . "/login/index";
                header("Location:{$url}");
                exit('账号已被禁用或删除, 请联系管理员');
            }
        }
        /* TODO 被踢下线需要通知用户
        if ($user['is_online'] == 0) {
            jReturn('-98', '登录异常或已被踢下线, 请重新登录');
        }*/
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
        exit('请先登录');
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
 * @param $user
 * @return array|false
 */
function getDownAgent($user)
{
    $children = [];
    if ($user['gid'] < 61) {
        $mysql = new Mysql(0);
        $children = $mysql->fetchRows("select * from sys_user where gid in (1, 61, 81) and status < 99");
        $mysql->close();
        unset($mysql);
    } else {
        $temp_children = getDownUser($user['id'], true);
        foreach ($temp_children as $child) {
            if ($child['status'] < 99 && in_array($child['gid'], [61, 81])) {
                $children[] = $child;
            }
        }
    }

    return $children;
}

/**
 * 删除用户以其名下所有收款码
 * @param $uid
 * @param null $mysql
 * @return bool
 */
function deleteUser($uid, $mysql = null)
{
    $to_free_mysql = false;
    if (!$mysql) {
        $to_free_mysql = true;
        $mysql = new Mysql(0);
    }
    // 删除用户缓存数据
    $mem_key = 'user_' . $uid;
    memcacheDelete($mem_key);

    // 删除用户及其名下所有收款码
    $data = ['status' => 99];
    $res1 = $mysql->update($data, "id={$uid}", 'sys_user');
    $res2 = $mysql->update($data, "uid={$uid}", 'sk_ma');

    if ($to_free_mysql) {
        $mysql->close();
        unset($mysql);
    }
    if (!$res1 || !$res2) {
        return false;
    }
    return true;
}

/**
 * 禁用或启用用户，如果禁用，则将其收款码全部下线
 * @param $uid
 * @param bool $is_forbidden
 * @param null $mysql
 * @return bool
 */
function setUserForbidden($uid, $is_forbidden = true, $mysql = null)
{
    $to_free_mysql = false;
    if (!$mysql) {
        $to_free_mysql = true;
        $mysql = new Mysql(0);
    }

    $res1 = true;
    // 改变用户和收款码状态
    if ($is_forbidden) {
        $data_user = ['status' => 1, 'is_online' => 0];
        $data_skma = ['status' => 1];
        $res1 = $mysql->update($data_skma, "uid={$uid}", 'sk_ma');

        // 删除用户缓存数据
        $mem_key = 'user_' . $uid;
        memcacheDelete($mem_key);
    } else {
        $data_user = ['status' => 2];
    }
    // 改变用户状态
    $res2 = $mysql->update($data_user, "id={$uid}", 'sys_user');

    if ($to_free_mysql) {
        $mysql->close();
        unset($mysql);
    }
    if (!$res1 || !$res2) {
        return false;
    }
    return true;
}

/**
 * 设置用户在线状态，如果下线，则同时下线所有收款码
 * @param $uid
 * @param bool $is_online
 * @param null $mysql
 * @return bool
 */
function setUserOnline($uid, $is_online = true, $mysql = null)
{
    $to_free_mysql = false;
    if (!$mysql) {
        $to_free_mysql = true;
        $mysql = new Mysql(0);
    }

    $res1 = true;
    // 改变用户和收款码状态
    if ($is_online) {
        $data_user = ['is_online' => 1];
    } else {
        $data_user = ['is_online' => 0];
        $data_skma = ['status' => 1];
        $res1 = $mysql->update($data_skma, "uid={$uid}", 'sk_ma');
        // 删除用户缓存数据
        $mem_key = 'user_' . $uid;
        memcacheDelete($mem_key);
    }
    $res2 = $mysql->update($data_user, "id={$uid}", 'sys_user');

    if ($to_free_mysql) {
        $mysql->close();
        unset($mysql);
    }
    if (!$res1 || !$res2) {
        return false;
    }
    return true;
}

/**
 * 获取用户可用通道列表
 * @param $user
 * @return array
 */
function getUserMtype($user, $mysql = null)
{
    $td_switch = json_decode($user['td_switch'], true);
    $td_switch_arr = [];
    foreach ($td_switch as $key => $val) {
        if ($val < 1) {
            continue;
        }
        $td_switch_arr[] = $key;
    }
    $td_switch_str = implode(',', $td_switch_arr);
    $where = "where is_open=1";
    if ($user['gid'] >= 61) {
        $where .= " and id in ({$td_switch_str})";
    }
    $to_free_mysql = false;
    if (!$mysql) {
        $mysql = new Mysql(0);
        $to_free_mysql = true;
    }
    $result =  rows2arr($mysql->fetchRows("select * from sk_mtype {$where}"));
    if ($to_free_mysql) {
        $mysql->close();
        unset($mysql);
    }
    return $result;
}

