<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Admin\AdminSite;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\View\RenderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 后台入口验证中间件
 *
 * 用途：验证访问的后台路径是否为站点配置的合法入口
 *
 * 使用方式：
 * 1. 在路由中使用：
 *    Router::addGroup('/admin-xxx', function () { ... }, ['middleware' => [AdminEntryMiddleware::class]]);
 *
 * 2. 在控制器中使用注解（如果支持）：
 *    #[Middleware(AdminEntryMiddleware::class)]
 *    class AdminController {}
 *
 * 安全特性：
 * - 验证访问路径是否匹配站点配置的后台入口
 * - 记录非法访问日志
 * - 返回 404 而不是 403，避免暴露后台存在
 */
class AdminEntryMiddleware implements MiddlewareInterface
{
    #[Inject]
    protected RenderInterface $render;
    public function __construct(
        protected RequestInterface $request,
        protected HttpResponse $response
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 从路由参数中获取后台入口路径标识
        // 路由规则：/admin/{adminPath}
        // 例如：/admin/xyz123/dashboard -> $adminPath = "xyz123"
        $adminPath = $this->request->route('adminPath');

        if (!$adminPath) {
            print_r(['未找到路径参数']);
            // 未找到路径参数
            return $this->denyAccess();
        }

        // 验证 adminPath 是否与当前站点的 admin_entry_path 匹配
        $site = site();
//        print_r(['$site'=>$site]);
        if (!$site || $site->admin_entry_path !== $adminPath) {
            // 记录非法访问
            $this->logIllegalAccess($request, $adminPath);
            // 非法访问路径，返回 404
            return $this->denyAccess();
        }

        // 验证通过，将完整的后台入口路径存入上下文（带 /admin 前缀）
        Context::set('admin_entry_path', '/admin/' . $adminPath);

        return $handler->handle($request);
    }


    /**
     * 根据域名和后台入口路径查找站点
     *
     * @param string $host 域名
     * @param string $adminEntryPath 后台入口路径
     * @return AdminSite|null
     */
    protected function findSiteByHostAndAdminPath(string $host, string $adminEntryPath): ?AdminSite
    {
        // 移除端口号
        $domain = explode(':', $host)[0];

        return AdminSite::query()
            ->where('domain', $domain)
            ->where('admin_entry_path', $adminEntryPath)
            ->where('status', AdminSite::STATUS_ENABLED)
            ->first();
    }

    /**
     * 拒绝访问
     *
     * @return ResponseInterface
     */
    protected function denyAccess(): ResponseInterface
    {
//        print_r(['Error denyAccess']);
        // 返回 404 而不是 403，避免暴露后台存在
        if($this->request->getMethod() == 'GET'){
            return $this->render->render('errors.404', [
                'requestPath' => $this->request->getUri()->getPath(),
                'requestMethod' => $this->request->getMethod(),
                'requestUri' => $this->request->getUri(),
            ]);
        }

        return $this->response->json([
            'msg' => '404 Not Found',
            'code' => 404,
            'data' => []
        ]);
    }


    /**
     * 记录非法访问日志
     *
     * @param ServerRequestInterface $request
     * @param string $adminEntryPath
     */
    protected function logIllegalAccess(ServerRequestInterface $request, string $adminEntryPath): void
    {
        $serverParams = $request->getServerParams();
        $ip = $serverParams['remote_addr'] ?? 'unknown';

        // 获取客户端真实 IP（如果有反向代理）
        if (isset($serverParams['http_x_forwarded_for'])) {
            $ips = explode(',', $serverParams['http_x_forwarded_for']);
            $ip = trim($ips[0]);
        }

        // 记录到日志
        logger()->warning('非法后台访问尝试', [
            'ip' => $ip,
            'host' => $request->getUri()->getHost(),
            'path' => $request->getUri()->getPath(),
            'admin_entry_path' => $adminEntryPath,
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'referer' => $request->getHeaderLine('Referer'),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        // TODO: 可以在这里添加更多安全措施
        // 1. IP 黑名单
        // 2. 频率限制
        // 3. 发送警报通知
    }
}

