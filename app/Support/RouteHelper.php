<?php

declare(strict_types=1);

namespace App\Support;

use Hyperf\HttpServer\Router\Router;

/**
 * 路由工具类：提供路由存在性检查和冲突解决功能
 *
 * 使用方法：
 * - RouteHelper::addRouteIfNotExists(['GET'], '/test', 'Controller@action')
 * - RouteHelper::addRouteWithPriority(['GET'], '/home', ['Plugin@home', 'Default@home'])
 */
class RouteHelper
{
    /**
     * 安全添加路由：如果路由已存在则跳过
     *
     * @param array|string $methods HTTP 方法
     * @param string $route 路由路径
     * @param mixed $handler 路由处理器
     * @param array $options 路由选项
     * @return bool 是否成功添加路由
     */
    public static function addRouteIfNotExists(array|string $methods, string $route, mixed $handler, array $options = []): bool
    {
        try {
            Router::addRoute($methods, $route, $handler, $options);
            return true;
        } catch (\FastRoute\BadRouteException $e) {
            if (str_contains($e->getMessage(), 'Cannot register two routes matching')) {
                // 路由已存在，跳过
                return false;
            }
            // 其他类型的异常，重新抛出
            throw $e;
        }
    }

    /**
     * 按优先级添加路由：第一个成功添加的路由生效
     *
     * @param array|string $methods HTTP 方法
     * @param string $route 路由路径
     * @param array $handlers 路由处理器数组（按优先级排序）
     * @param array $options 路由选项
     * @return mixed|null 成功添加的处理器，null表示所有处理器都已存在
     */
    public static function addRouteWithPriority(array|string $methods, string $route, array $handlers, array $options = []): mixed
    {
        foreach ($handlers as $handler) {
            if (self::addRouteIfNotExists($methods, $route, $handler, $options)) {
                return $handler;
            }
        }
        return null;
    }

    /**
     * 批量添加路由：跳过已存在的路由
     *
     * @param array $routes 路由配置数组
     * @return array 添加结果 ['added' => [], 'skipped' => []]
     */
    public static function addRoutesBatch(array $routes): array
    {
        $result = ['added' => [], 'skipped' => []];

        foreach ($routes as $routeConfig) {
            $methods = $routeConfig['methods'] ?? ['GET'];
            $route = $routeConfig['route'] ?? '';
            $handler = $routeConfig['handler'] ?? null;
            $options = $routeConfig['options'] ?? [];

            if (!$route || !$handler) {
                continue;
            }

            if (self::addRouteIfNotExists($methods, $route, $handler, $options)) {
                $result['added'][] = $route;
            } else {
                $result['skipped'][] = $route;
            }
        }

        return $result;
    }

    /**
     * 检查路由是否已注册（通过尝试添加临时路由）
     *
     * 注意：此方法会临时添加一个测试路由，不推荐在生产环境中频繁使用
     *
     * @param array|string $methods HTTP 方法
     * @param string $route 路由路径
     * @return bool
     */
    public static function hasRoute(array|string $methods, string $route): bool
    {
        $testRoute = $route . '_route_check_' . uniqid();

        try {
            Router::addRoute($methods, $testRoute, function() { return 'test'; });
            // 如果能添加成功，说明原路由不存在
            return false;
        } catch (\FastRoute\BadRouteException $e) {
            // 如果抛出异常，检查是否是因为原路由已存在
            return str_contains($e->getMessage(), 'Cannot register two routes matching') &&
                   !str_contains($e->getMessage(), $testRoute);
        }
    }
}