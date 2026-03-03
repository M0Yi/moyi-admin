<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Model;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Db;
use function Hyperf\Support\class_uses_recursive;

/**
 * 基础服务类
 *
 * 提供通用的 CRUD 操作和查询构建方法，供自定义服务继承
 *
 * 特性：
 * - 通用 CRUD 操作（create、update、delete、find）
 * - 查询构建器封装（站点过滤、关键词搜索、过滤条件）
 * - 分页处理
 * - 软删除支持
 * - 事务封装
 * - 唯一性检查
 *
 * 使用示例：
 * ```php
 * class UserService extends BaseService
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
 * }
 * ```
 *
 * @package App\Service\Admin
 */
abstract class BaseService
{
    /**
     * 获取模型类名
     * 子类必须实现此方法
     *
     * @return string 模型类名
     */
    abstract protected function getModelClass(): string;

    /**
     * 获取可搜索字段列表
     *
     * @return array
     */
    protected function getSearchableFields(): array
    {
        return [];
    }

    /**
     * 获取可排序字段列表
     *
     * @return array
     */
    protected function getSortableFields(): array
    {
        return ['id', 'created_at', 'updated_at'];
    }

    /**
     * 获取默认排序字段
     *
     * @return string
     */
    protected function getDefaultSortField(): string
    {
        return 'id';
    }

    /**
     * 获取默认排序方向
     *
     * @return string
     */
    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    /**
     * 检查模型是否使用软删除
     *
     * @return bool
     */
    protected function usesSoftDeletes(): bool
    {
        $modelClass = $this->getModelClass();
        if (!class_exists($modelClass)) {
            return false;
        }

        return in_array(
            SoftDeletes::class,
            class_uses_recursive($modelClass),
            true
        );
    }

    /**
     * 检查模型是否有 site_id 字段
     *
     * @return bool
     */
    protected function hasSiteId(): bool
    {
        $modelClass = $this->getModelClass();
        if (!class_exists($modelClass)) {
            return false;
        }

        $model = new $modelClass();
        $fillable = $model->getFillable();

        return in_array('site_id', $fillable, true);
    }

    /**
     * 获取站点 ID
     *
     * @param array $data
     * @return int|null
     */
    protected function resolveSiteId(array $data = []): ?int
    {
        // 优先使用参数传递的 site_id
        if (isset($data['site_id']) && is_numeric($data['site_id'])) {
            return (int) $data['site_id'];
        }

        // 超级管理员可以传递 site_id，普通用户使用当前站点
        if (is_super_admin() && isset($data['site_id'])) {
            return (int) $data['site_id'];
        }

        return site_id();
    }

    /**
     * 获取基础查询构建器
     *
     * @return \Hyperf\Database\Model\Builder
     */
    protected function getQuery(): \Hyperf\Database\Model\Builder
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query();

        // 自动添加站点过滤
        if ($this->hasSiteId()) {
            $siteId = site_id();
            if ($siteId && !is_super_admin()) {
                $query->where('site_id', $siteId);
            }
        }

        // 自动过滤软删除记录
        if ($this->usesSoftDeletes()) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    /**
     * 获取查询构建器（带条件）
     *
     * @param array $params 查询参数
     * @return \Hyperf\Database\Model\Builder
     */
    protected function buildQuery(array $params = []): \Hyperf\Database\Model\Builder
    {
        $query = $this->getQuery();

        // 关键词搜索
        if (!empty($params['keyword'])) {
            $keyword = trim((string) $params['keyword']);
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
            $this->applyFilters($query, $params['filters']);
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

        return $query;
    }

    /**
     * 应用过滤条件
     *
     * @param \Hyperf\Database\Model\Builder $query
     * @param array $filters
     * @return void
     */
    protected function applyFilters($query, array $filters): void
    {
        foreach ($filters as $field => $value) {
            // 跳过空值
            if ($value === '' || $value === null) {
                continue;
            }

            // 跳过系统保留字段
            if (is_string($field) && str_starts_with($field, '_')) {
                continue;
            }

            // 处理数组值（多选）
            if (is_array($value)) {
                $value = array_filter($value, fn($v) => $v !== '' && $v !== null);
                if (!empty($value)) {
                    $query->whereIn($field, $value);
                }
                continue;
            }

            // 处理区间字段
            if (is_string($field) && str_ends_with($field, '_min')) {
                $baseField = substr($field, 0, -4);
                $query->where($baseField, '>=', $value);
                continue;
            }
            if (is_string($field) && str_ends_with($field, '_max')) {
                $baseField = substr($field, 0, -4);
                $query->where($baseField, '<=', $value);
                continue;
            }

            // 默认：精确匹配
            $query->where($field, $value);
        }
    }

    /**
     * 获取列表数据
     *
     * @param array $params
     * @param int $pageSize
     * @return array
     */
    public function getList(array $params = [], int $pageSize = 15): array
    {
        $query = $this->buildQuery($params);

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
     * 获取列表数据（不分页）
     *
     * @param array $params
     * @return array
     */
    public function getAll(array $params = []): array
    {
        $query = $this->buildQuery($params);
        return $query->get()->toArray();
    }

    /**
     * 获取单条记录
     *
     * @param int $id
     * @return Model|null
     */
    public function find(int $id): ?Model
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query()->where('id', $id);

        // 站点过滤
        if ($this->hasSiteId()) {
            $siteId = site_id();
            if ($siteId && !is_super_admin()) {
                $query->where('site_id', $siteId);
            }
        }

        return $query->first();
    }

    /**
     * 获取单条记录（不存在则抛出异常）
     *
     * @param int $id
     * @param string|null $errorMessage
     * @return Model
     * @throws BusinessException
     */
    public function findOrFail(int $id, ?string $errorMessage = null): Model
    {
        $model = $this->find($id);

        if (!$model) {
            throw new BusinessException(
                ErrorCode::NOT_FOUND,
                $errorMessage ?? $this->getModelName() . '不存在'
            );
        }

        return $model;
    }

    /**
     * 根据条件获取单条记录
     *
     * @param array $conditions
     * @return Model|null
     */
    public function first(array $conditions = []): ?Model
    {
        $query = $this->getQuery();

        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }

        return $query->first();
    }

