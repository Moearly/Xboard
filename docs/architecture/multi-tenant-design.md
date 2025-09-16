# Xboard 多租户架构改造方案

## 1. 架构概述

将 Xboard 改造为支持多租户的 SaaS 平台，实现：
- **多域名支持**：每个域名对应独立的前端界面
- **用户隔离**：各租户用户体系完全独立
- **统一管理**：共用一个超级管理后台
- **资源共享**：所有租户共享节点服务器池

## 2. 数据库架构设计

### 2.1 新增核心表

```sql
-- 租户表
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL COMMENT '租户唯一标识',
  `name` varchar(255) NOT NULL COMMENT '租户名称',
  `domain` varchar(255) NOT NULL COMMENT '主域名',
  `subdomain` varchar(255) DEFAULT NULL COMMENT '子域名',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1启用 0禁用',
  `config` json DEFAULT NULL COMMENT '租户配置',
  `theme_config` json DEFAULT NULL COMMENT '主题配置',
  `payment_config` json DEFAULT NULL COMMENT '支付配置',
  `email_config` json DEFAULT NULL COMMENT '邮件配置',
  `expire_at` timestamp NULL DEFAULT NULL COMMENT '过期时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_uuid_unique` (`uuid`),
  UNIQUE KEY `tenants_domain_unique` (`domain`),
  KEY `tenants_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 租户域名映射表
CREATE TABLE `tenant_domains` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `domain` varchar(255) NOT NULL COMMENT '域名',
  `type` enum('primary','alias') DEFAULT 'alias' COMMENT '域名类型',
  `ssl_cert` text COMMENT 'SSL证书',
  `ssl_key` text COMMENT 'SSL私钥',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_domains_tenant_id_foreign` (`tenant_id`),
  UNIQUE KEY `tenant_domains_domain_unique` (`domain`),
  CONSTRAINT `tenant_domains_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 租户管理员表
CREATE TABLE `tenant_admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `role` enum('owner','admin','operator') DEFAULT 'admin',
  `permissions` json DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_admins_tenant_id_foreign` (`tenant_id`),
  UNIQUE KEY `tenant_admins_email_tenant_unique` (`email`,`tenant_id`),
  CONSTRAINT `tenant_admins_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 租户统计表
CREATE TABLE `tenant_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `user_count` int(11) DEFAULT '0' COMMENT '用户数',
  `active_users` int(11) DEFAULT '0' COMMENT '活跃用户数',
  `order_count` int(11) DEFAULT '0' COMMENT '订单数',
  `order_amount` decimal(10,2) DEFAULT '0.00' COMMENT '订单金额',
  `traffic_used` bigint(20) DEFAULT '0' COMMENT '流量使用(字节)',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_stats_tenant_id_date_index` (`tenant_id`,`date`),
  CONSTRAINT `tenant_stats_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 修改现有表结构

```sql
-- 为所有业务表添加 tenant_id 字段
ALTER TABLE `users` ADD COLUMN `tenant_id` bigint(20) unsigned NOT NULL AFTER `id`;
ALTER TABLE `users` ADD INDEX `users_tenant_id_index` (`tenant_id`);

ALTER TABLE `plans` ADD COLUMN `tenant_id` bigint(20) unsigned DEFAULT NULL AFTER `id`;
ALTER TABLE `plans` ADD INDEX `plans_tenant_id_index` (`tenant_id`);

ALTER TABLE `orders` ADD COLUMN `tenant_id` bigint(20) unsigned NOT NULL AFTER `id`;
ALTER TABLE `orders` ADD INDEX `orders_tenant_id_index` (`tenant_id`);

ALTER TABLE `tickets` ADD COLUMN `tenant_id` bigint(20) unsigned NOT NULL AFTER `id`;
ALTER TABLE `tickets` ADD INDEX `tickets_tenant_id_index` (`tenant_id`);

ALTER TABLE `notices` ADD COLUMN `tenant_id` bigint(20) unsigned DEFAULT NULL AFTER `id`;
ALTER TABLE `notices` ADD INDEX `notices_tenant_id_index` (`tenant_id`);

