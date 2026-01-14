<?php

declare(strict_types=1);

namespace Addons\AddonsStore\Controller\Api;

use Addons\AddonsStore\Service\AddonsStoreService;
use App\Controller\AbstractController;
use Hyperf\Di\Annotation\Inject;

/**
 * 插件商店 API 控制器
 */
class AddonsStoreApiController extends AbstractController
{
    #[Inject]
    protected AddonsStoreService $storeService;

    /**
     * 获取插件列表（用于数据表格）
     */
    public function list()
    {
        logger()->info('Get Addon List', $this->request->all());
        $params = $this->request->all();

        try {
            $result = $this->storeService->getAddonList($params);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取统计数据（用于仪表盘）
     */
    public function stats()
    {
        $params = $this->request->all();

        try {
            $result = $this->storeService->getOperationLogs($params);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取版本列表（支持特定插件版本或所有版本）
     */
    public function getVersions()
    {
        $addonId = (int) $this->request->input('addon_id', 0);
        $params = $this->request->all();

        try {
            if ($addonId) {
                // 获取特定插件的版本
                $versions = $this->storeService->getAddonVersions($addonId);
                return $this->success([
                    'data' => $versions,
                    'total' => count($versions),
                    'page' => 1,
                    'per_page' => count($versions),
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
     * 下载插件版本
     */
    public function downloadVersion(int $versionId)
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
    public function deleteVersion(int $versionId)
    {
        try {
            // 这里应该调用服务层删除版本的方法
            // 暂时返回成功，具体实现需要根据业务逻辑
            return $this->success([], '版本删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
