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
        // 检查是否已登录
        if (!$request->user()) {
            abort(403, 'Unauthorized access');
        }

        // 检查是否是超级管理员
        // 可以通过以下方式判断：
        // 1. 检查特定的邮箱域名
        // 2. 检查用户ID是否为1
        // 3. 检查是否在超级管理员域名访问
        
        $user = $request->user();
        
        // 方式1：检查是否是第一个用户（ID=1）或特定邮箱
        $superAdminEmails = config('app.super_admin_emails', ['admin@vpnall.com']);
        
        if ($user->id === 1 || in_array($user->email, $superAdminEmails)) {
            return $next($request);
        }
        
        // 方式2：检查是否从超级管理员域名访问
        $adminDomain = config('app.admin_domain', 'admin.vpnall.com');
        if ($request->getHost() === $adminDomain && $user->is_admin) {
            return $next($request);
        }
        
        abort(403, 'Access denied. Super admin privileges required.');
    }
}