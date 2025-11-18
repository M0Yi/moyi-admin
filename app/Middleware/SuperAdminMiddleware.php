<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\View\RenderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 超级管理员权限验证中间件
 *
 * 功能：
 * - 验证当前登录用户是否是超级管理员（ID为1）
 * - API 请求返回 403 JSON
 * - 页面请求返回 403 错误页面
 */
class SuperAdminMiddleware implements MiddlewareInterface
{
    #[Inject]
    protected HttpResponse $response;

    #[Inject]
    protected RenderInterface $render;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 检查是否是超级管理员
        if (!is_super_admin()) {
            // 判断是否为 API 请求
            if ($this->isApiRequest($request)) {
                return $this->response->json([
                    'code' => 403,
                    'message' => '仅超级管理员可访问',
                ])->withStatus(403);
            }

            // 页面请求返回 403 错误页面
            return $this->response->raw(
                $this->render->render('errors.403')
            )->withStatus(403);
        }

        return $handler->handle($request);
    }

    /**
     * 判断是否为 API 请求
     */
    protected function isApiRequest(ServerRequestInterface $request): bool
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
}

