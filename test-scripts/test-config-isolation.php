<?php

// 多租户配置隔离 - 完整测试脚本

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 多租户配置隔离 - 完整测试\n";
echo str_repeat("=", 60) . "\n\n";

// 步骤1: 创建测试租户
echo "📋 步骤1: 创建测试租户\n";
$tenantA = \App\Models\Tenant::firstOrCreate(
    ['domain' => 'tenant-a.test.local'],
    ['name' => '测试租户A', 'status' => true]
);
$tenantB = \App\Models\Tenant::firstOrCreate(
    ['domain' => 'tenant-b.test.local'],
    ['name' => '测试租户B', 'status' => true]
);
echo "✅ 租户A (ID: {$tenantA->id}): {$tenantA->name}\n";
echo "✅ 租户B (ID: {$tenantB->id}): {$tenantB->name}\n\n";

// 步骤2: 租户A设置配置
echo "📝 步骤2: 租户A设置配置\n";
app()->forgetInstance('currentTenant');
app()->singleton('currentTenant', fn() => $tenantA);

admin_setting([
    'app_name' => 'VPN Service A',
    'app_description' => 'This is Tenant A',
    'app_url' => 'https://tenant-a.com'
]);

$configA = [
    'app_name' => admin_setting('app_name'),
    'app_description' => admin_setting('app_description'),
    'app_url' => admin_setting('app_url'),
];
echo "  站点名称: {$configA['app_name']}\n";
echo "  站点描述: {$configA['app_description']}\n";
echo "  站点URL: {$configA['app_url']}\n";

$testA1 = ($configA['app_name'] === 'VPN Service A' 
    && $configA['app_description'] === 'This is Tenant A'
    && $configA['app_url'] === 'https://tenant-a.com');
echo ($testA1 ? "  ✅ 租户A配置设置成功\n\n" : "  ❌ 租户A配置设置失败\n\n");

// 步骤3: 租户B设置配置  
echo "📝 步骤3: 租户B设置配置\n";
app()->forgetInstance('currentTenant');
app()->singleton('currentTenant', fn() => $tenantB);

admin_setting([
    'app_name' => 'VPN Service B',
    'app_description' => 'This is Tenant B',
    'app_url' => 'https://tenant-b.com'
]);

$configB = [
    'app_name' => admin_setting('app_name'),
    'app_description' => admin_setting('app_description'),
    'app_url' => admin_setting('app_url'),
];
echo "  站点名称: {$configB['app_name']}\n";
echo "  站点描述: {$configB['app_description']}\n";
echo "  站点URL: {$configB['app_url']}\n";

$testB1 = ($configB['app_name'] === 'VPN Service B'
    && $configB['app_description'] === 'This is Tenant B'
    && $configB['app_url'] === 'https://tenant-b.com');
echo ($testB1 ? "  ✅ 租户B配置设置成功\n\n" : "  ❌ 租户B配置设置失败\n\n");

// 步骤4: 验证租户A配置未被影响
echo "📝 步骤4: 验证租户A配置隔离\n";
app()->forgetInstance('currentTenant');
app()->singleton('currentTenant', fn() => $tenantA);

$verifyA = [
    'app_name' => admin_setting('app_name'),
    'app_description' => admin_setting('app_description'),
    'app_url' => admin_setting('app_url'),
];
echo "  站点名称: {$verifyA['app_name']}\n";
echo "  站点描述: {$verifyA['app_description']}\n";
echo "  站点URL: {$verifyA['app_url']}\n";

$testA2 = ($verifyA['app_name'] === 'VPN Service A'
    && $verifyA['app_description'] === 'This is Tenant A'
    && $verifyA['app_url'] === 'https://tenant-a.com');
echo ($testA2 ? "  ✅ 租户A配置未被污染\n\n" : "  ❌ 租户A配置被污染\n\n");

// 步骤5: 验证租户B配置未被影响
echo "📝 步骤5: 验证租户B配置隔离\n";
app()->forgetInstance('currentTenant');
app()->singleton('currentTenant', fn() => $tenantB);

$verifyB = [
    'app_name' => admin_setting('app_name'),
    'app_description' => admin_setting('app_description'),
    'app_url' => admin_setting('app_url'),
];
echo "  站点名称: {$verifyB['app_name']}\n";
echo "  站点描述: {$verifyB['app_description']}\n";
echo "  站点URL: {$verifyB['app_url']}\n";

$testB2 = ($verifyB['app_name'] === 'VPN Service B'
    && $verifyB['app_description'] === 'This is Tenant B'
    && $verifyB['app_url'] === 'https://tenant-b.com');
echo ($testB2 ? "  ✅ 租户B配置未被污染\n\n" : "  ❌ 租户B配置被污染\n\n");

// 步骤6: 检查数据库记录
echo "📝 步骤6: 检查数据库记录\n";
$dbSettingsA = \App\Models\Setting::where('tenant_id', $tenantA->id)
    ->whereIn('name', ['app_name', 'app_description', 'app_url'])
    ->get()
    ->pluck('value', 'name');
    
$dbSettingsB = \App\Models\Setting::where('tenant_id', $tenantB->id)
    ->whereIn('name', ['app_name', 'app_description', 'app_url'])
    ->get()
    ->pluck('value', 'name');

echo "  租户A数据库记录:\n";
foreach($dbSettingsA as $key => $value) {
    echo "    - {$key}: {$value}\n";
}

echo "  租户B数据库记录:\n";
foreach($dbSettingsB as $key => $value) {
    echo "    - {$key}: {$value}\n";
}

$testDB = ($dbSettingsA['app_name'] === 'VPN Service A' 
    && $dbSettingsB['app_name'] === 'VPN Service B');
echo ($testDB ? "  ✅ 数据库记录正确\n\n" : "  ❌ 数据库记录错误\n\n");

// 步骤7: 统计总结
echo str_repeat("=", 60) . "\n";
echo "🎯 测试总结\n\n";

$allTests = [
    '租户A配置设置' => $testA1,
    '租户B配置设置' => $testB1,
    '租户A配置隔离' => $testA2,
    '租户B配置隔离' => $testB2,
    '数据库记录正确' => $testDB,
];

$passedCount = 0;
foreach($allTests as $name => $result) {
    echo ($result ? "  ✅ " : "  ❌ ") . $name . "\n";
    if($result) $passedCount++;
}

echo "\n";
echo "通过: {$passedCount}/" . count($allTests) . " 项测试\n";
echo str_repeat("=", 60) . "\n";

if($passedCount === count($allTests)) {
    echo "\n🎉 所有测试通过！多租户配置隔离功能正常工作！\n\n";
    exit(0);
} else {
    echo "\n❌ 部分测试失败，请检查实现\n\n";
    exit(1);
}

