<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;

/**
 * AI Agent 使用日志模型
 *
 * @property int $id ID
 * @property int|null $site_id 站点ID
 * @property int $agent_id Agent ID
 * @property string|null $session_id 会话ID
 * @property string|null $agent_name Agent名称
 * @property string|null $agent_type Agent类型
 * @property int|null $user_id 操作用户ID
 * @property string|null $username 操作用户名
 * @property string|null $prompt 输入提示词
 * @property string|null $content 待处理内容
 * @property array|null $result 处理结果
 * @property int $status 状态
 * @property string|null $error_message 错误信息
 * @property int|null $tokens 消耗Token数
 * @property int|null $duration 执行时长
 * @property string|null $ip IP地址
 * @property string|null $user_agent User Agent
 * @property string|null $created_at 创建时间
 */
class AiAgentLog extends Model
{
    protected ?string $table = 'ai_agent_logs';

    protected string $keyType = 'int';

    public bool $incrementing = true;

    public bool $timestamps = false;

    protected array $fillable = [
        'site_id',
        'agent_id',
        'session_id',
        'agent_name',
        'agent_type',
        'user_id',
        'username',
        'prompt',
        'content',
        'result',
        'status',
        'error_message',
        'tokens',
        'duration',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'agent_id' => 'integer',
        'user_id' => 'integer',
        'result' => 'array',
        'status' => 'integer',
        'tokens' => 'integer',
        'duration' => 'integer',
        'created_at' => 'datetime',
    ];

    public const STATUS_FAILED = 0;
    public const STATUS_SUCCESS = 1;

    public static function getStatuses(): array
    {
        return [
            self::STATUS_FAILED => '失败',
            self::STATUS_SUCCESS => '成功',
        ];
    }

    public function scopeByAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function getStatusTextAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? '未知';
    }
}
