<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Model\Admin\AdminUser;

/**
 * 博客文章管理控制器
 *
 * 这是一个示例控制器，用于演示标准的 CRUD 操作
 */
class SimpleBlogController extends AbstractController
{
    /**
     * 文章状态常量
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    /**
     * 文章列表
     */
    public function index()
    {
        // 获取查询参数
        $page = max(1, (int) ($this->request->query('page', 1)));
        $pageSize = min(100, max(1, (int) ($this->request->query('page_size', 15))));
        $keyword = trim($this->request->query('keyword', ''));
        $status = trim($this->request->query('status', ''));
        $category = trim($this->request->query('category', ''));

        // 构建查询
        $query = AdminUser::query();

        if (! empty($keyword)) {
            $query->where('username', 'like', "%{$keyword}%");
        }

        // 统计数据
        $stats = [
            'total_posts' => 10,
            'published_posts' => 8,
            'draft_posts' => 2,
            'total_views' => 1234,
        ];

        // 模拟分类
        $categories = ['技术', '生活', '随笔', '教程'];

        // 模拟文章列表
        $posts = $this->getMockPosts();

        return $this->renderAdmin('admin.simple_blog.index', [
            'posts' => $posts,
            'stats' => $stats,
            'categories' => $categories,
            'params' => [
                'keyword' => $keyword,
                'status' => $status,
                'category' => $category,
            ],
        ]);
    }

    /**
     * 创建文章页面
     */
    public function create()
    {
        $categories = ['技术', '生活', '随笔', '教程'];

        return $this->renderAdmin('admin.simple_blog.create', [
            'categories' => $categories,
        ]);
    }

    /**
     * 保存文章
     */
    public function store()
    {
        $data = $this->request->all();

        // TODO: 保存文章到数据库

        return $this->success([], '创建成功');
    }

    /**
     * 编辑文章页面
     */
    public function edit(int $id)
    {
        $categories = ['技术', '生活', '随笔', '教程'];

        // 模拟文章数据
        $post = $this->getMockPost($id);

        if (! $post) {
            return $this->fail('文章不存在');
        }

        return $this->renderAdmin('admin.simple_blog.edit', [
            'post' => $post,
            'categories' => $categories,
        ]);
    }

    /**
     * 更新文章
     */
    public function update(int $id)
    {
        $data = $this->request->all();

        // TODO: 更新文章

        return $this->success([], '更新成功');
    }

    /**
     * 删除文章
     */
    public function destroy(int $id)
    {
        // TODO: 删除文章

        return $this->success([], '删除成功');
    }

    /**
     * 获取模拟文章列表
     */
    private function getMockPosts(): array
    {
        return [
            (object) [
                'id' => 1,
                'title' => 'Hyperf 框架入门指南',
                'category' => '技术',
                'tags' => ['PHP', 'Hyperf', '框架'],
                'status' => 'published',
                'is_featured' => true,
                'is_pinned' => false,
                'view_count' => 1523,
                'created_at' => '2024-01-15 10:30:00',
            ],
            (object) [
                'id' => 2,
                'title' => '我的第一篇博客',
                'category' => '生活',
                'tags' => ['随笔', '日常'],
                'status' => 'published',
                'is_featured' => false,
                'is_pinned' => true,
                'view_count' => 856,
                'created_at' => '2024-01-10 14:20:00',
            ],
            (object) [
                'id' => 3,
                'title' => 'PHP 8 新特性详解',
                'category' => '技术',
                'tags' => ['PHP', '编程'],
                'status' => 'draft',
                'is_featured' => false,
                'is_pinned' => false,
                'view_count' => 0,
                'created_at' => '2024-01-08 09:15:00',
            ],
        ];
    }

    /**
     * 获取模拟文章
     */
    private function getMockPost(int $id): ?object
    {
        $posts = $this->getMockPosts();

        foreach ($posts as $post) {
            if ($post->id === $id) {
                return $post;
            }
        }

        return null;
    }
}
