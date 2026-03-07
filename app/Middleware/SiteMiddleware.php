<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Admin\AdminSite;
use App\Service\Admin\SiteService;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function Hyperf\Support\env;

/**
 * 站点识别中间件
 *
 * 根据访问域名自动识别当前站点，并将站点信息存入上下文
 *
 * 功能：
 * 1. 根据 Host 域名匹配站点（优先匹配 Redis 缓存，通过 SiteService）
 * 2. 检查站点状态（启用/禁用）
 * 3. 将站点信息存入 Context 上下文供全局使用
 * 4. 未匹配时使用默认站点（ID=1）
 * 5. 如果没有默认站点，重定向到安装页面
 * 6. 安装页面路径（/install）跳过站点检查
 * 7. 使用 Redis 缓存域名匹配结果，提升性能（通过 SiteService）
 */
class SiteMiddleware implements MiddlewareInterface
{
    #[Inject]
    protected HttpResponse $response;

    #[Inject]
    protected SiteService $siteService;

    /**
     * 处理请求
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 获取请求路径
        $path = $request->getUri()->getPath();

        logger()->debug('[SiteMiddleware] 请求路径: ' . $path);

        // 如果是安装页面相关路径，跳过站点检查
        if ($this->isInstallPath($path)) {
            logger()->debug('[SiteMiddleware] 跳过站点检查（安装页面）');
            return $handler->handle($request);
        }

        // 获取请求的 Host
        $host = $request->getHeaderLine('Host');

        logger()->debug('[SiteMiddleware] Host: ' . $host);

        if (empty($host)) {
            logger()->warning('[SiteMiddleware] Host 为空');
            return $this->response->raw('Invalid Host')->withStatus(400);
        }

        // 移除端口号（如果有）
        $domain = $this->extractDomain($host);
        logger()->debug('[SiteMiddleware] 提取域名: ' . $domain);

        // 检查是否启用多站点功能
        $enableSite = env('ENABLE_SITE', false);
        
        if (!$enableSite) {
            // 多站点功能关闭，使用默认站点（ID=1）
            logger()->debug('[SiteMiddleware] 多站点功能关闭，使用默认站点');
            $site = $this->siteService->getDefaultSite();
            
            if (!$site) {
                logger()->error('[SiteMiddleware] 未找到默认站点');
                return $this->response->raw('Default site not found')->withStatus(500);
            }
        } else {
            // 多站点功能开启，根据域名查找站点
            $site = $this->siteService->getSiteByDomain($domain);

            if (! $site) {
                logger()->info('[SiteMiddleware] 未找到匹配的站点, 域名: ' . $domain);
                // 未匹配任何站点，继续后续流程（site() 将返回 null）
                return $handler->handle($request);
            }

            logger()->debug('[SiteMiddleware] 找到站点: ' . $site->name . ', ID: ' . $site->id);

            // 检查站点状态
            if (! $site->isEnabled()) {
                logger()->warning('[SiteMiddleware] 站点未启用: ' . $site->name . ', ID: ' . $site->id);
                // 站点未启用
                return $this->response->raw('Site is inactive')
                    ->withStatus(503);
            }
        }

        // 将站点信息设置到上下文中（全局访问）
        Context::set('site', $site);
        Context::set('site_id', $site->id);

        logger()->debug('[SiteMiddleware] 站点信息已设置到上下文, ID: ' . $site->id);

        // 将站点信息添加到请求属性中，方便在 Controller 中获取
        $request = $request->withAttribute('site', $site);

        return $handler->handle($request);
    }

    /**
     * 从 Host 中提取域名，非默认端口需要保留
     */
    private function extractDomain(string $host): string
    {
        // 处理 IPv6（形如 [::1]:8080）
        if (str_starts_with($host, '[')) {
            $endBracket = strpos($host, ']');
            if ($endBracket !== false) {
                $ip = substr($host, 0, $endBracket + 1);
                $port = substr($host, $endBracket + 1);
                if ($port !== '' && str_starts_with($port, ':')) {
                    $port = substr($port, 1);
                }
                return $this->formatHostWithPort($ip, $port);
            }
        }

        // IPv4 或域名
        $parts = explode(':', $host);
        $domain = $parts[0];
        $port = $parts[1] ?? null;

        return $this->formatHostWithPort($domain, $port);
    }

    /**
     * 根据端口拼接 Host，保留非默认端口
     */
    private function formatHostWithPort(string $domain, ?string $port): string
    {
        if ($port === null || $port === '') {
            return $domain;
        }

        // 默认 HTTP/HTTPS 端口不需要写在域名上
        if ($port === '80' || $port === '443') {
            return $domain;
        }

        return $domain . ':' . $port;
    }

    /**
     * 检查是否是安装页面路径
     */
    private function isInstallPath(string $path): bool
    {
        // 安装页面相关的路径不需要站点检查
        return str_starts_with($path, '/install');
    }
}

