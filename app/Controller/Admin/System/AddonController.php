<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\AbstractController;
use App\Service\Admin\AddonService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

/**
 * 插件管理控制器
 */
class AddonController extends AbstractController
{
    #[Inject]
    protected AddonService $addonService;

    #[Inject]
    protected ResponseInterface $response;

    /**
     * 插件列表页面
     */
    public function index(RequestInterface $request)
    {
        // 如果是 AJAX 请求，返回 JSON 数据
        if ($request->input('_ajax') === '1' || $request->input('format') === 'json') {
            return $this->listData($request);
        }

        $addons = $this->addonService->getAllAddons();

        return $this->renderAdmin('admin.system.addon.index', [
            'addons' => $addons,
        ]);
    }

    /**
     * 获取插件列表数据（API）
     */
    public function listData(RequestInterface $request)
    {
        // 支持前端传入 filters（SearchFormRenderer 会以 filters[...] 提交）
        $allParams = $this->request->all();

        // 处理filters参数 - 可能是JSON字符串（URL编码）
        $filtersParam = $this->request->input('filters');
        $filters = [];

        if (!empty($filtersParam)) {
            // 如果是JSON字符串，解析它
            if (is_string($filtersParam)) {
                $decoded = json_decode($filtersParam, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $filters = $decoded;
                    logger()->info('[插件列表] 成功解析JSON格式的filters参数', [
                        'raw_filters' => $filtersParam,
                        'parsed_filters' => $filters
                    ]);
                } else {
                    logger()->warning('[插件列表] filters参数不是有效的JSON字符串', [
                        'raw_filters' => $filtersParam,
                        'json_error' => json_last_error_msg()
                    ]);
                }
            } elseif (is_array($filtersParam)) {
                $filters = $filtersParam;
            }
        }

        logger()->info('[插件列表] ===== 请求开始 =====', [
            'all_params' => $allParams,
            'raw_filters_param' => $this->request->input('filters'),
            'parsed_filters' => $filters,
            'source_filter' => $filters['source'] ?? 'NOT_SET',
            'has_source_filter' => isset($filters['source']),
            'source_filter_empty' => empty($filters['source']),
            'filters_type' => gettype($filters),
            'filters_count' => count($filters),
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getUri()->getPath(),
            'query_string' => $request->getUri()->getQuery()
        ]);

        // 根据来源筛选优化数据获取
        if (!empty($filters['source']) && $filters['source'] === 'store') {
            logger()->info('[插件列表] 检测到商店来源筛选，获取商店插件列表');
            $addons = $this->addonService->getStoreAddons(true); // true 表示强制刷新，不使用缓存
            logger()->info('[插件列表] 商店插件获取完成', [
                'count' => count($addons),
                'source_value' => 'store',
                'early_return' => true
            ]);
        } elseif (!empty($filters['source']) && $filters['source'] === 'local') {
            logger()->info('[插件列表] 检测到本地来源筛选，只获取本地插件列表');
            $localAddons = $this->addonService->scanAddons();
            // 为本地插件添加source字段
            foreach ($localAddons as &$addon) {
                $addon['source'] = 'local';
            }
            $addons = $localAddons;
            logger()->info('[插件列表] 本地插件获取完成', [
                'count' => count($addons),
                'source_value' => 'local',
                'early_return' => true
            ]);
        } else {
            logger()->info('[插件列表] 无来源筛选或全部来源，获取所有插件列表', [
                'source_value' => $filters['source'] ?? 'NOT_SET',
                'source_condition_check' => !empty($filters['source']) && in_array($filters['source'], ['store', 'local'])
            ]);
            $addons = $this->addonService->getAllAddons();
            logger()->info('[插件列表] 所有插件获取完成', [
                'count' => count($addons),
                'early_return' => false
            ]);
        }

        // 简单过滤实现：name 模糊、version 模糊、enabled 精确、category 精确、source 精确（若存在）
        $originalCount = count($addons);
        logger()->info('[插件列表] ===== 开始过滤 =====', [
            'original_count' => $originalCount,
            'filters_applied' => $filters,
            'has_filters' => !empty($filters) && is_array($filters)
        ]);

        if (!empty($filters) && is_array($filters)) {
            $filterStats = [
                'name_filter' => !empty($filters['name']),
                'version_filter' => !empty($filters['version']),
                'enabled_filter' => isset($filters['enabled']) && $filters['enabled'] !== '',
                'category_filter' => !empty($filters['category']),
                'source_filter' => !empty($filters['source'])
            ];

            logger()->info('[插件列表] 过滤条件分析', array_merge($filterStats, [
                'source_filter_value' => $filters['source'] ?? 'NOT_SET',
                'source_filter_condition' => !empty($filters['source'])
            ]));

            $addons = array_filter($addons, function ($addon) use ($filters, &$filterStats) {
                $addonId = $addon['id'] ?? 'unknown';
                $addonName = $addon['name'] ?? 'unknown';
                $addonSource = $addon['source'] ?? 'local';

                // 名称搜索（模糊）
                if (!empty($filters['name'])) {
                    $needle = mb_strtolower(trim($filters['name']));
                    $hay = mb_strtolower($addon['name'] ?? '');
                    if (strpos($hay, $needle) === false) {
                        logger()->debug("[插件列表] 名称过滤 - 排除插件: {$addonName} (ID: {$addonId})");
                        return false;
                    }
                }

                // 版本搜索（模糊）
                if (!empty($filters['version'])) {
                    $needle = mb_strtolower(trim($filters['version']));
                    $hay = mb_strtolower($addon['version'] ?? '');
                    if (strpos($hay, $needle) === false) {
                        logger()->debug("[插件列表] 版本过滤 - 排除插件: {$addonName} (ID: {$addonId})");
                        return false;
                    }
                }

                // enabled 精确匹配（'1' 或 '0'）
                if (isset($filters['enabled']) && $filters['enabled'] !== '') {
                    $val = $filters['enabled'];
                    $addonEnabled = isset($addon['enabled']) ? ($addon['enabled'] ? '1' : '0') : '0';
                    if ((string)$addonEnabled !== (string)$val) {
                        logger()->debug("[插件列表] 启用状态过滤 - 排除插件: {$addonName} (ID: {$addonId}), 期望: {$val}, 实际: {$addonEnabled}");
                        return false;
                    }
                }

                // category 精确匹配（如果 addon 中存在 category 字段）
                if (!empty($filters['category']) && isset($addon['category'])) {
                    if ((string)$addon['category'] !== (string)$filters['category']) {
                        logger()->debug("[插件列表] 分类过滤 - 排除插件: {$addonName} (ID: {$addonId}), 期望: {$filters['category']}, 实际: {$addon['category']}");
                        return false;
                    }
                }

                // source 过滤（local=本地插件，store=商店插件）
                if (!empty($filters['source'])) {
                    logger()->info("[插件列表] Source过滤检查", [
                        'plugin_id' => $addonId,
                        'plugin_name' => $addonName,
                        'plugin_source' => $addonSource,
                        'filter_source' => $filters['source'],
                        'source_match' => (string)$addonSource === (string)$filters['source']
                    ]);

                    if ((string)$addonSource !== (string)$filters['source']) {
                        logger()->info("[插件列表] Source过滤 - 排除插件: {$addonName} (ID: {$addonId}), 期望来源: {$filters['source']}, 插件来源: {$addonSource}");
                        $filterStats['source_excluded_count'] = ($filterStats['source_excluded_count'] ?? 0) + 1;
                        return false;
                    } else {
                        logger()->info("[插件列表] Source过滤 - 保留插件: {$addonName} (ID: {$addonId}), 来源匹配: {$addonSource}");
                        $filterStats['source_included_count'] = ($filterStats['source_included_count'] ?? 0) + 1;
                    }
                }

                return true;
            });

            // array_filter preserves keys -> reindex
            $addons = array_values($addons);

            logger()->info('[插件列表] ===== 过滤完成 =====', [
                'original_count' => $originalCount,
                'filtered_count' => count($addons),
                'removed_count' => $originalCount - count($addons),
                'filter_stats' => $filterStats
            ]);
        } else {
            logger()->info('[插件列表] 无过滤条件，跳过过滤');
        }

        // 分析返回数据的source分布和安装状态
        $sourceStats = [];
        $installStats = ['installed' => 0, 'not_installed' => 0, 'can_upgrade' => 0];
        foreach ($addons as $addon) {
            $source = $addon['source'] ?? 'local';
            $sourceStats[$source] = ($sourceStats[$source] ?? 0) + 1;

            if (!empty($addon['installed'])) {
                $installStats['installed']++;
                if (!empty($addon['can_upgrade'])) {
                    $installStats['can_upgrade']++;
                }
            } else {
                $installStats['not_installed']++;
            }
        }

        logger()->info('[插件列表] ===== 返回数据统计 =====', [
            'final_count' => count($addons),
            'source_distribution' => $sourceStats,
            'install_stats' => $installStats,
            'sample_addons' => count($addons) > 0 ? array_map(function($addon) {
                return [
                    'id' => $addon['id'] ?? 'unknown',
                    'name' => $addon['name'] ?? 'unknown',
                    'source' => $addon['source'] ?? 'local',
                    'installed' => $addon['installed'] ?? false,
                    'can_upgrade' => $addon['can_upgrade'] ?? false,
                    'current_version' => $addon['current_version'] ?? '',
                    'version' => $addon['version'] ?? '',
                    'has_install_status' => isset($addon['installed']),
                    'has_upgrade_status' => isset($addon['can_upgrade'])
                ];
            }, array_slice($addons, 0, 3)) : []
        ]);

        // 统一返回格式：插件列表数据
        return $this->success([
            'data' => $addons,
            'total' => count($addons),
            'page' => 1,
            'page_size' => count($addons),
            'last_page' => 1,
        ]);
    }

    /**
     * 获取插件列表数据（兼容旧接口）
     */
    public function list()
    {
        return $this->listData($this->request);
    }

    /**
     * 查看插件详情
     */
    public function show($addonId)
    {
        $addon = $this->addonService->getAddonInfoById($addonId);

        if (!$addon) {
            return $this->error('插件不存在');
        }

        return $this->renderAdmin('admin.system.addon.show', [
            'addon' => $addon,
        ]);
    }

