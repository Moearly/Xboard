<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InitializeTenantSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:init-settings {tenant_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '为新租户初始化默认配置';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            $this->error("❌ 租户 ID {$tenantId} 不存在！");
            return 1;
        }
        
        $this->info("🚀 为租户 {$tenant->name} 初始化配置...");
        $this->newLine();
        
        // 检查是否已有配置
        $existingCount = Setting::where('tenant_id', $tenantId)->count();
        if ($existingCount > 0) {
            if (!$this->confirm("该租户已有 {$existingCount} 条配置，是否覆盖？", false)) {
                $this->warn('⚠️  操作已取消');
                return 0;
            }
        }
        
        // 默认配置
        $defaultSettings = $this->getDefaultSettings($tenant);
        
        $this->info("📝 正在创建 " . count($defaultSettings) . " 条配置...");
        $bar = $this->output->createProgressBar(count($defaultSettings));
        $bar->setFormat('  %current%/%max% [%bar%] %percent:3s%%');
        
        $createdCount = 0;
        foreach ($defaultSettings as $key => $value) {
            try {
                Setting::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'name' => $key,
                    ],
                    [
                        'value' => is_array($value) ? json_encode($value) : $value,
                    ]
                );
                $createdCount++;
            } catch (\Exception $e) {
                $this->error("创建配置 {$key} 失败: " . $e->getMessage());
            }
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->newLine();
        $this->info("✅ 成功创建 {$createdCount} 条配置");
        $this->info('🎉 配置初始化完成！');
        
        return 0;
    }
    
    /**
     * 获取租户默认配置
     */
    private function getDefaultSettings($tenant): array
    {
        return [
            // 站点信息
            'app_name' => $tenant->name,
            'app_description' => 'Professional VPN Service',
            'app_url' => 'https://' . $tenant->domain,
            
            // 主题配置
            'frontend_theme' => 'v2board',
            
            // 安全配置
            'server_token' => Str::random(32),
            'secure_path' => 'admin',
            
            // 注册配置
            'register_enable' => '1',
            'email_verify' => '0',
            'email_whitelist_enable' => '0',
            'email_gmail_limit_enable' => '0',
            'stop_register' => '0',
            'invite_force' => '0',
            'invite_commission' => '10',
            'commission_first_time_enable' => '1',
            'commission_auto_check_enable' => '1',
            'commission_withdraw_limit' => '100',
            'commission_withdraw_method' => json_encode(['USDT']),
            
            // 邮件配置（需要租户自行配置）
            'email_host' => '',
            'email_port' => '465',
            'email_username' => '',
            'email_password' => '',
            'email_encryption' => 'ssl',
            'email_from_address' => '',
            'email_template' => 'default',
            
            // 支付配置
            'currency' => 'CNY',
            'currency_symbol' => '¥',
            
            // 工单配置
            'ticket_enable' => '1',
            'ticket_close_enable' => '1',
            'ticket_reply_notify_enable' => '1',
            
            // 知识库配置
            'knowledge_enable' => '1',
            
            // 流量重置配置
            'reset_traffic_method' => '0',
            
            // 设备限制
            'device_limit' => '3',
            
            // 订阅配置
            'subscribe_url' => 'https://' . $tenant->domain,
            'subscribe_domain' => $tenant->domain,
            
            // 支付配置（需要租户自行配置）
            'alipay_enable' => '0',
            'stripe_enable' => '0',
            
            // 其他默认配置
            'tos_url' => '',
            'staff_url' => '',
            'stop_register_tip' => '',
        ];
    }
}

