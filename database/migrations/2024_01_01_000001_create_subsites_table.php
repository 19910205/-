<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 分站管理表迁移
 * 用于管理本站分站和第三方发卡网对接
 */
return new class extends Migration
{
    /**
     * 执行迁移
     */
    public function up(): void
    {
        Schema::create('subsites', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->string('name')->comment('分站名称');
            $table->string('domain')->unique()->comment('分站域名');
            $table->string('subdomain')->nullable()->unique()->comment('子域名');
            $table->string('api_url')->nullable()->comment('API接口地址');
            $table->string('api_key')->nullable()->comment('API密钥');
            $table->string('api_secret')->nullable()->comment('API秘钥');
            $table->tinyInteger('type')->default(1)->comment('分站类型：1=本站分站，2=第三方对接');
            $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
            $table->decimal('commission_rate', 5, 2)->default(0)->comment('佣金比例(%)');
            $table->decimal('balance', 10, 2)->default(0)->comment('分站余额');
            $table->json('settings')->nullable()->comment('分站配置信息');
            $table->json('api_config')->nullable()->comment('API配置参数');
            $table->text('description')->nullable()->comment('分站描述');
            $table->string('contact_email')->nullable()->comment('联系邮箱');
            $table->string('contact_phone')->nullable()->comment('联系电话');
            $table->string('logo_url')->nullable()->comment('分站Logo地址');
            $table->string('theme_color')->nullable()->comment('主题颜色');
            $table->timestamp('last_sync_at')->nullable()->comment('最后同步时间');
            $table->timestamps();
            $table->softDeletes();
            
            // 索引优化
            $table->index(['status', 'type'], 'idx_status_type');
            $table->index('domain', 'idx_domain');
            $table->index('subdomain', 'idx_subdomain');
            $table->index('last_sync_at', 'idx_last_sync');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('subsites');
    }
};
