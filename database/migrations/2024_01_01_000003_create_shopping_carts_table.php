<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 购物车表迁移
 * 用于管理用户购物车商品
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        Schema::create('shopping_carts', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->string('session_id')->comment('会话ID');
            $table->string('user_email')->nullable()->comment('用户邮箱');
            $table->unsignedBigInteger('goods_id')->comment('商品ID');
            $table->unsignedBigInteger('goods_sku_id')->nullable()->comment('商品规格ID');
            $table->integer('quantity')->default(1)->comment('购买数量');
            $table->decimal('price', 10, 2)->comment('商品单价');
            $table->decimal('total_price', 10, 2)->comment('商品总价');
            $table->json('goods_snapshot')->nullable()->comment('商品信息快照');
            $table->json('sku_snapshot')->nullable()->comment('规格信息快照');
            $table->json('custom_fields')->nullable()->comment('自定义字段数据');
            $table->string('coupon_code')->nullable()->comment('优惠券代码');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('优惠金额');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->timestamps();
            
            // 外键约束
            $table->foreign('goods_id')->references('id')->on('goods')->onDelete('cascade');
            
            // 索引优化
            $table->index(['session_id', 'user_email'], 'idx_session_email');
            $table->index(['goods_id', 'goods_sku_id'], 'idx_goods_sku');
            $table->index('expires_at', 'idx_expires');
            $table->index('created_at', 'idx_created');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_carts');
    }
};
