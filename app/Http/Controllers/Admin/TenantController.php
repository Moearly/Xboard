<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * 获取租户列表
     */
    public function index(Request $request)
    {
        $query = Tenant::query();

        // 搜索
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%")
                  ->orWhere('admin_email', 'like', "%{$search}%");
            });
        }

        // 状态筛选
        if ($request->has('status') && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->where('status', true);
            } elseif ($request->status === 'inactive') {
                $query->where('status', false);
            }
        }

        $perPage = $request->get('pageSize', 20);
        $tenants = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // 为每个租户更新统计缓存
        $tenants->getCollection()->transform(function ($tenant) {
            $tenant->statistics = $tenant->getStatistics();
            return $tenant;
        });

        return response()->json([
            'data' => $tenants->items(),
            'total' => $tenants->total(),
            'current_page' => $tenants->currentPage(),
            'per_page' => $tenants->perPage(),
            'last_page' => $tenants->lastPage(),
        ]);
    }

    /**
     * 获取单个租户详情
     */
    public function show($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->statistics = $tenant->getStatistics(true); // 强制刷新统计

        return response()->json([
            'data' => $tenant
        ]);
    }

    /**
     * 创建租户
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants,domain',
            'admin_email' => 'required|email|max:255',
            'admin_phone' => 'nullable|string|max:20',
            'expire_at' => 'nullable|date',
            'limits.max_users' => 'required|integer|min:1',
            'limits.max_orders_per_month' => 'required|integer|min:1',
            'limits.max_nodes' => 'required|integer|min:1',
            'limits.max_monthly_revenue' => 'required|numeric|min:1',
            'features' => 'required|array',
            'config' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $tenant = Tenant::create([
                'uuid' => Str::uuid()->toString(),
                'name' => $request->name,
                'domain' => $request->domain,
                'status' => true,
                'admin_email' => $request->admin_email,
                'admin_phone' => $request->admin_phone,
                'expire_at' => $request->expire_at,
                'max_users' => $request->input('limits.max_users'),
                'max_orders_per_month' => $request->input('limits.max_orders_per_month'),
                'max_nodes' => $request->input('limits.max_nodes'),
                'max_monthly_revenue' => $request->input('limits.max_monthly_revenue'),
                'features' => $request->features,
                'config' => $request->config ?? [],
                'theme_config' => [
                    'primary_color' => '#3b82f6',
                    'secondary_color' => '#64748b',
                    'theme_name' => 'default',
                    'dark_mode_enabled' => false,
                ],
            ]);

            // 初始化统计缓存
            $tenant->updateStatisticsCache();

            DB::commit();

            return response()->json([
                'data' => $tenant,
                'message' => '租户创建成功'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Creation failed',
                'message' => '创建租户失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新租户
     */
    public function update(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'domain' => 'sometimes|string|max:255|unique:tenants,domain,' . $id,
            'admin_email' => 'sometimes|email|max:255',
            'admin_phone' => 'nullable|string|max:20',
            'expire_at' => 'nullable|date',
            'status' => 'sometimes|boolean',
            'limits.max_users' => 'sometimes|integer|min:1',
            'limits.max_orders_per_month' => 'sometimes|integer|min:1',
            'limits.max_nodes' => 'sometimes|integer|min:1',
            'limits.max_monthly_revenue' => 'sometimes|numeric|min:1',
            'features' => 'sometimes|array',
            'config' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $updateData = $request->only([
                'name', 'domain', 'admin_email', 'admin_phone', 
                'expire_at', 'status', 'features', 'config'
            ]);

            // 处理 limits 数据
            if ($request->has('limits')) {
                $limits = $request->limits;
                if (isset($limits['max_users'])) {
                    $updateData['max_users'] = $limits['max_users'];
                }
                if (isset($limits['max_orders_per_month'])) {
                    $updateData['max_orders_per_month'] = $limits['max_orders_per_month'];
                }
                if (isset($limits['max_nodes'])) {
                    $updateData['max_nodes'] = $limits['max_nodes'];
                }
                if (isset($limits['max_monthly_revenue'])) {
                    $updateData['max_monthly_revenue'] = $limits['max_monthly_revenue'];
                }
            }

            $tenant->update($updateData);

            // 刷新统计缓存
            $tenant->updateStatisticsCache();

            DB::commit();

            return response()->json([
                'data' => $tenant->fresh(),
                'message' => '租户更新成功'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Update failed',
                'message' => '更新租户失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除租户
     */
    public function destroy($id)
    {
        $tenant = Tenant::findOrFail($id);

        DB::beginTransaction();
        try {
            // 检查是否有关联数据
            $userCount = $tenant->users()->count();
            $orderCount = $tenant->orders()->count();

            if ($userCount > 0 || $orderCount > 0) {
                return response()->json([
                    'error' => 'Cannot delete tenant',
                    'message' => "无法删除租户，存在关联数据：{$userCount} 个用户，{$orderCount} 个订单"
                ], 400);
            }

            $tenant->delete();

            DB::commit();

            return response()->json([
                'message' => '租户删除成功'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Delete failed',
                'message' => '删除租户失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新租户状态
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive,suspended,expired'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $tenant = Tenant::findOrFail($id);

        $statusMap = [
            'active' => true,
            'inactive' => false,
            'suspended' => false,
            'expired' => false,
        ];

        $tenant->update([
            'status' => $statusMap[$request->status]
        ]);

        return response()->json([
            'data' => $tenant,
            'message' => '租户状态更新成功'
        ]);
    }

    /**
     * 获取所有服务器（共享模式）
     * 所有租户共享同一套节点，不再需要分配
     */
    public function getAllServers(Request $request)
    {
        $servers = Server::where('show', 1)->get();

            return response()->json([
            'data' => $servers,
            'message' => '所有租户共享这些节点'
        ]);
    }

    /**
     * 获取租户可用的服务器（共享模式）
     */
    public function getTenantServers($id)
    {
        $tenant = Tenant::findOrFail($id);

        // 共享模式：所有租户都可以访问所有显示的节点
        $servers = Server::where('show', 1)->get();

        return response()->json([
            'data' => $servers,
            'message' => '共享节点模式：所有租户可访问所有显示的节点'
        ]);
    }

    /**
     * 获取全局统计
     */
    public function getGlobalStatistics()
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', true)->count(),
            'total_users' => User::count(),
            'total_revenue' => Order::where('status', 3)->sum('total_amount') / 100,
            'server_utilization' => $this->calculateServerUtilization(),
            'monthly_growth' => $this->calculateMonthlyGrowth(),
            'top_tenants' => $this->getTopTenants(),
        ];

        return response()->json($stats);
    }

    /**
     * 获取租户统计
     */
    public function getTenantStatistics()
    {
        $tenants = Tenant::all();
        $revenueByTenant = [];

        foreach ($tenants as $tenant) {
            $revenue = $tenant->orders()
                ->where('status', 3)
                ->sum('total_amount') / 100;

            $revenueByTenant[] = [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'revenue' => $revenue
            ];
        }

        return response()->json([
            'total_tenants' => $tenants->count(),
            'active_tenants' => $tenants->where('status', true)->count(),
            'suspended_tenants' => $tenants->where('status', false)->count(),
            'total_users_across_tenants' => User::count(),
            'revenue_by_tenant' => $revenueByTenant
        ]);
    }

    /**
     * 获取当前租户信息（用于普通租户）
     */
    public function getCurrentTenant(Request $request)
    {
        if (!app()->has('currentTenant')) {
            return response()->json([
                'error' => 'No tenant context',
                'message' => '未找到租户上下文'
            ], 404);
        }

        $tenant = app('currentTenant');
        $tenant->statistics = $tenant->getStatistics();

        return response()->json([
            'data' => $tenant
        ]);
    }

    /**
     * 获取当前租户统计（用于普通租户）
     */
    public function getTenantStats(Request $request)
    {
        if (!app()->has('currentTenant')) {
            return response()->json([
                'error' => 'No tenant context',
                'message' => '未找到租户上下文'
            ], 404);
        }

        $tenant = app('currentTenant');
        $stats = $tenant->getStatistics(true); // 强制刷新

        $resourceUsage = [
            'users' => [
                'current' => $stats['users_count'],
                'limit' => $tenant->max_users
            ],
            'orders' => [
                'current' => $stats['monthly_orders'],
                'limit' => $tenant->max_orders_per_month
            ],
            'revenue' => [
                'current' => $stats['monthly_revenue'],
                'limit' => $tenant->max_monthly_revenue
            ]
        ];

        return response()->json([
            'users_count' => $stats['users_count'],
            'orders_count' => $stats['orders_count'],
            'monthly_revenue' => $stats['monthly_revenue'],
            'resource_usage' => $resourceUsage
        ]);
    }

    /**
     * 计算服务器利用率
     */
    private function calculateServerUtilization()
    {
        $totalServers = Server::count();
        $usedServers = DB::table('tenant_server')->distinct('server_id')->count();

        return $totalServers > 0 ? round(($usedServers / $totalServers) * 100, 2) : 0;
    }

    /**
     * 计算月增长率
     */
    private function calculateMonthlyGrowth()
    {
        $currentMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $lastMonth = User::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        if ($lastMonth == 0) {
            return $currentMonth > 0 ? 100 : 0;
        }

        return round((($currentMonth - $lastMonth) / $lastMonth) * 100, 2);
    }

    /**
     * 获取顶级租户
     */
    private function getTopTenants()
    {
        return Tenant::select('id', 'name')
            ->withCount('users')
            ->get()
            ->map(function ($tenant) {
                $monthlyRevenue = $tenant->getCurrentMonthRevenue();
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'users_count' => $tenant->users_count,
                    'monthly_revenue' => $monthlyRevenue
                ];
            })
            ->sortByDesc('monthly_revenue')
            ->take(5)
            ->values();
    }
}
