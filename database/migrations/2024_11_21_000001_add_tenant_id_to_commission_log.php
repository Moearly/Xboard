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
     * 为佣金记录表添加租户ID字段
     */
    public function up(): void
    {
        Schema::table('v2_commission_log', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
        
        // 为现有数据分配默认租户ID（从关联的用户获取）
        DB::statement('
            UPDATE v2_commission_log cl
            INNER JOIN v2_user u ON cl.user_id = u.id
            SET cl.tenant_id = u.tenant_id
            WHERE cl.tenant_id IS NULL AND u.tenant_id IS NOT NULL
        ');
        
        // 如果用户表没有tenant_id，则使用默认租户ID
        DB::statement('
            UPDATE v2_commission_log
            SET tenant_id = 1
            WHERE tenant_id IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_commission_log', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};

