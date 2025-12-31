<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
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

    /**
     * 获取错误统计列表
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getList(array $params = []): array
    {
        $query = AdminErrorStatistic::query()
            ->orderBy('last_occurred_at', 'desc');

        // 站点过滤：普通管理员固定当前站点，超级管理员可根据筛选选择站点
        $requestedSiteId = isset($params['site_id']) ? (int) $params['site_id'] : 0;
        $currentSiteId = (int) (site_id() ?? 0);
        if (is_super_admin()) {
            if ($requestedSiteId > 0) {
                $query->where('site_id', $requestedSiteId);
            }
        } elseif ($currentSiteId > 0) {
            $query->where('site_id', $currentSiteId);
        }

        // 用户筛选
        if (!empty($params['user_id'])) {
            $query->where('user_id', (int) $params['user_id']);
        }

        // 用户名筛选
        if (!empty($params['username'])) {
            $query->where('username', 'like', '%' . trim((string) $params['username']) . '%');
        }

        // 异常类筛选
        if (!empty($params['exception_class'])) {
            $query->where('exception_class', 'like', '%' . trim((string) $params['exception_class']) . '%');
        }

        // 错误消息筛选
        if (!empty($params['error_message'])) {
            $query->where('error_message', 'like', '%' . trim((string) $params['error_message']) . '%');
        }

        // 错误等级筛选
        if (!empty($params['error_level'])) {
            $query->where('error_level', trim((string) $params['error_level']));
        }

        // 状态码筛选
        if (isset($params['status_code']) && $params['status_code'] !== '') {
            $query->where('status_code', (int) $params['status_code']);
        }

        // 请求路径筛选
        if (!empty($params['request_path'])) {
            $query->where('request_path', 'like', '%' . trim((string) $params['request_path']) . '%');
        }

        // IP地址筛选
        if (!empty($params['request_ip'])) {
            $query->where('request_ip', 'like', '%' . trim((string) $params['request_ip']) . '%');
        }

        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        // 日期范围筛选
        if (!empty($params['start_date'])) {
            $query->where('last_occurred_at', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->where('last_occurred_at', '<=', $params['end_date'] . ' 23:59:59');
        }

        // 分页
        $pageSize = $params['page_size'] ?? 15;
        $paginator = $query->paginate((int)$pageSize);

        return [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * 获取错误统计详情
     *
     * @param int $id 错误统计ID
     * @return AdminErrorStatistic
     * @throws BusinessException
     */
    public function getById(int $id): AdminErrorStatistic
    {
        $query = AdminErrorStatistic::query()->where('id', $id);

        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        $errorStat = $query->first();

        if (!$errorStat) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '错误统计记录不存在');
        }

        return $errorStat;
    }

    /**
     * 获取站点筛选选项（仅超级管理员）
     *
     * @return array
     */
    public function getSiteFilterOptions(): array
    {
        if (!is_super_admin()) {
            return [];
        }

        $sites = \App\Model\Admin\AdminSite::query()
            ->where('status', 1)
            ->orderBy('id', 'asc')
            ->get();

        $options = [['value' => '', 'label' => '全部站点']];
        foreach ($sites as $site) {
            $options[] = [
                'value' => $site->id,
                'label' => $site->name,
            ];
        }

        return $options;
    }

    /**
     * 删除错误统计记录
     *
     * @param int $id 错误统计ID
     * @return bool
     * @throws BusinessException
     */
    public function delete(int $id): bool
    {
        $errorStat = $this->getById($id);
        return $errorStat->delete();
    }

    /**
     * 批量删除错误统计记录
     *
     * @param array $ids 错误统计ID数组
     * @return int 删除的记录数
     */
    public function batchDelete(array $ids): int
    {
        $query = AdminErrorStatistic::query()->whereIn('id', $ids);

        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->delete();
    }

    /**
     * 标记错误为已解决
     *
     * @param int $id 错误统计ID
     * @return bool
     */
    public function resolve(int $id): bool
    {
        $errorStat = $this->getById($id);
        $errorStat->status = 2; // 2表示已解决
        $errorStat->resolved_at = now();
        return $errorStat->save();
    }

    /**
     * 批量标记错误为已解决
     *
     * @param array $ids 错误统计ID数组
     * @return int 更新的记录数
     */
    public function batchResolve(array $ids): int
    {
        $query = AdminErrorStatistic::query()
            ->whereIn('id', $ids)
            ->where('status', '<', 2); // 只更新未解决和处理中的

        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->update([
            'status' => 2,
            'resolved_at' => now(),
        ]);
    }
}


