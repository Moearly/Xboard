<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class TenantIdentification
{
    public function handle(Request $request, Closure $next)
    {
        $domain = $request->getHost();
        
        // 如果是超级管理员域名，跳过租户识别
        if ($domain === config('app.admin_domain', 'admin.vpnall.com')) {
            return $next($request);
        }
        
        // 根据域名查找租户
        $tenant = Tenant::where('domain', $domain)
            ->where('status', true)
            ->first();
        
        if (!$tenant) {
            // 尝试从请求头获取租户信息（用于API调用）
            $tenantUuid = $request->header('X-Tenant-UUID');
            if ($tenantUuid) {
                $tenant = Tenant::where('uuid', $tenantUuid)
                    ->where('status', true)
                    ->first();
            }
        }
        
        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'Invalid domain or tenant'
            ], 404);
        }
        
        // 检查租户是否已过期
        if (!$tenant->isActive()) {
            return response()->json([
                'error' => 'Tenant expired',
                'message' => 'This tenant has expired'
            ], 403);
        }
        
        // 将租户信息绑定到容器
        app()->singleton('currentTenant', function () use ($tenant) {
            return $tenant;
        });
        
        // 添加租户信息到请求
        $request->merge(['tenant' => $tenant]);
        
        return $next($request);
    }
}