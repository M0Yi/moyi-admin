<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminLoginLog;
use App\Model\Admin\AdminUser;

class LoginLogService
{
    /**
     * 获取登录日志列表
     *
     * @param array $params
     * @return array
     */
    public function getList(array $params = []): array
    {
        $query = AdminLoginLog::query()
            ->with(['user', 'site'])
            ->orderBy('created_at', 'desc');

        $requestedSiteId = isset($params['site_id']) ? (int) $params['site_id'] : 0;
        $currentSiteId = (int) (site_id() ?? 0);
        if (is_super_admin()) {
            if ($requestedSiteId > 0) {
                $query->where('site_id', $requestedSiteId);
            }
        } elseif ($currentSiteId > 0) {
            $query->where('site_id', $currentSiteId);
        }

        if (!empty($params['username'])) {
            $query->where('username', 'like', '%' . trim((string)$params['username']) . '%');
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int)$params['status']);
        }

        if (!empty($params['ip'])) {
            $query->where('ip', 'like', '%' . trim((string)$params['ip']) . '%');
        }

        if (!empty($params['start_date'])) {
            $query->where('created_at', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->where('created_at', '<=', $params['end_date'] . ' 23:59:59');
        }

        $pageSize = $params['page_size'] ?? 15;
        $paginator = $query->paginate((int)$pageSize);

        return [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    public function getById(int $id): AdminLoginLog
    {
        $query = AdminLoginLog::query()->where('id', $id);
        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        $log = $query->with(['user', 'site'])->first();
        if (!$log) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '登录日志不存在');
        }

        return $log;
    }

    public function delete(int $id): bool
    {
        $log = $this->getById($id);
        return $log->delete();
    }

    public function batchDelete(array $ids): int
    {
        $query = AdminLoginLog::query()->whereIn('id', $ids);
        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->delete();
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
}





