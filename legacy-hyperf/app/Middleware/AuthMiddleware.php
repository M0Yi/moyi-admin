<?php

declare(strict_types=1);

namespace App\Middleware;

use HyperfExtension\Auth\Contracts\AuthManagerInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;

class AuthMiddleware implements MiddlewareInterface
{
    protected AuthManagerInterface $auth;
    protected HttpResponse $response;

    public function __construct(ContainerInterface $container)
    {
        $this->auth = $container->get(AuthManagerInterface::class);
        $this->response = $container->get(HttpResponse::class);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $guard = $request->getAttribute('auth_guard', 'web');

        if (!$this->auth->guard($guard)->check()) {
            // 用户未登录，重定向到登录页面
            return $this->response->redirect('/login');
        }

        // 用户已登录，继续处理请求
        return $handler->handle($request);
    }
}
