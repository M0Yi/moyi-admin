<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\LoginAttemptService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 登录验证码中间件
 * 
 * 功能：
 * - 根据登录失败次数决定是否需要验证码
 * - 支持免验证码令牌（第一个窗口可以免验证码）
 * - 验证 POST/PUT/DELETE 请求中的验证码
 * - API 请求返回 JSON 错误
 * - 页面请求返回错误信息
 * 
 * 使用场景：
 * - 登录页面
 * 
 * 注意：
 * - 与 ConditionalCaptchaMiddleware 功能相同
 * - 此中间件专门用于登录场景，命名更明确
 * - 如果需要更通用的命名，可以使用 ConditionalCaptchaMiddleware
 */
class LoginCaptchaMiddleware extends VerifyCaptchaMiddleware
{
    #[Inject]
    protected LoginAttemptService $loginAttemptService;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 只验证 POST/PUT/DELETE/PATCH 请求
        $method = $request->getMethod();
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return $handler->handle($request);
        }

        // 检查是否有免验证码令牌
        $parsedBody = $request->getParsedBody() ?? [];
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

        // 需要验证码，调用父类的验证逻辑
        return parent::process($request, $handler);
    }
}

