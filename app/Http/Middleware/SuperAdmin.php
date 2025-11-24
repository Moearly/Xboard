<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 检查是否已登录 - 使用Auth::user()而不是$request->user()
        // 因为Admin中间件使用Auth::setUser()设置用户
        $user = auth()->user();
        
        if (!$user) {
            abort(403, 'Unauthorized access');
        }

        // 检查是否是超级管理员
        // 可以通过以下方式判断：
        // 1. 检查用户是否是管理员（is_admin字段）
        // 2. 检查特定的邮箱
        // 3. 检查是否从超级管理员域名或localhost访问
        
        // 方式1：检查是否是管理员用户
        if ($user->is_admin) {
            return $next($request);
        }
        
        // 方式2：检查是否是特定的超级管理员邮箱
        $superAdminEmails = config('app.super_admin_emails', ['admin@vpnall.com']);
        if (in_array($user->email, $superAdminEmails)) {
            return $next($request);
        }
        
        // 方式3：检查是否从超级管理员域名或localhost访问
        $adminDomain = config('app.admin_domain', 'admin.vpnall.com');
        $host = $request->getHost();
        if (($host === $adminDomain || $host === 'localhost' || $host === '127.0.0.1') && $user->is_admin) {
            return $next($request);
        }
        
        abort(403, 'Access denied. Super admin privileges required.');
    }
}