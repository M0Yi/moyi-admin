<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\LoginAttemptService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 条件验证码中间件（基于登录失败逻辑）
 * 
 * 功能：
 * - 根据登录失败次数决定是否需要验证码
 * - 支持免验证码令牌（第一个窗口可以免验证码，后续窗口需要验证码）
 * - 登录失败后自动要求验证码
 * - 验证 POST/PUT/DELETE/PATCH 请求中的验证码
 * - API 请求返回 JSON 错误
 * - 页面请求返回错误信息
 * 
 * 工作原理：
 * 1. 第一个窗口：可以获取免验证码令牌，无需输入验证码即可提交
 * 2. 后续窗口：无法获取令牌，需要输入验证码
 * 3. 登录失败后：清除令牌，后续请求必须输入验证码
 * 4. 基于 IP 地址记录失败次数，防止暴力破解
 * 
 * 使用场景：
 * - 登录页面（第一个窗口免验证码，后续窗口需要验证码）
 * - 需要基于失败次数决定是否需要验证码的场景
 * 
 * 使用示例：
 * ```php
 * // config/routes.php
 * Router::post('/login', 'App\Controller\AuthController@login', [
 *     'middleware' => [
 *         \App\Middleware\ConditionalCaptchaMiddleware::class,
 *     ]
 * ]);
 * ```
 * 
 * 前端配合使用：
 * ```blade
 * @php
 *     // 动态构建检查验证码的 URL
 *     $currentPath = $request->getUri()->getPath();
 *     $checkUrl = preg_replace('/\/login$/', '/login/check-captcha', $currentPath);
 * @endphp
 * 
 * @include('components.captcha', [
 *     'name' => 'captcha',
 *     'id' => 'captcha',
 *     'label' => '验证码',
 *     'required' => false,
 *     'captchaUrl' => '/captcha',
 *     'checkUrl' => $checkUrl,
 *     'showFreeToken' => true,
 * ])
 * ```
 * 
 * 注意：
 * - 此中间件依赖 LoginAttemptService，基于 IP 地址记录失败次数
 * - 免验证码令牌基于 IP 地址，同一 IP 的第一个窗口可以获取令牌
 * - 登录失败后会自动清除令牌，后续请求必须输入验证码
 * - 与 LoginCaptchaMiddleware 功能相同，但命名更通用
 */
class ConditionalCaptchaMiddleware extends VerifyCaptchaMiddleware
{
    #[Inject]
    protected LoginAttemptService $loginAttemptService;

    /**
     * 处理请求
     * 
     * 逻辑流程：
     * 1. 检查是否有免验证码令牌，如果有且有效，直接通过
     * 2. 检查是否需要验证码（根据登录失败次数）
     * 3. 如果不需要验证码，直接通过
     * 4. 如果需要验证码，调用父类验证逻辑
     */
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

