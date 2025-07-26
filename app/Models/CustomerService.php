<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 客服模型
 * 用于管理客服人员信息
 * 
 * @author Augment Agent
 */
class CustomerService extends BaseModel
{
    use HasFactory;

    protected $table = 'customer_services';

    // 状态常量
    const STATUS_OFFLINE = 0;  // 离线
    const STATUS_ONLINE = 1;   // 在线
    const STATUS_BUSY = 2;     // 忙碌

    protected $fillable = [
        'name',
        'avatar',
        'email',
        'phone',
        'qq',
        'wechat',
        'status',
        'max_sessions',
        'current_sessions',
        'auto_reply',
        'welcome_message',
        'working_hours',
        'skills',
        'department',
        'sort',
        'is_enabled'
    ];

    protected $casts = [
        'working_hours' => 'array',
        'skills' => 'array',
        'is_enabled' => 'boolean'
    ];

    /**
     * 获取状态映射
     */
    public static function getStatusMap(): array
    {
        return [
            self::STATUS_OFFLINE => '离线',
            self::STATUS_ONLINE => '在线',
            self::STATUS_BUSY => '忙碌'
        ];
    }

    /**
     * 关联客服会话
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CustomerServiceSession::class, 'service_id');
    }

    /**
     * 关联客服消息
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CustomerServiceMessage::class, 'service_id');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatusMap()[$this->status] ?? '';
    }

    /**
     * 是否在线
     */
    public function isOnline(): bool
    {
        return $this->status === self::STATUS_ONLINE;
    }

    /**
     * 是否忙碌
     */
    public function isBusy(): bool
    {
        return $this->status === self::STATUS_BUSY;
    }

    /**
     * 是否可接受新会话
     */
    public function canAcceptNewSession(): bool
    {
        return $this->isOnline() && 
               $this->current_sessions < $this->max_sessions &&
               $this->is_enabled;
    }

    /**
     * 增加当前会话数
     */
    public function incrementSessions(): bool
    {
        return $this->increment('current_sessions');
    }

    /**
     * 减少当前会话数
     */
    public function decrementSessions(): bool
    {
        return $this->decrement('current_sessions');
    }

    /**
     * 设置状态
     */
    public function setStatus(int $status): bool
    {
        return $this->update(['status' => $status]);
    }

    /**
     * 上线
     */
    public function goOnline(): bool
    {
        return $this->setStatus(self::STATUS_ONLINE);
    }

    /**
     * 下线
     */
    public function goOffline(): bool
    {
        return $this->setStatus(self::STATUS_OFFLINE);
    }

    /**
     * 设为忙碌
     */
    public function setBusy(): bool
    {
        return $this->setStatus(self::STATUS_BUSY);
    }

    /**
     * 获取技能列表
     */
    public function getSkillsList(): array
    {
        return $this->skills ?? [];
    }

    /**
     * 是否有技能
     */
    public function hasSkill(string $skill): bool
    {
        return in_array($skill, $this->getSkillsList());
    }

    /**
     * 添加技能
     */
    public function addSkill(string $skill): bool
    {
        $skills = $this->skills ?? [];
        if (!in_array($skill, $skills)) {
            $skills[] = $skill;
            return $this->update(['skills' => $skills]);
        }
        return true;
    }

    /**
     * 移除技能
     */
    public function removeSkill(string $skill): bool
    {
        $skills = $this->skills ?? [];
        $key = array_search($skill, $skills);
        if ($key !== false) {
            unset($skills[$key]);
            return $this->update(['skills' => array_values($skills)]);
        }
        return true;
    }

    /**
     * 检查工作时间
     */
    public function isWorkingTime(): bool
    {
        if (!$this->working_hours) {
            return true; // 没有设置工作时间则认为全天工作
        }

        $now = now();
        $currentDay = strtolower($now->format('l')); // monday, tuesday, etc.
        $currentTime = $now->format('H:i');

        $daySchedule = $this->working_hours[$currentDay] ?? null;
        if (!$daySchedule || !$daySchedule['enabled']) {
            return false;
        }

        return $currentTime >= $daySchedule['start'] && $currentTime <= $daySchedule['end'];
    }

    /**
     * 获取欢迎消息
     */
    public function getWelcomeMessage(): string
    {
        return $this->welcome_message ?? '您好，有什么可以帮助您的吗？';
    }

    /**
     * 获取自动回复
     */
    public function getAutoReply(): ?string
    {
        return $this->auto_reply;
    }

    /**
     * 获取当前会话负载率
     */
    public function getLoadRateAttribute(): float
    {
        if ($this->max_sessions <= 0) {
            return 0;
        }
        return ($this->current_sessions / $this->max_sessions) * 100;
    }
}
