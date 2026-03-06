<?php

declare(strict_types=1);

/**
 * 插件环境检查服务
 *
 * 负责插件启用前的环境依赖检查：
 * 1. 数据库类型兼容性检查
 * 2. PostgreSQL 扩展依赖检查
 * 3. 插件目录结构检查
 * 4. 资源文件部署检查
 *
 * 系统自动检测插件需求，无需插件额外配置
 */

namespace App\Service\Admin\Addons;

use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Support\Traits\StaticInstance;
use Hyperf\Di\Annotation\Inject;

class AddonsCheckService
{
    use StaticInstance;

    #[Inject]
    protected ?ConfigInterface $config = null;

    /**
     * 插件目录路径.
     */
    private string $addonPath;

    /**
     * 扩展描述映射.
     */
    private const EXTENSION_DESCRIPTIONS = [
        'pg_trgm' => '用于全文搜索的 trigram 匹配',
        'vector' => '用于语义搜索的向量存储（pgvector）',
        'zhparser' => '用于中文分词',
        'btree_gin' => 'GIN 索引所需的 btree 操作符类',
        'btree_gist' => 'GIST 索引所需的 btree 操作符类',
    ];

    /**
     * 必需的插件基础文件.
     */
    private const REQUIRED_FILES = [
        'info.php' => '插件信息文件',
    ];

    /**
     * 推荐的目录结构.
     */
    private const RECOMMENDED_DIRS = [
        'Manager' => '插件管理器目录',
        'Controller' => '控制器目录',
        'Service' => '服务目录',
        'Model' => '模型目录',
        'View' => '视图目录',
        'Public' => '公共资源目录',
    ];

    public function __construct()
    {
        $this->addonPath = BASE_PATH . '/addons';
    }

    /**
     * 插件启用前完整环境检查.
     *
     * 检查项目：
     * 1. 插件基础目录结构检查
     * 2. 资源文件部署检查
     * 3. 数据库类型兼容性检查
     * 4. PostgreSQL 扩展依赖检查
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

        // 1. 检查插件基础目录结构
        $structureCheck = $this->checkAddonStructure($addonName);
        if (! $structureCheck['passed']) {
            $result['errors'] = array_merge($result['errors'], $structureCheck['errors']);
            $result['passed'] = false;
        }
        if (! empty($structureCheck['warnings'])) {
            $result['warnings'] = array_merge($result['warnings'], $structureCheck['warnings']);
        }

        // 2. 检查资源文件部署
        $assetsCheck = $this->checkAssetsDeployment($addonName);
        if (! $assetsCheck['passed']) {
            $result['errors'] = array_merge($result['errors'], $assetsCheck['errors']);
            $result['passed'] = false;
        }
        if (! empty($assetsCheck['warnings'])) {
            $result['warnings'] = array_merge($result['warnings'], $assetsCheck['warnings']);
        }

        // 3. 自动检测数据库类型并检查
        $dbTypeCheck = $this->checkDatabaseTypeCompatibility($addonName);
        if (! $dbTypeCheck['passed']) {
            $result['errors'] = array_merge($result['errors'], $dbTypeCheck['errors']);
            $result['passed'] = false;
        }
        if (! empty($dbTypeCheck['warnings'])) {
            $result['warnings'] = array_merge($result['warnings'], $dbTypeCheck['warnings']);
        }

        // 4. 检查 PostgreSQL 扩展依赖
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
     * 检查插件基础目录结构.
     *
     * 检查必需文件是否存在，推荐目录是否存在
     *
     * @param string $addonName 插件目录名
     * @return array ['passed' => bool, 'errors' => array, 'warnings' => array]
     */
    public function checkAddonStructure(string $addonName): array
    {
        $result = [
            'passed' => true,
            'errors' => [],
            'warnings' => [],
        ];

        $addonDir = $this->addonPath . '/' . $addonName;

        // 检查插件目录是否存在
        if (! is_dir($addonDir)) {
            $result['errors'][] = "插件目录不存在: {$addonDir}";
            $result['passed'] = false;

            return $result;
        }

        // 检查必需文件
        foreach (self::REQUIRED_FILES as $file => $description) {
            $filePath = $addonDir . '/' . $file;
            if (! file_exists($filePath)) {
                $result['errors'][] = "插件 [{$addonName}] 缺少必需文件: {$file} ({$description})";
                $result['passed'] = false;
            }
        }

        // 检查推荐目录
        foreach (self::RECOMMENDED_DIRS as $dir => $description) {
            $dirPath = $addonDir . '/' . $dir;
            if (! is_dir($dirPath)) {
                $result['warnings'][] = "插件 [{$addonName}] 缺少推荐目录: {$dir} ({$description})";
            }
        }

        // 检查配置文件
        $configFile = $addonDir . '/config.php';
        if (! file_exists($configFile)) {
            $result['warnings[]'] = "插件 [{$addonName}] 没有配置文件 (config.php)";
        }

        return $result;
    }

