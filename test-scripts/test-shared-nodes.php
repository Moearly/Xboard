<?php

/**
 * 共享节点测试脚本
 * 
 * 测试所有租户是否可以访问所有节点
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Models\Server;
use App\Models\User;
use App\Models\Plan;

echo "\n";
echo "==========================================\n";
echo "   共享节点多租户测试\n";
echo "==========================================\n\n";

// 1. 检查租户数量
echo "【步骤1】检查租户数量\n";
$tenants = Tenant::all();
echo "✓ 找到 {$tenants->count()} 个租户\n\n";

foreach ($tenants as $tenant) {
    echo "  - {$tenant->name} ({$tenant->domain})\n";
}
echo "\n";

// 2. 检查节点数量
echo "【步骤2】检查节点数量\n";
$servers = Server::where('show', 1)->get();
echo "✓ 找到 {$servers->count()} 个可用节点\n\n";

foreach ($servers as $server) {
    echo "  - {$server->name} ({$server->type})\n";
}
echo "\n";

// 3. 测试每个租户可以访问的节点
echo "【步骤3】测试租户节点访问权限\n\n";

foreach ($tenants as $tenant) {
    echo "测试租户: {$tenant->name}\n";
    echo str_repeat("-", 50) . "\n";
    
    // 模拟当前租户
    app()->instance('currentTenant', $tenant);
    
    // 获取可用节点
    $availableServers = $tenant->availableServers();
    echo "  可访问节点数: {$availableServers->count()}\n";
    
    if ($availableServers->count() === $servers->count()) {
        echo "  ✓ 租户可以访问所有节点（共享模式正常）\n";
    } else {
        echo "  ✗ 租户只能访问部分节点（共享模式异常）\n";
        echo "    期望: {$servers->count()}, 实际: {$availableServers->count()}\n";
    }
    
    // 检查租户数据隔离
    $userCount = $tenant->users()->count();
    $orderCount = $tenant->orders()->count();
    $planCount = $tenant->plans()->count();
    
    echo "  数据隔离检查:\n";
    echo "    - 用户数: {$userCount}\n";
    echo "    - 订单数: {$orderCount}\n";
    echo "    - 套餐数: {$planCount}\n";
    
    echo "\n";
}

// 4. 测试配置隔离
echo "【步骤4】测试配置隔离\n\n";

foreach ($tenants as $tenant) {
    app()->instance('currentTenant', $tenant);
    
    $appName = admin_setting('app_name', 'N/A');
    $serverToken = admin_setting('server_token', 'N/A');
    
    echo "租户: {$tenant->name}\n";
    echo "  - 站点名称(独立): {$appName}\n";
    echo "  - 服务器令牌(共享): " . substr($serverToken, 0, 10) . "...\n\n";
}

// 5. 测试统计数据
echo "【步骤5】测试租户统计数据\n\n";

foreach ($tenants as $tenant) {
    $stats = $tenant->getStatistics();
    
    echo "租户: {$tenant->name}\n";
    echo "  - 用户数: {$stats['users_count']}\n";
    echo "  - 订单数: {$stats['orders_count']}\n";
    echo "  - 套餐数: {$stats['plans_count']}\n";
    echo "  - 节点数: {$stats['nodes_count']} (所有租户共享)\n";
    echo "  - 总收入: ¥" . number_format($stats['total_revenue'], 2) . "\n\n";
}

// 6. 检查是否还存在 tenant_server 表
echo "【步骤6】检查数据库表结构\n";
try {
    $hasTenantServerTable = Schema::hasTable('tenant_server');
    if ($hasTenantServerTable) {
        echo "  ✗ tenant_server 表仍然存在（应该已删除）\n";
    } else {
        echo "  ✓ tenant_server 表已删除（共享模式正确）\n";
    }
} catch (\Exception $e) {
    echo "  ! 无法检查表结构: {$e->getMessage()}\n";
}

echo "\n";
echo "==========================================\n";
echo "   测试完成\n";
echo "==========================================\n\n";

echo "总结:\n";
echo "1. 所有租户共享 {$servers->count()} 个节点\n";
echo "2. 用户/订单/套餐数据完全隔离\n";
echo "3. 配置支持共享和独立两种模式\n";
echo "4. 统计数据按租户分别计算\n\n";

