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
        Schema::create('subsites', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('分站名称');
            $table->string('domain')->unique()->comment('分站域名');
            $table->string('api_url')->nullable()->comment('API接口地址');
            $table->string('api_key')->nullable()->comment('API密钥');
            $table->string('api_secret')->nullable()->comment('API秘钥');
            $table->tinyInteger('type')->default(1)->comment('分站类型：1=本站分站，2=第三方对接');
            $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
            $table->decimal('commission_rate', 5, 2)->default(0)->comment('佣金比例');
            $table->json('settings')->nullable()->comment('分站配置');
            $table->text('description')->nullable()->comment('分站描述');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subsites');
    }
};
