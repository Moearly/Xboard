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
        Schema::table('tenants', function (Blueprint $table) {
            // 资源限制
            $table->integer('max_users')->nullable()->after('config')->comment('最大用户数限制');
            $table->integer('max_orders_per_month')->nullable()->after('max_users')->comment('每月最大订单数');
            $table->integer('max_nodes')->nullable()->after('max_orders_per_month')->comment('最大节点数限制');
            $table->decimal('max_monthly_revenue', 10, 2)->nullable()->after('max_nodes')->comment('月收入上限');
            
            // 功能开关
            $table->json('features')->nullable()->after('config')->comment('功能开关配置');
            
            // 主题配置
            $table->json('theme_config')->nullable()->after('features')->comment('主题配置');
            
            // 统计数据（缓存）
            $table->json('statistics_cache')->nullable()->comment('统计数据缓存');
            $table->timestamp('statistics_updated_at')->nullable()->comment('统计更新时间');
            
            // 联系信息
            $table->string('admin_email')->nullable()->comment('管理员邮箱');
            $table->string('admin_phone')->nullable()->comment('管理员电话');
            $table->text('notes')->nullable()->comment('备注');
        });
        
        // 创建租户操作日志表
        Schema::create('tenant_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 50);
            $table->string('description');
            $table->json('data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'max_users',
                'max_orders_per_month',
                'max_nodes',
                'max_monthly_revenue',
                'features',
                'theme_config',
                'statistics_cache',
                'statistics_updated_at',
                'admin_email',
                'admin_phone',
                'notes'
            ]);
        });
        
        Schema::dropIfExists('tenant_logs');
    }
};