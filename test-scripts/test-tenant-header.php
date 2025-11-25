<?php

/**
 * 测试Tenant Header传递
 * 
 * 目的：验证X-Tenant-ID header是否能正确传递到Laravel应用
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// 获取一个admin用户的token
$adminUser = \Illuminate\Support\Facades\DB::table('v2_user')->where('is_admin', 1)->first();
if (!$adminUser || !$adminUser->token) {
    echo "❌ 错误：未找到admin用户或token\n";
    exit(1);
}

echo "使用Admin用户: {$adminUser->email} (ID: {$adminUser->id})\n\n";

// 创建模拟请求
$postData = ['app_name' => '接口测试-PHP脚本'];
$request = Illuminate\Http\Request::create(
    '/api/v2/admin/config/save',
    'POST',
    $postData, // POST data
    [],  // cookies
    [],  // files
    [    // server variables (headers)
        'HTTP_X_TENANT_ID' => '15',
        'HTTP_X_SUPER_ADMIN' => 'true',
        'HTTP_AUTHORIZATION' => 'Bearer ' . $adminUser->token,
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
    ]
);

// 手动设置请求内容（模拟POST body）
$request->headers->set('Content-Type', 'application/x-www-form-urlencoded');

echo "=== 测试开始 ===\n\n";
echo "请求URL: " . $request->getUri() . "\n";
echo "请求方法: " . $request->method() . "\n";
echo "X-Tenant-ID: " . $request->header('X-Tenant-ID') . "\n";
echo "X-Super-Admin: " . $request->header('X-Super-Admin') . "\n\n";

try {
    // 处理请求
    $response = $kernel->handle($request);
    
    // 检查currentTenant是否被设置
    echo "=== 中间件检查 ===\n";
    if (app()->has('currentTenant')) {
        $tenant = app('currentTenant');
        echo "✅ currentTenant已设置: ID={$tenant->id}, Name={$tenant->name}\n";
    } else {
        echo "❌ currentTenant未设置\n";
    }
    echo "\n";
    
    echo "=== 响应信息 ===\n";
    echo "状态码: " . $response->getStatusCode() . "\n";
    echo "响应内容:\n";
    
    $content = $response->getContent();
    $decoded = json_decode($content, true);
    
    if ($decoded) {
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        // 检查_debug信息
        if (isset($decoded['_debug'])) {
            echo "=== Debug信息 ===\n";
            echo "X-Tenant-ID header: " . ($decoded['_debug']['X-Tenant-ID_header'] ?? 'NULL') . "\n";
            echo "has currentTenant: " . ($decoded['_debug']['has_currentTenant'] ? 'YES' : 'NO') . "\n";
            echo "currentTenant ID: " . ($decoded['_debug']['currentTenant_id'] ?? 'NULL') . "\n";
        }
    } else {
        echo $content . "\n";
    }
    
    // 验证数据库
    echo "\n=== 数据库验证 ===\n";
    $setting = \Illuminate\Support\Facades\DB::table('v2_settings')
        ->where('name', 'app_name')
        ->orderBy('updated_at', 'desc')
        ->first();
    
    if ($setting) {
        echo "最新app_name配置:\n";
        echo "  tenant_id: {$setting->tenant_id}\n";
        echo "  value: {$setting->value}\n";
        echo "  updated_at: {$setting->updated_at}\n";
        
        if ($setting->tenant_id == 15 && $setting->value == '接口测试') {
            echo "\n✅✅✅ 成功！数据已正确保存到 tenant_id=15\n";
        } else {
            echo "\n❌ 失败！数据未正确隔离\n";
        }
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}

$kernel->terminate($request, $response);

