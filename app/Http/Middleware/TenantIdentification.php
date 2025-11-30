<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantIdentification
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $domain = $request->getHost();
        $adminDomain = config('app.admin_domain', 'admin.vpnall.com');
        
        Log::info('Tenant middleware processing request', [
            'domain' => $domain,
            'admin_domain' => $adminDomain,
            'path' => $request->path(),
            'headers' => [
                'X-Tenant-Domain' => $request->header('X-Tenant-Domain'),
                'X-Super-Admin' => $request->header('X-Super-Admin'),
                'X-Tenant-ID' => $request->header('X-Tenant-ID'),
            ]
        ]);

        // 检查是否为超级管理员域名或请求
        // 使用动态计算的管理路径，确保与路由配置一致
        $securePath = admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))));
        $isAdminPath = $request->path() === $securePath || str_starts_with($request->path(), $securePath . '/');
        $isAdminApi = str_starts_with($request->path(), 'api/v2/admin/') || str_starts_with($request->path(), 'api/v1/admin/');
        
        if ($domain === $adminDomain || 
            $request->header('X-Super-Admin') === 'true' ||
            $isAdminPath ||
            $isAdminApi) {
            Log::info('Super admin request detected', [
                'is_admin_domain' => $domain === $adminDomain,
                'has_super_admin_header' => $request->header('X-Super-Admin') === 'true',
                'is_admin_path' => $isAdminPath,
                'path' => $request->path(),
                'secure_path' => $securePath
            ]);
            
            // 超级管理员可以通过 X-Tenant-ID 指定要操作的租户
            $tenantId = $request->header('X-Tenant-ID');
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    Log::info('Super admin specifying tenant via X-Tenant-ID', [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name
                    ]);
                    app()->singleton('currentTenant', function () use ($tenant) {
                        return $tenant;
                    });
                    $request->merge(['tenant' => $tenant]);
                }
            }
            
            return $next($request);
        }

        $tenant = null;

        // 1. 首先尝试通过域名识别租户
        if ($domain && $domain !== 'localhost' && $domain !== '127.0.0.1') {
            $tenant = Tenant::where('domain', $domain)
                ->where('status', true)
                ->first();
            
            if ($tenant) {
                Log::info('Tenant identified by domain', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'domain' => $domain
                ]);
            }
        }

        // 2. 如果域名识别失败，尝试通过 X-Tenant-Domain 头识别
        if (!$tenant) {
            $tenantDomain = $request->header('X-Tenant-Domain');
            if ($tenantDomain) {
                $tenant = Tenant::where('domain', $tenantDomain)
                    ->where('status', true)
                    ->first();
                
                if ($tenant) {
                    Log::info('Tenant identified by X-Tenant-Domain header', [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'header_domain' => $tenantDomain
                    ]);
                }
            }
        }

        // 3. 如果还是没找到，尝试通过 X-Tenant-ID 头识别（用于超级管理员跨站点配置）
        if (!$tenant) {
            $tenantId = $request->header('X-Tenant-ID');
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                
                if ($tenant && $tenant->status) {
                    Log::info('Tenant identified by X-Tenant-ID header', [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'header_id' => $tenantId
                    ]);
                }
            }
        }

        // 4. 如果还是没找到，尝试通过 X-Tenant-UUID 头识别
        if (!$tenant) {
            $tenantUuid = $request->header('X-Tenant-UUID');
            if ($tenantUuid) {
                $tenant = Tenant::where('uuid', $tenantUuid)
                    ->where('status', true)
                    ->first();
                
                if ($tenant) {
                    Log::info('Tenant identified by X-Tenant-UUID header', [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'uuid' => $tenantUuid
                    ]);
                }
            }
        }

        // 5. 如果还是没找到，尝试通过查询参数 tenant_domain 识别（用于测试）
        if (!$tenant) {
            $tenantDomain = $request->query('tenant_domain');
            if ($tenantDomain) {
                $tenant = Tenant::where('domain', $tenantDomain)
                    ->where('status', true)
                    ->first();
                
                if ($tenant) {
                    Log::info('Tenant identified by tenant_domain query parameter', [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'query_domain' => $tenantDomain
                    ]);
                }
            }
        }

        // 如果没有找到租户，返回错误
        if (!$tenant) {
            Log::warning('No tenant found for request', [
                'domain' => $domain,
                'headers' => $request->headers->all()
            ]);
            
            return response()->json([
                'error' => 'Tenant not found',
                'message' => '租户不存在或域名配置错误'
            ], 404);
        }

        // 检查租户是否过期
        if (!$tenant->isActive()) {
            Log::warning('Tenant is not active', [
                'tenant_id' => $tenant->id,
                'status' => $tenant->status,
                'expire_at' => $tenant->expire_at
            ]);
            
            return response()->json([
                'error' => 'Tenant expired',
                'message' => '租户已过期或被暂停'
            ], 403);
        }

        // 将租户绑定到应用容器
        app()->singleton('currentTenant', function () use ($tenant) {
            return $tenant;
        });

        // 将租户信息添加到请求中
        $request->merge(['tenant' => $tenant]);

        Log::info('Tenant successfully bound to request', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name
        ]);

        return $next($request);
    }
}