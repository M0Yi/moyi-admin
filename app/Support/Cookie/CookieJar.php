<?php

declare(strict_types=1);

namespace HyperfExtension\Cookie;

use HyperfExtension\Cookie\Contract\CookieJarInterface;
use Hyperf\HttpMessage\Cookie\Cookie;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

/**
 * Cookie Jar Implementation - 基于 Hyperf 的 Cookie 实现
 */
class CookieJar implements CookieJarInterface
{
    protected ContainerInterface $container;
    protected array $queuedCookies = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * 设置一个 cookie
     */
    public function set(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = false
    ): void {
        $cookie = new Cookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
        $this->queuedCookies[$name] = $cookie;

        // 尝试直接设置到响应中
        if ($this->container->has(ResponseInterface::class)) {
            try {
                $response = $this->container->get(ResponseInterface::class);
                $response->withCookie($cookie);
            } catch (\Throwable $e) {
                // 静默处理，将在队列中保存
            }
        }
    }

    /**
     * 获取一个 cookie
     */
    public function get(string $name, $default = null)
    {
        // 先检查队列中的 cookie
        if (isset($this->queuedCookies[$name])) {
            return $this->queuedCookies[$name]->getValue();
        }

        // 从请求中获取
        if ($this->container->has(RequestInterface::class)) {
            try {
                $request = $this->container->get(RequestInterface::class);
                return $request->getCookieParams()[$name] ?? $default;
            } catch (\Throwable $e) {
                // 静默处理
            }
        }

        return $default;
    }

    /**
     * 检查 cookie 是否存在
     */
    public function has(string $name): bool
    {
        if (isset($this->queuedCookies[$name])) {
            return true;
        }

        if ($this->container->has(RequestInterface::class)) {
            try {
                $request = $this->container->get(RequestInterface::class);
                return isset($request->getCookieParams()[$name]);
            } catch (\Throwable $e) {
                // 静默处理
            }
        }

        return false;
    }

    /**
     * 删除一个 cookie
     */
    public function forget(string $name, string $path = '/', string $domain = ''): void
    {
        // 设置过期时间为过去的时间来删除 cookie
        $this->set($name, '', time() - 3600, $path, $domain);
    }

    /**
     * 获取所有 cookies
     */
    public function all(): array
    {

        // 添加队列中的 cookies
        $cookies = array_map(function ($cookie) {
            return $cookie->getValue();
        }, $this->queuedCookies);

        // 添加请求中的 cookies
        if ($this->container->has(RequestInterface::class)) {
            try {
                $request = $this->container->get(RequestInterface::class);
                $cookies = array_merge($request->getCookieParams(), $cookies);
            } catch (\Throwable $e) {
                // 静默处理
            }
        }

        return $cookies;
    }

    /**
     * 获取排队的 cookies（用于发送到响应）
     */
    public function getQueuedCookies(): array
    {
        return $this->queuedCookies;
    }

    /**
     * 清空队列
     */
    public function flushQueuedCookies(): void
    {
        $this->queuedCookies = [];
    }
}
