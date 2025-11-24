<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Exception\ValidationException;
use App\Model\Model;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 基于 Hyperf Model 的 CRUD 基类控制器
 *
 * 适用于使用 Eloquent Model 的场景，提供完整的 CRUD 功能
 * 子类需要指定具体的 Model 类，并可以重写方法以实现自定义逻辑
 *
 * 功能特性：
 * - 列表查询（支持搜索、过滤、排序、分页）
 * - 创建记录
 * - 编辑记录
 * - 删除记录（支持软删除和硬删除）
 * - 切换字段值（如状态切换）
 * - 数据导出
 * - 回收站管理（如果启用软删除）
 * - 数据验证
 * - 站点过滤（如果模型有 site_id 字段）
 *
 * 使用示例：
 * ```php
 * class UserController extends BaseModelCrudController
 * {
 *     protected function getModelClass(): string
 *     {
 *         return AdminUser::class;
 *     }
 *
 *     protected function getValidationRules(string $scene, ?int $id = null): array
 *     {
 *         return [
 *             'create' => [
 *                 'username' => 'required|string|max:50|unique:admin_users',
 *                 'email' => 'required|email|unique:admin_users',
 *             ],
 *             'update' => [
 *                 'username' => 'required|string|max:50|unique:admin_users,username,' . $id,
 *                 'email' => 'required|email|unique:admin_users,email,' . $id,
 *             ],
 *         ][$scene] ?? [];
 *     }
 * }
 * ```
 *
 * @package App\Controller\Admin
 */
abstract class BaseModelCrudController extends AbstractController
{
    #[Inject]
    protected ValidatorFactoryInterface $validatorFactory;

    /**
     * 获取 Model 类名
     * 子类必须实现此方法，返回对应的 Model 类名
     *
     * @return string Model 类名，例如：AdminUser::class
     */
    abstract protected function getModelClass(): string;

