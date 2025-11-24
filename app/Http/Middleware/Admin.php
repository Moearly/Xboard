<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Auth;
use Closure;
use App\Models\User;

class Admin
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // 获取Authorization header中的token
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // 使用token从数据库查询用户
        // 注意：超级管理员可能跨租户操作，所以需要禁用租户过滤
        /** @var User|null $user */
        $user = User::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('token', $token)
            ->first();
        
        if (!$user || !$user->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // 设置当前认证用户（可选，但有助于后续使用Auth::user()）
        Auth::setUser($user);
        
        return $next($request);
    }
}
