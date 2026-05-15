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

namespace Addons\SimpleBlog\Controller\Web;

use Addons\SimpleBlog\Service\SimpleBlogService;
use App\Controller\AbstractController;

/**
 * 简单博客前端控制器
 */
class SimpleBlogWebController extends AbstractController
{
    public function __construct(
        private SimpleBlogService $blogService
    ) {}

    /**
     * 博客首页
     */
    public function index()
    {
        $params = $this->request->all();

        $posts = $this->blogService->getPublicPosts($params);
        $categories = $this->blogService->getCategories();

        // 获取精选文章（置顶或推荐）
        $featuredPosts = $this->blogService->getFeaturedPosts(3);

        // 获取最新文章
        $latestPosts = $this->blogService->getPublicPosts(['limit' => 5]);

        // 获取所有标签
        $tags = $this->blogService->getTags();

        // 导航状态
        $isHome = !isset($params['featured']) && !isset($params['sort_by']);
        $isFeatured = isset($params['featured']) && $params['featured'] == '1';
        $isHot = isset($params['sort_by']) && $params['sort_by'] === 'view_count';

        return $this->render->render('web.simple_blog.index', [
            'posts' => $posts,
            'categories' => $categories,
            'featuredPosts' => $featuredPosts,
            'latestPosts' => $latestPosts,
            'tags' => $tags,
            'params' => $params,
            'isHome' => $isHome,
            'isFeatured' => $isFeatured,
            'isHot' => $isHot,
        ]);
    }

    /**
     * 文章详情页
     */
    public function show(int $id)
    {
        try {
            $post = $this->blogService->getPost($id);

            // 只显示已发布的文章
            if ($post->status !== $post::STATUS_PUBLISHED) {
                abort(404, '文章不存在');
            }

            return $this->render->render('web.simple_blog.show', [
                'post' => $post,
                'isHome' => false,
                'isFeatured' => false,
                'isHot' => false,
            ]);
        } catch (\Exception $e) {
            abort(404, '文章不存在');
        }
    }

    /**
     * 分类文章列表
     */
    public function category(string $category)
    {
        $params = $this->request->all();
        $params['category'] = $category;

        $posts = $this->blogService->getPublicPosts($params);
        $categories = $this->blogService->getCategories();

        return $this->render->render('web.simple_blog.category', [
            'posts' => $posts,
            'categories' => $categories,
            'current_category' => $category,
            'params' => $params,
            'isHome' => false,
            'isFeatured' => false,
            'isHot' => false,
        ]);
    }
}
