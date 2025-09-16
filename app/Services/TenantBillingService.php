<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantBill;
use App\Models\TenantBillingPlan;
use App\Models\TenantBillingSubscription;
use App\Models\TenantBillingLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantBillingService
{
    /**
     * 生成租户账单
     */
    public static function generateBill(Tenant $tenant, Carbon $billingDate = null): ?TenantBill
    {
        if (!$tenant->billing_enabled) {
            return null;
        }

        $billingDate = $billingDate ?: now();
        
        // 获取订阅信息
        $subscription = TenantBillingSubscription::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();
        
        if (!$subscription) {
            Log::warning("Tenant {$tenant->id} has billing enabled but no active subscription");
            return null;
        }
        
        $plan = $subscription->billingPlan;
        
        // 确定计费周期
        $periodStart = $subscription->last_billed_at ?? $subscription->start_date;
        $periodEnd = self::getNextBillingDate($periodStart, $subscription->billing_cycle);
        
        // 获取使用量统计
        $usage = self::getTenantUsage($tenant, $periodStart, $periodEnd);
        
        // 计算费用
        $fees = $plan->calculateFees($usage, $subscription);
        
        // 创建账单
        $bill = TenantBill::create([
            'tenant_id' => $tenant->id,
            'bill_number' => TenantBill::generateBillNumber(),
            'billing_date' => $billingDate,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'base_fee' => $fees['base_fee'],
            'user_fee' => $fees['user_fee'],
            'traffic_fee' => $fees['traffic_fee'],
            'node_fee' => $fees['node_fee'],
            'addon_fee' => $fees['revenue_fee'] ?? 0,
            'discount' => $fees['discount'] ?? 0,
            'total_amount' => $fees['total'],
            'user_count' => $usage['user_count'],
            'traffic_used' => $usage['traffic_used'],
            'node_count' => $usage['node_count'],
            'order_count' => $usage['order_count'],
            'revenue_amount' => $usage['revenue_amount'],
            'status' => TenantBill::STATUS_PENDING,
            'due_date' => $billingDate->copy()->addDays(7), // 7天后到期
        ]);
        
        // 记录计费日志
        self::logBilling(
            $tenant,
            'charge',
            $bill->total_amount,
            "生成账单 #{$bill->bill_number}",
            $bill->id
        );
        
        // 更新租户最后计费时间
        $tenant->update(['last_billed_at' => $billingDate]);
        
        // 更新订阅下次计费时间
        $subscription->update(['next_billing_date' => $periodEnd]);
        
        return $bill;
    }

    /**
     * 获取租户使用量
     */
    public static function getTenantUsage(Tenant $tenant, $startDate, $endDate): array
    {
        $startTimestamp = Carbon::parse($startDate)->timestamp;
        $endTimestamp = Carbon::parse($endDate)->timestamp;
        
        return [
            'user_count' => $tenant->users()->count(),
            'traffic_used' => DB::table('v2_stat_user')
                ->where('tenant_id', $tenant->id)
                ->whereBetween('record_at', [$startTimestamp, $endTimestamp])
                ->sum(DB::raw('u + d')),
            'node_count' => $tenant->servers()->count(),
            'order_count' => $tenant->orders()
                ->whereBetween('created_at', [$startTimestamp, $endTimestamp])
                ->count(),
            'revenue_amount' => $tenant->orders()
                ->where('status', 3) // 已支付
                ->whereBetween('created_at', [$startTimestamp, $endTimestamp])
                ->sum('total_amount'),
        ];
    }

    /**
     * 处理账单支付
     */
    public static function processBillPayment(
        TenantBill $bill,
        float $amount,
        string $paymentMethod = null
    ): bool {
        if ($bill->status !== TenantBill::STATUS_PENDING) {
            return false;
        }
        
        DB::beginTransaction();
        try {
            // 更新账单状态
            $bill->update([
                'paid_amount' => DB::raw("paid_amount + {$amount}"),
                'payment_method' => $paymentMethod,
            ]);
            
            $bill->refresh();
            
            // 如果全额支付，标记为已支付
            if ($bill->paid_amount >= $bill->total_amount) {
                $bill->markAsPaid($paymentMethod);
            }
            
            // 记录支付日志
            self::logBilling(
                $bill->tenant,
                'payment',
                $amount,
                "支付账单 #{$bill->bill_number}",
                $bill->id
            );
            
            // 更新租户余额
            if ($bill->tenant->balance < 0) {
                $bill->tenant->increment('balance', $amount);
            }
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bill payment failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 自动扣费
     */
    public static function autoCharge(Tenant $tenant, TenantBill $bill): bool
    {
        // 检查是否启用自动扣费
        $subscription = $tenant->billingSubscription;
        if (!$subscription || !$subscription->auto_charge) {
            return false;
        }
        
        // 检查余额是否足够
        $totalDue = $bill->total_amount;
        $availableBalance = $tenant->balance + $tenant->credit_limit;
        
        if ($availableBalance < $totalDue) {
            Log::warning("Tenant {$tenant->id} insufficient balance for auto charge");
            return false;
        }
        
        DB::beginTransaction();
        try {
            // 扣除余额
            $tenant->decrement('balance', $totalDue);
            
            // 标记账单为已支付
            $bill->markAsPaid('balance');
            
            // 记录扣费日志
            self::logBilling(
                $tenant,
                'auto_charge',
                $totalDue,
                "自动扣费 - 账单 #{$bill->bill_number}",
                $bill->id
            );
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Auto charge failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 处理逾期账单
     */
    public static function processOverdueBills(): int
    {
        $count = 0;
        
        TenantBill::pending()
            ->where('due_date', '<', now())
            ->chunk(100, function ($bills) use (&$count) {
                foreach ($bills as $bill) {
                    $bill->markAsOverdue();
                    
                    // 通知租户
                    self::notifyOverdue($bill);
                    
                    // 检查是否需要暂停服务
                    if ($bill->tenant->balance < -$bill->tenant->credit_limit) {
                        self::suspendTenant($bill->tenant);
                    }
                    
                    $count++;
                }
            });
        
        return $count;
    }

    /**
     * 批量生成账单
     */
    public static function batchGenerateBills(): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];
        
        // 获取需要计费的租户
        $tenants = Tenant::where('billing_enabled', true)
            ->whereHas('billingSubscription', function ($query) {
                $query->where('status', 'active')
                      ->where('next_billing_date', '<=', now());
            })
            ->get();
        
        foreach ($tenants as $tenant) {
            try {
                $bill = self::generateBill($tenant);
                if ($bill) {
                    // 尝试自动扣费
                    if (self::autoCharge($tenant, $bill)) {
                        Log::info("Auto charged bill {$bill->id} for tenant {$tenant->id}");
                    }
                    $results['success']++;
                } else {
                    $results['skipped']++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to generate bill for tenant {$tenant->id}: " . $e->getMessage());
                $results['failed']++;
            }
        }
        
        return $results;
    }

    /**
     * 获取下次计费日期
     */
    private static function getNextBillingDate($currentDate, string $cycle): Carbon
    {
        $date = Carbon::parse($currentDate);
        
        return match($cycle) {
            'monthly' => $date->addMonth(),
            'quarterly' => $date->addMonths(3),
            'yearly' => $date->addYear(),
            default => $date->addMonth(),
        };
    }

    /**
     * 记录计费日志
     */
    private static function logBilling(
        Tenant $tenant,
        string $type,
        float $amount,
        string $description,
        int $billId = null
    ): void {
        TenantBillingLog::create([
            'tenant_id' => $tenant->id,
            'bill_id' => $billId,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $tenant->balance,
            'balance_after' => $tenant->balance + ($type === 'payment' ? $amount : -$amount),
            'description' => $description,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * 通知逾期
     */
    private static function notifyOverdue(TenantBill $bill): void
    {
        // TODO: 发送邮件或通知
        Log::info("Bill {$bill->id} is overdue for tenant {$bill->tenant_id}");
    }

    /**
     * 暂停租户服务
     */
    private static function suspendTenant(Tenant $tenant): void
    {
        $tenant->update(['status' => false]);
        
        // 更新订阅状态
        TenantBillingSubscription::where('tenant_id', $tenant->id)
            ->update(['status' => 'suspended']);
        
        Log::warning("Tenant {$tenant->id} suspended due to overdue bills");
    }

    /**
     * 获取计费统计
     */
    public static function getBillingStatistics($startDate = null, $endDate = null): array
    {
        $query = TenantBill::query();
        
        if ($startDate) {
            $query->where('billing_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('billing_date', '<=', $endDate);
        }
        
        return [
            'total_bills' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'paid_amount' => $query->sum('paid_amount'),
            'pending_amount' => $query->pending()->sum('total_amount'),
            'overdue_amount' => $query->overdue()->sum('total_amount'),
            'bills_by_status' => $query->groupBy('status')
                ->selectRaw('status, count(*) as count, sum(total_amount) as amount')
                ->get()
                ->keyBy('status'),
        ];
    }
}