    /**
     * 创建记录
     *
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model
    {
        $modelClass = $this->getModelClass();

        // 自动填充站点 ID
        if ($this->hasSiteId()) {
            $siteId = $this->resolveSiteId($data);
            if ($siteId && !is_super_admin()) {
                $data['site_id'] = $siteId;
            }
        }

        // 过滤可填充字段
        $data = $this->filterFillable($data);

        // 规范化数据
        $data = $this->normalizeData($data);

        /** @var Model $model */
        $model = new $modelClass($data);
        $model->save();

        return $model;
    }

    /**
     * 更新记录
     *
     * @param int $id
     * @param array $data
     * @return Model
     * @throws BusinessException
     */
    public function update(int $id, array $data): Model
    {
        $model = $this->findOrFail($id);

        // 不能更新站点 ID
        if ($this->hasSiteId()) {
            unset($data['site_id']);
        }

        // 过滤可填充字段
        $data = $this->filterFillable($data);

        // 规范化数据
        $data = $this->normalizeData($data);

        // 移除不允许更新的字段
        unset($data['id'], $data['created_at']);

        $model->fill($data);
        $model->save();

        return $model;
    }

    /**
     * 删除记录
     *
     * @param int $id
     * @return bool
     * @throws BusinessException
     */
    public function delete(int $id): bool
    {
        $model = $this->findOrFail($id);

        if ($this->usesSoftDeletes()) {
            return $model->delete() !== false;
        }

        return $model->forceDelete() !== false;
    }

    /**
     * 批量删除
     *
     * @param array $ids
     * @return int
     */
    public function batchDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        // 验证 ID
        $ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
        if (empty($ids)) {
            return 0;
        }

        $modelClass = $this->getModelClass();
        $query = $modelClass::query()->whereIn('id', $ids);

        // 站点过滤
        if ($this->hasSiteId()) {
            $siteId = site_id();
            if ($siteId && !is_super_admin()) {
                $query->where('site_id', $siteId);
            }
        }

        if ($this->usesSoftDeletes()) {
            return $query->delete();
        }

        return $query->forceDelete();
    }

    /**
     * 切换字段值
     *
     * @param int $id
     * @param string $field
     * @return Model
     * @throws BusinessException
     */
    public function toggleField(int $id, string $field): Model
    {
        $model = $this->findOrFail($id);

        if (!isset($model->{$field}) || !is_numeric($model->{$field})) {
            throw new BusinessException(ErrorCode::BAD_REQUEST, '字段类型不支持切换');
        }

        $newValue = $model->{$field} ? 0 : 1;
        $model->{$field} = $newValue;

        $model->save();

        return $model;
    }

    /**
     * 检查字段唯一性
     *
     * @param string $field
     * @param mixed $value
     * @param int|null $excludeId 排除的 ID（用于更新时的唯一性检查）
     * @return bool
     */
    protected function isUnique(string $field, mixed $value, ?int $excludeId = null): bool
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::where($field, $value);

        // 站点过滤
        if ($this->hasSiteId()) {
            $siteId = site_id();
            if ($siteId && !is_super_admin()) {
                $query->where('site_id', $siteId);
            }
        }

        // 排除指定 ID
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    /**
     * 在事务中执行操作
     *
     * @param callable $callback
     * @param string|null $errorMessage
     * @return mixed
     * @throws BusinessException
     */
    protected function transaction(callable $callback, ?string $errorMessage = null): mixed
    {
        Db::beginTransaction();

        try {
            $result = $callback();
            Db::commit();
            return $result;
        } catch (BusinessException $e) {
            Db::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            Db::rollBack();
            $this->logError($errorMessage ?? '事务操作失败', $e);
            throw new BusinessException(
                ErrorCode::SERVER_ERROR,
                $errorMessage ?? '操作失败：' . $e->getMessage()
            );
        }
    }

    /**
     * 过滤可填充字段
     *
     * @param array $data
     * @return array
     */
    protected function filterFillable(array $data): array
    {
        $modelClass = $this->getModelClass();
        $model = new $modelClass();
        $fillable = $model->getFillable();

        if (empty($fillable)) {
            // 如果没有定义 fillable，移除 id 和时间戳字段
            unset($data['id'], $data['created_at'], $data['updated_at']);
            return $data;
        }

        return array_intersect_key($data, array_flip($fillable));
    }

    /**
     * 规范化数据
     *
     * @param array $data
     * @return array
     */
    protected function normalizeData(array $data): array
    {
        foreach ($data as $key => $value) {
            // 空字符串转换为 null
            if ($value === '') {
                $data[$key] = null;
            }
            // 数组转换为 JSON 字符串
            elseif (is_array($value)) {
                $data[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }

        return $data;
    }

    /**
     * 获取模型名称（用于错误信息）
     *
     * @return string
     */
    protected function getModelName(): string
    {
        $modelClass = $this->getModelClass();
        $parts = explode('\\', $modelClass);
        return end($parts);
    }

    /**
     * 记录错误日志
     *
     * @param string $message
     * @param \Throwable $e
     * @return void
     */
    protected function logError(string $message, \Throwable $e): void
    {
        // 记录到 PHP 错误日志
        error_log(sprintf(
            "[%s] %s: %s in %s:%d\n%s",
            date('Y-m-d H:i:s'),
            $message,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }
}
