<?php

use think\Request;
use app\common\Mysql;
use app\common\MyMemcache;


//检查权限
function checkPower($nkey = '')
{
    $user = checkLogin();
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
        $nkey = Request::instance()->controller() . '_' . Request::instance()->action();
    }

    $mysql = new Mysql(0);
    $result = false;
    do {
        $node = $mysql->fetchRow("select id,public from sys_node where nkey='{$nkey}'");
        if (!$node) {
            break;
        }
        if ($node['public']) {
            $result = true;
            break;
        }
        $access_ids_arr = getAccessNode($user['id'], $mysql);
        if (!$access_ids_arr) {
            break;
        }
        if (!in_array($node['id'], $access_ids_arr)) {
            break;
        }
        $result = true;
    } while (0);

    $mysql->close();
    unset($mysql);

    $param_dump = [
        'nkey' => $nkey,
        'userId' => $user['id'],
        'userGid' => $user['gid'],
        'account' => $user['account'],
        'parentId' => $user['pid'],
        'result' => $result,
    ];
    debugLog("hasPower: " . var_export($param_dump, true));
    return $result;
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
                    'c' => strtolower($ca_arr[0]),
                    'a' => strtolower(count($ca_arr) > 1 ? $ca_arr[1] : ''),
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
                    'c' => strtolower($ca_arr[0]),
                    'a' => strtolower($ca_arr[1]),
                    'ico' => $nv['ico'],
                    'public' => $nv['public'],
                    'url' => $nv['url']
                );
            }
        }
        $memcache->set($mem_key, $menu_arr, 3600);
    }

    unset($memcache);
    return $menu_arr;
}

//获取某个用户拥有的权限节点
function getAccessNode($uid = 0, $mysql = null)
{
    if (!$uid) {
        return false;
    }
    $user = getUserinfo($uid, $mysql);
    if (!$user) {
        return false;
    }
    $memcache = new MyMemcache(0);
    $mem_key = $_ENV['CONFIG']['MEMCACHE']['PREFIX'] . 'access_ids_' . $uid;
    $access_ids_arr = $memcache->get($mem_key);
    if (!$access_ids_arr) {
        if (!$mysql) {
            $mysql = new Mysql(0);
        }
        $access = $mysql->fetchRows("select node_ids from sys_access where uid={$user['id']} or gid={$user['gid']}");
        foreach ($access as $acv) {
            if (!$acv['node_ids']) {
                continue;
            }
            $tmp_node_ids = explode(',', $acv['node_ids']);
            foreach ($tmp_node_ids as $tv) {
                $i_tv = intval($tv);
                if ($i_tv) {
                    $access_ids_arr[] = $i_tv;
                }
            }
        }
        if ($access_ids_arr) {
            $access_ids_arr = array_unique($access_ids_arr);
        }
        $memcache->set($mem_key, $access_ids_arr, 3600);
    }
    unset($memcache);
    return $access_ids_arr;
}

function debugLog($param)
{
    if (!$param) {
        return;
    }
    if (is_string($param)) {
        file_put_contents(ROOT_PATH . 'logs/test.txt', $param . "\n\n", FILE_APPEND);
    } else {
        file_put_contents(ROOT_PATH . 'logs/test.txt', var_export($param, true) . "\n\n", FILE_APPEND);
    }
}
