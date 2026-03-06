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

namespace Addons\SimpleBlog\Model;

use Addons\SimpleBlog\Service\VectorService;
use App\Model\PgsqlModel;
use Carbon\Carbon;
use Hyperf\Context\ApplicationContext;

/**
 * 简单博客文章表模型.
 *
 * @property int $id 主键ID
 * @property string $title 文章标题
 * @property string $content 文章内容
 * @property array $title_embedding 标题语义向量
 * @property array $content_embedding 内容语义向量
 * @property string $content_vector 全文搜索向量
 * @property string $content_zh_vector 中文分词向量
 * @property string $status 状态：draft=草稿，published=已发布
 * @property string $category 文章分类
 * @property array $tags 文章标签数组
 * @property mixed $metadata 文章元数据
 * @property int $view_count 查看次数
 * @property int $like_count 点赞次数
 * @property int $comment_count 评论数量
 * @property int $author_id 作者ID
 * @property bool $is_featured 是否精选文章
 * @property bool $is_pinned 是否置顶文章
 * @property Carbon $published_at 发布时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 */
class SimpleBlogPost extends PgsqlModel
{
    /**
     * 状态常量.
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /**
     * 表名.
     */
    protected ?string $table = 'simple_blog_posts';

    /**
     * 可批量赋值的属性.
     */
    protected array $fillable = [
        'title',
        'content',
        'status',
        'category',
        'tags',
        'metadata',
        'view_count',
        'like_count',
        'comment_count',
        'author_id',
        'is_featured',
        'is_pinned',
        'published_at',
        'title_embedding',
        'content_embedding',
    ];

    /**
     * 隐藏的属性.
     */
    protected array $hidden = [];

    /**
     * PostgreSQL 数组类型字段.
     */
    protected array $pgsqlArrays = ['tags'];

    /**
     * JSON/JSONB 类型字段.
     */
    protected array $pgsqlJson = ['metadata', 'title_embedding', 'content_embedding'];

    /**
     * 类型转换.
     * 注意: tags 和 metadata 等已由 pgsqlArrays/pgsqlJson 处理,不要在 casts 中重复定义
     */
    protected array $casts = [
        'id' => 'integer',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'author_id' => 'integer',
        'is_featured' => 'boolean',
        'is_pinned' => 'boolean',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 查询作用域：已发布
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * 查询作用域：草稿
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * 查询作用域：按分类筛选
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * 获取文章摘要
     */
    public function getSummaryAttribute(): string
    {
        // 从内容中提取前200个字符作为摘要
        $content = strip_tags($this->content);
        return mb_substr($content, 0, 200) . (mb_strlen($content) > 200 ? '...' : '');
    }

    /**
     * 增加查看次数
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * 获取状态标签
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => '草稿',
            self::STATUS_PUBLISHED => '已发布',
            default => '未知',
        };
    }

    /**
     * 获取格式化的创建时间
     */
    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at->format('Y-m-d H:i:s');
    }

    /**
     * 获取格式化的发布时间
     */
    public function getFormattedPublishedAtAttribute(): ?string
    {
        return $this->published_at?->format('Y-m-d H:i:s');
    }


    /**
     * 全文搜索（中文）
     */
    public static function searchChinese(string $query, int $limit = 20): \Hyperf\Paginator\Paginator
    {
        $sql = "
            SELECT id, title, content,
                   ts_rank(content_zh_vector, plainto_tsquery('zhparser', ?)) as rank
            FROM simple_blog_posts
            WHERE content_zh_vector @@ plainto_tsquery('zhparser', ?)
              AND status = 'published'
            ORDER BY rank DESC
            LIMIT ?
        ";

        // 使用 PostgreSQL 连接
        $results = \Hyperf\DbConnection\Db::connection('pgsql')->select($sql, [$query, $query, $limit]);

        // 获取完整文章数据
        $ids = array_column($results, 'id');
        if (empty($ids)) {
            return new \Hyperf\Paginator\Paginator([], 0, $limit);
        }

        $posts = self::whereIn('id', $ids)->get();

        return new \Hyperf\Paginator\Paginator($posts, count($posts), $limit);
    }

