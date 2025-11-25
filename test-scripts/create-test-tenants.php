<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🏢 创建测试租户\n";
echo "================================\n\n";

// 清理旧数据
\App\Models\Tenant::truncate();
\DB::table('v2_settings')->truncate();

// 创建租户A
$tenantA = \App\Models\Tenant::create([
    'name' => '租户A - VPN服务商',
    'domain' => 'tenant-a.local',
    'status' => 'active',
    'admin_email' => 'admin@tenant-a.local',
]);

// 创建租户B
$tenantB = \App\Models\Tenant::create([
    'name' => '租户B - 网络加速',
    'domain' => 'tenant-b.local',
    'status' => 'active',
    'admin_email' => 'admin@tenant-b.local',
]);

echo "✅ 租户A创建成功:\n";
echo "   ID: {$tenantA->id}\n";
echo "   域名: {$tenantA->domain}\n";
echo "   访问地址: http://tenant-a.local:5173\n\n";

echo "✅ 租户B创建成功:\n";
echo "   ID: {$tenantB->id}\n";
echo "   域名: {$tenantB->domain}\n";
echo "   访问地址: http://tenant-b.local:5173\n\n";

// 为租户A设置初始配置
app()->singleton('currentTenant', fn() => $tenantA);
\App\Models\Setting::updateOrCreate(
    ['name' => 'app_name', 'tenant_id' => $tenantA->id],
    ['value' => 'VPN服务商A']
);
\App\Models\Setting::updateOrCreate(
    ['name' => 'app_description', 'tenant_id' => $tenantA->id],
    ['value' => '专业的VPN服务提供商']
);
\App\Models\Setting::updateOrCreate(
    ['name' => 'app_url', 'tenant_id' => $tenantA->id],
    ['value' => 'https://tenant-a.com'\]
);

echo "✅ 租户A配置已初始化\n\n";

// 为租户B设置初始配置
app()->forgetInstance('currentTenant');
app()->singleton('currentTenant', fn() => $tenantB);
\App\Models\Setting::updateOrCreate(
    ['name' => 'app_name', 'tenant_id' => $tenantB->id],
    ['value' => '网络加速B']
);
\App\Models\Setting::updateOrCreate(
    ['name' => 'app_description', 'tenant_id' => $tenantB->id],
    ['value' => '高速网络加速服务']
);
\App\Models\Setting::updateOrCreate(
    ['name' => 'app_url', 'tenant_id' => $tenantB->id],
    ['value' => 'https://tenant-b.com'\]
);

echo "✅ 租户B配置已初始化\n\n";

echo "================================\n";
echo "🎉 测试环境准备完成！\n\n";
echo "📝 测试步骤：\n";
echo "1. 访问 http://tenant-a.local:5173/config/system\n";
echo "2. 修改站点名称为 'VPN服务商A - 已修改'\n";
echo "3. 保存配置\n";
echo "4. 访问 http://tenant-b.local:5173/config/system\n";
echo "5. 确认站点名称仍然是 '网络加速B'\n";
echo "6. 修改站点名称为 '网络加速B - 已修改'\n";
echo "7. 保存配置\n";
echo "8. 返回租户A，确认配置未被影响\n\n";
