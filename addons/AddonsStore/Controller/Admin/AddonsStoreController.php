<?php

declare(strict_types=1);

namespace Addons\AddonsStore\Controller\Admin;

use Addons\AddonsStore\Service\AddonsStoreService;
use App\Controller\AbstractController;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 应用商店管理控制器
 */
class AddonsStoreController extends AbstractController
{
    #[Inject]
    protected AddonsStoreService $storeService;

    /**
     * 规范化过滤器参数
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
     * 插件管理列表页面
     */
    public function index(RequestInterface $request): ResponseInterface
    {
        // 如果是 AJAX 请求，返回 JSON 数据（支持 _ajax=1 和 format=json）
        if ($request->input('_ajax') === '1' || $request->input('format') === 'json') {
            return $this->listData($request);
        }

        $searchFields = ['name', 'author', 'addon_id', 'identifier', 'category', 'status'];
        $fields = [
            [
                'name' => 'name',
                'label' => '插件名称',
                'type' => 'text',
                'placeholder' => '输入插件名称',
                'col' => 'col-md-3',
            ],
            [
                'name' => 'author',
                'label' => '作者',
                'type' => 'text',
                'placeholder' => '输入作者名称',
                'col' => 'col-md-3',
            ],
            [
                'name' => 'addon_id',
                'label' => '插件ID',
                'type' => 'text',
                'placeholder' => '输入插件ID',
                'col' => 'col-md-2',
            ],
            [
                'name' => 'identifier',
                'label' => '标识符ID',
                'type' => 'text',
                'placeholder' => '输入标识符ID',
                'col' => 'col-md-2',
            ],
            [
                'name' => 'category',
                'label' => '分类',
                'type' => 'select',
                'options' => [
                    '' => '全部分类',
                    'system' => '系统',
                    'tool' => '工具',
                    'theme' => '主题',
                    'other' => '其他',
                ],
                'placeholder' => '请选择分类',
                'col' => 'col-md-3',
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'select',
                'options' => [
                    '' => '全部状态',
                    '1' => '启用',
                    '0' => '禁用',
                ],
                'placeholder' => '请选择状态',
                'col' => 'col-md-2',
            ],
        ];

        $searchConfig = [
            'search_fields' => $searchFields,
            'fields' => $fields,
        ];

        return $this->renderAdmin('admin.addons_store.apps.index', [
            'searchConfig' => $searchConfig,
            'uploadUrl' => admin_route('addons_store/upload'),
        ]);
    }

