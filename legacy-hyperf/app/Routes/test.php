<?php

declare(strict_types=1);

/**
 * 测试页面路由
 * 
 * 注意：此文件仅用于开发测试环境
 * 生产环境应该删除或禁用这些路由
 */

use Hyperf\HttpServer\Router\Router;

// 开发测试页面
Router::get('/admin/test/http-test', 'App\Controller\Admin\Test\HttpTestController@index');
