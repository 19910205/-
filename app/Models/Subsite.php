<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 分站模型
 * 用于管理本站分站和第三方发卡网对接
 * 
 * @author Augment Agent
 */
class Subsite extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'subsites';

    // 分站类型常量
    const TYPE_LOCAL = 1;        // 本站分站
    const TYPE_THIRD_PARTY = 2;  // 第三方对接

    // 状态常量
    const STATUS_DISABLED = 0;   // 禁用
    const STATUS_ENABLED = 1;    // 启用

    protected $fillable = [
        'name',
        'domain',
        'subdomain',
        'api_url',
        'api_key',
        'api_secret',
        'type',
        'status',
        'commission_rate',
        'balance',
        'settings',
        'api_config',
        'description',
        'contact_email',
        'contact_phone',
        'logo_url',
        'theme_color',
        'last_sync_at'
    ];

    protected $casts = [
        'settings' => 'array',
        'api_config' => 'array',
        'commission_rate' => 'decimal:2',
        'balance' => 'decimal:2',
        'last_sync_at' => 'datetime'
    ];

    /**
     * 获取分站类型映射
     */
    public static function getTypeMap(): array
    {
        return [
            self::TYPE_LOCAL => '本站分站',
            self::TYPE_THIRD_PARTY => '第三方对接'
        ];
    }

    /**
     * 获取状态映射
     */
    public static function getStatusMap(): array
    {
        return [
            self::STATUS_DISABLED => '禁用',
            self::STATUS_ENABLED => '启用'
        ];
    }

    /**
     * 关联分站订单
     */
    public function orders(): HasMany
    {
        return $this->hasMany(SubsiteOrder::class);
    }

    /**
     * 获取类型文本
     */
    public function getTypeTextAttribute(): string
    {
        return self::getTypeMap()[$this->type] ?? '';
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatusMap()[$this->status] ?? '';
    }

    /**
     * 是否启用
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 是否本站分站
     */
    public function isLocal(): bool
    {
        return $this->type === self::TYPE_LOCAL;
    }

    /**
     * 是否第三方对接
     */
    public function isThirdParty(): bool
    {
        return $this->type === self::TYPE_THIRD_PARTY;
    }

    /**
     * 增加余额
     */
    public function addBalance(float $amount): bool
    {
        return $this->increment('balance', $amount);
    }

    /**
     * 扣减余额
     */
    public function deductBalance(float $amount): bool
    {
        if ($this->balance < $amount) {
            return false;
        }
        return $this->decrement('balance', $amount);
    }

    /**
     * 更新最后同步时间
     */
    public function updateLastSyncTime(): bool
    {
        return $this->update(['last_sync_at' => now()]);
    }

    /**
     * 计算佣金
     */
    public function calculateCommission(float $orderAmount): float
    {
        return $orderAmount * ($this->commission_rate / 100);
    }

    /**
     * 获取API配置
     */
    public function getApiConfig(string $key = null)
    {
        if ($key) {
            return $this->api_config[$key] ?? null;
        }
        return $this->api_config;
    }

    /**
     * 设置API配置
     */
    public function setApiConfig(string $key, $value): bool
    {
        $config = $this->api_config ?? [];
        $config[$key] = $value;
        return $this->update(['api_config' => $config]);
    }

    /**
     * 获取分站设置
     */
    public function getSetting(string $key = null)
    {
        if ($key) {
            return $this->settings[$key] ?? null;
        }
        return $this->settings;
    }

    /**
     * 设置分站配置
     */
    public function setSetting(string $key, $value): bool
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        return $this->update(['settings' => $settings]);
    }
}
