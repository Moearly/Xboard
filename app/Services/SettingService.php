<?php

namespace App\Services;

use App\Models\Setting as SettingModel;

class SettingService
{
    /**
     * 获取配置项的租户ID（支持共享/独立混合模式）
     */
    private function getTenantIdForKey(string $key): int
    {
        return \App\Support\SharedSettings::getTenantIdForKey($key);
    }

    /**
     * 获取配置（支持共享/独立混合模式）
     */
    public function get($name, $default = null)
    {
        $tenantId = $this->getTenantIdForKey($name);
        
        $setting = SettingModel::where('name', $name)
            ->where('tenant_id', $tenantId)
            ->first();
            
        return $setting ? $setting->value : $default;
    }

    /**
     * 获取所有配置（自动合并共享和租户独立配置）
     */
    public function getAll()
    {
        $currentTenantId = app()->has('currentTenant') 
            ? app('currentTenant')->id 
            : 1;
        
        // 获取共享配置（tenant_id=1）
        $sharedSettings = SettingModel::where('tenant_id', 1)
            ->whereIn('name', \App\Support\SharedSettings::SHARED_KEYS)
            ->pluck('value', 'name')
            ->toArray();
        
        // 获取租户独立配置
        $tenantSettings = SettingModel::where('tenant_id', $currentTenantId)
            ->whereIn('name', \App\Support\SharedSettings::TENANT_SPECIFIC_KEYS)
            ->pluck('value', 'name')
            ->toArray();
        
        // 合并配置
        return array_merge($sharedSettings, $tenantSettings);
    }
}
