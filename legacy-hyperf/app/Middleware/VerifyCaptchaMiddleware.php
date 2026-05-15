<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\CaptchaService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 通用验证码验证中间件
 * 
 * 功能：
 * - 验证 POST/PUT/DELETE/PATCH 请求中的验证码
 * - 支持配置验证码字段名
 * - API 请求返回 JSON 错误
 * - 页面请求返回错误信息
 * - 默认模式：始终验证验证码（always 模式）
 * 
 * 使用场景：
 * - 注册、找回密码、重置密码等始终需要验证码的场景
 * - 前端登录系统（需要始终保持验证码）
 * 
 * 注意：
 * - 此中间件默认始终验证验证码
 * - 如果需要根据条件决定是否验证（如登录失败次数），请使用 LoginCaptchaMiddleware
 * - 如果需要更明确的语义，可以使用 AlwaysCaptchaMiddleware（继承自本类）
 * 
 * 使用示例：
 * ```php
 * // 方式1：直接使用（始终验证验证码）
 * Router::post('/register', 'App\Controller\AuthController@register', [
 *     'middleware' => [
 *         \App\Middleware\VerifyCaptchaMiddleware::class,
 *     ]
 * ]);
 * 
 * // 方式2：使用 AlwaysCaptchaMiddleware（语义更明确）
 * Router::post('/frontend/login', 'App\Controller\Frontend\AuthController@login', [
 *     'middleware' => [
 *         \App\Middleware\AlwaysCaptchaMiddleware::class,
 *     ]
 * ]);
 * ```
 */
class VerifyCaptchaMiddleware implements MiddlewareInterface
{
    #[Inject]
    protected CaptchaService $captchaService;

    public function __construct(
        protected HttpResponse $response,
        /**
         * 验证码字段名（支持多个字段名，按顺序检查）
         */
        protected array $captchaFields = ['captcha', 'captcha_code'],
        /**
         * 是否只验证 POST/PUT/DELETE/PATCH 请求
         */
        protected bool $onlyVerifyModifyingMethods = true,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 如果只验证修改类请求，则跳过 GET/HEAD 等请求
        if ($this->onlyVerifyModifyingMethods) {
            $method = $request->getMethod();
            if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
                return $handler->handle($request);
            }
        }

        // 获取验证码
        $parsedBody = $request->getParsedBody() ?? [];
        $captcha = null;

        // 按顺序检查多个字段名
        foreach ($this->captchaFields as $field) {
            if (isset($parsedBody[$field])) {
                $captcha = (string)$parsedBody[$field];
                break;
            }
        }

        // 如果没有提供验证码，返回错误
        if ($captcha === null || $captcha === '') {
            return $this->errorResponse($request, '验证码不能为空');
        }

        // 验证验证码
        if (!$this->captchaService->verify($captcha)) {
            return $this->errorResponse($request, '验证码错误或已过期');
        }

        return $handler->handle($request);
    }

    /**
     * 返回错误响应
     */
    protected function errorResponse(ServerRequestInterface $request, string $message): ResponseInterface
    {
        // 判断是否为 API 请求
        if ($this->isApiRequest($request)) {
            return $this->response->json([
                'code' => 400,
                'msg' => $message,
                'data' => null,
            ])->withStatus(200);
        }

        // 页面请求返回 JSON（可以通过 Session Flash 传递错误信息）
        return $this->response->json([
            'code' => 400,
            'msg' => $message,
            'data' => null,
        ])->withStatus(200);
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

