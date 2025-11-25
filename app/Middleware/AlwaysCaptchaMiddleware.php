<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 始终需要验证码的中间件
 * 
 * 功能：
 * - 始终验证验证码（无论登录失败次数）
 * - 继承自 VerifyCaptchaMiddleware，提供更明确的语义
 * - 支持配置验证码字段名
 * - API 请求返回 JSON 错误
 * - 页面请求返回错误信息
 * 
 * 使用场景：
 * - 前端登录系统（需要始终保持验证码）
 * - 注册、找回密码、重置密码等始终需要验证码的场景
 * 
 * 使用示例：
 * ```php
 * // config/routes.php
 * Router::post('/frontend/login', 'App\Controller\Frontend\AuthController@login', [
 *     'middleware' => [
 *         \App\Middleware\AlwaysCaptchaMiddleware::class,
 *     ]
 * ]);
 * ```
 */
class AlwaysCaptchaMiddleware extends VerifyCaptchaMiddleware
{
    /**
     * 构造函数
     * 
     * @param HttpResponse $response HTTP 响应对象
     * @param array $captchaFields 验证码字段名（支持多个字段名，按顺序检查）
     * @param bool $onlyVerifyModifyingMethods 是否只验证 POST/PUT/DELETE/PATCH 请求
     */
    public function __construct(
        HttpResponse $response,
        array $captchaFields = ['captcha', 'captcha_code'],
        bool $onlyVerifyModifyingMethods = true,
    ) {
        parent::__construct($response, $captchaFields, $onlyVerifyModifyingMethods);
    }

    /**
     * 处理请求
     * 
     * 始终验证验证码，不进行任何条件判断
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 直接调用父类的验证逻辑，始终验证验证码
        return parent::process($request, $handler);
    }
}

