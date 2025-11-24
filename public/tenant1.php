<?php
// 设置租户域名头，然后重定向到主应用
$_SERVER['HTTP_HOST'] = 'tenant1.xboard.test';
$_SERVER['HTTP_X_TENANT_DOMAIN'] = 'tenant1.xboard.test';

// 加载Laravel应用
require_once __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::capture();
// 强制设置请求头
$request->headers->set('X-Tenant-Domain', 'tenant1.xboard.test');
$request->server->set('HTTP_HOST', 'tenant1.xboard.test');

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