    /**
     * 启用插件
     */
    public function enable($addonId)
    {
        $result = $this->addonService->enableAddonById($addonId);
        if ($result) {
            return $this->success([], '插件启用成功');
        } else {
            return $this->error('插件启用失败');
        }
    }

    /**
     * 禁用插件
     */
    public function disable($addonId)
    {
        $result = $this->addonService->disableAddonById($addonId);

        if ($result) {
            return $this->success([], '插件禁用成功');
        } else {
            return $this->error('插件禁用失败');
        }
    }

    /**
     * 安装插件
     */
    public function install()
    {
        logger()->info("[插件安装] 开始处理插件安装请求");

        $request = $this->request;
        $files = $request->getUploadedFiles();

        logger()->info("[插件安装] 上传文件信息", [
            'files_count' => count($files),
            'files_keys' => array_keys($files),
            'has_addon_file' => isset($files['addon_file'])
        ]);

        // 检查是否有文件上传
        if (empty($files) || !isset($files['addon_file'])) {
            logger()->error("[插件安装] 未找到上传的插件文件");
            return $this->error('请上传插件文件');
        }

        $uploadedFile = $files['addon_file'];
        logger()->info("[插件安装] 获取到上传文件", [
            'filename' => $uploadedFile->getClientFilename(),
            'size' => $uploadedFile->getSize(),
            'error' => $uploadedFile->getError()
        ]);

        // 验证文件
        $uploadError = $uploadedFile->getError();
        if ($uploadError !== UPLOAD_ERR_OK) {
            logger()->error("[插件安装] 文件上传错误", ['error_code' => $uploadError]);
            return $this->error('文件上传失败，错误码: ' . $uploadError);
        }

        // 检查文件类型
        $filename = $uploadedFile->getClientFilename();
        logger()->info("[插件安装] 检查文件类型", ['filename' => $filename]);

        if (!preg_match('/\.zip$/i', $filename)) {
            logger()->error("[插件安装] 文件类型不正确", ['filename' => $filename]);
            return $this->error('只支持zip格式的插件文件');
        }

        // 检查文件大小（限制为50MB）
        $fileSize = $uploadedFile->getSize();
        $maxSize = 50 * 1024 * 1024; // 50MB
        logger()->info("[插件安装] 检查文件大小", [
            'file_size' => $fileSize,
            'max_size' => $maxSize,
            'size_mb' => round($fileSize / 1024 / 1024, 2)
        ]);

        if ($fileSize > $maxSize) {
            logger()->error("[插件安装] 文件大小超过限制", [
                'file_size' => $fileSize,
                'max_size' => $maxSize
            ]);
            return $this->error('插件文件大小不能超过50MB');
        }

        try {
            // 创建临时目录（放在 runtime 目录下）
            $runtimeDir = BASE_PATH . '/runtime';
            if (!is_dir($runtimeDir)) {
                mkdir($runtimeDir, 0755, true);
            }

            $tempDir = $runtimeDir . '/temp/addon_import_' . uniqid();
            logger()->info("[插件安装] 创建临时目录", ['tempDir' => $tempDir]);

            if (!mkdir($tempDir, 0755, true)) {
                logger()->error("[插件导入] 无法创建临时目录", ['tempDir' => $tempDir]);
                return $this->error('无法创建临时目录');
            }

            // 移动上传的文件到临时位置
            $tempZipFile = $tempDir . '/addon.zip';
            logger()->info("[插件安装] 移动文件到临时位置", ['tempZipFile' => $tempZipFile]);

            $uploadedFile->moveTo($tempZipFile);

            if (!file_exists($tempZipFile)) {
                logger()->error("[插件导入] 文件移动失败", ['tempZipFile' => $tempZipFile]);
                return $this->error('文件保存失败');
            }

            // 验证并解压插件
            logger()->info("[插件安装] 开始验证插件结构", ['tempZipFile' => $tempZipFile]);
            $addonInfo = $this->extractAndValidateAddon($tempZipFile, $tempDir);

            if (!$addonInfo) {
                logger()->error("[插件安装] 插件验证失败，返回null");
                $this->cleanupTempFiles($tempDir);
                return $this->error('插件文件格式不正确或缺少必要文件，请检查日志获取详细信息');
            }

            logger()->info("[插件安装] 插件验证成功", ['result' => $addonInfo]);

            // 解析返回结果
            $addonInfoData = $addonInfo['addon_info'];
            $pluginDir = $addonInfo['plugin_dir'];

            // 直接使用压缩包内的目录名作为插件标识符和目录名，保持原名不变
            $actualPluginName = basename($pluginDir);
            $addonId = $actualPluginName; // 直接使用目录名作为插件ID
            $addonDir = BASE_PATH . '/addons/' . $addonId;

            logger()->info("[插件导入] 直接使用压缩包目录名", [
                'zipDirName' => $actualPluginName,
                'addonDir' => $addonDir,
                'addonId' => $addonId
            ]);

            // 检查插件目录是否已存在（按目录名检查）
            if (is_dir($addonDir)) {
                logger()->error("[插件安装] 插件目录已存在", ['dirName' => $actualPluginName, 'addonDir' => $addonDir]);
                $this->cleanupTempFiles($tempDir);
                return $this->error("插件目录 {$actualPluginName} 已经存在，请先删除现有目录或使用其他名称");
            }

            // 直接复制插件目录到addons目录，保持原名
            logger()->info("[插件安装] 复制插件目录到addons", [
                'sourceDir' => $pluginDir,
                'targetDir' => $addonDir
            ]);

            if (!$this->moveDirectory($pluginDir, $addonDir)) {
                logger()->error("[插件安装] 复制插件目录失败", [
                    'sourceDir' => $pluginDir,
                    'addonDir' => $addonDir
                ]);
                $this->cleanupTempFiles($tempDir);
                return $this->error('无法安装插件到addons目录');
            }

            // 清理临时文件
            $this->cleanupTempFiles($tempDir);

            // 执行完整的插件安装流程（包括数据库表管理、资源部署、菜单权限等）
            logger()->info("[插件安装] 执行插件完整安装流程", ['addonId' => $addonId]);
            try {
                $installResult = $this->addonService->installAddon($addonId);
                if (!$installResult) {
                    logger()->error("[插件安装] 插件 {$addonId} 完整安装流程失败");
                    $this->cleanupTempFiles($addonDir); // 清理已安装的插件目录
                    return $this->error('插件安装失败，请查看日志获取详细信息');
                }
            } catch (\Throwable $e) {
                logger()->error("[插件安装] 插件 {$addonId} 安装异常: " . $e->getMessage());
                $this->cleanupTempFiles($addonDir); // 清理已安装的插件目录
                return $this->error('插件安装失败：' . $e->getMessage());
            }

            // 清除插件商店缓存
            $this->addonService->clearStoreAddonsCache();
            logger()->info("[插件安装] 已清除插件商店缓存", ['addonId' => $addonId]);

            logger()->info("[插件安装] 插件 {$addonId} 安装成功", [
                'addonId' => $addonId,
                'addonName' => $addonInfoData['name'] ?? 'unknown',
                'version' => $addonInfoData['version'] ?? 'unknown'
            ]);

            return $this->success([
                'addon_id' => $addonId,
                'addon_name' => $addonInfoData['name'] ?? 'unknown',
                'version' => $addonInfoData['version'] ?? 'unknown',
                'description' => $addonInfoData['description'] ?? ''
            ], '插件导入成功');

        } catch (\Throwable $e) {
            logger()->error("[插件安装] 安装失败", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('插件导入失败：' . $e->getMessage());
        }
    }

    /**
     * 提取并验证插件
     */
    private function extractAndValidateAddon(string $zipFile, string $tempDir): ?array
    {
        logger()->info("[插件导入] 开始验证插件文件", ['zipFile' => basename($zipFile)]);

        // 检查ZipArchive扩展
        if (!class_exists('ZipArchive')) {
            logger()->error("[插件安装] ZipArchive扩展不可用");
            throw new \RuntimeException('服务器不支持ZipArchive，无法安装插件');
        }

        $zip = new \ZipArchive();
        $openResult = $zip->open($zipFile);
        if ($openResult !== true) {
            logger()->error("[插件导入] 无法打开zip文件", ['error_code' => $openResult]);
            throw new \RuntimeException('无法打开zip文件，错误码: ' . $openResult);
        }

        logger()->info("[插件安装] zip文件打开成功", ['file_count' => $zip->numFiles]);

        // 创建解压目录
        $extractDir = $tempDir . '/addon';
        if (!mkdir($extractDir, 0755, true)) {
            $zip->close();
            logger()->error("[插件安装] 无法创建解压目录", ['extractDir' => $extractDir]);
            throw new \RuntimeException('无法创建解压目录');
        }

        logger()->info("[插件安装] 解压目录创建成功", ['extractDir' => $extractDir]);

        // 解压文件
        $extractResult = $zip->extractTo($extractDir);
        $zip->close();

        if (!$extractResult) {
            logger()->error("[插件导入] 解压文件失败");
            throw new \RuntimeException('解压文件失败');
        }

        logger()->info("[插件导入] 文件解压成功");

        // 查找插件目录和info.php文件
        // 插件结构：zip包内有一个文件夹，文件夹内包含info.php等文件
        $pluginDir = null;
        $infoFile = null;

        // 获取解压目录下的所有项
        $items = scandir($extractDir);
        logger()->info("[插件安装] 扫描解压目录内容", [
            'extractDir' => $extractDir,
            'items' => array_filter($items, fn($item) => !in_array($item, ['.', '..']))
        ]);

        // 查找插件目录（排除.和..）
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $extractDir . '/' . $item;
            if (is_dir($itemPath)) {
                // 检查这个目录是否包含info.php
                $potentialInfoFile = $itemPath . '/info.php';
                if (file_exists($potentialInfoFile)) {
                    $pluginDir = $itemPath;
                    $infoFile = $potentialInfoFile;
                    logger()->info("[插件安装] 找到插件目录和info.php文件", [
                        'pluginDir' => $pluginDir,
                        'infoFile' => $infoFile
                    ]);
                    break;
                }
            }
        }

        // 如果没找到插件目录，尝试直接在根目录查找
        if (!$infoFile) {
            $rootInfoFile = $extractDir . '/info.php';
            if (file_exists($rootInfoFile)) {
                $pluginDir = $extractDir;
                $infoFile = $rootInfoFile;
                logger()->info("[插件安装] 在根目录找到info.php文件", ['infoFile' => $infoFile]);
            }
        }

        // 如果仍然没找到info.php，返回null
        if (!$infoFile || !$pluginDir) {
            logger()->error("[插件安装] 未找到插件目录或info.php文件", [
                'extractDir' => $extractDir,
                'items' => array_filter($items, fn($item) => !in_array($item, ['.', '..'])),
                'searched_dirs' => array_filter(array_map(function($item) use ($extractDir) {
                    $path = $extractDir . '/' . $item;
                    return is_dir($path) ? $item : null;
                }, $items), fn($item) => $item !== null)
            ]);
            return null;
        }

        // 读取插件信息
        try {
            $addonInfo = include $infoFile;

            logger()->info("[插件安装] info.php文件内容", [
                'addonInfo_type' => gettype($addonInfo),
                'addonInfo_keys' => is_array($addonInfo) ? array_keys($addonInfo) : null,
                'has_id' => is_array($addonInfo) && isset($addonInfo['id']),
                'has_name' => is_array($addonInfo) && isset($addonInfo['name'])
            ]);

            if (!is_array($addonInfo)) {
                logger()->error("[插件安装] info.php返回值不是数组", ['returned_type' => gettype($addonInfo)]);
                return null;
            }

            if (!isset($addonInfo['id'])) {
                logger()->error("[插件安装] info.php缺少id字段");
                return null;
            }

            if (!isset($addonInfo['name'])) {
                logger()->error("[插件安装] info.php缺少name字段");
                return null;
            }

            logger()->info("[插件安装] 插件信息验证通过", [
                'addon_id' => $addonInfo['id'],
                'addon_name' => $addonInfo['name'],
                'addon_version' => $addonInfo['version'] ?? 'unknown',
                'plugin_dir' => $pluginDir
            ]);

            // 返回包含插件信息和插件目录的数组
            return [
                'addon_info' => $addonInfo,
                'plugin_dir' => $pluginDir
            ];

        } catch (\Throwable $e) {
            logger()->error("[插件安装] 读取info.php文件失败", [
                'error' => $e->getMessage(),
                'file' => $infoFile
            ]);
            return null;
        }
    }

