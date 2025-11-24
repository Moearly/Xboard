<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('v2_settings', function (Blueprint $table) {
            // 添加 tenant_id 字段
            $table->unsignedInteger('tenant_id')->default(1)->after('id');
            
            // 添加复合索引优化查询性能
            $table->index(['tenant_id', 'name'], 'idx_settings_tenant_name');
        });
        
        // 删除旧的唯一索引（如果存在）
        try {
            Schema::table('v2_settings', function (Blueprint $table) {
                $table->dropUnique(['name']);
            });
        } catch (\Exception $e) {
            // 如果不存在该索引，忽略错误
        }
        
        // 添加新的复合唯一索引
        Schema::table('v2_settings', function (Blueprint $table) {
            $table->unique(['tenant_id', 'name'], 'unique_tenant_name');
        });
        
        // 为现有数据分配默认租户ID
        DB::table('v2_settings')
            ->whereNull('tenant_id')
            ->orWhere('tenant_id', 0)
            ->update(['tenant_id' => 1]);
            
        // 添加外键约束（可选，如果 tenants 表存在）
        if (Schema::hasTable('tenants')) {
            Schema::table('v2_settings', function (Blueprint $table) {
                $table->foreign('tenant_id', 'fk_settings_tenant')
                    ->references('id')
                    ->on('tenants')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('v2_settings', function (Blueprint $table) {
            // 删除外键约束
            try {
                $table->dropForeign('fk_settings_tenant');
            } catch (\Exception $e) {
                // 忽略错误
            }
            
            // 删除索引
            $table->dropIndex('idx_settings_tenant_name');
            $table->dropUnique('unique_tenant_name');
            
            // 删除字段
            $table->dropColumn('tenant_id');
        });
        
        // 恢复原有的 name 唯一索引
        try {
            Schema::table('v2_settings', function (Blueprint $table) {
                $table->unique('name');
            });
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
};

