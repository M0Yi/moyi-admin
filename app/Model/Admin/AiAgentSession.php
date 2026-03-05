<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Hyperf\Database\Model\SoftDeletes;

/**
 * AI 客服会话模型
 *
 * @property int $id ID
 * @property int|null $site_id 站点ID
 * @property int $agent_id Agent ID
 * @property string $session_id 会话唯一标识
 * @property int|null $user_id 用户ID
 * @property string $user_type 用户类型
 * @property string|null $user_name 用户名称
 * @property array|null $context 会话上下文
 * @property array|null $metadata 会话元数据
 * @property int $status 状态
 * @property int $total_tokens 累计消耗Token
 * @property int $message_count 消息数量
 * @property string|null $last_message_at 最后消息时间
 * @property string|null $created_at 创建时间
 * @property string|null $updated_at 更新时间
 */
class AiAgentSession extends Model
{
    use SoftDeletes;

    protected ?string $table = 'ai_agent_sessions';

    protected string $keyType = 'int';

    public bool $incrementing = true;

    public bool $timestamps = true;

    protected array $fillable = [
        'site_id',
        'agent_id',
        'session_id',
        'user_id',
        'user_type',
        'user_name',
        'context',
        'metadata',
        'status',
        'total_tokens',
        'message_count',
        'last_message_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'agent_id' => 'integer',
        'user_id' => 'integer',
        'context' => 'array',
        'metadata' => 'array',
        'status' => 'integer',
        'total_tokens' => 'integer',
        'message_count' => 'integer',
        'last_message_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public const STATUS_ENDED = 0;
    public const STATUS_ACTIVE = 1;

    public const USER_TYPE_GUEST = 'guest';
    public const USER_TYPE_MEMBER = 'member';
    public const USER_TYPE_ADMIN = 'admin';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ENDED => '已结束',
            self::STATUS_ACTIVE => '进行中',
        ];
    }

    public static function getUserTypes(): array
    {
        return [
            self::USER_TYPE_GUEST => '访客',
            self::USER_TYPE_MEMBER => '会员',
            self::USER_TYPE_ADMIN => '管理员',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function getStatusTextAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? '未知';
    }

    public function getUserTypeTextAttribute(): string
    {
        return self::getUserTypes()[$this->user_type] ?? '未知';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
