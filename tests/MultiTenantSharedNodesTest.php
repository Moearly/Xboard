<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\Server;
use App\Models\User;
use App\Models\Plan;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class MultiTenantSharedNodesTest extends TestCase
{
    /**
     * 测试所有租户可以访问所有节点（共享模式）
     */
    public function test_all_tenants_can_access_all_servers()
    {
        $tenants = Tenant::all();
        $servers = Server::where('show', 1)->get();
        
        $this->assertGreaterThan(0, $tenants->count(), '至少应该有一个租户');
        $this->assertGreaterThan(0, $servers->count(), '至少应该有一个节点');
        
        foreach ($tenants as $tenant) {
            app()->instance('currentTenant', $tenant);
            
            $availableServers = $tenant->availableServers();
            
            $this->assertEquals(
                $servers->count(), 
                $availableServers->count(),
                "租户 {$tenant->name} 应该可以访问所有节点"
            );
        }
    }
    
    /**
     * 测试租户数据隔离
     */
    public function test_tenant_data_isolation()
    {
        $tenants = Tenant::take(2)->get();
        
        if ($tenants->count() < 2) {
            $this->markTestSkipped('需要至少2个租户来测试数据隔离');
        }
        
        $tenant1 = $tenants[0];
        $tenant2 = $tenants[1];
        
        // 检查用户隔离
        app()->instance('currentTenant', $tenant1);
        $tenant1Users = User::all();
        
        app()->instance('currentTenant', $tenant2);
        $tenant2Users = User::all();
        
        // 验证用户数据不会混淆
        foreach ($tenant1Users as $user) {
            $this->assertEquals($tenant1->id, $user->tenant_id);
        }
        
        foreach ($tenant2Users as $user) {
            $this->assertEquals($tenant2->id, $user->tenant_id);
        }
    }
    
    /**
     * 测试tenant_server表已被删除
     */
    public function test_tenant_server_table_removed()
    {
        $this->assertFalse(
            Schema::hasTable('tenant_server'),
            'tenant_server 表应该已经被删除（共享节点模式）'
        );
    }
    
    /**
     * 测试配置的共享和隔离
     */
    public function test_settings_shared_and_isolated()
    {
        $tenants = Tenant::take(2)->get();
        
        if ($tenants->count() < 2) {
            $this->markTestSkipped('需要至少2个租户来测试配置');
        }
        
        $tenant1 = $tenants[0];
        $tenant2 = $tenants[1];
        
        // 测试租户独立配置（app_name应该不同）
        app()->instance('currentTenant', $tenant1);
        $appName1 = admin_setting('app_name');
        
        app()->instance('currentTenant', $tenant2);
        $appName2 = admin_setting('app_name');
        
        // 两个租户的站点名称应该可以不同（独立配置）
        $this->assertNotEmpty($appName1);
        $this->assertNotEmpty($appName2);
        
        // 测试共享配置（server_token应该相同）
        app()->instance('currentTenant', $tenant1);
        $serverToken1 = admin_setting('server_token');
        
        app()->instance('currentTenant', $tenant2);
        $serverToken2 = admin_setting('server_token');
        
        if ($serverToken1 && $serverToken2) {
            $this->assertEquals(
                $serverToken1, 
                $serverToken2,
                'server_token 应该是共享配置，所有租户使用相同值'
            );
        }
    }
    
    /**
     * 测试租户统计数据
     */
    public function test_tenant_statistics()
    {
        $tenant = Tenant::first();
        $this->assertNotNull($tenant);
        
        $stats = $tenant->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('users_count', $stats);
        $this->assertArrayHasKey('orders_count', $stats);
        $this->assertArrayHasKey('plans_count', $stats);
        $this->assertArrayHasKey('nodes_count', $stats);
        
        // 节点数应该是所有显示节点的数量（共享）
        $expectedNodeCount = Server::where('show', 1)->count();
        $this->assertEquals($expectedNodeCount, $stats['nodes_count']);
    }
    
    /**
     * 测试租户中间件正确识别租户
     */
    public function test_tenant_middleware_identification()
    {
        $tenant = Tenant::where('status', true)->first();
        $this->assertNotNull($tenant);
        
        // 通过域名访问
        $response = $this->get('/', [
            'HTTP_HOST' => $tenant->domain
        ]);
        
        // 应该成功识别租户
        $this->assertNotEquals(404, $response->status());
        $this->assertNotEquals(403, $response->status());
    }
}

