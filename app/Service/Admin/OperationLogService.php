<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminOperationLog;
use App\Model\Admin\AdminUser;

class OperationLogService
{
    /**
     * 获取操作日志列表
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getList(array $params = []): array
    {
        $query = AdminOperationLog::query()
            ->with(['user', 'site'])
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

        // 用户筛选
        if (!empty($params['user_id'])) {
            $query->where('user_id', (int) $params['user_id']);
        }

        // 用户名筛选
        if (!empty($params['username'])) {
            $query->where('username', 'like', '%' . trim((string) $params['username']) . '%');
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
     * 获取操作日志详情
     *
     * @param int $id 日志ID
     * @return AdminOperationLog
     * @throws \App\Exception\BusinessException
     */
    public function getById(int $id): AdminOperationLog
    {
        $query = AdminOperationLog::query()->where('id', $id);
        
        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        $log = $query->with(['user', 'site'])->first();

        if (!$log) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '操作日志不存在');
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
     * 获取用户筛选选项
     *
     * @return array
     */
    public function getUserFilterOptions(): array
    {
        $query = AdminUser::query()
            ->where('status', 1)
            ->orderBy('id', 'asc');

        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        $users = $query->get();

        $options = [['value' => '', 'label' => '全部用户']];
        foreach ($users as $user) {
            $options[] = [
                'value' => $user->id,
                'label' => $user->username . ($user->real_name ? ' (' . $user->real_name . ')' : ''),
            ];
        }

        return $options;
    }

    /**
     * 删除操作日志
     *
     * @param int $id 日志ID
     * @return bool
     * @throws \App\Exception\BusinessException
     */
    public function delete(int $id): bool
    {
        $log = $this->getById($id);
        return $log->delete();
    }

    /**
     * 批量删除操作日志
     *
     * @param array $ids 日志ID数组
     * @return int 删除的记录数
     */
    public function batchDelete(array $ids): int
    {
        $query = AdminOperationLog::query()->whereIn('id', $ids);
        
        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->delete();
    }
}

