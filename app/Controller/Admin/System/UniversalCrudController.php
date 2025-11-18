<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\AbstractController;
use App\Service\Admin\UniversalCrudService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * 通用 CRUD 控制器
 *
 * 通过路由参数 {model} 动态操作不同的模型
 * 无需为每个模型单独创建 Controller、Service、View
 *
 * 路由参数说明：
 * - {model} 应该传入模型名（model_name），例如：AdminUser、AdminRole
 * - 也兼容 route_slug、table_name 和 ID（向后兼容）
 * - 推荐使用 model_name，这样路由更清晰、更语义化
 *
 * 支持两种路径格式：
 * 1. 短路径（推荐）：/admin/{adminPath}/u/{model}
 *    - 例如：/admin/admin/u/AdminUser -> 管理 AdminUser 模型
 * 2. 完整路径（向后兼容）：/admin/{adminPath}/universal/{model}
 *    - 例如：/admin/admin/universal/AdminUser -> 管理 AdminUser 模型
 *
 * @package App\Controller\Admin\System
 */
class UniversalCrudController extends AbstractController
{
    #[Inject]
    protected UniversalCrudService $service;

    #[Inject]
    protected ValidatorFactoryInterface $validatorFactory;

    /**
     * 列表页面
     */
    public function index(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');

         print_r(["model>>>"=>$this->request->getMethod()]);
        // 验证模型
//        if (!$this->service->isAllowedModel($model)) {
//            return $this->error('不允许访问的模型');
//        }

        // 获取配置
        $config = $this->service->getModelConfig($model);

        // 判断是否是 API 请求
        if ($request->input('_ajax') === '1') {
            return $this->listData($request, $model);
        }

        // 获取表列配置
        $tableColumns = $this->service->getTableColumns($model);

        // 转换为组件需要的格式
        $columns = $this->convertToComponentColumns($tableColumns, $model);

        // 获取关联配置，传递给视图
        $relations = $config['relations'] ?? [];

        // 返回列表页面
        return $this->render->render('admin.system.universal.index', [
            'model' => $model,
            'config' => $config,
            'columns' => $columns,
            'relations' => $relations,
            'data' => [],  // 初始为空，通过 AJAX 加载
        ]);
    }

