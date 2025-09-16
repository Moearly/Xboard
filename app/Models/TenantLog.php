<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'description',
        'data',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 日志所属租户
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 操作用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 记录日志
     */
    public static function log($action, $description, $data = null, $tenantId = null)
    {
        if (!$tenantId && app()->has('currentTenant')) {
            $tenantId = app('currentTenant')->id;
        }

        return static::create([
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'data' => $data,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * 常用操作类型
     */
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_PAYMENT = 'payment';
    const ACTION_CONFIG = 'config';
    const ACTION_NODE_ASSIGN = 'node_assign';
    const ACTION_EXPORT = 'export';
}