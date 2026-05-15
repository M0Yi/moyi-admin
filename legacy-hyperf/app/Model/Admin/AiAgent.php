<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Hyperf\Database\Model\SoftDeletes;

/**
 * AI Agent 模型
 *
 * @property int $id ID
 * @property int|null $site_id 站点ID
 * @property string $name Agent名称
 * @property string $slug Agent标识
 * @property string $type Agent类型
 * @property string|null $description 描述
 * @property string|null $icon 图标
 * @property string $class Agent类名
 * @property array|null $config Agent配置
 * @property int $is_default 是否默认Agent
 * @property int $status 状态
 * @property int $sort 排序
 * @property string|null $created_at 创建时间
 * @property string|null $updated_at 更新时间
 */
class AiAgent extends Model
{
    use SoftDeletes;

    protected ?string $table = 'ai_agents';

    protected string $keyType = 'int';

    public bool $incrementing = true;

    public bool $timestamps = true;

    protected array $fillable = [
        'site_id',
        'name',
        'slug',
        'type',
        'description',
        'icon',
        'class',
        'config',
        'is_default',
        'status',
        'sort',
    ];

    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'config' => 'array',
        'is_default' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public const TYPE_AUDIT = 'audit';
    public const TYPE_SEO = 'seo';
    public const TYPE_SERVICE = 'service';
    public const TYPE_CONTENT = 'content';
    public const TYPE_CUSTOM = 'custom';

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    public static function getTypes(): array
    {
        return [
            self::TYPE_AUDIT => '审核',
            self::TYPE_SEO => 'SEO',
            self::TYPE_SERVICE => '客服',
            self::TYPE_CONTENT => '内容生成',
            self::TYPE_CUSTOM => '自定义',
        ];
    }

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

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function getStatusTextAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? '未知';
    }

    public function getTypeTextAttribute(): string
    {
        return self::getTypes()[$this->type] ?? '未知';
    }

    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }
}