    /**
     * 将字段配置转换为组件需要的列配置格式
     */
    protected function convertToComponentColumns(array $tableColumns, string $model): array
    {
        $columns = [];
        $index = 0;

        foreach ($tableColumns as $column) {
            $name = $column['name'];
            $label = $column['label'] ?? $name;
            $dbType = $column['type'] ?? 'string';

            // 检查是否可列出（只显示 listable=true 的字段）
            $listable = $column['listable'] ?? $column['show_in_list'] ?? true;
            if (!$listable) {
                continue; // 跳过不可列出的字段，不占据表头位置
            }

            // 确定列默认显示状态
            $visible = $column['list_default'] ?? $column['list_show'] ?? true;

            // 优先使用 column_type（列类型），如果没有则根据 form_type 或数据库类型推断
            $columnType = $column['column_type'] ?? $column['render_type'] ?? null;
            
            if ($columnType) {
                // 如果明确指定了列类型，直接使用
                $type = $columnType;
            } else {
                // 否则根据 form_type 或数据库类型推断
                $formType = $column['form_type'] ?? null;
                $type = $this->convertDbTypeToColumnType($dbType, $name, $formType);
                
                // 如果是 relation 类型，设置为 relation 列类型（显示关联名称）
                if ($formType === 'relation') {
                    $type = 'relation';
                }
            }

            // 构建列配置
            $columnConfig = [
                'index' => $index++,
                'label' => $label,
                'field' => $name,
                'name' => $name,  // 添加 name 字段，方便视图查找
                'type' => $type,
                'visible' => $visible,
                'sortable' => $column['sortable'] ?? false,  // 是否支持排序
                'db_type' => $dbType,  // 添加数据库类型
                'form_type' => $formType,  // 添加表单类型
            ];
            
            // 如果是 relation 类型，添加关联配置信息
            if ($type === 'relation') {
                // 获取关联配置（从列配置中提取，或从 config 中获取）
                $relationConfig = null;
                if (isset($column['relation_table'])) {
                    $relationConfig = [
                        'table' => $column['relation_table'] ?? '',
                        'label_field' => $column['relation_label_field'] ?? $column['relation_display_field'] ?? 'name',
                        'value_field' => $column['relation_value_field'] ?? 'id',
                        'multiple' => str_ends_with($name, '_ids') || 
                                     ($column['relation_multiple'] ?? false) ||
                                     ($column['model_type'] ?? '') === 'array',
                    ];
                }
                
                if ($relationConfig) {
                    $columnConfig['relation'] = $relationConfig;
                    // 设置显示字段为 label 字段（用于前端显示）
                    $columnConfig['labelField'] = "{$name}_label";
                }
            }

            // 如果是 badge 类型，构建 badgeMap
            // 1. 如果 form_type 是 radio/select 且有 options，使用配置的 options
            // 2. 如果类型是 badge 但没有 options，尝试使用默认 options（特别是 status 字段）
            if ($type === 'badge' || $formType === 'radio' || $formType === 'select') {
                $options = null;
                
                // 优先使用配置的 options
                if (isset($column['options']) && !empty($column['options'])) {
                    $options = $column['options'];
                } elseif ($name === 'status') {
                    // status 字段默认使用标准选项
                    $options = [
                        '0' => '禁用',
                        '1' => '启用',
                    ];
                }
                
                // 如果有 options，构建 badgeMap
                if ($options) {
                    $badgeMap = $this->buildBadgeMapFromOptions($options, $name);
                    if ($badgeMap) {
                        $columnConfig['badgeMap'] = $badgeMap;
                    }
                }
            }

            // 根据字段名称添加特殊配置（不影响已通过 form_type 确定的类型）
            // 注意：form_type 优先级最高，如果已设置 form_type，只设置其他配置（如宽度、事件等）
            switch ($name) {
                case 'id':
                    $columnConfig['width'] = '60';
                    // 如果没有 form_type，设置为 number
                    if (!$formType) {
                        $columnConfig['type'] = 'number';
                    }
                    break;

                case 'icon':
                    // 只有当 form_type 不是 image 时，才设置为 icon 类型
                    // 如果 form_type 是 image，则使用 image 类型（已在上面处理）
                    if (!$formType || $formType === 'icon') {
                        $columnConfig['type'] = 'icon';
                        $columnConfig['size'] = '1.2rem';
                        $columnConfig['width'] = '80';
                    }
                    break;

                case 'status':
                    // 只有 form_type 是 switch 或没有 form_type 时，才设置为 switch
                    // 如果 form_type 是 radio/select，使用 form_type 确定的类型（badge）
                    if (!$formType || $formType === 'switch') {
                        $columnConfig['type'] = 'switch';
                        $columnConfig['onChange'] = "toggleStatus({id}, this, '" . admin_route("universal/{$model}") . "/{id}')";
                        $columnConfig['width'] = '70';
                    } else {
                        // form_type 是其他类型（如 radio/select），只设置宽度，不覆盖类型
                        // 注意：badgeMap 已在上面构建，不会被覆盖
                        if (!isset($columnConfig['width'])) {
                            $columnConfig['width'] = '150';
                        }
                    }
                    break;

                case 'sort':
                case 'order':
                    // 如果没有 form_type，设置为 number
                    if (!$formType) {
                        $columnConfig['type'] = 'number';
                    }
                    $columnConfig['width'] = '70';
                    break;

                case 'created_at':
                case 'updated_at':
                    // 如果没有 form_type，设置为 date
                    if (!$formType) {
                        $columnConfig['type'] = 'date';
                    }
                    $columnConfig['format'] = 'Y-m-d H:i:s';
                    $columnConfig['width'] = '150';
                    $columnConfig['visible'] = false;  // 默认隐藏时间字段
                    break;
            }

            // 如果类型是 switch，设置 onChange 回调（无论字段名是什么）
            if ($type === 'switch') {
                // 如果还没有设置 onChange，则自动设置
                if (!isset($columnConfig['onChange'])) {
                    $columnConfig['onChange'] = "toggleStatus({id}, this, '" . admin_route("universal/{$model}") . "/{id}')";
                }
                // 设置字段名，供前端使用
                $columnConfig['fieldName'] = $name;
                // 如果没有设置宽度，设置默认宽度
                if (!isset($columnConfig['width'])) {
                    $columnConfig['width'] = '70';
                }
            }

            // 根据列类型设置特殊配置（优先级高于字段名判断）
            if ($type === 'image') {
                // 单图上传字段：设置列宽度（表头）和图片显示尺寸
                $columnConfig['width'] = '150';  // 列宽度（表头）
                // 图片显示尺寸（传递给 image-cell 组件）
                $columnConfig['imageWidth'] = '80px';  // 图片宽度
                $columnConfig['imageHeight'] = '80px';  // 图片高度
                // 确保类型正确
                $columnConfig['type'] = 'image';
            } elseif ($type === 'images') {
                // 多图上传字段：设置列宽度（表头）和图片显示尺寸
                $columnConfig['width'] = '200';  // 列宽度（表头）
                // 图片显示尺寸（传递给 image-cell 组件）
                $columnConfig['imageWidth'] = '60px';  // 每张图片宽度
                $columnConfig['imageHeight'] = '60px';  // 每张图片高度
                // 确保类型正确
                $columnConfig['type'] = 'images';
            } elseif ($type === 'icon' && $name !== 'icon') {
                // 如果类型是 icon 但字段名不是 icon，可能是通过 form_type 设置的
                // 保持 icon 类型，设置默认宽度
                if (!isset($columnConfig['width'])) {
                    $columnConfig['width'] = '80';
                }
            }

            // 根据数据库类型设置宽度
            if (!isset($columnConfig['width'])) {
                if (in_array($dbType, ['text', 'longtext'])) {
                    $columnConfig['width'] = '200';
                } elseif (in_array($dbType, ['varchar', 'string'])) {
                    $columnConfig['width'] = '150';
                }
            }

            $columns[] = $columnConfig;
        }

        // 注意：操作列的定义在视图文件中（storage/view/admin/system/universal/index.blade.php）
        // 这样可以更好地控制操作按钮的显示和自定义

        return $columns;
    }

