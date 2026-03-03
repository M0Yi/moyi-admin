<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminOperationLog;
use App\Model\Admin\AdminUser;

/**
 * 操作日志服务
 */
class OperationLogService extends BaseService
{
    /**
     * 获取模型类名
     */
    protected function getModelClass(): string
    {
        return AdminOperationLog::class;
    }

    /**
     * 获取可搜索字段列表
     */
    protected function getSearchableFields(): array
    {
        return ['username', 'path', 'ip'];
    }

    /**
     * 获取可排序字段列表
     */
    protected function getSortableFields(): array
    {
        return ['id', 'created_at', 'duration'];
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
     * 重写 getQuery 添加站点过滤
     */
    protected function getQuery(): \Hyperf\Database\Model\Builder
    {
        $query = parent::getQuery();

        // 站点过滤：普通管理员固定当前站点，超级管理员可根据筛选选择站点
        $requestedSiteId = 0; // 不在基础查询中过滤
        $currentSiteId = (int) (site_id() ?? 0);

        if (is_super_admin()) {
            // 超级管理员可以看到所有站点的数据
            // 具体过滤由 listData 中的参数决定
        } elseif ($currentSiteId > 0) {
            // 普通管理员只能看到当前站点的数据
            $query->where('site_id', $currentSiteId);
        }

        return $query;
    }

    /**
     * 获取列表数据（覆盖 BaseService）
     */
    public function getList(array $params = [], int $pageSize = 15): array
    {
        $query = $this->buildQuery($params);

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

        // 用户名筛选（覆盖默认搜索）
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
     * 获取操作日志详情（覆盖 BaseService）
     */
    public function find(int $id): ?\App\Model\Model
    {
        $query = $this->getQuery();
        $query->with(['user', 'site']);

        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        /** @var AdminOperationLog|null $log */
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
     * 获取用户筛选选项
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
     * 删除操作日志（覆盖 BaseService）
     */
    public function delete(int $id): bool
    {
        $log = $this->find($id);

        if (!$log) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '操作日志不存在');
        }

        return $log->delete();
    }

    /**
     * 批量删除操作日志
     */
    public function batchDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $query = $this->getQuery()->whereIn('id', $ids);

        return $query->delete();
    }

    /**
     * 获取所有数据（不分页）
     */
    public function getAll(array $params = []): array
    {
        return $this->getList($params, 0)['list'] ?? [];
    }
}
