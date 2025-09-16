<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantBill extends Model
{
    protected $fillable = [
        'tenant_id',
        'bill_number',
        'billing_date',
        'period_start',
        'period_end',
        'base_fee',
        'user_fee',
        'traffic_fee',
        'node_fee',
        'addon_fee',
        'discount',
        'total_amount',
        'paid_amount',
        'user_count',
        'traffic_used',
        'node_count',
        'order_count',
        'revenue_amount',
        'status',
        'due_date',
        'paid_at',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'billing_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'base_fee' => 'decimal:2',
        'user_fee' => 'decimal:2',
        'traffic_fee' => 'decimal:2',
        'node_fee' => 'decimal:2',
        'addon_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'revenue_amount' => 'decimal:2',
    ];

    // 状态常量
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * 关联租户
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 关联计费日志
     */
    public function logs(): HasMany
    {
        return $this->hasMany(TenantBillingLog::class, 'bill_id');
    }

    /**
     * 生成账单编号
     */
    public static function generateBillNumber(): string
    {
        $prefix = 'BILL';
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "{$prefix}{$date}{$random}";
    }

    /**
     * 计算总金额
     */
    public function calculateTotal(): float
    {
        $total = $this->base_fee + $this->user_fee + $this->traffic_fee + 
                 $this->node_fee + $this->addon_fee - $this->discount;
        return max(0, $total);
    }

    /**
     * 获取未付金额
     */
    public function getUnpaidAmount(): float
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    /**
     * 检查是否逾期
     */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING && 
               $this->due_date && 
               $this->due_date->isPast();
    }

    /**
     * 标记为已支付
     */
    public function markAsPaid(string $paymentMethod = null): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'paid_amount' => $this->total_amount,
            'payment_method' => $paymentMethod,
        ]);
    }

    /**
     * 标记为逾期
     */
    public function markAsOverdue(): void
    {
        if ($this->status === self::STATUS_PENDING) {
            $this->update(['status' => self::STATUS_OVERDUE]);
        }
    }

    /**
     * 取消账单
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'notes' => $reason ?: $this->notes,
        ]);
    }

    /**
     * 获取状态标签
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => '待支付',
            self::STATUS_PAID => '已支付',
            self::STATUS_OVERDUE => '已逾期',
            self::STATUS_CANCELLED => '已取消',
            default => '未知',
        };
    }

    /**
     * 获取状态颜色
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_PAID => 'success',
            self::STATUS_OVERDUE => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * 作用域: 待支付
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * 作用域: 已支付
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * 作用域: 逾期
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE)
                     ->orWhere(function ($q) {
                         $q->where('status', self::STATUS_PENDING)
                           ->where('due_date', '<', now());
                     });
    }
}