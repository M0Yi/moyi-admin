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
    // CRUD 代码生成器（系统管理，仅超级管理员可访问）
    // ========================================
    Router::addGroup('/system/crud-generator', function () {
        Router::get('', 'App\Controller\Admin\System\CrudGeneratorController@index');
        Router::get('/create', 'App\Controller\Admin\System\CrudGeneratorController@create');
        Router::get('/config/{tableName}', 'App\Controller\Admin\System\CrudGeneratorController@config');
        Router::get('/configv2/{tableName}', 'App\Controller\Admin\System\CrudGeneratorController@configV2');
        Router::get('/raw-fields-config/{tableName}', 'App\Controller\Admin\System\CrudGeneratorController@getRawFieldsConfig');
        Router::get('/fields-config/{tableName}', 'App\Controller\Admin\System\CrudGeneratorController@getFieldsConfig');
        Router::post('/save-config', 'App\Controller\Admin\System\CrudGeneratorController@saveConfig');
        Router::post('/save-config-v2', 'App\Controller\Admin\System\CrudGeneratorController@saveConfigV2');
        Router::get('/preview/{id:\d+}', 'App\Controller\Admin\System\CrudGeneratorController@preview');
        Router::post('/generate/{id:\d+}', 'App\Controller\Admin\System\CrudGeneratorController@generate');
        Router::get('/download/{id:\d+}', 'App\Controller\Admin\System\CrudGeneratorController@download');
        Router::delete('/{id:\d+}', 'App\Controller\Admin\System\CrudGeneratorController@delete');
    }, [
        'middleware' => [
            \App\Middleware\AdminAuthMiddleware::class,
            \App\Middleware\SuperAdminMiddleware::class,
        ]
    ]);

    // ========================================
    // 后台页面路由（需要登录）
    // ========================================
    Router::addGroup('', function () {
        // 仪表盘
        Router::get('/dashboard', 'App\Controller\Admin\DashboardController@index');

        // 测试页面
        Router::get('/test', 'App\\Controller\\Admin\\TestController@index');

        // iframe 模式体验页
        Router::get('/system/iframe-demo', 'App\Controller\Admin\System\IframeDemoController@index');
        Router::get('/system/iframe-demo/modal-demo', 'App\Controller\Admin\System\IframeDemoController@modalDemo');

        // ========================================
            // 菜单管理
            // ========================================
        Router::addGroup('/system/menus', function () {
            Router::get('', 'App\Controller\Admin\System\MenuController@index');
            Router::get('/create', 'App\Controller\Admin\System\MenuController@create');
            Router::post('', 'App\Controller\Admin\System\MenuController@store');
            Router::get('/{id:\d+}/edit', 'App\Controller\Admin\System\MenuController@edit');
            Router::put('/{id:\d+}', 'App\Controller\Admin\System\MenuController@update');
            Router::delete('/{id:\d+}', 'App\Controller\Admin\System\MenuController@destroy');
            Router::post('/batch-destroy', 'App\Controller\Admin\System\MenuController@batchDestroy');
            Router::post('/{id:\d+}/toggle-status', 'App\Controller\Admin\System\MenuController@toggleStatus');
            Router::post('/update-sort', 'App\Controller\Admin\System\MenuController@updateSort');
        });

        // ========================================
        // 站点设置
        // ========================================
        Router::addGroup('/system/sites', function () {
            Router::get('', 'App\Controller\Admin\System\SiteController@edit');
            Router::put('', 'App\Controller\Admin\System\SiteController@update');
        });

        // ========================================
        // 图片上传 API（客户端直传PUT方案）
        // ========================================
        Router::post('/api/admin/upload/token', 'App\Controller\Admin\System\ImageUploadController@getUploadToken');
        Router::put('/api/admin/upload/{path:.+}', 'App\Controller\Admin\System\ImageUploadController@upload');



        // ========================================
        // 通用 CRUD 接口（动态模型管理）
        // ========================================
        // 通过路由参数 {model} 指定要操作的模型
        // {model} 应该传入模型名（model_name），例如：AdminUser、AdminRole
        // 例如：/admin/admin/universal/AdminUser -> 管理 AdminUser 模型（对应 admin_users 表）
        // 注意：推荐使用 model_name，但也兼容 route_slug、table_name 和 ID
        Router::addGroup('/u/{model}', function () {
            // 保存数据
            Router::post('', 'App\Controller\Admin\System\UniversalCrudController@store');
            // 列表页面
            Router::get('', 'App\Controller\Admin\System\UniversalCrudController@index');
            // 创建页面
            Router::get('/create', 'App\Controller\Admin\System\UniversalCrudController@create');
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
            // 回收站页面
            Router::get('/trash', 'App\Controller\Admin\System\UniversalCrudController@trash');
            // 恢复记录
            Router::post('/{id:\d+}/restore', 'App\Controller\Admin\System\UniversalCrudController@restore');
            // 永久删除记录（使用 DELETE 方法，但路由中需要匹配已删除的记录）
            Router::delete('/{id:\d+}/force-delete', 'App\Controller\Admin\System\UniversalCrudController@forceDelete');
            // 批量恢复
            Router::post('/batch-restore', 'App\Controller\Admin\System\UniversalCrudController@batchRestore');
            // 批量永久删除
            Router::post('/batch-force-delete', 'App\Controller\Admin\System\UniversalCrudController@batchForceDelete');
            // 清空回收站
            Router::post('/clear-trash', 'App\Controller\Admin\System\UniversalCrudController@clearTrash');
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
