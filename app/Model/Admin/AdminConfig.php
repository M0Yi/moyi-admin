<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Hyperf\Cache\Annotation\Cacheable;
use Hyperf\Cache\Annotation\CacheEvict;

/**
 * @property int $id
 * @property int|null $site_id
 * @property string $group
 * @property string $key
 * @property string $value
 * @property string $type
 * @property string $description
 * @property int $sort
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AdminConfig extends Model
{
    /**
     * 表名
     */
    protected ?string $table = 'admin_configs';

    /**
     * 类型常量
     */
    public const TYPE_STRING = 'string';
    public const TYPE_INT = 'int';
    public const TYPE_BOOL = 'bool';
    public const TYPE_JSON = 'json';

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'site_id',
        'group',
        'key',
        'value',
        'type',
        'description',
        'sort',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'sort' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联站点
     */
    public function site()
    {
        return $this->belongsTo(AdminSite::class, 'site_id', 'id');
    }

    /**
     * 查询作用域：指定站点
     */
    public function scopeBySite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * 查询作用域：按分组筛选
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * 查询作用域：按排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort', 'asc')->orderBy('id', 'asc');
    }

    /**
     * 获取配置值（带类型转换和缓存）
     */
    #[Cacheable(prefix: 'admin_config', ttl: 3600, value: '_#{key}')]
    public static function getValue(string $key, $default = null)
    {
        $config = self::where('key', $key)->first();

        if (! $config) {
            return $default;
        }

        return self::castValue($config->value, $config->type);
    }

    /**
     * 设置配置值（清除缓存）
     */
    #[CacheEvict(prefix: 'admin_config', value: '_#{key}')]
    public static function setValue(string $key, $value): bool
    {
        $config = self::where('key', $key)->first();

        if (! $config) {
            return false;
        }

        // 根据类型转换值
        $config->value = self::valueToString($value, $config->type);
        return $config->save();
    }

    /**
     * 类型转换：字符串转实际类型
     */
    protected static function castValue(string $value, string $type)
    {
        return match ($type) {
            self::TYPE_INT => (int) $value,
            self::TYPE_BOOL => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_JSON => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * 类型转换：实际类型转字符串
     */
    protected static function valueToString($value, string $type): string
    {
        return match ($type) {
            self::TYPE_INT => (string) (int) $value,
            self::TYPE_BOOL => $value ? '1' : '0',
            self::TYPE_JSON => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    /**
     * 获取某个分组的所有配置
     */
    #[Cacheable(prefix: 'admin_config_group', ttl: 3600, value: '_#{group}')]
    public static function getByGroup(string $group): array
    {
        $configs = self::byGroup($group)->ordered()->get();

        $result = [];
        foreach ($configs as $config) {
            $result[$config->key] = self::castValue($config->value, $config->type);
        }

        return $result;
    }

    /**
     * 批量设置配置
     */
    public static function setMany(array $configs): void
    {
        foreach ($configs as $key => $value) {
            self::setValue($key, $value);
        }
    }
}

