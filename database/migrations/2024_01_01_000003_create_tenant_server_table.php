<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 创建租户-节点关联表
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
        
        // 为 ServerGroup 添加租户支持（可选：允许租户创建自己的服务器组）
        Schema::table('v2_server_group', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->boolean('is_global')->default(false)->after('tenant_id'); // 标记是否为全局组
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('v2_server_group', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn(['tenant_id', 'is_global']);
        });
        
        Schema::dropIfExists('tenant_server');
    }
};