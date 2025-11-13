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
use Hyperf\HttpServer\Router\Router;


// 站点初始化安装
Router::get('/install', 'App\Controller\Admin\InstallController@index');
Router::post('/install', 'App\Controller\Admin\InstallController@install');
Router::get('/install/check-environment', 'App\Controller\Admin\InstallController@checkEnvironment');

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');



Router::addGroup('/admin/{adminPath:[a-zA-Z0-9\-_]+}', function () {
    // ========================================
    // 认证相关路由（无需登录）
    // ========================================
    Router::get('/login', 'App\Controller\Admin\AuthController@login');
    Router::post('/login', 'App\Controller\Admin\AuthController@doLogin');
    Router::get('/logout', 'App\Controller\Admin\AuthController@logout');
    Router::get('', 'App\Controller\Admin\DashboardController@index');

    // ========================================
    // 后台页面路由（需要登录）
    // ========================================
    Router::addGroup('', function () {
        // 仪表盘
        Router::get('/dashboard', 'App\Controller\Admin\DashboardController@index');

        // ========================================
        // 通用 CRUD 接口（动态模型管理）
        // ========================================
        // 通过路由参数 {model} 指定要操作的模型
        // {model} 应该传入模型名（model_name），例如：AdminUser、AdminRole
        // 例如：/admin/admin/universal/AdminUser -> 管理 AdminUser 模型（对应 admin_users 表）
        // 注意：推荐使用 model_name，但也兼容 route_prefix、table_name 和 ID
        Router::addGroup('/universal/{model}', function () {
            // 列表页面
            Router::get('', 'App\Controller\Admin\System\UniversalCrudController@index');
            // 创建页面
            Router::get('/create', 'App\Controller\Admin\System\UniversalCrudController@create');
            // 保存数据
            Router::post('', 'App\Controller\Admin\System\UniversalCrudController@store');
            // 编辑页面
            Router::get('/{id:\d+}/edit', 'App\Controller\Admin\System\UniversalCrudController@edit');
            // 更新数据
            Router::put('/{id:\d+}', 'App\Controller\Admin\System\UniversalCrudController@update');
            // 删除数据
            Router::delete('/{id:\d+}', 'App\Controller\Admin\System\UniversalCrudController@destroy');
            // 批量删除
            Router::post('/batch-destroy', 'App\Controller\Admin\System\UniversalCrudController@batchDestroy');
            // 切换状态
            Router::post('/{id:\d+}/toggle-status', 'App\Controller\Admin\System\UniversalCrudController@toggleStatus');
            // 导出数据
            Router::get('/export', 'App\Controller\Admin\System\UniversalCrudController@export');
            // 搜索关联选项（支持搜索和分页）
            Router::get('/search-relation-options', 'App\Controller\Admin\System\UniversalCrudController@searchRelationOptions');
        });

    }, [
        'middleware' => [
            \App\Middleware\AdminAuthMiddleware::class,
        ]
    ]);

}, [
    'middleware' => [
        \App\Middleware\AdminEntryMiddleware::class,
    ]
]);

Router::get('/favicon.ico', function () {
    return '';
});