    /**
     * 将数据库字段类型转换为组件列类型
     *
     * @param string $dbType 数据库类型
     * @param string $fieldName 字段名
     * @param string|null $formType 表单类型（form_type），如果提供则优先使用
     * @return string 组件列类型
     */
    protected function convertDbTypeToColumnType(string $dbType, string $fieldName, ?string $formType = null): string
    {
        // 支持的列类型列表（与 table-cells 目录中的组件对应）
        $supportedColumnTypes = [
            'text', 'number', 'date', 'icon', 'image', 'images',
            'switch', 'badge', 'code', 'custom', 'link', 'relation', 'columns'
        ];

        // 优先使用 form_type 来判断列类型
        if ($formType) {
            // 表单类型直接支持的列类型（直接使用）
            $directSupportedTypes = [
                'text', 'textarea', 'number', 'date', 'image', 'images',
                'icon', 'switch', 'code', 'custom'
            ];

            // 表单类型需要映射到列类型
            $formTypeToColumnTypeMap = [
                'datetime' => 'date',        // 日期时间 → date 列类型
                'timestamp' => 'date',        // 时间戳 → date 列类型
                'url' => 'link',             // URL 字段 → link 列类型
                'file' => 'text',            // 文件上传 → text 列类型（显示文件路径）
                'email' => 'text',           // 邮箱字段 → text 列类型
                'color' => 'text',           // 颜色字段 → text 列类型
                'password' => 'text',         // 密码字段 → text 列类型（显示隐藏）
                'radio' => 'badge',          // 单选框 → badge 列类型（显示彩色标签）
                'select' => 'badge',          // 下拉选择 → badge 列类型（显示彩色标签）
                'checkbox' => 'text',         // 复选框 → text 列类型（显示数组值）
                'relation' => 'relation',     // 关联字段 → relation 列类型（显示关联名称）
                'rich_text' => 'text',        // 富文本 → text 列类型
                'number_range' => 'text',     // 数字区间 → text 列类型（显示范围）
            ];

            // 如果表单类型直接支持，直接使用
            if (in_array($formType, $directSupportedTypes)) {
                $columnType = $formType;
            }
            // 如果需要映射，使用映射后的类型
            elseif (isset($formTypeToColumnTypeMap[$formType])) {
                $columnType = $formTypeToColumnTypeMap[$formType];
            }
            // 如果不支持，使用 text
            else {
                $columnType = 'text';
            }

            // 验证列类型是否在支持列表中
            if (!in_array($columnType, $supportedColumnTypes)) {
                $columnType = 'text';
            }

            return $columnType;
        }

        // 如果没有 form_type，根据字段名和数据库类型推断
        // 先根据字段名判断
        if (str_ends_with($fieldName, '_at') || str_ends_with($fieldName, '_time')) {
            return 'date';
        }

        // 根据字段名判断：区分 icon 和 image
        // icon 字段（字体图标）→ icon 类型
        if ($fieldName === 'icon' || str_ends_with($fieldName, '_icon')) {
            return 'icon';
        }

        // avatar 字段通常也是图片，但在没有 form_type 的情况下，假设是 icon（兼容旧代码）
        if ($fieldName === 'avatar') {
            return 'icon';
        }

        // image 字段：如果没有 form_type，默认当作图片处理，返回 image 类型
        if ($fieldName === 'image' || str_contains($fieldName, '_image') || str_contains($fieldName, 'image_')) {
            return 'image';
        }

        // 注意：不要根据字段名覆盖 form_type
        // 如果没有 form_type，且字段名是 status/is_active/is_enabled，且是 tinyint(1)，才使用 switch
        // 但这个判断已经在 form_type 识别阶段处理了，这里保留作为后备

        // 根据数据库类型判断
        $typeMap = [
            'int' => 'number',
            'integer' => 'number',
            'bigint' => 'number',
            'tinyint' => 'number',
            'smallint' => 'number',
            'decimal' => 'number',
            'float' => 'number',
            'double' => 'number',
            'date' => 'date',
            'datetime' => 'date',
            'timestamp' => 'date',
            'text' => 'text',
            'longtext' => 'text',
            'json' => 'code',
        ];

        return $typeMap[strtolower($dbType)] ?? 'text';
    }

