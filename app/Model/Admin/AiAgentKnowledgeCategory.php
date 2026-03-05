<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;

/**
 * AI 知识库分类模型
 *
 * @property int $id ID
 * @property int|null $site_id 站点ID
 * @property int $agent_id 所属Agent ID
 * @property int $parent_id 父级ID
 * @property string $name 分类名称
 * @property int $sort 排序
 * @property string|null $created_at 创建时间
 * @property string|null $updated_at 更新时间
 */
class AiAgentKnowledgeCategory extends Model
{
    protected ?string $table = 'ai_agent_knowledge_categories';

    protected string $keyType = 'int';

    public bool $incrementing = true;

    public bool $timestamps = true;

    protected array $fillable = [
        'site_id',
        'agent_id',
        'parent_id',
        'name',
        'sort',
    ];

    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'agent_id' => 'integer',
        'parent_id' => 'integer',
        'sort' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeByAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeParents($query)
    {
        return $query->where('parent_id', 0);
    }
}
