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

namespace Addons\SimpleBlog\Service;

use Addons\SimpleBlog\Model\SimpleBlogPost;
use Hyperf\Paginator\LengthAwarePaginator;

/**
 * 简单博客服务类
 */
class SimpleBlogService
{
    /**
     * 获取文章列表
     */
    public function getPostList(array $params = []): LengthAwarePaginator
    {
        $query = SimpleBlogPost::query();

        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        // 分类筛选
        if (isset($params['category']) && $params['category'] !== '') {
            $query->where('category', $params['category']);
        }

        // 关键词搜索
        if (isset($params['keyword']) && $params['keyword'] !== '') {
            $keyword = $params['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        // 排序
        $orderBy = $params['order_by'] ?? 'created_at';
        $orderDirection = $params['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        // 分页
        $perPage = $params['per_page'] ?? 10;

        return $query->paginate($perPage);
    }

    /**
     * 创建文章
     */
    public function createPost(array $data): SimpleBlogPost
    {
        return SimpleBlogPost::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'status' => $data['status'] ?? SimpleBlogPost::STATUS_DRAFT,
            'category' => $data['category'] ?? '',
            'tags' => $data['tags'] ?? [],
            'metadata' => $data['metadata'] ?? [],
            'view_count' => 0,
            'like_count' => 0,
            'comment_count' => 0,
            'author_id' => $data['author_id'] ?? null,
            'is_featured' => $data['is_featured'] ?? false,
            'is_pinned' => $data['is_pinned'] ?? false,
            'published_at' => !empty($data['published_at']) ? $data['published_at'] : (
                ($data['status'] ?? SimpleBlogPost::STATUS_DRAFT) === SimpleBlogPost::STATUS_PUBLISHED ? now() : null
            ),
        ]);
    }

    /**
     * 更新文章
     */
    public function updatePost(int $id, array $data): bool
    {
        $post = SimpleBlogPost::findOrFail($id);

        $updateData = [
            'title' => $data['title'],
            'content' => $data['content'],
            'status' => $data['status'] ?? $post->status,
            'category' => $data['category'] ?? $post->category,
            'tags' => $data['tags'] ?? $post->tags,
            'metadata' => $data['metadata'] ?? $post->metadata,
            'is_featured' => $data['is_featured'] ?? $post->is_featured,
            'is_pinned' => $data['is_pinned'] ?? $post->is_pinned,
        ];

        // 处理发布时间：优先使用用户提交的时间
        if (array_key_exists('published_at', $data)) {
            $updateData['published_at'] = !empty($data['published_at']) ? $data['published_at'] : null;
        }

        return $post->update($updateData);
    }

    /**
     * 删除文章
     */
    public function deletePost(int $id): bool
    {
        $post = SimpleBlogPost::findOrFail($id);
        return $post->delete();
    }

    /**
     * 获取文章详情
     */
    public function getPost(int $id): SimpleBlogPost
    {
        $post = SimpleBlogPost::findOrFail($id);

        // 增加查看次数
        $post->incrementViewCount();

        return $post;
    }


    /**
     * 获取所有分类
     */
    public function getCategories(): array
    {
        return SimpleBlogPost::distinct()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->pluck('category')
            ->toArray();
    }

    /**
     * 获取公开文章列表（前端使用）
     *
     * @param array $params
     * @param int $params['limit'] 限制数量，不使用分页
     * @param int $params['per_page'] 每页数量（分页模式）
     * @return LengthAwarePaginator|\Hyperf\Database\Model\Collection
     */
    public function getPublicPosts(array $params = [])
    {
        // 如果指定了 limit，则不使用分页，返回有限数量
        if (isset($params['limit']) && is_numeric($params['limit'])) {
            return SimpleBlogPost::published()
                ->orderBy('published_at', 'desc')
                ->limit((int) $params['limit'])
                ->get();
        }

        $query = SimpleBlogPost::published();

        // 分类筛选
        if (isset($params['category']) && $params['category'] !== '') {
            $query->where('category', $params['category']);
        }

        // 关键词搜索 - 使用PostgreSQL全文搜索
        if (isset($params['keyword']) && $params['keyword'] !== '') {
            $keyword = $params['keyword'];

            // 首先尝试中文全文搜索
            $chineseResults = SimpleBlogPost::searchChinese($keyword, 1000);

            if ($chineseResults->total() > 0) {
                $ids = $chineseResults->getCollection()->pluck('id')->toArray();
                $query->whereIn('id', $ids)->orderByRaw("array_position(ARRAY[" . implode(',', $ids) . "], id)");
            } else {
                // 回退到传统LIKE搜索
                $query->where(function ($q) use ($keyword) {
                    $q->where('title', 'ilike', "%{$keyword}%")
                      ->orWhere('content', 'ilike', "%{$keyword}%");
                });
            }
        }

        // 标签筛选
        if (isset($params['tag']) && $params['tag'] !== '') {
            $query->whereRaw("? = ANY(tags)", [$params['tag']]);
        }

        // 精选文章筛选
        if (isset($params['featured']) && $params['featured']) {
            $query->where('is_featured', true);
        }

        // 排序
        if (isset($params['sort_by'])) {
            $sortBy = $params['sort_by'];
            $sortOrder = $params['sort_order'] ?? 'desc';

            switch ($sortBy) {
                case 'published_at':
                    $query->orderBy('published_at', $sortOrder);
                    break;
                case 'view_count':
                    $query->orderBy('view_count', $sortOrder);
                    break;
                case 'like_count':
                    $query->orderBy('like_count', $sortOrder);
                    break;
                default:
                    $query->orderBy('created_at', $sortOrder);
            }
        } else {
            // 默认按发布时间排序
            $query->orderBy('published_at', 'desc');
        }

        // 分页
        $perPage = $params['per_page'] ?? 10;

        return $query->paginate($perPage);
    }

    /**
     * 全文搜索文章
     */
    public function searchPosts(string $query, array $params = []): LengthAwarePaginator
    {
        // 使用PostgreSQL中文全文搜索
        $searchResults = SimpleBlogPost::searchChinese($query, 100);

        if ($searchResults->total() > 0) {
            $ids = $searchResults->getCollection()->pluck('id')->toArray();

            $postsQuery = SimpleBlogPost::whereIn('id', $ids)
                ->where('status', SimpleBlogPost::STATUS_PUBLISHED)
                ->orderByRaw("array_position(ARRAY[" . implode(',', $ids) . "], id)");

            $perPage = $params['per_page'] ?? 10;
            return $postsQuery->paginate($perPage);
        }

        // 如果没有搜索到结果，返回空的分页器
        return new LengthAwarePaginator([], 0, $params['per_page'] ?? 10);
    }

    /**
     * 获取文章统计信息（使用PostgreSQL函数）
     */
    public function getPostStats(): array
    {
        try {
            // 使用 PostgreSQL 连接
            $result = \Hyperf\DbConnection\Db::connection('pgsql')->select("SELECT * FROM get_blog_posts_stats()");

            if (!empty($result)) {
                $stats = $result[0];
                return [
                    'total_posts' => (int) $stats->total_posts,
                    'published_posts' => (int) $stats->published_posts,
                    'draft_posts' => (int) $stats->draft_posts,
                    'total_views' => (int) $stats->total_views,
                    'total_likes' => (int) $stats->total_likes,
                ];
            }
        } catch (\Throwable $e) {
            logger()->warning('[SimpleBlog] PostgreSQL函数调用失败，使用传统查询: ' . $e->getMessage());
        }

        // 回退到传统查询
        return [
            'total_posts' => SimpleBlogPost::count(),
            'published_posts' => SimpleBlogPost::published()->count(),
            'draft_posts' => SimpleBlogPost::draft()->count(),
            'total_views' => SimpleBlogPost::sum('view_count'),
            'total_likes' => SimpleBlogPost::sum('like_count'),
        ];
    }

    /**
     * 获取热门标签
     */
    public function getPopularTags(int $limit = 20): array
    {
        $tags = SimpleBlogPost::where('status', SimpleBlogPost::STATUS_PUBLISHED)
            ->whereNotNull('tags')
            ->selectRaw("unnest(tags) as tag, count(*) as count")
            ->groupBy('tag')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();

        return array_column($tags, 'count', 'tag');
    }

    /**
     * 获取相关文章（基于标签）
     */
    public function getRelatedPosts(int $postId, int $limit = 5): array
    {
        $post = SimpleBlogPost::find($postId);

        if (!$post || empty($post->tags)) {
            return [];
        }

        return SimpleBlogPost::where('id', '!=', $postId)
            ->where('status', SimpleBlogPost::STATUS_PUBLISHED)
            ->where(function ($query) use ($post) {
                foreach ($post->tags as $tag) {
                    $query->orWhereRaw("? = ANY(tags)", [$tag]);
                }
            })
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 获取精选文章
     */
    public function getFeaturedPosts(int $limit = 3): \Hyperf\Database\Model\Collection
    {
        return SimpleBlogPost::published()
            ->where(function ($query) {
                $query->where('is_featured', true)
                    ->orWhere('is_pinned', true);
            })
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 获取所有标签
     */
    public function getTags(): array
    {
        $tags = SimpleBlogPost::where('status', SimpleBlogPost::STATUS_PUBLISHED)
            ->whereNotNull('tags')
            ->where('tags', '!=', '{}')
            ->selectRaw("unnest(tags) as tag")
            ->distinct()
            ->pluck('tag')
            ->toArray();

        return $tags;
    }
}
