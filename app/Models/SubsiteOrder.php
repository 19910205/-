<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 分站订单模型
 * 用于记录分站订单和佣金结算
 * 
 * @author Augment Agent
 */
class SubsiteOrder extends BaseModel
{
    use HasFactory;

    protected $table = 'subsite_orders';

    // 佣金状态常量
    const COMMISSION_STATUS_PENDING = 0;  // 未结算
    const COMMISSION_STATUS_SETTLED = 1;  // 已结算

    // 同步状态常量
    const SYNC_STATUS_PENDING = 0;  // 未同步
    const SYNC_STATUS_SUCCESS = 1;  // 已同步
    const SYNC_STATUS_FAILED = 2;   // 同步失败

    protected $fillable = [
        'subsite_id',
        'order_id',
        'subsite_order_sn',
        'commission_amount',
        'commission_status',
        'commission_settled_at',
        'sync_data',
        'sync_status',
        'synced_at',
        'sync_error',
        'retry_count',
        'next_retry_at'
    ];

    protected $casts = [
        'sync_data' => 'array',
        'commission_amount' => 'decimal:2',
        'commission_settled_at' => 'datetime',
        'synced_at' => 'datetime',
        'next_retry_at' => 'datetime'
    ];

    /**
     * 获取佣金状态映射
     */
    public static function getCommissionStatusMap(): array
    {
        return [
            self::COMMISSION_STATUS_PENDING => '未结算',
            self::COMMISSION_STATUS_SETTLED => '已结算'
        ];
    }

    /**
     * 获取同步状态映射
     */
    public static function getSyncStatusMap(): array
    {
        return [
            self::SYNC_STATUS_PENDING => '未同步',
            self::SYNC_STATUS_SUCCESS => '已同步',
            self::SYNC_STATUS_FAILED => '同步失败'
        ];
    }

    /**
     * 关联分站
     */
    public function subsite(): BelongsTo
    {
        return $this->belongsTo(Subsite::class);
    }

    /**
     * 关联订单
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * 获取佣金状态文本
     */
    public function getCommissionStatusTextAttribute(): string
    {
        return self::getCommissionStatusMap()[$this->commission_status] ?? '';
    }

    /**
     * 获取同步状态文本
     */
    public function getSyncStatusTextAttribute(): string
    {
        return self::getSyncStatusMap()[$this->sync_status] ?? '';
    }

    /**
     * 是否佣金已结算
     */
    public function isCommissionSettled(): bool
    {
        return $this->commission_status === self::COMMISSION_STATUS_SETTLED;
    }

    /**
     * 是否已同步
     */
    public function isSynced(): bool
    {
        return $this->sync_status === self::SYNC_STATUS_SUCCESS;
    }

    /**
     * 标记佣金已结算
     */
    public function markCommissionSettled(): bool
    {
        return $this->update([
            'commission_status' => self::COMMISSION_STATUS_SETTLED,
            'commission_settled_at' => now()
        ]);
    }

    /**
     * 标记同步成功
     */
    public function markSyncSuccess(): bool
    {
        return $this->update([
            'sync_status' => self::SYNC_STATUS_SUCCESS,
            'synced_at' => now(),
            'sync_error' => null,
            'retry_count' => 0,
            'next_retry_at' => null
        ]);
    }

    /**
     * 标记同步失败
     */
    public function markSyncFailed(string $error): bool
    {
        $retryCount = $this->retry_count + 1;
        $nextRetryAt = now()->addMinutes(pow(2, $retryCount)); // 指数退避

        return $this->update([
            'sync_status' => self::SYNC_STATUS_FAILED,
            'synced_at' => now(),
            'sync_error' => $error,
            'retry_count' => $retryCount,
            'next_retry_at' => $nextRetryAt
        ]);
    }

    /**
     * 重置重试
     */
    public function resetRetry(): bool
    {
        return $this->update([
            'retry_count' => 0,
            'next_retry_at' => null,
            'sync_error' => null
        ]);
    }

    /**
     * 是否可以重试
     */
    public function canRetry(): bool
    {
        return $this->retry_count < 5 && 
               ($this->next_retry_at === null || $this->next_retry_at->isPast());
    }

    /**
     * 获取同步数据
     */
    public function getSyncData(string $key = null)
    {
        if ($key) {
            return $this->sync_data[$key] ?? null;
        }
        return $this->sync_data;
    }

    /**
     * 设置同步数据
     */
    public function setSyncData(string $key, $value): bool
    {
        $data = $this->sync_data ?? [];
        $data[$key] = $value;
        return $this->update(['sync_data' => $data]);
    }
}
