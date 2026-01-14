<?php

declare(strict_types=1);

namespace Addons\AddonsStore\Model;

use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\SoftDeletes;

/**
 * 插件商店插件信息模型
 *
 * @property int $id
 * @property string $name 插件名称
 * @property string $slug 插件标识
 * @property string $identifier 插件标识符ID
 * @property string|null $description 插件描述
 * @property string|null $author 作者
 * @property string $version 版本号
 * @property string $category 分类
 * @property array|null $tags 标签
 * @property string|null $homepage 主页
 * @property string|null $repository 仓库地址
 * @property string|null $license 许可证
 * @property int $downloads 下载次数
 * @property float $rating 评分
 * @property int $reviews_count 评论数
 * @property int $status 状态：0=禁用，1=启用
 * @property bool $is_official 是否官方插件
 * @property bool $is_featured 是否推荐
 * @property int|null $user_id 用户ID
 * @property bool $is_free 是否免费
 * @property string|null $package_path 包文件路径
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class AddonsStoreAddon extends Model
{
    use SoftDeletes;

    /**
     * 表名
     */
    protected ?string $table = 'addons_store_addons';

    /**
     * 分类常量
     */
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_TOOL = 'tool';
    public const CATEGORY_THEME = 'theme';
    public const CATEGORY_OTHER = 'other';

    /**
     * 状态常量
     */
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'name',
        'slug',
        'identifier',
        'description',
        'author',
        'version',
        'category',
        'tags',
        'homepage',
        'repository',
        'license',
        'downloads',
        'rating',
        'reviews_count',
        'status',
        'is_official',
        'is_featured',
        'user_id',
        'is_free',
        'package_path',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'downloads' => 'integer',
        'rating' => 'decimal:2',
        'reviews_count' => 'integer',
        'status' => 'integer',
        'is_official' => 'boolean',
        'is_featured' => 'boolean',
        'user_id' => 'integer',
        'tags' => 'json',
        'is_free' => 'boolean',
        'identifier' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 获取最新版本
     */
    public function latestVersion()
    {
        return $this->hasOne(AddonsStoreVersion::class, 'addon_id')
            ->where('status', 1)
            ->orderBy('released_at', 'desc');
    }

    /**
     * 获取所有版本
     */
    public function versions()
    {
        return $this->hasMany(AddonsStoreVersion::class, 'addon_id')
            ->where('status', 1)
            ->orderBy('released_at', 'desc');
    }

    /**
     * 获取评价
     */
    public function reviews()
    {
        return $this->hasMany(AddonsStoreReview::class, 'addon_id')
            ->where('status', 1)
            ->orderBy('created_at', 'desc');
    }

    /**
     * 获取下载日志
     */
    public function downloadLogs()
    {
        return $this->hasMany(AddonsStoreDownloadLog::class, 'addon_id');
    }

    /**
     * 查询作用域：按标识符筛选
     */
    public function scopeByIdentifier($query, string $identifier)
    {
        return $query->where('identifier', $identifier);
    }

    /**
     * 查询作用域：按分类筛选
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * 查询作用域：按状态筛选
     */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 查询作用域：按作者筛选
     */
    public function scopeByAuthor($query, string $author)
    {
        return $query->where('author', 'like', "%{$author}%");
    }

    /**
     * 查询作用域：按名称或描述搜索
     */
    public function scopeByKeyword($query, string $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('name', 'like', "%{$keyword}%")
              ->orWhere('description', 'like', "%{$keyword}%")
              ->orWhere('author', 'like', "%{$keyword}%");
        });
    }

    /**
     * 查询作用域：官方插件
     */
    public function scopeOfficial($query)
    {
        return $query->where('is_official', true);
    }

    /**
     * 查询作用域：推荐插件
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * 查询作用域：免费插件
     */
    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    /**
     * 查询作用域：启用状态
     */
    public function scopeEnabled($query)
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
     * 查询作用域：按下载量排序（降序）
     */
    public function scopeOrderByDownloads($query)
    {
        return $query->orderBy('downloads', 'desc');
    }

    /**
     * 查询作用域：按评分排序（降序）
     */
    public function scopeOrderByRating($query)
    {
        return $query->orderBy('rating', 'desc');
    }

    /**
     * 查询作用域：按创建时间排序（降序）
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
