<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;
use function Hyperf\Support\now;

/**
 * CRUD生成器配置模型
 *
 * @property int $id
 * @property int $site_id
 * @property string $table_name
 * @property int $is_remote_connection 是否使用远程数据库连接：0=配置文件连接，1=远程数据库连接
 * @property string|null $db_connection 数据库连接名称：当 is_remote_connection=0 时对应 config/databases.php 中的连接键名；当 is_remote_connection=1 时对应 admin_database_connections 表中的 name 字段
 * @property string $model_name
 * @property string $controller_name
 * @property string $module_name
 * @property string $route_slug
 * @property string $route_prefix
 * @property string|null $icon
 * @property int $page_size
 * @property int $soft_delete
 * @property int $feature_search
 * @property int $feature_add
 * @property int $feature_edit
 * @property int $feature_delete
 * @property int $feature_export
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
        'is_remote_connection',
        'db_connection',
        'model_name',
        'controller_name',
        'module_name',
        'route_slug',
        'route_prefix',
        'icon',
        'page_size',
        'soft_delete',
        'feature_search',
        'feature_add',
        'feature_edit',
        'feature_delete',
        'feature_export',
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
        'is_remote_connection' => 'integer',
        'route_slug' => 'string',
        'route_prefix' => 'string',
        'page_size' => 'integer',
        'soft_delete' => 'integer',
        'feature_search' => 'integer',
        'feature_add' => 'integer',
        'feature_edit' => 'integer',
        'feature_delete' => 'integer',
        'feature_export' => 'integer',
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

