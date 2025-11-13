<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Admin\AdminSite;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 站点识别中间件
 *
 * 根据访问域名自动识别当前站点，并将站点信息存入上下文
 *
 * 功能：
 * 1. 根据 Host 域名匹配站点
 * 2. 检查站点状态（启用/禁用）
 * 3. 将站点信息存入 Context 上下文供全局使用
 * 4. 未匹配时使用默认站点（ID=1）
 * 5. 如果没有默认站点，重定向到安装页面
 * 6. 安装页面路径（/install）跳过站点检查
 */
class SiteMiddleware implements MiddlewareInterface
{
    #[Inject]
    protected HttpResponse $response;

    /**
     * 处理请求
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 获取请求路径
        $path = $request->getUri()->getPath();

        // 如果是安装页面相关路径，跳过站点检查
        if ($this->isInstallPath($path)) {
            return $handler->handle($request);
        }

        // 获取请求的 Host
        $host = $request->getHeaderLine('Host');

        if (empty($host)) {
            return $this->response->raw('Invalid Host')->withStatus(400);
        }

        // 移除端口号（如果有）
        $domain = $this->extractDomain($host);

        // 根据域名查找站点
        $site = $this->findSiteByDomain($domain);

        if (! $site) {
            // 域名未匹配，使用默认站点（ID=1）
            $site = $this->getDefaultSite();

            if (!$site) {
                // 默认站点也不存在，重定向到安装页面
                return $this->response->redirect('/install');
            }
        }

        // 检查站点状态
        if (! $site->isEnabled()) {
            // 站点未启用
            return $this->response->raw('Site is inactive')
                ->withStatus(503);
        }

        // 将站点信息设置到上下文中（全局访问）
        Context::set('site', $site);
        Context::set('site_id', $site->id);

        // 将站点信息添加到请求属性中，方便在 Controller 中获取
        $request = $request->withAttribute('site', $site);

        return $handler->handle($request);
    }

    /**
     * 从 Host 中提取域名（移除端口号）
     */
    private function extractDomain(string $host): string
    {
        // 如果包含端口号，移除它
        if (str_contains($host, ':')) {
            return explode(':', $host)[0];
        }

        return $host;
    }

    /**
     * 根据域名查找站点
     */
    private function findSiteByDomain(string $domain): ?AdminSite
    {
        try {
            return AdminSite::query()
                ->byDomain($domain)
                ->active()
                ->first();
        } catch (\Throwable $e) {
            // 记录错误日志
            \Hyperf\Support\make(\Psr\Log\LoggerInterface::class)->error(
                'Site middleware: Failed to find site by domain',
                [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                ]
            );

            return null;
        }
    }

    /**
     * 获取默认站点（ID=1）
     */
    private function getDefaultSite(): ?AdminSite
    {
        try {
            return AdminSite::query()
                ->where('id', 1)
                ->first();
        } catch (\Throwable $e) {
            // 记录错误日志
            \Hyperf\Support\make(\Psr\Log\LoggerInterface::class)->error(
                'Site middleware: Failed to get default site',
                [
                    'error' => $e->getMessage(),
                ]
            );

            return null;
        }
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

