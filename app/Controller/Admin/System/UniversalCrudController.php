<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\AbstractController;
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
//
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
//
//    /**
//     * 编辑页面
//     */
//    public function edit(RequestInterface $request, int $id): PsrResponseInterface
//    {
//        $model = $request->route('model');
//
//        // 验证模型
//        if (!$this->service->isAllowedModel($model)) {
//            return $this->error('不允许访问的模型', 403);
//        }
//
//        // 获取数据
//        $record = $this->service->find($model, $id);
//        if (!$record) {
//            return $this->error('记录不存在', 404);
//        }
//
//        // 获取配置
//        $config = $this->service->getModelConfig($model);
//
//        // 获取表单字段
//        $fields = $this->service->getFormFields($model, 'edit');
//
//        // 调试：检查过滤后的字段
//        foreach ($fields as $index => $field) {
//            if ($field['name'] === 'user_ids') {
//                \Hyperf\Support\make(\Psr\Log\LoggerInterface::class)->info('过滤后的 user_ids 字段', [
//                    'field' => $field,
//                    'editable' => $field['editable'] ?? 'NOT_SET',
//                    'editable_type' => gettype($field['editable'] ?? null),
//                    'editable_strict_true' => ($field['editable'] ?? null) === true,
//                ]);
//            }
//        }
//
//        // 获取关联数据
//        $relations = $this->service->getRelationOptions($model);
//
//        return $this->render->render('admin.system.universal.edit', [
//            'model' => $model,
//            'config' => $config,
//            'fields' => $fields,
//            'relations' => $relations,
//            'record' => $record,
//        ]);
//    }

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
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
//
//    /**
//     * 删除数据
//     */
//    public function destroy(RequestInterface $request, int $id): PsrResponseInterface
//    {
//        $model = $request->route('model');
//
//        // 验证模型
//        if (!$this->service->isAllowedModel($model)) {
//            return $this->error('不允许访问的模型', 403);
//        }
//
//        try {
//            $this->service->delete($model, $id);
//
//            return $this->success([], '删除成功');
//        } catch (\Throwable $e) {
//            return $this->error($e->getMessage());
//        }
//    }
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
//
//    /**
//     * 更新状态（通用切换字段）
//     */
//    public function toggleStatus(RequestInterface $request, int $id): PsrResponseInterface
//    {
//        $model = $request->route('model');
//        $field = $request->input('field', 'status');
//
//        // 验证模型
//        if (!$this->service->isAllowedModel($model)) {
//            return $this->error('不允许访问的模型', 403);
//        }
//
//        try {
//            $this->service->toggleField($model, $id, $field);
//
//            return $this->success([], '状态更新成功');
//        } catch (\Throwable $e) {
//            return $this->error($e->getMessage());
//        }
//    }
//
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
//
//    /**
//     * 导出数据
//     */
//    public function export(RequestInterface $request): PsrResponseInterface
//    {
//        $model = $request->route('model');
//
//        // 验证模型
//        if (!$this->service->isAllowedModel($model)) {
//            return $this->error('不允许访问的模型', 403);
//        }
//
//        try {
//            // 获取查询参数（支持搜索和过滤）
//            $keyword = $request->input('keyword', '');
//            if (is_array($keyword)) {
//                $keyword = '';
//            } else {
//                $keyword = (string)$keyword;
//            }
//
//            $filters = $request->input('filters', []);
//            if (is_string($filters) && !empty($filters)) {
//                try {
//                    $decoded = json_decode($filters, true);
//                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
//                        $filters = $decoded;
//                    } else {
//                        $filters = [];
//                    }
//                } catch (\Throwable $e) {
//                    $filters = [];
//                }
//            } elseif (!is_array($filters)) {
//                $filters = [];
//            }
//
//            $params = [
//                'keyword' => $keyword,
//                'filters' => $filters,
//                'sort_field' => $request->input('sort_field', ''),
//                'sort_order' => $request->input('sort_order', 'desc'),
//            ];
//
//            // 获取配置和列信息
//            $config = $this->service->getModelConfig($model);
//            $title = $config['title'] ?? '数据';
//            $columns = $this->service->getExportColumns($model, $params);
//
//            // 生成文件名
//            $filename = $title . '_' . date('YmdHis') . '.csv';
//
//            // 使用流式输出，支持大数据量导出
//            // 直接在 runtime 目录创建临时文件（避免 tmpfile() 关闭后自动删除的问题）
//            $tempFilePath = BASE_PATH . '/runtime/temp_' . uniqid() . '_' . $filename;
//
//            // 确保 runtime 目录存在
//            $runtimeDir = BASE_PATH . '/runtime';
//            if (!is_dir($runtimeDir)) {
//                mkdir($runtimeDir, 0755, true);
//            }
//
//            // 打开文件用于写入
//            $tempFile = fopen($tempFilePath, 'wb');
//            if ($tempFile === false) {
//                throw new \RuntimeException('无法创建临时文件');
//            }
//
//            try {
//                // 添加 BOM 以支持 Excel 正确显示中文
//                fwrite($tempFile, chr(0xEF) . chr(0xBB) . chr(0xBF));
//
//                // 写入表头
//                $headers = [];
//                foreach ($columns as $column) {
//                    $headers[] = $column['label'];
//                }
//                fputcsv($tempFile, $headers);
//
//                // 分批查询并写入数据（每批 2000 条，避免内存溢出）
//                $batchSize = 2000;
//                $offset = 0;
//                $totalExported = 0;
//
//                while (true) {
//                    // 获取一批数据
//                    $batch = $this->service->exportBatch($model, $params, $offset, $batchSize);
//
//                    if (empty($batch)) {
//                        break; // 没有更多数据了
//                    }
//
//                    // 写入这批数据
//                    foreach ($batch as $row) {
//                        $csvRow = [];
//                        foreach ($columns as $column) {
//                            $name = $column['name'];
//                            $value = $row[$name] ?? '';
//
//                            // 如果是关联字段，使用 label 字段
//                            if (isset($row["{$name}_label"])) {
//                                $labelValue = $row["{$name}_label"];
//                                if (is_array($labelValue)) {
//                                    $value = implode(', ', $labelValue);
//                                } else {
//                                    $value = $labelValue;
//                                }
//                            }
//
//                            // 处理数组和对象
//                            if (is_array($value)) {
//                                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
//                            } elseif (is_object($value)) {
//                                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
//                            }
//
//                            // 转换为字符串
//                            $value = (string)$value;
//
//                            $csvRow[] = $value;
//                        }
//                        fputcsv($tempFile, $csvRow);
//                        $totalExported++;
//                    }
//
//                    // 如果这批数据少于批次大小，说明已经是最后一批了
//                    if (count($batch) < $batchSize) {
//                        break;
//                    }
//
//                    $offset += $batchSize;
//
//                    // 释放内存
//                    unset($batch);
//                }
//
//                // 关闭文件句柄
//                fclose($tempFile);
//                $tempFile = null;
//
//                // 将临时文件移动到最终下载路径
//                $downloadPath = BASE_PATH . '/runtime/' . $filename;
//
//                // 使用 rename 移动文件（更高效，不需要复制）
//                if (!rename($tempFilePath, $downloadPath)) {
//                    // 如果 rename 失败，尝试复制
//                    if (!copy($tempFilePath, $downloadPath)) {
//                        throw new \RuntimeException('无法创建下载文件');
//                    }
//                    // 删除临时文件
//                    @unlink($tempFilePath);
//                }
//
//                // 返回下载响应
//                return $this->response->download($downloadPath, $filename)->withHeader('Content-Type', 'text/csv; charset=utf-8');
//            } catch (\Throwable $e) {
//                // 确保文件句柄被关闭
//                if ($tempFile !== null && is_resource($tempFile)) {
//                    fclose($tempFile);
//                }
//                // 删除临时文件
//                if (file_exists($tempFilePath)) {
//                    @unlink($tempFilePath);
//                }
//                throw $e;
//            }
//        } catch (\Throwable $e) {
//            logger()->error('[UniversalCrudController] 导出失败', [
//                'model' => $model,
//                'error' => $e->getMessage(),
//                'trace' => $e->getTraceAsString(),
//            ]);
//            return $this->error('导出失败：' . $e->getMessage());
//        }
//    }
//
//    /**
//     * 搜索关联选项（支持搜索和分页）
//     * 用于 Tom Select 等组件的异步加载
//     */
//    public function searchRelationOptions(RequestInterface $request): PsrResponseInterface
//    {
//        $model = $request->route('model');
//        $field = $request->input('field', '');
//        $search = $request->input('search', '');
//        $page = (int)$request->input('page', 1);
//        $perPage = (int)$request->input('per_page', 20);
//
//        if (empty($field)) {
//            return $this->error('字段名不能为空', 400);
//        }
//
//        try {
//            $config = $this->service->getModelConfig($model);
//            $relations = $config['relations'] ?? [];
//
//            if (!isset($relations[$field])) {
//                return $this->error('关联字段不存在', 404);
//            }
//
//            $relation = $relations[$field];
//            $relationTable = $relation['table'] ?? '';
//            $labelField = $relation['label_field'] ?? 'name';
//            $valueField = $relation['value_field'] ?? 'id';
//
//            if (empty($relationTable)) {
//                return $this->error('关联表未配置', 400);
//            }
//
//            // 构建查询
//            $query = \Hyperf\DbConnection\Db::table($relationTable);
//
//            // 站点过滤
//            if (!empty($relation['has_site_id']) && site_id() && !is_super_admin()) {
//                $query->where('site_id', site_id());
//            }
//
//            // 如果提供了 value 参数，优先通过值查找（用于预加载已选中的选项）
//            $valueParam = $request->input('value', '');
//            if (!empty($valueParam)) {
//                if (is_array($valueParam)) {
//                    // 多个值
//                    $query->whereIn($valueField, $valueParam);
//                } else {
//                    // 单个值
//                    $query->where($valueField, $valueParam);
//                }
//            } elseif (!empty($search)) {
//                // 如果没有 value 参数，使用搜索过滤
//                // 如果搜索关键词是数字，同时搜索ID和标签字段
//                if (is_numeric($search)) {
//                    $query->where(function ($q) use ($valueField, $labelField, $search) {
//                        $q->where($valueField, $search)
//                            ->orWhere($labelField, 'like', "%{$search}%");
//                    });
//                } else {
//                    // 非数字，只搜索标签字段
//                    $query->where($labelField, 'like', "%{$search}%");
//                }
//            }
//
//            // 如果提供了 value 参数，直接返回所有匹配的选项（不分页）
//            if (!empty($valueParam)) {
//                $items = $query->select($valueField . ' as value', $labelField . ' as label')
//                    ->orderBy($labelField, 'asc')
//                    ->get();
//
//                $total = $items->count();
//                $offset = 0;
//                $perPage = $total;
//            } else {
//                // 正常搜索模式：分页查询
//                $total = $query->count();
//                $offset = ($page - 1) * $perPage;
//                $items = $query->select($valueField . ' as value', $labelField . ' as label')
//                    ->orderBy($labelField, 'asc')
//                    ->offset($offset)
//                    ->limit($perPage)
//                    ->get();
//            }
//
//            // 转换为 Tom Select 需要的格式
//            // 显示格式：标签文本 (ID)
//            $results = [];
//            foreach ($items as $item) {
//                $value = (string)$item->value;
//                $label = $item->label ?? '';
//                // 如果标签为空，使用ID作为标签
//                if (empty($label)) {
//                    $label = $value;
//                }
//                // 组合显示：标签 (ID)
//                $displayText = $label . ' (' . $value . ')';
//                $results[] = [
//                    'value' => $value,
//                    'text' => $displayText,
//                    'label' => $label, // 保留原始标签，用于搜索
//                ];
//            }
//
//            return $this->success([
//                'results' => $results,
//                'pagination' => [
//                    'more' => !empty($valueParam) ? false : (($offset + $perPage) < $total),
//                ],
//            ]);
//        } catch (\Throwable $e) {
//            return $this->error($e->getMessage(), 500);
//        }
//    }
}

