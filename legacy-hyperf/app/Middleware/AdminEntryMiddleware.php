<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Admin\AdminSite;
use App\Service\Admin\InterceptLogService;
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

    #[Inject]
    protected InterceptLogService $interceptLogService;

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
            // 未找到路径参数
            return $this->denyAccess();
        }

        // 验证 adminPath 是否与当前站点的 admin_entry_path 匹配
        $site = site();
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
        // 返回 404 而不是 403，避免暴露后台存在
        if($this->request->getMethod() == 'GET'){
            return $this->render->render('errors.admin_illegal_access');
        }

        return $this->response->json([
            'code' => 403,
            'message' => '非法访问',
            'data' => null
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

        // 获取完整的IP列表
        $ipList = $this->getClientIpList($request);

        // 记录到拦截日志表
        try {
            // 这里不使用静态方法，而是使用注入的服务实例
            // 构建拦截日志数据
            $logData = [
                'site_id' => null, // 非法访问时可能没有站点信息
                'admin_entry_path' => $adminEntryPath,
                'method' => strtoupper($request->getMethod()),
                'path' => $request->getUri()->getPath(),
                'ip' => $ip,
                'ip_list' => $ipList,
                'user_agent' => $request->getHeaderLine('User-Agent') ?: null,
                'params' => $this->getRequestParams($request),
                'intercept_type' => 'invalid_path', // 使用非法路径类型
                'reason' => '非法后台入口访问尝试',
                'status_code' => 404,
                'duration' => null,
            ];

            // 异步记录拦截日志
            \Hyperf\Coroutine\Coroutine::create(function () use ($logData) {
                try {
                    \App\Model\Admin\AdminInterceptLog::create($logData);
                } catch (\Throwable $e) {
                    logger()->error('记录拦截日志失败', [
                        'error' => $e->getMessage(),
                        'data' => $logData,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            logger()->error('准备拦截日志数据失败', [
                'error' => $e->getMessage(),
            ]);
        }

        // 记录到 Hyperf 日志
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

    /**
     * 获取客户端IP列表
     */
    private function getClientIpList(ServerRequestInterface $request): array
    {
        $ips = [];

        // X-Forwarded-For
        $xForwardedFor = $request->getHeaderLine('X-Forwarded-For');
        if (!empty($xForwardedFor)) {
            $parts = array_map('trim', explode(',', $xForwardedFor));
            foreach ($parts as $ip) {
                if ($this->isValidIp($ip)) {
                    $ips[] = $ip;
                }
            }
        }

        // 其他代理头
        $otherHeaders = ['X-Real-IP', 'CF-Connecting-IP', 'True-Client-IP'];
        foreach ($otherHeaders as $header) {
            $ip = trim($request->getHeaderLine($header));
            if (!empty($ip) && $this->isValidIp($ip)) {
                $ips[] = $ip;
            }
        }

        // REMOTE_ADDR
        $serverParams = $request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? $serverParams['remote_addr'] ?? null;
        if ($remoteAddr && $this->isValidIp($remoteAddr)) {
            $ips[] = $remoteAddr;
        }

        return array_values(array_unique($ips));
    }

    /**
     * 验证IP地址是否有效
     */
    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * 获取请求参数
     */
    private function getRequestParams(ServerRequestInterface $request): array
    {
        $params = [];

        // 获取查询参数
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams)) {
            $params['query'] = $queryParams;
        }

        // 获取请求体参数（限制大小）
        $parsedBody = $request->getParsedBody();
        if (!empty($parsedBody) && is_array($parsedBody)) {
            // 限制参数数量和长度
            $params['body'] = $this->limitParams($parsedBody);
        }

        // 过滤敏感信息
        return $this->filterSensitiveData($params);
    }

    /**
     * 限制参数大小
     */
    private function limitParams(array $params, int $maxKeys = 5, int $maxValueLength = 50): array
    {
        $limited = [];
        $count = 0;

        foreach ($params as $key => $value) {
            if ($count >= $maxKeys) {
                $limited['...'] = '参数过多，已截断';
                break;
            }

            if (is_array($value)) {
                $limited[$key] = $this->limitParams($value, $maxKeys, $maxValueLength);
            } elseif (is_string($value)) {
                $limited[$key] = mb_substr($value, 0, $maxValueLength);
                if (mb_strlen($value) > $maxValueLength) {
                    $limited[$key] .= '...';
                }
            } else {
                $limited[$key] = $value;
            }

            $count++;
        }

        return $limited;
    }

    /**
     * 过滤敏感信息
     */
    private function filterSensitiveData(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitiveFields = [
            'password',
            'password_confirmation',
            'old_password',
            'new_password',
            'token',
            'api_key',
            'secret',
            'access_token',
            'refresh_token',
        ];

        $filtered = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (in_array($lowerKey, $sensitiveFields)) {
                $filtered[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $filtered[$key] = $this->filterSensitiveData($value);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}

