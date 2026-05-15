<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;
use Hyperf\Database\Model\SoftDeletes;

/**
 * 远程数据库连接配置模型
 *
 * @property int $id 连接ID
 * @property int|null $site_id 站点ID
 * @property string $name 连接名称（用于在配置中引用，如 db2、db3）
 * @property string $driver 驱动类型：mysql、pgsql、sqlite等
 * @property string $host 主机地址
 * @property int $port 端口
 * @property string $database 数据库名
 * @property string $username 用户名
 * @property string $password 密码（明文存储，用于数据库连接）
 * @property string $charset 字符集
 * @property string $collation 排序规则
 * @property string|null $prefix 表前缀
 * @property string|null $description 描述
 * @property int $status 状态：0=禁用，1=启用
 * @property int $sort 排序
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 */
class AdminDatabaseConnection extends Model
{
    use SoftDeletes;

    /**
     * 表名
     */
    protected ?string $table = 'admin_database_connections';

    /**
     * 主键类型
     */
    protected string $keyType = 'int';

    /**
     * 是否自增主键
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
        'name',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password',
        'charset',
        'collation',
        'prefix',
        'description',
        'status',
        'sort',
    ];

    /**
     * 隐藏的属性
     */
    protected array $hidden = [
        'password',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'port' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 状态常量
     */
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    /**
     * 驱动类型常量
     */
    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_PGSQL = 'pgsql';

    /**
     * 获取所有可用状态
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DISABLED => '禁用',
            self::STATUS_ENABLED => '启用',
        ];
    }

    /**
     * 获取所有可用驱动类型
     */
    public static function getDrivers(): array
    {
        return [
            self::DRIVER_MYSQL => 'MySQL',
            self::DRIVER_PGSQL => 'PostgreSQL',
        ];
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? '未知';
    }

    /**
     * 获取驱动类型文本
     */
    public function getDriverTextAttribute(): string
    {
        return self::getDrivers()[$this->driver] ?? $this->driver;
    }

    /**
     * 密码访问器：明文存储密码
     * 注意：密码以明文形式存储，便于直接用于数据库连接
     * 前端显示时应隐藏密码
     */
    public function setPasswordAttribute($value): void
    {
        // 如果密码为空，不更新
        if (empty($value)) {
            return;
        }

        // 直接存储明文密码
        $this->attributes['password'] = $value;
    }

    /**
     * 查询作用域：启用状态
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 查询作用域：禁用状态
     */
    public function scopeDisabled($query)
    {
        return $query->where('status', self::STATUS_DISABLED);
    }

    /**
     * 查询作用域：根据连接名称查找
     */
    public function scopeByName($query, string $name)
    {
        return $query->where('name', $name);
    }

    /**
     * 查询作用域：排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort', 'asc')->orderBy('id', 'asc');
    }

    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 检查是否禁用
     */
    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    /**
     * 启用连接
     */
    public function enable(): bool
    {
        return $this->update(['status' => self::STATUS_ENABLED]);
    }

    /**
     * 禁用连接
     */
    public function disable(): bool
    {
        return $this->update(['status' => self::STATUS_DISABLED]);
    }

    /**
     * 获取数据库连接配置数组（用于动态配置）
     *
     * @param string|null $password 密码（如果提供，将使用此密码；否则使用存储的密码）
     * @return array
     */
    public function toConnectionConfig(?string $password = null): array
    {
        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $password ?? $this->password, // 使用明文密码
            'charset' => $this->charset,
            'collation' => $this->collation,
            'prefix' => $this->prefix ?? '',
        ];
    }
}

