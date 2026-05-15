<?php

declare(strict_types=1);

namespace App\Controller\Admin\Strategy;

use Hyperf\Database\Model\SoftDeletes;
use function Hyperf\Support\class_uses_recursive;

/**
 * CRUD 软删除策略
 *
 * 负责处理回收站、恢复、永久删除等软删除相关逻辑
 *
 * 使用示例：
 * ```php
 * class UserTrashStrategy extends CrudTrashStrategy
 * {
 *     protected function getModelClass(): string
 *     {
 *         return AdminUser::class;
 *     }
 * }
 * ```
 */
abstract class CrudTrashStrategy
{
    protected string $modelClass;

    public function __construct()
    {
        $this->modelClass = $this->getModelClass();
    }

    /**
     * 获取模型类名
     */
    abstract protected function getModelClass(): string;

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
     * 获取回收站查询构建器
     */
    protected function getTrashQuery(): object
    {
        $query = $this->modelClass::query()->onlyTrashed();

        // 站点过滤
        if ($this->hasSiteId() && site_id() && !is_super_admin()) {
            $query->where('site_id', site_id());
        }

        return $query;
    }

    /**
     * 获取回收站分页数据
     */
    public function getTrashPaginatedData(array $params = []): array
    {
        $query = $this->getTrashQuery();

        // 关键词搜索
        if (!empty($params['keyword'])) {
            $keyword = trim((string)$params['keyword']);
            $searchFields = $this->getSearchableFields();
            if (!empty($searchFields)) {
                $query->where(function ($q) use ($searchFields, $keyword) {
                    foreach ($searchFields as $field) {
                        $q->orWhere($field, 'like', '%' . $keyword . '%');
                    }
                });
            }
        }

        // 应用过滤条件
        if (!empty($params['filters']) && is_array($params['filters'])) {
            foreach ($params['filters'] as $field => $value) {
                if ($value === '' || $value === null || str_starts_with($field, '_')) {
                    continue;
                }
                if (is_array($value)) {
                    $value = array_filter($value, fn($v) => $v !== '' && $v !== null);
                    if (!empty($value)) {
                        $query->whereIn($field, $value);
                    }
                    continue;
                }
                $query->where($field, $value);
            }
        }

        // 排序
        $sortField = $params['sort_field'] ?? 'deleted_at';
        $sortOrder = $params['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        $page = (int)($params['page'] ?? 1);
        $pageSize = (int)($params['page_size'] ?? 15);

        if ($pageSize > 0) {
            $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

            return [
                'data' => $paginator->items(),
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'page_size' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ];
        }

        $total = $query->count();
        return [
            'data' => $query->get()->toArray(),
            'total' => $total,
            'page' => 1,
            'page_size' => $total,
            'last_page' => 1,
        ];
    }

    /**
     * 获取可搜索字段
     */
    protected function getSearchableFields(): array
    {
        return ['id'];
    }

    /**
     * 恢复记录
     */
    public function restore(int $id): bool
    {
        $query = $this->getTrashQuery();
        $model = $query->where('id', $id)->first();

        if (!$model) {
            return false;
        }

        return $model->restore() !== false;
    }

    /**
     * 永久删除记录
     */
    public function forceDelete(int $id): bool
    {
        $query = $this->getTrashQuery();
        $model = $query->where('id', $id)->first();

        if (!$model) {
            return false;
        }

        return $model->forceDelete() !== false;
    }

    /**
     * 批量恢复
     */
    public function batchRestore(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $query = $this->getTrashQuery();
        return $query->whereIn('id', $ids)->restore();
    }

    /**
     * 批量永久删除
     */
    public function batchForceDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $query = $this->getTrashQuery();
        return $query->whereIn('id', $ids)->forceDelete();
    }

    /**
     * 清空回收站
     */
    public function clear(): int
    {
        $query = $this->getTrashQuery();
        return $query->forceDelete();
    }
}
