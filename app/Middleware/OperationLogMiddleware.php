<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Admin\AdminOperationLog;
use App\Model\Admin\AdminUser;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coroutine\Coroutine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 操作日志记录中间件
 *
 * 功能：
 * - 自动记录 POST、PUT、DELETE、PATCH 等写操作
 * - 记录用户信息、请求信息、响应信息
 * - 异步记录，不影响响应速度
 * - 排除不需要记录的路径（如操作日志查看页面本身）
 */
class OperationLogMiddleware implements MiddlewareInterface
{
    /**
     * 不需要记录日志的路径前缀
     */
    private const EXCLUDED_PATHS = [
        '/operation-logs',  // 操作日志查看页面
        '/login',           // 登录页面
        '/logout',          // 登出
        '/captcha',         // 验证码
        '/install',         // 安装页面
    ];

    /**
     * 需要记录日志的请求方法
     */
    private const LOGGED_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];

    public function __construct(
        private readonly StdoutLoggerInterface $logger
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();

        // 只记录写操作
        if (!in_array($method, self::LOGGED_METHODS)) {
            return $handler->handle($request);
        }

        // 排除不需要记录的路径
        if ($this->isExcludedPath($path)) {
            return $handler->handle($request);
        }

        // 获取用户信息
        $user = Context::get('admin_user');
        if (!$user) {
            return $handler->handle($request);
        }

        // 记录开始时间
        $startTime = microtime(true);

        // 处理请求
        $response = $handler->handle($request);

        // 计算执行时长
        $duration = (int) ((microtime(true) - $startTime) * 1000);

        // 异步记录日志（不阻塞响应）
        $this->recordLogAsync($request, $response, $user, $duration);

        return $response;
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
     * 异步记录操作日志
     */
    private function recordLogAsync(
        ServerRequestInterface $request,
        ResponseInterface $response,
        mixed $user,
        int $duration
    ): void {
        try {
            // 提取用户信息
            $userId = is_object($user) ? $user->id : ($user['id'] ?? null);
            $username = is_object($user) ? $user->username : ($user['username'] ?? 'unknown');

            if (!$userId) {
                return;
            }

            // 获取站点ID
            $siteId = Context::get('site_id');

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

            // 获取响应信息
            $statusCode = $response->getStatusCode();
            $responseData = $this->getResponseData($response);

            // 记录日志（使用协程异步执行，不阻塞）
            Coroutine::create(function () use (
                $siteId,
                $userId,
                $username,
                $method,
                $path,
                $ip,
                $ipList,
                $userAgent,
                $params,
                $responseData,
                $statusCode,
                $duration
            ) {
                try {
                    AdminOperationLog::create([
                        'site_id' => $siteId,
                        'user_id' => $userId,
                        'username' => $username,
                        'method' => $method,
                        'path' => $path,
                        'ip' => $ip,
                        'ip_list' => $ipList,
                        'user_agent' => $userAgent,
                        'params' => $params,
                        'response' => $responseData,
                        'status_code' => $statusCode,
                        'duration' => $duration,
                    ]);
                } catch (\Throwable $e) {
                    print_r($e->getMessage());
                    // 记录日志失败不影响主流程，打印 payload 便于调试
                    $this->logger->error('操作日志记录失败', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'payload' => isset($payload) ? print_r($payload, true) : 'payload not set',
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // 记录日志失败不影响主流程
            $this->logger->error('操作日志中间件异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 获取客户端IP地址
     * 
     * 支持反向代理环境，按优先级获取真实客户端IP：
     * 1. X-Forwarded-For（最常用，Nginx/Cloudflare等）
     * 2. X-Real-IP（Nginx常用）
     * 3. CF-Connecting-IP（Cloudflare专用）
     * 4. True-Client-IP（部分CDN使用）
     * 5. REMOTE_ADDR（直接连接）
     * 
     * 注意：X-Forwarded-For 可能包含多个IP（客户端IP,代理1,代理2...），取第一个
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        // 方式1：从 HTTP 头获取（标准方式，推荐）
        // X-Forwarded-For: 可能包含多个IP，格式为 "client_ip, proxy1_ip, proxy2_ip"
        $xForwardedFor = $request->getHeaderLine('X-Forwarded-For');
        if (!empty($xForwardedFor)) {
            $ips = array_map('trim', explode(',', $xForwardedFor));
            $ip = $ips[0];
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        // 方式2：X-Real-IP（Nginx常用）
        $xRealIp = $request->getHeaderLine('X-Real-IP');
        if (!empty($xRealIp)) {
            $ip = trim($xRealIp);
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        // 方式3：CF-Connecting-IP（Cloudflare专用）
        $cfConnectingIp = $request->getHeaderLine('CF-Connecting-IP');
        if (!empty($cfConnectingIp)) {
            $ip = trim($cfConnectingIp);
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        // 方式4：True-Client-IP（部分CDN使用）
        $trueClientIp = $request->getHeaderLine('True-Client-IP');
        if (!empty($trueClientIp)) {
            $ip = trim($trueClientIp);
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        // 方式5：从服务器参数获取（兼容性处理）
        $serverParams = $request->getServerParams();
        
        // 尝试多种可能的键名格式（Swoole/Hyperf 可能使用不同格式）
        $possibleKeys = [
            'HTTP_X_FORWARDED_FOR',
            'http_x_forwarded_for',
            'HTTP_X_REAL_IP',
            'http_x_real_ip',
            'CF_CONNECTING_IP',
            'cf_connecting_ip',
        ];

        foreach ($possibleKeys as $key) {
            if (isset($serverParams[$key]) && !empty($serverParams[$key])) {
                $value = $serverParams[$key];
                // 如果是 X-Forwarded-For，可能包含多个IP
                if (str_contains($key, 'FORWARDED')) {
                    $ips = array_map('trim', explode(',', $value));
                    $ip = $ips[0];
                } else {
                    $ip = trim($value);
                }
                
                if ($this->isValidIp($ip)) {
                    return $ip;
                }
            }
        }

        // 最后从 REMOTE_ADDR 获取（直接连接的IP）
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? $serverParams['remote_addr'] ?? null;
        if ($remoteAddr && $this->isValidIp($remoteAddr)) {
            return $remoteAddr;
        }

        // 如果都获取不到，返回默认值
        return '0.0.0.0';
    }

    /**
     * 验证IP地址是否有效
     * 
     * @param string $ip IP地址
     * @return bool
     */
    private function isValidIp(string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        // 过滤掉内网IP和无效IP（可选，根据需求决定）
        // 如果希望记录所有IP，可以只验证格式
        $ip = trim($ip);
        
        // 验证IPv4格式
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        // 验证IPv6格式
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return true;
        }

        return false;
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

        // 获取请求体参数
        $parsedBody = $request->getParsedBody();
        if (!empty($parsedBody)) {
            // 如果是数组，直接使用；如果是对象，转换为数组
            if (is_array($parsedBody)) {
                $params['body'] = $parsedBody;
            } elseif (is_object($parsedBody)) {
                $params['body'] = (array) $parsedBody;
            }
        }

        // 如果是文件上传，记录文件信息（不记录文件内容）
        $uploadedFiles = $request->getUploadedFiles();
        if (!empty($uploadedFiles)) {
            $fileInfo = [];
            foreach ($uploadedFiles as $key => $file) {
                if (is_array($file)) {
                    foreach ($file as $f) {
                        $fileInfo[$key][] = [
                            'name' => $f->getClientFilename(),
                            'size' => $f->getSize(),
                            'type' => $f->getClientMediaType(),
                        ];
                    }
                } else {
                    $fileInfo[$key] = [
                        'name' => $file->getClientFilename(),
                        'size' => $file->getSize(),
                        'type' => $file->getClientMediaType(),
                    ];
                }
            }
            $params['files'] = $fileInfo;
        }

        // 过滤敏感信息
        $params = $this->filterSensitiveData($params);

        return $params;
    }

    /**
     * 获取响应数据
     */
    private function getResponseData(ResponseInterface $response): ?array
    {
        try {
            $body = $response->getBody();
            
            // 检查响应体大小
            $size = $body->getSize();
            if ($size === null || $size === 0) {
                return null;
            }

            // 限制读取大小，避免内存问题（最大 100KB）
            $maxSize = 100 * 1024;
            if ($size > $maxSize) {
                return [
                    'truncated' => true,
                    'size' => $size,
                    'message' => '响应体过大，已截断',
                ];
            }

            // 读取响应内容
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $content = $body->getContents();
            
            // 重置指针（如果可定位）
            if ($body->isSeekable()) {
                $body->rewind();
            }

            // 尝试解析 JSON
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                // 过滤敏感信息
                return $this->filterSensitiveData($data);
            }

            // 如果不是 JSON，只记录前 1000 个字符
            return [
                'raw' => mb_substr($content, 0, 1000),
            ];
        } catch (\Throwable $e) {
            // 读取失败不影响主流程
            return [
                'error' => '无法读取响应内容',
            ];
        }
    }

    /**
     * 获取客户端 IP 列表（按代理链顺序，最先为真实客户端IP）
     *
     * @param ServerRequestInterface $request
     * @return array
     */
    private function getClientIpList(ServerRequestInterface $request): array
    {
        $ips = [];

        // 优先使用 X-Forwarded-For（可能包含多个 IP）
        $xForwardedFor = $request->getHeaderLine('X-Forwarded-For');
        if (!empty($xForwardedFor)) {
            $parts = array_map('trim', explode(',', $xForwardedFor));
            foreach ($parts as $p) {
                if ($this->isValidIp($p)) {
                    $ips[] = $p;
                }
            }
            if (!empty($ips)) {
                return array_values(array_unique($ips));
            }
        }

        // 其他头信息
        $otherHeaders = [
            'X-Real-IP',
            'CF-Connecting-IP',
            'True-Client-IP',
        ];
        foreach ($otherHeaders as $header) {
            $val = $request->getHeaderLine($header);
            if (!empty($val) && $this->isValidIp($val)) {
                $ips[] = trim($val);
            }
        }

        // 最后尝试从 server params 中读取
        $serverParams = $request->getServerParams();
        $remoteCandidates = [
            $serverParams['REMOTE_ADDR'] ?? null,
            $serverParams['remote_addr'] ?? null,
            $serverParams['HTTP_X_FORWARDED_FOR'] ?? null,
            $serverParams['http_x_forwarded_for'] ?? null,
        ];
        foreach ($remoteCandidates as $candidate) {
            if (empty($candidate)) {
                continue;
            }
            // 如果是逗号分隔，取每一项
            if (str_contains((string)$candidate, ',')) {
                $parts = array_map('trim', explode(',', (string)$candidate));
                foreach ($parts as $p) {
                    if ($this->isValidIp($p)) {
                        $ips[] = $p;
                    }
                }
            } else {
                if ($this->isValidIp((string)$candidate)) {
                    $ips[] = (string)$candidate;
                }
            }
        }

        return array_values(array_unique($ips));
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

