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
        Schema::create('goods_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goods_id')->comment('商品ID');
            $table->string('name')->comment('属性名称');
            $table->json('values')->comment('属性值列表');
            $table->tinyInteger('type')->default(1)->comment('属性类型：1=文本，2=颜色，3=图片');
            $table->integer('sort')->default(0)->comment('排序');
            $table->tinyInteger('is_required')->default(1)->comment('是否必选：0=否，1=是');
            $table->timestamps();
            
            $table->foreign('goods_id')->references('id')->on('goods')->onDelete('cascade');
            $table->index(['goods_id', 'sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_attributes');
    }
};
