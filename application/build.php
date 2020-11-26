<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [
    // 生成应用公共文件
    '__file__' => ['common.php', 'config.php', 'database.php'],

    // 定义demo模块的自动生成 （按照实际定义的文件名生成）
    'demo'     => [
        '__file__'   => ['common.php'],
        '__dir__'    => ['behavior', 'controller', 'model', 'view'],
        'controller' => ['Index', 'Test', 'UserType'],
        'model'      => ['User', 'UserType'],
        'view'       => ['index/index'],
    ],

    // 其他更多的模块定义
    'home'     => [
        '__file__'   => ['common.php'],
        '__dir__'    => ['behavior', 'controller', 'model', 'view'],
        'controller' => ['Base', 'Default', 'Finance', 'Jssdk', 'Login',
                        'Notify2', 'Notify', 'Order', 'Pay', 'Service',
                        'Skma', 'Test', 'Tg', 'User'],
        'model'      => [],
        'view'       => ['Default/index', 'Finance/balancelog', 'Finance/cash', 'Finance/hkuan', 'Finance/hkuanInfo',
                        'Finance/hkuanlog', 'Finance/pay', 'Finance/payInfo', 'Finance/paylog', 'Finance/yong',
                        'Login/forget', 'Login/login', 'Login/register', 'Order/index', 'Pay/alipay2', 'Pay/info',
                        'Pay/p11', 'Pay/p13', 'Pay/preAlipay', 'Pay/test', 'Service/online', 'Skma/index', 'Skma/info',
                        'Test/socket', 'Tg/index', 'User/api', 'User/bcard', 'User/google', 'User/index', 'User/password',
                        'User/password2', 'User/setting', 'User/team', 'User/teamInfo', 'foot', 'head', 'js', 'menu'],
    ],

    'admin'     => [
        '__file__'   => ['common.php'],
        '__dir__'    => ['behavior', 'controller', 'model', 'view'],
        'controller' => ['Base', 'Default', 'Finance', 'Login', 'News', 'Pay', 'Sys', 'Test', 'User'],
        'model'      => [],
        'view'       => ['Default/default', 'Default/index', 'Finance/agenthk', 'Finance/balance', 'Finance/balancelog',
                        'Finance/bank', 'Finance/banklog', 'Finance/bklog', 'Finance/cashlog', 'Finance/cashlog_ms',
                        'Finance/paylog', 'Login/index', 'News/arccat', 'News/arclist', 'News/ptype', 'Pay/mtype',
                        'Pay/order', 'Pay/skma', 'Pay/skmaTrash', 'Pay/yong', 'Sys/bset', 'Sys/cdata', 'Sys/log', 'Sys/node',
                        'Sys/oauth', 'Sys/safety', 'Sys/userinfo', 'User/agent', 'User/apikey', 'User/datatj', 'User/rate',
                        'User/rsaset', 'User/sjuser', 'User/user', 'User/userms'],
    ],
];
