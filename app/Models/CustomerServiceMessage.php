<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 客服消息模型
 * 用于管理客服会话中的消息
 * 
 * @author Augment Agent
 */
class CustomerServiceMessage extends BaseModel
{
    use HasFactory;

    protected $table = 'customer_service_messages';

    // 发送者类型常量
    const SENDER_USER = 1;      // 用户
    const SENDER_SERVICE = 2;   // 客服
    const SENDER_SYSTEM = 3;    // 系统

    // 消息类型常量
    const TYPE_TEXT = 1;        // 文本
    const TYPE_IMAGE = 2;       // 图片
    const TYPE_FILE = 3;        // 文件
    const TYPE_VOICE = 4;       // 语音
    const TYPE_VIDEO = 5;       // 视频
    const TYPE_SYSTEM = 6;      // 系统消息

    // 状态常量
    const STATUS_UNREAD = 0;    // 未读
    const STATUS_READ = 1;      // 已读

    protected $fillable = [
        'session_id',
        'service_id',
        'sender_type',
        'sender_name',
        'message_type',
        'content',
        'attachments',
        'status',
        'is_auto_reply',
        'reply_to_id',
        'read_at'
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_auto_reply' => 'boolean',
        'read_at' => 'datetime'
    ];

    /**
     * 获取发送者类型映射
     */
    public static function getSenderTypeMap(): array
    {
        return [
            self::SENDER_USER => '用户',
            self::SENDER_SERVICE => '客服',
            self::SENDER_SYSTEM => '系统'
        ];
    }

    /**
     * 获取消息类型映射
     */
    public static function getMessageTypeMap(): array
    {
        return [
            self::TYPE_TEXT => '文本',
            self::TYPE_IMAGE => '图片',
            self::TYPE_FILE => '文件',
            self::TYPE_VOICE => '语音',
            self::TYPE_VIDEO => '视频',
            self::TYPE_SYSTEM => '系统消息'
        ];
    }

    /**
     * 获取状态映射
     */
    public static function getStatusMap(): array
    {
        return [
            self::STATUS_UNREAD => '未读',
            self::STATUS_READ => '已读'
        ];
    }

    /**
     * 关联会话
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CustomerServiceSession::class, 'session_id');
    }

    /**
     * 关联客服
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class, 'service_id');
    }

    /**
     * 关联回复的消息
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * 关联回复消息
     */
    public function replies()
    {
        return $this->hasMany(self::class, 'reply_to_id');
    }

    /**
     * 获取发送者类型文本
     */
    public function getSenderTypeTextAttribute(): string
    {
        return self::getSenderTypeMap()[$this->sender_type] ?? '';
    }

    /**
     * 获取消息类型文本
     */
    public function getMessageTypeTextAttribute(): string
    {
        return self::getMessageTypeMap()[$this->message_type] ?? '';
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatusMap()[$this->status] ?? '';
    }

    /**
     * 是否用户发送
     */
    public function isFromUser(): bool
    {
        return $this->sender_type === self::SENDER_USER;
    }

    /**
     * 是否客服发送
     */
    public function isFromService(): bool
    {
        return $this->sender_type === self::SENDER_SERVICE;
    }

    /**
     * 是否系统消息
     */
    public function isSystemMessage(): bool
    {
        return $this->sender_type === self::SENDER_SYSTEM;
    }

    /**
     * 是否已读
     */
    public function isRead(): bool
    {
        return $this->status === self::STATUS_READ;
    }

    /**
     * 是否自动回复
     */
    public function isAutoReply(): bool
    {
        return $this->is_auto_reply;
    }

    /**
     * 标记为已读
     */
    public function markAsRead(): bool
    {
        return $this->update([
            'status' => self::STATUS_READ,
            'read_at' => now()
        ]);
    }

    /**
     * 获取附件列表
     */
    public function getAttachmentsList(): array
    {
        return $this->attachments ?? [];
    }

    /**
     * 添加附件
     */
    public function addAttachment(array $attachment): bool
    {
        $attachments = $this->attachments ?? [];
        $attachments[] = $attachment;
        return $this->update(['attachments' => $attachments]);
    }

    /**
     * 获取格式化内容
     */
    public function getFormattedContentAttribute(): string
    {
        switch ($this->message_type) {
            case self::TYPE_IMAGE:
                return '<img src="' . $this->content . '" alt="图片" style="max-width: 200px;">';
            case self::TYPE_FILE:
                return '<a href="' . $this->content . '" target="_blank">📎 文件下载</a>';
            case self::TYPE_VOICE:
                return '🎵 语音消息';
            case self::TYPE_VIDEO:
                return '🎬 视频消息';
            case self::TYPE_SYSTEM:
                return '<em>' . $this->content . '</em>';
            default:
                return $this->content;
        }
    }

    /**
     * 创建文本消息
     */
    public static function createTextMessage(int $sessionId, int $senderType, string $content, ?int $serviceId = null, ?string $senderName = null): self
    {
        return self::create([
            'session_id' => $sessionId,
            'service_id' => $serviceId,
            'sender_type' => $senderType,
            'sender_name' => $senderName,
            'message_type' => self::TYPE_TEXT,
            'content' => $content,
            'status' => self::STATUS_UNREAD
        ]);
    }

    /**
     * 创建系统消息
     */
    public static function createSystemMessage(int $sessionId, string $content): self
    {
        return self::create([
            'session_id' => $sessionId,
            'sender_type' => self::SENDER_SYSTEM,
            'sender_name' => '系统',
            'message_type' => self::TYPE_SYSTEM,
            'content' => $content,
            'status' => self::STATUS_READ
        ]);
    }

    /**
     * 创建自动回复消息
     */
    public static function createAutoReply(int $sessionId, int $serviceId, string $content): self
    {
        return self::create([
            'session_id' => $sessionId,
            'service_id' => $serviceId,
            'sender_type' => self::SENDER_SERVICE,
            'message_type' => self::TYPE_TEXT,
            'content' => $content,
            'status' => self::STATUS_UNREAD,
            'is_auto_reply' => true
        ]);
    }
}
