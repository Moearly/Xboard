<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tenant extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'domain',
        'status',
        'config',
        'expire_at',
        'max_users',
        'max_orders_per_month',
        'max_nodes',
        'max_monthly_revenue',
        'features',
        'theme_config',
        'statistics_cache',
        'statistics_updated_at',
        'admin_email',
        'admin_phone',
        'notes',
    ];

    protected $casts = [
        'status' => 'boolean',
        'config' => 'array',
        'expire_at' => 'datetime',
        'features' => 'array',
        'theme_config' => 'array',
        'statistics_cache' => 'array',
        'statistics_updated_at' => 'datetime',
        'max_monthly_revenue' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = Str::uuid()->toString();
            }
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    /**
     * 租户分配的节点
     */
    public function servers()
    {
        return $this->belongsToMany(Server::class, 'tenant_server')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * 获取租户可用的节点
     */
    public function availableServers()
    {
        return $this->servers()
            ->wherePivot('is_active', true)
            ->where('show', 1);
    }

    public function isActive()
    {
        return $this->status && (!$this->expire_at || $this->expire_at->isFuture());
    }

    /**
     * 操作日志
     */
    public function logs()
    {
        return $this->hasMany(TenantLog::class);
    }

    /**
     * 检查是否达到用户数限制
     */
    public function hasReachedUserLimit()
    {
        if (!$this->max_users) {
            return false;
        }
        return $this->users()->count() >= $this->max_users;
    }

    /**
     * 检查是否达到月订单限制
     */
    public function hasReachedOrderLimit()
    {
        if (!$this->max_orders_per_month) {
            return false;
        }
        
        $currentMonthOrders = $this->orders()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
            
        return $currentMonthOrders >= $this->max_orders_per_month;
    }

    /**
     * 检查是否达到节点数限制
     */
    public function hasReachedNodeLimit()
    {
        if (!$this->max_nodes) {
            return false;
        }
        return $this->servers()->count() >= $this->max_nodes;
    }

    /**
     * 获取当月收入
     */
    public function getCurrentMonthRevenue()
    {
        return $this->orders()
            ->where('status', 3) // 已支付
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount') / 100; // 转换为元
    }

    /**
     * 检查功能是否启用
     */
    public function hasFeature($feature)
    {
        $features = $this->features ?? [];
        return isset($features[$feature]) && $features[$feature] === true;
    }

    /**
     * 更新统计缓存
     */
    public function updateStatisticsCache()
    {
        $stats = [
            'users_count' => $this->users()->count(),
            'active_users_count' => $this->users()->where('expired_at', '>', time())->count(),
            'orders_count' => $this->orders()->count(),
            'monthly_orders' => $this->orders()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'total_revenue' => $this->orders()->where('status', 3)->sum('total_amount') / 100,
            'monthly_revenue' => $this->getCurrentMonthRevenue(),
            'plans_count' => $this->plans()->count(),
            'nodes_count' => $this->servers()->count(),
        ];
        
        $this->update([
            'statistics_cache' => $stats,
            'statistics_updated_at' => now(),
        ]);
        
        return $stats;
    }

    /**
     * 获取统计数据（带缓存）
     */
    public function getStatistics($forceRefresh = false)
    {
        if ($forceRefresh || 
            !$this->statistics_cache || 
            !$this->statistics_updated_at ||
            $this->statistics_updated_at->diffInMinutes(now()) > 30) {
            return $this->updateStatisticsCache();
        }
        
        return $this->statistics_cache;
    }

    /**
     * 默认功能配置
     */
    public static function getDefaultFeatures()
    {
        return [
            'tickets' => true,        // 工单系统
            'knowledge' => true,      // 知识库
            'coupons' => true,        // 优惠券
            'invites' => true,        // 邀请系统
            'announcements' => true,  // 公告
            'custom_theme' => false,  // 自定义主题
            'api_access' => false,    // API访问
            'export_data' => false,   // 数据导出
        ];
    }
}