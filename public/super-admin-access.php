<?php
// 超级管理员访问入口 - 绕过租户识别，直接访问管理后台
$_SERVER['HTTP_HOST'] = 'admin.vpnall.com';
$_SERVER['HTTP_X_SUPER_ADMIN'] = 'true';
$_SERVER['REQUEST_URI'] = '/admin';

require_once __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/admin', 'GET');
$request->headers->set('X-Super-Admin', 'true');
$request->server->set('HTTP_HOST', 'admin.vpnall.com');

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

