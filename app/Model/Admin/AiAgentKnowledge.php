<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;

/**
 * AI 知识库模型
 *
 * @property int $id ID
 * @property int|null $site_id 站点ID
 * @property int $agent_id 所属Agent ID
 * @property int|null $category_id 分类ID
 * @property string $title 标题
 * @property string $content 内容
 * @property array|null $keywords 关键词
 * @property int $status 状态
 * @property int $sort 排序
 * @property string|null $created_at 创建时间
 * @property string|null $updated_at 更新时间
 */
class AiAgentKnowledge extends Model
{
    protected ?string $table = 'ai_agent_knowledge';

    protected string $keyType = 'int';

    public bool $incrementing = true;

    public bool $timestamps = true;

    protected array $fillable = [
        'site_id',
        'agent_id',
        'category_id',
        'title',
        'content',
        'keywords',
        'status',
        'sort',
    ];

    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'agent_id' => 'integer',
        'category_id' => 'integer',
        'keywords' => 'array',
        'status' => 'integer',
        'sort' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    public static function getStatuses(): array
    {
        return [
            self::STATUS_DISABLED => '禁用',
            self::STATUS_ENABLED => '启用',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    public function scopeByAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function getStatusTextAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? '未知';
    }

    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }
}