ALTER TABLE `coupons` ADD COLUMN `tenant_id` bigint(20) unsigned DEFAULT NULL AFTER `id`;
ALTER TABLE `coupons` ADD INDEX `coupons_tenant_id_index` (`tenant_id`);

-- 节点表保持全局共享，但添加租户访问控制
CREATE TABLE `tenant_servers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `server_id` bigint(20) unsigned NOT NULL,
  `custom_name` varchar(255) DEFAULT NULL COMMENT '租户自定义节点名',
  `custom_rate` decimal(10,2) DEFAULT NULL COMMENT '租户自定义倍率',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_servers_unique` (`tenant_id`,`server_id`),
  KEY `tenant_servers_server_id_foreign` (`server_id`),
  CONSTRAINT `tenant_servers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_servers_server_id_foreign` FOREIGN KEY (`server_id`) REFERENCES `server_nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 3. 后端架构实现

### 3.1 租户识别中间件

```php
<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;

class TenantIdentification
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    public function handle(Request $request, Closure $next)
    {
        // 获取当前访问域名
        $domain = $request->getHost();
        
        // 超级管理后台域名直接放行
        if ($domain === config('app.admin_domain')) {
            return $next($request);
        }
        
        // 查找对应租户
        $tenant = $this->tenantService->findByDomain($domain);
        
        if (!$tenant) {
            abort(404, '租户不存在');
        }
        
        if (!$tenant->isActive()) {
            abort(403, '租户已禁用或过期');
        }
        
        // 设置当前租户上下文
        app()->instance('currentTenant', $tenant);
        
        // 配置数据库查询作用域
        $this->configureTenantScope($tenant);
        
        return $next($request);
    }
    
    protected function configureTenantScope($tenant)
    {
        // 为 Eloquent 模型设置全局作用域
        $tenantId = $tenant->id;
        
        // 自动为查询添加 tenant_id 条件
        \App\Models\User::addGlobalScope('tenant', function ($builder) use ($tenantId) {
            $builder->where('tenant_id', $tenantId);
        });
        
        \App\Models\Order::addGlobalScope('tenant', function ($builder) use ($tenantId) {
            $builder->where('tenant_id', $tenantId);
        });
        
        // ... 其他模型
    }
}
```

### 3.2 租户服务类

```php
<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TenantService
{
    /**
     * 根据域名查找租户
     */
    public function findByDomain($domain)
    {
        return Cache::remember("tenant:domain:{$domain}", 3600, function () use ($domain) {
            // 先查找主域名
            $tenant = Tenant::where('domain', $domain)->first();
            
            if (!$tenant) {
                // 查找别名域名
                $tenantDomain = TenantDomain::where('domain', $domain)
                    ->where('status', 1)
                    ->first();
                    
                if ($tenantDomain) {
                    $tenant = $tenantDomain->tenant;
                }
            }
            
            return $tenant;
        });
    }
    
    /**
     * 创建新租户
     */
    public function createTenant($data)
    {
        $tenant = new Tenant();
        $tenant->uuid = Str::uuid();
        $tenant->name = $data['name'];
        $tenant->domain = $data['domain'];
        $tenant->status = 1;
        $tenant->config = $data['config'] ?? [];
        $tenant->save();
        
        // 创建租户管理员
        $this->createTenantAdmin($tenant, $data['admin']);
        
        // 初始化租户数据
        $this->initializeTenantData($tenant);
        
        return $tenant;
    }
    
    /**
     * 初始化租户数据
     */
    protected function initializeTenantData($tenant)
    {
        // 创建默认套餐
        $this->createDefaultPlans($tenant);
        
        // 创建默认公告
        $this->createWelcomeNotice($tenant);
        
        // 分配默认节点
        $this->assignDefaultServers($tenant);
    }
    
    /**
     * 获取租户可用节点
     */
    public function getTenantServers($tenant)
    {
        return $tenant->servers()
            ->where('tenant_servers.status', 1)
            ->where('server_nodes.status', 1)
            ->get();
    }
    
    /**
     * 切换租户上下文
     */
    public function switchTenant($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        app()->instance('currentTenant', $tenant);
        
        // 清除相关缓存
        Cache::tags(['tenant:' . $tenantId])->flush();
        
        return $tenant;
    }
}
```

### 3.3 租户模型 Trait

```php
<?php

