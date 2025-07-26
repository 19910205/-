<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为现有表添加SKU支持
 * 扩展订单和卡密表以支持多规格
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        // 为订单表添加SKU支持
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('goods_sku_id')->nullable()->after('goods_id')->comment('商品规格ID');
            $table->json('sku_snapshot')->nullable()->after('info')->comment('规格信息快照');
            $table->foreign('goods_sku_id')->references('id')->on('goods_skus')->onDelete('set null');
            $table->index('goods_sku_id', 'idx_goods_sku_id');
        });

        // 为卡密表添加SKU支持
        Schema::table('carmis', function (Blueprint $table) {
            $table->unsignedBigInteger('goods_sku_id')->nullable()->after('goods_id')->comment('商品规格ID');
            $table->foreign('goods_sku_id')->references('id')->on('goods_skus')->onDelete('set null');
            $table->index(['goods_id', 'goods_sku_id', 'status'], 'idx_goods_sku_status');
        });

        // 为商品表添加多规格支持标识
        Schema::table('goods', function (Blueprint $table) {
            $table->tinyInteger('has_sku')->default(0)->after('type')->comment('是否有多规格：0=否，1=是');
            $table->decimal('min_price', 10, 2)->nullable()->after('actual_price')->comment('最低价格');
            $table->decimal('max_price', 10, 2)->nullable()->after('min_price')->comment('最高价格');
            $table->index('has_sku', 'idx_has_sku');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['goods_sku_id']);
            $table->dropIndex('idx_goods_sku_id');
            $table->dropColumn(['goods_sku_id', 'sku_snapshot']);
        });

        Schema::table('carmis', function (Blueprint $table) {
            $table->dropForeign(['goods_sku_id']);
            $table->dropIndex('idx_goods_sku_status');
            $table->dropColumn('goods_sku_id');
        });

        Schema::table('goods', function (Blueprint $table) {
            $table->dropIndex('idx_has_sku');
            $table->dropColumn(['has_sku', 'min_price', 'max_price']);
        });
    }
};
