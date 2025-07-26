<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商品规格表迁移
 * 用于管理商品的多规格信息
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        Schema::create('goods_skus', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('goods_id')->comment('商品ID');
            $table->string('sku_code')->unique()->comment('SKU编码');
            $table->string('name')->comment('规格名称');
            $table->json('attributes')->comment('规格属性JSON');
            $table->decimal('price', 10, 2)->comment('规格价格');
            $table->decimal('wholesale_price', 10, 2)->nullable()->comment('批发价格');
            $table->decimal('cost_price', 10, 2)->nullable()->comment('成本价格');
            $table->integer('stock')->default(0)->comment('库存数量');
            $table->integer('sold_count')->default(0)->comment('销售数量');
            $table->integer('warning_stock')->default(10)->comment('库存预警数量');
            $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
            $table->string('image')->nullable()->comment('规格图片');
            $table->decimal('weight', 8, 2)->nullable()->comment('重量(kg)');
            $table->string('barcode')->nullable()->comment('条形码');
            $table->string('supplier_code')->nullable()->comment('供应商编码');
            $table->integer('sort')->default(0)->comment('排序权重');
            $table->json('extra_data')->nullable()->comment('扩展数据');
            $table->timestamps();
            $table->softDeletes();
            
            // 外键约束
            $table->foreign('goods_id')->references('id')->on('goods')->onDelete('cascade');
            
            // 索引优化
            $table->index(['goods_id', 'status'], 'idx_goods_status');
            $table->index(['sku_code', 'status'], 'idx_sku_status');
            $table->index(['stock', 'status'], 'idx_stock_status');
            $table->index('warning_stock', 'idx_warning_stock');
            $table->index('sort', 'idx_sort');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_skus');
    }
};
