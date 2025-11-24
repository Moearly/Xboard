<?php
// 租户1管理后台访问代理
$_SERVER['HTTP_HOST'] = 'tenant1.xboard.test';
$_SERVER['HTTP_X_TENANT_DOMAIN'] = 'tenant1.xboard.test';
$_SERVER['REQUEST_URI'] = '/admin';

require_once __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/admin', 'GET');
$request->headers->set('X-Tenant-Domain', 'tenant1.xboard.test');
$request->server->set('HTTP_HOST', 'tenant1.xboard.test');

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

