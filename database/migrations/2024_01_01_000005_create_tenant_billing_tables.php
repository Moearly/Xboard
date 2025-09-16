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
        // 租户账单表
        Schema::create('tenant_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->index();
            $table->string('bill_number')->unique(); // 账单编号
            $table->date('billing_date'); // 账单日期
            $table->date('period_start'); // 计费周期开始
            $table->date('period_end'); // 计费周期结束
            
            // 费用明细
            $table->decimal('base_fee', 10, 2)->default(0); // 基础费用
            $table->decimal('user_fee', 10, 2)->default(0); // 用户费用
            $table->decimal('traffic_fee', 10, 2)->default(0); // 流量费用
            $table->decimal('node_fee', 10, 2)->default(0); // 节点费用
            $table->decimal('addon_fee', 10, 2)->default(0); // 附加费用
            $table->decimal('discount', 10, 2)->default(0); // 折扣金额
            $table->decimal('total_amount', 10, 2); // 总金额
            $table->decimal('paid_amount', 10, 2)->default(0); // 已付金额
            
            // 使用量统计
            $table->integer('user_count')->default(0); // 用户数量
            $table->bigInteger('traffic_used')->default(0); // 使用流量(字节)
            $table->integer('node_count')->default(0); // 节点数量
            $table->integer('order_count')->default(0); // 订单数量
            $table->decimal('revenue_amount', 10, 2)->default(0); // 收入金额
            
            // 状态
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->timestamp('due_date')->nullable(); // 到期日期
            $table->timestamp('paid_at')->nullable(); // 支付时间
            $table->string('payment_method')->nullable(); // 支付方式
            $table->text('notes')->nullable(); // 备注
            
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['billing_date', 'status']);
            $table->index(['tenant_id', 'status']);
        });
        
        // 租户计费方案表
        Schema::create('tenant_billing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 方案名称
            $table->string('code')->unique(); // 方案代码
            $table->text('description')->nullable(); // 描述
            
            // 基础费用
            $table->decimal('base_fee', 10, 2)->default(0); // 基础月费
            $table->decimal('setup_fee', 10, 2)->default(0); // 安装费
            
            // 用户费用
            $table->integer('free_users')->default(0); // 免费用户数
            $table->decimal('per_user_fee', 10, 2)->default(0); // 每用户费用
            
            // 流量费用
            $table->bigInteger('free_traffic')->default(0); // 免费流量(GB)
            $table->decimal('per_gb_fee', 10, 2)->default(0); // 每GB费用
            
            // 节点费用
            $table->integer('free_nodes')->default(0); // 免费节点数
            $table->decimal('per_node_fee', 10, 2)->default(0); // 每节点费用
            
            // 收入分成
            $table->decimal('revenue_share', 5, 2)->default(0); // 收入分成比例(%)
            $table->decimal('min_revenue_fee', 10, 2)->default(0); // 最低收入费用
            
            // 限制
            $table->integer('max_users')->nullable(); // 最大用户数
            $table->integer('max_nodes')->nullable(); // 最大节点数
            $table->bigInteger('max_traffic')->nullable(); // 最大流量(GB)
            $table->decimal('max_revenue', 10, 2)->nullable(); // 最大收入
            
            // 功能
            $table->json('features')->nullable(); // 包含的功能
            $table->json('limits')->nullable(); // 其他限制
            
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
        
        // 租户计费关联
        Schema::create('tenant_billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->unique();
            $table->unsignedBigInteger('billing_plan_id');
            
            // 订阅信息
            $table->date('start_date'); // 开始日期
            $table->date('next_billing_date'); // 下次计费日期
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            
            // 自定义费用（覆盖方案默认值）
            $table->decimal('custom_base_fee', 10, 2)->nullable();
            $table->decimal('custom_per_user_fee', 10, 2)->nullable();
            $table->decimal('custom_per_gb_fee', 10, 2)->nullable();
            $table->decimal('custom_per_node_fee', 10, 2)->nullable();
            $table->decimal('custom_discount', 5, 2)->nullable(); // 自定义折扣(%)
            
            // 支付信息
            $table->string('payment_method')->nullable();
            $table->json('payment_config')->nullable(); // 支付配置
            $table->boolean('auto_charge')->default(false); // 自动扣费
            
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('billing_plan_id')->references('id')->on('tenant_billing_plans');
        });
        
        // 计费日志
        Schema::create('tenant_billing_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->string('type'); // 类型: charge, payment, refund, adjustment
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_before', 10, 2)->nullable();
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('bill_id')->references('id')->on('tenant_bills')->onDelete('set null');
            $table->index(['tenant_id', 'type']);
            $table->index('created_at');
        });
        
        // 添加余额字段到租户表
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->default(0)->after('status'); // 账户余额
            $table->decimal('credit_limit', 10, 2)->default(0)->after('balance'); // 信用额度
            $table->unsignedBigInteger('billing_plan_id')->nullable()->after('credit_limit');
            $table->boolean('billing_enabled')->default(false)->after('billing_plan_id'); // 是否启用计费
            $table->date('last_billed_at')->nullable()->after('billing_enabled'); // 最后计费时间
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['balance', 'credit_limit', 'billing_plan_id', 'billing_enabled', 'last_billed_at']);
        });
        
        Schema::dropIfExists('tenant_billing_logs');
        Schema::dropIfExists('tenant_billing_subscriptions');
        Schema::dropIfExists('tenant_billing_plans');
        Schema::dropIfExists('tenant_bills');
    }
};