<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商品属性表迁移
 * 用于管理商品的属性配置
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        Schema::create('goods_attributes', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->unsignedBigInteger('goods_id')->comment('商品ID');
            $table->string('name')->comment('属性名称');
            $table->json('values')->comment('属性值列表');
            $table->tinyInteger('type')->default(1)->comment('属性类型：1=文本，2=颜色，3=图片，4=尺寸，5=数字');
            $table->tinyInteger('input_type')->default(1)->comment('输入类型：1=单选，2=多选，3=输入框，4=下拉框');
            $table->integer('sort')->default(0)->comment('排序权重');
            $table->tinyInteger('is_required')->default(1)->comment('是否必选：0=否，1=是');
            $table->tinyInteger('is_filterable')->default(0)->comment('是否可筛选：0=否，1=是');
            $table->tinyInteger('is_searchable')->default(0)->comment('是否可搜索：0=否，1=是');
            $table->string('unit')->nullable()->comment('属性单位');
            $table->string('default_value')->nullable()->comment('默认值');
            $table->text('description')->nullable()->comment('属性描述');
            $table->json('validation_rules')->nullable()->comment('验证规则');
            $table->timestamps();
            
            // 外键约束
            $table->foreign('goods_id')->references('id')->on('goods')->onDelete('cascade');
            
            // 索引优化
            $table->index(['goods_id', 'sort'], 'idx_goods_sort');
            $table->index(['goods_id', 'is_filterable'], 'idx_goods_filterable');
            $table->index(['goods_id', 'is_searchable'], 'idx_goods_searchable');
            $table->index(['type', 'input_type'], 'idx_type_input');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_attributes');
    }
};
