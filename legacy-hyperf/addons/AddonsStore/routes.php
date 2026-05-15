<?php

declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;

/**
 * AddonsStore 插件路由定义
 */

// ========================================
// 管理后台路由
// ========================================
// Router::get('/', 'Addons\AddonsStore\Controller\Api\AddonsStoreApiController@list');

Router::addGroup('/admin/{adminPath:[a-zA-Z0-9\-_]+}/addons_store', function () {

            // 插件管理
            Router::get('', 'Addons\AddonsStore\Controller\Admin\AddonsStoreController@index');
            Router::get('/list', 'Addons\AddonsStore\Controller\Admin\AddonsStoreController@listData');
            Router::get('/create', 'Addons\AddonsStore\Controller\Admin\AddonsStoreController@create');
            Router::post('', 'Addons\AddonsStore\Controller\Admin\AddonsStoreController@store');
            Router::get('/{id:\d+}/edit', 'Addons\AddonsStore\Controller\Admin\AddonsStoreController@edit');
            Router::put('/{id:\d+}', 'Addons\AddonsStore\Controller\Admin\AddonsStoreController@update');
            Router::delete('/{id:\d+}', 'Addons\AddonsStore\Controller\Admin\AddonsStoreController@destroy');
            Router::post('/{id:\d+}/toggle-status', 'Addons\AddonsStore\Controller\Admin\AddonsStoreController@toggleStatus');
            Router::post('/upload', 'Addons\AddonsStore\Controller\Admin\AddonsStoreController@upload');

            // 版本管理
            Router::get('/versions', 'Addons\AddonsStore\Controller\Admin\AddonsStoreVersionController@index');
            Router::get('/versions/list', 'Addons\AddonsStore\Controller\Admin\AddonsStoreVersionController@listData');
            Router::get('/{addonId:\d+}/versions', 'Addons\AddonsStore\Controller\Admin\AddonsStoreVersionController@index');
            Router::get('/{addonId:\d+}/versions/list', 'Addons\AddonsStore\Controller\Admin\AddonsStoreVersionController@listData');
            Router::get('/versions/{versionId:\d+}/download', 'Addons\AddonsStore\Controller\Admin\AddonsStoreVersionController@download');
            Router::delete('/versions/{id:\d+}', 'Addons\AddonsStore\Controller\Admin\AddonsStoreVersionController@delete');
            Router::post('/versions/batch-destroy', 'Addons\AddonsStore\Controller\Admin\AddonsStoreVersionController@batchDestroy');
      
}, [
    // 应用中间件
    'middleware' => [
        \App\Middleware\AdminAuthMiddleware::class,      // 管理员认证
        \App\Middleware\PermissionMiddleware::class,     // 权限验证
        \App\Middleware\OperationLogMiddleware::class,   // 操作日志
    ]
]);

// ========================================
// API 接口路由
// ========================================

Router::addGroup('/api/addons_store', function () {
    // 插件列表
    Router::get('', 'Addons\AddonsStore\Controller\Api\AddonsStoreApiController@list');
    Router::get('/list', 'Addons\AddonsStore\Controller\Api\AddonsStoreApiController@list');

    // 插件统计
    Router::get('/stats', 'Addons\AddonsStore\Controller\Api\AddonsStoreApiController@stats');

    // 版本相关接口
    Router::get('/versions', 'Addons\AddonsStore\Controller\Api\AddonsStoreApiController@getVersions');
    Router::get('/versions/{versionId:\d+}/download', 'Addons\AddonsStore\Controller\Api\AddonsStoreApiController@downloadVersion');
    Router::delete('/versions/{versionId:\d+}', 'Addons\AddonsStore\Controller\Api\AddonsStoreApiController@deleteVersion');
});
