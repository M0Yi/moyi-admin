<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminInterceptLog;
use App\Model\Admin\AdminSite;

class InterceptLogService
{
    /**
     * 获取拦截日志列表
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getList(array $params = []): array
    {
        $query = AdminInterceptLog::query()
            ->with(['site'])
            ->orderBy('created_at', 'desc');

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

        // 后台入口筛选
        if (!empty($params['admin_entry_path'])) {
            $query->where('admin_entry_path', trim((string) $params['admin_entry_path']));
        }

        // 拦截类型筛选
        if (!empty($params['intercept_type'])) {
            $query->where('intercept_type', trim((string) $params['intercept_type']));
        }

        // 请求方法筛选
        if (!empty($params['method'])) {
            $query->where('method', strtoupper(trim((string) $params['method'])));
        }

        // 请求路径筛选
        if (!empty($params['path'])) {
            $query->where('path', 'like', '%' . trim((string) $params['path']) . '%');
        }

        // IP地址筛选
        if (!empty($params['ip'])) {
            $query->where('ip', 'like', '%' . trim((string) $params['ip']) . '%');
        }

        // 状态码筛选
        if (isset($params['status_code']) && $params['status_code'] !== '') {
            $query->where('status_code', (int) $params['status_code']);
        }

        // 日期范围筛选
        if (!empty($params['start_date'])) {
            $query->where('created_at', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->where('created_at', '<=', $params['end_date'] . ' 23:59:59');
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
     * 获取拦截日志详情
     *
     * @param int $id 日志ID
     * @return AdminInterceptLog
     * @throws BusinessException
     */
    public function getById(int $id): AdminInterceptLog
    {
        $query = AdminInterceptLog::query()->where('id', $id);

        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        $log = $query->with(['site'])->first();

        if (!$log) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '拦截日志不存在');
        }

        return $log;
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

        $sites = AdminSite::query()
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
     * 获取拦截类型筛选选项
     *
     * @return array
     */
    public function getInterceptTypeFilterOptions(): array
    {
        return [
            ['value' => '', 'label' => '全部类型'],
            ['value' => AdminInterceptLog::TYPE_404, 'label' => '页面不存在'],
            ['value' => AdminInterceptLog::TYPE_INVALID_PATH, 'label' => '非法路径'],
            ['value' => AdminInterceptLog::TYPE_UNAUTHORIZED, 'label' => '未授权访问'],
        ];
    }

    /**
     * 获取请求方法筛选选项
     *
     * @return array
     */
    public function getMethodFilterOptions(): array
    {
        return [
            ['value' => '', 'label' => '全部方法'],
            ['value' => 'GET', 'label' => 'GET'],
            ['value' => 'POST', 'label' => 'POST'],
            ['value' => 'PUT', 'label' => 'PUT'],
            ['value' => 'DELETE', 'label' => 'DELETE'],
            ['value' => 'PATCH', 'label' => 'PATCH'],
            ['value' => 'HEAD', 'label' => 'HEAD'],
            ['value' => 'OPTIONS', 'label' => 'OPTIONS'],
        ];
    }

    /**
     * 获取状态码筛选选项
     *
     * @return array
     */
    public function getStatusCodeFilterOptions(): array
    {
        return [
            ['value' => '', 'label' => '全部状态码'],
            ['value' => 404, 'label' => '404 - 页面不存在'],
            ['value' => 403, 'label' => '403 - 禁止访问'],
            ['value' => 401, 'label' => '401 - 未授权'],
            ['value' => 405, 'label' => '405 - 方法不允许'],
        ];
    }

    /**
     * 删除拦截日志
     *
     * @param int $id 日志ID
     * @return bool
     * @throws BusinessException
     */
    public function delete(int $id): bool
    {
        $log = $this->getById($id);
        return $log->delete();
    }

    /**
     * 批量删除拦截日志
     *
     * @param array $ids 日志ID数组
     * @return int 删除的记录数
     */
    public function batchDelete(array $ids): int
    {
        $query = AdminInterceptLog::query()->whereIn('id', $ids);

        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->delete();
    }

    /**
     * 清理过期日志
     *
     * @param int $days 保留天数，默认30天
     * @return int 删除的记录数
     */
    public function cleanupExpiredLogs(int $days = 30): int
    {
        $query = AdminInterceptLog::query()
            ->where('created_at', '<', now()->subDays($days));

        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->delete();
    }

    /**
     * 获取统计数据
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $query = AdminInterceptLog::query();

        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        // 今日拦截次数
        $todayCount = (clone $query)->whereDate('created_at', today())->count();

        // 本周拦截次数
        $weekCount = (clone $query)->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->count();

        // 本月拦截次数
        $monthCount = (clone $query)->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        // 拦截类型统计
        $typeStats = (clone $query)->selectRaw('intercept_type, COUNT(*) as count')
            ->groupBy('intercept_type')
            ->pluck('count', 'intercept_type')
            ->toArray();

        // 状态码统计
        $statusStats = (clone $query)->selectRaw('status_code, COUNT(*) as count')
            ->groupBy('status_code')
            ->pluck('count', 'status_code')
            ->toArray();

        // 热门IP统计（Top 10）
        $topIps = (clone $query)->selectRaw('ip, COUNT(*) as count')
            ->groupBy('ip')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'today_count' => $todayCount,
            'week_count' => $weekCount,
            'month_count' => $monthCount,
            'type_stats' => $typeStats,
            'status_stats' => $statusStats,
            'top_ips' => $topIps,
        ];
    }
}
