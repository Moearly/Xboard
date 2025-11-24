<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateTenantSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:migrate-settings 
                            {--default-tenant=1 : 默认租户ID}
                            {--copy-to-all : 是否将默认租户配置复制到所有租户}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '迁移配置数据以支持多租户';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $defaultTenantId = $this->option('default-tenant');
        
        $this->info('🚀 开始迁移配置数据...');
        $this->newLine();
        
        // 1. 确保默认租户存在
        $defaultTenant = Tenant::find($defaultTenantId);
        if (!$defaultTenant) {
            $this->error("❌ 默认租户 ID {$defaultTenantId} 不存在！");
            $this->info("💡 提示：请先创建租户或使用正确的租户ID");
            return 1;
        }
        
        $this->info("✅ 找到默认租户: {$defaultTenant->name} (ID: {$defaultTenantId})");
        $this->newLine();
        
        // 2. 为现有配置分配默认租户ID
        $this->info('📝 为现有配置分配租户ID...');
        
        try {
            $updatedCount = DB::table('v2_settings')
                ->where(function($query) {
                    $query->whereNull('tenant_id')
                          ->orWhere('tenant_id', 0);
                })
                ->update(['tenant_id' => $defaultTenantId]);
                
            $this->info("✅ 已为 {$updatedCount} 条配置分配租户ID: {$defaultTenantId}");
        } catch (\Exception $e) {
            $this->error("❌ 分配租户ID失败: " . $e->getMessage());
            return 1;
        }
        
        $this->newLine();
        
        // 3. 如果需要，将默认配置复制到所有租户
        if ($this->option('copy-to-all')) {
            $this->copySettingsToAllTenants($defaultTenantId);
        } else {
            $this->warn('💡 使用 --copy-to-all 选项可以将默认配置复制到所有租户');
        }
        
        $this->newLine();
        $this->info('🎉 配置迁移完成！');
        
        return 0;
    }
    
    /**
     * 将默认租户的配置复制到所有租户
     */
    private function copySettingsToAllTenants($sourceTenantId)
    {
        $sourceSettings = Setting::where('tenant_id', $sourceTenantId)->get();
        $tenants = Tenant::where('id', '!=', $sourceTenantId)->get();
        
        if ($tenants->isEmpty()) {
            $this->warn('⚠️  没有其他租户需要复制配置');
            return;
        }
        
        $this->info("📋 开始复制配置到 {$tenants->count()} 个租户...");
        $this->newLine();
        
        foreach ($tenants as $tenant) {
            $this->info("🔄 正在复制到租户: {$tenant->name} (ID: {$tenant->id})");
            
            $bar = $this->output->createProgressBar($sourceSettings->count());
            $bar->setFormat('  %current%/%max% [%bar%] %percent:3s%%');
            
            $copiedCount = 0;
            foreach ($sourceSettings as $setting) {
                try {
                    Setting::updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'name' => $setting->name,
                        ],
                        [
                            'value' => $setting->getRawOriginal('value'), // 使用原始值
                        ]
                    );
                    $copiedCount++;
                } catch (\Exception $e) {
                    // 忽略错误，继续下一个
                }
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine();
            $this->info("  ✅ 已复制 {$copiedCount} 条配置");
            $this->newLine();
        }
        
        $this->info('🎉 所有租户配置复制完成！');
    }
}

