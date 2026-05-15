<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\CaptchaService;
use App\Service\LoginAttemptService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 验证码中间件
 * 
 * 功能：
 * - 根据登录失败次数决定是否需要验证码
 * - 验证 POST/PUT/DELETE 请求中的验证码
 * - API 请求返回 JSON 错误
 * - 页面请求返回错误信息（通过 Session Flash）
 */
class CaptchaMiddleware implements MiddlewareInterface
{
    #[Inject]
    protected CaptchaService $captchaService;

    #[Inject]
    protected LoginAttemptService $loginAttemptService;

    public function __construct(
        protected HttpResponse $response
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 只验证 POST/PUT/DELETE/PATCH 请求
        $method = $request->getMethod();
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return $handler->handle($request);
        }

        // 检查是否有免验证码令牌
        $parsedBody = $request->getParsedBody();
        $freeToken = $parsedBody['free_token'] ?? null;
        
        // 验证免验证码令牌
        if ($freeToken && $this->loginAttemptService->verifyFreeToken($freeToken)) {
            // 令牌有效，免验证码，直接通过
            return $handler->handle($request);
        }

        // 检查是否需要验证码（根据登录失败次数）
        if (!$this->loginAttemptService->requiresCaptcha()) {
            // 不需要验证码，直接通过
            return $handler->handle($request);
        }

        // 需要验证码，进行验证
        $captcha = $parsedBody['captcha'] ?? $parsedBody['captcha_code'] ?? '';

        // 验证验证码
        if (!$this->captchaService->verify((string)$captcha)) {
            // 判断是否为 API 请求
            if ($this->isApiRequest($request)) {
                return $this->response->json([
                    'code' => 400,
                    'message' => '验证码错误或已过期',
                ])->withStatus(400);
            }

            // 页面请求返回错误（可以通过 Session Flash 传递错误信息）
            // 这里简单返回 JSON，实际可以根据需要调整
            return $this->response->json([
                'code' => 400,
                'message' => '验证码错误或已过期',
            ])->withStatus(400);
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

