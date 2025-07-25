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
        Schema::create('shopping_carts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->comment('会话ID');
            $table->string('user_email')->nullable()->comment('用户邮箱');
            $table->unsignedBigInteger('goods_id')->comment('商品ID');
            $table->unsignedBigInteger('goods_sku_id')->nullable()->comment('商品规格ID');
            $table->integer('quantity')->default(1)->comment('数量');
            $table->decimal('price', 10, 2)->comment('单价');
            $table->json('goods_info')->nullable()->comment('商品信息快照');
            $table->timestamps();
            
            $table->foreign('goods_id')->references('id')->on('goods')->onDelete('cascade');
            $table->index(['session_id', 'user_email']);
            $table->index('goods_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_carts');
    }
};