namespace App\Models\Traits;

use App\Scopes\TenantScope;

trait BelongsToTenant
{
    /**
     * 模型启动时自动添加租户作用域
     */
    protected static function bootBelongsToTenant()
    {
        static::addGlobalScope(new TenantScope);
        
        static::creating(function ($model) {
            if (app()->has('currentTenant')) {
                $model->tenant_id = app('currentTenant')->id;
            }
        });
    }
    
    /**
     * 定义租户关系
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
```

## 4. 前端多租户实现

### 4.1 动态主题加载

```javascript
// services/ThemeService.js
class ThemeService {
    constructor() {
        this.currentTheme = null;
    }
    
    async loadTenantTheme() {
        const response = await api.get('/api/tenant/config');
        const { theme, config } = response.data;
        
        // 动态加载 CSS
        if (theme.css_url) {
            this.loadCSS(theme.css_url);
        }
        
        // 应用主题配置
        this.applyThemeConfig(config);
        
        // 设置网站信息
        document.title = config.site_name;
        this.updateFavicon(config.favicon_url);
        
        return config;
    }
    
    loadCSS(url) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url;
        document.head.appendChild(link);
    }
    
    applyThemeConfig(config) {
        // 应用颜色变量
        const root = document.documentElement;
        if (config.colors) {
            Object.entries(config.colors).forEach(([key, value]) => {
                root.style.setProperty(`--color-${key}`, value);
            });
        }
    }
}
```

### 4.2 租户配置获取

```javascript
// stores/tenantStore.js
import { defineStore } from 'pinia';

export const useTenantStore = defineStore('tenant', {
    state: () => ({
        config: null,
        theme: null,
        payment_methods: [],
        announcements: []
    }),
    
    actions: {
        async fetchTenantConfig() {
            try {
                const response = await api.get('/api/tenant/config');
                this.config = response.data.config;
                this.theme = response.data.theme;
                this.payment_methods = response.data.payment_methods;
                
                // 应用租户特定配置
                this.applyTenantSettings();
                
                return this.config;
            } catch (error) {
                console.error('Failed to fetch tenant config:', error);
            }
        },
        
        applyTenantSettings() {
            // 设置 API 基础 URL
            if (this.config.api_url) {
                api.defaults.baseURL = this.config.api_url;
            }
            
            // 设置语言
            if (this.config.locale) {
                i18n.locale = this.config.locale;
            }
        }
    }
});
```

## 5. 超级管理后台

### 5.1 租户管理界面

```jsx
// components/TenantManagement.jsx
import React, { useState, useEffect } from 'react';
import { Table, Button, Modal, Form, Input, Select, Switch } from 'antd';

const TenantManagement = () => {
    const [tenants, setTenants] = useState([]);
    const [modalVisible, setModalVisible] = useState(false);
    const [form] = Form.useForm();
    
    const columns = [
        {
            title: '租户ID',
            dataIndex: 'id',
            key: 'id',
        },
        {
            title: '租户名称',
            dataIndex: 'name',
            key: 'name',
        },
        {
            title: '主域名',
            dataIndex: 'domain',
            key: 'domain',
            render: (domain) => (
                <a href={`https://${domain}`} target="_blank">
                    {domain}
                </a>
            ),
        },
        {
            title: '用户数',
            dataIndex: 'user_count',
            key: 'user_count',
        },
        {
            title: '状态',
            dataIndex: 'status',
            key: 'status',
            render: (status) => (
                <Switch
                    checked={status === 1}
                    onChange={(checked) => handleStatusChange(record.id, checked)}
                />
            ),
        },
        {
            title: '到期时间',
            dataIndex: 'expire_at',
            key: 'expire_at',
        },
        {
            title: '操作',
            key: 'action',
            render: (_, record) => (
                <>
                    <Button onClick={() => handleEdit(record)}>编辑</Button>
                    <Button onClick={() => handleManage(record)}>管理</Button>
                    <Button onClick={() => handleStats(record)}>统计</Button>
                </>
            ),
        },
    ];
    
    const handleCreateTenant = async (values) => {
        try {
            const response = await api.post('/api/admin/tenants', values);
            message.success('租户创建成功');
            setModalVisible(false);
            fetchTenants();
        } catch (error) {
            message.error('创建失败: ' + error.message);
        }
    };
    
    const handleManage = (tenant) => {
        // 切换到租户管理模式
        window.location.href = `/admin/tenant/${tenant.id}/dashboard`;
    };
    
    return (
        <div>
            <div className="mb-4">
                <Button type="primary" onClick={() => setModalVisible(true)}>
                    创建租户
                </Button>
            </div>
            
            <Table
                columns={columns}
                dataSource={tenants}
                rowKey="id"
                pagination={{ pageSize: 20 }}
            />
            
            <Modal
                title="创建租户"
                visible={modalVisible}
                onCancel={() => setModalVisible(false)}
                footer={null}
            >
                <Form form={form} onFinish={handleCreateTenant}>
                    <Form.Item
                        name="name"
                        label="租户名称"
                        rules={[{ required: true }]}
                    >
                        <Input />
                    </Form.Item>
                    
                    <Form.Item
                        name="domain"
                        label="主域名"
                        rules={[{ required: true }]}
                    >
                        <Input placeholder="example.com" />
                    </Form.Item>
                    
                    <Form.Item
                        name="admin_email"
                        label="管理员邮箱"
                        rules={[{ required: true, type: 'email' }]}
                    >
                        <Input />
                    </Form.Item>
                    
                    <Form.Item
                        name="admin_password"
                        label="管理员密码"
                        rules={[{ required: true, min: 8 }]}
                    >
                        <Input.Password />
                    </Form.Item>
                    
                    <Form.Item
                        name="expire_days"
                        label="有效期(天)"
                        initialValue={365}
                    >
                        <Input type="number" />
                    </Form.Item>
                    
                    <Form.Item>
                        <Button type="primary" htmlType="submit">
                            创建
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>
        </div>
    );
};
```

### 5.2 节点分配管理

```jsx
// components/ServerAllocation.jsx
const ServerAllocation = ({ tenantId }) => {
    const [availableServers, setAvailableServers] = useState([]);
    const [allocatedServers, setAllocatedServers] = useState([]);
    
    const handleAllocate = async (serverIds) => {
        try {
            await api.post(`/api/admin/tenants/${tenantId}/servers`, {
                server_ids: serverIds
            });
            message.success('节点分配成功');
            fetchServers();
        } catch (error) {
            message.error('分配失败: ' + error.message);
        }
    };
    
    return (
        <Transfer
            dataSource={availableServers}
            targetKeys={allocatedServers}
            onChange={handleAllocate}
            render={item => item.name}
            titles={['可用节点', '已分配节点']}
        />
    );
};
```

## 6. API 路由设计

### 6.1 租户管理 API

```php
// routes/api.php

// 超级管理员 API
Route::prefix('admin')->middleware(['auth:admin', 'super_admin'])->group(function () {
    // 租户管理
    Route::apiResource('tenants', TenantController::class);
    Route::post('tenants/{tenant}/switch', [TenantController::class, 'switch']);
    Route::get('tenants/{tenant}/stats', [TenantController::class, 'stats']);
    Route::post('tenants/{tenant}/servers', [TenantController::class, 'allocateServers']);
    Route::post('tenants/{tenant}/domains', [TenantController::class, 'addDomain']);
    
    // 全局节点管理
    Route::apiResource('global-servers', GlobalServerController::class);
});

// 租户管理员 API
Route::prefix('tenant-admin')->middleware(['auth:tenant_admin', 'tenant'])->group(function () {
    Route::get('dashboard', [TenantAdminController::class, 'dashboard']);
    Route::get('users', [TenantAdminController::class, 'users']);
    Route::get('orders', [TenantAdminController::class, 'orders']);
    Route::post('config', [TenantAdminController::class, 'updateConfig']);
    Route::post('theme', [TenantAdminController::class, 'updateTheme']);
});

// 租户用户 API（自动应用租户作用域）
Route::prefix('api')->middleware(['tenant'])->group(function () {
    Route::get('tenant/config', [TenantConfigController::class, 'index']);
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user/info', [UserController::class, 'info']);
        Route::get('user/subscription', [SubscriptionController::class, 'index']);
        Route::get('servers', [ServerController::class, 'index']);
    });
});
```

## 7. 部署配置

### 7.1 Nginx 配置

```nginx
# 超级管理后台
server {
    listen 80;
    server_name admin.vpnall.com;
    
    location / {
        proxy_pass http://127.0.0.1:7001;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# 租户站点（通配）
server {
    listen 80;
    server_name *.vpnall.com *.tenant1.com *.tenant2.com;
    
    location / {
        proxy_pass http://127.0.0.1:7001;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Tenant-Domain $host;
    }
}
```

### 7.2 环境变量配置

```env
# .env 文件
APP_NAME="VpnAll Multi-Tenant Platform"
APP_ENV=production
APP_URL=https://admin.vpnall.com
ADMIN_DOMAIN=admin.vpnall.com

# 多租户配置
MULTI_TENANT_ENABLED=true
TENANT_DEFAULT_THEME=default
TENANT_CACHE_TTL=3600

# 数据库配置
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vpnall_multi
DB_USERNAME=vpnall
DB_PASSWORD=secure_password

# Redis 配置
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# 节点通信密钥
NODE_SECRET_KEY=your_global_node_secret
```

## 8. 数据迁移脚本

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class MigrateToMultiTenant extends Command
{
    protected $signature = 'xboard:migrate-multi-tenant {--default-tenant=}';
    
    protected $description = '将现有单租户数据迁移到多租户架构';
    
    public function handle()
    {
        $this->info('开始多租户迁移...');
        
        DB::beginTransaction();
        
        try {
            // 创建默认租户
            $tenant = $this->createDefaultTenant();
            
            // 迁移用户数据
            $this->migrateUsers($tenant);
            
            // 迁移订单数据
            $this->migrateOrders($tenant);
            
            // 迁移其他数据
            $this->migrateOtherData($tenant);
            
            DB::commit();
            
            $this->info('迁移完成！');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('迁移失败: ' . $e->getMessage());
        }
    }
    
    protected function createDefaultTenant()
    {
        $domain = $this->option('default-tenant') ?: 'default.vpnall.com';
        
        return Tenant::create([
            'uuid' => Str::uuid(),
            'name' => 'Default Tenant',
            'domain' => $domain,
            'status' => 1,
        ]);
    }
    
    protected function migrateUsers($tenant)
    {
        $this->info('迁移用户数据...');
        
        User::whereNull('tenant_id')->update([
            'tenant_id' => $tenant->id
        ]);
    }
}
```

## 9. 监控和统计

```php
// app/Services/TenantStatsService.php
class TenantStatsService
{
    public function collectDailyStats()
    {
        $tenants = Tenant::active()->get();
        
        foreach ($tenants as $tenant) {
            $this->switchTenant($tenant);
            
            $stats = [
                'tenant_id' => $tenant->id,
                'date' => today(),
                'user_count' => User::count(),
                'active_users' => User::where('last_login_at', '>=', now()->subDays(7))->count(),
                'order_count' => Order::whereDate('created_at', today())->count(),
                'order_amount' => Order::whereDate('created_at', today())->sum('total_amount'),
                'traffic_used' => $this->calculateTrafficUsage($tenant),
            ];
            
            TenantStat::updateOrCreate(
                ['tenant_id' => $tenant->id, 'date' => today()],
                $stats
            );
        }
    }
}
```

## 总结

这个多租户架构方案实现了：

1. **完全的租户隔离**：每个租户拥有独立的用户体系
2. **统一的管理后台**：超级管理员可以管理所有租户
3. **灵活的域名映射**：支持多域名和子域名
4. **共享的节点资源**：所有租户共享节点池，但可自定义显示
5. **独立的主题定制**：每个租户可以有自己的界面主题
6. **完善的权限控制**：多级权限管理体系

需要我详细实现某个具体模块吗？