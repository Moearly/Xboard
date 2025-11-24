<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 移除租户-服务器关联表，改为共享节点模式
     * 所有租户共享同一套节点
     */
    public function up(): void
    {
        // 移除 ServerGroup 的租户字段
        if (Schema::hasColumn('v2_server_group', 'tenant_id')) {
            Schema::table('v2_server_group', function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn(['tenant_id', 'is_global']);
            });
        }
        
        // 删除租户-服务器关联表
        Schema::dropIfExists('tenant_server');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 恢复租户-服务器关联表
        Schema::create('tenant_server', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('server_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['tenant_id', 'server_id']);
            $table->index('tenant_id');
            $table->index('server_id');
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('server_id')->references('id')->on('v2_server')->onDelete('cascade');
        });
        
        // 恢复 ServerGroup 的租户支持
        Schema::table('v2_server_group', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->boolean('is_global')->default(false)->after('tenant_id');
            $table->index('tenant_id');
        });
    }
};

