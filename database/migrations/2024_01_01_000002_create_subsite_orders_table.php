<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 分站订单关联表迁移
 * 用于记录分站订单和佣金结算
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        Schema::create('subsite_orders', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('subsite_id')->comment('分站ID');
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->string('subsite_order_sn')->nullable()->comment('分站订单号');
            $table->decimal('commission_amount', 10, 2)->default(0)->comment('佣金金额');
            $table->tinyInteger('commission_status')->default(0)->comment('佣金状态：0=未结算，1=已结算');
            $table->timestamp('commission_settled_at')->nullable()->comment('佣金结算时间');
            $table->json('sync_data')->nullable()->comment('同步数据');
            $table->tinyInteger('sync_status')->default(0)->comment('同步状态：0=未同步，1=已同步，2=同步失败');
            $table->timestamp('synced_at')->nullable()->comment('同步时间');
            $table->text('sync_error')->nullable()->comment('同步错误信息');
            $table->integer('retry_count')->default(0)->comment('重试次数');
            $table->timestamp('next_retry_at')->nullable()->comment('下次重试时间');
            $table->timestamps();
            
            // 外键约束
            $table->foreign('subsite_id')->references('id')->on('subsites')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            
            // 索引优化
            $table->index(['subsite_id', 'commission_status'], 'idx_subsite_commission');
            $table->index(['order_id', 'sync_status'], 'idx_order_sync');
            $table->index('commission_settled_at', 'idx_commission_settled');
            $table->index('synced_at', 'idx_synced');
            $table->index('next_retry_at', 'idx_next_retry');
            $table->unique(['subsite_id', 'order_id'], 'uk_subsite_order');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('subsite_orders');
    }
};
