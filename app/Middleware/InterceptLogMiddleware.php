<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Admin\AdminInterceptLog;
use App\Support\SiteVerificationToken;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coroutine\Coroutine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 拦截日志记录中间件
 *
 * 功能：
 * - 自动记录后台路径访问错误（404页面不存在）
 * - 记录路径输入错误的情况
 * - 记录未授权访问的情况
 * - 异步记录，不影响响应速度
 */
class InterceptLogMiddleware implements MiddlewareInterface
{
    /**
     * 不需要记录拦截日志的路径前缀
     */
    private const EXCLUDED_PATHS = [
        '/favicon.ico',      // 网站图标
        '/robots.txt',       // 爬虫协议
        '/sitemap.xml',      // 网站地图
        '/api/',             // API 接口（由其他中间件处理）
        '/assets/',          // 静态资源
        '/css/',             // CSS 文件
        '/js/',              // JS 文件
        '/images/',          // 图片文件
        '/uploads/',         // 上传文件
    ];

    /**
     * 需要记录拦截的HTTP状态码
     */
    private const INTERCEPT_STATUS_CODES = [404, 403, 401, 405];

    /**
     * 后台入口路径模式（用于识别后台访问）
     */
    private const ADMIN_ENTRY_PATTERN = '/^\/[^\/]+\/.*$/';

    public function __construct(
        private readonly StdoutLoggerInterface $logger,
        private readonly SiteVerificationToken $siteVerificationToken
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 记录开始时间
        $startTime = microtime(true);

        // 处理请求
        $response = $handler->handle($request);

        // 计算执行时长
        $duration = (int) ((microtime(true) - $startTime) * 1000);

        // 检查是否需要记录拦截日志
        if ($this->shouldIntercept($request, $response)) {
            // 异步记录日志（不阻塞响应）
            $this->recordInterceptLogAsync($request, $response, $duration);
        }

        return $response;
    }

    /**
     * 判断是否应该拦截并记录日志
     */
    private function shouldIntercept(ServerRequestInterface $request, ResponseInterface $response): bool
    {
        $path = $request->getUri()->getPath();
        $statusCode = $response->getStatusCode();

        // 排除不需要记录的路径
        if ($this->isExcludedPath($path)) {
            return false;
        }

        // 只记录特定的状态码
        if (!in_array($statusCode, self::INTERCEPT_STATUS_CODES)) {
            return false;
        }

        // 只记录后台路径的访问
        if (!$this->isAdminPath($path)) {
            return false;
        }

        return true;
    }

