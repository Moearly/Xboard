<?php

/**
 * 租户全局作用域测试脚本
 * 
 * 测试目的：
 * 1. 验证模型的全局作用域是否正常工作
 * 2. 验证Service层是否自动继承租户隔离
 * 3. 验证跨租户访问是否被正确阻止
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Models\Plan;
use App\Models\User;
use App\Models\Order;
use App\Models\Coupon;
use App\Models\Notice;
use App\Models\Knowledge;
use App\Models\Ticket;
use App\Services\PlanService;
use App\Services\UserService;
use App\Scopes\TenantScope;

class TenantScopeTest
{
    private $results = [];
    
    public function run()
    {
        echo "\n";
        echo "========================================\n";
        echo "   租户全局作用域测试\n";
        echo "========================================\n\n";
        
        // 1. 检查租户是否存在
        $this->testTenantsExist();
        
        // 2. 测试模型层全局作用域
        $this->testModelGlobalScope();
        
        // 3. 测试跨租户访问保护
        $this->testCrossTenantAccess();
        
        // 4. 测试Service层自动隔离
        $this->testServiceLayerIsolation();
        
        // 5. 显示测试结果
        $this->displayResults();
    }
    
    private function testTenantsExist()
    {
        echo "📋 测试1：检查租户数据\n";
        echo "----------------------------------------\n";
        
        $tenants = Tenant::all();
        $count = $tenants->count();
        
        if ($count > 0) {
            echo "✅ 找到 {$count} 个租户\n";
            foreach ($tenants as $tenant) {
                echo "   - 租户 #{$tenant->id}: {$tenant->name} ({$tenant->domain})\n";
            }
            $this->recordResult('tenants_exist', true, "找到 {$count} 个租户");
        } else {
            echo "❌ 未找到任何租户\n";
            echo "   提示：请先创建租户数据\n";
            $this->recordResult('tenants_exist', false, "未找到租户");
        }
        
        echo "\n";
    }
    
    private function testModelGlobalScope()
    {
        echo "📋 测试2：模型层全局作用域\n";
        echo "----------------------------------------\n";
        
        $tenant1 = Tenant::first();
        if (!$tenant1) {
            echo "❌ 无法进行测试：需要至少1个租户\n\n";
            return;
        }
        
        // 设置当前租户
        app()->instance('currentTenant', $tenant1);
        echo "🔧 设置当前租户：#{$tenant1->id} - {$tenant1->name}\n\n";
        
        // 测试各个模型
        $models = [
            'Plan' => Plan::class,
            'User' => User::class,
            'Order' => Order::class,
            'Coupon' => Coupon::class,
            'Notice' => Notice::class,
            'Knowledge' => Knowledge::class,
            'Ticket' => Ticket::class,
        ];
        
        foreach ($models as $name => $class) {
            $this->testSingleModel($name, $class, $tenant1->id);
        }
        
        echo "\n";
    }
    
    private function testSingleModel($name, $class, $tenantId)
    {
        try {
            // 检查模型是否使用了 BelongsToTenant trait
            $traits = class_uses_recursive($class);
            $hasTrait = in_array('App\Models\Traits\BelongsToTenant', $traits);
            
            echo "🔍 测试 {$name} 模型\n";
            echo "   - Trait: " . ($hasTrait ? "✅ BelongsToTenant" : "❌ 未使用") . "\n";
            
            if (!$hasTrait) {
                $this->recordResult("{$name}_global_scope", false, "未使用 BelongsToTenant trait");
                echo "\n";
                return;
            }
            
            // 获取SQL查询
            $query = $class::query();
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            
            echo "   - SQL: {$sql}\n";
            echo "   - 绑定值: " . json_encode($bindings) . "\n";
            
            // 检查是否包含 tenant_id 过滤
            if (strpos($sql, 'tenant_id') !== false) {
                echo "   - 全局作用域: ✅ 已应用（包含 tenant_id 过滤）\n";
                
                // 测试查询
                $count = $query->count();
                echo "   - 查询结果: {$count} 条记录（租户 #{$tenantId}）\n";
                
                $this->recordResult("{$name}_global_scope", true, "全局作用域正常工作");
            } else {
                echo "   - 全局作用域: ⚠️ 未应用（SQL中无 tenant_id）\n";
                $this->recordResult("{$name}_global_scope", false, "SQL中未包含 tenant_id 过滤");
            }
            
        } catch (\Exception $e) {
            echo "   - 错误: ❌ {$e->getMessage()}\n";
            $this->recordResult("{$name}_global_scope", false, $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function testCrossTenantAccess()
    {
        echo "📋 测试3：跨租户访问保护\n";
        echo "----------------------------------------\n";
        
        $tenant1 = Tenant::skip(0)->first();
        $tenant2 = Tenant::skip(1)->first();
        
        if (!$tenant1 || !$tenant2) {
            echo "❌ 无法进行测试：需要至少2个租户\n\n";
            $this->recordResult('cross_tenant_access', false, "租户数量不足");
            return;
        }
        
        echo "🔧 租户1：#{$tenant1->id} - {$tenant1->name}\n";
        echo "🔧 租户2：#{$tenant2->id} - {$tenant2->name}\n\n";
        
        // 设置租户1
        app()->forgetInstance('currentTenant');
        app()->instance('currentTenant', $tenant1);
        
        // 获取租户1的套餐
        $tenant1Plans = Plan::all();
        $tenant1PlanIds = $tenant1Plans->pluck('id')->toArray();
        echo "📦 租户1的套餐: " . count($tenant1PlanIds) . " 个 (IDs: " . implode(', ', $tenant1PlanIds) . ")\n";
        
        // 切换到租户2
        app()->forgetInstance('currentTenant');
        app()->instance('currentTenant', $tenant2);
        
        // 尝试访问租户1的套餐
        if (!empty($tenant1PlanIds)) {
            $firstPlanId = $tenant1PlanIds[0];
            $plan = Plan::find($firstPlanId);
            
            if ($plan === null) {
                echo "✅ 跨租户访问保护：成功阻止访问租户1的套餐 #{$firstPlanId}\n";
                $this->recordResult('cross_tenant_access', true, "跨租户访问被正确阻止");
            } else {
                echo "❌ 跨租户访问保护：失败！能够访问租户1的套餐 #{$firstPlanId}\n";
                echo "   警告：存在安全隐患！\n";
                $this->recordResult('cross_tenant_access', false, "跨租户访问未被阻止");
            }
        } else {
            echo "⚠️  无法测试：租户1没有套餐数据\n";
            $this->recordResult('cross_tenant_access', null, "无测试数据");
        }
        
        // 获取租户2的套餐
        $tenant2Plans = Plan::all();
        $tenant2PlanIds = $tenant2Plans->pluck('id')->toArray();
        echo "📦 租户2的套餐: " . count($tenant2PlanIds) . " 个 (IDs: " . implode(', ', $tenant2PlanIds) . ")\n";
        
        echo "\n";
    }
    
    private function testServiceLayerIsolation()
    {
        echo "📋 测试4：Service层自动隔离\n";
        echo "----------------------------------------\n";
        
        $tenant1 = Tenant::first();
        if (!$tenant1) {
            echo "❌ 无法进行测试：需要至少1个租户\n\n";
            return;
        }
        
        // 设置当前租户
        app()->forgetInstance('currentTenant');
        app()->instance('currentTenant', $tenant1);
        echo "🔧 设置当前租户：#{$tenant1->id} - {$tenant1->name}\n\n";
        
        // 测试 UserService
        try {
            $userService = new UserService();
            $users = $userService->getAllUsers();
            echo "✅ UserService::getAllUsers() - 返回 " . $users->count() . " 个用户\n";
            
            if ($users->count() > 0) {
                $allBelongToTenant = $users->every(fn($user) => $user->tenant_id == $tenant1->id);
                if ($allBelongToTenant) {
                    echo "   ✅ 所有用户都属于租户 #{$tenant1->id}\n";
                    $this->recordResult('service_user', true, "UserService 正确隔离");
                } else {
                    echo "   ❌ 存在其他租户的用户！\n";
                    $this->recordResult('service_user', false, "UserService 隔离失败");
                }
            }
        } catch (\Exception $e) {
            echo "❌ UserService 测试失败: {$e->getMessage()}\n";
            $this->recordResult('service_user', false, $e->getMessage());
        }
        
        echo "\n";
        
        // 测试 PlanService
        try {
            $plan = Plan::first();
            if ($plan) {
                $planService = new PlanService($plan);
                $availablePlans = $planService->getAvailablePlans();
                echo "✅ PlanService::getAvailablePlans() - 返回 " . $availablePlans->count() . " 个套餐\n";
                
                if ($availablePlans->count() > 0) {
                    $allBelongToTenant = $availablePlans->every(fn($p) => $p->tenant_id == $tenant1->id);
                    if ($allBelongToTenant) {
                        echo "   ✅ 所有套餐都属于租户 #{$tenant1->id}\n";
                        $this->recordResult('service_plan', true, "PlanService 正确隔离");
                    } else {
                        echo "   ❌ 存在其他租户的套餐！\n";
                        $this->recordResult('service_plan', false, "PlanService 隔离失败");
                    }
                }
            } else {
                echo "⚠️  无法测试 PlanService：没有套餐数据\n";
                $this->recordResult('service_plan', null, "无测试数据");
            }
        } catch (\Exception $e) {
            echo "❌ PlanService 测试失败: {$e->getMessage()}\n";
            $this->recordResult('service_plan', false, $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function recordResult($test, $passed, $message)
    {
        $this->results[] = [
            'test' => $test,
            'passed' => $passed,
            'message' => $message
        ];
    }
    
    private function displayResults()
    {
        echo "========================================\n";
        echo "   测试结果汇总\n";
        echo "========================================\n\n";
        
        $total = count($this->results);
        $passed = count(array_filter($this->results, fn($r) => $r['passed'] === true));
        $failed = count(array_filter($this->results, fn($r) => $r['passed'] === false));
        $skipped = count(array_filter($this->results, fn($r) => $r['passed'] === null));
        
        echo "📊 总计：{$total} 项测试\n";
        echo "   ✅ 通过：{$passed}\n";
        echo "   ❌ 失败：{$failed}\n";
        echo "   ⚠️  跳过：{$skipped}\n\n";
        
        if ($failed > 0) {
            echo "❌ 失败的测试:\n";
            foreach ($this->results as $result) {
                if ($result['passed'] === false) {
                    echo "   - {$result['test']}: {$result['message']}\n";
                }
            }
            echo "\n";
        }
        
        $successRate = $total > 0 ? round(($passed / $total) * 100, 2) : 0;
        echo "📈 成功率：{$successRate}%\n\n";
        
        if ($successRate >= 80) {
            echo "🎉 结论：Service层租户隔离基本正常！\n";
        } elseif ($successRate >= 50) {
            echo "⚠️  结论：Service层租户隔离部分正常，需要修复失败项\n";
        } else {
            echo "❌ 结论：Service层租户隔离存在严重问题！\n";
        }
        
        echo "\n";
    }
}

// 运行测试
$test = new TenantScopeTest();
$test->run();

