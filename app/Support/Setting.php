<?php

namespace App\Support;

use App\Models\Setting as SettingModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository;

class Setting
{
    const CACHE_KEY_PREFIX = 'admin_settings';

    private Repository $cache;
    private ?array $loadedSettings = null; // 请求内缓存

    public function __construct()
    {
        // 使用默认缓存驱动（支持file/redis/array等）
        $this->cache = Cache::store();
    }

    /**
     * 获取当前租户的缓存键
     */
    private function getCacheKey(): string
    {
        $tenantId = $this->getCurrentTenantId();
        return self::CACHE_KEY_PREFIX . ':tenant_' . $tenantId;
    }
    
    /**
     * 获取共享配置的缓存键
     */
    private function getSharedCacheKey(): string
    {
        return self::CACHE_KEY_PREFIX . ':shared';
    }
    
    /**
     * 获取当前租户ID
     */
    private function getCurrentTenantId(): int
    {
        if (app()->has('currentTenant')) {
            return app('currentTenant')->id;
        }
        return 1; // 默认租户
    }
    
    /**
     * 当前加载的租户ID（用于检测租户切换）
     */
    private ?int $currentLoadedTenantId = null;

    /**
     * 获取配置.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();
        return Arr::get($this->loadedSettings, strtolower($key), $default);
    }

    /**
     * 设置配置信息.
     */
    public function set(string $key, mixed $value = null): bool
    {
        SettingModel::createOrUpdate(strtolower($key), $value);
        $this->flush();
        return true;
    }

    /**
     * 保存配置到数据库.
     */
    public function save(array $settings): bool
    {
        foreach ($settings as $key => $value) {
            SettingModel::createOrUpdate(strtolower($key), $value);
        }
        $this->flush();
        return true;
    }

    /**
     * 删除配置信息（支持共享/独立混合模式）
     */
    public function remove(string $key): bool
    {
        $tenantId = \App\Support\SharedSettings::getTenantIdForKey($key);
        
        SettingModel::where('name', $key)
            ->where('tenant_id', $tenantId)
            ->delete();
            
        $this->flush();
        return true;
    }

    /**
     * 更新单个设置项
     */
    public function update(string $key, $value): bool
    {
        return $this->set($key, $value);
    }
    
    /**
     * 批量获取配置项
     */
    public function getBatch(array $keys): array
    {
        $this->load();
        $result = [];
        
        foreach ($keys as $index => $item) {
            $isNumericIndex = is_numeric($index);
            $key = strtolower($isNumericIndex ? $item : $index);
            $default = $isNumericIndex ? config('v2board.' . $item) : (config('v2board.' . $key) ?? $item);
            
            $result[$item] = Arr::get($this->loadedSettings, $key, $default);
        }
        
        return $result;
    }
    
    /**
     * 将所有设置转换为数组
     */
    public function toArray(): array
    {
        $this->load();
        return $this->loadedSettings;
    }

    /**
     * 加载配置到请求内缓存（租户感知版本）
     */
    private function load(): void
    {
        $currentTenantId = $this->getCurrentTenantId();
        
        // 如果租户切换了，清空缓存
        if ($this->loadedSettings !== null && $this->currentLoadedTenantId !== $currentTenantId) {
            $this->loadedSettings = null;
        }
        
        if ($this->loadedSettings !== null) {
            return;
        }
        
        // 记住当前租户ID
        $this->currentLoadedTenantId = $currentTenantId;

        try {
            $cacheKey = $this->getCacheKey();
            $tenantId = $this->getCurrentTenantId();
            
            $settings = $this->cache->rememberForever($cacheKey, function () use ($tenantId): array {
                return array_change_key_case(
                    SettingModel::where('tenant_id', $tenantId)
                        ->pluck('value', 'name')
                        ->toArray(),
                    CASE_LOWER
                );
            });
            
            // 处理JSON格式的值
            foreach ($settings as $key => $value) {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $settings[$key] = $decoded;
                    }
                }
            }
            
            $this->loadedSettings = $settings;
        } catch (\Throwable $e) {
            \Log::error('Failed to load tenant settings', [
                'tenant_id' => $this->getCurrentTenantId(),
                'error' => $e->getMessage()
            ]);
            $this->loadedSettings = [];
        }
    }

    /**
     * 清空缓存（租户特定）
     */
    private function flush(): void
    {
        $cacheKey = $this->getCacheKey();
        $this->cache->forget($cacheKey);
        $this->loadedSettings = null;
    }
    
    /**
     * 清空所有租户的配置缓存（超级管理员使用）
     */
    public function flushAll(): void
    {
        try {
            // 清空所有租户的缓存
            $tenants = \App\Models\Tenant::all();
            foreach ($tenants as $tenant) {
                $cacheKey = self::CACHE_KEY_PREFIX . ':tenant_' . $tenant->id;
                $this->cache->forget($cacheKey);
            }
            $this->loadedSettings = null;
        } catch (\Throwable $e) {
            \Log::error('Failed to flush all tenant settings cache', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