    /**
     * 清理临时文件
     */
    private function cleanupTempFiles(string $tempDir): void
    {
        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }
    }

    /**
     * 从应用商城安装插件
     */
    public function installStoreAddon($addonId)
    {
        logger()->info("[应用商城安装] 开始从应用商城安装插件", ['addonId' => $addonId]);

        try {
            // 首先获取商店插件信息
            $storeAddons = $this->addonService->getStoreAddons(true); // 强制刷新
            $addonInfo = null;

            foreach ($storeAddons as $addon) {
                if ($addon['id'] === $addonId) {
                    $addonInfo = $addon;
                    break;
                }
            }

            if (!$addonInfo) {
                logger()->error("[应用商城安装] 插件不存在", ['addonId' => $addonId]);
                return $this->error('插件在应用商城中不存在');
            }

            // 检查插件是否已经安装
            $localAddon = $this->addonService->getAddonInfoById($addonId);
            if ($localAddon && $localAddon['installed']) {
                logger()->warning("[应用商城安装] 插件已经安装", ['addonId' => $addonId]);
                return $this->error('插件已经安装');
            }

            // 从商店下载插件
            $downloadUrl = $addonInfo['download_url'] ?? '';
            if (!$downloadUrl) {
                logger()->error("[应用商城安装] 插件下载地址不存在", ['addonId' => $addonId]);
                return $this->error('插件下载地址不存在');
            }

            logger()->info("[应用商城安装] 开始下载插件", [
                'addonId' => $addonId,
                'downloadUrl' => $downloadUrl
            ]);

            // 下载插件文件
            $client = $this->addonService->getClientFactory()->create();
            $response = $client->get($downloadUrl, [
                'timeout' => 300, // 5分钟超时
            ]);

            if ($response->getStatusCode() !== 200) {
                logger()->error("[应用商城安装] 下载失败", [
                    'addonId' => $addonId,
                    'statusCode' => $response->getStatusCode()
                ]);
                return $this->error('插件下载失败');
            }

            // 创建临时文件
            $tempDir = BASE_PATH . '/runtime/temp/store_install_' . uniqid();
            if (!mkdir($tempDir, 0755, true)) {
                logger()->error("[应用商城安装] 无法创建临时目录", ['tempDir' => $tempDir]);
                return $this->error('无法创建临时目录');
            }

            $zipFile = $tempDir . '/addon.zip';
            file_put_contents($zipFile, $response->getBody()->getContents());

            logger()->info("[应用商城安装] 插件下载完成，开始验证", [
                'addonId' => $addonId,
                'zipFile' => $zipFile,
                'fileSize' => filesize($zipFile)
            ]);

            // 验证并解压插件
            $addonInfo = $this->extractAndValidateAddon($zipFile, $tempDir);

            if (!$addonInfo) {
                $this->cleanupTempFiles($tempDir);
                logger()->error("[应用商城安装] 插件验证失败", ['addonId' => $addonId]);
                return $this->error('插件文件格式不正确');
            }

            // 安装插件
            $pluginDir = $addonInfo['plugin_dir'];
            $actualPluginName = basename($pluginDir);
            $addonDir = BASE_PATH . '/addons/' . $actualPluginName;

            // 检查插件目录是否已存在
            if (is_dir($addonDir)) {
                $this->cleanupTempFiles($tempDir);
                logger()->error("[应用商城安装] 插件目录已存在", [
                    'addonId' => $addonId,
                    'addonDir' => $addonDir
                ]);
                return $this->error('插件目录已存在');
            }

            // 移动插件到addons目录
            if (!$this->moveDirectory($pluginDir, $addonDir)) {
                $this->cleanupTempFiles($tempDir);
                logger()->error("[应用商城安装] 移动插件失败", [
                    'addonId' => $addonId,
                    'source' => $pluginDir,
                    'target' => $addonDir
                ]);
                return $this->error('无法安装插件到addons目录');
            }

            // 清理临时文件
            $this->cleanupTempFiles($tempDir);

            // 执行插件安装流程
            $installResult = $this->addonService->installAddon($actualPluginName);
            if (!$installResult) {
                $this->cleanupTempFiles($addonDir); // 清理已安装的插件目录
                logger()->error("[应用商城安装] 插件安装流程失败", ['addonId' => $addonId]);
                return $this->error('插件安装失败');
            }

            // 清除插件商店缓存
            $this->addonService->clearStoreAddonsCache();
            logger()->info("[应用商城安装] 已清除插件商店缓存", ['addonId' => $addonId]);

            logger()->info("[应用商城安装] 插件安装成功", [
                'addonId' => $addonId,
                'addonName' => $addonInfo['addon_info']['name'] ?? 'unknown'
            ]);

            return $this->success([
                'addon_id' => $actualPluginName,
                'addon_name' => $addonInfo['addon_info']['name'] ?? 'unknown',
                'version' => $addonInfo['addon_info']['version'] ?? 'unknown'
            ], '插件从应用商城安装成功');

        } catch (\Throwable $e) {
            logger()->error("[应用商城安装] 安装异常", [
                'addonId' => $addonId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('插件安装失败：' . $e->getMessage());
        }
    }

    /**
     * 从应用商城升级插件
     */
    public function upgradeStoreAddon($addonId)
    {
        logger()->info("[应用商城升级] 开始从应用商城升级插件", ['addonId' => $addonId]);

        try {
            // 首先获取商店插件信息
            $storeAddons = $this->addonService->getStoreAddons(true); // 强制刷新
            $addonInfo = null;

            foreach ($storeAddons as $addon) {
                if ($addon['id'] === $addonId) {
                    $addonInfo = $addon;
                    break;
                }
            }

            if (!$addonInfo) {
                logger()->error("[应用商城升级] 插件不存在", ['addonId' => $addonId]);
                return $this->error('插件在应用商城中不存在');
            }

            // 检查插件是否已经安装
            $localAddon = $this->addonService->getAddonInfoById($addonId);
            if (!$localAddon || !$localAddon['installed']) {
                logger()->warning("[应用商城升级] 插件未安装", ['addonId' => $addonId]);
                return $this->error('插件未安装，无法升级');
            }

            // 检查是否需要升级
            if (!$addonInfo['can_upgrade']) {
                logger()->warning("[应用商城升级] 插件已是最新版本", [
                    'addonId' => $addonId,
                    'currentVersion' => $addonInfo['current_version'],
                    'latestVersion' => $addonInfo['version']
                ]);
                return $this->error('插件已是最新版本，无需升级');
            }

            // 从商店下载插件
            $downloadUrl = $addonInfo['download_url'] ?? '';
            if (!$downloadUrl) {
                logger()->error("[应用商城升级] 插件下载地址不存在", ['addonId' => $addonId]);
                return $this->error('插件下载地址不存在');
            }

            logger()->info("[应用商城升级] 开始下载插件", [
                'addonId' => $addonId,
                'currentVersion' => $addonInfo['current_version'],
                'latestVersion' => $addonInfo['version']
            ]);

            // 下载插件文件
            $client = $this->addonService->getClientFactory()->create();
            $response = $client->get($downloadUrl, [
                'timeout' => 300, // 5分钟超时
            ]);

            if ($response->getStatusCode() !== 200) {
                logger()->error("[应用商城升级] 下载失败", [
                    'addonId' => $addonId,
                    'statusCode' => $response->getStatusCode()
                ]);
                return $this->error('插件下载失败');
            }

            // 创建临时文件
            $tempDir = BASE_PATH . '/runtime/temp/upgrade_store_' . uniqid();
            if (!mkdir($tempDir, 0755, true)) {
                logger()->error("[应用商城升级] 无法创建临时目录", ['tempDir' => $tempDir]);
                return $this->error('无法创建临时目录');
            }

            $zipFile = $tempDir . '/addon.zip';
            file_put_contents($zipFile, $response->getBody()->getContents());

            logger()->info("[应用商城升级] 插件下载完成，开始验证", [
                'addonId' => $addonId,
                'zipFile' => $zipFile,
                'fileSize' => filesize($zipFile)
            ]);

            // 验证并解压插件
            $addonInfo = $this->extractAndValidateAddon($zipFile, $tempDir);

            if (!$addonInfo) {
                $this->cleanupTempFiles($tempDir);
                logger()->error("[应用商城升级] 插件验证失败", ['addonId' => $addonId]);
                return $this->error('插件文件格式不正确');
            }

            // 升级插件（覆盖安装）
            $pluginDir = $addonInfo['plugin_dir'];
            $actualPluginName = basename($pluginDir);
            $addonDir = BASE_PATH . '/addons/' . $actualPluginName;

            // 检查插件目录是否存在（应该存在，因为是升级）
            if (!is_dir($addonDir)) {
                $this->cleanupTempFiles($tempDir);
                logger()->error("[应用商城升级] 插件目录不存在，无法升级", [
                    'addonId' => $addonId,
                    'addonDir' => $addonDir
                ]);
                return $this->error('插件目录不存在，无法升级');
            }

            // 备份当前版本（可选）
            $backupDir = $addonDir . '_backup_' . date('Y-m-d_H-i-s');
            if (!$this->moveDirectory($addonDir, $backupDir)) {
                logger()->warning("[应用商城升级] 备份当前版本失败，继续升级", [
                    'addonId' => $addonId,
                    'backupDir' => $backupDir
                ]);
            }

            // 移动新版本到插件目录
            if (!$this->moveDirectory($pluginDir, $addonDir)) {
                // 如果移动失败，尝试恢复备份
                if (is_dir($backupDir)) {
                    $this->moveDirectory($backupDir, $addonDir);
                }
                $this->cleanupTempFiles($tempDir);
                logger()->error("[应用商城升级] 升级失败", [
                    'addonId' => $addonId,
                    'source' => $pluginDir,
                    'target' => $addonDir
                ]);
                return $this->error('插件升级失败');
            }

            // 清理临时文件
            $this->cleanupTempFiles($tempDir);

            // 如果有备份，删除它
            if (is_dir($backupDir)) {
                $this->deleteDirectory($backupDir);
            }

            // 重新安装/启用插件（如果需要的话）
            $installResult = $this->addonService->installAddon($actualPluginName);
            if (!$installResult) {
                logger()->warning("[应用商城升级] 插件重新安装失败，但升级已完成", ['addonId' => $addonId]);
            }

            // 清除插件商店缓存
            $this->addonService->clearStoreAddonsCache();
            logger()->info("[应用商城升级] 已清除插件商店缓存", ['addonId' => $addonId]);

            logger()->info("[应用商城升级] 插件升级成功", [
                'addonId' => $addonId,
                'fromVersion' => $addonInfo['current_version'] ?? 'unknown',
                'toVersion' => $addonInfo['version'] ?? 'unknown'
            ]);

            return $this->success([
                'addon_id' => $actualPluginName,
                'addon_name' => $addonInfo['name'] ?? 'unknown',
                'from_version' => $addonInfo['current_version'] ?? 'unknown',
                'to_version' => $addonInfo['version'] ?? 'unknown'
            ], '插件从应用商城升级成功');

        } catch (\Throwable $e) {
            logger()->error("[应用商城升级] 升级异常", [
                'addonId' => $addonId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('插件升级失败：' . $e->getMessage());
        }
    }

    /**
     * 安装插件（为已存在的插件执行安装操作）
     */
    public function installAddon($addonId)
    {
        $addon = $this->addonService->getAddonInfoById($addonId);

        if (!$addon) {
            return $this->error('插件不存在');
        }

        // 检查插件是否已安装
        if ($addon['installed']) {
            return $this->error('插件已经安装');
        }

        // 检查插件是否已启用
        if ($addon['enabled']) {
            return $this->error('插件已启用，无需安装');
        }

        try {
            $result = $this->addonService->installAddonById($addonId);

            if ($result) {
                logger()->info("[插件安装] 插件 {$addonId} 安装成功");
                return $this->success([], '插件安装成功');
            } else {
                logger()->error("[插件安装] 插件 {$addonId} 安装失败");
                return $this->error('插件安装失败');
            }
        } catch (\Throwable $e) {
            logger()->error("[插件安装] 插件 {$addonId} 安装异常: " . $e->getMessage());
            return $this->error('插件安装失败：' . $e->getMessage());
        }
    }

    /**
     * 卸载插件
     */
    public function uninstall($addonId)
    {
        $result = $this->addonService->uninstallAddon($addonId);

        if ($result) {
            // 清除插件商店缓存
            $this->addonService->clearStoreAddonsCache();
            logger()->info("[插件卸载] 已清除插件商店缓存", ['addonId' => $addonId]);

            return $this->success([], '插件卸载成功');
        } else {
            return $this->error('插件卸载失败');
        }
    }

    /**
     * 删除插件
     */
    public function delete($addonId)
    {
        logger()->info("[插件控制器] 收到删除插件请求", [
            'addonId' => $addonId,
            'user_id' => $this->session->get('admin_user_id'),
            'ip' => $this->request->getServerParams()['remote_addr'] ?? 'unknown'
        ]);

        $addon = $this->addonService->getAddonInfoById($addonId);

        if (!$addon) {
            logger()->warning("[插件控制器] 插件不存在", [
                'addonId' => $addonId,
                'user_id' => $this->session->get('admin_user_id')
            ]);
            return $this->error('插件不存在');
        }

        // 检查插件状态，只有禁用状态才能删除
        if ($addon['enabled']) {
            logger()->warning("[插件控制器] 尝试删除已启用的插件", [
                'addonId' => $addonId,
                'addon_name' => $addon['name'],
                'enabled' => $addon['enabled'],
                'user_id' => $this->session->get('admin_user_id')
            ]);
            return $this->error('只能删除已禁用的插件');
        }

        // 使用 AddonService 获取正确的插件目录名
        $addonDirName = $this->addonService->getAddonDirById($addonId);

        if (!$addonDirName) {
            logger()->error("[插件控制器] 无法获取插件目录名", [
                'addonId' => $addonId,
                'addon_name' => $addon['name'],
                'user_id' => $this->session->get('admin_user_id')
            ]);
            return $this->error('插件目录不存在');
        }

        $addonDir = BASE_PATH . '/addons/' . $addonDirName;

        if (!is_dir($addonDir)) {
            logger()->error("[插件控制器] 插件目录不存在", [
                'addonId' => $addonId,
                'addon_name' => $addon['name'],
                'dir_name' => $addonDirName,
                'expected_dir' => $addonDir,
                'dir_exists' => file_exists($addonDir),
                'is_dir' => is_dir($addonDir),
                'user_id' => $this->session->get('admin_user_id')
            ]);
            return $this->error('插件目录不存在');
        }

        try {
            // 先卸载插件资源（清理已安装的文件）
            logger()->info("[插件删除] 开始卸载插件资源", ['addonId' => $addonId]);
            $uninstallResult = $this->addonService->uninstallAddon($addonId);

            if (!$uninstallResult) {
                logger()->warning("[插件删除] 插件资源卸载失败，但继续删除目录", ['addonId' => $addonId]);
            }

            // 删除插件目录
            logger()->info("[插件删除] 开始删除插件目录", [
                'addonId' => $addonId,
                'addon_name' => $addon['name'],
                'dir_name' => $addonDirName,
                'addonDir' => $addonDir
            ]);
            $deleteResult = $this->deleteDirectory($addonDir);

            if ($deleteResult) {
                logger()->info("[插件删除] 插件目录删除成功", [
                    'addonId' => $addonId,
                    'addon_name' => $addon['name'],
                    'dir_name' => $addonDirName,
                    'addonDir' => $addonDir
                ]);
            } else {
                logger()->error("[插件删除] 插件目录删除失败", [
                    'addonId' => $addonId,
                    'addon_name' => $addon['name'],
                    'dir_name' => $addonDirName,
                    'addonDir' => $addonDir,
                    'user_id' => $this->session->get('admin_user_id')
                ]);
            }

            if (!$deleteResult) {
                logger()->error("[插件控制器] 插件目录删除失败", [
                    'addonId' => $addonId,
                    'addon_name' => $addon['name'],
                    'dir_name' => $addonDirName,
                    'addonDir' => $addonDir,
                    'user_id' => $this->session->get('admin_user_id')
                ]);
                return $this->error('插件目录删除失败');
            }

            // 清除插件商店缓存
            $this->addonService->clearStoreAddonsCache();
            logger()->info("[插件删除] 已清除插件商店缓存", ['addonId' => $addonId]);

            logger()->info("[插件控制器] 插件删除成功", [
                'addonId' => $addonId,
                'addon_name' => $addon['name'],
                'user_id' => $this->session->get('admin_user_id')
            ]);
            return $this->success([], '插件删除成功');

        } catch (\Throwable $e) {
            logger()->error("[插件控制器] 插件删除失败", [
                'addonId' => $addonId,
                'addon_name' => $addon['name'] ?? 'unknown',
                'user_id' => $this->session->get('admin_user_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('插件删除失败：' . $e->getMessage());
        }
    }

    /**
     * 移动目录（递归复制然后删除源目录）
     */
    private function moveDirectory(string $source, string $destination): bool
    {
        try {
            // 首先尝试使用 rename（最简单的方法）
            if (rename($source, $destination)) {
                logger()->info("[插件导入] 使用 rename() 成功移动目录", [
                    'source' => $source,
                    'destination' => $destination
                ]);
                return true;
            }

            // 如果 rename 失败，使用递归复制方法
            logger()->info("[插件导入] rename() 失败，使用递归复制方法", [
                'source' => $source,
                'destination' => $destination
            ]);

            // 确保目标目录不存在
            if (is_dir($destination)) {
                $this->deleteDirectory($destination);
            }

            // 创建目标目录
            if (!mkdir($destination, 0755, true)) {
                logger()->error("[插件导入] 无法创建目标目录", ['destination' => $destination]);
                return false;
            }

            // 递归复制文件和目录
            if (!$this->copyDirectoryRecursive($source, $destination)) {
                logger()->error("[插件导入] 递归复制失败", [
                    'source' => $source,
                    'destination' => $destination
                ]);
                return false;
            }

            // 删除源目录
            if (!$this->deleteDirectory($source)) {
                logger()->warning("[插件导入] 删除源目录失败，但复制成功", [
                    'source' => $source,
                    'destination' => $destination
                ]);
                // 复制成功就算成功，即使删除源目录失败
            }

            logger()->info("[插件导入] 使用递归复制成功移动目录", [
                'source' => $source,
                'destination' => $destination
            ]);

            return true;

        } catch (\Throwable $e) {
            logger()->error("[插件导入] 移动目录异常", [
                'source' => $source,
                'destination' => $destination,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 递归复制目录
     */
    private function copyDirectoryRecursive(string $source, string $destination): bool
    {
        try {
            $dir = opendir($source);
            if (!$dir) {
                logger()->error("[插件导入] 无法打开源目录", ['source' => $source]);
                return false;
            }

            while (($file = readdir($dir)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $sourcePath = $source . '/' . $file;
                $destinationPath = $destination . '/' . $file;

                if (is_dir($sourcePath)) {
                    // 创建子目录并递归复制
                    if (!mkdir($destinationPath, 0755, true)) {
                        logger()->error("[插件导入] 无法创建子目录", ['path' => $destinationPath]);
                        closedir($dir);
                        return false;
                    }

                    if (!$this->copyDirectoryRecursive($sourcePath, $destinationPath)) {
                        closedir($dir);
                        return false;
                    }
                } else {
                    // 复制文件
                    if (!copy($sourcePath, $destinationPath)) {
                        logger()->error("[插件导入] 复制文件失败", [
                            'source' => $sourcePath,
                            'destination' => $destinationPath
                        ]);
                        closedir($dir);
                        return false;
                    }
                }
            }

            closedir($dir);
            return true;

        } catch (\Throwable $e) {
            logger()->error("[插件导入] 递归复制异常", [
                'source' => $source,
                'destination' => $destination,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 递归删除目录
     */
    private function deleteDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $files = array_diff(scandir($directory), ['.', '..']);

        foreach ($files as $file) {
            $path = $directory . '/' . $file;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($directory);
    }

    /**
     * 刷新插件列表
     */
    public function refresh()
    {
        // 清除商店插件缓存
        $this->addonService->clearStoreAddonsCache();

        $addons = $this->addonService->getAllAddons();

        return $this->success([
            'addons' => $addons,
            'count' => count($addons),
        ], '插件列表刷新成功');
    }

    /**
     * 插件配置页面
     */
    public function config($addonId)
    {
        logger()->info("[插件配置] 开始配置插件", ['addonId' => $addonId]);

        $addon = $this->addonService->getAddonInfoById($addonId);
        logger()->info("[插件配置] 获取插件信息", [
            'addonId' => $addonId,
            'addon_exists' => $addon ? true : false,
            'addon_data' => $addon ? array_keys($addon) : null
        ]);

        if (!$addon) {
            logger()->error("[插件配置] 插件不存在", ['addonId' => $addonId]);
            return $this->error('插件不存在');
        }

        $isEnabled = $addon['enabled'] ?? false;
        logger()->info("[插件配置] 插件启用状态", [
            'addonId' => $addonId,
            'enabled' => $isEnabled,
            'addon_name' => $addon['name'] ?? 'unknown'
        ]);

        if (!$isEnabled) {
            logger()->warning("[插件配置] 插件未启用", ['addonId' => $addonId]);
            return $this->error('插件未启用，无法配置');
        }

        $hasConfigs = isset($addon['config']['configs']) && is_array($addon['config']['configs']);
        logger()->info("[插件配置] 插件配置检查", [
            'addonId' => $addonId,
            'has_configs' => $hasConfigs,
            'configs_count' => $hasConfigs ? count($addon['config']['configs']) : 0,
            'config_keys' => isset($addon['config']) ? array_keys($addon['config']) : null,
            'addon_keys' => array_keys($addon)
        ]);

        // 构建配置数据
        $config = [
            'title' => ($addon['name'] ?? '') . ' 配置',
            'model' => 'addon_config',
            'primary_key' => 'id'
        ];

        // 构建表单schema
        $fields = $hasConfigs ? $this->convertAddonFields($addon['config']['configs']) : [];
        $formSchema = [
            'fields' => $fields,
            'endpoints' => [
                'submit' => admin_route("system/addons/{$addonId}/save-config")
            ]
        ];

        return $this->renderAdmin('admin.system.addon.config', [
            'addon' => $addon,
            'config' => $config,
            'configJson' => json_encode($config),
            'formSchemaJson' => json_encode($formSchema)
        ]);
    }

    /**
     * 转换插件配置字段为UniversalFormRenderer支持的格式
     */
    private function convertAddonFields(array $addonFields): array
    {
        return array_map(function ($field) {
            // 插件配置已经使用标准格式，只需要转换选项格式
            $universalField = $field;

            // 处理选项格式转换：从 ['key' => 'label'] 转换为 [{value: 'key', label: 'label'}]
            if (isset($field['options']) && is_array($field['options'])) {
                $universalField['options'] = array_map(function ($value, $label) {
                    return [
                        'value' => $value,
                        'label' => $label
                    ];
                }, array_keys($field['options']), array_values($field['options']));
            }

            // 确保开关有默认值
            if ($field['type'] === 'switch') {
                if (!isset($field['on_value'])) {
                    $universalField['on_value'] = 1;
                }
                if (!isset($field['off_value'])) {
                    $universalField['off_value'] = 0;
                }
            }

            return $universalField;
        }, $addonFields);
    }


    /**
     * 导出插件为zip文件
     */
    public function export($addonId)
    {
        logger()->info("[插件导出] 开始导出插件", ['addonId' => $addonId]);

        $addonsBaseDir = BASE_PATH . '/addons';

        logger()->info("[插件导出] 开始查找插件目录", [
            'addonId' => $addonId,
            'addonsBaseDir' => $addonsBaseDir,
            'addonsBaseDir_exists' => is_dir($addonsBaseDir)
        ]);

        // 通过扫描所有插件目录，找到 ID 匹配的插件
        $actualAddonName = null;

        if (is_dir($addonsBaseDir)) {
            $dirs = scandir($addonsBaseDir);
            logger()->info("[插件导出] 扫描插件目录", [
                'totalDirs' => count($dirs),
                'addonDirs' => array_filter($dirs, function($dir) use ($addonsBaseDir) {
                    return $dir !== '.' && $dir !== '..' && is_dir($addonsBaseDir . '/' . $dir);
                })
            ]);

            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }

                $fullPath = $addonsBaseDir . '/' . $dir;
                if (!is_dir($fullPath)) {
                    continue;
                }

                // 检查这个目录的 info.php 文件
                $infoFile = $fullPath . '/info.php';
                if (!file_exists($infoFile)) {
                    logger()->debug("[插件导出] 跳过没有 info.php 的目录", [
                        'dir' => $dir,
                        'infoFile' => $infoFile
                    ]);
                    continue;
                }

                try {
                    $info = include $infoFile;
                    $pluginId = $info['id'] ?? null;

                    logger()->debug("[插件导出] 检查插件 ID", [
                        'dir' => $dir,
                        'pluginId' => $pluginId,
                        'targetAddonId' => $addonId,
                        'matches' => $pluginId === $addonId
                    ]);

                    if ($pluginId === $addonId) {
                        $actualAddonName = $dir;
                        logger()->info("[插件导出] 通过插件 ID 找到匹配的插件目录", [
                            'addonId' => $addonId,
                            'actualAddonName' => $actualAddonName,
                            'pluginId' => $pluginId,
                            'fullPath' => $fullPath
                        ]);
                        break;
                    }
                } catch (\Throwable $e) {
                    logger()->warning("[插件导出] 读取插件 info.php 失败", [
                        'dir' => $dir,
                        'infoFile' => $infoFile,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
        } else {
            logger()->error("[插件导出] addons 目录不存在", [
                'addonsBaseDir' => $addonsBaseDir,
                'BASE_PATH' => BASE_PATH
            ]);
        }

        if (!$actualAddonName) {
            logger()->error("[插件导出] 未找到匹配的插件目录", [
                'addonId' => $addonId,
                'addonsBaseDir' => $addonsBaseDir,
                'searchedDirs' => is_dir($addonsBaseDir) ? array_filter(scandir($addonsBaseDir), function($dir) use ($addonsBaseDir) {
                    return $dir !== '.' && $dir !== '..' && is_dir($addonsBaseDir . '/' . $dir);
                }) : []
            ]);
            return $this->error('插件不存在');
        }

        // 使用实际的目录名获取插件信息
        $addon = $this->addonService->getAddonInfo($actualAddonName);

        if (!$addon) {
            logger()->warning("[插件导出] 插件信息获取失败", [
                'addonId' => $addonId,
                'actualAddonName' => $actualAddonName
            ]);
            return $this->error('插件不存在');
        }

        // 检查插件状态，只有禁用状态才能导出
        if ($addon['enabled']) {
            logger()->warning("[插件导出] 插件已启用，无法导出", [
                'addonId' => $addonId,
                'actualAddonName' => $actualAddonName,
                'addonName' => $addon['name'],
                'enabled' => $addon['enabled']
            ]);
            return $this->error('只能导出已禁用的插件');
        }

        logger()->info("[插件导出] 插件状态检查通过", [
            'addonId' => $addonId,
            'actualAddonName' => $actualAddonName,
            'addonName' => $addon['name'],
            'enabled' => $addon['enabled']
        ]);

        // 设置插件目录路径
        $actualAddonDir = $addonsBaseDir . '/' . $actualAddonName;

        logger()->info("[插件导出] 插件目录路径", [
            'addonId' => $addonId,
            'actualAddonName' => $actualAddonName,
            'actualAddonDir' => $actualAddonDir,
            'dirExists' => is_dir($actualAddonDir)
        ]);

        if (!is_dir($actualAddonDir)) {
            logger()->error("[插件导出] 插件目录不存在", [
                'addonId' => $addonId,
                'actualAddonName' => $actualAddonName,
                'actualAddonDir' => $actualAddonDir
            ]);
            return $this->error('插件目录不存在');
        }

        // 检查ZipArchive扩展
        if (!class_exists('ZipArchive')) {
            logger()->error("[插件导出] ZipArchive扩展不可用", [
                'addonId' => $addonId,
                'addonName' => $actualAddonName
            ]);
            return $this->error('服务器不支持ZipArchive，无法导出插件');
        }

        logger()->info("[插件导出] ZipArchive扩展检查通过");

        try {
            // 创建临时目录（放在 runtime 目录下）
            $runtimeDir = BASE_PATH . '/runtime';
            logger()->info("[插件导出] 创建临时目录", ['runtimeDir' => $runtimeDir]);

            if (!is_dir($runtimeDir)) {
                mkdir($runtimeDir, 0755, true);
                logger()->info("[插件导出] 创建runtime目录", ['runtimeDir' => $runtimeDir]);
            }

            $tempDir = $runtimeDir . '/temp/addon_export_' . uniqid();
            logger()->info("[插件导出] 创建临时导出目录", ['tempDir' => $tempDir]);

            if (!mkdir($tempDir, 0755, true)) {
                logger()->error("[插件导出] 无法创建临时目录", ['tempDir' => $tempDir]);
                return $this->error('无法创建临时目录');
            }

            // 创建zip文件名（ID + 插件名称 + 时间戳）
            $addonVersion = $addon['version'] ?? 'unknown';
            // 生成简单的临时文件名，后端不处理最终下载文件名
            $zipFileName = 'addon_export_' . $addonId . '_' . date('Ymd_His') . '.zip';
            $zipFilePath = $tempDir . '/' . $zipFileName;

            logger()->info("[插件导出] 创建ZIP文件", [
                'addonId' => $addonId,
                'actualAddonName' => $actualAddonName,
                'addonVersion' => $addonVersion,
                'zipFileName' => $zipFileName,
                'zipFilePath' => $zipFilePath,
                'frontendControlFilename' => true
            ]);

            $zip = new \ZipArchive();
            logger()->info("[插件导出] 打开ZIP文件进行写入", ['zipFilePath' => $zipFilePath]);

            if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                logger()->error("[插件导出] 无法创建ZIP文件", [
                    'zipFilePath' => $zipFilePath,
                    'zipError' => $zip->getStatusString()
                ]);
                $this->cleanupTempFiles($tempDir);
                return $this->error('无法创建ZIP文件');
            }

            logger()->info("[插件导出] 开始添加插件文件到ZIP", [
                'actualAddonDir' => $actualAddonDir,
                'actualAddonName' => $actualAddonName
            ]);

            try {
                // 添加插件目录到zip文件（包含目录名）
                $this->addDirectoryToZip($zip, $actualAddonDir, $actualAddonName);
            } catch (\Throwable $zipException) {
                logger()->error("[插件导出] 添加文件到ZIP时出错", [
                    'error' => $zipException->getMessage(),
                    'actualAddonDir' => $actualAddonDir
                ]);
                $zip->close();
                $this->cleanupTempFiles($tempDir);
                return $this->error('添加文件到ZIP时出错：' . $zipException->getMessage());
            }

            $zip->close();

            logger()->info("[插件导出] ZIP文件创建完成", ['zipFilePath' => $zipFilePath]);

            // 验证ZIP文件是否创建成功
            if (!file_exists($zipFilePath)) {
                logger()->error("[插件导出] ZIP文件不存在", ['zipFilePath' => $zipFilePath]);
                $this->cleanupTempFiles($tempDir);
                return $this->error('ZIP文件创建失败');
            }

            $fileSize = filesize($zipFilePath);
            if ($fileSize === 0) {
                logger()->error("[插件导出] ZIP文件为空", [
                    'zipFilePath' => $zipFilePath,
                    'fileSize' => $fileSize
                ]);
                $this->cleanupTempFiles($tempDir);
                return $this->error('ZIP文件创建失败，文件为空');
            }

            logger()->info("[插件导出] ZIP文件验证通过", [
                'zipFilePath' => $zipFilePath,
                'fileSize' => $fileSize,
                'fileSizeHuman' => $this->formatBytes($fileSize)
            ]);

            // 在创建响应前再次验证文件
            if (!file_exists($zipFilePath) || filesize($zipFilePath) === 0) {
                logger()->error("[插件导出] 文件在最终验证时无效", [
                    'zipFilePath' => $zipFilePath,
                    'fileExists' => file_exists($zipFilePath),
                    'fileSize' => file_exists($zipFilePath) ? filesize($zipFilePath) : 0
                ]);
                $this->cleanupTempFiles($tempDir);
                return $this->error('文件验证失败，请重试');
            }

            $finalFileSize = filesize($zipFilePath);

            logger()->info("[插件导出] 准备返回文件下载响应", [
                'addonId' => $addonId,
                'addonName' => $actualAddonName,
                'zipFileName' => $zipFileName,
                'zipFileSize' => $finalFileSize
            ]);

            try {
                // 创建文件流
                $fileStream = new \Hyperf\HttpMessage\Stream\SwooleFileStream($zipFilePath);

                // 后端不设置下载文件名，由前端控制
                $contentDisposition = 'attachment';

                logger()->info("[插件导出] 后端响应处理", [
                    'fileName' => $zipFileName,
                    'contentDisposition' => $contentDisposition,
                    'frontendControl' => true
                ]);

                // 返回文件下载响应
                $response = $this->response
                    ->withHeader('Content-Type', 'application/zip')
                    ->withHeader('Content-Disposition', $contentDisposition)
                    ->withHeader('Content-Length', $finalFileSize)
                    ->withBody($fileStream);

            } catch (\Throwable $streamException) {
            logger()->error("[插件导出] 创建文件流失败", [
                'addonId' => $addonId,
                'actualAddonName' => $actualAddonName,
                'addonDisplayName' => $addon['name'] ?? $actualAddonName,
                'zipFilePath' => $zipFilePath,
                'zipFileName' => $zipFileName,
                'error' => $streamException->getMessage()
            ]);
                $this->cleanupTempFiles($tempDir);
                return $this->error('文件流创建失败，请重试');
            }

            // 注册清理回调，在响应发送后删除临时文件
            register_shutdown_function(function() use ($tempDir) {
                logger()->info("[插件导出] 开始清理临时文件", ['tempDir' => $tempDir]);
                // 清理整个临时目录
                if (is_dir($tempDir)) {
                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::CHILD_FIRST
                    );

                    foreach ($files as $fileinfo) {
                        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                        $todo($fileinfo->getRealPath());
                    }

                    rmdir($tempDir);
                    logger()->info("[插件导出] 临时文件清理完成", ['tempDir' => $tempDir]);
                }
            });

            logger()->info("[插件导出] 插件导出成功", [
                'addonId' => $addonId,
                'actualAddonName' => $actualAddonName,
                'addonDisplayName' => $addon['name'] ?? $actualAddonName,
                'zipFileName' => $zipFileName,
                'tempDir' => $tempDir
            ]);

            return $response;

        } catch (\Throwable $e) {
            logger()->error('插件导出失败', [
                'addonId' => $addonId,
                'actualAddonName' => $actualAddonName ?? 'unknown',
                'addonDisplayName' => $addon['name'] ?? $actualAddonName ?? 'unknown',
                'tempDir' => $tempDir ?? 'not_created',
                'zipFileName' => $zipFileName ?? 'not_created',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // 清理临时文件
            if (isset($tempDir) && is_dir($tempDir)) {
                try {
                    $this->cleanupTempFiles($tempDir);
                    logger()->info("[插件导出] 临时文件清理完成", ['tempDir' => $tempDir]);
                } catch (\Throwable $cleanupException) {
                    logger()->warning("[插件导出] 临时文件清理失败", [
                        'tempDir' => $tempDir,
                        'error' => $cleanupException->getMessage()
                    ]);
                }
            }

            // 确保返回的是JSON错误响应，而不是文件内容
            return $this->response
                ->json([
                    'code' => 500,
                    'msg' => '插件导出失败：' . $e->getMessage(),
                    'data' => null
                ])
                ->withStatus(500);
        }
    }

    /**
     * 递归添加目录到zip文件
     */
    private function addDirectoryToZip(\ZipArchive $zip, string $directory, string $basePath = '', array $excludePatterns = []): void
    {
        $directory = rtrim($directory, '/');

        logger()->info("[插件导出] 开始添加目录到ZIP", [
            'directory' => $directory,
            'basePath' => $basePath,
            'excludePatterns' => $excludePatterns
        ]);

        if (!is_dir($directory)) {
            logger()->warning("[插件导出] 目录不存在", ['directory' => $directory]);
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $fileCount = 0;
        $dirCount = 0;

        foreach ($files as $file) {
            if (!$file->isReadable()) {
                logger()->debug("[插件导出] 文件不可读，跳过", ['filePath' => $file->getRealPath()]);
                continue;
            }

            $filePath = $file->getRealPath();
            $relativePath = $basePath . '/' . substr($filePath, strlen($directory) + 1);

            // 检查是否需要排除
            $shouldExclude = false;
            foreach ($excludePatterns as $pattern) {
                if (preg_match($pattern, $relativePath)) {
                    $shouldExclude = true;
                    logger()->debug("[插件导出] 文件被排除", [
                        'filePath' => $filePath,
                        'relativePath' => $relativePath,
                        'pattern' => $pattern
                    ]);
                    break;
                }
            }

            if ($shouldExclude) {
                continue;
            }

            if ($file->isFile()) {
                $zip->addFile($filePath, $relativePath);
                $fileCount++;
                logger()->debug("[插件导出] 添加文件到ZIP", [
                    'filePath' => $filePath,
                    'relativePath' => $relativePath,
                    'fileSize' => filesize($filePath)
                ]);
            } elseif ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
                $dirCount++;
                logger()->debug("[插件导出] 添加目录到ZIP", [
                    'dirPath' => $filePath,
                    'relativePath' => $relativePath
                ]);
            }
        }

        logger()->info("[插件导出] 目录添加完成", [
            'directory' => $directory,
            'filesAdded' => $fileCount,
            'dirsAdded' => $dirCount,
            'totalItems' => $fileCount + $dirCount
        ]);
    }

    /**
     * 保存插件配置
     */
    public function saveConfig($addonId)
    {
        logger()->info("[插件配置] 开始保存配置", [
            'addonId' => $addonId,
            'request_method' => $this->request->getMethod(),
            'request_uri' => $this->request->getUri()->getPath()
        ]);

        $addon = $this->addonService->getAddonInfoById($addonId);
        logger()->info("[插件配置] 获取插件信息", [
            'addonId' => $addonId,
            'addon_exists' => $addon ? true : false,
            'addon_name' => $addon ? $addon['name'] ?? 'unknown' : null,
            'addon_enabled' => $addon ? ($addon['enabled'] ?? false) : null
        ]);

        if (!$addon) {
            logger()->error("[插件配置] 插件不存在", ['addonId' => $addonId]);
            return $this->error('插件不存在');
        }

        if (!$addon['enabled']) {
            logger()->warning("[插件配置] 插件未启用", [
                'addonId' => $addonId,
                'addon_name' => $addon['name'] ?? 'unknown'
            ]);
            return $this->error('插件未启用，无法配置');
        }

        $data = $this->request->post();
        logger()->info("[插件配置] 接收到的表单数据", [
            'addonId' => $addonId,
            'data_keys' => array_keys($data),
            'data_count' => count($data),
            'data_sample' => count($data) > 0 ? array_slice($data, 0, 3) : null // 只记录前3个字段作为示例
        ]);

        // 保存配置
        logger()->info("[插件配置] 调用服务保存配置", ['addonId' => $addonId]);
        $result = $this->addonService->saveAddonConfig($addonId, $data);

        if ($result) {
            logger()->info("[插件配置] 配置保存成功", [
                'addonId' => $addonId,
                'addon_name' => $addon['name'] ?? 'unknown'
            ]);
            return $this->success([], '配置保存成功');
        } else {
            logger()->error("[插件配置] 配置保存失败", [
                'addonId' => $addonId,
                'addon_name' => $addon['name'] ?? 'unknown',
                'data_keys' => array_keys($data)
            ]);
            return $this->error('配置保存失败');
        }
    }

    /**
     * 测试插件商店API连接
     */
    public function testStoreApi()
    {
        try {
            logger()->info('[插件商店测试] 开始测试API连接');

            // 获取配置
            $apiUrl = config('addons_store.api_url');
            $apiToken = config('addons_store.api_token');

            logger()->info('[插件商店测试] 配置信息', [
                'apiUrl' => $apiUrl,
                'hasToken' => !empty($apiToken),
                'tokenLength' => $apiToken ? strlen($apiToken) : 0
            ]);

            // 测试基础连接
            try {
                $client = $this->addonService->getClientFactory()->create();

                logger()->info('[插件商店测试] 创建HTTP客户端成功');

                $headers = [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ];

                if ($apiToken) {
                    $headers['Authorization'] = 'Bearer ' . $apiToken;
                }

                logger()->info('[插件商店测试] 准备发送请求', [
                    'url' => $apiUrl,
                    'headers' => array_keys($headers),
                    'timeout' => 10
                ]);

                try {
                    $response = $client->get($apiUrl, [
                        'headers' => $headers,
                        'timeout' => 10, // 缩短超时时间以便快速反馈
                    ]);

                    $statusCode = $response->getStatusCode();
                    $responseBody = $response->getBody()->getContents();

                    logger()->info('[插件商店测试] 收到HTTP响应', [
                        'statusCode' => $statusCode,
                        'responseLength' => strlen($responseBody),
                        'contentType' => $response->getHeaderLine('content-type')
                    ]);

                } catch (\GuzzleHttp\Exception\ConnectException $e) {
                    logger()->error('[插件商店测试] 连接失败', [
                        'url' => $apiUrl,
                        'error' => $e->getMessage()
                    ]);
                    return $this->error('网络连接失败：无法连接到 ' . $apiUrl . ' - ' . $e->getMessage());
                } catch (\GuzzleHttp\Exception\RequestException $e) {
                    $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'unknown';
                    logger()->error('[插件商店测试] HTTP请求失败', [
                        'url' => $apiUrl,
                        'statusCode' => $statusCode,
                        'error' => $e->getMessage()
                    ]);
                    return $this->error('HTTP请求失败 (状态码: ' . $statusCode . ')：' . $e->getMessage());
                } catch (\Throwable $e) {
                    logger()->error('[插件商店测试] 未知错误', [
                        'url' => $apiUrl,
                        'error' => $e->getMessage(),
                        'errorClass' => get_class($e)
                    ]);
                    return $this->error('测试过程中发生错误：' . $e->getMessage());
                }

                logger()->info('[插件商店测试] 收到响应', [
                    'statusCode' => $statusCode,
                    'responseLength' => strlen($responseBody),
                    'responsePreview' => substr($responseBody, 0, 200)
                ]);

                // 解析JSON响应
                $data = json_decode($responseBody, true);
                $jsonError = json_last_error();

                logger()->info('[插件商店测试] JSON解析结果', [
                    'isValidJson' => $jsonError === JSON_ERROR_NONE,
                    'jsonError' => $jsonError,
                    'dataKeys' => is_array($data) ? array_keys($data) : null,
                    'dataCode' => is_array($data) && isset($data['code']) ? $data['code'] : null
                ]);

                if ($jsonError !== JSON_ERROR_NONE) {
                    return $this->error('API返回的不是有效JSON: ' . json_last_error_msg());
                }

            } catch (\Throwable $httpError) {
                logger()->error('[插件商店测试] HTTP请求失败', [
                    'error' => $httpError->getMessage(),
                    'errorClass' => get_class($httpError),
                    'file' => $httpError->getFile(),
                    'line' => $httpError->getLine()
                ]);

                return $this->error('HTTP请求失败: ' . $httpError->getMessage());
            }

            // 如果HTTP请求成功，继续获取插件数据
            $addons = $this->addonService->getStoreAddons(true);

            logger()->info('[插件商店测试] API测试完成', [
                'addons_count' => count($addons),
                'has_addons' => !empty($addons)
            ]);

            return $this->success([
                'addons_count' => count($addons),
                'addons' => $addons,
                'api_config' => [
                    'api_url' => $apiUrl,
                    'has_token' => !empty($apiToken)
                ],
                'http_test' => [
                    'status_code' => $statusCode,
                    'response_length' => strlen($responseBody),
                    'is_valid_json' => $jsonError === JSON_ERROR_NONE,
                    'api_code' => is_array($data) && isset($data['code']) ? $data['code'] : null
                ]
            ], 'API测试完成');

        } catch (\Throwable $e) {
            logger()->error('[插件商店测试] API测试失败', [
                'error' => $e->getMessage(),
                'errorClass' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 1000)
            ]);

            return $this->error('API测试失败：' . $e->getMessage());
        }
    }

    /**
     * 检查插件安装状态
     */
    public function checkAddonStatus()
    {
        try {
            $addonsBaseDir = BASE_PATH . '/addons';
            $addonsStoreDir = $addonsBaseDir . '/AddonsStore';

            $status = [
                'addons_base_dir' => [
                    'path' => $addonsBaseDir,
                    'exists' => is_dir($addonsBaseDir),
                    'writable' => is_writable($addonsBaseDir)
                ],
                'addons_store' => [
                    'path' => $addonsStoreDir,
                    'exists' => is_dir($addonsStoreDir),
                    'info_file' => $addonsStoreDir . '/info.php',
                    'info_exists' => file_exists($addonsStoreDir . '/info.php'),
                    'routes_file' => $addonsStoreDir . '/routes.php',
                    'routes_exists' => file_exists($addonsStoreDir . '/routes.php')
                ]
            ];

            // 尝试读取插件信息
            if ($status['addons_store']['info_exists']) {
                try {
                    $info = include $status['addons_store']['info_file'];
                    $status['addons_store']['info'] = $info;
                } catch (\Throwable $e) {
                    $status['addons_store']['info_error'] = $e->getMessage();
                }
            }

            // 检查插件是否已安装（通过扫描addons目录）
            $localAddons = $this->addonService->scanAddons();
            $addonsStoreInstalled = false;
            $addonsStoreInfo = null;

            foreach ($localAddons as $addon) {
                if ($addon['id'] === 'addons_store') {
                    $addonsStoreInstalled = true;
                    $addonsStoreInfo = $addon;
                    break;
                }
            }

            $status['addons_store']['installed'] = $addonsStoreInstalled;
            $status['addons_store']['installed_info'] = $addonsStoreInfo;

            // API连接测试
            $apiUrl = config('addons_store.api_url');
            $status['api_config'] = [
                'url' => $apiUrl,
                'configured' => !empty($apiUrl)
            ];

            // 测试本地API连接
            if (!empty($apiUrl)) {
                try {
                    $client = $this->addonService->getClientFactory()->create();
                    $response = $client->get($apiUrl, [
                        'timeout' => 5,
                        'headers' => [
                            'Accept' => 'application/json',
                        ]
                    ]);

                    $status['api_connection'] = [
                        'status_code' => $response->getStatusCode(),
                        'success' => $response->getStatusCode() === 200,
                        'response_length' => strlen($response->getBody()->getContents())
                    ];
                } catch (\Throwable $e) {
                    $status['api_connection'] = [
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e)
                    ];
                }
            }

            return $this->success($status, '插件状态检查完成');

        } catch (\Throwable $e) {
            logger()->error('[插件状态检查] 失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error('状态检查失败：' . $e->getMessage());
        }
    }

    /**
     * 测试操作按钮条件渲染（用于调试）
     */
    public function testActionConditions()
    {
        try {
            $addons = $this->addonService->getAllAddons();

            $testResults = [];

            foreach ($addons as $addon) {
                $addonId = $addon['id'] ?? 'unknown';
                $addonName = $addon['name'] ?? 'unknown';
                $source = $addon['source'] ?? 'unknown';
                $installed = $addon['installed'] ?? false;
                $canUpgrade = $addon['can_upgrade'] ?? false;
                $enabled = $addon['enabled'] ?? false;

                // 测试各种条件
                $conditions = [
                    'install_store' => $source === 'store' && !$installed,
                    'upgrade_store' => $source === 'store' && $installed && $canUpgrade,
                    'enable_local' => $source === 'local' && !$enabled,
                    'disable_local' => $source === 'local' && $enabled,
                    'configure_local' => $source === 'local' && $enabled,
                    'export_local' => $source === 'local',
                    'delete_local' => $source === 'local'
                ];

                $testResults[] = [
                    'id' => $addonId,
                    'name' => $addonName,
                    'source' => $source,
                    'installed' => $installed,
                    'can_upgrade' => $canUpgrade,
                    'enabled' => $enabled,
                    'conditions' => $conditions,
                    'visible_buttons' => array_keys(array_filter($conditions, fn($v) => $v))
                ];
            }

            return $this->success([
                'total_addons' => count($addons),
                'test_results' => $testResults,
                'summary' => [
                    'store_addons' => count(array_filter($testResults, fn($r) => $r['source'] === 'store')),
                    'local_addons' => count(array_filter($testResults, fn($r) => $r['source'] === 'local')),
                    'installed_addons' => count(array_filter($testResults, fn($r) => $r['installed'])),
                    'can_upgrade_addons' => count(array_filter($testResults, fn($r) => $r['can_upgrade']))
                ]
            ], '操作按钮条件测试完成');

        } catch (\Throwable $e) {
            logger()->error('[测试] 条件测试失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('测试失败：' . $e->getMessage());
        }
    }

    /**
     * 测试插件交集逻辑（用于调试）
     */
    public function testAddonIntersection()
    {
        try {
            logger()->info('[测试] 开始测试插件交集逻辑');

            // 获取本地插件
            $localAddons = $this->addonService->scanAddons();
            logger()->info('[测试] 本地插件列表', [
                'count' => count($localAddons),
                'addons' => array_map(function($addon) {
                    return [
                        'id' => $addon['id'] ?? 'unknown',
                        'name' => $addon['name'] ?? 'unknown',
                        'version' => $addon['version'] ?? 'unknown'
                    ];
                }, $localAddons)
            ]);

            // 获取商店插件
            $storeAddons = $this->addonService->getStoreAddons(true);
            logger()->info('[测试] 商店插件列表', [
                'count' => count($storeAddons),
                'addons' => array_map(function($addon) {
                    return [
                        'id' => $addon['id'] ?? 'unknown',
                        'name' => $addon['name'] ?? 'unknown',
                        'installed' => $addon['installed'] ?? false,
                        'can_upgrade' => $addon['can_upgrade'] ?? false,
                        'current_version' => $addon['current_version'] ?? '',
                        'version' => $addon['version'] ?? '',
                        'all_keys' => array_keys($addon)
                    ];
                }, $storeAddons)
            ]);

            // 获取所有插件（交集）
            $allAddons = $this->addonService->getAllAddons();
            logger()->info('[测试] 交集后的插件列表', [
                'count' => count($allAddons),
                'addons' => array_map(function($addon) {
                    return [
                        'id' => $addon['id'] ?? 'unknown',
                        'name' => $addon['name'] ?? 'unknown',
                        'source' => $addon['source'] ?? 'unknown',
                        'installed' => $addon['installed'] ?? false,
                        'can_upgrade' => $addon['can_upgrade'] ?? false,
                        'current_version' => $addon['current_version'] ?? '',
                        'version' => $addon['version'] ?? '',
                        'has_installed_field' => isset($addon['installed']),
                        'has_upgrade_field' => isset($addon['can_upgrade']),
                        'all_keys' => array_keys($addon)
                    ];
                }, $allAddons)
            ]);

            // 分析交集结果
            $localIds = array_column($localAddons, 'id');
            $storeIds = array_column($storeAddons, 'id');
            $allIds = array_column($allAddons, 'id');

            $intersection = array_intersect($localIds, $storeIds);
            $localOnly = array_diff($localIds, $storeIds);
            $storeOnly = array_diff($storeIds, $localIds);

            logger()->info('[测试] 交集分析', [
                'local_only_count' => count($localOnly),
                'store_only_count' => count($storeOnly),
                'intersection_count' => count($intersection),
                'total_unique_count' => count($allIds),
                'local_only_ids' => array_values($localOnly),
                'store_only_ids' => array_values($storeOnly),
                'intersection_ids' => array_values($intersection)
            ]);

            return $this->success([
                'local_count' => count($localAddons),
                'store_count' => count($storeAddons),
                'total_unique_count' => count($allAddons),
                'intersection_count' => count($intersection),
                'local_only_count' => count($localOnly),
                'store_only_count' => count($storeOnly),
                'sample_intersection' => count($intersection) > 0 ? array_map(function($addonId) use ($allAddons) {
                    foreach ($allAddons as $addon) {
                        if (($addon['id'] ?? '') === $addonId) {
                            return [
                                'id' => $addonId,
                                'name' => $addon['name'] ?? 'unknown',
                                'source' => $addon['source'] ?? 'unknown',
                                'installed' => $addon['installed'] ?? false,
                                'can_upgrade' => $addon['can_upgrade'] ?? false
                            ];
                        }
                    }
                    return null;
                }, array_slice(array_values($intersection), 0, 3)) : [],
                'data_structure_check' => [
                    'first_addon_keys' => count($allAddons) > 0 ? array_keys($allAddons[0]) : [],
                    'has_install_status' => count($allAddons) > 0 ? isset($allAddons[0]['installed']) : false,
                    'has_upgrade_status' => count($allAddons) > 0 ? isset($allAddons[0]['can_upgrade']) : false
                ]
            ], '插件交集测试完成');

        } catch (\Throwable $e) {
            logger()->error('[测试] 交集测试失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('测试失败：' . $e->getMessage());
        }
    }

    /**
     * 测试filters参数解析（用于调试）
     */
    public function testFiltersParsing()
    {
        try {
            $allParams = $this->request->all();
            $rawFiltersParam = $this->request->input('filters');
            $parsedFilters = [];

            // 模拟listData中的解析逻辑
            if (!empty($rawFiltersParam)) {
                if (is_string($rawFiltersParam)) {
                    $decoded = json_decode($rawFiltersParam, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $parsedFilters = $decoded;
                    }
                } elseif (is_array($rawFiltersParam)) {
                    $parsedFilters = $rawFiltersParam;
                }
            }

            $result = [
                'request_params' => $allParams,
                'raw_filters_param' => $rawFiltersParam,
                'raw_filters_type' => gettype($rawFiltersParam),
                'parsed_filters' => $parsedFilters,
                'parsed_filters_type' => gettype($parsedFilters),
                'json_decode_error' => json_last_error_msg(),
                'source_filter' => $parsedFilters['source'] ?? 'NOT_SET',
                'query_string' => $this->request->getUri()->getQuery(),
                'decoded_query' => urldecode($this->request->getUri()->getQuery())
            ];

            logger()->info('[测试] filters参数解析测试', $result);

            return $this->success($result, 'filters参数解析测试完成');

        } catch (\Throwable $e) {
            logger()->error('[测试] 解析测试失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('测试失败：' . $e->getMessage());
        }
    }

    /**
     * 手动触发商店插件获取（用于调试）
     */
    public function debugStoreList()
    {
        try {
            logger()->info('[调试] 手动触发商店插件获取');

            // 模拟前端发送的filters参数
            $filters = ['source' => 'store'];

            // 复制listData的逻辑
            if (!empty($filters['source']) && $filters['source'] === 'store') {
                logger()->info('[调试] 获取商店插件列表');
                $addons = $this->addonService->getStoreAddons(true);
                logger()->info('[调试] 商店插件数量', ['count' => count($addons)]);

                // 显示前3个插件的详细信息
                if (count($addons) > 0) {
                    logger()->info('[调试] 前3个插件详情', [
                        'addon1' => count($addons) > 0 ? [
                            'id' => $addons[0]['id'],
                            'name' => $addons[0]['name'],
                            'version' => $addons[0]['version'],
                            'download_url' => $addons[0]['download_url'] ?? 'N/A'
                        ] : null,
                        'addon2' => count($addons) > 1 ? [
                            'id' => $addons[1]['id'],
                            'name' => $addons[1]['name'],
                            'version' => $addons[1]['version'],
                            'download_url' => $addons[1]['download_url'] ?? 'N/A'
                        ] : null,
                        'addon3' => count($addons) > 2 ? [
                            'id' => $addons[2]['id'],
                            'name' => $addons[2]['name'],
                            'version' => $addons[2]['version'],
                            'download_url' => $addons[2]['download_url'] ?? 'N/A'
                        ] : null
                    ]);
                }
            } else {
                logger()->info('[调试] 获取所有插件列表');
                $addons = $this->addonService->getAllAddons();
                logger()->info('[调试] 所有插件数量', ['count' => count($addons)]);
            }

            // 过滤逻辑
            if (!empty($filters) && is_array($filters)) {
                $originalCount = count($addons);
                $addons = array_filter($addons, function ($addon) use ($filters) {
                    if (!empty($filters['source'])) {
                        $addonSource = $addon['source'] ?? 'local';
                        if ((string)$addonSource !== (string)$filters['source']) {
                            return false;
                        }
                    }
                    return true;
                });
                $addons = array_values($addons);
                logger()->info('[调试] 过滤后插件数量', [
                    'original' => $originalCount,
                    'filtered' => count($addons)
                ]);
            }

            return $this->success([
                'data' => $addons,
                'total' => count($addons),
                'page' => 1,
                'page_size' => count($addons),
                'last_page' => 1,
                'filters' => $filters,
                'data_structure' => count($addons) > 0 ? array_keys($addons[0]) : []
            ], '调试完成');

        } catch (\Throwable $e) {
            logger()->error('[调试] 调试失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->error('调试失败：' . $e->getMessage());
        }
    }

    /**
     * 格式化文件大小
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int)floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
