<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminLoginLog;

/**
 * 登录日志服务
 */
class LoginLogService extends BaseService
{
    /**
     * 获取模型类名
     */
    protected function getModelClass(): string
    {
        return AdminLoginLog::class;
    }

    /**
     * 获取可搜索字段列表
     */
    protected function getSearchableFields(): array
    {
        return ['username', 'ip'];
    }

    /**
     * 获取可排序字段列表
     */
    protected function getSortableFields(): array
    {
        return ['id', 'created_at'];
    }

    /**
     * 获取默认排序字段
     */
    protected function getDefaultSortField(): string
    {
        return 'created_at';
    }

    /**
     * 获取默认排序方向
     */
    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    /**
     * 获取列表数据（覆盖 BaseService）
     */
    public function getList(array $params = [], int $pageSize = 15): array
    {
        $query = $this->buildQuery($params);

        // 站点过滤
        $requestedSiteId = isset($params['site_id']) ? (int) $params['site_id'] : 0;
        $currentSiteId = (int) (site_id() ?? 0);

        if (is_super_admin()) {
            if ($requestedSiteId > 0) {
                $query->where('site_id', $requestedSiteId);
            }
        } elseif ($currentSiteId > 0) {
            $query->where('site_id', $currentSiteId);
        }

        // 用户名筛选
        if (!empty($params['username'])) {
            $query->where('username', 'like', '%' . trim((string)$params['username']) . '%');
        }

        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int)$params['status']);
        }

        // IP 筛选
        if (!empty($params['ip'])) {
            $query->where('ip', 'like', '%' . trim((string)$params['ip']) . '%');
        }

        // 日期范围筛选
        if (!empty($params['start_date'])) {
            $query->where('created_at', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->where('created_at', '<=', $params['end_date'] . ' 23:59:59');
        }

        // 排序
        $sortField = $params['sort_field'] ?? $this->getDefaultSortField();
        $sortOrder = $params['sort_order'] ?? $this->getDefaultSortOrder();
        $sortableFields = $this->getSortableFields();

        if (in_array($sortField, $sortableFields, true)) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            $query->orderBy($this->getDefaultSortField(), $this->getDefaultSortOrder());
        }

        // 分页
        $page = (int) ($params['page'] ?? 1);
        $pageSize = (int) ($params['page_size'] ?? $pageSize);
        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        return [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 获取登录日志详情（覆盖 BaseService）
     */
    public function find(int $id): ?\App\Model\Model
    {
        $query = $this->getQuery();
        $query->with(['user', 'site']);

        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        /** @var AdminLoginLog|null $log */
        $log = $query->where('id', $id)->first();

        return $log;
    }

    /**
     * 获取站点筛选选项（仅超级管理员）
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
     * 删除登录日志（覆盖 BaseService）
     */
    public function delete(int $id): bool
    {
        $log = $this->find($id);

        if (!$log) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '登录日志不存在');
        }

        return $log->delete();
    }

    /**
     * 批量删除登录日志
     */
    public function batchDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $query = $this->getQuery()->whereIn('id', $ids);        return $query->delete();
    }    /**
     * 获取所有数据（不分页）
     */
    public function getAll(array $params = []): array
    {
        return $this->getList($params, 0)['list'] ?? [];
    }
}