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

/**
 * 博客文章表模型.
 *
 * @property int $id 主键ID
 * @property string $title 文章标题
 * @property string $description 文章描述/摘要
 * @property string $slug URL友好的标识符
 * @property string $status 状态：draft=草稿，published=已发布
 * @property int $author_id 作者ID
 * @property string $category 文章分类
 * @property array $tags 标签数组
 * @property int $view_count 查看次数
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 */
class BlogPost extends Model
{
    /**
     * 状态常量.
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /**
     * 表名.
     */
    protected ?string $table = 'blog_posts';

    protected ?string $connection = 'pgsql';

    /**
     * 可批量赋值的属性.
     */
    protected array $fillable = [
        'title',
        'description',
        'slug',
        'status',
        'author_id',
        'category',
        'tags',
        'view_count',
    ];

    /**
     * 类型转换.
     */
    protected array $casts = [
        'id' => 'integer',
        'author_id' => 'integer',
        'tags' => 'array',
        'view_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 隐藏的属性.
     */
    protected array $hidden = [];

    /**
     * 需要追加到模型数组的访问器.
     */
    protected array $appends = [];

    /**
     * 获取文章作者.
     */
    public function author(): \Hyperf\Database\Model\Relations\BelongsTo
    {
        return $this->belongsTo(PgsqlFeaturesDemo::class, 'author_id', 'id');
    }

    /**
     * 范围查询：已发布文章.
     * @param mixed $query
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * 范围查询：草稿文章.
     * @param mixed $query
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * 范围查询：按分类筛选.
     * @param mixed $query
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * 范围查询：包含指定标签.
     * @param mixed $query
     */
    public function scopeWithTag($query, string $tag)
    {
        return $query->whereRaw("'{$tag}' = ANY(tags)");
    }

    /**
     * 范围查询：按作者筛选.
     * @param mixed $query
     */
    public function scopeByAuthor($query, int $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    /**
     * 范围查询：按标题或描述搜索.
     * @param mixed $query
     */
    public function scopeSearch($query, string $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('title', 'ILIKE', "%{$keyword}%")
                ->orWhere('description', 'ILIKE', "%{$keyword}%");
        });
    }

    /**
     * 范围查询：最热门文章（按查看次数排序）.
     * @param mixed $query
     */
    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderBy('view_count', 'desc')->limit($limit);
    }

    /**
     * 范围查询：最新文章.
     * @param mixed $query
     */
    public function scopeLatest($query, int $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * 访问器：获取文章状态标签.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PUBLISHED => '已发布',
            self::STATUS_DRAFT => '草稿',
            default => '未知',
        };
    }

    /**
     * 访问器：获取文章标签字符串.
     */
    public function getTagsStringAttribute(): string
    {
        return $this->tags ? implode(', ', $this->tags) : '';
    }




    /**
     * 增加查看次数.
     */
    public function incrementViewCount(): int
    {
        return $this->increment('view_count');
    }

    /**
     * 检查文章是否已发布.
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * 检查文章是否为草稿.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * 发布文章.
     */
    public function publish(): bool
    {
        return $this->update(['status' => self::STATUS_PUBLISHED]);
    }

    /**
     * 转为草稿.
     */
    public function draft(): bool
    {
        return $this->update(['status' => self::STATUS_DRAFT]);
    }

    /**
     * 生成URL友好的slug.
     */
    private function generateSlug(string $title): string
    {
        // 转换为小写
        $slug = strtolower($title);

        // 替换中文字符和特殊字符
        $slug = preg_replace('/[^\w\s-]/u', '', $slug);

        // 替换空格和多个连字符为单个连字符
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);

        // 移除开头和结尾的连字符
        $slug = trim($slug, '-');

        // 确保不为空
        if (empty($slug)) {
            $slug = 'post-' . time();
        }

        // 检查slug是否已存在，如果存在则添加数字后缀
        $originalSlug = $slug;
        $counter = 1;

        while (self::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            ++$counter;
        }

        return $slug;
    }
}
