<?php
// 租户3访问代理
$_SERVER['HTTP_HOST'] = 'tenant3.xboard.test';
$_SERVER['HTTP_X_TENANT_DOMAIN'] = 'tenant3.xboard.test';

require_once __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::capture();
$request->headers->set('X-Tenant-Domain', 'tenant3.xboard.test');
$request->server->set('HTTP_HOST', 'tenant3.xboard.test');

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

