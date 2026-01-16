<?php

declare(strict_types=1);

/**
 * 管理员后台路由配置
 *
 * 本文件包含所有管理员后台相关的路由定义
 * 包括认证、系统管理、通用CRUD等功能
 */

use Hyperf\HttpServer\Router\Router;

/**
 * 定义管理员路由组
 *
 * @param string $adminPath 管理员路径参数（支持动态路径）
 */
Router::addGroup('/admin/{adminPath:[a-zA-Z0-9\-_]+}', function () {
    // ========================================
    // 认证相关路由（无需登录）
    // ========================================
    Router::get('/login', 'App\Controller\Admin\AuthController@login');

    // 登录提交路由（需要验证码验证）
    Router::addGroup('', function () {
    Router::post('/login', 'App\Controller\Admin\AuthController@doLogin');
    }, [
        'middleware' => [
            \App\Middleware\LoginCaptchaMiddleware::class,
        ]
    ]);

    Router::get('/logout', 'App\Controller\Admin\AuthController@logout');

    // Cookie 测试页面（无需登录，用于排查登录问题）
    Router::addGroup('/cookie-test', function () {
        Router::get('', 'App\Controller\Admin\CookieTestController@index');
        Router::get('/guard-check', 'App\Controller\Admin\CookieTestController@guardCheck');
        Router::post('/set', 'App\Controller\Admin\CookieTestController@setTestCookie');
        Router::post('/delete', 'App\Controller\Admin\CookieTestController@deleteTestCookie');
    });

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
        Router::post('/generate/{id:\d+}', 'App\Controller\Admin\System\CrudGeneratorController@generate');
        Router::get('/download/{id:\d+}', 'App\Controller\Admin\System\CrudGeneratorController@download');
        Router::delete('/{id:\d+}', 'App\Controller\Admin\System\CrudGeneratorController@delete');
    }, [
        'middleware' => [
            \App\Middleware\AdminAuthMiddleware::class,
            \App\Middleware\PermissionMiddleware::class,
            \App\Middleware\SuperAdminMiddleware::class,
        ]
    ]);

    // ========================================
    // 后台页面路由（需要登录）
    // ========================================
    Router::addGroup('', function () {
        // 仪表盘
        Router::get('/dashboard', 'App\Controller\Admin\DashboardController@index');

        // 测试页面路由已移至动态加载：app/Routes/test.php

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
        // 用户管理
        // ========================================
        Router::addGroup('/system/users', function () {
            Router::get('', 'App\Controller\Admin\System\UserController@index');
            Router::get('/create', 'App\Controller\Admin\System\UserController@create');
            Router::post('', 'App\Controller\Admin\System\UserController@store');
            Router::get('/{id:\d+}/edit', 'App\Controller\Admin\System\UserController@edit');
            Router::put('/{id:\d+}', 'App\Controller\Admin\System\UserController@update');
            Router::delete('/{id:\d+}', 'App\Controller\Admin\System\UserController@destroy');
            Router::post('/batch-destroy', 'App\Controller\Admin\System\UserController@batchDestroy');
            Router::post('/{id:\d+}/toggle-status', 'App\Controller\Admin\System\UserController@toggleStatus');
        });

        // ========================================
        // 角色管理（仅超级管理员可访问）
        // ========================================
        Router::addGroup('/system/roles', function () {
            Router::get('', 'App\Controller\Admin\System\RoleController@index');
            Router::get('/create', 'App\Controller\Admin\System\RoleController@create');
            Router::post('', 'App\Controller\Admin\System\RoleController@store');
            Router::get('/{id:\d+}/edit', 'App\Controller\Admin\System\RoleController@edit');
            Router::put('/{id:\d+}', 'App\Controller\Admin\System\RoleController@update');
            Router::delete('/{id:\d+}', 'App\Controller\Admin\System\RoleController@destroy');
            Router::post('/batch-destroy', 'App\Controller\Admin\System\RoleController@batchDestroy');
            Router::post('/{id:\d+}/toggle-status', 'App\Controller\Admin\System\RoleController@toggleStatus');
        }, [
            'middleware' => [
                \App\Middleware\SuperAdminMiddleware::class,
            ]
        ]);

        // ========================================
        // 权限管理
        // ========================================
        Router::addGroup('/system/permissions', function () {
            Router::get('', 'App\Controller\Admin\System\PermissionController@index');
            Router::get('/create', 'App\Controller\Admin\System\PermissionController@create');
            Router::post('', 'App\Controller\Admin\System\PermissionController@store');
            Router::get('/{id:\d+}/edit', 'App\Controller\Admin\System\PermissionController@edit');
            Router::put('/{id:\d+}', 'App\Controller\Admin\System\PermissionController@update');
            Router::delete('/{id:\d+}', 'App\Controller\Admin\System\PermissionController@destroy');
            Router::post('/batch-destroy', 'App\Controller\Admin\System\PermissionController@batchDestroy');
            Router::post('/{id:\d+}/toggle-status', 'App\Controller\Admin\System\PermissionController@toggleStatus');
        });

        // ========================================
        // 站点设置
        // ========================================
        Router::addGroup('/system/sites', function () {
            Router::get('', 'App\Controller\Admin\System\SiteController@edit');
            Router::put('', 'App\Controller\Admin\System\SiteController@update');
            Router::get('/options', 'App\Controller\Admin\System\SiteController@options');
            Router::get('/status', 'App\Controller\Admin\System\SiteController@status');
        });

        // ========================================
        // 远程数据库连接管理
        // ========================================
        Router::addGroup('/system/database-connections', function () {
            Router::get('', 'App\Controller\Admin\System\DatabaseConnectionController@index');
            Router::get('/create', 'App\Controller\Admin\System\DatabaseConnectionController@create');
            Router::post('', 'App\Controller\Admin\System\DatabaseConnectionController@store');
            Router::get('/{id:\d+}/edit', 'App\Controller\Admin\System\DatabaseConnectionController@edit');
            Router::put('/{id:\d+}', 'App\Controller\Admin\System\DatabaseConnectionController@update');
            Router::delete('/{id:\d+}', 'App\Controller\Admin\System\DatabaseConnectionController@destroy');
            Router::post('/batch-destroy', 'App\Controller\Admin\System\DatabaseConnectionController@batchDestroy');
            Router::post('/{id:\d+}/toggle-status', 'App\Controller\Admin\System\DatabaseConnectionController@toggleStatus');
            Router::post('/{id:\d+}/test-connection', 'App\Controller\Admin\System\DatabaseConnectionController@testConnection');
        });

        // ========================================
        // 操作日志
        // ========================================
        Router::addGroup('/system/operation-logs', function () {
            Router::get('', 'App\Controller\Admin\System\OperationLogController@index');
            Router::get('/{id:\d+}', 'App\Controller\Admin\System\OperationLogController@show');
            Router::delete('/{id:\d+}', 'App\Controller\Admin\System\OperationLogController@destroy');
        });

        // ========================================
        // 登录日志
        // ========================================
        Router::addGroup('/system/login-logs', function () {
            Router::get('', 'App\Controller\Admin\System\LoginLogController@index');
            Router::get('/{id:\d+}', 'App\Controller\Admin\System\LoginLogController@show');
            Router::delete('/{id:\d+}', 'App\Controller\Admin\System\LoginLogController@destroy');
        });

        // ========================================
        // 错误统计日志
        // ========================================
        Router::addGroup('/system/error-statistics', function () {
            Router::get('', 'App\Controller\Admin\System\ErrorStatisticController@index');
            Router::get('/{id:\d+}', 'App\Controller\Admin\System\ErrorStatisticController@show');
            Router::delete('/{id:\d+}', 'App\Controller\Admin\System\ErrorStatisticController@destroy');
            Router::post('/{id:\d+}/resolve', 'App\Controller\Admin\System\ErrorStatisticController@resolve');
            Router::post('/batch-resolve', 'App\Controller\Admin\System\ErrorStatisticController@batchResolve');
        });

        // ========================================
        // 拦截日志
        // ========================================
        Router::addGroup('/system/intercept-logs', function () {
            Router::get('', 'App\Controller\Admin\System\InterceptLogController@index');
            Router::get('/{id:\d+}', 'App\Controller\Admin\System\InterceptLogController@show');
            Router::delete('/{id:\d+}', 'App\Controller\Admin\System\InterceptLogController@destroy');
            Router::post('/batch-destroy', 'App\Controller\Admin\System\InterceptLogController@batchDestroy');
            Router::get('/statistics', 'App\Controller\Admin\System\InterceptLogController@statistics');
            Router::post('/cleanup', 'App\Controller\Admin\System\InterceptLogController@cleanup');
        });

        // ========================================
        // 文件管理
        // ========================================
        Router::addGroup('/system/upload-files', function () {
            Router::get('', 'App\Controller\Admin\System\UploadFileController@index');
            Router::get('/create', 'App\Controller\Admin\System\UploadFileController@create');
            Router::put('/upload/{path:.+}', 'App\Controller\Admin\System\UploadFileController@upload');
            Router::get('/{id:\d+}', 'App\Controller\Admin\System\UploadFileController@show');
            Router::get('/{id:\d+}/preview', 'App\Controller\Admin\System\UploadFileController@preview');
            Router::get('/{id:\d+}/check', 'App\Controller\Admin\System\UploadFileController@check');
            Router::post('/{id:\d+}/check', 'App\Controller\Admin\System\UploadFileController@check');
            Router::delete('/{id:\d+}', 'App\Controller\Admin\System\UploadFileController@destroy');
            Router::post('/batch-destroy', 'App\Controller\Admin\System\UploadFileController@batchDestroy');
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

        // ========================================
        // 插件管理
        // ========================================
        Router::addGroup('/system/addons', function () {
            Router::get('', 'App\Controller\Admin\System\AddonController@index');
            Router::get('/list', 'App\Controller\Admin\System\AddonController@list');
            Router::get('/{addonId}', 'App\Controller\Admin\System\AddonController@show');
            Router::get('/{addonId}/config', 'App\Controller\Admin\System\AddonController@config');
            Router::post('/{addonId}/save-config', 'App\Controller\Admin\System\AddonController@saveConfig');
            Router::post('/install', 'App\Controller\Admin\System\AddonController@install');
            Router::post('/install-store/{addonId}', 'App\Controller\Admin\System\AddonController@installStoreAddon');
            Router::post('/upgrade-store/{addonId}', 'App\Controller\Admin\System\AddonController@upgradeStoreAddon');
            Router::post('/{addonId}/enable', 'App\Controller\Admin\System\AddonController@enable');
            Router::post('/{addonId}/disable', 'App\Controller\Admin\System\AddonController@disable');
            Router::post('/{addonId}/install', 'App\Controller\Admin\System\AddonController@installAddon');
            Router::post('/{addonId}/uninstall', 'App\Controller\Admin\System\AddonController@uninstall');
            Router::get('/{addonId}/export', 'App\Controller\Admin\System\AddonController@export');
            Router::delete('/{addonId}', 'App\Controller\Admin\System\AddonController@delete');
            Router::post('/refresh', 'App\Controller\Admin\System\AddonController@refresh');
            Router::post('/test-config/{addonName}', 'App\Controller\Admin\System\AddonController@testConfigUpdate');
            Router::get('/route-status', 'App\Controller\Admin\System\AddonController@getRouteLoadStatus');
            Router::get('/test-store-api', 'App\Controller\Admin\System\AddonController@testStoreApi');
            Router::get('/debug-store-list', 'App\Controller\Admin\System\AddonController@debugStoreList');
            Router::get('/check-addon-status', 'App\Controller\Admin\System\AddonController@checkAddonStatus');
            Router::get('/test-filters-parsing', 'App\Controller\Admin\System\AddonController@testFiltersParsing');
            Router::get('/test-local-addons', 'App\Controller\Admin\System\AddonController@testLocalAddons');
            Router::get('/test-addon-intersection', 'App\Controller\Admin\System\AddonController@testAddonIntersection');
            Router::get('/test-action-conditions', 'App\Controller\Admin\System\AddonController@testActionConditions');
        });
    }, [
        'middleware' => [
            \App\Middleware\AdminAuthMiddleware::class,
            \App\Middleware\PermissionMiddleware::class,
            \App\Middleware\OperationLogMiddleware::class,
            \App\Middleware\InterceptLogMiddleware::class,
        ]
    ]);

}, [
    'middleware' => [
        \App\Middleware\AdminEntryMiddleware::class,
    ]
]);
