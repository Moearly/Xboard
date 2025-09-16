<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantLog;
use App\Models\User;
use App\Models\Order;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TenantService
{
    /**
     * 创建租户
     */
    public static function createTenant($data)
    {
        DB::beginTransaction();
        try {
            // 设置默认功能
            if (!isset($data['features'])) {
                $data['features'] = Tenant::getDefaultFeatures();
            }
            
            // 创建租户
            $tenant = Tenant::create($data);
            
            // 记录日志
            TenantLog::log(
                TenantLog::ACTION_CREATE,
                "创建租户: {$tenant->name}",
                $data,
                $tenant->id
            );
            
            // 创建默认套餐（可选）
            if (isset($data['create_default_plan']) && $data['create_default_plan']) {
                self::createDefaultPlan($tenant);
            }
            
            DB::commit();
            return $tenant;
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
    
    /**
     * 创建默认套餐
     */
    protected static function createDefaultPlan($tenant)
    {
        $defaultPlan = [
            'tenant_id' => $tenant->id,
            'name' => '基础套餐',
            'group_id' => 1,
            'transfer_enable' => 100 * 1024 * 1024 * 1024, // 100GB
            'speed_limit' => 100, // 100Mbps
            'device_limit' => 3,
            'prices' => [
                'month' => 1000, // 10元
                'quarter' => 2700, // 27元
                'half_year' => 5000, // 50元
                'year' => 9000, // 90元
            ],
            'show' => true,
            'sell' => true,
            'renew' => true,
        ];
        
        Plan::create($defaultPlan);
    }
    
    /**
     * 检查租户限制
     */
    public static function checkLimits(Tenant $tenant, $type)
    {
        switch ($type) {
            case 'user':
                if ($tenant->hasReachedUserLimit()) {
                    throw new \Exception("已达到最大用户数限制 ({$tenant->max_users})");
                }
                break;
                
            case 'order':
                if ($tenant->hasReachedOrderLimit()) {
                    throw new \Exception("已达到本月最大订单数限制 ({$tenant->max_orders_per_month})");
                }
                break;
                
            case 'node':
                if ($tenant->hasReachedNodeLimit()) {
                    throw new \Exception("已达到最大节点数限制 ({$tenant->max_nodes})");
                }
                break;
                
            case 'revenue':
                if ($tenant->max_monthly_revenue) {
                    $currentRevenue = $tenant->getCurrentMonthRevenue();
                    if ($currentRevenue >= $tenant->max_monthly_revenue) {
                        throw new \Exception("已达到本月收入上限 (￥{$tenant->max_monthly_revenue})");
                    }
                }
                break;
        }
        
        return true;
    }
    
    /**
     * 导出租户数据
     */
    public static function exportTenantData(Tenant $tenant, $format = 'json')
    {
        $data = [
            'tenant' => $tenant->toArray(),
            'statistics' => $tenant->getStatistics(),
            'users' => $tenant->users()->get()->toArray(),
            'orders' => $tenant->orders()->with('user')->get()->toArray(),
            'plans' => $tenant->plans()->get()->toArray(),
            'servers' => $tenant->servers()->get()->toArray(),
            'export_time' => now()->toDateTimeString(),
        ];
        
        // 记录导出日志
        TenantLog::log(
            TenantLog::ACTION_EXPORT,
            "导出租户数据",
            ['format' => $format],
            $tenant->id
        );
        
        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                
            case 'csv':
                return self::convertToCSV($data);
                
            default:
                return $data;
        }
    }
    
    /**
     * 转换为CSV格式
     */
    protected static function convertToCSV($data)
    {
        $csv = [];
        
        // 租户信息
        $csv[] = ['租户信息'];
        $csv[] = ['字段', '值'];
        foreach ($data['tenant'] as $key => $value) {
            if (!is_array($value)) {
                $csv[] = [$key, $value];
            }
        }
        
        // 统计信息
        $csv[] = [];
        $csv[] = ['统计信息'];
        $csv[] = ['指标', '值'];
        foreach ($data['statistics'] as $key => $value) {
            $csv[] = [$key, $value];
        }
        
        // 用户列表
        $csv[] = [];
        $csv[] = ['用户列表'];
        if (!empty($data['users'])) {
            $csv[] = array_keys($data['users'][0]);
            foreach ($data['users'] as $user) {
                $csv[] = array_values($user);
            }
        }
        
        // 转换为CSV字符串
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);
        
        return $csvString;
    }
    
    /**
     * 克隆租户配置到新租户
     */
    public static function cloneTenant(Tenant $sourceTenant, $newData)
    {
        DB::beginTransaction();
        try {
            // 复制基本配置
            $cloneData = array_merge([
                'config' => $sourceTenant->config,
                'features' => $sourceTenant->features,
                'theme_config' => $sourceTenant->theme_config,
                'max_users' => $sourceTenant->max_users,
                'max_orders_per_month' => $sourceTenant->max_orders_per_month,
                'max_nodes' => $sourceTenant->max_nodes,
                'max_monthly_revenue' => $sourceTenant->max_monthly_revenue,
            ], $newData);
            
            // 创建新租户
            $newTenant = Tenant::create($cloneData);
            
            // 复制套餐
            $plans = $sourceTenant->plans()->get();
            foreach ($plans as $plan) {
                $planData = $plan->toArray();
                unset($planData['id'], $planData['created_at'], $planData['updated_at']);
                $planData['tenant_id'] = $newTenant->id;
                Plan::create($planData);
            }
            
            // 复制节点分配
            $serverIds = $sourceTenant->servers()->pluck('server_id')->toArray();
            $syncData = [];
            foreach ($serverIds as $serverId) {
                $syncData[$serverId] = ['is_active' => true];
            }
            $newTenant->servers()->sync($syncData);
            
            // 记录日志
            TenantLog::log(
                TenantLog::ACTION_CREATE,
                "从租户 {$sourceTenant->name} 克隆创建",
                ['source_tenant_id' => $sourceTenant->id],
                $newTenant->id
            );
            
            DB::commit();
            return $newTenant;
            
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
    
    /**
     * 批量更新租户配置
     */
    public static function batchUpdate($tenantIds, $updates)
    {
        $tenants = Tenant::whereIn('id', $tenantIds)->get();
        $updated = 0;
        
        foreach ($tenants as $tenant) {
            $tenant->update($updates);
            
            TenantLog::log(
                TenantLog::ACTION_UPDATE,
                "批量更新配置",
                $updates,
                $tenant->id
            );
            
            $updated++;
        }
        
        return $updated;
    }
    
    /**
     * 获取租户健康状态
     */
    public static function getHealthStatus(Tenant $tenant)
    {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'warnings' => [],
        ];
        
        // 检查过期
        if ($tenant->expire_at && $tenant->expire_at->isPast()) {
            $health['status'] = 'expired';
            $health['issues'][] = '租户已过期';
        }
        
        // 检查是否禁用
        if (!$tenant->status) {
            $health['status'] = 'disabled';
            $health['issues'][] = '租户已禁用';
        }
        
        // 检查资源使用
        if ($tenant->max_users) {
            $userCount = $tenant->users()->count();
            $usage = ($userCount / $tenant->max_users) * 100;
            if ($usage >= 90) {
                $health['warnings'][] = "用户数接近上限 ({$userCount}/{$tenant->max_users})";
            }
        }
        
        if ($tenant->max_monthly_revenue) {
            $revenue = $tenant->getCurrentMonthRevenue();
            $usage = ($revenue / $tenant->max_monthly_revenue) * 100;
            if ($usage >= 90) {
                $health['warnings'][] = "月收入接近上限 (￥{$revenue}/￥{$tenant->max_monthly_revenue})";
            }
        }
        
        // 检查节点分配
        if ($tenant->servers()->count() == 0) {
            $health['warnings'][] = '未分配任何节点';
        }
        
        // 设置最终状态
        if (!empty($health['issues'])) {
            $health['status'] = 'critical';
        } elseif (!empty($health['warnings'])) {
            $health['status'] = 'warning';
        }
        
        return $health;
    }
}