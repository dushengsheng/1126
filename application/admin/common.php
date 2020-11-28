<?php

use think\Request;
use think\Db;
use app\Common;


define('NKEY', Request::instance()->module() . '_' . Request::instance()->controller());

//检查权限
function checkPower($nkey = '')
{
    $user = Common::checkLogin();
    //超管不用检测权限
    if ($user['id'] == 1 || $user['gid'] == 1) {
        return $user;
    } else {
        $check_res = hasPower($user, $nkey);
    }
    if (!$check_res) {
        if (Request::instance()->isAjax()) {
            jReturn('-99', '抱歉没有权限');
        } else {
            exit('抱歉没有权限');
        }
    }
    return $user;
}

function hasPower($user, $nkey)
{
    if ($user['id'] == 1 || $user['gid'] == 1) {
        return true;
    }
    if (!$nkey) {
        $nkey = NKEY;
    }
    $mysql = new Mysql(0);
    $node = Db::query("select id,public from sys_node where nkey='{$nkey}'");
    if (!$node) {
        return false;
    }
    if ($node['public']) {
        return true;
    }
    $access_ids_arr = getAccessNode($user['id'], $mysql);
    if (!$access_ids_arr) {
        return false;
    }
    if (!in_array($node['id'], $access_ids_arr)) {
        return false;
    }
    return true;
}

//获取个人菜单
function getUserMenu($uid, $mysql)
{
    if (!$uid) {
        return false;
    }
    if (!$mysql) {
        $mysql = new Mysql(0);
    }
    $user = getUserinfo($uid, $mysql);
    if (!$user) {
        return false;
    }

    $memcache = new MyMemcache(0);
    $mem_key = $_ENV['CONFIG']['MEMCACHE']['PREFIX'] . 'menu_arr_' . $uid;
    $menu_arr = $memcache->get($mem_key);
    if (!$menu_arr) {
        $mysql = new Mysql(0);
        if ($user['id'] == 1 || $user['gid'] == 1) {
            $node = $mysql->fetchRows("select * from sys_node where type=1 order by pid,sort,id");
        } else {
            $access_ids_arr = getAccessNode($user['id'], $mysql);
            if (!$access_ids_arr) {
                $node = array();
            } else {
                $access_ids_str = implode(',', $access_ids_arr);
                $node = $mysql->fetchRows("select * from sys_node where (id in({$access_ids_str}) or public=1) and type=1 order by pid,sort,id");
            }
        }
        foreach ($node as $nv) {
            if (!$nv['pid']) {
                $ca_arr = explode('_', $nv['nkey']);
                $menu_arr[$nv['id']] = array(
                    'id' => $nv['id'],
                    'pid' => $nv['pid'],
                    'name' => $nv['name'],
                    'c' => $ca_arr[0],
                    'a' => $ca_arr[1],
                    'nkey' => $nv['nkey'],
                    'ico' => $nv['ico'],
                    'public' => $nv['public'],
                    'url' => $nv['url']
                );
            } else {
                $ca_arr = explode('_', $nv['nkey']);
                $menu_arr[$nv['pid']]['sub_node'][] = array(
                    'id' => $nv['id'],
                    'pid' => $nv['pid'],
                    'name' => $nv['name'],
                    'nkey' => $nv['nkey'],
                    'c' => $ca_arr[0],
                    'a' => $ca_arr[1],
                    'ico' => $nv['ico'],
                    'public' => $nv['public'],
                    'url' => $nv['url']
                );
            }
        }
        $memcache->set($mem_key, $menu_arr, 3600);
    } else {
        //p($menu_arr);exit;
    }
    unset($mysql, $memcache);
    return $menu_arr;
}
