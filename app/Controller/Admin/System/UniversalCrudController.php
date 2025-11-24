<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\AbstractController;
use App\Exception\BusinessException;
use App\Exception\ValidationException;
use App\Service\Admin\UniversalCrudService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
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

        // 获取配置
        $config = $this->service->getModelConfig($model);

        // 仅保留需要在列表中展示的字段（show_in_list 或 list_default 为 true）
        if (!empty($config['fields_config']) && is_array($config['fields_config'])) {
            $config['fields_config'] = array_values(array_filter(
                $config['fields_config'],
                fn($fieldConfig) => (bool) ($fieldConfig['show_in_list'] ?? $fieldConfig['list_default'] ?? true)
            ));
        }

        // 判断是否是 API 请求
        if ($request->input('_ajax') === '1') {
            return $this->listData($request, $model);
        }

        // 将配置转换为 JSON，供前端脚本解析
        $configJson = '{}';
        try {
            $configJson = (string) json_encode(
                $config,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (\JsonException $exception) {
            logger()->error('[UniversalCrudController] 配置 JSON 序列化失败', [
                'model' => $model,
                'error' => $exception->getMessage(),
            ]);
        }

        // 返回列表页面
        return $this->renderAdmin('admin.system.universal.index', [
            'model' => $model,
            'config' => $config,
            'configJson' => $configJson,
        ]);
    }

    public function listData(RequestInterface $request, string $model): PsrResponseInterface
    {
        try {
            // 获取并规范化参数
            $keyword = $request->input('keyword', '');
            // 确保 keyword 是字符串
            if (is_array($keyword)) {
                $keyword = '';
            } else {
                $keyword = (string)$keyword;
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

            // 移除 iframe/系统级保留参数，防止误参与查询（如 _embed 等）
            if (!empty($filters) && is_array($filters)) {
                $filters = array_filter(
                    $filters,
                    static function ($value, $key): bool {
                        if (!is_string($key)) {
                            return true;
                        }

                        if ($key === '_embed') {
                            return false;
                        }

                        return !str_starts_with($key, '_');
                    },
                    ARRAY_FILTER_USE_BOTH
                );
            }

            $params = [
                'page' => (int)$request->input('page', 1),
                'page_size' => (int)$request->input('page_size', 15),
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
            print_r($e);
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

        $fields = $this->service->getFormFields($model, 'create');
        $relationOptions = $this->normalizeRelationOptions(
            $this->service->getRelationOptions($model)
        );

        $formSchema = [
            'model' => $model,
            'title' => $config['title'] ?? $model,
            'fields' => $fields,
            'relations' => $config['relations'] ?? [],
            'relationOptions' => $relationOptions,
            'validation' => $config['validation']['create'] ?? [],
            'submitUrl' => admin_route("u/{$model}"),
            'redirectUrl' => admin_route("u/{$model}"),
            'endpoints' => [
                'relationSearch' => admin_route("u/{$model}/search-relation-options"),
                'uploadToken' => admin_route('api/admin/upload/token'),
            ],
        ];

        $configJson = $this->encodeToJson($config);
        $formSchemaJson = $this->encodeToJson($formSchema);

        logger()->info('通用创建表单配置', [
            'model' => $model,
            'field_count' => count($fields),
            'has_relations' => !empty($relationOptions),
        ]);

        return $this->renderAdmin('admin.system.universal.create', [
            'model' => $model,
            'config' => $config,
            'configJson' => $configJson,
            'formSchemaJson' => $formSchemaJson,
        ]);
    }

    protected function encodeToJson(array $payload): string
    {
        try {
            return (string) json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (\JsonException $exception) {
            logger()->error('[UniversalCrudController] JSON 序列化失败', [
                'error' => $exception->getMessage(),
                'payload_keys' => array_keys($payload),
            ]);
        }

        return '{}';
    }

    protected function normalizeRelationOptions(array $options): array
    {
        if (empty($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $field => $list) {
            // 将 Collection 转换为数组
            if ($list instanceof \Hyperf\Collection\Collection) {
                $list = $list->toArray();
            } elseif (!is_array($list)) {
                $list = [];
            }

            $normalized[$field] = array_map(static function ($item) {
                if (is_array($item)) {
                    return [
                        'value' => (string) ($item['value'] ?? ''),
                        'label' => (string) ($item['label'] ?? ''),
                    ];
                }

                return [
                    'value' => (string) ($item->value ?? ''),
                    'label' => (string) ($item->label ?? ''),
                ];
            }, $list);
        }

        return $normalized;
    }

    /**
     * 将 record 数据填充到字段的 default 属性中
     * 
     * @param array $fields 字段配置数组
     * @param array $record 记录数据
     * @param array $relationOptions 关联选项数据（用于 relation 字段）
     * @return array 填充了默认值的字段配置数组
     */
    protected function populateFieldDefaults(array $fields, array $record, array $relationOptions = []): array
    {
        foreach ($fields as &$field) {
            $fieldName = $field['name'] ?? '';
            if (empty($fieldName)) {
                continue;
            }

            // 如果字段在 record 中存在，使用 record 的值作为 default
            if (isset($record[$fieldName])) {
                $value = $record[$fieldName];
                
                // 处理特殊字段类型
                $fieldType = $field['type'] ?? 'text';
                
                // 对于关系字段（relation/select）
                if (in_array($fieldType, ['relation', 'select'])) {
                    // 判断是否多选：优先从 relation.multiple 读取，其次从字段的 multiple 属性，最后判断字段名
                    $isMultiple = false;
                    if ($fieldType === 'relation' && isset($field['relation']['multiple'])) {
                        $isMultiple = filter_var($field['relation']['multiple'], FILTER_VALIDATE_BOOLEAN);
                    } elseif (isset($field['multiple'])) {
                        $isMultiple = filter_var($field['multiple'], FILTER_VALIDATE_BOOLEAN);
                    } elseif (str_ends_with($fieldName, '_ids')) {
                        $isMultiple = true;
                    }
                    
                    if ($isMultiple) {
                        // 多选关系字段：如果是 JSON 字符串，解析为数组
                        if (is_string($value) && !empty($value)) {
                            try {
                                $decoded = json_decode($value, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    $value = $decoded;
                                } elseif (str_contains($value, ',')) {
                                    // 如果不是 JSON，可能是逗号分隔的字符串
                                    $value = array_filter(array_map('trim', explode(',', $value)));
                                } else {
                                    // 单个值，转换为数组
                                    $value = [$value];
                                }
                            } catch (\Throwable $e) {
                                // 解析失败，保持原值
                            }
                        } elseif (!is_array($value)) {
                            // 如果不是数组，转换为数组
                            $value = $value !== null && $value !== '' ? [$value] : [];
                        }
                    } else {
                        // 单选关系字段：确保值是字符串或数字（不是数组）
                        if (is_array($value)) {
                            // 如果是数组，取第一个值
                            $value = !empty($value) ? $value[0] : '';
                        } elseif (is_string($value) && !empty($value)) {
                            // 如果是 JSON 字符串，尝试解析（可能是单个值的 JSON）
                            try {
                                $decoded = json_decode($value, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    if (is_array($decoded) && !empty($decoded)) {
                                        $value = $decoded[0];
                                    } else {
                                        $value = $decoded;
                                    }
                                }
                            } catch (\Throwable $e) {
                                // 解析失败，保持原值
                            }
                        }
                        // 转换为字符串（前端需要字符串类型）
                        $value = $value !== null ? (string)$value : '';
                    }
                    
                    // 对于 relation 类型字段，如果有值，从 relationOptions 中查找对应的选项并添加到字段的 options 中
                    if ($fieldType === 'relation' && !empty($value) && isset($relationOptions[$fieldName])) {
                        $fieldOptions = $field['options'] ?? [];
                        $fieldOptionsMap = [];
                        // 将现有 options 转换为 map，避免重复
                        foreach ($fieldOptions as $opt) {
                            if (is_array($opt)) {
                                $fieldOptionsMap[$opt['value'] ?? $opt['id'] ?? ''] = $opt;
                            } else {
                                $fieldOptionsMap[] = $opt;
                            }
                        }
                        
                        // 要查找的值列表（单选是单个值，多选是数组）
                        $valuesToFind = $isMultiple ? (is_array($value) ? $value : [$value]) : [$value];
                        
                        // 从 relationOptions 中查找对应的选项
                        foreach ($relationOptions[$fieldName] as $option) {
                            $optionValue = (string)($option['value'] ?? '');
                            if (in_array($optionValue, $valuesToFind) && !isset($fieldOptionsMap[$optionValue])) {
                                // 找到匹配的选项，添加到 fieldOptions
                                $fieldOptions[] = [
                                    'value' => $optionValue,
                                    'label' => (string)($option['label'] ?? ''),
                                ];
                                $fieldOptionsMap[$optionValue] = true;
                            }
                        }
                        
                        // 更新字段的 options
                        if (!empty($fieldOptions)) {
                            $field['options'] = $fieldOptions;
                        }
                    }
                } elseif ($fieldType === 'images') {
                    // 多图字段：如果是 JSON 字符串，解析为数组
                    if (is_string($value) && !empty($value)) {
                        try {
                            $decoded = json_decode($value, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $value = $decoded;
                            } elseif (str_contains($value, ',')) {
                                // 逗号分隔的字符串
                                $value = array_filter(array_map('trim', explode(',', $value)));
                            } else {
                                // 单个值，转换为数组
                                $value = [$value];
                            }
                        } catch (\Throwable $e) {
                            // 解析失败，保持原值
                        }
                    } elseif (!is_array($value)) {
                        // 如果不是数组，转换为数组
                        $value = $value !== null && $value !== '' ? [$value] : [];
                    }
                } elseif ($fieldType === 'checkbox') {
                    // 复选框字段：如果是 JSON 字符串，解析为数组
                    if (is_string($value) && !empty($value)) {
                        try {
                            $decoded = json_decode($value, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $value = $decoded;
                            } elseif (str_contains($value, ',')) {
                                // 逗号分隔的字符串
                                $value = array_filter(array_map('trim', explode(',', $value)));
                            }
                        } catch (\Throwable $e) {
                            // 解析失败，保持原值
                        }
                    }
                } elseif ($fieldType === 'number_range') {
                    // 数字范围字段：如果是 JSON 字符串，保持原样（前端会解析）
                    // 不需要特殊处理，直接使用原值
                }
                
                $field['default'] = $value;
            }
        }
        unset($field); // 解除引用

        return $fields;
    }
//
    /**
     * 保存数据
     */
    public function store(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型', code:403);
        }

        try {
            $data = $request->all();

            // 数据验证
            $this->validateData($model, $data, 'create');

            // 创建记录
            $id = $this->service->create($model, $data);

            return $this->success(['id' => $id], '创建成功');
        } catch (ValidationException $e) {
            // 返回结构化的验证错误信息
            return $this->error($e->getMessage(), [
                'errors' => $e->getErrors(),
            ], 422);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage(), [], $e->getCode());
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
            return $this->error('不允许访问的模型', code:403);
        }

        // 获取数据
        $record = $this->service->find($model, $id);
        if (!$record) {
            return $this->error('记录不存在', code:404);
        }

        // 获取配置
        $config = $this->service->getModelConfig($model);

        // 获取表单字段
        $fields = $this->service->getFormFields($model, 'edit');

        // 获取关联数据（在填充默认值之前获取，以便用于查找已选中的选项）
        $relationOptions = $this->normalizeRelationOptions(
            $this->service->getRelationOptions($model)
        );

        // 将 record 数据填充到字段的 default 属性中，并补充 relation 字段的 options
        $fields = $this->populateFieldDefaults($fields, $record, $relationOptions);

        // 构建表单 Schema（类似 create 方法）
        $formSchema = [
            'model' => $model,
            'title' => $config['title'] ?? $model,
            'fields' => $fields,
            'relations' => $config['relations'] ?? [],
            'relationOptions' => $relationOptions,
            'validation' => $config['validation']['update'] ?? [],
            'submitUrl' => admin_route("u/{$model}") . '/' . $id,
            'redirectUrl' => admin_route("u/{$model}"),
            'endpoints' => [
                'relationSearch' => admin_route("u/{$model}/search-relation-options"),
                'uploadToken' => admin_route('api/admin/upload/token'),
            ],
            'method' => 'PUT', // 编辑使用 PUT 方法
            'recordId' => $id, // 传递记录 ID
        ];

        $configJson = $this->encodeToJson($config);
        $formSchemaJson = $this->encodeToJson($formSchema);

        logger()->info('通用编辑表单配置', [
            'model' => $model,
            'id' => $id,
            'field_count' => count($fields),
            'has_relations' => !empty($relationOptions),
        ]);

        return $this->renderAdmin('admin.system.universal.edit', [
            'model' => $model,
            'config' => $config,
            'configJson' => $configJson,
            'formSchemaJson' => $formSchemaJson,
            'recordId' => $id,
        ]);
    }

    /**
     * 更新数据public function index(RequestInterface $request): PsrResponseInterface
     * {
     */
    public function update(?string $model,?int $id): PsrResponseInterface
    {
        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error(msg:'不允许访问的模型', code:403);
        }
        try {
            $data = $this->request->all();
            // 数据验证
            $this->validateData($model, $data, 'update', $id);
            // 更新记录
            $this->service->update($model, $id, $data);
            return $this->success([], '更新成功');
        } catch (ValidationException $e) {
            // 返回结构化的验证错误信息
            return $this->error($e->getMessage(), [
                'errors' => $e->getErrors(),
            ], 422);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage(), [], $e->getCode());
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除数据
     * 
     * 支持软删除和硬删除：
     * - 如果模型配置了 soft_delete 或使用了 SoftDeletes trait，执行软删除
     * - 否则执行硬删除（永久删除）
     */
    public function destroy(RequestInterface $request, int $id): PsrResponseInterface
    {
        $model = $request->route('model');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型', 403);
        }

        try {
            // 验证 ID
            if ($id <= 0) {
                return $this->error('无效的记录ID', 400);
            }

            // 检查记录是否存在
            $record = $this->service->find($model, $id);
            if (!$record) {
                return $this->error('记录不存在或已被删除', 404);
            }

            // 获取模型配置，检查是否支持软删除
            $config = $this->service->getModelConfig($model);
            $softDeleteEnabled = !empty($config['soft_delete']);

            // 如果配置中启用了软删除，尝试检查模型类是否使用了 SoftDeletes trait
            $usesSoftDeletes = false;
            if ($softDeleteEnabled) {
                try {
                    $modelClass = $this->service->getModelClass($model);
                    if (class_exists($modelClass)) {
                        // 检查模型类是否使用了 SoftDeletes trait
                        $usesSoftDeletes = in_array(
                            \Hyperf\Database\Model\SoftDeletes::class,
                            class_uses_recursive($modelClass)
                        );
                    }
                } catch (\Throwable $e) {
                    // 如果无法获取模型类，忽略错误，使用配置中的 soft_delete 选项
                    logger()->warning('[UniversalCrudController] 无法检查模型 SoftDeletes trait', [
                        'model' => $model,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 执行删除操作
            // service->delete() 方法会根据配置和模型 trait 自动判断使用软删除还是硬删除
            $result = $this->service->delete($model, $id);

            if (!$result) {
                return $this->error('删除失败，请稍后重试', 500);
            }

            // 根据删除类型返回不同的提示信息
            $message = ($softDeleteEnabled || $usesSoftDeletes) 
                ? '删除成功（已移至回收站）' 
                : '删除成功';

            return $this->success([], $message);
        } catch (\Throwable $e) {
            logger()->error('[UniversalCrudController] 删除记录失败', [
                'model' => $model,
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 返回友好的错误信息
            $errorMessage = $e->getMessage();
            if (empty($errorMessage) || str_contains($errorMessage, 'SQLSTATE')) {
                $errorMessage = '删除失败，请稍后重试';
            }

            return $this->error($errorMessage);
        }
    }
//
//    /**
//     * 批量删除
//     */
//    public function batchDestroy(RequestInterface $request): PsrResponseInterface
//    {
//        $model = $request->route('model');
//
//        // 验证模型
//        if (!$this->service->isAllowedModel($model)) {
//            return $this->error('不允许访问的模型', 403);
//        }
//
//        try {
//            $ids = $request->input('ids', []);
//            if (empty($ids)) {
//                return $this->error('请选择要删除的记录');
//            }
//
//            $this->service->batchDelete($model, $ids);
//
//            return $this->success([], '批量删除成功');
//        } catch (\Throwable $e) {
//            return $this->error($e->getMessage());
//        }
//    }

    /**
     * 更新状态（通用切换字段）
     */
    public function toggleStatus(RequestInterface $request, int $id): PsrResponseInterface
    {
        $model = $request->route('model');
        $field = $request->input('field', 'status');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型', code:403);
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
        // 1. 首先检查不可编辑字段
        $this->validateEditableFields($model, $data, $scene);

        // 2. 获取验证规则
        $rules = $this->service->getValidationRules($model, $scene, $id);

        if (empty($rules)) {
            return;
        }

        // 3. 根据字段的 editable 属性过滤验证规则
        // 不可编辑的字段应该忽略其内容验证
        $rules = $this->filterRulesByEditable($model, $rules, $scene);

        if (empty($rules)) {
            return;
        }

        // 4. 进行常规验证
        $validator = $this->validatorFactory->make($data, $rules);

        if ($validator->fails()) {
            // 获取所有验证错误（字段 => 错误消息数组）
            $errors = $validator->errors()->toArray();
            
            // 获取字段标签映射
            $fieldLabels = $this->getFieldLabels($model);
            
            // 转换错误消息为中文友好的格式
            $translatedErrors = $this->translateValidationErrors($errors, $fieldLabels);
            
            // 抛出结构化验证异常
            throw new ValidationException($translatedErrors, '数据验证失败', $fieldLabels);
        }
    }

    /**
     * 验证字段是否可编辑
     * 
     * @param string $model 模型名
     * @param array $data 提交的数据
     * @param string $scene 场景：create 或 update
     * @throws \RuntimeException 如果包含不可编辑字段
     */
    protected function validateEditableFields(string $model, array $data, string $scene): void
    {
        // 获取模型配置
        $config = $this->service->getModelConfig($model);
        $fieldsConfig = $config['fields_config'] ?? [];

        if (empty($fieldsConfig)) {
            // 如果没有字段配置，跳过检查（向后兼容）
            return;
        }

        // 构建字段可编辑性映射
        $editableMap = [];
        foreach ($fieldsConfig as $field) {
            if (empty($field['name'])) {
                continue;
            }

            $fieldName = $field['name'];
            
            // 规范化 editable 值（与 extractFormFields 中的逻辑一致）
            $editable = $field['editable'] ?? null;
            if ($editable !== null && $editable !== '') {
                if (is_string($editable)) {
                    $editable = filter_var($editable, FILTER_VALIDATE_BOOLEAN);
                } elseif (is_numeric($editable)) {
                    $editable = (bool) $editable;
                } else {
                    $editable = (bool) $editable;
                }
            } else {
                $editable = null;
            }

            $editableMap[$fieldName] = $editable;
        }

        // 检查提交的数据中是否包含不可编辑字段
        $forbiddenFields = [];
        foreach ($data as $fieldName => $value) {
            // 跳过系统保留字段（这些字段由系统自动处理）
            $skipFields = ['id', 'site_id', 'created_at', 'updated_at', 'deleted_at', '_token', '_method'];
            if (in_array($fieldName, $skipFields, true)) {
                continue;
            }

            // 如果字段不在配置中，跳过检查（可能是动态字段）
            if (!isset($editableMap[$fieldName])) {
                continue;
            }

            $editable = $editableMap[$fieldName];

            // 编辑场景：只允许 editable === true 的字段
            if ($scene === 'update') {
                if ($editable !== true) {
                    $forbiddenFields[] = $fieldName;
                }
            } 
            // 创建场景：不允许 editable === false 的字段
            elseif ($scene === 'create') {
                if ($editable === false) {
                    $forbiddenFields[] = $fieldName;
                }
            }
        }

        // 如果发现不可编辑字段，抛出异常
        if (!empty($forbiddenFields)) {
            // 构建字段标签映射（字段名 => 字段标签）
            $fieldLabelsMap = [];
            $fieldLabelsForMessage = [];
            
            foreach ($forbiddenFields as $fieldName) {
                // 尝试获取字段标签
                $fieldLabel = $fieldName;
                foreach ($fieldsConfig as $field) {
                    if (($field['name'] ?? '') === $fieldName) {
                        $fieldLabel = $field['field_name'] ?? $field['label'] ?? $fieldName;
                        break;
                    }
                }
                $fieldLabelsMap[$fieldName] = $fieldLabel;
                $fieldLabelsForMessage[] = $fieldLabel;
            }

            $message = sprintf(
                '以下字段不可编辑：%s',
                implode('、', $fieldLabelsForMessage)
            );

            // 构建验证异常格式的错误信息
            $errors = [];
            foreach ($forbiddenFields as $fieldName) {
                $errors[$fieldName] = ['该字段不可编辑'];
            }
            
            throw new ValidationException($errors, $message, $fieldLabelsMap);
        }
    }

    /**
     * 根据字段的 editable 属性过滤验证规则
     * 不可编辑的字段应该忽略其内容验证
     * 
     * @param string $model 模型名
     * @param array<string, string|array> $rules 验证规则
     * @param string $scene 场景：create 或 update
     * @return array<string, string|array> 过滤后的验证规则
     */
    protected function filterRulesByEditable(string $model, array $rules, string $scene): array
    {
        // 获取模型配置
        $config = $this->service->getModelConfig($model);
        $fieldsConfig = $config['fields_config'] ?? [];

        if (empty($fieldsConfig)) {
            // 如果没有字段配置，返回原始规则（向后兼容）
            return $rules;
        }

        // 构建字段可编辑性映射
        $editableMap = [];
        foreach ($fieldsConfig as $field) {
            if (empty($field['name'])) {
                continue;
            }

            $fieldName = $field['name'];
            
            // 规范化 editable 值（与 extractFormFields 中的逻辑一致）
            $editable = $field['editable'] ?? null;
            if ($editable !== null && $editable !== '') {
                if (is_string($editable)) {
                    $editable = filter_var($editable, FILTER_VALIDATE_BOOLEAN);
                } elseif (is_numeric($editable)) {
                    $editable = (bool) $editable;
                } else {
                    $editable = (bool) $editable;
                }
            } else {
                $editable = null;
            }

            $editableMap[$fieldName] = $editable;
        }

        // 过滤验证规则：移除不可编辑字段的验证规则
        $filteredRules = [];
        foreach ($rules as $fieldName => $rule) {
            // 跳过系统保留字段（这些字段由系统自动处理）
            $skipFields = ['id', 'site_id', 'created_at', 'updated_at', 'deleted_at', '_token', '_method'];
            if (in_array($fieldName, $skipFields, true)) {
                continue;
            }

            // 如果字段不在配置中，保留验证规则（可能是动态字段）
            if (!isset($editableMap[$fieldName])) {
                $filteredRules[$fieldName] = $rule;
                continue;
            }

            $editable = $editableMap[$fieldName];

            // 根据场景判断是否应该验证该字段
            $shouldValidate = false;
            
            if ($scene === 'update') {
                // 更新场景：只验证 editable === true 的字段
                // editable === false 或 null 的字段不验证
                $shouldValidate = ($editable === true);
            } elseif ($scene === 'create') {
                // 创建场景：验证 editable !== false 的字段
                // editable === false 的字段不验证
                // editable === true 或 null 的字段验证
                $shouldValidate = ($editable !== false);
            }

            // 如果应该验证，保留该字段的验证规则
            if ($shouldValidate) {
                $filteredRules[$fieldName] = $rule;
            }
            // 否则忽略该字段的验证规则（不添加到 $filteredRules 中）
        }

        return $filteredRules;
    }

    /**
     * 获取字段标签映射
     * 
     * @param string $model 模型名
     * @return array<string, string> 字段名 => 字段标签
     */
    protected function getFieldLabels(string $model): array
    {
        $config = $this->service->getModelConfig($model);
        $fieldsConfig = $config['fields_config'] ?? [];
        
        $labels = [];
        foreach ($fieldsConfig as $field) {
            if (empty($field['name'])) {
                continue;
            }
            
            $fieldName = $field['name'];
            // 优先使用 field_name，其次 label，最后使用字段名
            $label = $field['field_name'] ?? $field['label'] ?? $fieldName;
            $labels[$fieldName] = $label;
        }
        
        return $labels;
    }

    /**
     * 转换验证错误消息为中文友好的格式
     * 
     * @param array<string, array<string>> $errors 原始错误信息
     * @param array<string, string> $fieldLabels 字段标签映射
     * @return array<string, array<string>> 转换后的错误信息
     */
    protected function translateValidationErrors(array $errors, array $fieldLabels): array
    {
        $translated = [];
        
        foreach ($errors as $field => $fieldErrors) {
            $fieldLabel = $fieldLabels[$field] ?? $field;
            $translated[$field] = [];
            
            foreach ($fieldErrors as $error) {
                // 转换常见的验证错误消息
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
            // 尝试替换其中的字段名为字段标签
            return $error;
        }
        
        // 处理 validation.xxx 格式的错误消息
        if (preg_match('/^validation\.(\w+)(?:\.(.+))?$/i', $error, $matches)) {
            $rule = strtolower($matches[1]);
            $param = $matches[2] ?? null;
            
            return match ($rule) {
                'required' => "{$fieldLabel}不能为空",
                'email' => "{$fieldLabel}必须是有效的邮箱地址",
                'unique' => "{$fieldLabel}已存在，请使用其他值",
                'integer' => "{$fieldLabel}必须是整数",
                'numeric' => "{$fieldLabel}必须是数字",
                'string' => "{$fieldLabel}必须是字符串",
                'date' => "{$fieldLabel}必须是有效的日期",
                'max' => $param ? "{$fieldLabel}不能超过{$param}个字符" : "{$fieldLabel}超出最大限制",
                'min' => $param ? "{$fieldLabel}至少需要{$param}个字符" : "{$fieldLabel}未达到最小要求",
                'between' => $param ? "{$fieldLabel}必须在{$param}之间" : "{$fieldLabel}超出范围",
                default => "{$fieldLabel}验证失败",
            };
        }
        
        // 处理 "The field is required" 格式
        if (preg_match('/^The (.+?) (?:field )?is required$/i', $error, $matches)) {
            return "{$fieldLabel}不能为空";
        }
        
        // 处理 "The field must be..." 格式
        if (preg_match('/^The (.+?) must be (?:a )?(.+?)(?:\.|$)/i', $error, $matches)) {
            $type = strtolower(trim($matches[2]));
            return match ($type) {
                'valid email address', 'an email' => "{$fieldLabel}必须是有效的邮箱地址",
                'an integer' => "{$fieldLabel}必须是整数",
                'a number', 'numeric' => "{$fieldLabel}必须是数字",
                'a string' => "{$fieldLabel}必须是字符串",
                default => "{$fieldLabel}格式不正确",
            };
        }
        
        // 处理 "The field may not be greater than..." 格式（max 规则）
        if (preg_match('/^The (.+?) may not be greater than (\d+)/i', $error, $matches)) {
            $max = $matches[2];
            return "{$fieldLabel}不能超过{$max}个字符";
        }
        
        // 处理 "The field must be at least..." 格式（min 规则）
        if (preg_match('/^The (.+?) must be at least (\d+)/i', $error, $matches)) {
            $min = $matches[2];
            return "{$fieldLabel}至少需要{$min}个字符";
        }
        
        // 处理 "The field has already been taken" 格式（unique 规则）
        if (preg_match('/^The (.+?) has already been taken$/i', $error, $matches)) {
            return "{$fieldLabel}已存在，请使用其他值";
        }
        
        // 处理 "The field does not match..." 格式
        if (preg_match('/^The (.+?) does not match/i', $error, $matches)) {
            return "{$fieldLabel}格式不正确";
        }
        
        // 如果无法匹配，尝试提取数字参数并返回通用消息
        if (preg_match('/(\d+)/', $error, $numMatches)) {
            $num = $numMatches[1];
            if (stripos($error, 'greater') !== false || stripos($error, 'more') !== false) {
                return "{$fieldLabel}不能超过{$num}";
            }
            if (stripos($error, 'less') !== false || stripos($error, 'at least') !== false) {
                return "{$fieldLabel}至少需要{$num}";
            }
        }
        
        // 默认情况：返回字段标签 + 原始错误消息（去除 "The field" 等前缀）
        $cleaned = preg_replace('/^The (.+?) /i', '', $error);
        return "{$fieldLabel}：{$cleaned}";
    }
//
    /**
     * 导出数据
     */
    public function export(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');

        // 验证模型
        if (!$this->service->isAllowedModel($model)) {
            return $this->error('不允许访问的模型', code:403);
        }

        try {
            // 获取查询参数（支持搜索和过滤）
            $keyword = $request->input('keyword', '');
            if (is_array($keyword)) {
                $keyword = '';
            } else {
                $keyword = (string)$keyword;
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
                            $value = (string)$value;

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
            return $this->error('字段名不能为空', code:400);
        }

        try {
            $config = $this->service->getModelConfig($model);
            $relations = $config['relations'] ?? [];

            if (!isset($relations[$field])) {
                return $this->error('关联字段不存在', code:404);
            }

            $relation = $relations[$field];
            $relationTable = $relation['table'] ?? '';
            $labelField = $relation['label_field'] ?? 'name';
            $valueField = $relation['value_field'] ?? 'id';

            if (empty($relationTable)) {
                return $this->error('关联表未配置', code:400);
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
            return $this->error($e->getMessage(), code:500);
        }
    }

    /**
     * 回收站页面
     */
    public function trash(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');

        // 获取配置
        $config = $this->service->getModelConfig($model);

        // 检查是否启用软删除
        if (empty($config['soft_delete'])) {
            return $this->error('该模型未启用软删除功能');
        }

        // 仅保留需要在列表中展示的字段（show_in_list 或 list_default 为 true）
        if (!empty($config['fields_config']) && is_array($config['fields_config'])) {
            $config['fields_config'] = array_values(array_filter(
                $config['fields_config'],
                fn($fieldConfig) => (bool) ($fieldConfig['show_in_list'] ?? $fieldConfig['list_default'] ?? true)
            ));
        }

        // 判断是否是 API 请求
        if ($request->input('_ajax') === '1') {
            return $this->trashData($request, $model);
        }

        // 将配置转换为 JSON，供前端脚本解析
        $configJson = '{}';
        try {
            $configJson = (string) json_encode(
                $config,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (\JsonException $exception) {
            logger()->error('[UniversalCrudController] 配置 JSON 序列化失败', [
                'model' => $model,
                'error' => $exception->getMessage(),
            ]);
        }

        // 返回回收站页面
        return $this->renderAdmin('admin.system.universal.trash', [
            'model' => $model,
            'config' => $config,
            'configJson' => $configJson,
        ]);
    }

    /**
     * 获取回收站数据（只查询已删除的记录）
     */
    public function trashData(RequestInterface $request, string $model): PsrResponseInterface
    {
        try {
            // 获取并规范化参数
            $keyword = $request->input('keyword', '');
            if (is_array($keyword)) {
                $keyword = '';
            } else {
                $keyword = (string)$keyword;
            }

            $filters = $request->input('filters', []);

            // 如果 filters 是 JSON 字符串，先解析它
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

            // 移除 iframe/系统级保留参数
            if (!empty($filters) && is_array($filters)) {
                $filters = array_filter(
                    $filters,
                    static function ($value, $key): bool {
                        if (!is_string($key)) {
                            return true;
                        }
                        if ($key === '_embed') {
                            return false;
                        }
                        return !str_starts_with($key, '_');
                    },
                    ARRAY_FILTER_USE_BOTH
                );
            }

            $params = [
                'page' => (int)$request->input('page', 1),
                'page_size' => (int)$request->input('page_size', 15),
                'keyword' => $keyword,
                'filters' => $filters,
                'sort_field' => $request->input('sort_field', 'deleted_at'),
                'sort_order' => $request->input('sort_order', 'desc'),
                'only_trashed' => true, // 标记只查询已删除的记录
            ];

            $result = $this->service->getList($model, $params);

            return $this->success($result);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 恢复记录
     */
    public function restore(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');
        $id = (int)$request->route('id');

        try {
            $result = $this->service->restore($model, $id);
            if ($result) {
                return $this->success([], '恢复成功');
            }
            return $this->error('恢复失败');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 永久删除记录
     */
    public function forceDelete(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');
        $id = (int)$request->route('id');

        try {
            $result = $this->service->forceDelete($model, $id);
            if ($result) {
                return $this->success([], '永久删除成功');
            }
            return $this->error('永久删除失败');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 批量恢复
     */
    public function batchRestore(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');
        $ids = $request->input('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return $this->error('请选择要恢复的记录');
        }

        try {
            $count = $this->service->batchRestore($model, $ids);
            if ($count === 0) {
                return $this->error('没有记录被恢复，可能选中的记录已经被恢复或不存在');
            }
            return $this->success(['count' => $count], "成功恢复 {$count} 条记录");
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 批量永久删除
     */
    public function batchForceDelete(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');
        $ids = $request->input('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return $this->error('请选择要永久删除的记录');
        }

        try {
            $count = $this->service->batchForceDelete($model, $ids);
            if ($count === 0) {
                return $this->error('没有记录被删除，可能选中的记录已经被删除或不存在');
            }
            return $this->success(['count' => $count], "成功永久删除 {$count} 条记录");
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 清空回收站
     */
    public function clearTrash(RequestInterface $request): PsrResponseInterface
    {
        $model = $request->route('model');

        try {
            $count = $this->service->clearTrash($model);
            if ($count === 0) {
                return $this->success(['count' => 0], '回收站已经是空的，没有记录需要删除');
            }
            return $this->success(['count' => $count], "成功清空回收站，共永久删除 {$count} 条记录");
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}