    /**
     * 检查路径是否需要排除
     */
    private function isExcludedPath(string $path): bool
    {
        foreach (self::EXCLUDED_PATHS as $excludedPath) {
            if (str_contains($path, $excludedPath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查是否为后台路径
     */
    private function isAdminPath(string $path): bool
    {
        // 检查是否匹配后台入口路径模式（/xxx/...）
        if (preg_match(self::ADMIN_ENTRY_PATTERN, $path)) {
            return true;
        }

        // 检查是否为站点特定的后台路径
        $site = Context::get('site');
        if ($site && isset($site->admin_entry_path)) {
            $adminPrefix = '/' . $site->admin_entry_path;
            return str_starts_with($path, $adminPrefix);
        }

        return false;
    }

    /**
     * 异步记录拦截日志
     */
    private function recordInterceptLogAsync(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $duration
    ): void {
        try {
            // 获取站点信息
            $site = Context::get('site');
            $siteId = $site ? $site->id : null;
            $adminEntryPath = $site ? $site->admin_entry_path : null;

            // 获取请求信息
            $method = strtoupper($request->getMethod());
            $path = $request->getUri()->getPath();

            // 获取IP地址
            $ip = $this->getClientIp($request);

            // 获取完整的代理链 IP 列表
            $ipList = $this->getClientIpList($request);

            // 获取User Agent
            $userAgent = $request->getHeaderLine('User-Agent') ?: null;

            // 获取请求参数
            $params = $this->getRequestParams($request);

            // 获取状态码
            $statusCode = $response->getStatusCode();

            // 确定拦截类型和原因
            [$interceptType, $reason] = $this->determineInterceptTypeAndReason($statusCode, $path);

            // 记录日志（使用协程异步执行，不阻塞）
            Coroutine::create(function () use (
                $siteId,
                $adminEntryPath,
                $method,
                $path,
                $ip,
                $ipList,
                $userAgent,
                $params,
                $interceptType,
                $reason,
                $statusCode,
                $duration
            ) {
                try {
                    AdminInterceptLog::create([
                        'site_id' => $siteId,
                        'admin_entry_path' => $adminEntryPath,
                        'method' => $method,
                        'path' => $path,
                        'ip' => $ip,
                        'ip_list' => $ipList,
                        'user_agent' => $userAgent,
                        'params' => $params,
                        'intercept_type' => $interceptType,
                        'reason' => $reason,
                        'status_code' => $statusCode,
                        'duration' => $duration,
                    ]);
                } catch (\Throwable $e) {
                    // 记录日志失败不影响主流程
                    $this->logger->error('拦截日志记录失败', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // 记录日志失败不影响主流程
            $this->logger->error('拦截日志中间件异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 确定拦截类型和原因
     */
    private function determineInterceptTypeAndReason(int $statusCode, string $path): array
    {
        switch ($statusCode) {
            case 404:
                // 检查是否为路径输入错误（包含特殊字符或明显错误的路径）
                if ($this->isInvalidPath($path)) {
                    return [AdminInterceptLog::TYPE_INVALID_PATH, '路径输入错误或包含非法字符'];
                } else {
                    return [AdminInterceptLog::TYPE_404, '页面不存在'];
                }
            case 403:
                return [AdminInterceptLog::TYPE_UNAUTHORIZED, '权限不足，禁止访问'];
            case 401:
                return [AdminInterceptLog::TYPE_UNAUTHORIZED, '未授权访问'];
            case 405:
                return [AdminInterceptLog::TYPE_INVALID_PATH, '请求方法不允许'];
            default:
                return [AdminInterceptLog::TYPE_404, '未知错误'];
        }
    }

    /**
     * 检查是否为无效路径
     */
    private function isInvalidPath(string $path): bool
    {
        // 检查是否包含危险字符
        $dangerousChars = ['<', '>', '"', "'", ';', '\\', '..'];
        foreach ($dangerousChars as $char) {
            if (str_contains($path, $char)) {
                return true;
            }
        }

        // 检查是否包含连续的特殊字符
        if (preg_match('/[^\w\-\/\.]{2,}/', $path)) {
            return true;
        }

        // 检查路径长度是否过长（可能为攻击）
        if (strlen($path) > 200) {
            return true;
        }

        return false;
    }

    /**
     * 获取客户端IP地址
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        // 优先从 X-Forwarded-For 获取
        $xForwardedFor = $request->getHeaderLine('X-Forwarded-For');
        if (!empty($xForwardedFor)) {
            $ips = array_map('trim', explode(',', $xForwardedFor));
            $ip = $ips[0];
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        // 其他代理头
        $otherHeaders = ['X-Real-IP', 'CF-Connecting-IP', 'True-Client-IP'];
        foreach ($otherHeaders as $header) {
            $ip = trim($request->getHeaderLine($header));
            if (!empty($ip) && $this->isValidIp($ip)) {
                return $ip;
            }
        }

        // 从服务器参数获取
        $serverParams = $request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? $serverParams['remote_addr'] ?? null;
        if ($remoteAddr && $this->isValidIp($remoteAddr)) {
            return $remoteAddr;
        }

        return '0.0.0.0';
    }

    /**
     * 验证IP地址是否有效
     */
    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
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

        // 获取请求体参数（限制大小，避免记录大量数据）
        $parsedBody = $request->getParsedBody();
        if (!empty($parsedBody)) {
            if (is_array($parsedBody)) {
                // 限制记录的参数数量和长度
                $params['body'] = $this->limitParams($parsedBody);
            }
        }

        // 过滤敏感信息
        $params = $this->filterSensitiveData($params);

        return $params;
    }

    /**
     * 限制参数大小
     */
    private function limitParams(array $params, int $maxKeys = 10, int $maxValueLength = 100): array
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

        // 敏感字段列表
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

            // 如果是敏感字段，替换为星号
            if (in_array($lowerKey, $sensitiveFields)) {
                $filtered[$key] = '***';
                continue;
            }

            // 递归处理嵌套数组
            if (is_array($value)) {
                $filtered[$key] = $this->filterSensitiveData($value);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
