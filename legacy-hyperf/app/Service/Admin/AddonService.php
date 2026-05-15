<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Service\Admin;

use App\Exception\BusinessException;
use App\Model\Admin\AdminMenu;
use App\Model\Admin\AdminPermission;
use App\Service\Admin\Addons\AddonsAssetsService;
use App\Service\Admin\Addons\AddonsCheckService;
use App\Service\Admin\Addons\AddonsConfigService;
use App\Service\Admin\Addons\AddonsMenuService;
use App\Service\Admin\Addons\AddonsMysqlService;
use App\Service\Admin\Addons\AddonsPermissionsService;
use App\Service\Admin\Addons\AddonsPgsqlService;
use App\Service\Admin\Addons\AddonsStoreService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Guzzle\ClientFactory;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;
use Throwable;

use function Hyperf\Config\config;
use function Hyperf\Support\now;

/**
 * 插件管理服务
 *
 * 负责扫描和管理addons目录下的插件
 */
class AddonService
{
    #[Inject]
    protected CacheInterface $cache;

    #[Inject]
    protected ClientFactory $clientFactory;

    #[Inject]
    protected AddonsMysqlService $mysqlService;

    #[Inject]
    protected AddonsAssetsService $assetsService;

    #[Inject]
    protected AddonsMenuService $menuService;

    #[Inject]
    protected AddonsPermissionsService $permissionsService;

    #[Inject]
    protected AddonsPgsqlService $pgsqlService;

    #[Inject]
    protected AddonsConfigService $configService;

    #[Inject]
    protected AddonsStoreService $storeService;

    #[Inject]
    protected AddonsCheckService $checkService;

    /**
     * 插件目录路径.
     */
    private string $addonPath;

    public function __construct()
    {
        $this->addonPath = BASE_PATH . '/addons';

        // 设置商店服务的插件服务引用（避免循环依赖）
        if ($this->storeService) {
            $this->storeService->setAddonService($this);
        }
    }

