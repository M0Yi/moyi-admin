<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Exception\ValidationException;
use App\Model\Model;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use function Hyperf\Support\class_uses_recursive;

/**
 * 基于 Hyperf Model 的 CRUD 基类控制器
 *
 * 职责分离设计：
 * - 查询逻辑：getListQuery() 系列方法
 * - 验证逻辑：validateData() 系列方法
 * - 软删除逻辑：trash 系列方法
 * - 模型操作：store/update/destroy 方法
 *
 * 子类只需实现必要的抽象方法即可获得完整 CRUD 功能
 *
 * @package App\Controller\Admin
 */
abstract class BaseModelCrudController extends AbstractController
{
    #[Inject]
    protected ValidatorFactoryInterface $validatorFactory;

    // ============================================
    // 抽象方法（子类必须实现）
    // ============================================

    /**
     * 获取 Model 类名
     */
    abstract protected function getModelClass(): string;

    // ============================================
    // 可选覆盖方法（验证规则）
    // ============================================

    /**
     * 获取验证规则
     *
     * @param string $scene 场景：create 或 update
     * @param int|null $id 记录ID
     * @return array
     */
    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        return [];
    }

    // ============================================
    // 可选覆盖方法（查询构建）
    // ============================================

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
     * 获取每页数量（兼容方法）
     */
    protected function getDefaultPageSize(): int
    {
        return $this->getPageSize();
    }

    // ============================================
    // 可选覆盖方法（字段标签）
    // ============================================

    /**
     * 获取字段标签映射
     */
    protected function getFieldLabels(): array
    {
        return [
            'status' => '状态',
            'visible' => '可见性',
            'status_enabled' => '已启用',
            'status_disabled' => '已禁用',
            'visible_enabled' => '已显示',
            'visible_disabled' => '已隐藏',
        ];
    }

    // ============================================
    // 辅助方法（模型检测）
    // ============================================

    /**
     * 检查模型是否使用软删除
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

    // ============================================
    // 核心方法（列表查询）
    // ============================================

    /**
     * 获取列表查询构建器
     */
    protected function getListQuery()
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query();

        // 站点过滤
        if ($this->hasSiteId() && site_id() && !is_super_admin()) {
            $query->where('site_id', site_id());
        }

        // 软删除过滤
        if ($this->usesSoftDeletes()) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    /**
     * 获取列表数据（API）
     */
    public function listData(RequestInterface $request): ResponseInterface
    {
        try {
            $params = $this->prepareListParams($request);
            $query = $this->getListQuery();

            // 应用搜索
            $this->applyKeywordSearch($query, $params['keyword']);

            // 应用过滤
            $this->applyFilters($query, $params['filters']);

            // 应用排序
            $this->applySorting($query, $params['sort_field'], $params['sort_order']);

            // 分页处理
            $data = $this->paginateQuery($query, $params);

            return $this->success([
                'data' => $data['items'],
                'total' => $data['total'],
                'page' => $data['page'],
                'page_size' => $data['page_size'],
                'last_page' => $data['last_page'],
            ]);
        } catch (\Throwable $e) {
            logger()->error('[BaseModelCrudController] 获取列表数据失败', [
                'model' => $this->getModelClass(),
                'error' => $e->getMessage(),
            ]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 准备列表查询参数
     */
    protected function prepareListParams(RequestInterface $request): array
    {
        return [
            'keyword' => $this->normalizeKeyword($request->input('keyword', '')),
            'filters' => $this->normalizeFilters($request->input('filters', [])),
            'page' => (int)$request->input('page', 1),
            'page_size' => (int)$request->input('page_size', $this->getPageSize()),
            'sort_field' => $request->input('sort_field', $this->getDefaultSortField()),
            'sort_order' => $request->input('sort_order', $this->getDefaultSortOrder()),
        ];
    }

    /**
     * 应用关键词搜索
     */
    protected function applyKeywordSearch($query, string $keyword): void
    {
        if (!empty($keyword)) {
            $searchFields = $this->getSearchableFields();
            if (!empty($searchFields)) {
                $query->where(function ($q) use ($searchFields, $keyword) {
                    foreach ($searchFields as $field) {
                        $q->orWhere($field, 'like', '%' . $keyword . '%');
                    }
                });
            }
        }
    }

    /**
     * 应用过滤条件
     */
    protected function applyFilters($query, array $filters): void
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
                    $query->whereIn($field, $value);
                }
                continue;
            }

            if (str_ends_with($field, '_min')) {
                $baseField = substr($field, 0, -4);
                $query->where($baseField, '>=', $value);
                continue;
            }

            if (str_ends_with($field, '_max')) {
                $baseField = substr($field, 0, -4);
                $query->where($baseField, '<=', $value);
                continue;
            }

            $query->where($field, $value);
        }
    }

    /**
     * 应用排序
     */
    protected function applySorting($query, string $sortField, string $sortOrder): void
    {
        $sortableFields = $this->getSortableFields();
        if (in_array($sortField, $sortableFields, true)) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            $query->orderBy($this->getDefaultSortField(), $this->getDefaultSortOrder());
        }
    }

    /**
     * 分页查询
     */
    protected function paginateQuery($query, array $params): array
    {
        $page = $params['page'];
        $pageSize = $params['page_size'];

        if ($pageSize > 0) {
            $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

            return [
                'items' => $this->formatListData($paginator->items()),
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'page_size' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ];
        }

        $total = $query->count();
        return [
            'items' => $this->formatListData($query->get()->toArray()),
            'total' => $total,
            'page' => 1,
            'page_size' => $total,
            'last_page' => 1,
        ];
    }

    /**
     * 格式化列表数据
     */
    protected function formatListData(array $data): array
    {
        return array_map(function ($item) {
            return is_array($item) ? $item : $item->toArray();
        }, $data);
    }

    // ============================================
    // 核心方法（验证逻辑）
    // ============================================

    /**
     * 验证数据
     */
    protected function validateData(array $data, string $scene, ?int $id = null): void
    {
        $rules = $this->getValidationRules($scene, $id);
        if (empty($rules)) {
            return;
        }

        $validator = $this->validatorFactory->make($data, $rules);
        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $fieldLabels = $this->getFieldLabels();
            $translatedErrors = $this->translateValidationErrors($errors, $fieldLabels);
            throw new ValidationException($translatedErrors, '数据验证失败', $fieldLabels);
        }
    }

    /**
     * 转换验证错误消息
     */
    protected function translateValidationErrors(array $errors, array $fieldLabels): array
    {
        $translated = [];
        foreach ($errors as $field => $fieldErrors) {
            $fieldLabel = $fieldLabels[$field] ?? $field;
            $translated[$field] = [];
            foreach ($fieldErrors as $error) {
                $translated[$field][] = $this->translateErrorMessage($error, $fieldLabel);
            }
        }
        return $translated;
    }

    /**
     * 转换错误消息
     */
    protected function translateErrorMessage(string $error, string $fieldLabel): string
    {
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $error)) {
            return $error;
        }

        if (preg_match('/^The (.+?) (?:field )?is required$/i', $error)) {
            return "{$fieldLabel}不能为空";
        }
        if (preg_match('/^The (.+?) must be (?:a )?valid email address$/i', $error)) {
            return "{$fieldLabel}必须是有效的邮箱地址";
        }
        if (preg_match('/^The (.+?) has already been taken$/i', $error)) {
            return "{$fieldLabel}已存在";
        }
        if (preg_match('/^The (.+?) may not be greater than (\d+)/i', $error, $matches)) {
            return "{$fieldLabel}不能超过{$matches[2]}个字符";
        }
        if (preg_match('/^The (.+?) must be at least (\d+)/i', $error, $matches)) {
            return "{$fieldLabel}至少需要{$matches[2]}个字符";
        }

        return "{$fieldLabel}：" . preg_replace('/^The (.+?) /i', '', $error);
    }

    // ============================================
    // 核心方法（模型操作）
    // ============================================

    /**
     * 查找模型
     */
    protected function findModel(int $id): ?\Hyperf\DbConnection\Model\Model
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query()->where('id', $id);

        if ($this->hasSiteId() && site_id() && !is_super_admin()) {
            $query->where('site_id', site_id());
        }

        // 软删除过滤：与 getListQuery 保持一致
        if ($this->usesSoftDeletes()) {
            $query->whereNull('deleted_at');
        }

        return $query->first();
    }

    /**
     * 创建记录
     */
    protected function storeModel(array $data): Model
    {
        $modelClass = $this->getModelClass();
        $model = new $modelClass();

        // 过滤可填充字段
        $fillable = $model->getFillable();
        if (!empty($fillable)) {
            $data = array_intersect_key($data, array_flip($fillable));
        }

        // 自动填充 site_id
        if ($this->hasSiteId() && site_id() && !isset($data['site_id'])) {
            $data['site_id'] = site_id();
        }

        $model->fill($data);
        $model->save();

        return $model;
    }

    /**
     * 更新记录
     */
    protected function updateModel(Model $model, array $data): Model
    {
        // 过滤可填充字段
        $fillable = $model->getFillable();
        if (!empty($fillable)) {
            $data = array_intersect_key($data, array_flip($fillable));
        }

        // 移除不允许更新的字段
        unset($data['id'], $data['created_at']);

        // 不允许更新 site_id
        if ($this->hasSiteId()) {
            unset($data['site_id']);
        }

        $model->fill($data);
        $model->save();

        return $model;
    }

    /**
     * 删除记录
     */
    protected function deleteModel(Model $model): bool
    {
        if ($this->usesSoftDeletes()) {
            return $model->delete() !== false;
        }
        return $model->forceDelete() !== false;
    }

    // ============================================
    // 辅助方法（参数处理）
    // ============================================

    /**
     * 规范化关键词
     */
    protected function normalizeKeyword($keyword): string
    {
        if (is_array($keyword)) {
            return '';
        }
        return trim((string)$keyword);
    }

    /**
     * 规范化过滤条件
     */
    protected function normalizeFilters($filters): array
    {
        if (is_string($filters) && !empty($filters)) {
            try {
                $decoded = json_decode($filters, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $filters = $decoded;
                } else {
                    $filters = [];
                }
            } catch (\Throwable $e) {
                $filters = [];
            }
        } elseif (!is_array($filters)) {
            $filters = [];
        }

        return array_filter(
            $filters,
            fn($value, $key) => is_string($key) && !str_starts_with($key, '_'),
            ARRAY_FILTER_USE_BOTH
        );
    }

    // ============================================
    // 公开方法（Controller 入口）
    // ============================================

    /**
     * 列表页面
     */
    public function index(RequestInterface $request): ResponseInterface
    {
        if ($request->input('_ajax') === '1') {
            return $this->listData($request);
        }
        return $this->renderListPage($request);
    }

    /**
     * 渲染列表页面
     */
    protected function renderListPage(RequestInterface $request): ResponseInterface
    {
        return $this->error('请实现 renderListPage 方法');
    }

    /**
     * 创建页面
     */
    public function create(RequestInterface $request): ResponseInterface
    {
        return $this->renderCreatePage($request);
    }

    /**
     * 渲染创建页面
     */
    protected function renderCreatePage(RequestInterface $request): ResponseInterface
    {
        return $this->error('请实现 renderCreatePage 方法');
    }

    /**
     * 保存数据
     */
    public function store(RequestInterface $request): ResponseInterface
    {
        try {
            $data = $request->all();
            $this->validateData($data, 'create');
            $model = $this->storeModel($data);
            return $this->success(['id' => $model->id], '创建成功');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), ['errors' => $e->getErrors()], 422);
        } catch (\Throwable $e) {
            logger()->error('[BaseModelCrudController] 创建记录失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 编辑页面
     */
    public function edit(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $model = $this->findModel($id);
            if (!$model) {
                return $this->error('记录不存在', code: 404);
            }
            return $this->renderEditPage($request, $model);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 渲染编辑页面
     */
    protected function renderEditPage(RequestInterface $request, Model $model): ResponseInterface
    {
        return $this->error('请实现 renderEditPage 方法');
    }

    /**
     * 更新数据
     */
    public function update(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $data = $request->all();
            $model = $this->findModel($id);
            if (!$model) {
                return $this->error('记录不存在', code: 404);
            }
            $this->validateData($data, 'update', $id);
            $this->updateModel($model, $data);
            return $this->success([], '更新成功');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), ['errors' => $e->getErrors()], 422);
        } catch (\Throwable $e) {
            logger()->error('[BaseModelCrudController] 更新记录失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除数据
     */
    public function destroy(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $model = $this->findModel($id);
            if (!$model) {
                return $this->error('记录不存在或已被删除', code: 404);
            }
            $this->deleteModel($model);
            $message = $this->usesSoftDeletes() ? '删除成功（已移至回收站）' : '删除成功';
            return $this->success([], $message);
        } catch (\Throwable $e) {
            logger()->error('[BaseModelCrudController] 删除记录失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 批量删除
     */
    public function batchDestroy(RequestInterface $request): ResponseInterface
    {
        try {
            $ids = $request->input('ids', []);
            if (empty($ids) || !is_array($ids)) {
                return $this->error('请选择要删除的记录');
            }

            $ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
            if (empty($ids)) {
                return $this->error('无效的记录ID');
            }

            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->whereIn('id', $ids);

            if ($this->hasSiteId() && site_id() && !is_super_admin()) {
                $query->where('site_id', site_id());
            }

            $count = $this->usesSoftDeletes()
                ? $query->delete()
                : $query->forceDelete();

            $message = $this->usesSoftDeletes()
                ? "成功删除 {$count} 条记录（已移至回收站）"
                : "成功删除 {$count} 条记录";

            return $this->success(['count' => $count], $message);
        } catch (\Throwable $e) {
            logger()->error('[BaseModelCrudController] 批量删除失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 切换字段值
     */
    public function toggleStatus(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $field = $request->input('field', 'status');
            if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $field)) {
                return $this->error('无效的字段名', code: 400);
            }

            $model = $this->findModel($id);
            if (!$model) {
                return $this->error('记录不存在', code: 404);
            }

            $currentValue = $model->{$field} ?? 0;
            $newValue = $currentValue == 1 ? 0 : 1;
            $model->{$field} = $newValue;
            $model->save();

            $fieldLabels = $this->getFieldLabels();
            $fieldLabel = $fieldLabels[$field] ?? $field;
            $message = $newValue == 1
                ? ($fieldLabels[$field . '_enabled'] ?? "{$fieldLabel}已启用")
                : ($fieldLabels[$field . '_disabled'] ?? "{$fieldLabel}已禁用");

            return $this->success([$field => $newValue], $message);
        } catch (\Throwable $e) {
            logger()->error('[BaseModelCrudController] 切换字段值失败', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // ============================================
    // 软删除方法（回收站）
    // ============================================

    /**
     * 回收站页面
     */
    public function trash(RequestInterface $request): ResponseInterface
    {
        if (!$this->usesSoftDeletes()) {
            return $this->error('该模型未启用软删除功能');
        }

        if ($request->input('_ajax') === '1') {
            return $this->trashData($request);
        }

        return $this->renderTrashPage($request);
    }

    /**
     * 渲染回收站页面
     */
    protected function renderTrashPage(RequestInterface $request): ResponseInterface
    {
        return $this->error('请实现 renderTrashPage 方法');
    }

    /**
     * 获取回收站数据
     */
    public function trashData(RequestInterface $request): ResponseInterface
    {
        try {
            $params = $this->prepareListParams($request);
            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->onlyTrashed();

            if ($this->hasSiteId() && site_id() && !is_super_admin()) {
                $query->where('site_id', site_id());
            }

            $this->applyKeywordSearch($query, $params['keyword']);
            $this->applyFilters($query, $params['filters']);
            $query->orderBy('deleted_at', 'desc');

            $page = $params['page'];
            $pageSize = $params['page_size'];

            if ($pageSize > 0) {
                $paginator = $query->paginate($pageSize, ['*'], 'page', $page);
                return $this->success([
                    'data' => $paginator->items(),
                    'total' => $paginator->total(),
                    'page' => $paginator->currentPage(),
                    'page_size' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                ]);
            }

            $total = $query->count();
            return $this->success([
                'data' => $query->get()->toArray(),
                'total' => $total,
                'page' => 1,
                'page_size' => $total,
                'last_page' => 1,
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 恢复记录
     */
    public function restore(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->onlyTrashed()->where('id', $id);

            if ($this->hasSiteId() && site_id() && !is_super_admin()) {
                $query->where('site_id', site_id());
            }

            $model = $query->first();
            if (!$model) {
                return $this->error('记录不存在或未被删除', code: 404);
            }

            $model->restore();
            return $this->success([], '恢复成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 永久删除
     */
    public function forceDelete(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->onlyTrashed()->where('id', $id);

            if ($this->hasSiteId() && site_id() && !is_super_admin()) {
                $query->where('site_id', site_id());
            }

            $model = $query->first();
            if (!$model) {
                return $this->error('记录不存在或未被删除', code: 404);
            }

            $model->forceDelete();
            return $this->success([], '永久删除成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 批量恢复
     */
    public function batchRestore(RequestInterface $request): ResponseInterface
    {
        try {
            $ids = $request->input('ids', []);
            if (empty($ids) || !is_array($ids)) {
                return $this->error('请选择要恢复的记录');
            }

            $ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
            if (empty($ids)) {
                return $this->error('无效的记录ID');
            }

            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->onlyTrashed()->whereIn('id', $ids);

            if ($this->hasSiteId() && site_id() && !is_super_admin()) {
                $query->where('site_id', site_id());
            }

            $count = $query->restore();
            return $this->success(['count' => $count], "成功恢复 {$count} 条记录");
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 批量永久删除
     */
    public function batchForceDelete(RequestInterface $request): ResponseInterface
    {
        try {
            $ids = $request->input('ids', []);
            if (empty($ids) || !is_array($ids)) {
                return $this->error('请选择要永久删除的记录');
            }

            $ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
            if (empty($ids)) {
                return $this->error('无效的记录ID');
            }

            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->onlyTrashed()->whereIn('id', $ids);

            if ($this->hasSiteId() && site_id() && !is_super_admin()) {
                $query->where('site_id', site_id());
            }

            $count = $query->forceDelete();
            return $this->success(['count' => $count], "成功永久删除 {$count} 条记录");
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 清空回收站
     */
    public function clearTrash(RequestInterface $request): ResponseInterface
    {
        try {
            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->onlyTrashed();

            if ($this->hasSiteId() && site_id() && !is_super_admin()) {
                $query->where('site_id', site_id());
            }

            $count = $query->forceDelete();

            if ($count === 0) {
                return $this->success(['count' => 0], '回收站已经是空的');
            }

            return $this->success(['count' => $count], "成功清空回收站，永久删除 {$count} 条记录");
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    // ============================================
    // 导入导出功能
    // ============================================

    /**
     * 导出数据
     */
    public function export(RequestInterface $request): ResponseInterface
    {
        try {
            $params = $request->all();
            $query = $this->getListQuery();

            // 应用搜索
            $this->applyKeywordSearch($query, $params['keyword'] ?? '');

            // 应用过滤
            $this->applyFilters($query, $this->normalizeFilters($params['filters'] ?? []));

            // 排序
            $sortField = $params['sort_field'] ?? $this->getDefaultSortField();
            $sortOrder = $params['sort_order'] ?? $this->getDefaultSortOrder();
            $this->applySorting($query, $sortField, $sortOrder);

            // 获取导出字段（子类可覆盖）
            $exportFields = $this->getExportFields();
            $data = $query->get()->toArray();

            // 格式化导出数据
            $exportData = $this->formatExportData($data, $exportFields);

            $filename = $this->getExportFilename();
            return $this->exportToCsv($exportData, $filename);
        } catch (\Exception $e) {
            return $this->error('导出失败：' . $e->getMessage());
        }
    }

    /**
     * 下载导入模板
     */
    public function exportTemplate(RequestInterface $request): ResponseInterface
    {
        try {
            $templateData = $this->getImportTemplate();
            $filename = $this->getExportFilename() . '_template';
            return $this->exportToCsv($templateData, $filename);
        } catch (\Exception $e) {
            return $this->error('模板生成失败：' . $e->getMessage());
        }
    }

    /**
     * 导入数据
     */
    public function import(RequestInterface $request): ResponseInterface
    {
        try {
            $file = $request->file('file');
            if (!$file) {
                return $this->error('请上传文件');
            }

            $extension = strtolower($file->getExtension());
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return $this->error('仅支持 xlsx、xls、csv 格式文件');
            }

            if ($file->getSize() > 10 * 1024 * 1024) { // 10MB
                return $this->error('文件大小不能超过 10MB');
            }

            $tempPath = $file->getRealPath();
            $data = $this->readImportFile($extension, $tempPath);

            if (empty($data)) {
                return $this->error('文件内容为空');
            }

            $headers = array_shift($data);
            if (!$this->validateImportHeaders($headers)) {
                return $this->error('文件格式不正确，请下载模板后按模板格式填写');
            }

            $result = $this->processImportData($data, $headers);

            return $this->success([
                'created' => $result['created'],
                'updated' => $result['updated'],
                'failed' => $result['failed'],
                'errors' => array_slice($result['errors'], 0, 10),
            ], sprintf('导入完成：新增 %d 条，更新 %d 条，失败 %d 条', $result['created'], $result['updated'], $result['failed']));
        } catch (\Exception $e) {
            return $this->error('导入失败：' . $e->getMessage());
        }
    }

    /**
     * 获取导出字段（子类可覆盖）
     */
    protected function getExportFields(): array
    {
        $modelClass = $this->getModelClass();
        $model = new $modelClass();
        $fillable = $model->getFillable();

        // 默认导出字段：id + fillable
        return array_merge(['id'], $fillable);
    }

    /**
     * 获取导出文件名
     */
    protected function getExportFilename(): string
    {
        $modelClass = $this->getModelClass();
        $shortName = (new \ReflectionClass($modelClass))->getShortName();
        return strtolower($shortName) . '_' . date('YmdHis');
    }

    /**
     * 格式化导出数据
     */
    protected function formatExportData(array $data, array $fields): array
    {
        return array_map(function ($item) use ($fields) {
            $row = [];
            foreach ($fields as $field) {
                $row[$field] = $item[$field] ?? '';
            }
            return $row;
        }, $data);
    }

    /**
     * 获取导入模板数据
     */
    protected function getImportTemplate(): array
    {
        $fields = $this->getExportFields();
        $headers = array_map(function ($field) {
            return match ($field) {
                'id' => 'ID',
                'name' => '名称',
                'title' => '标题',
                'slug' => '标识',
                'description' => '描述',
                'content' => '内容',
                'status' => '状态',
                'sort_order' => '排序',
                'is_active' => '是否启用',
                'type' => '类型',
                'url' => '链接',
                'position' => '位置',
                'parent_id' => '父级ID',
                'category_id' => '分类ID',
                'author' => '作者',
                default => $field,
            };
        }, $fields);

        // 添加一行示例数据
        $exampleData = [];
        foreach ($fields as $field) {
            $exampleData[$field] = match ($field) {
                'id' => '',
                'status' => 1,
                'is_active' => '是',
                'sort_order' => 0,
                default => '',
            };
        }

        return array_merge([$headers], [$exampleData]);
    }

    /**
     * 验证导入文件表头
     */
    protected function validateImportHeaders(array $headers): bool
    {
        // 默认验证：至少包含 ID 和一个其他字段
        return in_array('ID', $headers) || in_array('名称', $headers) || in_array('标题', $headers);
    }

    /**
     * 处理导入数据（子类可覆盖）
     */
    protected function processImportData(array $data, array $headers): array
    {
        $success = 0;
        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        // 获取导入字段映射
        $fieldMap = $this->getImportFieldMap($headers);
        if (empty($fieldMap)) {
            return ['created' => 0, 'updated' => 0, 'failed' => count($data), 'errors' => ['无法识别表头字段']];
        }

        $modelClass = $this->getModelClass();
        $model = new $modelClass();
        $fillable = $model->getFillable();

        foreach ($data as $rowIndex => $row) {
            try {
                // 跳过空行
                if (empty(array_filter($row))) {
                    continue;
                }

                // 构建数据
                $rowData = [];
                foreach ($fieldMap as $header => $field) {
                    $value = $row[array_search($header, $headers)] ?? '';
                    $rowData[$field] = $this->convertImportValue($field, $value);
                }

                // 过滤不可填充字段
                $rowData = array_intersect_key($rowData, array_flip($fillable));

                // 移除 id 和时间戳（让数据库自动生成）
                unset($rowData['id'], $rowData['created_at'], $rowData['updated_at']);

                // 根据 ID 判断新增或更新
                $id = $rowData['id'] ?? null;
                unset($rowData['id']);

                if (!empty($id)) {
                    $existing = $modelClass::find($id);
                    if ($existing) {
                        $existing->fill($rowData);
                        $existing->save();
                        $updated++;
                        $success++;
                        continue;
                    }
                }

                // 创建新记录
                $modelClass::create($rowData);
                $created++;
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "第" . ($rowIndex + 2) . "行：" . $e->getMessage();
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * 获取导入字段映射
     */
    protected function getImportFieldMap(array $headers): array
    {
        $map = [];
        $fieldLabels = [
            'ID' => 'id',
            '名称' => 'name',
            '标题' => 'title',
            '标识' => 'slug',
            '描述' => 'description',
            '内容' => 'content',
            '状态' => 'status',
            '排序' => 'sort_order',
            '是否启用' => 'is_active',
            '类型' => 'type',
            '链接' => 'url',
            '位置' => 'position',
            '父级ID' => 'parent_id',
            '分类ID' => 'category_id',
            '作者' => 'author',
        ];

        foreach ($headers as $header) {
            if (isset($fieldLabels[$header])) {
                $map[$header] = $fieldLabels[$header];
            }
        }

        return $map;
    }

    /**
     * 转换导入值
     */
    protected function convertImportValue(string $field, mixed $value): mixed
    {
        // 处理布尔值
        if (in_array($field, ['status', 'is_active', 'is_featured', 'is_pinned', 'visible'])) {
            if ($value === '是' || $value === 'true' || $value === '1') {
                return 1;
            }
            if ($value === '否' || $value === 'false' || $value === '0') {
                return 0;
            }
            return (int) $value;
        }

        // 处理整数
        if (in_array($field, ['sort_order', 'view_count', 'parent_id', 'category_id'])) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * 读取导入文件
     */
    private function readImportFile(string $extension, string $tempPath): array
    {
        $data = [];

        if (in_array($extension, ['xlsx', 'xls'])) {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempPath);
            $spreadsheet = $reader->load($tempPath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();
        } elseif ($extension === 'csv') {
            $content = file_get_contents($tempPath);
            // 移除 BOM
            if (str_starts_with($content, "\xef\xbb\xbf")) {
                $content = substr($content, 3);
            }
            // 编码转换
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8,GBK,GB2312');

            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line) {
                    $data[] = str_getcsv($line);
                }
            }
        }

        return $data;
    }

    /**
     * 导出为 CSV
     */
    private function exportToCsv(array $data, string $filename): ResponseInterface
    {
        if (empty($data)) {
            $data = [['无数据']];
        }

        $content = "\xef\xbb\xbf";
        foreach ($data as $line) {
            $content .= implode(',', array_map(function ($item) {
                return '"' . str_replace('"', '""', (string) $item) . '"';
            }, $line)) . "\n";
        }

        return $this->response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', "attachment; filename={$filename}.csv")
            ->withHeader('Content-Length', (string) strlen($content))
            ->withBody(new SwooleStream($content));
    }
}