    /**
     * 获取验证规则
     * 子类可以重写此方法以自定义验证规则
     *
     * @param string $scene 场景：create 或 update
     * @param int|null $id 记录ID（用于 update 场景的唯一性验证）
     * @return array 验证规则数组
     */
    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        return [];
    }

    /**
     * 获取列表查询构建器
     * 子类可以重写此方法以自定义查询逻辑（如添加关联、作用域等）
     *
     * @return \Hyperf\Database\Model\Builder 查询构建器
     */
    protected function getListQuery()
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query();

        // 自动添加站点过滤（如果模型有 site_id 字段且不是超级管理员）
        if ($this->hasSiteId() && site_id() && !is_super_admin()) {
            $query->where('site_id', site_id());
        }

        // 自动过滤软删除记录（如果模型使用 SoftDeletes）
        if ($this->usesSoftDeletes()) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    /**
     * 获取可搜索字段列表
     * 子类可以重写此方法以指定可搜索的字段
     *
     * @return array 字段名数组，例如：['username', 'email', 'real_name']
     */
    protected function getSearchableFields(): array
    {
        return ['id'];
    }

    /**
     * 获取可排序字段列表
     * 子类可以重写此方法以指定可排序的字段
     *
     * @return array 字段名数组，例如：['id', 'created_at', 'updated_at']
     */
    protected function getSortableFields(): array
    {
        return ['id', 'created_at', 'updated_at'];
    }

    /**
     * 获取默认排序字段
     *
     * @return string 字段名
     */
    protected function getDefaultSortField(): string
    {
        return 'id';
    }

    /**
     * 获取默认排序方向
     *
     * @return string asc 或 desc
     */
    protected function getDefaultSortOrder(): string
    {
        return 'desc';
    }

    /**
     * 获取每页数量
     *
     * @return int
     */
    protected function getPageSize(): int
    {
        return 15;
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
        $guarded = $model->getGuarded();

        // 检查 fillable 中是否有 site_id
        if (in_array('site_id', $fillable, true)) {
            return true;
        }

        // 如果 guarded 为空，说明所有字段都可填充，检查表结构
        if (empty($guarded) || $guarded === ['*']) {
            // 尝试查询表结构（简单检查，不实际查询数据库）
            return true; // 默认假设有 site_id（大多数表都有）
        }

        return false;
    }

    /**
     * 列表页面
     */
    public function index(RequestInterface $request): ResponseInterface
    {
        // 判断是否是 API 请求
        if ($request->input('_ajax') === '1') {
            return $this->listData($request);
        }

        // 返回列表页面（子类需要实现视图）
        return $this->renderListPage($request);
    }

    /**
     * 渲染列表页面
     * 子类可以重写此方法以自定义视图
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    protected function renderListPage(RequestInterface $request): ResponseInterface
    {
        // 默认实现：返回 JSON 提示需要实现视图
        return $this->error('请实现 renderListPage 方法或创建对应的视图');
    }

    /**
     * 获取列表数据（API）
     */
    public function listData(RequestInterface $request): ResponseInterface
    {
        try {
            // 获取查询参数
            $keyword = $this->normalizeKeyword($request->input('keyword', ''));
            $filters = $this->normalizeFilters($request->input('filters', []));
            $page = (int)$request->input('page', 1);
            $pageSize = (int)$request->input('page_size', $this->getPageSize());
            $sortField = $request->input('sort_field', $this->getDefaultSortField());
            $sortOrder = $request->input('sort_order', $this->getDefaultSortOrder());

            // 构建查询
            $query = $this->getListQuery();

            // 关键词搜索
            if (!empty($keyword)) {
                $searchFields = $this->getSearchableFields();
                $query->where(function ($q) use ($searchFields, $keyword) {
                    foreach ($searchFields as $field) {
                        $q->orWhere($field, 'like', '%' . $keyword . '%');
                    }
                });
            }

            // 应用过滤条件
            if (!empty($filters) && is_array($filters)) {
                $this->applyFilters($query, $filters);
            }

            // 排序
            $sortableFields = $this->getSortableFields();
            if (in_array($sortField, $sortableFields, true)) {
                $query->orderBy($sortField, $sortOrder);
            } else {
                $query->orderBy($this->getDefaultSortField(), $this->getDefaultSortOrder());
            }

            // 分页
            $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

            return $this->success([
                'data' => $paginator->items(),
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'page_size' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ]);
        } catch (\Throwable $e) {
            logger()->error('[BaseModelCrudController] 获取列表数据失败', [
                'model' => $this->getModelClass(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 应用过滤条件
     * 子类可以重写此方法以自定义过滤逻辑
     *
     * @param \Hyperf\Database\Model\Builder $query 查询构建器
     * @param array $filters 过滤条件数组
     */
    protected function applyFilters($query, array $filters): void
    {
        foreach ($filters as $field => $value) {
            // 跳过空值
            if ($value === '' || $value === null) {
                continue;
            }

            // 跳过系统保留字段
            if (str_starts_with($field, '_')) {
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

            // 处理区间字段（_min 和 _max 后缀）
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

            // 默认：精确匹配
            $query->where($field, $value);
        }
    }

    /**
     * 创建页面
     * 子类可以重写此方法以自定义创建页面
     */
    public function create(RequestInterface $request): ResponseInterface
    {
        return $this->renderCreatePage($request);
    }

    /**
     * 渲染创建页面
     * 子类可以重写此方法以自定义视图
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    protected function renderCreatePage(RequestInterface $request): ResponseInterface
    {
        return $this->error('请实现 renderCreatePage 方法或创建对应的视图');
    }

    /**
     * 保存数据
     */
    public function store(RequestInterface $request): ResponseInterface
    {
        try {
            $data = $request->all();

            // 数据验证
            $this->validateData($data, 'create');

            // 创建记录
            $modelClass = $this->getModelClass();
            $model = new $modelClass();

            // 过滤可填充字段
            $fillable = $model->getFillable();
            if (!empty($fillable)) {
                $data = array_intersect_key($data, array_flip($fillable));
            }

            // 自动填充 site_id（如果模型有该字段且当前有站点ID）
            if ($this->hasSiteId() && site_id() && !isset($data['site_id'])) {
                $data['site_id'] = site_id();
            }

            // 填充数据
            $model->fill($data);
            $model->save();

            return $this->success(['id' => $model->id], '创建成功');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), [
                'errors' => $e->getErrors(),
            ], 422);
        } catch (\Throwable $e) {
            logger()->error('[BaseModelCrudController] 创建记录失败', [
                'model' => $this->getModelClass(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 编辑页面
     * 子类可以重写此方法以自定义编辑页面
     */
    public function edit(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $modelClass = $this->getModelClass();
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
     * 子类可以重写此方法以自定义视图
     *
     * @param RequestInterface $request
     * @param Model $model 模型实例
     * @return ResponseInterface
     */
    protected function renderEditPage(RequestInterface $request, Model $model): ResponseInterface
    {
        return $this->error('请实现 renderEditPage 方法或创建对应的视图');
    }

    /**
     * 更新数据
     */
    public function update(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $data = $request->all();

            // 查找记录
            $model = $this->findModel($id);
            if (!$model) {
                return $this->error('记录不存在', code: 404);
            }

            // 数据验证
            $this->validateData($data, 'update', $id);

            // 过滤可填充字段
            $fillable = $model->getFillable();
            if (!empty($fillable)) {
                $data = array_intersect_key($data, array_flip($fillable));
            }

            // 移除不允许更新的字段
            unset($data['id'], $data['created_at']);

            // 如果模型有 site_id，不允许更新
            if ($this->hasSiteId()) {
                unset($data['site_id']);
            }

            // 更新数据
            $model->fill($data);
            $model->save();

            return $this->success([], '更新成功');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), [
                'errors' => $e->getErrors(),
            ], 422);
        } catch (\Throwable $e) {
            logger()->error('[BaseModelCrudController] 更新记录失败', [
                'model' => $this->getModelClass(),
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除数据
     * 支持软删除和硬删除（根据模型是否使用 SoftDeletes trait）
     */
    public function destroy(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $model = $this->findModel($id);
            if (!$model) {
                return $this->error('记录不存在或已被删除', code: 404);
            }

            // 如果模型使用软删除，执行软删除
            if ($this->usesSoftDeletes()) {
                $model->delete(); // Eloquent 会自动处理软删除
                $message = '删除成功（已移至回收站）';
            } else {
                $model->forceDelete(); // 硬删除
                $message = '删除成功';
            }

            return $this->success([], $message);
        } catch (\Throwable $e) {
            logger()->error('[BaseModelCrudController] 删除记录失败', [
                'model' => $this->getModelClass(),
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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

            // 验证 ID
            $ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
            if (empty($ids)) {
                return $this->error('无效的记录ID');
            }

            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->whereIn('id', $ids);

            // 站点过滤
            if ($this->hasSiteId() && site_id() && !is_super_admin()) {
                $query->where('site_id', site_id());
            }

            $count = 0;
            if ($this->usesSoftDeletes()) {
                $count = $query->delete(); // 软删除
                $message = "成功删除 {$count} 条记录（已移至回收站）";
            } else {
                $count = $query->forceDelete(); // 硬删除
                $message = "成功删除 {$count} 条记录";
            }

            return $this->success(['count' => $count], $message);
        } catch (\Throwable $e) {
            logger()->error('[BaseModelCrudController] 批量删除失败', [
                'model' => $this->getModelClass(),
                'error' => $e->getMessage(),
            ]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 切换字段值（通用方法）
     * 通过 field 参数指定要切换的字段，默认切换 status 字段
     */
    public function toggleStatus(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $field = $request->input('field', 'status');

            // 验证字段名
            if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $field)) {
                return $this->error('无效的字段名', code: 400);
            }

            $model = $this->findModel($id);
            if (!$model) {
                return $this->error('记录不存在', code: 404);
            }

            // 切换字段值（0/1 切换）
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
            logger()->error('[BaseModelCrudController] 切换字段值失败', [
                'model' => $this->getModelClass(),
                'id' => $id,
                'field' => $field ?? 'status',
                'error' => $e->getMessage(),
            ]);
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取字段标签映射
     * 子类可以重写此方法以自定义字段标签
     *
     * @return array 字段标签映射
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

    /**
     * 查找模型实例
     *
     * @param int $id 记录ID
     * @return Model|null
     */
    protected function findModel(int $id): ?Model
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query()->where('id', $id);

        // 站点过滤
        if ($this->hasSiteId() && site_id() && !is_super_admin()) {
            $query->where('site_id', site_id());
        }

        return $query->first();
    }

    /**
     * 验证数据
     *
     * @param array $data 数据数组
     * @param string $scene 场景：create 或 update
     * @param int|null $id 记录ID（用于 update 场景）
     * @throws ValidationException
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
     * 转换验证错误消息为中文友好的格式
     *
     * @param array $errors 原始错误信息
     * @param array $fieldLabels 字段标签映射
     * @return array 转换后的错误信息
     */
    protected function translateValidationErrors(array $errors, array $fieldLabels): array
    {
        $translated = [];
        foreach ($errors as $field => $fieldErrors) {
            $fieldLabel = $fieldLabels[$field] ?? $field;
            $translated[$field] = [];
            foreach ($fieldErrors as $error) {
                $translatedError = $this->translateErrorMessage($error, $fieldLabel);
                $translated[$field][] = $translatedError;
            }
        }
        return $translated;
    }

    /**
     * 转换单个错误消息
     *
     * @param string $error 原始错误消息
     * @param string $fieldLabel 字段标签
     * @return string 转换后的错误消息
     */
    protected function translateErrorMessage(string $error, string $fieldLabel): string
    {
        // 如果已经是中文消息，直接返回
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $error)) {
            return $error;
        }

        // 处理常见的验证错误消息
        if (preg_match('/^The (.+?) (?:field )?is required$/i', $error)) {
            return "{$fieldLabel}不能为空";
        }
        if (preg_match('/^The (.+?) must be (?:a )?valid email address$/i', $error)) {
            return "{$fieldLabel}必须是有效的邮箱地址";
        }
        if (preg_match('/^The (.+?) has already been taken$/i', $error)) {
            return "{$fieldLabel}已存在，请使用其他值";
        }
        if (preg_match('/^The (.+?) may not be greater than (\d+)/i', $error, $matches)) {
            return "{$fieldLabel}不能超过{$matches[2]}个字符";
        }
        if (preg_match('/^The (.+?) must be at least (\d+)/i', $error, $matches)) {
            return "{$fieldLabel}至少需要{$matches[2]}个字符";
        }

        // 默认：返回字段标签 + 原始错误消息
        $cleaned = preg_replace('/^The (.+?) /i', '', $error);
        return "{$fieldLabel}：{$cleaned}";
    }

    /**
     * 规范化关键词
     *
     * @param mixed $keyword
     * @return string
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
     *
     * @param mixed $filters
     * @return array
     */
    protected function normalizeFilters($filters): array
    {
        // 如果是 JSON 字符串，解析它
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

        // 移除系统保留参数
        return array_filter(
            $filters,
            fn($value, $key) => is_string($key) && !str_starts_with($key, '_'),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * 回收站页面（如果启用软删除）
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
     * 子类可以重写此方法以自定义视图
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    protected function renderTrashPage(RequestInterface $request): ResponseInterface
    {
        return $this->error('请实现 renderTrashPage 方法或创建对应的视图');
    }

    /**
     * 获取回收站数据（只查询已删除的记录）
     */
    public function trashData(RequestInterface $request): ResponseInterface
    {
        try {
            $keyword = $this->normalizeKeyword($request->input('keyword', ''));
            $filters = $this->normalizeFilters($request->input('filters', []));
            $page = (int)$request->input('page', 1);
            $pageSize = (int)$request->input('page_size', $this->getPageSize());
            $sortField = $request->input('sort_field', 'deleted_at');
            $sortOrder = $request->input('sort_order', 'desc');

            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->onlyTrashed(); // 只查询已删除的记录

            // 站点过滤
            if ($this->hasSiteId() && site_id() && !is_super_admin()) {
                $query->where('site_id', site_id());
            }

            // 关键词搜索
            if (!empty($keyword)) {
                $searchFields = $this->getSearchableFields();
                $query->where(function ($q) use ($searchFields, $keyword) {
                    foreach ($searchFields as $field) {
                        $q->orWhere($field, 'like', '%' . $keyword . '%');
                    }
                });
            }

            // 应用过滤条件
            if (!empty($filters)) {
                $this->applyFilters($query, $filters);
            }

            // 排序
            $query->orderBy($sortField, $sortOrder);

            // 分页
            $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

            return $this->success([
                'data' => $paginator->items(),
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'page_size' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
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
            if (!$this->usesSoftDeletes()) {
                return $this->error('该模型未启用软删除功能');
            }

            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->onlyTrashed()->where('id', $id);

            // 站点过滤
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
     * 永久删除记录
     */
    public function forceDelete(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            if (!$this->usesSoftDeletes()) {
                return $this->error('该模型未启用软删除功能');
            }

            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->onlyTrashed()->where('id', $id);

            // 站点过滤
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
            if (!$this->usesSoftDeletes()) {
                return $this->error('该模型未启用软删除功能');
            }

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

            // 站点过滤
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
            if (!$this->usesSoftDeletes()) {
                return $this->error('该模型未启用软删除功能');
            }

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

            // 站点过滤
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
            if (!$this->usesSoftDeletes()) {
                return $this->error('该模型未启用软删除功能');
            }

            $modelClass = $this->getModelClass();
            $query = $modelClass::query()->onlyTrashed();

            // 站点过滤
            if ($this->hasSiteId() && site_id() && !is_super_admin()) {
                $query->where('site_id', site_id());
            }

            $count = $query->forceDelete();

            if ($count === 0) {
                return $this->success(['count' => 0], '回收站已经是空的，没有记录需要删除');
            }

            return $this->success(['count' => $count], "成功清空回收站，共永久删除 {$count} 条记录");
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}

