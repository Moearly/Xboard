<?php

namespace App\Traits;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait for a model.
     *
     * @return void
     */
    protected static function bootBelongsToTenant()
    {
        // 添加全局作用域，自动过滤租户数据
        static::addGlobalScope(new TenantScope);
        
        // 创建模型时自动设置 tenant_id
        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && app()->has('currentTenant')) {
                $model->tenant_id = app('currentTenant')->id;
            }
        });
    }
    
    /**
     * 租户关联
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}

