<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;

/**
 * AI 工具注册模型
 *
 * @property int $id ID
 * @property int|null $site_id 站点ID
 * @property int $agent_id 所属Agent ID
 * @property string $name 工具名称
 * @property string $slug 工具标识
 * @property string|null $description 工具描述
 * @property string $class 工具类
 * @property array|null $config 工具配置
 * @property int $is_enabled 是否启用
 * @property int $sort 排序
 * @property string|null $created_at 创建时间
 * @property string|null $updated_at 更新时间
 */
class AiAgentTool extends Model
{
    protected ?string $table = 'ai_agent_tools';

    protected string $keyType = 'int';

    public bool $incrementing = true;

    public bool $timestamps = true;

    protected array $fillable = [
        'site_id',
        'agent_id',
        'name',
        'slug',
        'description',
        'class',
        'config',
        'is_enabled',
        'sort',
    ];

    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'agent_id' => 'integer',
        'config' => 'array',
        'is_enabled' => 'integer',
        'sort' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', 1);
    }

    public function scopeByAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function isEnabled(): bool
    {
        return $this->is_enabled === 1;
    }
}
