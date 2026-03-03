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
use App\Support\RouteHelper;
use App\Support\RouteLoader;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Router\Router;

use function Hyperf\Support\make;

// 引入管理员路由
require_once __DIR__ . '/routes/admin.php';

// 站点初始化安装
Router::get('/install', 'App\Controller\Admin\InstallController@index');
Router::post('/install', 'App\Controller\Admin\InstallController@install');
Router::get('/install/check-environment', 'App\Controller\Admin\InstallController@checkEnvironment');

Router::addRoute(['GET', 'POST', 'HEAD'], '/test', 'App\Controller\IndexController@test');

// ========================================
// API测试路由（用于测试数据库连接等）
// ========================================
Router::get('/api/test/pgsql/connection', 'App\Controller\ApiTestController@testPgConnection');
Router::get('/api/test/pgsql/query', 'App\Controller\ApiTestController@testPgQuery');
Router::get('/api/test/connections', 'App\Controller\ApiTestController@testAllConnections');
Router::get('/api/test/db-status', 'App\Controller\ApiTestController@getDbStatus');

// ========================================
// 验证码路由（通用接口，无需登录）
// ========================================
Router::get('/captcha', 'App\Controller\CaptchaController@getCaptcha');

// 管理员路由已移至 config/routes/admin.php

// Favicon 图标路由：根据站点配置重定向到自定义 favicon
Router::get('/favicon.ico', 'App\Controller\PublicController@favicon');

// 动态加载自定义路由文件
$routeLoader = new RouteLoader();
$routeLoader->loadRoutes();

// 首页路由定义：按优先级添加，避免路由冲突
// 优先级：插件路由 > 默认首页路由
$homeRouteHandlers = [
    'App\Controller\IndexController@index',                            // 默认首页
];

// 按优先级添加首页路由，第一个成功添加的生效
RouteHelper::addRouteWithPriority(['GET', 'POST', 'HEAD'], '/', $homeRouteHandlers);