    /**
     * 检查资源文件部署状态.
     *
     * 检查 assets.json 中声明的资源文件是否正确部署：
     * 1. 检查源文件是否存在（应该在插件目录中）
     * 2. 检查目标目录是否存在
     * 3. 检查目标文件是否存在（防止覆盖）
     * 4. 检查目标位置是否可写
     *
     * @param string $addonName 插件目录名
     * @return array ['passed' => bool, 'errors' => array, 'warnings' => array]
     */
    public function checkAssetsDeployment(string $addonName): array
    {
        $result = [
            'passed' => true,
            'errors' => [],
            'warnings' => [],
        ];

        $addonDir = $this->addonPath . '/' . $addonName;
        $assetsFile = $addonDir . '/Manager/assets.json';

        // 如果没有 assets.json，跳过检查
        if (! file_exists($assetsFile)) {
            return $result;
        }

        try {
            $content = file_get_contents($assetsFile);
            $assetsConfig = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['errors'][] = "插件 [{$addonName}] 的 assets.json 配置文件解析失败: " . json_last_error_msg();
                $result['passed'] = false;

                return $result;
            }

            logger()->info("[插件检查] 检查插件 {$addonName} 的资源文件部署状态");

            // === 第一部分：检查源文件（在插件目录中） ===

            // 检查源目录是否存在
            $missingSourceDirs = [];
            if (isset($assetsConfig['directories']) && is_array($assetsConfig['directories'])) {
                foreach ($assetsConfig['directories'] as $source => $target) {
                    $sourcePath = $addonDir . '/' . $source;
                    if (! is_dir($sourcePath)) {
                        $missingSourceDirs[] = $source;
                    }
                }
            }

            // 检查源文件是否存在
            $missingSourceFiles = [];
            if (isset($assetsConfig['files']) && is_array($assetsConfig['files'])) {
                foreach ($assetsConfig['files'] as $source => $target) {
                    $sourcePath = $addonDir . '/' . $source;
                    if (! file_exists($sourcePath)) {
                        $missingSourceFiles[] = $source;
                    }
                }
            }

            // 报告缺失的源文件
            if (! empty($missingSourceDirs)) {
                $result['errors'][] = "插件 [{$addonName}] 以下资源目录在插件目录中不存在: " . implode(', ', $missingSourceDirs);
                $result['passed'] = false;
            }

            if (! empty($missingSourceFiles)) {
                $result['errors'][] = "插件 [{$addonName}] 以下资源文件在插件目录中不存在: " . implode(', ', $missingSourceFiles);
                $result['passed'] = false;
            }

            // === 第二部分：检查目标位置（防止覆盖）===

            // 获取当前已安装的插件列表（排除当前插件）
            $installedAddons = $this->getInstalledAddonNames();
            $currentAddonName = $addonName;

            // 系统保护目录列表（防止插件部署到关键系统目录）
            // 注意：插件资源文件应该部署到 storage/、public/ 等目录
            // runtime/ 目录是系统运行缓存目录，插件不应部署文件到此
            $protectedDirs = [
                'runtime/',    // 运行缓存目录，插件不应写入
                'app/',        // 应用核心代码目录
                'config/',     // 配置文件目录
            ];

            $conflictDirs = [];

            if (isset($assetsConfig['directories']) && is_array($assetsConfig['directories'])) {
                foreach ($assetsConfig['directories'] as $source => $target) {
                    $targetPath = BASE_PATH . '/' . $target;

                    // 检查是否是受保护的系统目录
                    $isProtected = false;
                    foreach ($protectedDirs as $protected) {
                        // 使用精确匹配：目标是受保护目录本身，或目标是受保护目录下的某个位置
                        if ($target === $protected || strpos($target, $protected) === 0) {
                            $isProtected = true;
                            break;
                        }
                    }

                    if ($isProtected) {
                        $result['errors'][] = "插件 [{$addonName}] 不能部署到系统保护目录: {$target}";
                        $result['passed'] = false;
                        continue;
                    }

                    // 检查目标目录是否存在
                    if (is_dir($targetPath)) {
                        // 检查是否被其他插件使用
                        foreach ($installedAddons as $addon) {
                            if ($addon !== $currentAddonName) {
                                $otherAddonDir = $this->addonPath . '/' . $addon;
                                if (is_dir($otherAddonDir)) {
                                    // 检查其他插件是否也部署到相同目录
                                    $otherAssetsFile = $otherAddonDir . '/Manager/assets.json';
                                    if (file_exists($otherAssetsFile)) {
                                        $otherAssets = json_decode(file_get_contents($otherAssetsFile), true);
                                        if (isset($otherAssets['directories']) && is_array($otherAssets['directories'])) {
                                            foreach ($otherAssets['directories'] as $otherSource => $otherTarget) {
                                                if ($otherTarget === $target) {
                                                    $conflictDirs[] = [
                                                        'target' => $target,
                                                        'source' => $addon,
                                                    ];
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // 检查目标文件是否存在（且不是当前插件部署的）
            $conflictFiles = [];
            if (isset($assetsConfig['files']) && is_array($assetsConfig['files'])) {
                foreach ($assetsConfig['files'] as $source => $target) {
                    $targetPath = BASE_PATH . '/' . $target;

                    // 检查是否是受保护的系统文件
                    $isProtected = false;
                    foreach ($protectedDirs as $protected) {
                        if ($target === $protected || strpos($target, $protected) === 0) {
                            $isProtected = true;
                            break;
                        }
                    }

                    if ($isProtected) {
                        $result['errors'][] = "插件 [{$addonName}] 不能部署到系统保护目录: {$target}";
                        $result['passed'] = false;
                        continue;
                    }

                    // 检查目标文件是否存在
                    if (file_exists($targetPath)) {
                        // 检查是否被其他插件使用
                        foreach ($installedAddons as $addon) {
                            if ($addon !== $currentAddonName) {
                                $otherAddonDir = $this->addonPath . '/' . $addon;
                                if (is_dir($otherAddonDir)) {
                                    $otherAssetsFile = $otherAddonDir . '/Manager/assets.json';
                                    if (file_exists($otherAssetsFile)) {
                                        $otherAssets = json_decode(file_get_contents($otherAssetsFile), true);
                                        if (isset($otherAssets['files']) && is_array($otherAssets['files'])) {
                                            foreach ($otherAssets['files'] as $otherSource => $otherTarget) {
                                                if ($otherTarget === $target) {
                                                    $conflictFiles[] = [
                                                        'target' => $target,
                                                        'source' => $addon,
                                                    ];
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // 如果文件存在但没有被其他插件使用，检查是否会被当前插件覆盖
                        // 这实际上是允许的，因为是同一插件重新部署
                        logger()->info("[插件检查] 插件 {$addonName} 将覆盖文件: {$target}");
                    }
                }
            }

            // 报告冲突
            foreach ($conflictDirs as $conflict) {
                $result['errors'][] = "插件 [{$addonName}] 目标目录与插件 [{$conflict['source']}] 冲突: {$conflict['target']}";
                $result['passed'] = false;
            }

            foreach ($conflictFiles as $conflict) {
                $result['errors'][] = "插件 [{$addonName}] 目标文件与插件 [{$conflict['source']}] 冲突: {$conflict['target']}";
                $result['passed'] = false;
            }

            // === 第三部分：检查目录权限 ===

            // 检查 public 目录是否可写
            $publicPath = BASE_PATH . '/public';
            if (is_dir($publicPath) && ! is_writable($publicPath)) {
                $result['errors'][] = "插件 [{$addonName}] 的 public 目录不可写，无法部署资源文件";
                $result['passed'] = false;
            }

            // 检查 storage 目录是否可写
            $storagePath = BASE_PATH . '/storage/app/public';
            if (is_dir(dirname($storagePath)) && ! is_writable(dirname($storagePath))) {
                $result['errors[]'] = "插件 [{$addonName}] 的 storage/app 目录不可写，无法部署资源文件";
                $result['passed'] = false;
            }

        } catch (\Throwable $e) {
            $result['errors'][] = "插件 [{$addonName}] 资源文件检查异常: " . $e->getMessage();
            $result['passed'] = false;
        }

        return $result;
    }

    /**
     * 获取已安装的插件名称列表（排除 . 和 ..）.
     *
     * @return array
     */
    private function getInstalledAddonNames(): array
    {
        $addons = [];
        $dirs = scandir($this->addonPath);

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || ! is_dir($this->addonPath . '/' . $dir)) {
                continue;
            }

            // 只检查有效的插件（包含 info.php）
            $infoFile = $this->addonPath . '/' . $dir . '/info.php';
            if (file_exists($infoFile)) {
                $addons[] = $dir;
            }
        }

        return $addons;
    }

    /**
     * 检查资源文件是否已正确部署.
     *
     * 用于验证已启用的插件资源文件是否完整
     * 同时检查是否有文件冲突
     *
     * @param string $addonName 插件目录名
     * @return array ['passed' => bool, 'errors' => array, 'warnings' => array]
     */
    public function verifyAssetsDeployed(string $addonName): array
    {
        $result = [
            'passed' => true,
            'errors' => [],
            'warnings' => [],
        ];

        $addonDir = $this->addonPath . '/' . $addonName;
        $assetsFile = $addonDir . '/Manager/assets.json';

        // 如果没有 assets.json，跳过检查
        if (! file_exists($assetsFile)) {
            return $result;
        }

        try {
            $content = file_get_contents($assetsFile);
            $assetsConfig = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $result;
            }

            $missingFiles = [];
            $missingDirs = [];
            $conflictFiles = [];

            // === 检查 directories 部署 ===
            if (isset($assetsConfig['directories']) && is_array($assetsConfig['directories'])) {
                foreach ($assetsConfig['directories'] as $source => $target) {
                    $targetPath = BASE_PATH . '/' . $target;
                    if (! is_dir($targetPath)) {
                        $missingDirs[] = $target;
                    }
                }
            }

            // === 检查 files 部署 ===
            if (isset($assetsConfig['files']) && is_array($assetsConfig['files'])) {
                foreach ($assetsConfig['files'] as $source => $target) {
                    $targetPath = BASE_PATH . '/' . $target;
                    if (! file_exists($targetPath)) {
                        $missingFiles[] = $target;
                    }
                }
            }

            // === 检查文件冲突（与其他插件）===
            $installedAddons = $this->getInstalledAddonNames();
            foreach ($installedAddons as $otherAddon) {
                if ($otherAddon === $addonName) {
                    continue;
                }

                $otherDir = $this->addonPath . '/' . $otherAddon;
                $otherAssetsFile = $otherDir . '/Manager/assets.json';

                if (! file_exists($otherAssetsFile)) {
                    continue;
                }

                $otherAssets = json_decode(file_get_contents($otherAssetsFile), true);

                // 检查文件冲突
                if (isset($assetsConfig['files']) && isset($otherAssets['files'])) {
                    foreach ($assetsConfig['files'] as $source => $target) {
                        foreach ($otherAssets['files'] as $otherSource => $otherTarget) {
                            if ($target === $otherTarget) {
                                $conflictFiles[] = [
                                    'file' => $target,
                                    'conflicted_with' => $otherAddon,
                                ];
                            }
                        }
                    }
                }

                // 检查目录冲突
                if (isset($assetsConfig['directories']) && isset($otherAssets['directories'])) {
                    foreach ($assetsConfig['directories'] as $source => $target) {
                        foreach ($otherAssets['directories'] as $otherSource => $otherTarget) {
                            if ($target === $otherTarget) {
                                $result['warnings'][] = "插件 [{$addonName}] 的目录 [{$target}] 与插件 [{$otherAddon}] 共享";
                            }
                        }
                    }
                }
            }

            // 报告缺失的文件
            if (! empty($missingDirs)) {
                $result['errors'][] = "插件 [{$addonName}] 以下目录未部署: " . implode(', ', $missingDirs);
                $result['passed'] = false;
            }

            if (! empty($missingFiles)) {
                $result['errors'][] = "插件 [{$addonName}] 以下文件未部署: " . implode(', ', $missingFiles);
                $result['passed'] = false;
            }

            // 报告冲突
            foreach ($conflictFiles as $conflict) {
                $result['errors'][] = "插件 [{$addonName}] 的文件 [{$conflict['file']}] 与插件 [{$conflict['conflicted_with']}] 冲突";
                $result['passed'] = false;
            }

            if ($result['passed']) {
                logger()->info("[插件检查] 插件 {$addonName} 资源文件已全部正确部署");
            }

        } catch (\Throwable $e) {
            $result['warnings'][] = "验证资源部署时发生异常: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * 自动检测插件需要的数据库类型并检查兼容性.
     *
     * 根据插件的配置文件自动判断需要的数据库类型：
     * - 有 pgsql.json → 检查 PostgreSQL 连接是否可用
     * - 有 database.json → 检查 MySQL 连接是否可用
     * - 都有 → 检查默认数据库类型
     *
     * @param string $addonName 插件目录名
     * @return array ['passed' => bool, 'errors' => array, 'warnings' => array]
     */
    public function checkDatabaseTypeCompatibility(string $addonName): array
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

        // 获取当前默认数据库驱动
        $currentDriver = $this->config
            ? strtolower($this->config->get('database.default') ?? 'mysql')
            : 'mysql';

        // 情况1：插件同时有 pgsql.json 和 database.json
        if ($hasPgsql && $hasMysql) {
            if (! in_array($currentDriver, ['mysql', 'pgsql'])) {
                $result['errors'][] = "插件 [{$addonName}] 需要 MySQL 或 PostgreSQL 数据库，当前默认配置为 [{$currentDriver}]";
                $result['passed'] = false;
            } else {
                logger()->info("[插件检查] 插件 {$addonName} 支持 MySQL 和 PostgreSQL，当前默认: {$currentDriver}");
            }
            return $result;
        }

        // 情况2：只有 pgsql.json
        if ($hasPgsql && ! $hasMysql) {
            // 直接测试 pgsql 连接是否可用
            try {
                Db::connection('pgsql')->select('SELECT 1');
                logger()->info("[插件检查] 插件 {$addonName} PostgreSQL 数据库连接成功");
            } catch (\Throwable $e) {
                $result['errors'][] = "插件 [{$addonName}] 无法连接到 PostgreSQL 数据库: " . $e->getMessage();
                $result['passed'] = false;
            }
            return $result;
        }

        // 情况3：只有 database.json（MySQL）
        if ($hasMysql && ! $hasPgsql) {
            // 直接测试 mysql 连接是否可用
            try {
                // 检查配置中是否存在 mysql 连接，如果不存在则使用默认连接
                $mysqlConfigExists = $this->config && $this->config->has('databases.mysql');
                $connectionName = $mysqlConfigExists ? 'mysql' : null;

                Db::connection($connectionName)->select('SELECT 1');
                logger()->info("[插件检查] 插件 {$addonName} MySQL 数据库连接成功");
            } catch (\Throwable $e) {
                $result['errors'][] = "插件 [{$addonName}] 无法连接到 MySQL 数据库: " . $e->getMessage();
                $result['passed'] = false;
            }
            return $result;
        }

        return $result;
    }

    /**
     * 检查 PostgreSQL 扩展依赖.
     *
     * @param string $addonName 插件目录名
     * @return array ['passed' => bool, 'errors' => array, 'warnings' => array]
     */
    public function checkPgsqlRequirements(string $addonName): array
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

            // 检查数据库连接（使用 pgsql 连接）
            try {
                Db::connection('pgsql')->select('SELECT 1');
            } catch (\Throwable $e) {
                $result['errors'][] = "PostgreSQL 数据库连接失败: " . $e->getMessage();
                $result['passed'] = false;

                return $result;
            }

            // 检查每个扩展
            $requiredExtensions = [];
            foreach ($extensions as $ext) {
                // 全部视为必需
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
    public function isPgsqlExtensionInstalled(string $extensionName): bool
    {
        try {
            $sql = "SELECT 1 FROM pg_extension WHERE extname = ?";
            // 使用 pgsql 连接，而不是默认的 mysql 连接
            $result = Db::connection('pgsql')->select($sql, [$extensionName]);

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
    public function formatPgsqlExtensionError(string $addonName, array $missingExtensions): string
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
    public function getPgsqlExtensionDescription(string $extensionName): string
    {
        return self::EXTENSION_DESCRIPTIONS[$extensionName] ?? '未知扩展';
    }

    /**
     * 获取插件完整检查状态（用于后台显示）.
     *
     * @param string $addonName 插件目录名
     * @return array
     */
    public function getAddonCheckStatus(string $addonName): array
    {
        $addonDir = $this->addonPath . '/' . $addonName;

        $status = [
            'addon_name' => $addonName,
            'has_pgsql' => file_exists($addonDir . '/Manager/pgsql.json'),
            'has_mysql' => file_exists($addonDir . '/Manager/database.json'),
            'has_assets' => file_exists($addonDir . '/Manager/assets.json'),
            'has_config' => file_exists($addonDir . '/config.php'),
            'checks' => [],
            'overall_passed' => false,
        ];

        // 1. 检查插件基础结构
        $structureCheck = $this->checkAddonStructure($addonName);
        $status['checks']['structure'] = [
            'name' => '目录结构',
            'passed' => $structureCheck['passed'],
            'errors' => $structureCheck['errors'],
            'warnings' => $structureCheck['warnings'] ?? [],
        ];

        // 2. 检查资源文件部署
        if ($status['has_assets']) {
            $assetsCheck = $this->checkAssetsDeployment($addonName);
            $status['checks']['assets'] = [
                'name' => '资源文件',
                'passed' => $assetsCheck['passed'],
                'errors' => $assetsCheck['errors'],
                'warnings' => $assetsCheck['warnings'] ?? [],
            ];
        }

        // 3. 检查数据库类型兼容性
        if ($status['has_pgsql'] || $status['has_mysql']) {
            $dbTypeStatus = $this->checkDatabaseTypeCompatibility($addonName);
            $status['checks']['database_type'] = [
                'name' => '数据库类型',
                'passed' => $dbTypeStatus['passed'],
                'errors' => $dbTypeStatus['errors'],
                'warnings' => $dbTypeStatus['warnings'] ?? [],
            ];
        }

        // 4. 检查 PostgreSQL 扩展
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

    /**
     * 批量检查多个插件的启用状态.
     *
     * @param array $addonNames 插件目录名列表
     * @return array ['插件名' => ['passed' => bool, 'errors' => array]]
     */
    public function batchCheckAddons(array $addonNames): array
    {
        $results = [];

        foreach ($addonNames as $addonName) {
            $results[$addonName] = $this->checkAddonEnableRequirements($addonName);
        }

        return $results;
    }

    /**
     * 验证插件是否可安全启用.
     *
     * 在启用插件前进行完整检查，包括验证资源文件是否已部署
     *
     * @param string $addonName 插件目录名
     * @return array ['safe' => bool, 'messages' => array]
     */
    public function canSafelyEnable(string $addonName): array
    {
        $messages = [];
        $isSafe = true;

        // 检查插件是否已启用（验证是否需要重新部署资源）
        $configFile = $this->addonPath . '/' . $addonName . '/config.php';
        if (file_exists($configFile)) {
            $config = include $configFile;
            $isEnabled = $config['enabled'] ?? false;

            if ($isEnabled) {
                // 插件已启用，检查资源文件是否完整
                $verifyResult = $this->verifyAssetsDeployed($addonName);
                if (! $verifyResult['passed']) {
                    $isSafe = false;
                    $messages = array_merge($messages, $verifyResult['errors']);
                }
            }
        }

        return [
            'safe' => $isSafe,
            'messages' => $messages,
        ];
    }
}
