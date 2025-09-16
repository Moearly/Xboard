<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantBill;
use App\Models\TenantBillingPlan;
use App\Models\TenantBillingSubscription;
use App\Models\TenantBillingLog;
use App\Services\TenantBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TenantBillingController extends Controller
{
    /**
     * 获取计费方案列表
     */
    public function plans(Request $request)
    {
        $plans = TenantBillingPlan::query()
            ->when($request->active_only, function ($query) {
                $query->where('is_active', true);
            })
            ->withCount('subscriptions')
            ->ordered()
            ->paginate($request->per_page ?? 15);

        return response()->json($plans);
    }

    /**
     * 创建计费方案
     */
    public function createPlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:tenant_billing_plans,code',
            'description' => 'nullable|string',
            'base_fee' => 'required|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'free_users' => 'nullable|integer|min:0',
            'per_user_fee' => 'nullable|numeric|min:0',
            'free_traffic' => 'nullable|integer|min:0',
            'per_gb_fee' => 'nullable|numeric|min:0',
            'free_nodes' => 'nullable|integer|min:0',
            'per_node_fee' => 'nullable|numeric|min:0',
            'revenue_share' => 'nullable|numeric|min:0|max:100',
            'min_revenue_fee' => 'nullable|numeric|min:0',
            'max_users' => 'nullable|integer|min:0',
            'max_nodes' => 'nullable|integer|min:0',
            'max_traffic' => 'nullable|integer|min:0',
            'max_revenue' => 'nullable|numeric|min:0',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $plan = TenantBillingPlan::create($validated);

        return response()->json([
            'message' => '计费方案创建成功',
            'data' => $plan
        ], 201);
    }

    /**
     * 更新计费方案
     */
    public function updatePlan(Request $request, $id)
    {
        $plan = TenantBillingPlan::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:tenant_billing_plans,code,' . $id,
            'description' => 'nullable|string',
            'base_fee' => 'sometimes|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'free_users' => 'nullable|integer|min:0',
            'per_user_fee' => 'nullable|numeric|min:0',
            'free_traffic' => 'nullable|integer|min:0',
            'per_gb_fee' => 'nullable|numeric|min:0',
            'free_nodes' => 'nullable|integer|min:0',
            'per_node_fee' => 'nullable|numeric|min:0',
            'revenue_share' => 'nullable|numeric|min:0|max:100',
            'min_revenue_fee' => 'nullable|numeric|min:0',
            'max_users' => 'nullable|integer|min:0',
            'max_nodes' => 'nullable|integer|min:0',
            'max_traffic' => 'nullable|integer|min:0',
            'max_revenue' => 'nullable|numeric|min:0',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $plan->update($validated);

        return response()->json([
            'message' => '计费方案更新成功',
            'data' => $plan
        ]);
    }

    /**
     * 删除计费方案
     */
    public function deletePlan($id)
    {
        $plan = TenantBillingPlan::findOrFail($id);
        
        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'message' => '该方案有活跃订阅，无法删除'
            ], 400);
        }
        
        $plan->delete();

        return response()->json([
            'message' => '计费方案删除成功'
        ]);
    }

    /**
     * 获取租户订阅信息
     */
    public function getSubscription($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        $subscription = TenantBillingSubscription::where('tenant_id', $tenantId)
            ->with('billingPlan')
            ->first();
        
        if (!$subscription) {
            return response()->json([
                'message' => '该租户没有订阅'
            ], 404);
        }
        
        return response()->json($subscription);
    }

    /**
     * 创建或更新租户订阅
     */
    public function upsertSubscription(Request $request, $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        $validated = $request->validate([
            'billing_plan_id' => 'required|exists:tenant_billing_plans,id',
            'billing_cycle' => 'required|in:monthly,quarterly,yearly',
            'custom_base_fee' => 'nullable|numeric|min:0',
            'custom_per_user_fee' => 'nullable|numeric|min:0',
            'custom_per_gb_fee' => 'nullable|numeric|min:0',
            'custom_per_node_fee' => 'nullable|numeric|min:0',
            'custom_discount' => 'nullable|numeric|min:0|max:100',
            'auto_charge' => 'boolean',
            'payment_method' => 'nullable|string',
        ]);
        
        DB::beginTransaction();
        try {
            $subscription = TenantBillingSubscription::updateOrCreate(
                ['tenant_id' => $tenantId],
                array_merge($validated, [
                    'start_date' => now(),
                    'next_billing_date' => TenantBillingService::getNextBillingDate(
                        now(),
                        $validated['billing_cycle']
                    ),
                    'status' => 'active',
                ])
            );
            
            // 更新租户计费状态
            $tenant->update([
                'billing_enabled' => true,
                'billing_plan_id' => $validated['billing_plan_id'],
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => '订阅' . ($subscription->wasRecentlyCreated ? '创建' : '更新') . '成功',
                'data' => $subscription->load('billingPlan')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => '操作失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 取消租户订阅
     */
    public function cancelSubscription($tenantId)
    {
        $subscription = TenantBillingSubscription::where('tenant_id', $tenantId)->first();
        
        if (!$subscription) {
            return response()->json([
                'message' => '该租户没有订阅'
            ], 404);
        }
        
        $subscription->update(['status' => 'cancelled']);
        
        Tenant::find($tenantId)->update(['billing_enabled' => false]);
        
        return response()->json([
            'message' => '订阅已取消'
        ]);
    }

    /**
     * 获取租户账单列表
     */
    public function getBills(Request $request, $tenantId = null)
    {
        $query = TenantBill::query();
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        $query->when($request->status, function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($request->start_date, function ($q, $date) {
                $q->where('billing_date', '>=', $date);
            })
            ->when($request->end_date, function ($q, $date) {
                $q->where('billing_date', '<=', $date);
            });
        
        $bills = $query->with('tenant:id,name,domain')
            ->orderBy('billing_date', 'desc')
            ->paginate($request->per_page ?? 15);
        
        return response()->json($bills);
    }

    /**
     * 获取账单详情
     */
    public function getBillDetail($id)
    {
        $bill = TenantBill::with(['tenant', 'logs'])->findOrFail($id);
        
        return response()->json($bill);
    }

    /**
     * 手动生成账单
     */
    public function generateBill(Request $request, $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        if (!$tenant->billing_enabled) {
            return response()->json([
                'message' => '该租户未启用计费'
            ], 400);
        }
        
        try {
            $bill = TenantBillingService::generateBill($tenant);
            
            if (!$bill) {
                return response()->json([
                    'message' => '无法生成账单，请检查订阅状态'
                ], 400);
            }
            
            return response()->json([
                'message' => '账单生成成功',
                'data' => $bill
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '生成账单失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 支付账单
     */
    public function payBill(Request $request, $id)
    {
        $bill = TenantBill::findOrFail($id);
        
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
        ]);
        
        $success = TenantBillingService::processBillPayment(
            $bill,
            $validated['amount'],
            $validated['payment_method']
        );
        
        if ($success) {
            return response()->json([
                'message' => '支付成功',
                'data' => $bill->fresh()
            ]);
        }
        
        return response()->json([
            'message' => '支付失败'
        ], 400);
    }

    /**
     * 取消账单
     */
    public function cancelBill(Request $request, $id)
    {
        $bill = TenantBill::findOrFail($id);
        
        if ($bill->status === TenantBill::STATUS_PAID) {
            return response()->json([
                'message' => '已支付账单无法取消'
            ], 400);
        }
        
        $bill->cancel($request->reason);
        
        return response()->json([
            'message' => '账单已取消'
        ]);
    }

    /**
     * 批量生成账单
     */
    public function batchGenerateBills()
    {
        $results = TenantBillingService::batchGenerateBills();
        
        return response()->json([
            'message' => '批量生成完成',
            'data' => $results
        ]);
    }

    /**
     * 处理逾期账单
     */
    public function processOverdueBills()
    {
        $count = TenantBillingService::processOverdueBills();
        
        return response()->json([
            'message' => "处理了 {$count} 个逾期账单"
        ]);
    }

    /**
     * 获取计费日志
     */
    public function getBillingLogs(Request $request, $tenantId = null)
    {
        $query = TenantBillingLog::query();
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        $logs = $query->when($request->type, function ($q, $type) {
                $q->where('type', $type);
            })
            ->when($request->start_date, function ($q, $date) {
                $q->where('created_at', '>=', $date);
            })
            ->when($request->end_date, function ($q, $date) {
                $q->where('created_at', '<=', $date);
            })
            ->with('tenant:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);
        
        return response()->json($logs);
    }

    /**
     * 获取计费统计
     */
    public function getStatistics(Request $request)
    {
        $stats = TenantBillingService::getBillingStatistics(
            $request->start_date,
            $request->end_date
        );
        
        // 添加更多统计信息
        $stats['active_subscriptions'] = TenantBillingSubscription::where('status', 'active')->count();
        $stats['total_tenants_billed'] = Tenant::where('billing_enabled', true)->count();
        
        // 按方案统计
        $stats['subscriptions_by_plan'] = TenantBillingSubscription::query()
            ->join('tenant_billing_plans', 'tenant_billing_subscriptions.billing_plan_id', '=', 'tenant_billing_plans.id')
            ->groupBy('billing_plan_id', 'tenant_billing_plans.name')
            ->selectRaw('billing_plan_id, tenant_billing_plans.name as plan_name, count(*) as count')
            ->get();
        
        // 月度趋势
        $stats['monthly_trend'] = TenantBill::query()
            ->when($request->start_date, function ($q, $date) {
                $q->where('billing_date', '>=', $date);
            })
            ->when($request->end_date, function ($q, $date) {
                $q->where('billing_date', '<=', $date);
            })
            ->selectRaw('DATE_FORMAT(billing_date, "%Y-%m") as month, count(*) as bills, sum(total_amount) as amount')
            ->groupBy('month')
            ->orderBy('month')
            ->get();
        
        return response()->json($stats);
    }

    /**
     * 更新租户余额
     */
    public function updateBalance(Request $request, $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'type' => 'required|in:add,deduct,set',
            'description' => 'required|string',
        ]);
        
        DB::beginTransaction();
        try {
            $balanceBefore = $tenant->balance;
            
            switch ($validated['type']) {
                case 'add':
                    $tenant->increment('balance', $validated['amount']);
                    break;
                case 'deduct':
                    $tenant->decrement('balance', $validated['amount']);
                    break;
                case 'set':
                    $tenant->update(['balance' => $validated['amount']]);
                    break;
            }
            
            // 记录日志
            TenantBillingLog::create([
                'tenant_id' => $tenantId,
                'type' => 'adjustment',
                'amount' => $validated['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $tenant->fresh()->balance,
                'description' => $validated['description'],
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => '余额更新成功',
                'data' => [
                    'balance_before' => $balanceBefore,
                    'balance_after' => $tenant->balance,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }
}