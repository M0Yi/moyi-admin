<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;

/**
 * AI Provider 配置模型
 *
 * @property int $id ID
 * @property string $name Provider名称
 * @property string $slug Provider标识
 * @property string $driver 驱动类
 * @property string $base_url API基础URL
 * @property string|null $api_key API Key
 * @property array|null $models 可用模型列表
 * @property array|null $config 额外配置
 * @property int $is_default 是否默认
 * @property int $status 状态
 * @property int $sort 排序
 * @property string|null $created_at 创建时间
 * @property string|null $updated_at 更新时间
 */
class AiProvider extends Model
{
    protected ?string $table = 'ai_providers';

    protected string $keyType = 'int';

    public bool $incrementing = true;

    public bool $timestamps = true;

    protected array $fillable = [
        'name',
        'slug',
        'driver',
        'base_url',
        'api_key',
        'models',
        'config',
        'is_default',
        'status',
        'sort',
    ];

    protected array $casts = [
        'id' => 'integer',
        'models' => 'array',
        'config' => 'array',
        'is_default' => 'integer',
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

    public function scopeDefault($query)
    {
        return $query->where('is_default', 1);
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
