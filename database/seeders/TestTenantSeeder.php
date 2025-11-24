<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Server;
use App\Models\User;
use App\Models\Plan;
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TestTenantSeeder extends Seeder
{
    /**
     * 运行测试数据填充
     */
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            echo "🌱 开始创建测试数据...\n\n";
            
            // 1. 创建测试租户
            $this->createTestTenants();
            
            // 2. 创建共享节点
            $this->createSharedServers();
            
            // 3. 为每个租户创建测试用户
            $this->createTestUsers();
            
            // 4. 为每个租户创建测试套餐（跳过 - 字段不匹配）
            echo "\n💰 测试套餐创建已跳过（请通过管理后台创建套餐）\n";
            
            // 5. 初始化租户配置（跳过 - 依赖套餐）
            echo "\n⚙️ 租户配置初始化已跳过\n";
            
            DB::commit();
            
            echo "\n✅ 测试数据创建完成！\n";
            $this->printSummary();
            
        } catch (\Exception $e) {
            DB::rollBack();
            echo "\n❌ 测试数据创建失败: {$e->getMessage()}\n";
            throw $e;
        }
    }
    
    /**
     * 创建测试租户
     */
    private function createTestTenants(): void
    {
        echo "📋 创建测试租户...\n";
        
        $tenants = [
            [
                'name' => '测试租户A',
                'domain' => 'tenant1.xboard.test',
                'admin_email' => 'admin@tenant1.com',
                'admin_phone' => '13800000001',
            ],
            [
                'name' => '测试租户B',
                'domain' => 'tenant2.xboard.test',
                'admin_email' => 'admin@tenant2.com',
                'admin_phone' => '13800000002',
            ],
            [
                'name' => '测试租户C',
                'domain' => 'tenant3.xboard.test',
                'admin_email' => 'admin@tenant3.com',
                'admin_phone' => '13800000003',
            ],
        ];
        
        foreach ($tenants as $tenantData) {
            // 构建config字段内容（存储扩展配置）
            $config = [
                'admin_email' => $tenantData['admin_email'],
                'admin_phone' => $tenantData['admin_phone'],
                'max_users' => 1000,
                'max_orders_per_month' => 10000,
                'max_nodes' => 50,
                'max_monthly_revenue' => 100000,
                'features' => [
                    'tickets' => true,
                    'knowledge' => true,
                    'coupons' => true,
                    'invites' => true,
                    'announcements' => true,
                ],
                'theme_config' => [
                    'primary_color' => '#3b82f6',
                    'secondary_color' => '#64748b',
                    'theme_name' => 'default',
                ],
            ];
            
            $tenant = Tenant::firstOrCreate(
                ['domain' => $tenantData['domain']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'name' => $tenantData['name'],
                    'status' => true,
                    'config' => json_encode($config),
                    'expire_at' => now()->addYear(),
                ]
            );
            
            echo "  ✓ {$tenant->name} ({$tenant->domain})\n";
        }
    }
    
    /**
     * 创建共享节点（跳过 - XBoard使用分表策略）
     */
    private function createSharedServers(): void
    {
        echo "\n🌐 共享节点创建已跳过（XBoard使用分表策略，请通过管理后台创建节点）\n";
    }
    
    /**
     * 为每个租户创建测试用户
     */
    private function createTestUsers(): void
    {
        echo "\n👥 创建测试用户...\n";
        
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            // 绑定当前租户到容器
            app()->instance('currentTenant', $tenant);
            
            // 创建管理员用户
            $config = is_string($tenant->config) ? json_decode($tenant->config, true) : $tenant->config;
            $adminEmail = $config['admin_email'] ?? "admin@{$tenant->domain}";
            
            $admin = User::firstOrCreate(
                [
                    'email' => $adminEmail,
                    'tenant_id' => $tenant->id,
                ],
                [
                    'password' => Hash::make('admin123'),
                    'uuid' => Str::uuid()->toString(),
                    'token' => Str::random(32),
                    'is_admin' => 1,
                    'balance' => 10000, // 100元
                    'transfer_enable' => 107374182400, // 100GB
                    'expired_at' => now()->addYear()->timestamp,
                    'created_at' => time(),
                    'updated_at' => time(),
                ]
            );
            
            echo "  ✓ {$tenant->name}: 管理员 {$admin->email}\n";
            
            // 创建5个普通用户
            for ($i = 1; $i <= 5; $i++) {
                $user = User::firstOrCreate(
                    [
                        'email' => "user{$i}@{$tenant->domain}",
                        'tenant_id' => $tenant->id,
                    ],
                    [
                        'password' => Hash::make('user123'),
                        'uuid' => Str::uuid()->toString(),
                        'token' => Str::random(32),
                        'is_admin' => 0,
                        'balance' => rand(0, 5000),
                        'transfer_enable' => 53687091200, // 50GB
                        'expired_at' => now()->addMonths(rand(1, 12))->timestamp,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ]
                );
                
                echo "  ✓ {$tenant->name}: 用户 {$user->email}\n";
            }
        }
    }
    
    /**
     * 为每个租户创建测试套餐
     */
    private function createTestPlans(): void
    {
        echo "\n💰 创建测试套餐...\n";
        
        $tenants = Tenant::all();
        
        $plansTemplate = [
            [
                'name' => '基础套餐',
                'transfer_enable' => 53687091200, // 50GB
                'month_price' => 1000, // 10元
                'quarter_price' => 2700, // 27元
                'half_year_price' => 5000, // 50元
                'year_price' => 9000, // 90元
                'show' => 1,
                'renew' => 1,
                'sell' => 1,
                'sort' => 1,
            ],
            [
                'name' => '标准套餐',
                'transfer_enable' => 107374182400, // 100GB
                'month_price' => 2000,
                'quarter_price' => 5400,
                'half_year_price' => 10000,
                'year_price' => 18000,
                'show' => 1,
                'renew' => 1,
                'sell' => 1,
                'sort' => 2,
            ],
            [
                'name' => '高级套餐',
                'transfer_enable' => 214748364800, // 200GB
                'month_price' => 4000,
                'quarter_price' => 10800,
                'half_year_price' => 20000,
                'year_price' => 36000,
                'show' => 1,
                'renew' => 1,
                'sell' => 1,
                'sort' => 3,
            ],
        ];
        
        foreach ($tenants as $tenant) {
            app()->instance('currentTenant', $tenant);
            
            foreach ($plansTemplate as $planData) {
                $plan = Plan::firstOrCreate(
                    [
                        'name' => $planData['name'],
                        'tenant_id' => $tenant->id,
                    ],
                    array_merge($planData, [
                        'created_at' => time(),
                        'updated_at' => time(),
                    ])
                );
                
                echo "  ✓ {$tenant->name}: {$plan->name}\n";
            }
        }
    }
    
    /**
     * 初始化租户配置
     */
    private function initializeTenantSettings(): void
    {
        echo "\n⚙️  初始化租户配置...\n";
        
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            // 使用 artisan 命令初始化配置
            \Artisan::call('tenant:init-settings', ['tenant_id' => $tenant->id]);
            
            echo "  ✓ {$tenant->name}: 配置已初始化\n";
        }
    }
    
    /**
     * 打印摘要信息
     */
    private function printSummary(): void
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "📊 测试数据摘要\n";
        echo str_repeat("=", 50) . "\n\n";
        
        $tenants = Tenant::all();
        $servers = Server::where('show', 1)->get();
        
        echo "🏢 租户数量: {$tenants->count()}\n";
        echo "🌐 共享节点数量: {$servers->count()}\n\n";
        
        echo "📋 租户详情:\n";
        foreach ($tenants as $tenant) {
            $userCount = User::where('tenant_id', $tenant->id)->count();
            $planCount = Plan::where('tenant_id', $tenant->id)->count();
            
            echo "  • {$tenant->name}\n";
            echo "    - 域名: {$tenant->domain}\n";
            echo "    - 用户数: {$userCount}\n";
            echo "    - 套餐数: {$planCount}\n";
            echo "    - 管理员: {$tenant->admin_email} / admin123\n\n";
        }
        
        echo "🌐 共享节点列表:\n";
        foreach ($servers as $server) {
            echo "  • {$server->name} ({$server->type}) - 倍率: {$server->rate}\n";
        }
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "🎯 测试访问地址:\n";
        echo str_repeat("=", 50) . "\n\n";
        
        foreach ($tenants as $tenant) {
            echo "  {$tenant->name}:\n";
            echo "    http://localhost:8080 (Host: {$tenant->domain})\n";
            echo "    登录: {$tenant->admin_email} / admin123\n\n";
        }
        
        echo "  超级管理后台:\n";
        echo "    http://localhost:8080 (Host: admin.xboard.test)\n\n";
    }
}