    /**
     * 扫描所有插件.
     *
     * @return array 插件列表
     */
    public function scanAddons(): array
    {
        $addons = [];

        if (! is_dir($this->addonPath)) {
            return $addons;
        }

        $dirs = scandir($this->addonPath);
        if (! $dirs) {
            return $addons;
        }

        foreach ($dirs as $dir) {
            // 跳过特殊目录
            if ($dir === '.' || $dir === '..' || ! is_dir($this->addonPath . '/' . $dir)) {
                continue;
            }

            $addonInfo = $this->getAddonInfo($dir);
            if ($addonInfo) {
                $addons[] = $addonInfo;
            }
        }

        // 按插件名称排序
        usort($addons, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $addons;
    }

    /**
     * 获取插件商店插件列表.
     *
     * @param bool $forceRefresh 是否强制刷新（不使用缓存）
     * @return array 商店插件列表
     */
    public function getStoreAddons(bool $forceRefresh = false): array
    {
        return $this->storeService->getStoreAddons($forceRefresh);
    }

    /**
     * 获取所有插件（本地 + 商店，去重交集）.
     *
     * @return array 合并后的插件列表（以商店为准，只保留单个插件条目）
     */
    public function getAllAddons(): array
    {
        logger()->info('[插件服务] 开始获取所有插件（交集模式）');

        $localAddons = $this->scanAddons();
        logger()->info('[插件服务] 本地插件扫描完成', [
            'local_count' => count($localAddons),
            'sample_local' => count($localAddons) > 0 ? $localAddons[0]['id'] ?? 'unknown' : null,
        ]);

        // 获取商店插件（已包含安装状态检查）
        $storeAddons = $this->getStoreAddons();
        logger()->info('[插件服务] 商店插件获取完成', [
            'store_count' => count($storeAddons),
            'sample_store' => count($storeAddons) > 0 ? $storeAddons[0]['id'] ?? 'unknown' : null,
        ]);

        // 创建本地插件映射（用于查找）
        $localAddonMap = [];
        foreach ($localAddons as $localAddon) {
            $identifier = $localAddon['id'] ?? '';
            if (! empty($identifier)) {
                $localAddonMap[$identifier] = $localAddon;
            }
        }

        // 合并插件列表（以商店为准，进行交集）
        $allAddons = [];
        $processedIds = []; // 已处理的插件ID，避免重复

        // 首先处理商店插件（优先级高，包含完整信息）
        foreach ($storeAddons as $storeAddon) {
            $addonId = $storeAddon['id'] ?? '';
            if (! empty($addonId)) {
                // 检查本地是否也有安装，如果有则使用本地的启用状态
                if (isset($localAddonMap[$addonId])) {
                    $localAddon = $localAddonMap[$addonId];
                    // 使用本地插件的实际启用状态覆盖商店数据
                    $storeAddon['enabled'] = $localAddon['enabled'] ?? false;
                    $storeAddon['installed'] = true; // 如果本地有安装，则标记为已安装
                    $storeAddon['current_version'] = $localAddon['version'] ?? $storeAddon['version'] ?? '';

                    logger()->debug('[插件服务] 商店插件与本地插件交集，使用本地启用状态', [
                        'id' => $addonId,
                        'store_name' => $storeAddon['name'] ?? 'unknown',
                        'local_enabled' => $localAddon['enabled'] ?? false,
                        'installed' => true,
                    ]);
                }

                $allAddons[] = $storeAddon;
                $processedIds[$addonId] = true;
                logger()->debug('[插件服务] 添加商店插件到结果集', [
                    'id' => $addonId,
                    'name' => $storeAddon['name'] ?? 'unknown',
                    'installed' => $storeAddon['installed'] ?? false,
                    'enabled' => $storeAddon['enabled'] ?? false,
                ]);
            }
        }

        // 然后处理本地独有插件（不在商店中的）
        foreach ($localAddons as $localAddon) {
            $addonId = $localAddon['id'] ?? '';
            if (! empty($addonId) && ! isset($processedIds[$addonId])) {
                // 为本地插件添加source字段和其他必要字段
                $localAddon['source'] = 'local';
                $localAddon['installed'] = true; // 本地插件默认已安装
                $localAddon['can_upgrade'] = false; // 本地独有插件无法升级
                $localAddon['current_version'] = $localAddon['version'] ?? '';
                $localAddon['is_free'] = true; // 本地插件默认免费

                $allAddons[] = $localAddon;
                logger()->debug('[插件服务] 添加本地独有插件到结果集', [
                    'id' => $addonId,
                    'name' => $localAddon['name'] ?? 'unknown',
                ]);
            }
        }

        logger()->info('[插件服务] 插件列表交集合并完成', [
            'store_count' => count($storeAddons),
            'local_only_count' => count($localAddons) - count(array_intersect_key($localAddonMap, array_flip(array_column($storeAddons, 'id')))),
            'total_unique_count' => count($allAddons),
        ]);

        // 统计source分布
        $sourceStats = [];
        $installStats = ['installed' => 0, 'not_installed' => 0, 'can_upgrade' => 0];
        foreach ($allAddons as $addon) {
            $source = $addon['source'] ?? 'unknown';
            $sourceStats[$source] = ($sourceStats[$source] ?? 0) + 1;

            if (! empty($addon['installed'])) {
                ++$installStats['installed'];
                if (! empty($addon['can_upgrade'])) {
                    ++$installStats['can_upgrade'];
                }
            } else {
                ++$installStats['not_installed'];
            }
        }
        logger()->info('[插件服务] 最终统计', [
            'source_stats' => $sourceStats,
            'install_stats' => $installStats,
            'total_addons' => count($allAddons),
        ]);

        // 按插件名称排序
        usort($allAddons, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        logger()->info('[插件服务] 插件排序完成，按名称排序');

        return $allAddons;
    }

    /**
     * 获取HTTP客户端工厂
     */
    public function getClientFactory(): ClientFactory
    {
        return $this->clientFactory;
    }

    /**
     * 清除商店插件缓存.
     */
    public function clearStoreAddonsCache(): void
    {
        $this->storeService->clearStoreAddonsCache();
    }

    /**
     * 根据插件ID获取插件目录名
     * 插件ID使用下划线命名法，目录名使用驼峰命名法.
     *
     * @param string $addonId 插件ID（如：addons_store）
     * @return string 插件目录名（如：AddonsStore）
     */
    public function getAddonDirById(string $addonId): string
    {
        // 将下划线命名法转换为驼峰命名法
        $dirName = $this->convertIdToDirName($addonId);

        logger()->debug('[插件映射] ID转换为目录名', [
            'addonId' => $addonId,
            'dirName' => $dirName,
        ]);

        return $dirName;
    }

    /**
     * 根据插件ID获取插件信息.
     *
     * @param string $addonId 插件ID
     * @return null|array 插件信息
     */
    public function getAddonInfoById(string $addonId): ?array
    {
        $dirName = $this->getAddonDirById($addonId);
        return $this->getAddonInfo($dirName);
    }

    /**
     * 根据插件ID启用插件.
     *
     * @param string $addonId 插件ID
     */
    public function enableAddonById(string $addonId): bool
    {
        $dirName = $this->getAddonDirById($addonId);
        return $this->enableAddon($dirName);
    }

    /**
     * 根据插件ID禁用插件.
     *
     * @param string $addonId 插件ID
     */
    public function disableAddonById(string $addonId): bool
    {
        $dirName = $this->getAddonDirById($addonId);
        return $this->disableAddon($dirName);
    }

    /**
     * 根据插件ID安装插件.
     *
     * @param string $addonId 插件ID
     */
    public function installAddonById(string $addonId): bool
    {
        $dirName = $this->getAddonDirById($addonId);
        return $this->installAddon($dirName);
    }

    /**
     * 根据插件ID卸载插件.
     *
     * @param string $addonId 插件ID
     */
    public function uninstallAddonById(string $addonId): bool
    {
        $dirName = $this->getAddonDirById($addonId);
        return $this->uninstallAddon($dirName);
    }

    /**
     * 获取插件信息.
     *
     * @param string $addonName 插件目录名
     * @return null|array 插件信息
     */
    public function getAddonInfo(string $addonName): ?array
    {
        logger()->info('[插件信息] 开始获取插件信息', ['addonName' => $addonName]);

        $addonDir = $this->addonPath . '/' . $addonName;
        $infoFile = $addonDir . '/info.php';

        logger()->info('[插件信息] 检查info.php文件', [
            'addonName' => $addonName,
            'addonDir' => $addonDir,
            'infoFile' => $infoFile,
            'infoFile_exists' => file_exists($infoFile),
        ]);

        if (! file_exists($infoFile)) {
            logger()->error('[插件信息] info.php文件不存在', ['addonName' => $addonName, 'infoFile' => $infoFile]);
            return null;
        }

        try {
            $info = include $infoFile;

            logger()->info('[插件信息] 读取info.php内容', [
                'addonName' => $addonName,
                'info_type' => gettype($info),
                'info_is_array' => is_array($info),
                'info_keys' => is_array($info) ? array_keys($info) : null,
            ]);

            if (! is_array($info)) {
                logger()->error('[插件信息] info.php返回的不是数组', ['addonName' => $addonName, 'info' => $info]);
                return null;
            }

            // 验证必要字段
            if (! isset($info['id'], $info['name'], $info['version'])) {
                logger()->error('[插件信息] 缺少必要字段', [
                    'addonName' => $addonName,
                    'has_id' => isset($info['id']),
                    'has_name' => isset($info['name']),
                    'has_version' => isset($info['version']),
                ]);
                return null;
            }

            // 添加额外信息
            $info['directory'] = $addonName;
            $info['path'] = $addonDir;
            $info['installed'] = $this->isAddonInstalled($addonName);
            $info['enabled'] = $this->isAddonEnabled($addonName);
            $info['has_controller'] = is_dir($addonDir . '/Controller');
            $info['has_routes'] = file_exists($addonDir . '/routes.php');
            $info['has_views'] = is_dir($addonDir . '/View');
            $info['has_public'] = is_dir($addonDir . '/Public');
            $info['has_manager'] = is_dir($addonDir . '/Manager');
            $info['has_config'] = file_exists($addonDir . '/config.php');

            // 读取插件配置
            $configData = $this->getAddonConfig($addonName);
            $info['config'] = $configData;

            logger()->info('[插件信息] 插件配置读取', [
                'addonName' => $addonName,
                'config_type' => gettype($configData),
                'config_is_array' => is_array($configData),
                'config_keys' => is_array($configData) ? array_keys($configData) : null,
                'has_configs' => is_array($configData) && isset($configData['configs']),
                'configs_count' => is_array($configData) && isset($configData['configs']) && is_array($configData['configs']) ? count($configData['configs']) : 0,
            ]);

            // 读取插件菜单和权限配置
            $info['menus_permissions'] = $this->getAddonMenusAndPermissions($addonName);

            logger()->info('[插件信息] 插件信息获取完成', [
                'addonName' => $addonName,
                'final_info_keys' => array_keys($info),
                'enabled' => $info['enabled'],
                'has_configs_in_final' => isset($info['config']['configs']) && is_array($info['config']['configs']),
            ]);

            return $info;
        } catch (Throwable $e) {
            logger()->error('[插件信息] 获取插件信息异常', [
                'addonName' => $addonName,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * 检查插件是否已安装.
     *
     * @param string $addonName 插件目录名
     */
    public function isAddonInstalled(string $addonName): bool
    {
        // 这里可以实现安装状态检查逻辑
        // 目前简单返回true，实际项目中可以检查数据库或配置文件
        return true;
    }

    /**
     * 检查插件是否已启用.
     *
     * @param string $addonName 插件目录名
     */
    public function isAddonEnabled(string $addonName): bool
    {
        // 只检查插件自己的配置文件中的 enabled 状态
        $pluginConfig = $this->getAddonConfig($addonName);
        return isset($pluginConfig['enabled']) && $pluginConfig['enabled'];
    }

    /**
     * 获取首页替换插件.
     *
     * 查找所有启用的插件中配置了 replace_homepage 的插件，
     * 返回优先级最高的插件信息
     *
     * @return null|array 返回插件信息，包含：
     *                    - addon_name: 插件目录名
     *                    - controller: 控制器类名
     *                    - action: 方法名
     *                    - middleware: 额外中间件
     *                    - priority: 优先级
     */
    public function getHomepageReplacementAddon(): ?array
    {
        $addons = $this->scanAddons();
        $homepageReplacers = [];

        foreach ($addons as $addon) {
            $addonName = $addon['name'];

            // 只检查启用的插件
            if (! $this->isAddonEnabled($addonName)) {
                continue;
            }

            $config = $this->getAddonConfig($addonName);

            // 检查是否有首页替换配置
            if (! isset($config['replace_homepage'])
                || ! is_array($config['replace_homepage'])
                || ! isset($config['replace_homepage']['enabled'])
                || ! $config['replace_homepage']['enabled']) {
                continue;
            }

            $replacementConfig = $config['replace_homepage'];

            // 验证必需字段
            if (! isset($replacementConfig['controller']) || ! isset($replacementConfig['action'])) {
                logger()->warning("[首页替换] 插件 {$addonName} 首页替换配置不完整，缺少必需字段", [
                    'config' => $replacementConfig,
                ]);
                continue;
            }

            $homepageReplacers[] = [
                'addon_name' => $addonName,
                'controller' => $replacementConfig['controller'],
                'action' => $replacementConfig['action'],
                'middleware' => $replacementConfig['middleware'] ?? [],
                'priority' => $replacementConfig['priority'] ?? 10,
            ];
        }

        // 按优先级排序（优先级高的在前）
        usort($homepageReplacers, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        // 返回优先级最高的插件
        return $homepageReplacers[0] ?? null;
    }

    /**
     * 启用插件.
     *
     * 启用插件时会自动执行安装操作（部署资源文件）
     * 启用前会先执行环境检查
     *
     * @param string $addonName 插件目录名
     */
    public function enableAddon(string $addonName): bool
    {
        logger()->info("[插件管理] 开始启用插件: {$addonName}");

        try {
            // 1. 执行启用前环境检查（由 AddonsCheckService 处理）
            logger()->info("[插件管理] 执行启用前环境检查: {$addonName}");
            $checkResult = $this->checkService->checkAddonEnableRequirements($addonName);
            if (! $checkResult['passed']) {
                logger()->error("[插件管理] 插件 {$addonName} 环境检查未通过: " . json_encode($checkResult['errors'], JSON_UNESCAPED_UNICODE));

                // 抛出业务异常，将检查错误信息传递给前端
                $errorMessage = "插件 [{$addonName}] 环境检查未通过";
                $exception = new BusinessException(
                    400, // 客户端错误
                    $errorMessage,
                    null,
                    [
                        'check_errors' => $checkResult['errors'] ?? [],
                        'check_warnings' => $checkResult['warnings'] ?? [],
                    ]
                );
                $exception->withData('check_result', $checkResult);
                throw $exception;
            }
            logger()->info("[插件管理] 插件 {$addonName} 环境检查通过");

            // 2. 执行插件安装（启用即视为安装）
            logger()->info("[插件管理] 执行插件安装: {$addonName}");
            $installResult = $this->installAddon($addonName);
            if (! $installResult) {
                logger()->error("[插件管理] 插件 {$addonName} 安装失败，终止启用流程");
                return false;
            }

            // 3. 更新插件自己的配置文件中的enabled状态
            $this->updateAddonConfigValue($addonName, 'enabled', true);
            logger()->info("[插件管理] 更新插件配置: {$addonName} -> enabled");

            // 4. 部署插件资源文件
            logger()->info("[插件管理] 部署插件资源文件: {$addonName}");
            $this->assetsService->deployAddonAssets($addonName);

            // 5. 安装菜单和权限
            logger()->info("[插件管理] 安装菜单和权限: {$addonName}");
            $this->installAddonMenusAndPermissions($addonName);

            // 6. 执行插件启用钩子
            $this->executeAddonHook($addonName, 'enable');

            logger()->info("[插件管理] 插件 {$addonName} 启用成功");
            return true;
        } catch (BusinessException $e) {
            // 业务异常直接抛出，由全局异常处理器处理
            throw $e;
        } catch (Throwable $e) {
            logger()->error("[插件管理] 插件 {$addonName} 启用失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 禁用插件.
     *
     * 禁用插件时会自动清理相关的文件和目录，但保留数据表
     *
     * @param string $addonName 插件目录名
     */
    public function disableAddon(string $addonName): bool
    {
        logger()->info("[插件管理] 开始禁用插件: {$addonName}");

        try {
            // 禁用菜单和权限
            logger()->info("[插件管理] 禁用菜单和权限: {$addonName}");
            $this->disableAddonMenusAndPermissions($addonName);

            // 执行插件资源清理（清理文件和目录，保留数据表）
            logger()->info("[插件管理] 执行插件资源清理: {$addonName}");
            $uninstallResult = $this->assetsService->uninstallAddonAssets($addonName);
            if (! $uninstallResult) {
                logger()->warning("[插件管理] 插件 {$addonName} 文件清理失败，但继续禁用流程");
            }

            // 更新插件自己的配置文件中的enabled状态
            $this->updateAddonConfigValue($addonName, 'enabled', false);
            logger()->info("[插件管理] 更新插件配置: {$addonName} -> disabled");

            // 执行插件禁用钩子
            $this->executeAddonHook($addonName, 'disable');

            logger()->info("[插件管理] 插件 {$addonName} 禁用成功");
            return true;
        } catch (Throwable $e) {
            logger()->error("[插件管理] 插件 {$addonName} 禁用失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新插件配置中的单个值
     *
     * @param string $addonName 插件名称
     * @param string $key 配置键（支持点号分隔的嵌套键，如 'test.debug_mode'）
     * @param mixed $value 配置值
     */
    public function updateAddonConfigValue(string $addonName, string $key, $value): bool
    {
        return $this->configService->updateAddonConfigValue($addonName, $key, $value);
    }

    /**
     * 保存插件配置.
     *
     * @param string $addonId 插件ID
     * @param array $configData 配置数据
     */
    public function saveAddonConfig(string $addonId, array $configData): bool
    {
        return $this->configService->saveAddonConfig($addonId, $configData);
    }

    /**
     * 安装插件资源文件.
     *
     * @param string $addonName 插件目录名
     */
    public function installAddon(string $addonName): bool
    {
        $addonDir = $this->addonPath . '/' . $addonName;
        $assetsFile = $addonDir . '/Manager/assets.json';
        $databaseFile = $addonDir . '/Manager/database.json';

        logger()->info("[插件安装] 开始安装插件: {$addonName}");

        // 执行插件安装钩子
        $this->executeAddonHook($addonName, 'install');

        // 执行数据库表管理
        logger()->info("[插件安装] 检查数据库配置文件: {$addonName}");
        if (file_exists($databaseFile)) {
            logger()->info('[插件安装] 发现数据库配置文件，开始执行数据库表管理');
            $dbResult = $this->mysqlService->manageDatabaseTables($addonName, $databaseFile);
            if (! $dbResult) {
                logger()->error("[插件安装] 插件 {$addonName} 数据库表管理失败");
                return false;
            }
        } else {
            logger()->info("[插件安装] 插件 {$addonName} 没有数据库配置文件，跳过数据库表管理");
        }

        // 执行PostgreSQL特有功能管理
        $pgsqlFile = $addonDir . '/Manager/pgsql.json';
        logger()->info("[插件安装] 检查PostgreSQL配置文件: {$addonName}");
        if (file_exists($pgsqlFile)) {
            logger()->info('[插件安装] 发现PostgreSQL配置文件，开始执行PostgreSQL特有功能管理');
            $pgsqlResult = $this->pgsqlService->managePgsqlDatabase($addonName, $pgsqlFile);
            if (! $pgsqlResult) {
                logger()->error("[插件安装] 插件 {$addonName} PostgreSQL特有功能管理失败");
                return false;
            }
        } else {
            logger()->info("[插件安装] 插件 {$addonName} 没有PostgreSQL配置文件，跳过PostgreSQL特有功能管理");
        }

        // 插件安装只处理数据库，不处理资源文件、菜单和权限
        logger()->info("[插件安装] 插件 {$addonName} 安装完成（只处理数据库）");
        return true;
    }

    /**
     * 卸载插件.
     *
     * @param string $addonName 插件目录名
     */

    /**
     * 执行PostgreSQL测试查询
     *
     * @param string $addonName 插件名称
     * @return array 测试结果
     */
    public function executePgsqlTestQueries(string $addonName): array
    {
        $pgsqlFile = $this->addonPath . '/' . $addonName . '/Manager/pgsql.json';

        if (!file_exists($pgsqlFile)) {
            logger()->warning("[PostgreSQL测试] 插件 {$addonName} 没有PostgreSQL配置文件");
            return [];
        }

        try {
            $configContent = file_get_contents($pgsqlFile);
            $config = json_decode($configContent, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($config['test_queries'])) {
                logger()->warning("[PostgreSQL测试] 插件 {$addonName} 没有测试查询配置");
                return [];
            }

            logger()->info("[PostgreSQL测试] 开始执行插件 {$addonName} 的PostgreSQL测试查询");
            $results = $this->pgsqlService->executeTestQueries($addonName, $config['test_queries']);
            logger()->info("[PostgreSQL测试] 插件 {$addonName} 的PostgreSQL测试查询执行完成");

            return $results;

        } catch (Throwable $e) {
            logger()->error("[PostgreSQL测试] 执行插件 {$addonName} 的PostgreSQL测试查询失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 执行插件钩子.
     *
     * @param string $addonName 插件名称
     * @param string $hook 钩子名称 (install, enable, disable, uninstall)
     */
    public function executeAddonHook(string $addonName, string $hook): bool
    {
        try {
            $addonDir = $this->addonPath . '/' . $addonName;
            $setupFile = $addonDir . '/Manager/Setup.php';

            if (! file_exists($setupFile)) {
                logger()->info("[插件钩子] 插件 {$addonName} 没有 Setup.php 文件，跳过 {$hook} 钩子");
                return true;
            }

            // 构建类名
            $className = "Addons\\{$addonName}\\Manager\\Setup";

            if (! class_exists($className)) {
                logger()->warning("[插件钩子] 插件 {$addonName} 的 Setup 类不存在: {$className}");
                return false;
            }

            logger()->info("[插件钩子] 开始执行插件 {$addonName} 的 {$hook} 钩子");

            // 实例化 Setup 类
            $setupInstance = new $className();

            // 检查方法是否存在
            if (! method_exists($setupInstance, $hook)) {
                logger()->warning("[插件钩子] 插件 {$addonName} 的 Setup 类没有 {$hook} 方法");
                return false;
            }

            // 调用钩子方法
            $result = $setupInstance->{$hook}();

            if ($result) {
                logger()->info("[插件钩子] 插件 {$addonName} 的 {$hook} 钩子执行成功");
                return true;
            }
            logger()->error("[插件钩子] 插件 {$addonName} 的 {$hook} 钩子执行失败");
            return false;
        } catch (Throwable $e) {
            logger()->error("[插件钩子] 执行插件 {$addonName} 的 {$hook} 钩子异常: " . $e->getMessage());
            return false;
        }
    }

    public function uninstallAddon(string $addonName): bool
    {
        logger()->info("[插件卸载] 开始卸载插件: {$addonName}");

        try {
            // 执行插件卸载钩子
            $this->executeAddonHook($addonName, 'uninstall');

            // 执行插件资源卸载
            $result = $this->assetsService->uninstallAddonAssets($addonName);

            if ($result) {
                logger()->info("[插件卸载] 插件 {$addonName} 卸载完成");
            } else {
                logger()->warning("[插件卸载] 插件 {$addonName} 部分卸载失败");
            }

            return $result;
        } catch (Throwable $e) {
            logger()->error("[插件卸载] 插件 {$addonName} 卸载失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取插件配置.
     *
     * @param string $addonName 插件名称
     */
    public function getAddonConfig(string $addonName): array
    {
        return $this->configService->getAddonConfig($addonName);
    }

    /**
     * 设置插件配置.
     *
     * @param string $addonName 插件名称
     * @param array $config 配置数据
     * @param bool $preserveFormat 是否保持原有格式
     */
    public function setAddonConfig(string $addonName, array $config, bool $preserveFormat = true): bool
    {
        return $this->configService->setAddonConfig($addonName, $config, $preserveFormat);
    }


    /**
     * 将插件ID转换为目录名
     * 下划线命名法 -> 驼峰命名法.
     *
     * @param string $addonId 插件ID
     * @return string 目录名
     */
    private function convertIdToDirName(string $addonId): string
    {
        // 将下划线分隔的字符串转换为驼峰命名
        $parts = explode('_', $addonId);
        $dirName = '';

        foreach ($parts as $part) {
            $dirName .= ucfirst($part);
        }

        return $dirName;
    }

    /**
     * 获取插件的菜单和权限配置.
     *
     * 支持三种配置方式：
     * 1. 分别配置：menus.json + permissions.json
     * 2. 合并配置：menus_permissions.json（向后兼容）
     *
     * @param string $addonName 插件目录名
     * @return null|array 菜单和权限配置
     */
    private function getAddonMenusAndPermissions(string $addonName): ?array
    {
        $addonDir = $this->addonPath . '/' . $addonName;

        // 初始化配置
        $config = [
            'menus' => [],
            'permissions' => []
        ];

        // 优先检查分别配置的文件
        $menusFile = $addonDir . '/Manager/menus.json';
        $permissionsFile = $addonDir . '/Manager/permissions.json';

        // 读取菜单配置
        if (file_exists($menusFile)) {
            try {
                $menusContent = file_get_contents($menusFile);
                $menusConfig = json_decode($menusContent, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($menusConfig)) {
                    $config['menus'] = $menusConfig;
                    logger()->debug("[插件配置] 成功读取菜单配置: {$addonName}");
        } else {
                    logger()->warning("[插件配置] 菜单配置文件格式错误: {$addonName}");
                }
        } catch (Throwable $e) {
                logger()->error("[插件配置] 读取菜单配置文件异常: {$addonName} - " . $e->getMessage());
            }
        }

        // 读取权限配置
        if (file_exists($permissionsFile)) {
            try {
                $permissionsContent = file_get_contents($permissionsFile);
                $permissionsConfig = json_decode($permissionsContent, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($permissionsConfig)) {
                    $config['permissions'] = $permissionsConfig;
                    logger()->debug("[插件配置] 成功读取权限配置: {$addonName}");
                } else {
                    logger()->warning("[插件配置] 权限配置文件格式错误: {$addonName}");
                }
        } catch (Throwable $e) {
                logger()->error("[插件配置] 读取权限配置文件异常: {$addonName} - " . $e->getMessage());
            }
        }

        // 检查是否读取到了配置
        $hasMenus = !empty($config['menus']);
        $hasPermissions = !empty($config['permissions']);

        // 如果分别配置都没有找到，尝试读取合并配置文件（向后兼容）
        if (!$hasMenus && !$hasPermissions) {
            $legacyFile = $addonDir . '/Manager/menus_permissions.json';
            if (file_exists($legacyFile)) {
                try {
                    logger()->info("[插件配置] 检测到旧版配置，使用合并配置文件: {$addonName}");
                    $legacyContent = file_get_contents($legacyFile);
                    $legacyConfig = json_decode($legacyContent, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($legacyConfig)) {
                        // 将旧版配置转换为新格式
                        $config['menus'] = $legacyConfig['menus'] ?? [];
                        $config['permissions'] = $legacyConfig['permissions'] ?? [];
                        logger()->debug("[插件配置] 成功读取合并配置文件: {$addonName}");
                    } else {
                        logger()->warning("[插件配置] 合并配置文件格式错误: {$addonName}");
                return null;
            }
        } catch (Throwable $e) {
                    logger()->error("[插件配置] 读取合并配置文件异常: {$addonName} - " . $e->getMessage());
            return null;
        }
                } else {
                logger()->debug("[插件配置] 未找到任何菜单权限配置文件: {$addonName}");
                return null;
            }
        }

        // 记录配置统计信息
        $menusCount = count($config['menus']);
        $permissionsCount = count($config['permissions']);

        logger()->info("[插件配置] 菜单权限配置加载完成: {$addonName}", [
            'menus_count' => $menusCount,
            'permissions_count' => $permissionsCount,
            'config_type' => $hasMenus || $hasPermissions ? 'separate' : 'legacy'
        ]);

            return $config;
    }

    /**
     * 安装插件菜单和权限.
     *
     * 支持分别配置和合并配置的安装方式
     *
     * @param string $addonName 插件名称
     */
    private function installAddonMenusAndPermissions(string $addonName): bool
    {
        // 获取菜单和权限配置
        $config = $this->getAddonMenusAndPermissions($addonName);

        if ($config === null) {
            logger()->info("[插件安装] 插件 {$addonName} 没有菜单权限配置，跳过安装");
            return true;
        }

        try {
            $permissionsCount = count($config['permissions'] ?? []);
            $menusCount = count($config['menus'] ?? []);

            logger()->info("[插件安装] 开始安装菜单和权限: {$addonName}", [
                'permissions_count' => $permissionsCount,
                'menus_count' => $menusCount
            ]);

            // 安装权限
            if ($permissionsCount > 0) {
                $this->permissionsService->installAddonPermissions($addonName, $config['permissions']);
            } else {
                logger()->debug("[插件安装] 插件 {$addonName} 没有权限配置，跳过权限安装");
            }

            // 安装菜单
            if ($menusCount > 0) {
                $this->menuService->installAddonMenus($addonName, $config['menus']);
            } else {
                logger()->debug("[插件安装] 插件 {$addonName} 没有菜单配置，跳过菜单安装");
            }

            logger()->info("[插件安装] 插件 {$addonName} 菜单和权限安装完成");
            return true;
        } catch (Throwable $e) {
            logger()->error("[插件安装] 插件 {$addonName} 菜单和权限安装失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 禁用插件菜单和权限.
     *
     * 支持分别配置和合并配置的禁用方式
     *
     * @param string $addonName 插件名称
     */
    private function disableAddonMenusAndPermissions(string $addonName): bool
    {
        // 获取菜单和权限配置
        $config = $this->getAddonMenusAndPermissions($addonName);

        if ($config === null) {
            logger()->info("[插件禁用] 插件 {$addonName} 没有菜单权限配置，跳过禁用");
            return true;
        }

        try {
            $permissionsCount = count($config['permissions'] ?? []);
            $menusCount = count($config['menus'] ?? []);

            logger()->info("[插件禁用] 开始删除菜单和权限: {$addonName}", [
                'permissions_count' => $permissionsCount,
                'menus_count' => $menusCount
            ]);

            // 删除权限
            if ($permissionsCount > 0) {
                $this->permissionsService->deleteAddonPermissions($addonName, $config['permissions']);
            } else {
                logger()->debug("[插件禁用] 插件 {$addonName} 没有权限配置，跳过权限删除");
            }

            // 删除菜单
            if ($menusCount > 0) {
                $this->menuService->deleteAddonMenus($addonName, $config['menus']);
            } else {
                logger()->debug("[插件禁用] 插件 {$addonName} 没有菜单配置，跳过菜单删除");
            }

            logger()->info("[插件禁用] 插件 {$addonName} 菜单和权限删除完成");
            return true;
        } catch (Throwable $e) {
            logger()->error("[插件禁用] 插件 {$addonName} 菜单和权限删除失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 插件启用前环境检查.
     *
     * 自动检测插件依赖的环境需求，包括：
     * 1. PostgreSQL 扩展依赖检查（如果插件有 pgsql.json）
     * 2. 数据库类型自动检测（根据 pgsql.json 或 database.json）
     *
     * 插件无需任何额外配置，系统自动识别
     *
     * @param string $addonName 插件目录名
     * @return array ['passed' => bool, 'errors' => array, 'warnings' => array]
     */
    public function checkAddonEnableRequirements(string $addonName): array
    {
        $result = [
            'passed' => true,
            'errors' => [],
            'warnings' => [],
        ];

        $addonDir = $this->addonPath . '/' . $addonName;

        // 1. 自动检测数据库类型并检查
        $dbTypeCheck = $this->checkDatabaseTypeCompatibility($addonName);
        if (! $dbTypeCheck['passed']) {
            $result['errors'] = array_merge($result['errors'], $dbTypeCheck['errors']);
            $result['passed'] = false;
        }
        if (! empty($dbTypeCheck['warnings'])) {
            $result['warnings'] = array_merge($result['warnings'], $dbTypeCheck['warnings']);
        }

        // 2. 检查 PostgreSQL 扩展依赖（如果插件有 pgsql.json）
        $pgsqlCheck = $this->checkPgsqlRequirements($addonName);
        if (! $pgsqlCheck['passed']) {
            $result['errors'] = array_merge($result['errors'], $pgsqlCheck['errors']);
            $result['passed'] = false;
        }
        if (! empty($pgsqlCheck['warnings'])) {
            $result['warnings'] = array_merge($result['warnings'], $pgsqlCheck['warnings']);
        }

        return $result;
    }

    /**
     * 自动检测插件需要的数据库类型并检查兼容性.
     *
     * 根据插件的配置文件自动判断需要的数据库类型：
     * - 有 pgsql.json → 需要 PostgreSQL
     * - 有 database.json → 需要 MySQL
     * - 都有 → 检查当前数据库类型是否匹配
     *
     * @param string $addonName 插件目录名
     * @return array ['passed' => bool, 'errors' => array, 'warnings' => array]
     */
    private function checkDatabaseTypeCompatibility(string $addonName): array
    {
        $result = [
            'passed' => true,
            'errors' => [],
            'warnings' => [],
        ];

        $addonDir = $this->addonPath . '/' . $addonName;
        $hasPgsql = file_exists($addonDir . '/Manager/pgsql.json');
        $hasMysql = file_exists($addonDir . '/Manager/database.json');

        // 如果插件没有数据库配置文件，跳过
        if (! $hasPgsql && ! $hasMysql) {
            return $result;
        }

        // 获取当前数据库类型
        $currentDriver = strtolower(config('database.default') ?? 'mysql');

        // 检查 PostgreSQL 兼容性
        if ($hasPgsql && $currentDriver !== 'pgsql') {
            $result['errors'][] = "插件 [{$addonName}] 需要 PostgreSQL 数据库，当前系统使用的是 [{$currentDriver}] 数据库";
            $result['passed'] = false;
        }

        // 检查 MySQL 兼容性
        if ($hasMysql && $currentDriver !== 'mysql') {
            $result['errors'][] = "插件 [{$addonName}] 需要 MySQL 数据库，当前系统使用的是 [{$currentDriver}] 数据库";
            $result['passed'] = false;
        }

        if (! $result['passed']) {
            logger()->warning("[插件检查] 插件 {$addonName} 数据库类型不兼容，当前: {$currentDriver}");
        }

        return $result;
    }

    /**
     * 检查 PostgreSQL 扩展依赖.
     *
     * @param string $addonName 插件目录名
     * @return array ['passed' => bool, 'errors' => array, 'warnings' => array]
     */
    private function checkPgsqlRequirements(string $addonName): array
    {
        $result = [
            'passed' => true,
            'errors' => [],
            'warnings' => [],
        ];

        $pgsqlFile = $this->addonPath . '/' . $addonName . '/Manager/pgsql.json';

        // 如果没有 pgsql.json，跳过检查
        if (! file_exists($pgsqlFile)) {
            return $result;
        }

        try {
            $content = file_get_contents($pgsqlFile);
            $config = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['errors'][] = "pgsql.json 配置文件解析失败: " . json_last_error_msg();
                $result['passed'] = false;

                return $result;
            }

            // 检查是否有扩展声明
            if (! isset($config['extensions']) || ! is_array($config['extensions'])) {
                return $result;
            }

            $extensions = $config['extensions'];
            if (empty($extensions)) {
                return $result;
            }

            logger()->info("[插件检查] 插件 {$addonName} 需要检查 PostgreSQL 扩展: " . implode(', ', $extensions));

            // 检查数据库连接
            try {
                \Hyperf\DbConnection\Db::select('SELECT 1');
            } catch (\Throwable $e) {
                $result['errors'][] = "数据库连接失败: " . $e->getMessage();
                $result['passed'] = false;

                return $result;
            }

            // 检查每个扩展
            $requiredExtensions = [];
            $optionalExtensions = [];

            foreach ($extensions as $ext) {
                // 区分必需和可选扩展（这里简单处理，全部视为必需）
                // 未来可以通过配置文件标记
                $requiredExtensions[] = $ext;
            }

            // 检查必需扩展
            $missingExtensions = [];
            foreach ($requiredExtensions as $ext) {
                $isInstalled = $this->isPgsqlExtensionInstalled($ext);
                if (! $isInstalled) {
                    $missingExtensions[] = $ext;
                }
            }

            if (! empty($missingExtensions)) {
                $result['errors'][] = $this->formatPgsqlExtensionError($addonName, $missingExtensions);
                $result['passed'] = false;
            }

        } catch (\Throwable $e) {
            $result['errors'][] = "PostgreSQL 环境检查异常: " . $e->getMessage();
            $result['passed'] = false;
        }

        return $result;
    }

    /**
     * 检查指定 PostgreSQL 扩展是否已安装.
     *
     * @param string $extensionName 扩展名称
     * @return bool
     */
    private function isPgsqlExtensionInstalled(string $extensionName): bool
    {
        try {
            $sql = "SELECT 1 FROM pg_extension WHERE extname = ?";
            $result = \Hyperf\DbConnection\Db::select($sql, [$extensionName]);

            return ! empty($result);
        } catch (\Throwable $e) {
            logger()->warning("[插件检查] 检查扩展 {$extensionName} 时出错: " . $e->getMessage());

            return false;
        }
    }

    /**
     * 格式化 PostgreSQL 扩展缺失错误信息.
     *
     * @param string $addonName 插件名称
     * @param array $missingExtensions 缺失的扩展列表
     * @return string
     */
    private function formatPgsqlExtensionError(string $addonName, array $missingExtensions): string
    {
        $messages = ["插件 [{$addonName}] 必需的 PostgreSQL 扩展未安装："];

        foreach ($missingExtensions as $ext) {
            $description = $this->getPgsqlExtensionDescription($ext);
            $messages[] = "  - {$ext}: {$description}";
        }

        $messages[] = "";
        $messages[] = "安装命令示例：";
        $messages[] = "";
        $messages[] = "# Ubuntu/Debian";
        $messages[] = "sudo apt-get install postgresql-postgis -y  # 包含 pg_trgm, btree_gin, btree_gist";
        $messages[] = "sudo apt-get install postgresql-pgvector -y  # 向量扩展";
        $messages[] = "";
        $messages[] = "# CentOS/RHEL";
        $messages[] = "sudo yum install postgresql-pgvector* -y";
        $messages[] = "";
        $messages[] = "# 在数据库中执行：";
        foreach ($missingExtensions as $ext) {
            $messages[] = "CREATE EXTENSION IF NOT EXISTS {$ext};";
        }

        return implode("\n", $messages);
    }

    /**
     * 获取 PostgreSQL 扩展描述.
     *
     * @param string $extensionName 扩展名称
     * @return string
     */
    private function getPgsqlExtensionDescription(string $extensionName): string
    {
        $descriptions = [
            'pg_trgm' => '用于全文搜索的 trigram 匹配',
            'vector' => '用于语义搜索的向量存储（pgvector）',
            'zhparser' => '用于中文分词',
            'btree_gin' => 'GIN 索引所需的 btree 操作符类',
            'btree_gist' => 'GIST 索引所需的 btree 操作符类',
        ];

        return $descriptions[$extensionName] ?? '未知扩展';
    }

    /**
     * 获取插件启用检查状态（用于后台显示）.
     *
     * @param string $addonName 插件目录名
     * @return array
     */
    public function getAddonCheckStatus(string $addonName): array
    {
        $status = [
            'addon_name' => $addonName,
            'has_pgsql' => file_exists($this->addonPath . '/' . $addonName . '/Manager/pgsql.json'),
            'has_mysql' => file_exists($this->addonPath . '/' . $addonName . '/Manager/database.json'),
            'checks' => [],
            'overall_passed' => false,
        ];

        // 1. 检查数据库类型兼容性
        $dbTypeStatus = $this->checkDatabaseTypeCompatibility($addonName);
        if ($status['has_pgsql'] || $status['has_mysql']) {
            $status['checks']['database_type'] = [
                'name' => '数据库类型',
                'passed' => $dbTypeStatus['passed'],
                'errors' => $dbTypeStatus['errors'],
                'warnings' => $dbTypeStatus['warnings'] ?? [],
            ];
        }

        // 2. 检查 PostgreSQL 扩展
        if ($status['has_pgsql']) {
            $pgsqlStatus = $this->checkPgsqlRequirements($addonName);
            $status['checks']['postgresql'] = [
                'name' => 'PostgreSQL 扩展',
                'passed' => $pgsqlStatus['passed'],
                'errors' => $pgsqlStatus['errors'],
                'warnings' => $pgsqlStatus['warnings'] ?? [],
            ];
        }

        // 计算总体结果
        $allChecks = $status['checks'];
        $status['overall_passed'] = empty($allChecks) || ! in_array(false, array_column($allChecks, 'passed'));

        return $status;
    }





}
