<?php

declare(strict_types=1);

namespace App\Service\Admin\Addons;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Guzzle\ClientFactory;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;
use Throwable;

use function Hyperf\Config\config;

/**
 * 插件商店服务
 *
 * 负责与插件商店API交互，获取商店插件信息
 */
class AddonsStoreService
{
    #[Inject]
    protected CacheInterface $cache;

    #[Inject]
    protected ClientFactory $clientFactory;

    /**
     * 本地插件服务（避免循环依赖，使用方法注入）
     */
    private $addonService;

    /**
     * 设置插件服务（用于获取本地插件列表）
     */
    public function setAddonService($addonService): void
    {
        $this->addonService = $addonService;
    }

    /**
     * 获取插件商店插件列表.
     *
     * @param bool $forceRefresh 是否强制刷新（不使用缓存）
     * @return array 商店插件列表
     */
    public function getStoreAddons(bool $forceRefresh = false): array
    {
        try {
            logger()->info('[插件商店] 开始获取商店插件列表', ['forceRefresh' => $forceRefresh]);

            // 从缓存获取（除非强制刷新）
            $cacheKey = 'addon_store_list';
            if (! $forceRefresh) {
                $cached = $this->cache->get($cacheKey);
                if ($cached) {
                    logger()->info('[插件商店] 使用缓存数据', ['count' => count($cached)]);
                    return $cached;
                }
            }

            // 获取配置
            $apiUrl = config('addons_store.api_url');
            $apiToken = config('addons_store.api_token');

            logger()->info('[插件商店] API配置', [
                'apiUrl' => $apiUrl,
                'hasToken' => ! empty($apiToken),
            ]);

            if (! $apiUrl) {
                logger()->warning('[插件商店] API地址未配置');
                return [];
            }

            // 构建请求
            $client = $this->clientFactory->create();
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            if ($apiToken) {
                $headers['Authorization'] = 'Bearer ' . $apiToken;
            }

            // 发送请求
            logger()->info('[插件商店] 发送API请求', [
                'url' => $apiUrl,
                'headers' => array_keys($headers),
                'timeout' => 30,
            ]);

            try {
                $response = $client->get($apiUrl, [
                    'headers' => $headers,
                    'timeout' => 30,
                ]);

                $statusCode = $response->getStatusCode();
                $responseBody = $response->getBody()->getContents();

                logger()->info('[插件商店] API响应成功', [
                    'statusCode' => $statusCode,
                    'responseLength' => strlen($responseBody),
                    'responsePreview' => substr($responseBody, 0, 200),
                    'contentType' => $response->getHeaderLine('content-type'),
                ]);
            } catch (ConnectException $e) {
                logger()->error('[插件商店] 网络连接失败 - 无法连接到服务器', [
                    'error' => $e->getMessage(),
                    'url' => $apiUrl,
                    'handler_context' => method_exists($e, 'getHandlerContext') ? $e->getHandlerContext() : 'N/A',
                ]);
                throw new RuntimeException('无法连接到插件商店服务器: ' . $apiUrl . ' - ' . $e->getMessage());
            } catch (RequestException $e) {
                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 'unknown';
                $responseBody = $e->getResponse() ? substr($e->getResponse()->getBody()->getContents(), 0, 500) : null;

                logger()->error('[插件商店] HTTP请求异常', [
                    'error' => $e->getMessage(),
                    'url' => $apiUrl,
                    'statusCode' => $statusCode,
                    'responsePreview' => $responseBody,
                ]);
                throw new RuntimeException('插件商店API请求失败 (HTTP ' . $statusCode . '): ' . $e->getMessage());
            } catch (Throwable $e) {
                logger()->error('[插件商店] 请求过程中发生未知异常', [
                    'error' => $e->getMessage(),
                    'errorClass' => get_class($e),
                    'url' => $apiUrl,
                ]);
                throw new RuntimeException('插件商店请求异常: ' . $e->getMessage());
            }

            if ($statusCode !== 200) {
                logger()->error('[插件商店] API请求失败', [
                    'status_code' => $statusCode,
                    'response' => $responseBody,
                ]);
                return [];
            }

            $data = json_decode($responseBody, true);
            logger()->info('[插件商店] 解析响应数据', [
                'dataType' => gettype($data),
                'isArray' => is_array($data),
                'hasData' => is_array($data) && isset($data['data']),
                'dataStructure' => is_array($data) && isset($data['data']) ? gettype($data['data']) : null,
                'response' => $data,
            ]);

            // 处理API返回的数据格式
            // 预期格式: {"data": {"data": [...], "total": X, "page": X, ...}}
            if (! $data || ! isset($data['data'])) {
                logger()->error('[插件商店] API返回数据格式错误 - 缺少data字段', ['response' => $data]);
                return [];
            }

            // 如果data字段是数组且包含分页信息，说明是分页数据
            if (is_array($data['data']) && isset($data['data']['data'])) {
                $paginationData = $data['data'];
                $storeAddons = $paginationData['data'] ?? [];
                logger()->info('[插件商店] 检测到分页数据格式', [
                    'total' => $paginationData['total'] ?? 0,
                    'page' => $paginationData['page'] ?? 1,
                    'per_page' => $paginationData['per_page'] ?? 20,
                    'addonsCount' => count($storeAddons),
                ]);
            } else {
                // 兼容旧格式或其他格式
                $storeAddons = is_array($data['data']) ? $data['data'] : [];
                logger()->info('[插件商店] 使用兼容数据格式', [
                    'addonsCount' => count($storeAddons),
                ]);
            }
            logger()->info('[插件商店] 原始插件数据', [
                'storeAddonsCount' => count($storeAddons),
                'firstAddon' => count($storeAddons) > 0 ? array_keys($storeAddons[0]) : null,
            ]);

            // 获取本地插件列表，用于检查安装状态
            $localAddons = $this->getLocalAddons();
            $localAddonMap = [];
            foreach ($localAddons as $localAddon) {
                $identifier = $localAddon['id'] ?? '';
                if (! empty($identifier)) {
                    $localAddonMap[$identifier] = $localAddon;
                }
            }

            logger()->info('[插件商店] 本地插件映射', [
                'localCount' => count($localAddonMap),
                'localAddonIds' => array_keys($localAddonMap),
            ]);

            $processedAddons = [];

            foreach ($storeAddons as $addon) {
                // 从latest_version中获取下载信息
                $latestVersion = $addon['latest_version'] ?? [];
                $storeIdentifier = $addon['identifier'] ?? $addon['id'] ?? '';
                $storeVersion = $addon['version'] ?? '0.0.0';

                // 检查本地安装状态
                $isInstalled = false;
                $canUpgrade = false;
                $currentVersion = '';
                $localAddonInfo = null;

                if (! empty($storeIdentifier) && isset($localAddonMap[$storeIdentifier])) {
                    $isInstalled = true;
                    $localAddonInfo = $localAddonMap[$storeIdentifier];
                    $currentVersion = $localAddonInfo['version'] ?? '0.0.0';

                    // 比较版本号，判断是否可以升级
                    $canUpgrade = $this->compareVersions($storeVersion, $currentVersion) > 0;

                    logger()->debug('[插件商店] 版本比较', [
                        'addonId' => $storeIdentifier,
                        'storeVersion' => $storeVersion,
                        'localVersion' => $currentVersion,
                        'canUpgrade' => $canUpgrade,
                    ]);
                }

                $processedAddon = [
                    'id' => $storeIdentifier, // 使用identifier作为插件ID
                    'name' => $addon['name'] ?? '',
                    'version' => $storeVersion,
                    'description' => $addon['description'] ?? '',
                    'author' => $addon['author'] ?? '',
                    'icon' => $addon['icon'] ?? '',
                    'category' => $addon['category'] ?? '未分类',
                    'source' => 'store', // 标记为商店插件
                    'enabled' => false, // 商店插件默认未启用
                    'installed' => $isInstalled, // 是否已安装
                    'can_upgrade' => $canUpgrade, // 是否可以升级
                    'current_version' => $currentVersion, // 当前本地版本
                    'status' => $addon['status'] == 1 ? 'available' : 'unavailable', // 商店插件状态
                    'price' => $addon['is_free'] ? 0 : ($addon['price'] ?? 0), // 根据is_free判断价格
                    'download_url' => ! empty($latestVersion) ? $this->buildDownloadUrl($latestVersion) : '',
                    'demo_url' => $addon['homepage'] ?? '',
                    'documentation_url' => $addon['repository'] ?? '',
                    'tags' => ! empty($addon['tags']) ? explode(',', $addon['tags']) : [],
                    'screenshots' => $addon['screenshots'] ?? [],
                    'compatibility' => ! empty($latestVersion['compatibility']) ? explode(',', $latestVersion['compatibility']) : [],
                    'downloads' => $addon['downloads'] ?? 0,
                    'rating' => $addon['rating'] ?? 0.0,
                    'reviews_count' => $addon['reviews_count'] ?? 0,
                    'is_official' => ! empty($addon['is_official']),
                    'is_featured' => ! empty($addon['is_featured']),
                    'is_free' => ! empty($addon['is_free']),
                    'package_path' => $addon['package_path'] ?? '',
                    'latest_version' => $latestVersion,
                    'local_addon_info' => $localAddonInfo, // 本地插件信息（用于调试）
                    'created_at' => $addon['created_at'] ?? '',
                    'updated_at' => $addon['updated_at'] ?? '',
                ];
                $processedAddons[] = $processedAddon;
            }

            logger()->info('[插件商店] 处理后的插件数据', [
                'processedCount' => count($processedAddons),
                'sampleAddon' => count($processedAddons) > 0 ? $processedAddons[0] : null,
            ]);

            // 缓存1小时
            $this->cache->set($cacheKey, $processedAddons, 3600);

            return $processedAddons;
        } catch (Throwable $e) {
            logger()->error('获取插件商店数据失败', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 1000), // 只记录前1000个字符
            ]);
            return [];
        }
    }

    /**
     * 清除商店插件缓存.
     */
    public function clearStoreAddonsCache(): void
    {
        try {
            $this->cache->delete('addon_store_list');
        } catch (InvalidArgumentException $e) {
            logger()->error('[插件服务] 删除商店插件缓存失败', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 构建插件下载URL.
     */
    private function buildDownloadUrl(array $version): string
    {
        if (empty($version['id'])) {
            return '';
        }

        // 构建下载URL，指向API的下载端点
        $baseUrl = config('addons_store.api_url');
        $baseUrl = rtrim($baseUrl, '/');

        return $baseUrl . '/versions/' . $version['id'] . '/download';
    }

    /**
     * 比较版本号
     * 返回值: 1表示version1 > version2, -1表示version1 < version2, 0表示相等.
     */
    private function compareVersions(string $version1, string $version2): int
    {
        // 使用PHP的version_compare函数进行版本比较
        // version_compare 返回: -1, 0, 1
        return version_compare($version1, $version2);
    }

    /**
     * 获取本地插件列表
     */
    private function getLocalAddons(): array
    {
        if ($this->addonService && method_exists($this->addonService, 'scanAddons')) {
            return $this->addonService->scanAddons();
        }

        logger()->warning('[插件商店] AddonService未设置，无法获取本地插件列表');
        return [];
    }
}
