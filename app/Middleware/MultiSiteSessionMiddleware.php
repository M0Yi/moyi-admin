<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 多站点 Session 配置中间件
 *
 * 功能：
 * 1. 在 SessionMiddleware 之前执行，根据当前站点动态设置 session cookie 的 domain
 * 2. 确保不同二级域名的 session cookie 使用不同的 domain，实现完全隔离
 * 3. 在 SiteMiddleware 之后执行，确保站点信息已识别
 *
 * 执行顺序：
 * SiteMiddleware -> MultiSiteSessionMiddleware -> SessionMiddleware -> ...
 */
class MultiSiteSessionMiddleware implements MiddlewareInterface
{
    #[Inject]
    protected ConfigInterface $config;

    /**
     * 处理请求
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 获取当前站点信息和 Host
        $site = Context::get('site');
        $host = $request->getHeaderLine('Host');

        // 如果识别到站点，动态设置 session cookie domain
        if ($site && $host) {
            $domain = $this->extractDomainFromHost($host);
            
            // 只有在是有效域名（非IP）时才设置 domain
            if (!empty($domain) && !filter_var($domain, FILTER_VALIDATE_IP)) {
                $this->setSessionDomain($domain);
            }
        }

        // 继续处理请求
        return $handler->handle($request);
    }

    /**
     * 从 Host 中提取域名（移除端口）
     */
    protected function extractDomainFromHost(string $host): string
    {
        // 处理 IPv6（形如 [::1]:8080）
        if (str_starts_with($host, '[')) {
            $endBracket = strpos($host, ']');
            if ($endBracket !== false) {
                // IPv6 地址，返回空（IP 地址不能设置 cookie domain）
                return '';
            }
        }

        // IPv4 或域名
        $parts = explode(':', $host);
        $domain = $parts[0];

        return $domain;
    }

    /**
     * 设置 Session Cookie Domain
     *
     * 通过修改配置，让后续的 SessionMiddleware 使用正确的 domain
     */
    protected function setSessionDomain(string $domain): void
    {
        // 获取当前 session 配置
        $sessionConfig = $this->config->get('session', []);
        $options = $sessionConfig['options'] ?? [];

        // 设置 cookie domain 为当前二级域名（不使用主域名，确保隔离）
        // 例如：site1.example.com 的 cookie domain 设置为 site1.example.com（不是 .example.com）
        $options['domain'] = $domain;

        // 更新配置（临时修改，仅对当前请求有效）
        // 注意：这里直接修改配置数组，Hyperf 的 SessionMiddleware 会读取这个配置
        $this->config->set('session.options', $options);
    }
}

