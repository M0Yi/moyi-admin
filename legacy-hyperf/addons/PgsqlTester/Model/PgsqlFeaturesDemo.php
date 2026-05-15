<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Addons\PgsqlTester\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Model;

use function Hyperf\Support\now;

/**
 * PostgreSQL 特性演示表模型.
 *
 * @property int $id 主键ID
 * @property null|string $uuid_field UUID字段（简化版）
 * @property string $status 用户状态枚举
 * @property null|string $priority 优先级枚举
 * @property null|array $tags 标签数组
 * @property null|array $metadata 键值对存储
 * @property null|array $settings JSON配置数据
 * @property null|string $contact_name 联系人姓名
 * @property null|string $contact_email 联系人邮箱
 * @property null|string $contact_phone 联系人电话
 * @property string $address_type 地址类型
 * @property null|string $title 标题
 * @property null|string $content 内容
 * @property null|string $content_vector 全文搜索向量
 * @property null|string $content_zh_vector 中文分词向量
 * @property null|array $search_tokens 搜索关键词数组
 * @property null|float $location_lat 地理位置纬度
 * @property null|float $location_lng 地理位置经度
 * @property null|string $ip_address IP地址
 * @property null|string $mac_address MAC地址
 * @property null|float $price 货币类型价格
 * @property null|string $bit_flags 位图标志
 * @property null|string $byte_data 二进制数据
 * @property null|float $large_number 高精度数值
 * @property Carbon $created_at 创建时间带时区
 * @property Carbon $updated_at 更新时间带时区
 * @property null|Carbon $expires_at 过期时间
 * @property null|Carbon $birth_date 出生日期
 * @property null|string $work_time 工作时间
 * @property null|string $timezone_offset 时区偏移
 * @property bool $is_active 是否激活
 * @property float $score 评分
 * @property int $view_count 查看次数
 * @property int $data_size 数据大小
 */
class PgsqlFeaturesDemo extends Model
{
    /**
     * 用户状态常量.
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    /**
     * 优先级常量.
     */
    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    /**
     * 地址类型常量.
     */
    public const ADDRESS_TYPE_HOME = 'home';

    public const ADDRESS_TYPE_WORK = 'work';

    public const ADDRESS_TYPE_OTHER = 'other';

    /**
     * 表名.
     */
    protected ?string $table = 'pgsql_features_demo';

    protected ?string $connection = 'pgsql';

    /**
     * 可批量赋值的属性.
     */
    protected array $fillable = [
        'uuid_field',
        'status',
        'priority',
        'tags',
        'metadata',
        'settings',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address_type',
        'title',
        'content',
        'content_vector',
        'content_zh_vector',
        'search_tokens',
        'location_lat',
        'location_lng',
        'ip_address',
        'mac_address',
        'price',
        'bit_flags',
        'byte_data',
        'large_number',
        'expires_at',
        'birth_date',
        'work_time',
        'timezone_offset',
        'is_active',
        'score',
        'view_count',
        'data_size',
    ];

    /**
     * 类型转换.
     */
    protected array $casts = [
        'id' => 'integer',
        'tags' => 'array',
        'metadata' => 'array',
        'settings' => 'json',
        'search_tokens' => 'array',
        'location_lat' => 'decimal:8',
        'location_lng' => 'decimal:8',
        'price' => 'decimal:2',
        'large_number' => 'decimal:8',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'expires_at' => 'datetime',
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'score' => 'decimal:2',
        'view_count' => 'integer',
        'data_size' => 'integer',
    ];

    /**
     * 查询作用域：按状态筛选.
     * @param mixed $query
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 查询作用域：按优先级筛选.
     * @param mixed $query
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * 查询作用域：按地址类型筛选.
     * @param mixed $query
     */
    public function scopeByAddressType($query, string $addressType)
    {
        return $query->where('address_type', $addressType);
    }

    /**
     * 查询作用域：激活状态
     * @param mixed $query
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 查询作用域：未过期
     * @param mixed $query
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * 查询作用域：按标签包含.
     * @param mixed $query
     */
    public function scopeWithTag($query, string $tag)
    {
        return $query->where('tags', '@>', json_encode([$tag]));
    }

    /**
     * 查询作用域：按元数据键值筛选.
     * @param mixed $query
     * @param mixed $value
     */
    public function scopeWithMetadata($query, string $key, $value)
    {
        return $query->where('metadata', '@>', json_encode([$key => $value]));
    }

    /**
     * 查询作用域：按设置键值筛选.
     * @param mixed $query
     * @param mixed $value
     */
    public function scopeWithSetting($query, string $key, $value)
    {
        return $query->where('settings', '@>', json_encode([$key => $value]));
    }

    /**
     * 查询作用域：按地理位置范围.
     * @param mixed $query
     */
    public function scopeInLocationRange($query, float $lat, float $lng, float $distanceKm = 10)
    {
        // PostgreSQL 地理位置计算（简化版）
        // 注意：这里需要安装 PostGIS 扩展才能使用真正的地理函数
        return $query->whereRaw(
            'earth_distance(ll_to_earth(?, ?), ll_to_earth(location_lat, location_lng)) <= ?',
            [$lat, $lng, $distanceKm * 1000] // 转换为米
        );
    }

