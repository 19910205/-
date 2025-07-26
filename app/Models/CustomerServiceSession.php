<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 客服会话模型
 * 用于管理客服与用户的会话
 * 
 * @author Augment Agent
 */
class CustomerServiceSession extends BaseModel
{
    use HasFactory;

    protected $table = 'customer_service_sessions';

    // 状态常量
    const STATUS_WAITING = 0;   // 等待中
    const STATUS_ACTIVE = 1;    // 进行中
    const STATUS_CLOSED = 2;    // 已关闭

    // 来源常量
    const SOURCE_WEB = 1;       // 网页
    const SOURCE_MOBILE = 2;    // 手机
    const SOURCE_API = 3;       // API

    protected $fillable = [
        'session_id',
        'user_email',
        'user_name',
        'user_ip',
        'user_agent',
        'service_id',
        'status',
        'source',
        'title',
        'priority',
        'tags',
        'started_at',
        'ended_at',
        'rating',
        'feedback',
        'last_message_at'
    ];

    protected $casts = [
        'tags' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_message_at' => 'datetime'
    ];

    /**
     * 获取状态映射
     */
    public static function getStatusMap(): array
    {
        return [
            self::STATUS_WAITING => '等待中',
            self::STATUS_ACTIVE => '进行中',
            self::STATUS_CLOSED => '已关闭'
        ];
    }

    /**
     * 获取来源映射
     */
    public static function getSourceMap(): array
    {
        return [
            self::SOURCE_WEB => '网页',
            self::SOURCE_MOBILE => '手机',
            self::SOURCE_API => 'API'
        ];
    }

    /**
     * 关联客服
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class, 'service_id');
    }

    /**
     * 关联消息
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CustomerServiceMessage::class, 'session_id');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatusMap()[$this->status] ?? '';
    }

    /**
     * 获取来源文本
     */
    public function getSourceTextAttribute(): string
    {
        return self::getSourceMap()[$this->source] ?? '';
    }

    /**
     * 是否等待中
     */
    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    /**
     * 是否进行中
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * 是否已关闭
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * 开始会话
     */
    public function start(int $serviceId): bool
    {
        return $this->update([
            'service_id' => $serviceId,
            'status' => self::STATUS_ACTIVE,
            'started_at' => now()
        ]);
    }

    /**
     * 结束会话
     */
    public function end(): bool
    {
        return $this->update([
            'status' => self::STATUS_CLOSED,
            'ended_at' => now()
        ]);
    }

    /**
     * 更新最后消息时间
     */
    public function updateLastMessageTime(): bool
    {
        return $this->update(['last_message_at' => now()]);
    }

    /**
     * 设置评分
     */
    public function setRating(int $rating, ?string $feedback = null): bool
    {
        return $this->update([
            'rating' => $rating,
            'feedback' => $feedback
        ]);
    }

    /**
     * 获取会话时长
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->ended_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    /**
     * 获取格式化时长
     */
    public function getFormattedDurationAttribute(): string
    {
        $duration = $this->duration;
        if (!$duration) {
            return '0秒';
        }

        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;

        $parts = [];
        if ($hours > 0) $parts[] = $hours . '小时';
        if ($minutes > 0) $parts[] = $minutes . '分钟';
        if ($seconds > 0) $parts[] = $seconds . '秒';

        return implode('', $parts);
    }

    /**
     * 获取标签列表
     */
    public function getTagsList(): array
    {
        return $this->tags ?? [];
    }

    /**
     * 添加标签
     */
    public function addTag(string $tag): bool
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            return $this->update(['tags' => $tags]);
        }
        return true;
    }

    /**
     * 移除标签
     */
    public function removeTag(string $tag): bool
    {
        $tags = $this->tags ?? [];
        $key = array_search($tag, $tags);
        if ($key !== false) {
            unset($tags[$key]);
            return $this->update(['tags' => array_values($tags)]);
        }
        return true;
    }

    /**
     * 获取消息数量
     */
    public function getMessageCountAttribute(): int
    {
        return $this->messages()->count();
    }

    /**
     * 获取用户消息数量
     */
    public function getUserMessageCountAttribute(): int
    {
        return $this->messages()->where('sender_type', CustomerServiceMessage::SENDER_USER)->count();
    }

    /**
     * 获取客服消息数量
     */
    public function getServiceMessageCountAttribute(): int
    {
        return $this->messages()->where('sender_type', CustomerServiceMessage::SENDER_SERVICE)->count();
    }

    /**
     * 是否超时
     */
    public function isTimeout(int $minutes = 30): bool
    {
        if (!$this->last_message_at) {
            return false;
        }

        return $this->last_message_at->addMinutes($minutes)->isPast();
    }
}
