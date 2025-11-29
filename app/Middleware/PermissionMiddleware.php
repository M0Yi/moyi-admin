<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\BusinessException;
use App\Model\Admin\AdminPermission;
use App\Model\Admin\AdminUser;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 管理后台权限中间件
 *
 * 职责：
 * - 基于当前请求的「业务路径 + HTTP 方法」匹配权限规则（admin_permissions.path + method）
 * - 将匹配到的权限规则映射到具体的权限标识（slug）
 * - 检查当前登录管理员是否拥有其中任意一个权限（通过角色关联）
 *
 * 设计要点：
 * - 仍然以 slug 作为权限的“业务主键”，path/method 只是辅助找到需要检查的 slug
 * - 超级管理员（is_admin = 1）跳过权限检查
 * - 暂时采用“宽松模式”：如果某个请求没有匹配到任何权限规则，则视为不做权限控制，直接放行
 */
class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly HttpResponse $response,
        private readonly StdoutLoggerInterface $logger
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var AdminUser|null $user */
        $user = Context::get('admin_user');

        // 未登录或无法获取用户，交给上游的认证中间件处理
        if (! $user instanceof AdminUser) {
            return $handler->handle($request);
        }

        // 超级管理员拥有所有权限，直接放行
        if ((int) $user->is_admin === 1) {
            return $handler->handle($request);
        }

        $rawPath = $request->getUri()->getPath();
        $method = strtoupper($request->getMethod());

        // 计算业务路径（去掉 /admin/{adminPath} 前缀）
        $businessPath = $this->normalizeBusinessPath($rawPath);

        $this->logger->debug('Permission check start', [
            'user_id' => $user->id ?? null,
            'username' => $user->username ?? null,
            'method' => $method,
            'raw_path' => $rawPath,
            'business_path' => $businessPath,
        ]);

        // 如果业务路径为空（极端情况），直接放行
        if ($businessPath === '') {
            $this->logger->debug('Permission check skipped: empty business path');
            return $handler->handle($request);
        }

        // 查询所有可能匹配当前 method 的权限规则（角色和权限已解耦站点，全局共享）
        $permissions = AdminPermission::query()
            ->where('status', 1)
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->where(static function ($query) use ($method) {
                $query->where('method', $method)
                    ->orWhere('method', '*');
            })
            ->get(['id', 'slug', 'path', 'method', 'type']);

        if ($permissions->isEmpty()) {
            // 宽松模式：如果没有配置任何匹配当前 method 的权限规则，则认为该请求不做权限控制
            $this->logger->debug('Permission check skipped: no permission configured for method');
            return $handler->handle($request);
        }

        // 根据 path 模式筛选出真正匹配当前业务路径的权限
        $matchedSlugs = [];

        foreach ($permissions as $permission) {
            $pattern = (string) $permission->path;

            if ($pattern === '') {
                continue;
            }

            if ($this->pathMatches($pattern, $businessPath)) {
                $matchedSlugs[] = (string) $permission->slug;
            }
        }

        // 如果没有任何规则匹配当前路径，同样采用宽松模式直接放行
        if ($matchedSlugs === []) {
            $this->logger->debug('Permission check skipped: no permission matched path');
            return $handler->handle($request);
        }

        // 去重，避免重复判断
        $matchedSlugs = array_values(array_unique($matchedSlugs));

        // 检查当前用户是否拥有其中任意一个权限
        foreach ($matchedSlugs as $slug) {
            if ($user->hasPermission($slug)) {
                $this->logger->debug('Permission check passed', [
                    'user_id' => $user->id,
                    'slug' => $slug,
                ]);
                return $handler->handle($request);
            }
        }

        // 无匹配权限：根据请求类型返回 JSON 或抛出业务异常
        $this->logger->warning('Permission denied', [
            'user_id' => $user->id ?? null,
            'username' => $user->username ?? null,
            'method' => $method,
            'path' => $businessPath,
            'matched_slugs' => $matchedSlugs,
        ]);
        if ($this->isApiRequest($request)) {
            return $this->response->json([
                'code' => 403,
                'message' => '无权访问',
            ])->withStatus(403);
        }

        // 页面请求抛业务异常，由全局异常处理器渲染
        throw new BusinessException(403, '无权访问');
    }

    /**
     * 将实际路径转换为“业务路径”，去掉 /admin/{adminPath} 前缀
     *
     * 例如：
     * - /admin/xyz123/system/users       -> /system/users
     * - /admin/xyz123/system/users/1    -> /system/users/1
     */
    private function normalizeBusinessPath(string $rawPath): string
    {
        $trimmed = trim($rawPath, '/');

        if ($trimmed === '') {
            return '';
        }

        $segments = explode('/', $trimmed);

        // 至少形如 admin/{entry}/...
        if (\count($segments) >= 3 && $segments[0] === 'admin') {
            $businessSegments = \array_slice($segments, 2);

            return '/' . implode('/', $businessSegments);
        }

        // 非后台路径（例如 /captcha、/install 等），直接返回原始路径
        return '/' . $trimmed;
    }

    /**
     * 判断请求是否为 API 请求（参考 AdminAuthMiddleware）
     */
    private function isApiRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        // 路径以 /api 开头
        if (str_starts_with($path, '/api/')) {
            return true;
        }

        // 请求头包含 Accept: application/json
        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // 请求头包含 X-Requested-With: XMLHttpRequest
        $xRequestedWith = $request->getHeaderLine('X-Requested-With');
        if ($xRequestedWith === 'XMLHttpRequest') {
            return true;
        }

        return false;
    }

    /**
     * 使用简单的通配符规则（*）判断路径是否匹配
     *
     * 规则：
     * - * 匹配任意长度的任意字符
     * - 其余字符按字面匹配
     * - 模式默认是完全匹配（自动加上 ^ 和 $）
     *
     * 例如：
     * - /system/users*       可以匹配 /system/users、/system/users/1/edit
     * - /system/users/*\/edit 可以匹配 /system/users/1/edit、/system/users/999/edit
     */
    private function pathMatches(string $pattern, string $path): bool
    {
        // 先对模式做正则转义，再把 \* 替换为 .*
        $quoted = preg_quote($pattern, '#');
        $regex = '#^' . str_replace('\*', '.*', $quoted) . '$#';

        return (bool) preg_match($regex, $path);
    }
}





