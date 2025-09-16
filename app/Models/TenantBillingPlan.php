<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantBillingPlan extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'base_fee',
        'setup_fee',
        'free_users',
        'per_user_fee',
        'free_traffic',
        'per_gb_fee',
        'free_nodes',
        'per_node_fee',
        'revenue_share',
        'min_revenue_fee',
        'max_users',
        'max_nodes',
        'max_traffic',
        'max_revenue',
        'features',
        'limits',
        'is_active',
        'sort',
    ];

    protected $casts = [
        'base_fee' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'per_user_fee' => 'decimal:2',
        'per_gb_fee' => 'decimal:2',
        'per_node_fee' => 'decimal:2',
        'revenue_share' => 'decimal:2',
        'min_revenue_fee' => 'decimal:2',
        'max_revenue' => 'decimal:2',
        'features' => 'array',
        'limits' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * 默认方案代码
     */
    const PLAN_FREE = 'free';
    const PLAN_STARTER = 'starter';
    const PLAN_PROFESSIONAL = 'professional';
    const PLAN_ENTERPRISE = 'enterprise';

    /**
     * 关联订阅
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantBillingSubscription::class, 'billing_plan_id');
    }

    /**
     * 获取活跃订阅数
     */
    public function getActiveSubscriptionsCount(): int
    {
        return $this->subscriptions()->where('status', 'active')->count();
    }

    /**
     * 计算租户费用
     */
    public function calculateFees(array $usage, TenantBillingSubscription $subscription = null): array
    {
        $fees = [
            'base_fee' => $this->base_fee,
            'user_fee' => 0,
            'traffic_fee' => 0,
            'node_fee' => 0,
            'revenue_fee' => 0,
            'total' => 0,
        ];

        // 使用自定义费率或默认费率
        $perUserFee = $subscription?->custom_per_user_fee ?? $this->per_user_fee;
        $perGbFee = $subscription?->custom_per_gb_fee ?? $this->per_gb_fee;
        $perNodeFee = $subscription?->custom_per_node_fee ?? $this->per_node_fee;
        
        // 计算用户费用
        $billableUsers = max(0, ($usage['user_count'] ?? 0) - $this->free_users);
        $fees['user_fee'] = $billableUsers * $perUserFee;
        
        // 计算流量费用 (将字节转换为GB)
        $trafficGb = ($usage['traffic_used'] ?? 0) / (1024 * 1024 * 1024);
        $billableTraffic = max(0, $trafficGb - $this->free_traffic);
        $fees['traffic_fee'] = $billableTraffic * $perGbFee;
        
        // 计算节点费用
        $billableNodes = max(0, ($usage['node_count'] ?? 0) - $this->free_nodes);
        $fees['node_fee'] = $billableNodes * $perNodeFee;
        
        // 计算收入分成
        if ($this->revenue_share > 0 && isset($usage['revenue_amount'])) {
            $fees['revenue_fee'] = max(
                $this->min_revenue_fee,
                $usage['revenue_amount'] * ($this->revenue_share / 100)
            );
        }
        
        // 计算总费用
        $fees['total'] = $fees['base_fee'] + $fees['user_fee'] + 
                        $fees['traffic_fee'] + $fees['node_fee'] + 
                        $fees['revenue_fee'];
        
        // 应用折扣
        if ($subscription && $subscription->custom_discount > 0) {
            $discount = $fees['total'] * ($subscription->custom_discount / 100);
            $fees['discount'] = $discount;
            $fees['total'] -= $discount;
        }
        
        return $fees;
    }

    /**
     * 检查使用量是否超出限制
     */
    public function checkLimits(array $usage): array
    {
        $violations = [];
        
        if ($this->max_users && ($usage['user_count'] ?? 0) > $this->max_users) {
            $violations[] = "User count exceeds limit ({$usage['user_count']} > {$this->max_users})";
        }
        
        if ($this->max_nodes && ($usage['node_count'] ?? 0) > $this->max_nodes) {
            $violations[] = "Node count exceeds limit ({$usage['node_count']} > {$this->max_nodes})";
        }
        
        if ($this->max_traffic) {
            $trafficGb = ($usage['traffic_used'] ?? 0) / (1024 * 1024 * 1024);
            if ($trafficGb > $this->max_traffic) {
                $violations[] = "Traffic exceeds limit ({$trafficGb}GB > {$this->max_traffic}GB)";
            }
        }
        
        if ($this->max_revenue && ($usage['revenue_amount'] ?? 0) > $this->max_revenue) {
            $violations[] = "Revenue exceeds limit ({$usage['revenue_amount']} > {$this->max_revenue})";
        }
        
        return $violations;
    }

    /**
     * 获取方案等级
     */
    public function getTier(): string
    {
        return match($this->code) {
            self::PLAN_FREE => 'free',
            self::PLAN_STARTER => 'starter',
            self::PLAN_PROFESSIONAL => 'professional',
            self::PLAN_ENTERPRISE => 'enterprise',
            default => 'custom',
        };
    }

    /**
     * 获取功能列表
     */
    public function getFeatureList(): array
    {
        $defaultFeatures = [
            'tickets' => false,
            'knowledge' => false,
            'coupons' => false,
            'invites' => false,
            'announcements' => false,
            'custom_theme' => false,
            'api_access' => false,
            'export_data' => false,
            'priority_support' => false,
            'white_label' => false,
        ];
        
        return array_merge($defaultFeatures, $this->features ?? []);
    }

    /**
     * 作用域: 活跃方案
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 作用域: 按排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort')->orderBy('id');
    }
}