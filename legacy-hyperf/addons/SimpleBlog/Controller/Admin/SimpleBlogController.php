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

namespace Addons\SimpleBlog\Controller\Admin;

use Addons\SimpleBlog\Service\SimpleBlogService;
use App\Controller\AbstractController;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

/**
 * 简单博客管理控制器
 */
class SimpleBlogController extends AbstractController
{
    public function __construct(
        private readonly SimpleBlogService $blogService,
        protected RequestInterface         $request
    ) {}

    /**
     * 文章列表页面
     */
    public function index()
    {
        $params = $this->request->all();

        $posts = $this->blogService->getPostList($params);
        $categories = $this->blogService->getCategories();

        // 搜索配置
        $searchFields = ['keyword', 'status', 'category'];
        $fields = [
            [
                'name' => 'keyword',
                'label' => '关键词',
                'type' => 'text',
                'placeholder' => '搜索标题或内容',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => '全部状态'],
                    ['value' => 'published', 'label' => '已发布'],
                    ['value' => 'draft', 'label' => '草稿'],
                ],
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'category',
                'label' => '分类',
                'type' => 'select',
                'options' => array_map(function ($cat) {
                    return ['value' => $cat, 'label' => $cat];
                }, $categories),
                'placeholder' => '全部分类',
                'col' => 'col-12 col-md-3',
            ],
        ];

        $searchConfig = [
            'search_fields' => $searchFields,
            'fields' => $fields,
        ];

        // 统计信息（保留用于显示）
        $stats = $this->blogService->getPostStats();

        return $this->renderAdmin('admin.simple_blog.index', [
            'posts' => $posts,
            'stats' => $stats,
            'categories' => $categories,
            'params' => $params,
            'searchConfig' => $searchConfig,
        ]);
    }

    /**
     * 创建文章页面
     */
    public function create()
    {
        $categories = $this->blogService->getCategories();

        // 构建分类选项
        $categoryOptions = array_map(function ($cat) {
            return ['value' => $cat, 'label' => $cat];
        }, $categories);

        // 构建标签选项（使用分类作为标签候选）
        $tagOptions = array_map(function ($cat) {
            return ['value' => $cat, 'label' => $cat];
        }, $categories);

        $fields = [
            // 标题
            [
                'name' => 'title',
                'label' => '标题',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入文章标题',
                'default' => '',
                'col' => 'col-12',
                'group' => '文章内容',
            ],
            // 内容
            [
                'name' => 'content',
                'label' => '内容',
                'type' => 'rich_text',
                'required' => true,
                'placeholder' => '请输入文章内容，支持富文本编辑',
                'default' => '',
                'col' => 'col-12',
                'rows' => 15,
                'group' => '文章内容',
            ],
            // 状态
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'draft', 'label' => '草稿'],
                    ['value' => 'published', 'label' => '立即发布'],
                ],
                'default' => 'draft',
                'col' => 'col-12 col-md-6',
                'group' => '发布设置',
            ],
            // 分类
            [
                'name' => 'category',
                'label' => '分类',
                'type' => 'select',
                'required' => false,
                'options' => array_merge([['value' => '', 'label' => '请选择分类']], $categoryOptions),
                'default' => '',
                'col' => 'col-12 col-md-6',
                'group' => '发布设置',
            ],
            // 标签
            [
                'name' => 'tags',
                'label' => '标签',
                'type' => 'select',
                'required' => false,
                'multiple' => true,
                'options' => $tagOptions,
                'default' => [],
                'col' => 'col-12',
                'placeholder' => '选择或输入标签',
                'group' => '发布设置',
            ],
            // 是否精选
            [
                'name' => 'is_featured',
                'label' => '设为精选文章',
                'type' => 'switch',
                'required' => false,
                'default' => false,
                'col' => 'col-12 col-md-6',
                'group' => '发布设置',
            ],
            // 是否置顶
            [
                'name' => 'is_pinned',
                'label' => '置顶显示',
                'type' => 'switch',
                'required' => false,
                'default' => false,
                'col' => 'col-12 col-md-6',
                'group' => '发布设置',
            ],
            // 发布时间
            [
                'name' => 'published_at',
                'label' => '发布时间',
                'type' => 'datetime',
                'required' => false,
                'placeholder' => '选择发布时间',
                'default' => date('Y-m-d H:i'),
                'col' => 'col-12 col-md-6',
                'group' => '发布设置',
            ],
        ];

        // 构建表单 Schema
        $formSchema = [
            'fields' => $fields,
            'method' => 'POST',
            'submitUrl' => admin_route('simple_blog'),
            'redirectUrl' => admin_route('simple_blog'),
        ];

        // 转换为 JSON
        $formSchemaJson = json_encode($formSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->renderAdmin('admin.simple_blog.create', [
            'formSchemaJson' => $formSchemaJson,
        ]);
    }

    /**
     * 保存新文章
     */
    public function store()
    {
        $data = $this->request->post();

        // 基本验证
        if (empty($data['title'])) {
            return $this->error('文章标题不能为空');
        }

        if (empty($data['content'])) {
            return $this->error('文章内容不能为空');
        }

        try {
            $post = $this->blogService->createPost($data);

            return $this->success([
                'id' => $post->id,
                'redirect_url' => '/admin/simple_blog',
            ], '文章创建成功');
        } catch (\Exception $e) {
            return $this->error('文章创建失败：' . $e->getMessage());
        }
    }

    /**
     * 编辑文章页面
     */
    public function edit(int $id)
    {
        $post = $this->blogService->getPost($id);
        $categories = $this->blogService->getCategories();

        // 构建分类选项
        $categoryOptions = array_map(function ($cat) {
            return ['value' => $cat, 'label' => $cat];
        }, $categories);

        // 构建标签选项（使用分类作为标签候选）
        $tagOptions = array_map(function ($cat) {
            return ['value' => $cat, 'label' => $cat];
        }, $categories);

        $fields = [
            // 标题
            [
                'name' => 'title',
                'label' => '标题',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入文章标题',
                'default' => $post->title,
                'col' => 'col-12',
                'group' => '文章内容',
            ],
            // 内容
            [
                'name' => 'content',
                'label' => '内容',
                'type' => 'rich_text',
                'required' => true,
                'placeholder' => '请输入文章内容，支持富文本编辑',
                'default' => $post->content,
                'col' => 'col-12',
                'rows' => 15,
                'group' => '文章内容',
            ],
            // 状态
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'draft', 'label' => '草稿'],
                    ['value' => 'published', 'label' => '已发布'],
                ],
                'default' => $post->status,
                'col' => 'col-12 col-md-6',
                'group' => '发布设置',
            ],
            // 分类
            [
                'name' => 'category',
                'label' => '分类',
                'type' => 'select',
                'required' => false,
                'options' => array_merge([['value' => '', 'label' => '请选择分类']], $categoryOptions),
                'default' => $post->category ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '发布设置',
            ],
            // 标签
            [
                'name' => 'tags',
                'label' => '标签',
                'type' => 'select',
                'required' => false,
                'multiple' => true,
                'options' => $tagOptions,
                'default' => $post->tags ?? [],
                'col' => 'col-12',
                'placeholder' => '选择或输入标签',
                'group' => '发布设置',
            ],
            // 是否精选
            [
                'name' => 'is_featured',
                'label' => '设为精选文章',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $post->is_featured ? '1' : '0',
                'col' => 'col-12 col-md-6',
                'group' => '发布设置',
            ],
            // 是否置顶
            [
                'name' => 'is_pinned',
                'label' => '置顶显示',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $post->is_pinned ? '1' : '0',
                'col' => 'col-12 col-md-6',
                'group' => '发布设置',
            ],
            // 浏览量（只读显示）
            [
                'name' => 'view_count',
                'label' => '浏览量',
                'type' => 'text',
                'required' => false,
                'readonly' => true,
                'default' => (string) $post->view_count,
                'col' => 'col-12 col-md-6',
                'group' => '统计信息',
            ],
            // 发布时间
            [
                'name' => 'published_at',
                'label' => '发布时间',
                'type' => 'datetime',
                'required' => false,
                'placeholder' => '选择发布时间',
                'default' => $post->published_at ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '统计信息',
            ],
        ];

        $formSchema = [
            'title' => '编辑文章',
            'fields' => $fields,
            'submitUrl' => admin_route("simple_blog/{$id}"),
            'method' => 'PUT',
            'redirectUrl' => admin_route('simple_blog'),
        ];

        return $this->renderAdmin('admin.simple_blog.edit', [
            'post' => $post,
            'categories' => $categories,
            'formSchemaJson' => json_encode($formSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * 更新文章
     */
    public function update(int $id)
    {
        $data = $this->request->post();

        // 基本验证
        if (empty($data['title'])) {
            return $this->error('文章标题不能为空');
        }

        if (empty($data['content'])) {
            return $this->error('文章内容不能为空');
        }

        try {
            $result = $this->blogService->updatePost($id, $data);

            if ($result) {
                return $this->success([
                    'redirect_url' => '/admin/simple_blog',
                ], '文章更新成功');
            } else {
                return $this->error('文章更新失败');
            }
        } catch (\Exception $e) {
            return $this->error('文章更新失败：' . $e->getMessage());
        }
    }

    /**
     * 删除文章
     */
    public function destroy(int $id)
    {
        try {
            $result = $this->blogService->deletePost($id);

            if ($result) {
                return $this->success([], '文章删除成功');
            } else {
                return $this->error('文章删除失败');
            }
        } catch (\Exception $e) {
            return $this->error('文章删除失败：' . $e->getMessage());
        }
    }

    /**
     * API: 获取文章列表
     * 
     * @return ResponseInterface
     */
    public function apiList(): ResponseInterface
    {
        $params = $this->request->all();

        try {
            $posts = $this->blogService->getPostList($params);

            // 返回组件期望的格式：data.data 包含数组列表
            return $this->success([
                'data' => $posts->items(),
                'total' => $posts->total(),
                'page' => $posts->currentPage(),
                'page_size' => $posts->perPage(),
            ]);
        } catch (\Exception $e) {
            return $this->error('获取文章列表失败：' . $e->getMessage());
        }
    }

    /**
     * API: 获取单篇文章
     */
    public function apiShow(int $id)
    {
        try {
            $post = $this->blogService->getPost($id);

            return $this->success($post);
        } catch (\Exception $e) {
            return $this->error('获取文章失败：' . $e->getMessage());
        }
    }
}
