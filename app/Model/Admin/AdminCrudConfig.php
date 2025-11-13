<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;

/**
 * CRUD生成器配置模型
 *
 * @property int $id
 * @property int $site_id
 * @property string $table_name
 * @property string|null $db_connection
 * @property string $model_name
 * @property string $controller_name
 * @property string $module_name
 * @property string $route_prefix
 * @property string|null $icon
 * @property array|null $fields_config
 * @property array|null $options
 * @property int $sync_to_menu
 * @property int $status
 * @property Carbon|null $generated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AdminCrudConfig extends Model
{
    /**
     * 状态：配置中
     */
    public const STATUS_CONFIGURING = 0;

    /**
     * 状态：已生成
     */
    public const STATUS_GENERATED = 1;

    /**
     * 表名
     */
    protected ?string $table = 'admin_crud_configs';

    /**
     * 主键类型
     */
    protected string $keyType = 'int';

    /**
     * 是否自增
     */
    public bool $incrementing = true;

    /**
     * 是否自动维护时间戳
     */
    public bool $timestamps = true;

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'site_id',
        'table_name',
        'db_connection',
        'model_name',
        'controller_name',
        'module_name',
        'route_prefix',
        'icon',
        'fields_config',
        'options',
        'sync_to_menu',
        'status',
        'generated_at',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'fields_config' => 'array',
        'options' => 'array',
        'sync_to_menu' => 'integer',
        'status' => 'integer',
        'generated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 查询作用域：已生成
     */
    public function scopeGenerated($query)
    {
        return $query->where('status', self::STATUS_GENERATED);
    }

    /**
     * 查询作用域：配置中
     */
    public function scopeConfiguring($query)
    {
        return $query->where('status', self::STATUS_CONFIGURING);
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_CONFIGURING => '配置中',
            self::STATUS_GENERATED => '已生成',
            default => '未知',
        };
    }

    /**
     * 标记为已生成
     */
    public function markAsGenerated(): bool
    {
        return $this->update([
            'status' => self::STATUS_GENERATED,
            'generated_at' => now(),
        ]);
    }
}

