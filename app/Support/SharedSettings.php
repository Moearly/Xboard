<?php

namespace App\Support;

/**
 * 共享配置定义
 * 
 * 这些配置在所有租户间共享，不需要租户隔离
 */
class SharedSettings
{
    /**
     * 共享配置项列表
     * 这些配置所有租户共用同一个值
     */
    public const SHARED_KEYS = [
        // 节点相关配置（所有租户共享节点，配置也共享）
        'server_token',
        'server_license',
        'server_log_enable',
        'server_v2ray_domain',
        'server_v2ray_protocol',
        
        // 系统级配置
        'app_version',
        'maintenance_mode',
        'force_https',
        'safe_mode_enable',
        
        // 全局限制
        'recaptcha_enable',
        'recaptcha_key',
        'recaptcha_site_key',
        
        // 系统通知配置（可选共享）
        'telegram_bot_enable',
        'telegram_bot_token',
        'telegram_discuss_link',
    ];
    
    /**
     * 租户独立配置项列表
     * 每个租户有自己独立的值
     */
    public const TENANT_SPECIFIC_KEYS = [
        // 站点信息
        'app_name',
        'app_description',
        'app_url',
        'frontend_theme',
        'frontend_theme_sidebar',
        'frontend_theme_header',
        'frontend_theme_color',
        'frontend_background_url',
        'frontend_admin_path',
        
        // 注册配置
        'register_enable',
        'email_verify',
        'email_whitelist_enable',
        'email_whitelist_suffix',
        'email_gmail_limit_enable',
        'stop_register',
        'invite_force',
        'invite_commission',
        'invite_gen_limit',
        'invite_never_expire',
        
        // 邮件配置
        'email_template',
        'email_host',
        'email_port',
        'email_username',
        'email_password',
        'email_encryption',
        'email_from_address',
        
        // 支付配置
        'alipay_enable',
        'alipay_appid',
        'alipay_public_key',
        'alipay_private_key',
        'stripe_enable',
        'stripe_sk_live',
        'stripe_pk_live',
        'stripe_webhook_key',
        'currency',
        'currency_symbol',
        
        // 佣金配置
        'commission_first_time_enable',
        'commission_auto_check_enable',
        'commission_withdraw_limit',
        'commission_withdraw_method',
        
        // 工单配置
        'ticket_enable',
        'ticket_close_enable',
        'ticket_reply_notify_enable',
        
        // 其他租户特定配置
        'tos_url',
        'staff_url',
        'subscribe_url',
        'subscribe_domain',
    ];
    
    /**
     * 检查配置项是否为共享配置
     */
    public static function isShared(string $key): bool
    {
        return in_array($key, self::SHARED_KEYS);
    }
    
    /**
     * 检查配置项是否为租户独立配置
     */
    public static function isTenantSpecific(string $key): bool
    {
        return in_array($key, self::TENANT_SPECIFIC_KEYS);
    }
    
    /**
     * 获取配置项的租户ID
     * 如果是共享配置，返回1（默认租户）
     * 如果是租户独立配置，返回当前租户ID
     */
    public static function getTenantIdForKey(string $key): int
    {
        if (self::isShared($key)) {
            return 1; // 共享配置使用默认租户ID
        }
        
        // 优先从应用容器获取当前租户
        if (app()->has('currentTenant')) {
            \Log::info("getTenantIdForKey: Using currentTenant from app container", ['tenant_id' => app('currentTenant')->id, 'key' => $key]);
            return app('currentTenant')->id;
        }
        
        // 如果没有绑定租户，尝试从请求头获取
        $request = request();
        if ($request) {
            $tenantId = $request->header('X-Tenant-ID');
            \Log::info("getTenantIdForKey: Checking request header", ['X-Tenant-ID' => $tenantId, 'key' => $key]);
            if ($tenantId && is_numeric($tenantId)) {
                \Log::info("getTenantIdForKey: Using X-Tenant-ID from header", ['tenant_id' => $tenantId, 'key' => $key]);
                return (int) $tenantId;
            }
        }
        
        \Log::warning("getTenantIdForKey: Falling back to default tenant", ['key' => $key]);
        return 1;
    }
}

