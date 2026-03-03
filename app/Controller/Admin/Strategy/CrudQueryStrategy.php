<?php

declare(strict_types=1);

namespace App\Controller\Admin\Strategy;

use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use function Hyperf\Support\class_uses_recursive;

/**
 * CRUD 查询策略
 *
 * 负责处理查询构建、过滤、排序、分页等逻辑
 *
 * 使用示例：
 * ```php
 * class UserQueryStrategy extends CrudQueryStrategy
 * {
 *     protected function getModelClass(): string
 *     {
 *         return AdminUser::class;
 *     }
 *
 *     protected function getSearchableFields(): array
 *     {
 *         return ['username', 'email', 'real_name'];
 *     }
 *
 *     protected function getSortableFields(): array
 *     {
 *         return ['id', 'username', 'created_at'];
 *     }
 *
 *     protected function applyCustomQuery(?int $siteId = null): void
 *     {
 *         // 自定义查询逻辑，如添加关联
 *         $this->query->with(['roles']);
 *     }
 * }
 * ```
 */
abstract class CrudQueryStrategy
{
    protected string $modelClass;
    protected object $query;

    public function __construct()
    {
        $this->modelClass = $this->getModelClass();
        $this->query = $this->modelClass::query();
    }

    /**
     * 获取模型类名
     */
    abstract protected function getModelClass(): string;

    /**
     * 获取可搜索字段
     */
    protected function getSearchableFields(): array
    {
        return ['id'];
    }

    /**
     * 获取可排序字段
     */
    protected function getSortableFields(): array
    {
        return ['id', 'created_at', 'updated_at'];
    }

    /**
     * 获取默认排序字段
     */
    protected function getDefaultSortField(): string
    {
        return 'id';
    }

    /**
     * 获取默认排序方向
     */
    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    /**
     * 获取每页数量
     */
    protected function getPageSize(): int
    {
        return 15;
    }

    /**
     * 构建查询
     */
    public function buildQuery(array $params = []): self
    {
        // 应用站点过滤
        if ($this->hasSiteId() && site_id() && !is_super_admin()) {
            $this->query->where('site_id', site_id());
        }

        // 应用软删除过滤
        if ($this->usesSoftDeletes()) {
            $this->query->whereNull('deleted_at');
        }

        // 应用自定义查询
        $this->applyCustomQuery(site_id());

        // 关键词搜索
        if (!empty($params['keyword'])) {
            $keyword = trim((string)$params['keyword']);
            $searchFields = $this->getSearchableFields();
            if (!empty($searchFields)) {
                $this->query->where(function ($q) use ($searchFields, $keyword) {
                    foreach ($searchFields as $field) {
                        $q->orWhere($field, 'like', '%' . $keyword . '%');
                    }
                });
            }
        }

        // 应用过滤条件
        if (!empty($params['filters']) && is_array($params['filters'])) {
            $this->applyFilters($params['filters']);
        }

        // 排序
        $sortField = $params['sort_field'] ?? $this->getDefaultSortField();
        $sortOrder = $params['sort_order'] ?? $this->getDefaultSortOrder();
        $sortableFields = $this->getSortableFields();
        if (in_array($sortField, $sortableFields, true)) {
            $this->query->orderBy($sortField, $sortOrder);
        } else {
            $this->query->orderBy($this->getDefaultSortField(), $this->getDefaultSortOrder());
        }

        return $this;
    }

    /**
     * 应用自定义查询（子类可覆盖）
     */
    protected function applyCustomQuery(?int $siteId = null): void
    {
        // 默认空实现，子类可覆盖
    }

    /**
     * 应用过滤条件
     */
    protected function applyFilters(array $filters): void
    {
        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (str_starts_with($field, '_')) {
                continue;
            }

            if (is_array($value)) {
                $value = array_filter($value, fn($v) => $v !== '' && $v !== null);
                if (!empty($value)) {
                    $this->query->whereIn($field, $value);
                }
                continue;
            }

            if (str_ends_with($field, '_min')) {
                $baseField = substr($field, 0, -4);
                $this->query->where($baseField, '>=', $value);
                continue;
            }

            if (str_ends_with($field, '_max')) {
                $baseField = substr($field, 0, -4);
                $this->query->where($baseField, '<=', $value);
                continue;
            }

            $this->query->where($field, $value);
        }
    }

    /**
     * 获取查询构建器
     */
    public function getQuery(): object
    {
        return $this->query;
    }

    /**
     * 获取分页数据
     */
    public function getPaginatedData(array $params = []): array
    {
        $this->buildQuery($params);

        $page = (int)($params['page'] ?? 1);
        $pageSize = (int)($params['page_size'] ?? $this->getPageSize());

        if ($pageSize > 0) {
            $paginator = $this->query->paginate($pageSize, ['*'], 'page', $page);

            return [
                'data' => $this->formatData($paginator->items()),
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'page_size' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ];
        }

        $total = $this->query->count();
        return [
            'data' => $this->formatData($this->query->get()->toArray()),
            'total' => $total,
            'page' => 1,
            'page_size' => $total,
            'last_page' => 1,
        ];
    }

    /**
     * 获取所有数据
     */
    public function getAllData(array $params = []): array
    {
        $this->buildQuery($params);
        return $this->formatData($this->query->get()->toArray());
    }

    /**
     * 格式化数据（子类可覆盖）
     */
    protected function formatData(array $data): array
    {
        return array_map(function ($item) {
            return is_array($item) ? $item : $item->toArray();
        }, $data);
    }

    /**
     * 检查模型是否使用软删除
     */
    public function usesSoftDeletes(): bool
    {
        return in_array(
            SoftDeletes::class,
            class_uses_recursive($this->modelClass),
            true
        );
    }

    /**
     * 检查模型是否有 site_id 字段
     */
    public function hasSiteId(): bool
    {
        $model = new $this->modelClass();
        $fillable = $model->getFillable();
        return in_array('site_id', $fillable, true);
    }

    /**
     * 根据 ID 查找记录
     */
    public function find(int $id): ?object
    {
        $query = $this->modelClass::query()->where('id', $id);

        if ($this->hasSiteId() && site_id() && !is_super_admin()) {
            $query->where('site_id', site_id());
        }

        return $query->first();
    }

    /**
     * 批量删除
     */
    public function batchDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $query = $this->modelClass::query()->whereIn('id', $ids);

        if ($this->hasSiteId() && site_id() && !is_super_admin()) {
            $query->where('site_id', site_id());
        }

        if ($this->usesSoftDeletes()) {
            return $query->delete();
        }

        return $query->forceDelete();
    }
}
