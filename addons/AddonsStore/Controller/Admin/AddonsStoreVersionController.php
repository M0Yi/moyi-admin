<?php

declare(strict_types=1);

namespace Addons\AddonsStore\Controller\Admin;

use Addons\AddonsStore\Model\AddonsStoreVersion;
use Addons\AddonsStore\Service\AddonsStoreService;
use App\Controller\AbstractController;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 应用商店版本管理控制器
 */
class AddonsStoreVersionController extends AbstractController
{
    #[Inject]
    protected AddonsStoreService $storeService;

    /**
     * 版本管理页面（支持特定插件版本和所有版本）
     */
    public function index(RequestInterface $request, ?int $addonId = null): ResponseInterface
    {
        // 如果是 AJAX 请求，返回 JSON 数据
        if ($request->input('_ajax') === '1' || $request->input('format') === 'json') {
            return $this->listData($request, $addonId);
        }

        // 如果有 addonId，显示特定插件的版本管理页面
        if ($addonId) {
            return $this->renderSpecificAddonVersions($addonId);
        }

        // 否则显示版本管理页面
        return $this->renderVersionManagement();
    }

    /**
     * 获取版本列表数据（支持特定插件版本和所有版本）
     */
    public function listData(RequestInterface $request, ?int $addonId = null): ResponseInterface
    {
        $params = $request->all();
        $filters = $this->normalizeFilters($request->input('filters', []));
        if (!empty($filters)) {
            $params = array_merge($params, $filters);
        }
        unset($params['filters']);

        try {
            if ($addonId) {
                // 获取特定插件的版本
                $versions = $this->storeService->getAddonVersions($addonId);
                return $this->success([
                    'data' => $versions,
                    'total' => count($versions),
                    'page' => 1,
                    'page_size' => count($versions),
                    'last_page' => 1
                ]);
            } else {
                // 获取所有版本
                $result = $this->storeService->getAllVersions($params);

                return $this->success([
                    'data' => $result['data'] ?? [],
                    'total' => $result['total'] ?? 0,
                    'page' => $result['page'] ?? 1,
                    'page_size' => $result['per_page'] ?? 15,
                    'last_page' => $result['last_page'] ?? 1,
                ]);
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 渲染特定插件的版本管理页面
     */
    private function renderSpecificAddonVersions(int $addonId): ResponseInterface
    {
        // 获取插件信息
        $addonInfo = $this->storeService->getAddonInfoById($addonId);
        if (!isset($addonInfo['addon'])) {
            return $this->error('插件不存在');
        }

        $addon = $addonInfo['addon'];

        // 页面配置
        $searchFields = []; // 版本管理页面不需要搜索
        $fields = []; // 版本管理页面不需要搜索表单

        $searchConfig = [
            'search_fields' => $searchFields,
            'fields' => $fields,
        ];

        return $this->render->render('admin.addons_store.apps.versions', [
            'addon' => $addon,
            'versions' => $addonInfo['versions'] ?? [],
            'hasValidFiles' => $addonInfo['has_valid_files'] ?? false,
            'searchConfig' => $searchConfig,
        ]);
    }

    /**
     * 渲染版本管理页面
     */
    private function renderVersionManagement(): ResponseInterface
    {
        // 搜索配置
        $searchFields = ['addon_name', 'addon_author', 'addon_id', 'identifier', 'addon_category', 'status'];
        $fields = [
            [
                'name' => 'addon_name',
                'label' => '插件名称',
                'type' => 'text',
                'placeholder' => '输入插件名称',
                'col' => 'col-md-3',
            ],
            [
                'name' => 'addon_author',
                'label' => '作者',
                'type' => 'text',
                'placeholder' => '输入作者名称',
                'col' => 'col-md-3',
            ],
            [
                'name' => 'addon_id',
                'label' => '应用ID',
                'type' => 'text',
                'placeholder' => '输入应用ID',
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
                'name' => 'addon_category',
                'label' => '应用分类',
                'type' => 'select',
                'options' => [
                    '' => '全部分类',
                    'system' => '系统',
                    'tool' => '工具',
                    'theme' => '主题',
                    'other' => '其他',
                ],
                'placeholder' => '请选择应用分类',
                'col' => 'col-md-3',
            ],
            [
                'name' => 'status',
                'label' => '版本状态',
                'type' => 'select',
                'options' => [
                    '' => '全部状态',
                    '1' => '启用',
                    '0' => '禁用',
                ],
                'placeholder' => '请选择版本状态',
                'col' => 'col-md-2',
            ],
        ];

        $searchConfig = [
            'search_fields' => $searchFields,
            'fields' => $fields,
        ];

        return $this->renderAdmin('admin.addons_store.versions.index', [
            'searchConfig' => $searchConfig,
        ]);
    }

    /**
     * 下载插件版本
     */
    public function download(int $versionId): ResponseInterface
    {
        try {
            // 获取版本信息
            $version = $this->storeService->getVersionById($versionId);
            if (!$version) {
                return $this->error('版本不存在');
            }

            // 检查版本状态
            if ($version['status'] != 1) {
                return $this->error('版本已被禁用');
            }

            // 获取下载信息
            $downloadInfo = $this->storeService->downloadAddon($version['addon_id'], $version['version']);

            // 返回文件下载响应
            $filePath = BASE_PATH . '/storage/' . $downloadInfo['filepath'];
            if (!file_exists($filePath)) {
                return $this->error('文件不存在');
            }

            return $this->response
                ->withHeader('Content-Type', 'application/octet-stream')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $downloadInfo['filename'] . '"')
                ->withHeader('Content-Length', filesize($filePath))
                ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream(file_get_contents($filePath)));

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除插件版本
     */
    public function destroy(int $versionId): ResponseInterface
    {
        try {
            // 这里应该调用服务层删除版本的方法
            // 暂时返回成功，具体实现需要根据业务逻辑
            return $this->success([], '版本删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 规范化过滤器参数
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
     * 删除版本
     */
    public function delete(int $id): ResponseInterface
    {
        try {
            $version = AddonsStoreVersion::findOrFail($id);
            $version->delete();

            return $this->success([], '删除成功');
        } catch (\Throwable $e) {
            return $this->error('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 批量删除版本
     */
    public function batchDestroy(RequestInterface $request): ResponseInterface
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return $this->error('请选择要删除的版本');
        }

        try {
            AddonsStoreVersion::whereIn('id', $ids)->delete();
            return $this->success([], '批量删除成功');
        } catch (\Throwable $e) {
            return $this->error('批量删除失败：' . $e->getMessage());
        }
    }
}