    /**
     * 增加点赞数
     */
    public function incrementLikes(): bool
    {
        return $this->increment('like_count');
    }

    /**
     * 增加评论数
     */
    public function incrementComments(): bool
    {
        return $this->increment('comment_count');
    }

    /**
     * 获取标签字符串
     */
    public function getTagsStringAttribute(): string
    {
        return is_array($this->tags) ? implode(', ', $this->tags) : '';
    }

    /**
     * 设置标签字符串
     */
    public function setTagsStringAttribute(string $value): void
    {
        $this->tags = array_map('trim', explode(',', $value));
    }

    /**
     * 获取阅读时间估算（分钟）
     */
    public function getEstimatedReadTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));
        $wordsPerMinute = 200; // 假设每分钟阅读200个单词
        return max(1, ceil($wordCount / $wordsPerMinute));
    }

    /**
     * 获取摘要（智能截取）
     */
    public function getSmartSummaryAttribute(): string
    {
        $content = strip_tags($this->content);

        // 查找第一个段落结束符
        $paragraphEnd = strpos($content, "\n\n");
        if ($paragraphEnd !== false && $paragraphEnd < 300) {
            return trim(substr($content, 0, $paragraphEnd));
        }

        // 查找句子结束符
        $sentenceEnd = strpos($content, '。');
        if ($sentenceEnd !== false && $sentenceEnd < 300) {
            return trim(substr($content, 0, $sentenceEnd + 1));
        }

        // 默认截取前200个字符
        return mb_substr($content, 0, 200) . (mb_strlen($content) > 200 ? '...' : '');
    }


    /**
     * 异步生成语义向量任务（协程方式）
     */
    public static function dispatchGenerateEmbedding(self $post): void
    {
        // 只处理已发布的文章
        if ($post->status !== self::STATUS_PUBLISHED) {
            return;
        }

        // 没有内容不生成
        if (empty($post->title) && empty($post->content)) {
            return;
        }

        // 使用协程处理
        \Hyperf\Coroutine\Coroutine::create(function () use ($post) {
            logger()->info('[SimpleBlog] 协程开始生成语义向量', ['post_id' => $post->id]);

            static::generateEmbeddingSync($post->id, $post->title, $post->content);

            logger()->info('[SimpleBlog] 协程语义向量生成完成', ['post_id' => $post->id]);
        });
    }

    /**
     * 同步生成语义向量（降级方案）
     */
    public static function generateEmbeddingSync(int $postId, string $title, string $content): void
    {
        $post = static::find($postId);
        if (! $post) {
            return;
        }

        $vectorService = ApplicationContext::getContainer()->get(VectorService::class);

        // 生成标题向量
        $titleEmbedding = null;
        if (! empty($title)) {
            $titleEmbedding = $vectorService->generateEmbedding($title);
        }

        // 生成内容向量
        $contentEmbedding = null;
        $contentText = trim(strip_tags($content));
        if (! empty($contentText)) {
            $contentEmbedding = $vectorService->generateEmbedding($contentText);
        }

        $updateData = [];
        if ($titleEmbedding !== null) {
            $updateData['title_embedding'] = $titleEmbedding['vector'];
        }
        if ($contentEmbedding !== null) {
            $updateData['content_embedding'] = $contentEmbedding['vector'];
        }

        if (! empty($updateData)) {
            // 使用 withoutEvents 避免触发循环
            $post->withoutEvents(function () use ($post, $updateData) {
                $post->update($updateData);
            });
            logger()->info('[SimpleBlog] 语义向量同步生成成功', [
                'post_id' => $postId,
                'has_title_embedding' => isset($updateData['title_embedding']),
                'has_content_embedding' => isset($updateData['content_embedding']),
            ]);
        }
    }
}
