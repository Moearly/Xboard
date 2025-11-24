<?php
// 超级管理员访问代理
$_SERVER['HTTP_HOST'] = 'admin.vpnall.com';
$_SERVER['HTTP_X_SUPER_ADMIN'] = 'true';

require_once __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::capture();
$request->headers->set('X-Super-Admin', 'true');
$request->server->set('HTTP_HOST', 'admin.vpnall.com');

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

