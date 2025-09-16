<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Server;
use App\Models\TenantLog;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * 获取租户列表
     */
    public function index(Request $request)
    {
        $tenants = Tenant::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($tenants);
    }

    /**
     * 创建新租户
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:tenants,domain',
            'status' => 'boolean',
            'expire_at' => 'nullable|date',
            'config' => 'nullable|array',
        ]);

        $tenant = Tenant::create(array_merge($validated, [
            'uuid' => Str::uuid()->toString(),
            'status' => $validated['status'] ?? true,
        ]));

        return response()->json([
            'message' => '租户创建成功',
            'data' => $tenant
        ], 201);
    }

    /**
     * 获取租户详情
     */
    public function show($id)
    {
        $tenant = Tenant::findOrFail($id);
        
        // 加载关联数据统计
        $tenant->loadCount(['users', 'orders', 'plans']);
        
        return response()->json($tenant);
    }

    /**
     * 更新租户信息
     */
    public function update(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'domain' => 'sometimes|string|unique:tenants,domain,' . $id,
            'status' => 'sometimes|boolean',
            'expire_at' => 'nullable|date',
            'config' => 'nullable|array',
        ]);

        $tenant->update($validated);

        return response()->json([
            'message' => '租户更新成功',
            'data' => $tenant
        ]);
    }

    /**
     * 删除租户
     */
    public function destroy($id)
    {
        $tenant = Tenant::findOrFail($id);
        
        // 检查是否有关联数据
        if ($tenant->users()->exists()) {
            return response()->json([
                'message' => '该租户下还有用户，无法删除'
            ], 400);
        }
        
        $tenant->delete();

        return response()->json([
            'message' => '租户删除成功'
        ]);
    }

    /**
     * 切换租户状态
     */
    public function toggleStatus($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->status = !$tenant->status;
        $tenant->save();

        return response()->json([
            'message' => '租户状态已更新',
            'data' => $tenant
        ]);
    }

    /**
     * 获取租户统计信息
     */
    public function statistics($id)
    {
        $tenant = Tenant::findOrFail($id);
        
        $stats = [
            'users_count' => $tenant->users()->count(),
            'active_users_count' => $tenant->users()
                ->where('expired_at', '>', time())
                ->count(),
            'orders_count' => $tenant->orders()->count(),
            'revenue' => $tenant->orders()
                ->where('status', 3) // 已支付
                ->sum('total_amount'),
            'plans_count' => $tenant->plans()->count(),
        ];

        return response()->json($stats);
    }

    /**
     * 获取租户分配的节点
     */
    public function getServers($id)
    {
        $tenant = Tenant::findOrFail($id);
        
        // 获取所有节点
        $allServers = Server::select('id', 'name', 'host', 'port', 'type', 'show')
            ->orderBy('sort')
            ->get();
        
        // 获取租户已分配的节点ID
        $assignedServerIds = $tenant->servers()->pluck('v2_server.id')->toArray();
        
        // 标记已分配的节点
        $servers = $allServers->map(function ($server) use ($assignedServerIds) {
            $server->is_assigned = in_array($server->id, $assignedServerIds);
            return $server;
        });
        
        return response()->json([
            'servers' => $servers,
            'assigned_count' => count($assignedServerIds),
            'total_count' => $allServers->count()
        ]);
    }

    /**
     * 更新租户分配的节点
     */
    public function updateServers(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);
        
        $validated = $request->validate([
            'server_ids' => 'array',
            'server_ids.*' => 'exists:v2_server,id'
        ]);
        
        // 同步节点关联
        $serverIds = $validated['server_ids'] ?? [];
        $syncData = [];
        
        foreach ($serverIds as $serverId) {
            $syncData[$serverId] = ['is_active' => true];
        }
        
        $tenant->servers()->sync($syncData);
        
        return response()->json([
            'message' => '节点分配已更新',
            'assigned_count' => count($serverIds)
        ]);
    }

    /**
     * 批量分配节点给多个租户
     */
    public function batchAssignServers(Request $request)
    {
        $validated = $request->validate([
            'tenant_ids' => 'required|array',
            'tenant_ids.*' => 'exists:tenants,id',
            'server_ids' => 'required|array',
            'server_ids.*' => 'exists:v2_server,id',
            'action' => 'required|in:assign,remove'
        ]);
        
        $tenants = Tenant::whereIn('id', $validated['tenant_ids'])->get();
        $serverIds = $validated['server_ids'];
        $action = $validated['action'];
        
        foreach ($tenants as $tenant) {
            if ($action === 'assign') {
                // 添加节点（不移除现有的）
                $existingIds = $tenant->servers()->pluck('v2_server.id')->toArray();
                $newIds = array_unique(array_merge($existingIds, $serverIds));
                $syncData = [];
                foreach ($newIds as $serverId) {
                    $syncData[$serverId] = ['is_active' => true];
                }
                $tenant->servers()->sync($syncData);
            } else {
                // 移除节点
                $tenant->servers()->detach($serverIds);
            }
        }
        
        return response()->json([
            'message' => $action === 'assign' ? '节点已批量分配' : '节点已批量移除',
            'affected_tenants' => count($tenants)
        ]);
    }

    /**
     * 获取租户健康状态
     */
    public function health($id)
    {
        $tenant = Tenant::findOrFail($id);
        $health = TenantService::getHealthStatus($tenant);
        
        return response()->json($health);
    }

    /**
     * 导出租户数据
     */
    public function export(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);
        $format = $request->get('format', 'json');
        
        $data = TenantService::exportTenantData($tenant, $format);
        
        $filename = "tenant_{$tenant->id}_" . date('YmdHis');
        
        if ($format === 'json') {
            return response($data)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}.json\"");
        } elseif ($format === 'csv') {
            return response($data)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}.csv\"");
        }
    }

    /**
     * 克隆租户
     */
    public function clone(Request $request, $id)
    {
        $sourceTenant = Tenant::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:tenants,domain',
            'admin_email' => 'nullable|email',
        ]);
        
        try {
            $newTenant = TenantService::cloneTenant($sourceTenant, $validated);
            
            return response()->json([
                'message' => '租户克隆成功',
                'data' => $newTenant
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '克隆失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新租户配置
     */
    public function updateConfig(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);
        
        $validated = $request->validate([
            'features' => 'nullable|array',
            'theme_config' => 'nullable|array',
            'max_users' => 'nullable|integer|min:0',
            'max_orders_per_month' => 'nullable|integer|min:0',
            'max_nodes' => 'nullable|integer|min:0',
            'max_monthly_revenue' => 'nullable|numeric|min:0',
        ]);
        
        $tenant->update($validated);
        
        TenantLog::log(
            TenantLog::ACTION_CONFIG,
            '更新租户配置',
            $validated,
            $tenant->id
        );
        
        return response()->json([
            'message' => '配置更新成功',
            'data' => $tenant
        ]);
    }

    /**
     * 获取租户操作日志
     */
    public function logs(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);
        
        $logs = $tenant->logs()
            ->with('user:id,email')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));
        
        return response()->json($logs);
    }

    /**
     * 批量操作
     */
    public function batchAction(Request $request)
    {
        $validated = $request->validate([
            'tenant_ids' => 'required|array',
            'tenant_ids.*' => 'exists:tenants,id',
            'action' => 'required|in:enable,disable,update_config,refresh_stats',
            'data' => 'nullable|array',
        ]);
        
        $tenantIds = $validated['tenant_ids'];
        $action = $validated['action'];
        $data = $validated['data'] ?? [];
        
        switch ($action) {
            case 'enable':
                Tenant::whereIn('id', $tenantIds)->update(['status' => true]);
                $message = '批量启用成功';
                break;
                
            case 'disable':
                Tenant::whereIn('id', $tenantIds)->update(['status' => false]);
                $message = '批量禁用成功';
                break;
                
            case 'update_config':
                TenantService::batchUpdate($tenantIds, $data);
                $message = '批量更新配置成功';
                break;
                
            case 'refresh_stats':
                foreach (Tenant::whereIn('id', $tenantIds)->get() as $tenant) {
                    $tenant->updateStatisticsCache();
                }
                $message = '统计数据刷新成功';
                break;
                
            default:
                return response()->json(['message' => '未知操作'], 400);
        }
        
        return response()->json([
            'message' => $message,
            'affected' => count($tenantIds)
        ]);
    }
}