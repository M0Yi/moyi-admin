<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Model\Admin\AdminErrorStatistic;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use function Hyperf\Support\now;

class ErrorStatisticService
{
    public function __construct(
        private StdoutLoggerInterface $logger
    ) {
    }

    /**
     * 记录系统异常信息
     */
    public function record(Throwable $throwable, ?ServerRequestInterface $request = null): void
    {
        try {
            $siteId = (int) (Context::get('site_id') ?? 0);
            $userContext = Context::get('admin_user');
            [$userId, $username] = $this->extractUserInfo($userContext);

            $errorHash = $this->buildErrorHash($throwable);
            $now = now();
            $payload = [
                'site_id' => $siteId,
                'user_id' => $userId,
                'username' => $username,
                'request_id' => $request?->getHeaderLine('X-Request-ID') ?: null,
                'error_hash' => $errorHash,
                'exception_class' => $throwable::class,
                'error_code' => $this->stringifyErrorCode($throwable->getCode()),
                'error_message' => $this->trimMessage($throwable->getMessage()),
                'error_file' => $throwable->getFile(),
                'error_line' => $throwable->getLine(),
                'error_level' => 'error',
                'status_code' => $this->resolveStatusCode($throwable),
                'request_method' => $request?->getMethod(),
                'request_path' => $request?->getUri()->getPath(),
                'request_ip' => $this->resolveIp($request),
                'user_agent' => $request?->getHeaderLine('User-Agent'),
                'request_query' => $request?->getQueryParams(),
                'request_body' => $this->normalizeArray($request?->getParsedBody()),
                'request_headers' => $request?->getHeaders(),
                'error_trace' => $throwable->getTraceAsString(),
                'context' => $this->buildContext($request),
                'last_occurred_at' => $now,
            ];

            $stat = AdminErrorStatistic::query()
                ->where('site_id', $siteId)
                ->where('error_hash', $errorHash)
                ->first();

            if ($stat) {
                $stat->fill($payload);
                $stat->occurrence_count = ($stat->occurrence_count ?? 0) + 1;
                $stat->save();

                return;
            }

            $payload['occurrence_count'] = 1;
            $payload['first_occurred_at'] = $now;

            AdminErrorStatistic::query()->create($payload);
        } catch (Throwable $exception) {
            $this->logger->warning('Failed to persist error statistic', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function extractUserInfo(mixed $userContext): array
    {
        if (is_object($userContext)) {
            $userId = $userContext->id ?? null;
            $username = $userContext->username ?? null;

            return [$userId ? (int) $userId : null, $username];
        }

        if (is_array($userContext)) {
            $userId = isset($userContext['id']) ? (int) $userContext['id'] : null;
            $username = $userContext['username'] ?? null;

            return [$userId, $username];
        }

        return [null, null];
    }

    private function buildErrorHash(Throwable $throwable): string
    {
        $parts = [
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            (string) $throwable->getLine(),
        ];

        return hash('sha256', implode('|', $parts));
    }

    private function trimMessage(string $message): string
    {
        return mb_strimwidth($message, 0, 500, '...');
    }

    private function resolveIp(?ServerRequestInterface $request): ?string
    {
        if (! $request) {
            return null;
        }

        $serverParams = $request->getServerParams();
        if (! empty($serverParams['http_x_forwarded_for'])) {
            $ips = explode(',', (string) $serverParams['http_x_forwarded_for']);

            return trim($ips[0]);
        }

        return $serverParams['remote_addr'] ?? null;
    }

    private function normalizeArray(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            return (array) $payload;
        }

        return null;
    }

    private function buildContext(?ServerRequestInterface $request): array
    {
        return [
            'referer' => $request?->getHeaderLine('Referer'),
            'accept' => $request?->getHeaderLine('Accept'),
            'content_type' => $request?->getHeaderLine('Content-Type'),
        ];
    }

    private function resolveStatusCode(Throwable $throwable): int
    {
        if (method_exists($throwable, 'getStatusCode')) {
            return (int) $throwable->getStatusCode();
        }

        return 500;
    }

    private function stringifyErrorCode(int|string $code): ?string
    {
        if ($code === 0 || $code === '0') {
            return null;
        }

        return (string) $code;
    }
}