    /**
     * 获取插件列表数据
     */
    public function listData(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();
        $filters = $this->normalizeFilters($request->input('filters', []));

        if (!empty($filters)) {
            $params = array_merge($params, $filters);
        }
        unset($params['filters']);

        try {
            $result = $this->storeService->getAddonList($params);

            // 统一返回格式：确保字段名正确
            return $this->success([
                'data' => $result['data'] ?? [],
                'total' => $result['total'] ?? 0,
                'page' => $result['page'] ?? 1,
                'page_size' => $result['per_page'] ?? 15,
                'last_page' => $result['last_page'] ?? 1,
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 创建插件页面
     */
    public function create()
    {
        $fields = $this->storeService->getFormFields('create');
        $formSchema = [
            'title' => '新增插件',
            'fields' => $fields,
            'submitUrl' => admin_route('addons_store'),
            'method' => 'POST',
            'redirectUrl' => admin_route('addons_store'),
        ];

        return $this->renderAdmin('admin.addons_store.apps.create', [
            'formSchemaJson' => json_encode($formSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * 保存插件
     */
    public function store()
    {
        $data = $this->request->all();

        try {
            $result = $this->storeService->createAddon($data);
            return $this->success($result, '插件创建成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 编辑插件页面
     */
    public function edit(int $id)
    {
        try {
            $addon = $this->storeService->getAddonById($id);
            $fields = $this->storeService->getFormFields('update', $addon);

            $formSchema = [
                'title' => '编辑插件',
                'fields' => $fields,
                'submitUrl' => admin_route("addons_store/{$id}"),
                'method' => 'PUT',
                'redirectUrl' => admin_route('addons_store'),
            ];

            return $this->renderAdmin('admin.addons_store.apps.edit', [
                'formSchemaJson' => json_encode($formSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'addon' => $addon, // 保留用于显示统计信息
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新插件
     */
    public function update(int $id)
    {
        $data = $this->request->all();

        try {
            $result = $this->storeService->updateAddon($id, $data);
            return $this->success($result, '插件更新成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除插件
     */
    public function destroy(int $id)
    {
        try {
            $this->storeService->deleteAddon($id);
            return $this->success([], '插件删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 切换插件状态
     */
    public function toggleStatus(RequestInterface $request, int $id): ResponseInterface
    {
        logger()->info('[AddonsStore] toggleStatus 开始处理', [
            'addon_id' => $id,
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getUri()->getPath(),
            'request_params' => $request->all(),
            'request_headers' => [
                'content-type' => $request->getHeaderLine('Content-Type'),
                'x-csrf-token' => substr($request->getHeaderLine('X-CSRF-TOKEN'), 0, 10) . '...', // 只记录前10个字符
                'x-requested-with' => $request->getHeaderLine('X-Requested-With'),
            ]
        ]);

        try {
            logger()->info('[AddonsStore] toggleStatus 获取插件信息', ['addon_id' => $id]);
            $addon = $this->storeService->getAddonById($id);

            if (!$addon) {
                logger()->warning('[AddonsStore] toggleStatus 插件不存在', ['addon_id' => $id]);
                return $this->error('插件不存在');
            }

            logger()->info('[AddonsStore] toggleStatus 插件信息获取成功', [
                'addon_id' => $id,
                'addon_name' => $addon['name'] ?? 'unknown',
                'current_status' => $addon['status'] ?? 'unknown'
            ]);

            // 从请求中获取要切换的字段，默认为 'status'
            $field = $request->input('field', 'status');
            logger()->info('[AddonsStore] toggleStatus 读取字段参数', [
                'addon_id' => $id,
                'field_param' => $field,
                'request_body' => $request->getParsedBody()
            ]);

            // 验证字段是否允许切换
            if (!in_array($field, ['status'])) {
                logger()->warning('[AddonsStore] toggleStatus 不允许切换的字段', [
                    'addon_id' => $id,
                    'field' => $field,
                    'allowed_fields' => ['status']
                ]);
                return $this->error('不允许切换此字段');
            }

            // 切换状态
            $currentValue = $addon[$field];
            $newStatus = $currentValue == 1 ? 0 : 1;

            logger()->info('[AddonsStore] toggleStatus 准备切换状态', [
                'addon_id' => $id,
                'field' => $field,
                'current_value' => $currentValue,
                'new_value' => $newStatus,
                'value_type' => gettype($currentValue)
            ]);

            $updateResult = $this->storeService->updateAddon($id, [$field => $newStatus]);

            logger()->info('[AddonsStore] toggleStatus 更新完成', [
                'addon_id' => $id,
                'field' => $field,
                'old_value' => $currentValue,
                'new_value' => $newStatus,
                'update_result' => $updateResult
            ]);

            // 验证更新是否成功
            $updatedAddon = $this->storeService->getAddonById($id);
            $actualNewValue = $updatedAddon[$field] ?? null;

            logger()->info('[AddonsStore] toggleStatus 验证更新结果', [
                'addon_id' => $id,
                'field' => $field,
                'expected_value' => $newStatus,
                'actual_value' => $actualNewValue,
                'update_success' => $actualNewValue == $newStatus
            ]);

            if ($actualNewValue != $newStatus) {
                logger()->error('[AddonsStore] toggleStatus 更新失败：数据库值未改变', [
                    'addon_id' => $id,
                    'field' => $field,
                    'expected' => $newStatus,
                    'actual' => $actualNewValue
                ]);
                return $this->error('状态更新失败：数据库更新异常');
            }

            logger()->info('[AddonsStore] toggleStatus 处理完成', [
                'addon_id' => $id,
                'field' => $field,
                'final_value' => $newStatus
            ]);

            return $this->success([$field => $newStatus], '状态更新成功');
        } catch (\Exception $e) {
            logger()->error('[AddonsStore] toggleStatus 异常', [
                'addon_id' => $id,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString()
            ]);
            return $this->error($e->getMessage());
        }
    }


    /**
     * 上传插件安装包
     */
    public function upload()
    {
        $request = $this->request;
        $files = $request->getUploadedFiles();

        // 检查是否有文件上传
        if (empty($files) || !isset($files['addon_package'])) {
            return $this->error('请选择要上传的插件安装包');
        }

        $file = $files['addon_package'];

        try {
            $result = $this->storeService->uploadAndProcessAddon($file);
            return $this->success($result, '插件上传处理完成');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }


}
