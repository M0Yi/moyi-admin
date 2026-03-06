<?php

declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;

/**
 * HomePageDemo 插件路由定义
 */

// ========================================
// API 接口路由
// ========================================

Router::addGroup('/api/homepage_demo', function () {
    // 插件信息接口
    Router::get('', 'Addons\HomePageDemo\Controller\HomePageController@api');
    Router::get('/info', 'Addons\HomePageDemo\Controller\HomePageController@api');
});

// 注意：首页路由会自动通过 replace_homepage 配置注册，无需在此手动定义
