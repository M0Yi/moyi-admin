<?php

declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;

/**
 * PgsqlTester 插件路由定义
 */

// ========================================
// 管理后台路由
// ========================================

Router::addGroup('/admin/{adminPath:[a-zA-Z0-9\-_]+}/pgsql_tester', function () {

    // PostgreSQL 测试管理
    Router::get('', 'Addons\PgsqlTester\Controller\Admin\PgsqlTesterController@index');
    Router::get('/dashboard', 'Addons\PgsqlTester\Controller\Admin\PgsqlTesterController@dashboard');
    Router::get('/connection-test', 'Addons\PgsqlTester\Controller\Admin\PgsqlTesterController@connectionTest');
    Router::post('/connection-test', 'Addons\PgsqlTester\Controller\Admin\PgsqlTesterController@runConnectionTest');
    Router::get('/query-test', 'Addons\PgsqlTester\Controller\Admin\PgsqlTesterController@queryTest');
    Router::post('/query-test', 'Addons\PgsqlTester\Controller\Admin\PgsqlTesterController@runQueryTest');
    Router::get('/performance-test', 'Addons\PgsqlTester\Controller\Admin\PgsqlTesterController@performanceTest');
    Router::post('/performance-test', 'Addons\PgsqlTester\Controller\Admin\PgsqlTesterController@runPerformanceTest');
    Router::get('/table-info', 'Addons\PgsqlTester\Controller\Admin\PgsqlTesterController@tableInfo');
    Router::get('/logs', 'Addons\PgsqlTester\Controller\Admin\PgsqlTesterController@logs');

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

Router::addGroup('/api/pgsql_tester', function () {
    // 博客相关接口
    Router::post('/blog/create', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@createBlogPost');

    // 连接测试
    Router::get('/getPgsqlFeaturesDemoList', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@getPgsqlFeaturesDemoList');
    Router::post('/connection', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@runConnectionTest');

    // 查询测试
    Router::get('/query', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@testQuery');
    Router::post('/query', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@runQueryTest');

    // 性能测试
    Router::post('/performance', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@runPerformanceTest');

    // 数据库信息
    Router::get('/info', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@getDatabaseInfo');
    Router::get('/tables', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@getTables');
    Router::get('/extensions', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@getExtensions');

    // 统计信息
    Router::get('/stats', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@getStats');

    // PostgreSQL 特性测试接口
    Router::get('/chinese-search', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@runChineseSearchTest');
    Router::post('/geospatial', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@runGeospatialTest');
    Router::get('/jsonb-query', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@jsonbQueryTest');
    Router::get('/array-query', 'Addons\PgsqlTester\Controller\Api\PgsqlTesterApiController@arrayQueryTest');
});

// 博客发布页面（公开访问）
Router::get('/blog/publish', 'Addons\PgsqlTester\Controller\Web\BlogController@publish');
Router::post('/blog/publish', 'Addons\PgsqlTester\Controller\Web\BlogController@store');

//测试路由返回json
Router::get('/test1', function () {
    return json([
        'code' => 200,
        'msg' => 'success',
        'data' => 'test',
    ]);
});
