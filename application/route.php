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

use think\Route;

Route::rule('admin/index', 'admin/Index/index');
Route::rule('admin/login', 'admin/Login/index');
Route::rule('admin/login/captcha', 'admin/Login/captcha');
Route::rule('admin/login/loginact', 'admin/Login/loginAct');
Route::rule('admin/login/logoutact', 'admin/Login/logoutAct');

Route::rule('admin/user/agentlist', 'admin/User/agentlist');