    /**
     * 查询作用域：按价格范围.
     * @param mixed $query
     */
    public function scopePriceBetween($query, float $min, float $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    /**
     * 查询作用域：按评分范围.
     * @param mixed $query
     */
    public function scopeScoreBetween($query, float $min, float $max)
    {
        return $query->whereBetween('score', [$min, $max]);
    }

    /**
     * 查询作用域：按查看次数排序（降序）.
     * @param mixed $query
     */
    public function scopeOrderByViewCount($query)
    {
        return $query->orderBy('view_count', 'desc');
    }

    /**
     * 查询作用域：按评分排序（降序）.
     * @param mixed $query
     */
    public function scopeOrderByScore($query)
    {
        return $query->orderBy('score', 'desc');
    }

    /**
     * 查询作用域：按创建时间排序（降序）.
     * @param mixed $query
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * 查询作用域：全文搜索（使用 PostgreSQL 全文搜索）.
     * @param mixed $query
     */
    public function scopeFullTextSearch($query, string $search)
    {
        return $query->whereRaw(
            "content_vector @@ plainto_tsquery('english', ?)",
            [$search]
        );
    }

    /**
     * 查询作用域：中文全文搜索.
     * @param mixed $query
     */
    public function scopeChineseFullTextSearch($query, string $search)
    {
        return $query->whereRaw(
            "content_zh_vector @@ plainto_tsquery('simple', ?)",
            [$search]
        );
    }

    /**
     * 查询作用域：模糊匹配标题或内容.
     * @param mixed $query
     */
    public function scopeSearch($query, string $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('title', 'ILIKE', "%{$keyword}%")
                ->orWhere('content', 'ILIKE', "%{$keyword}%")
                ->orWhere('contact_name', 'ILIKE', "%{$keyword}%")
                ->orWhere('contact_email', 'ILIKE', "%{$keyword}%");
        });
    }

    /**
     * 查询作用域：按IP地址范围.
     * @param mixed $query
     */
    public function scopeByIpRange($query, string $network)
    {
        return $query->whereRaw('ip_address <<= ?', [$network]);
    }

    /**
     * 查询作用域：按MAC地址范围.
     * @param mixed $query
     */
    public function scopeByMacRange($query, string $network)
    {
        return $query->whereRaw('mac_address <<= ?', [$network]);
    }

    /**
     * 访问器：获取格式化的价格
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price === null || $this->price === '') {
            return '';
        }

        // 确保价格是数值类型
        $price = is_numeric($this->price) ? (float) $this->price : 0.00;

        return '$' . number_format($price, 2);
    }

    /**
     * 访问器：获取状态标签.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => '激活',
            self::STATUS_INACTIVE => '未激活',
            self::STATUS_SUSPENDED => '暂停',
            default => '未知',
        };
    }

    /**
     * 访问器：获取优先级标签.
     */
    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => '低',
            self::PRIORITY_MEDIUM => '中',
            self::PRIORITY_HIGH => '高',
            self::PRIORITY_URGENT => '紧急',
            default => '未知',
        };
    }

    /**
     * 修改器：设置标签数组.
     * @param mixed $value
     */
    public function setTagsAttribute($value): void
    {
        $this->attributes['tags'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * 修改器：设置元数据.
     * @param mixed $value
     */
    public function setMetadataAttribute($value): void
    {
        $this->attributes['metadata'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * 修改器：设置设置数据.
     * @param mixed $value
     */
    public function setSettingsAttribute($value): void
    {
        $this->attributes['settings'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * 修改器：设置搜索关键词数组.
     * @param mixed $value
     */
    public function setSearchTokensAttribute($value): void
    {
        $this->attributes['search_tokens'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * 自定义方法：增加查看次数.
     */
    public function incrementViewCount(): int
    {
        return $this->increment('view_count');
    }

    /**
     * 自定义方法：更新评分.
     */
    public function updateScore(float $newScore): bool
    {
        if ($newScore < 0 || $newScore > 5) {
            return false;
        }

        return $this->update(['score' => $newScore]);
    }

    /**
     * 自定义方法：检查是否过期
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * 自定义方法：获取距离指定坐标的距离（公里）.
     */
    public function getDistanceFrom(float $lat, float $lng): ?float
    {
        if (! $this->location_lat || ! $this->location_lng) {
            return null;
        }

        // 简化的距离计算公式（实际应该使用 PostGIS）
        $earthRadius = 6371; // 地球半径（公里）

        $latDelta = deg2rad($lat - $this->location_lat);
        $lngDelta = deg2rad($lng - $this->location_lng);

        $a = sin($latDelta / 2) * sin($latDelta / 2)
            + cos(deg2rad($this->location_lat)) * cos(deg2rad($lat))
            * sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * 自定义方法：获取标签字符串.
     */
    public function getTagsString(): string
    {
        return $this->tags ? implode(', ', $this->tags) : '';
    }

    /**
     * 自定义方法：添加标签.
     */
    public function addTag(string $tag): bool
    {
        $tags = $this->tags ?? [];
        if (! in_array($tag, $tags)) {
            $tags[] = $tag;
            return $this->update(['tags' => $tags]);
        }
        return true;
    }

    /**
     * 自定义方法：移除标签.
     */
    public function removeTag(string $tag): bool
    {
        $tags = $this->tags ?? [];
        $key = array_search($tag, $tags);
        if ($key !== false) {
            unset($tags[$key]);
            return $this->update(['tags' => array_values($tags)]);
        }
        return true;
    }
}