    /**
     * 列表数据 API
     */
    public function listData(RequestInterface $request, string $model): PsrResponseInterface
    {
        try {
            // 获取并规范化参数
            $keyword = $request->input('keyword', '');
            // 确保 keyword 是字符串
            if (is_array($keyword)) {
                $keyword = '';
            } else {
                $keyword = (string) $keyword;
            }
            
            $filters = $request->input('filters', []);
            
            logger()->info('[UniversalCrudController] 接收到的 filters 原始值', [
                'filters' => $filters,
                'filters_type' => gettype($filters),
                'is_array' => is_array($filters),
                'is_string' => is_string($filters),
            ]);
            
            // 如果 filters 是 JSON 字符串，先解析它
            if (is_string($filters) && !empty($filters)) {
                logger()->info('[UniversalCrudController] 检测到 filters 是 JSON 字符串，开始解析', [
                    'json_string' => $filters,
                    'json_string_length' => strlen($filters),
                ]);
                try {
                    $decoded = json_decode($filters, true);
                    $jsonError = json_last_error();
                    logger()->info('[UniversalCrudController] JSON 解析结果', [
                        'decoded' => $decoded,
                        'decoded_type' => gettype($decoded),
                        'json_error' => $jsonError,
                        'json_error_msg' => json_last_error_msg(),
                    ]);
                    if ($jsonError === JSON_ERROR_NONE && is_array($decoded)) {
                        $filters = $decoded;
                        logger()->info('[UniversalCrudController] JSON 解析成功，使用解析后的数组', [
                            'decoded_filters' => $filters,
                            'filters_count' => count($filters),
                        ]);
                    } else {
                        logger()->warning('[UniversalCrudController] JSON 解析失败或结果不是数组，重置为空数组', [
                            'decoded' => $decoded,
                            'json_error' => $jsonError,
                            'json_error_msg' => json_last_error_msg(),
                        ]);
                        $filters = [];
                    }
                } catch (\Throwable $e) {
                    logger()->error('[UniversalCrudController] JSON 解析异常', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $filters = [];
                }
            } elseif (!is_array($filters)) {
                // 如果不是字符串，也不是数组，重置为空数组
                logger()->info('[UniversalCrudController] filters 不是数组也不是字符串，重置为空数组', [
                    'original_type' => gettype($filters),
                    'original_value' => $filters,
                ]);
                $filters = [];
            }
            
            logger()->info('[UniversalCrudController] 最终解析的 filters', [
                'filters' => $filters,
                'filters_type' => gettype($filters),
                'filters_count' => is_array($filters) ? count($filters) : 0,
            ]);
            
            $params = [
                'page' => (int) $request->input('page', 1),
                'page_size' => (int) $request->input('page_size', 15),
                'keyword' => $keyword,
                'filters' => $filters,
                'sort_field' => $request->input('sort_field', ''),
                'sort_order' => $request->input('sort_order', 'desc'),
            ];
            
            logger()->debug('[UniversalCrudController] 准备调用 service->getList', [
                'model' => $model,
                'params' => $params,
            ]);

            $result = $this->service->getList($model, $params);

            return $this->success($result);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 创建页面
     */
    public function create(RequestInterface $request): PsrResponseInterface
    {

        $model = $request->route('model');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型');
        }

        // 获取配置
        $config = $this->service->getModelConfig($model);

        // 获取表单字段
        $fields = $this->service->getFormFields($model, 'create');

        logger()->info('获取创建表单字段', [
            'model' => $model,
            'fields_count' => count($fields),
            'fields' => $fields,
        ]);
        
        // 获取关联数据（如下拉选项）
        $relations = $this->service->getRelationOptions($model);

        return $this->render->render('admin.system.universal.create', [
            'model' => $model,
            'config' => $config,
            'fields' => $fields,
            'relations' => $relations,
        ]);
    }

    /**
     * 保存数据
     */
    public function store(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型', 403);
        }

        try {
            $data = $request->all();

            // 数据验证
            $this->validateData($model, $data, 'create');

            // 创建记录
            $id = $this->service->create($model, $data);

            return $this->success(['id' => $id], '创建成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 编辑页面
     */
    public function edit(RequestInterface $request, int $id): PsrResponseInterface
    {
        $model = $request->route('model');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型', 403);
        }

        // 获取数据
        $record = $this->service->find($model, $id);
        if (!$record) {
            return $this->error('记录不存在', 404);
        }

        // 获取配置
        $config = $this->service->getModelConfig($model);

        // 获取表单字段
        $fields = $this->service->getFormFields($model, 'edit');
        
        // 调试：检查过滤后的字段
        foreach ($fields as $index => $field) {
            if ($field['name'] === 'user_ids') {
                \Hyperf\Support\make(\Psr\Log\LoggerInterface::class)->info('过滤后的 user_ids 字段', [
                    'field' => $field,
                    'editable' => $field['editable'] ?? 'NOT_SET',
                    'editable_type' => gettype($field['editable'] ?? null),
                    'editable_strict_true' => ($field['editable'] ?? null) === true,
                ]);
            }
        }

        // 获取关联数据
        $relations = $this->service->getRelationOptions($model);

        return $this->render->render('admin.system.universal.edit', [
            'model' => $model,
            'config' => $config,
            'fields' => $fields,
            'relations' => $relations,
            'record' => $record,
        ]);
    }

    /**
     * 更新数据
     */
    public function update(RequestInterface $request, int $id): PsrResponseInterface
    {
        $model = $request->route('model');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型', 403);
        }

        try {
            $data = $request->all();

            // 数据验证
            $this->validateData($model, $data, 'update', $id);

            // 更新记录
            $this->service->update($model, $id, $data);

            return $this->success([], '更新成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除数据
     */
    public function destroy(RequestInterface $request, int $id): PsrResponseInterface
    {
        $model = $request->route('model');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型', 403);
        }

        try {
            $this->service->delete($model, $id);

            return $this->success([], '删除成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 批量删除
     */
    public function batchDestroy(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型', 403);
        }

        try {
            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return $this->error('请选择要删除的记录');
            }

            $this->service->batchDelete($model, $ids);

            return $this->success([], '批量删除成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新状态（通用切换字段）
     */
    public function toggleStatus(RequestInterface $request, int $id): PsrResponseInterface
    {
        $model = $request->route('model');
        $field = $request->input('field', 'status');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型', 403);
        }

        try {
            $this->service->toggleField($model, $id, $field);

            return $this->success([], '状态更新成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 验证数据
     */
    protected function validateData(string $model, array $data, string $scene = 'create', ?int $id = null): void
    {
        $rules = $this->service->getValidationRules($model, $scene, $id);

        if (empty($rules)) {
            return;
        }

        $validator = $this->validatorFactory->make($data, $rules);

        if ($validator->fails()) {
            $errors = $validator->errors()->first();
            throw new \RuntimeException($errors);
        }
    }

    /**
     * 从选项配置构建 badgeMap
     *
     * @param array $options 选项数组，格式：['key' => 'value', ...]
     * @param string $fieldName 字段名（用于特殊字段的颜色映射）
     * @return array badgeMap 格式：['key' => ['text' => 'value', 'variant' => 'primary'], ...]
     */
    protected function buildBadgeMapFromOptions(array $options, string $fieldName): array
    {
        $badgeMap = [];
        $variants = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
        $variantIndex = 0;

        // 常见字段的特殊颜色映射
        $specialMappings = [
            'status' => [
                '0' => 'secondary',  // 禁用/否 → 灰色
                '1' => 'success',     // 启用/是 → 绿色
                '启用' => 'success',
                '禁用' => 'secondary',
                '是' => 'success',
                '否' => 'secondary',
                'active' => 'success',      // active → 绿色
                'inactive' => 'secondary',  // inactive → 灰色
            ],
            'type' => [
                'menu' => 'primary',
                'button' => 'info',
                'link' => 'warning',
            ],
        ];

        // 获取字段的特殊映射（如果有）
        $fieldMapping = $specialMappings[$fieldName] ?? [];

        foreach ($options as $key => $label) {
            // 规范化键的类型：数字键统一转换为字符串，确保与数据库值匹配
            // 数据库中的 tinyint(1) 字段值通常是整数 0/1，但 options 配置的键可能是字符串 '0'/'1'
            $normalizedKey = is_numeric($key) ? (string)$key : $key;
            
            // 优先使用字段的特殊映射
            if (isset($fieldMapping[$key])) {
                $variant = $fieldMapping[$key];
            } elseif (isset($fieldMapping[$label])) {
                $variant = $fieldMapping[$label];
            } else {
                // 根据值智能分配颜色
                $variant = $this->getVariantForValue($key, $label, $variants, $variantIndex);
                $variantIndex++;
            }

            // 使用规范化后的键，确保类型一致
            $badgeMap[$normalizedKey] = [
                'text' => $label,
                'variant' => $variant,
            ];
            
            // 同时支持原始键（如果原始键与规范化键不同），以确保兼容性
            if ($normalizedKey !== $key) {
                $badgeMap[$key] = [
                    'text' => $label,
                    'variant' => $variant,
                ];
            }
        }

        return $badgeMap;
    }

    /**
     * 根据值智能分配颜色变体
     *
     * @param string|int $key 选项键
     * @param string $label 选项标签
     * @param array $variants 可用颜色变体数组
     * @param int $variantIndex 当前索引
     * @return string 颜色变体
     */
    protected function getVariantForValue($key, string $label, array $variants, int $variantIndex): string
    {
        // 根据标签文本智能匹配颜色
        $labelLower = mb_strtolower($label);

        // 启用/激活/是 → success
        if (preg_match('/^(启用|激活|是|开启|正常|在线|公开|显示)$/u', $label)) {
            return 'success';
        }

        // 禁用/停用/否 → secondary
        if (preg_match('/^(禁用|停用|否|关闭|异常|离线|隐藏|删除)$/u', $label)) {
            return 'secondary';
        }

        // 警告/待审核 → warning
        if (preg_match('/^(警告|待审核|待处理|待发布|草稿)$/u', $label)) {
            return 'warning';
        }

        // 错误/拒绝/失败 → danger
        if (preg_match('/^(错误|拒绝|失败|已删除|已禁用)$/u', $label)) {
            return 'danger';
        }

        // 信息/默认 → info
        if (preg_match('/^(信息|默认|其他)$/u', $label)) {
            return 'info';
        }

        // 根据数字键分配颜色
        if (is_numeric($key)) {
            $numKey = (int)$key;
            if ($numKey === 0) {
                return 'secondary'; // 0 通常是禁用/否
            } elseif ($numKey === 1) {
                return 'success'; // 1 通常是启用/是
            }
        }

        // 循环使用可用颜色
        return $variants[$variantIndex % count($variants)];
    }

    /**
     * 导出数据
     */
    public function export(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型', 403);
        }

        try {
            // 获取查询参数（支持搜索和过滤）
            $keyword = $request->input('keyword', '');
            if (is_array($keyword)) {
                $keyword = '';
            } else {
                $keyword = (string) $keyword;
            }
            
            $filters = $request->input('filters', []);
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
            
            $params = [
                'keyword' => $keyword,
                'filters' => $filters,
                'sort_field' => $request->input('sort_field', ''),
                'sort_order' => $request->input('sort_order', 'desc'),
            ];

            // 获取配置和列信息
            $config = $this->service->getModelConfig($model);
            $title = $config['title'] ?? '数据';
            $columns = $this->service->getExportColumns($model, $params);

            // 生成文件名
            $filename = $title . '_' . date('YmdHis') . '.csv';

            // 使用流式输出，支持大数据量导出
            // 直接在 runtime 目录创建临时文件（避免 tmpfile() 关闭后自动删除的问题）
            $tempFilePath = BASE_PATH . '/runtime/temp_' . uniqid() . '_' . $filename;
            
            // 确保 runtime 目录存在
            $runtimeDir = BASE_PATH . '/runtime';
            if (!is_dir($runtimeDir)) {
                mkdir($runtimeDir, 0755, true);
            }
            
            // 打开文件用于写入
            $tempFile = fopen($tempFilePath, 'wb');
            if ($tempFile === false) {
                throw new \RuntimeException('无法创建临时文件');
            }

            try {
                // 添加 BOM 以支持 Excel 正确显示中文
                fwrite($tempFile, chr(0xEF) . chr(0xBB) . chr(0xBF));

                // 写入表头
                $headers = [];
                foreach ($columns as $column) {
                    $headers[] = $column['label'];
                }
                fputcsv($tempFile, $headers);

                // 分批查询并写入数据（每批 2000 条，避免内存溢出）
                $batchSize = 2000;
                $offset = 0;
                $totalExported = 0;

                while (true) {
                    // 获取一批数据
                    $batch = $this->service->exportBatch($model, $params, $offset, $batchSize);
                    
                    if (empty($batch)) {
                        break; // 没有更多数据了
                    }

                    // 写入这批数据
                    foreach ($batch as $row) {
                        $csvRow = [];
                        foreach ($columns as $column) {
                            $name = $column['name'];
                            $value = $row[$name] ?? '';
                            
                            // 如果是关联字段，使用 label 字段
                            if (isset($row["{$name}_label"])) {
                                $labelValue = $row["{$name}_label"];
                                if (is_array($labelValue)) {
                                    $value = implode(', ', $labelValue);
                                } else {
                                    $value = $labelValue;
                                }
                            }
                            
                            // 处理数组和对象
                            if (is_array($value)) {
                                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                            } elseif (is_object($value)) {
                                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                            }
                            
                            // 转换为字符串
                            $value = (string) $value;
                            
                            $csvRow[] = $value;
                        }
                        fputcsv($tempFile, $csvRow);
                        $totalExported++;
                    }

                    // 如果这批数据少于批次大小，说明已经是最后一批了
                    if (count($batch) < $batchSize) {
                        break;
                    }

                    $offset += $batchSize;

                    // 释放内存
                    unset($batch);
                }

                // 关闭文件句柄
                fclose($tempFile);
                $tempFile = null;

                // 将临时文件移动到最终下载路径
                $downloadPath = BASE_PATH . '/runtime/' . $filename;
                
                // 使用 rename 移动文件（更高效，不需要复制）
                if (!rename($tempFilePath, $downloadPath)) {
                    // 如果 rename 失败，尝试复制
                    if (!copy($tempFilePath, $downloadPath)) {
                        throw new \RuntimeException('无法创建下载文件');
                    }
                    // 删除临时文件
                    @unlink($tempFilePath);
                }

                // 返回下载响应
                return $this->response->download($downloadPath, $filename)->withHeader('Content-Type', 'text/csv; charset=utf-8');
            } catch (\Throwable $e) {
                // 确保文件句柄被关闭
                if ($tempFile !== null && is_resource($tempFile)) {
                    fclose($tempFile);
                }
                // 删除临时文件
                if (file_exists($tempFilePath)) {
                    @unlink($tempFilePath);
                }
                throw $e;
            }
        } catch (\Throwable $e) {
            logger()->error('[UniversalCrudController] 导出失败', [
                'model' => $model,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('导出失败：' . $e->getMessage());
        }
    }

    /**
     * 搜索关联选项（支持搜索和分页）
     * 用于 Tom Select 等组件的异步加载
     */
    public function searchRelationOptions(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');
        $field = $request->input('field', '');
        $search = $request->input('search', '');
        $page = (int)$request->input('page', 1);
        $perPage = (int)$request->input('per_page', 20);

        if (empty($field)) {
            return $this->error('字段名不能为空', 400);
        }

        try {
            $config = $this->service->getModelConfig($model);
            $relations = $config['relations'] ?? [];

            if (!isset($relations[$field])) {
                return $this->error('关联字段不存在', 404);
            }

            $relation = $relations[$field];
            $relationTable = $relation['table'] ?? '';
            $labelField = $relation['label_field'] ?? 'name';
            $valueField = $relation['value_field'] ?? 'id';

            if (empty($relationTable)) {
                return $this->error('关联表未配置', 400);
            }

            // 构建查询
            $query = \Hyperf\DbConnection\Db::table($relationTable);

            // 站点过滤
            if (!empty($relation['has_site_id']) && site_id() && !is_super_admin()) {
                $query->where('site_id', site_id());
            }

            // 如果提供了 value 参数，优先通过值查找（用于预加载已选中的选项）
            $valueParam = $request->input('value', '');
            if (!empty($valueParam)) {
                if (is_array($valueParam)) {
                    // 多个值
                    $query->whereIn($valueField, $valueParam);
                } else {
                    // 单个值
                    $query->where($valueField, $valueParam);
                }
            } elseif (!empty($search)) {
                // 如果没有 value 参数，使用搜索过滤
                // 如果搜索关键词是数字，同时搜索ID和标签字段
                if (is_numeric($search)) {
                    $query->where(function ($q) use ($valueField, $labelField, $search) {
                        $q->where($valueField, $search)
                          ->orWhere($labelField, 'like', "%{$search}%");
                    });
                } else {
                    // 非数字，只搜索标签字段
                    $query->where($labelField, 'like', "%{$search}%");
                }
            }

            // 如果提供了 value 参数，直接返回所有匹配的选项（不分页）
            if (!empty($valueParam)) {
                $items = $query->select($valueField . ' as value', $labelField . ' as label')
                    ->orderBy($labelField, 'asc')
                    ->get();
                
                $total = $items->count();
                $offset = 0;
                $perPage = $total;
            } else {
                // 正常搜索模式：分页查询
                $total = $query->count();
                $offset = ($page - 1) * $perPage;
                $items = $query->select($valueField . ' as value', $labelField . ' as label')
                    ->orderBy($labelField, 'asc')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get();
            }

            // 转换为 Tom Select 需要的格式
            // 显示格式：标签文本 (ID)
            $results = [];
            foreach ($items as $item) {
                $value = (string)$item->value;
                $label = $item->label ?? '';
                // 如果标签为空，使用ID作为标签
                if (empty($label)) {
                    $label = $value;
                }
                // 组合显示：标签 (ID)
                $displayText = $label . ' (' . $value . ')';
                $results[] = [
                    'value' => $value,
                    'text' => $displayText,
                    'label' => $label, // 保留原始标签，用于搜索
                ];
            }

            return $this->success([
                'results' => $results,
                'pagination' => [
                    'more' => !empty($valueParam) ? false : (($offset + $perPage) < $total),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}

