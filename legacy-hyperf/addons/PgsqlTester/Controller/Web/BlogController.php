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

namespace Addons\PgsqlTester\Controller\Web;

use Addons\PgsqlTester\Model\BlogPost;
use App\Controller\AbstractController;
use Exception;
use Hyperf\HttpServer\Contract\RequestInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * 博客Web控制器.
 */
class BlogController extends AbstractController
{
    /**
     * 博客发布页面.
     */
    public function publish(): ResponseInterface
    {
        $blogPost = BlogPost::all();
        print_r(['$blogPost' => $blogPost->all()]);
        return $this->render->render('addons.pgsql_tester.blog_publish');
    }

    /**
     * 处理博客发布提交.
     */
    public function store(RequestInterface $request): ResponseInterface
    {
        //        $blogPost = BlogPost::all();
        //        print_r(['$blogPost'=>$blogPost->all()]);
        $data = $request->all();

        // 记录开始处理发布        $blogPost = new BlogPost();
        //        $blogPost->create([
        //            'title' => '测试标题',
        //            'slug' => 'test-slug',
        //            'content' => '测试内容',
        //        ]);请求
        logger()->info('开始处理博客发布请求', [
            'title' => $data['title'] ?? '未提供',
            'category' => $data['category'] ?? '未分类',
            'publish_now' => isset($data['publish_now']) ? 'true' : 'false',
            'client_ip' => $request->getServerParams()['remote_addr'] ?? 'unknown',
        ]);

        try {
            // 验证数据
            $validatedData = $this->validateBlogData($data);
            logger()->info('博客数据验证通过', [
                'title' => $validatedData['title'],
                'status' => $validatedData['status'],
                'tag_count' => count($validatedData['tags'] ?? []),
            ]);
        } catch (InvalidArgumentException $e) {
            // 记录验证异常
            logger()->warning('博客发布请求验证失败', [
                'error_message' => $e->getMessage(),
                'title' => $data['title'] ?? '',
                'client_ip' => $request->getServerParams()['remote_addr'] ?? 'unknown',
            ]);

            return $this->render->render('addons.pgsql_tester.blog_publish', [
                'error' => $e->getMessage(),
                'oldInput' => $data,
            ]);
        }

        try {
            logger()->info('开始创建博客文章');
            // 准备创建数据（利用模型修改器自动处理）
            $createData = [
                'title' => $validatedData['title'],
                'description' => $validatedData['description'],
                'category' => $validatedData['category'] ?: null,
                'tags' => $validatedData['tags'] ?? [],
                'status' => $validatedData['status'],
                'author_id' => 1, // 默认作者ID
                'view_count' => 0,
            ];
            print_r(['$createData'=>$createData]);

            // 使用 create 方法，会自动触发修改器（如自动生成slug）
            $blogPost = BlogPost::create($createData);

            // 记录成功创建
            logger()->info('博客文章发布成功', [
                'blog_id' => $blogPost->id,
                'title' => $blogPost->title,
                'slug' => $blogPost->slug,
                'status' => $blogPost->status,
                'category' => $blogPost->category,
                'tags_count' => count($blogPost->tags ?? []),
                'created_at' => $blogPost->created_at->toDateTimeString(),
            ]);

            // 返回成功页面或重定向
            return $this->render->render('addons.pgsql_tester.blog_success', [
                'blog' => $blogPost,
                'message' => '博客文章发布成功！',
            ]);
        } catch (Exception $e) {
            // 记录发布失败
            logger()->error('博客文章发布失败', [
                'title' => $validatedData['title'] ?? 'unknown',
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            return $this->render->render('addons.pgsql_tester.blog_publish', [
                'error' => '发布失败：' . $e->getMessage(),
                'oldInput' => $data,
            ]);
        }
    }

    /**
     * 验证博客数据.
     */
    private function validateBlogData(array $data): array
    {
        logger()->info('开始详细验证博客数据字段');

        $errors = [];

        // 标题验证
        if (empty($data['title'])) {
            $errors['title'] = '标题不能为空';
            logger()->warning('标题验证失败：标题为空');
        } elseif (strlen($data['title']) > 200) {
            $errors['title'] = '标题不能超过200个字符';
            logger()->warning('标题验证失败：标题过长', [
                'title_length' => strlen($data['title']),
                'max_length' => 200,
            ]);
        } else {
            logger()->info('标题验证通过', [
                'title_length' => strlen($data['title']),
            ]);
        }

        // 描述验证
        if (empty($data['description'])) {
            $errors['description'] = '描述不能为空';
            logger()->warning('描述验证失败：描述为空');
        } elseif (strlen($data['description']) > 1000) {
            $errors['description'] = '描述不能超过1000个字符';
            logger()->warning('描述验证失败：描述过长', [
                'description_length' => strlen($data['description']),
                'max_length' => 1000,
            ]);
        } else {
            logger()->info('描述验证通过', [
                'description_length' => strlen($data['description']),
            ]);
        }

        // 分类验证
        if (! empty($data['category']) && ! in_array($data['category'], ['技术', '生活', '教程', '新闻', '其他'])) {
            $errors['category'] = '请选择有效的分类';
            logger()->warning('分类验证失败：无效分类', [
                'provided_category' => $data['category'],
                'valid_categories' => ['技术', '生活', '教程', '新闻', '其他'],
            ]);
        } else {
            logger()->info('分类验证通过', [
                'category' => $data['category'] ?? '未分类',
            ]);
        }

        // 标签验证
        $tags = [];
        if (! empty($data['tags'])) {
            $tags = array_map('trim', explode(',', $data['tags']));
            $tags = array_filter($tags, function ($tag) {
                return ! empty($tag);
            });
            logger()->info('标签验证通过', [
                'original_tags_string' => $data['tags'],
                'parsed_tags_count' => count($tags),
                'parsed_tags' => $tags,
            ]);
        } else {
            logger()->info('标签验证：无标签');
        }

        // 状态验证
        $status = isset($data['publish_now']) && $data['publish_now'] ? 'published' : 'draft';
        logger()->info('状态验证完成', [
            'publish_now_checked' => isset($data['publish_now']) && $data['publish_now'],
            'determined_status' => $status,
        ]);

        if (! empty($errors)) {
            logger()->error('数据验证失败', [
                'validation_errors' => $errors,
                'error_count' => count($errors),
            ]);

            throw new InvalidArgumentException('验证失败：' . implode(', ', $errors));
        }

        $validatedData = [
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'category' => $data['category'] ?? '',
            'tags' => $tags,
            'status' => $status,
        ];

        logger()->info('数据验证完全通过', [
            'final_title_length' => strlen($validatedData['title']),
            'final_description_length' => strlen($validatedData['description']),
            'final_category' => $validatedData['category'] ?: '未分类',
            'final_tags_count' => count($validatedData['tags']),
            'final_status' => $validatedData['status'],
        ]);

        return $validatedData;
    }
}
