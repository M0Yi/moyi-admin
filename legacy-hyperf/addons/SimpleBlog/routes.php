<?php

declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;

/**
 * SimpleBlog 插件路由定义
 */

// ========================================
// 管理后台路由
// ========================================

Router::addGroup('/admin/{adminPath:[a-zA-Z0-9\-_]+}/simple_blog', function () {
    
    // 博客文章管理
    Router::get('', 'Addons\SimpleBlog\Controller\Admin\SimpleBlogController@index');
    Router::get('/create', 'Addons\SimpleBlog\Controller\Admin\SimpleBlogController@create');
    Router::post('', 'Addons\SimpleBlog\Controller\Admin\SimpleBlogController@store');
    Router::get('/{id}/edit', 'Addons\SimpleBlog\Controller\Admin\SimpleBlogController@edit');
    Router::put('/{id}', 'Addons\SimpleBlog\Controller\Admin\SimpleBlogController@update');
    Router::delete('/{id}', 'Addons\SimpleBlog\Controller\Admin\SimpleBlogController@destroy');

}, [
    // 应用中间件
    'middleware' => [
        \App\Middleware\AdminAuthMiddleware::class,      // 管理员认证
        \App\Middleware\PermissionMiddleware::class,     // 权限验证
        \App\Middleware\OperationLogMiddleware::class,   // 操作日志
    ]
]);

// ========================================
// 前端页面路由
// ========================================

Router::addGroup('/blog', function () {
    // 博客首页
    Router::get('', 'Addons\SimpleBlog\Controller\Web\SimpleBlogWebController@index');

    // 文章详情页
    Router::get('/post/{id}', 'Addons\SimpleBlog\Controller\Web\SimpleBlogWebController@show');

    // 分类文章列表
    Router::get('/category/{category}', 'Addons\SimpleBlog\Controller\Web\SimpleBlogWebController@category');
}, [
    // 根据配置决定是否需要认证中间件
    'middleware' => []
]);

// ========================================
// API 接口路由
// ========================================

Router::addGroup('/api/simple_blog', function () {
    // 获取文章列表
    Router::get('/posts', 'Addons\SimpleBlog\Controller\Admin\SimpleBlogController@apiList');

    // 获取单篇文章
    Router::get('/posts/{id}', 'Addons\SimpleBlog\Controller\Admin\SimpleBlogController@apiShow');
});
