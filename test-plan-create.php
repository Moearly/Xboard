<?php

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 设置当前租户
$tenant = \App\Models\Tenant::find(13); // 测试租户A
app()->instance('currentTenant', $tenant);

echo "当前租户: " . ($tenant ? $tenant->name : 'NULL') . " (ID: " . ($tenant ? $tenant->id : 'NULL') . ")\n";

// 模拟创建套餐请求
$data = [
    'name' => '测试租户A专属套餐',
    'content' => '',
    'tags' => [],
    'transfer_enable' => 100,
    'prices' => [
        'monthly' => 88
    ],
    'group_id' => null,
    'speed_limit' => null,
    'device_limit' => null,
    'capacity_limit' => null,
    'reset_traffic_method' => 0,
    'show' => 1,
    'renew' => 1,
];

echo "提交数据:\n";
print_r($data);

try {
    // 尝试创建套餐
    DB::beginTransaction();
    
    $plan = \App\Models\Plan::create($data);
    
    echo "\n✅ 套餐创建成功!\n";
    echo "套餐ID: " . $plan->id . "\n";
    echo "租户ID: " . ($plan->tenant_id ?? 'NULL') . "\n";
    echo "套餐名称: " . $plan->name . "\n";
    
    DB::commit();
    
    // 验证数据隔离
    echo "\n验证数据隔离:\n";
    
    // 查询租户A的套餐
    app()->instance('currentTenant', $tenant);
    $tenantPlans = \App\Models\Plan::all();
    echo "租户A的套餐数量: " . $tenantPlans->count() . "\n";
    
    // 查询所有套餐（超级管理员模式）
    app()->forgetInstance('currentTenant');
    $allPlans = \App\Models\Plan::withoutGlobalScope(\App\Scopes\TenantScope::class)->get();
    echo "所有套餐数量: " . $allPlans->count() . "\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ 创建失败:\n";
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n堆栈跟踪:\n";
    echo $e->getTraceAsString() . "\n";
}

