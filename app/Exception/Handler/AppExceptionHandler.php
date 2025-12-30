<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Exception\Handler;

use App\Exception\BusinessException;
use App\Exception\ValidationException;
use App\Service\Admin\InterceptLogService;
use App\Service\Admin\ErrorStatisticService;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Database\Exception\QueryException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Exception\MethodNotAllowedHttpException;
use Hyperf\HttpMessage\Exception\NotFoundHttpException;
use Hyperf\HttpMessage\Exception\ServerErrorHttpException;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\View\RenderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    public function __construct(
        protected StdoutLoggerInterface $logger,
        protected RenderInterface $render,
        protected ErrorStatisticService $errorStatisticService,
        protected InterceptLogService $interceptLogService
    ) {
    }

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {

        $request = Context::get(ServerRequestInterface::class);
        $isApiRequest = $this->isApiRequest($request);

        if ($this->shouldRecordStatistic($throwable)) {
            $this->errorStatisticService->record($throwable, $request);
        }

        if ($throwable instanceof BusinessException) {
            $payload = [
                'code' => $throwable->getCode() > 0 ? $throwable->getCode() : 500,
                'msg' => $throwable->getMessage(),
                'data' => null,
            ];

            return $this->jsonResponse($response, $payload, 200);
        }

        if ($throwable instanceof ValidationException) {
            $payload = [
                'code' => 422,
                'msg' => $throwable->getMessage(),
                'data' => null,
                'errors' => $throwable->getErrors(),
            ];

            return $this->jsonResponse($response, $payload, 200);
        }

        if ($throwable instanceof QueryException) {
            $this->logger->error(sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile()));
            $message = str_contains($throwable->getMessage(), '1142')
                ? '数据库权限不足，请联系管理员'
                : '系统繁忙，请稍后再试';

            $payload = [
                'code' => 500,
                'msg' => $message,
                'data' => null,
            ];

            return $this->jsonResponse($response, $payload, 500);
        }

        if (! $isApiRequest) {
            if ($throwable instanceof NotFoundHttpException) {
                // 记录 404 拦截日志
                $this->recordInterceptLog($request, 404, '页面不存在');

                $content = $this->render->render('errors.404', [
                    'requestPath' => $request?->getUri()->getPath(),
                    'requestMethod' => $request?->getMethod(),
                ]);

                return $response
                    ->withHeader('Server', 'Hyperf')
                    ->withStatus(404)
                    ->withBody(new SwooleStream((string) $content));
            }

            if ($throwable instanceof MethodNotAllowedHttpException) {
                // 记录 405 拦截日志
                $this->recordInterceptLog($request, 405, '请求方法不允许');

                $content = $this->render->render('errors.405', [
                    'requestPath' => $request?->getUri()->getPath(),
                    'requestMethod' => $request?->getMethod(),
                ]);

                return $response
                    ->withHeader('Server', 'Hyperf')
                    ->withStatus(405)
                    ->withBody(new SwooleStream((string) $content));
            }

            if ($throwable instanceof ServerErrorHttpException) {
                $content = $this->render->render('errors.500', [
                    'errorMessage' => $throwable->getMessage(),
                    'errorFile' => $throwable->getFile(),
                    'errorLine' => $throwable->getLine(),
                ]);

                return $response
                    ->withHeader('Server', 'Hyperf')
                    ->withStatus(500)
                    ->withBody(new SwooleStream((string) $content));
            }
        }
        $this->logger->error(sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile()));
        $this->logger->error($throwable->getTraceAsString());
        return $response->withHeader('Server', 'Hyperf')->withStatus(500)->withBody(new SwooleStream('Internal Server Error.'));
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }

    protected function jsonResponse(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        return $response
            ->withHeader('Server', 'Hyperf')
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status)
            ->withBody(new SwooleStream((string) json_encode($payload, JSON_UNESCAPED_UNICODE)));
    }

    /**
     * 判断是否是 API 请求
     */
    protected function isApiRequest(?ServerRequestInterface $request): bool
    {
        if (! $request) {
            return false;
        }

        if ($request->getAttribute('expects_json')) {
            return true;
        }

        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/api/')) {
            return true;
        }

        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        $contentType = $request->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        return false;
    }

    /**
     * 是否需要写入错误统计
     */
    protected function shouldRecordStatistic(Throwable $throwable): bool
    {
        if ($throwable instanceof BusinessException) {
            return false;
        }

        if ($throwable instanceof ValidationException) {
            return false;
        }

        if ($throwable instanceof NotFoundHttpException) {
            return false;
        }

        if ($throwable instanceof MethodNotAllowedHttpException) {
            return false;
        }

        return true;
    }

    /**
     * 记录拦截日志
     */
    protected function recordInterceptLog(?ServerRequestInterface $request, int $statusCode, string $reason): void
    {
        if (!$request) {
            return;
        }

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

            // 确定拦截类型
            $interceptType = match ($statusCode) {
                404 => '404',
                405 => 'invalid_path',
                default => '404'
            };

            // 异步记录拦截日志
            \Hyperf\Coroutine\Coroutine::create(function () use (
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
                $statusCode
            ) {
                try {
                    \App\Model\Admin\AdminInterceptLog::create([
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
                        'duration' => null,
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error('记录异常拦截日志失败', [
                        'error' => $e->getMessage(),
                        'status_code' => $statusCode,
                        'path' => $path,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // 记录日志失败不影响异常处理
            $this->logger->error('准备异常拦截日志数据失败', [
                'error' => $e->getMessage(),
            ]);
        }
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
