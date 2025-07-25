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
        Schema::create('goods_skus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goods_id')->comment('商品ID');
            $table->string('sku_code')->unique()->comment('SKU编码');
            $table->string('name')->comment('规格名称');
            $table->json('attributes')->comment('规格属性JSON');
            $table->decimal('price', 10, 2)->comment('价格');
            $table->integer('stock')->default(0)->comment('库存');
            $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
            $table->string('image')->nullable()->comment('规格图片');
            $table->integer('sort')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('goods_id')->references('id')->on('goods')->onDelete('cascade');
            $table->index(['goods_id', 'status']);
            $table->index('sku_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_skus');
    }
};
