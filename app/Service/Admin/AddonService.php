<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Exception\BusinessException;
use App\Constants\ErrorCode;
use App\Model\Admin\AdminMenu;
use App\Model\Admin\AdminPermission;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\SimpleCache\CacheInterface;
use function Hyperf\Support\now;

/**
 * 插件管理服务
 *
 * 负责扫描和管理addons目录下的插件
 */
class AddonService
{
    /**
     * 插件目录路径
     */
    private string $addonPath;


    #[Inject]
    protected CacheInterface $cache;

    public function __construct()
    {
        $this->addonPath = BASE_PATH . '/addons';
    }

    /**
     * 扫描所有插件
     *
     * @return array 插件列表
     */
    public function scanAddons(): array
    {
        $addons = [];

        if (!is_dir($this->addonPath)) {
            return $addons;
        }

        $dirs = scandir($this->addonPath);
        if (!$dirs) {
            return $addons;
        }

        foreach ($dirs as $dir) {
            // 跳过特殊目录
            if ($dir === '.' || $dir === '..' || !is_dir($this->addonPath . '/' . $dir)) {
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
     * 根据插件ID获取插件目录名
     * 插件ID使用下划线命名法，目录名使用驼峰命名法
     *
     * @param string $addonId 插件ID（如：addons_store）
     * @return string 插件目录名（如：AddonsStore）
     */
    public function getAddonDirById(string $addonId): string
    {
        // 将下划线命名法转换为驼峰命名法
        $dirName = $this->convertIdToDirName($addonId);

        logger()->debug("[插件映射] ID转换为目录名", [
            'addonId' => $addonId,
            'dirName' => $dirName
        ]);

        return $dirName;
    }

    /**
     * 将插件ID转换为目录名
     * 下划线命名法 -> 驼峰命名法
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
     * 根据插件ID获取插件信息
     *
     * @param string $addonId 插件ID
     * @return array|null 插件信息
     */
    public function getAddonInfoById(string $addonId): ?array
    {
        $dirName = $this->getAddonDirById($addonId);
        return $this->getAddonInfo($dirName);
    }

    /**
     * 根据插件ID启用插件
     *
     * @param string $addonId 插件ID
     * @return bool
     */
    public function enableAddonById(string $addonId): bool
    {
        $dirName = $this->getAddonDirById($addonId);
        return $this->enableAddon($dirName);
    }

    /**
     * 根据插件ID禁用插件
     *
     * @param string $addonId 插件ID
     * @return bool
     */
    public function disableAddonById(string $addonId): bool
    {
        $dirName = $this->getAddonDirById($addonId);
        return $this->disableAddon($dirName);
    }

    /**
     * 根据插件ID安装插件
     *
     * @param string $addonId 插件ID
     * @return bool
     */
    public function installAddonById(string $addonId): bool
    {
        $dirName = $this->getAddonDirById($addonId);
        return $this->installAddon($dirName);
    }

    /**
     * 根据插件ID卸载插件
     *
     * @param string $addonId 插件ID
     * @return bool
     */
    public function uninstallAddonById(string $addonId): bool
    {
        $dirName = $this->getAddonDirById($addonId);
        return $this->uninstallAddon($dirName);
    }

    /**
     * 获取插件信息
     *
     * @param string $addonName 插件目录名
     * @return array|null 插件信息
     */
    public function getAddonInfo(string $addonName): ?array
    {
        logger()->info("[插件信息] 开始获取插件信息", ['addonName' => $addonName]);

        $addonDir = $this->addonPath . '/' . $addonName;
        $infoFile = $addonDir . '/info.php';

        logger()->info("[插件信息] 检查info.php文件", [
            'addonName' => $addonName,
            'addonDir' => $addonDir,
            'infoFile' => $infoFile,
            'infoFile_exists' => file_exists($infoFile)
        ]);

        if (!file_exists($infoFile)) {
            logger()->error("[插件信息] info.php文件不存在", ['addonName' => $addonName, 'infoFile' => $infoFile]);
            return null;
        }

        try {
            $info = include $infoFile;

            logger()->info("[插件信息] 读取info.php内容", [
                'addonName' => $addonName,
                'info_type' => gettype($info),
                'info_is_array' => is_array($info),
                'info_keys' => is_array($info) ? array_keys($info) : null
            ]);

            if (!is_array($info)) {
                logger()->error("[插件信息] info.php返回的不是数组", ['addonName' => $addonName, 'info' => $info]);
                return null;
            }

            // 验证必要字段
            if (!isset($info['id'], $info['name'], $info['version'])) {
                logger()->error("[插件信息] 缺少必要字段", [
                    'addonName' => $addonName,
                    'has_id' => isset($info['id']),
                    'has_name' => isset($info['name']),
                    'has_version' => isset($info['version'])
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

            logger()->info("[插件信息] 插件配置读取", [
                'addonName' => $addonName,
                'config_type' => gettype($configData),
                'config_is_array' => is_array($configData),
                'config_keys' => is_array($configData) ? array_keys($configData) : null,
                'has_configs' => is_array($configData) && isset($configData['configs']),
                'configs_count' => is_array($configData) && isset($configData['configs']) && is_array($configData['configs']) ? count($configData['configs']) : 0
            ]);

            // 读取插件菜单和权限配置
            $info['menus_permissions'] = $this->getAddonMenusAndPermissions($addonName);

            logger()->info("[插件信息] 插件信息获取完成", [
                'addonName' => $addonName,
                'final_info_keys' => array_keys($info),
                'enabled' => $info['enabled'],
                'has_configs_in_final' => isset($info['config']['configs']) && is_array($info['config']['configs'])
            ]);

            return $info;
        } catch (\Throwable $e) {
            logger()->error("[插件信息] 获取插件信息异常", [
                'addonName' => $addonName,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * 获取插件的菜单和权限配置
     *
     * @param string $addonName 插件目录名
     * @return array|null 菜单和权限配置
     */
    private function getAddonMenusAndPermissions(string $addonName): ?array
    {
        $addonDir = $this->addonPath . '/' . $addonName;
        $configFile = $addonDir . '/Manager/menus_permissions.json';

        if (!file_exists($configFile)) {
            return null;
        }

        try {
            $configContent = file_get_contents($configFile);
            $config = json_decode($configContent, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
                return null;
            }

            return $config;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 检查插件是否已安装
     *
     * @param string $addonName 插件目录名
     * @return bool
     */
    public function isAddonInstalled(string $addonName): bool
    {
        // 这里可以实现安装状态检查逻辑
        // 目前简单返回true，实际项目中可以检查数据库或配置文件
        return true;
    }

    /**
     * 检查插件是否已启用
     *
     * @param string $addonName 插件目录名
     * @return bool
     */
    public function isAddonEnabled(string $addonName): bool
    {
        // 只检查插件自己的配置文件中的 enabled 状态
        $pluginConfig = $this->getAddonConfig($addonName);
        return isset($pluginConfig['enabled']) ? (bool) $pluginConfig['enabled'] : false;
    }

    /**
     * 启用插件
     *
     * 启用插件时会自动执行安装操作（部署资源文件）
     *
     * @param string $addonName 插件目录名
     * @return bool
     */
    public function enableAddon(string $addonName): bool
    {
        logger()->info("[插件管理] 开始启用插件: {$addonName}");

        try {
            // 首先执行插件安装（启用即视为安装）
            logger()->info("[插件管理] 执行插件安装: {$addonName}");
            $installResult = $this->installAddon($addonName);
            if (!$installResult) {
                logger()->error("[插件管理] 插件 {$addonName} 安装失败，终止启用流程");
                return false;
            }

            // 更新插件自己的配置文件中的enabled状态
            $this->updateAddonConfigValue($addonName, 'enabled', true);
            logger()->info("[插件管理] 更新插件配置: {$addonName} -> enabled");

            // 部署插件资源文件
            logger()->info("[插件管理] 部署插件资源文件: {$addonName}");
            $this->deployAddonAssets($addonName);

            // 安装菜单和权限
            logger()->info("[插件管理] 安装菜单和权限: {$addonName}");
            $this->installAddonMenusAndPermissions($addonName);

            // 执行插件启用钩子
            $this->executeAddonHook($addonName, 'enable');

            logger()->info("[插件管理] 插件 {$addonName} 启用成功");
            return true;
        } catch (\Throwable $e) {
            logger()->error("[插件管理] 插件 {$addonName} 启用失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 禁用插件
     *
     * 禁用插件时会自动清理相关的文件和目录，但保留数据表
     *
     * @param string $addonName 插件目录名
     * @return bool
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
            $uninstallResult = $this->uninstallAddonAssets($addonName);
            if (!$uninstallResult) {
                logger()->warning("[插件管理] 插件 {$addonName} 文件清理失败，但继续禁用流程");
            }

            // 更新插件自己的配置文件中的enabled状态
            $this->updateAddonConfigValue($addonName, 'enabled', false);
            logger()->info("[插件管理] 更新插件配置: {$addonName} -> disabled");

            // 执行插件禁用钩子
            $this->executeAddonHook($addonName, 'disable');

            logger()->info("[插件管理] 插件 {$addonName} 禁用成功");
            return true;
        } catch (\Throwable $e) {
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
     * @return bool
     */
    public function updateAddonConfigValue(string $addonName, string $key, $value): bool
    {
        try {
            $configFile = $this->addonPath . '/' . $addonName . '/config.php';

            if (!file_exists($configFile)) {
                return false;
            }

            $content = file_get_contents($configFile);

            // 处理嵌套键
            $keys = explode('.', $key);
            $configKey = array_shift($keys);

            if (empty($keys)) {
                // 单层键值更新
                return $this->updateSimpleConfigValue($content, $configKey, $value, $configFile);
            } else {
                // 嵌套键值更新（暂时不支持，未来可以扩展）
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 更新简单的配置值
     *
     * @param string $content 文件内容
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @param string $configFile 配置文件路径
     * @return bool
     */
    private function updateSimpleConfigValue(string $content, string $key, $value, string $configFile): bool
    {
        // 格式化值
        if (is_bool($value)) {
            $valueStr = $value ? 'true' : 'false';
        } elseif (is_string($value)) {
            $valueStr = "'{$value}'";
        } elseif (is_null($value)) {
            $valueStr = 'null';
        } else {
            $valueStr = (string) $value;
        }

        // 首先尝试使用正则表达式替换
        $pattern = "/(['\"]?{$key}['\"]?\s*=>\s*)(true|false|null|'[^']*'|[^,\n]+)(,?\s*(?:\/\/.*)?)/m";
        $replacement = '${1}' . $valueStr . '${3}';
        $newContent = preg_replace($pattern, $replacement, $content);

        // 如果正则替换失败，尝试简单的字符串替换
        if ($newContent === $content || $newContent === null) {
            // 尝试替换 'enabled' => false
            $oldPattern = "'{$key}' => false";
            $newPattern = "'{$key}' => {$valueStr}";
            $newContent = str_replace($oldPattern, $newPattern, $content);

            // 如果还没替换，尝试替换 'enabled' => true
            if ($newContent === $content) {
                $oldPattern = "'{$key}' => true";
                $newPattern = "'{$key}' => {$valueStr}";
                $newContent = str_replace($oldPattern, $newPattern, $content);
            }

            // 如果还没替换，尝试替换 enabled => false (无引号)
            if ($newContent === $content) {
                $oldPattern = "{$key} => false";
                $newPattern = "{$key} => {$valueStr}";
                $newContent = str_replace($oldPattern, $newPattern, $content);
            }

            // 如果还没替换，尝试替换 enabled => true (无引号)
            if ($newContent === $content) {
                $oldPattern = "{$key} => true";
                $newPattern = "{$key} => {$valueStr}";
                $newContent = str_replace($oldPattern, $newPattern, $content);
            }
        }

        if ($newContent !== $content && $newContent !== null) {
            file_put_contents($configFile, $newContent);
            return true;
        }

        // 如果都失败了，记录错误日志
        logger()->error("[插件配置] 更新配置失败", [
            'key' => $key,
            'value' => $value,
            'configFile' => $configFile,
            'content_sample' => substr($content, 0, 200)
        ]);

        return false;
    }

    /**
     * 保存插件配置
     *
     * @param string $addonName 插件名称
     * @param array $configData 配置数据
     * @return bool
     */
    public function saveAddonConfig(string $addonName, array $configData): bool
    {
        logger()->info("[插件配置] 开始处理配置保存", [
            'addonName' => $addonName,
            'configData_keys' => array_keys($configData),
            'configData_count' => count($configData)
        ]);

        $addonDir = $this->addonPath . '/' . $addonName;
        $configFile = $addonDir . '/config.php';

        if (!file_exists($configFile)) {
            logger()->error("[插件配置] 配置文件不存在", [
                'addonName' => $addonName,
                'configFile' => $configFile
            ]);
            return false;
        }

        try {
            // 采用更优雅的配置保存方案：重新生成配置文件
            logger()->info("[插件配置] 采用重新生成方案保存配置", ['addonName' => $addonName]);

            // 读取并解析原始配置文件
            $originalConfig = $this->parseConfigFile($configFile);
            if (!is_array($originalConfig)) {
                logger()->warning("[插件配置] 原始配置解析失败，使用默认配置", [
                    'addonName' => $addonName,
                    'originalConfig_type' => gettype($originalConfig)
                ]);
                $originalConfig = ['enabled' => true, 'configs' => []];
            }

            // 更新配置项的值
            $updatedConfig = $this->updateConfigValues($originalConfig, $configData, $addonName);

            // 重新生成配置文件
            $newContent = $this->generateConfigFile($configFile, $updatedConfig);

            // 备份原文件（可选）
            $backupFile = $configFile . '.backup.' . date('YmdHis');
            if (copy($configFile, $backupFile)) {
                logger()->info("[插件配置] 原配置文件已备份", [
                    'addonName' => $addonName,
                    'backupFile' => $backupFile
                ]);
            }

            // 写入新配置
            $writeResult = file_put_contents($configFile, $newContent);
            if ($writeResult === false) {
                logger()->error("[插件配置] 配置文件写入失败", [
                    'addonName' => $addonName,
                    'configFile' => $configFile
                ]);
                return false;
            }

            logger()->info("[插件配置] 配置保存成功", [
                'addonName' => $addonName,
                'configFile' => $configFile,
                'written_bytes' => $writeResult
            ]);

            return true;
        } catch (\Throwable $e) {
            logger()->error("[插件配置] 保存配置异常", [
                'addonName' => $addonName,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 解析配置文件内容
     *
     * @param string $configFile
     * @return array|null
     */
    private function parseConfigFile(string $configFile): ?array
    {
        if (!file_exists($configFile)) {
            return null;
        }

        // 安全地包含配置文件
        $config = include $configFile;

        return is_array($config) ? $config : null;
    }

    /**
     * 更新配置项的值
     *
     * @param array $originalConfig
     * @param array $newData
     * @param string $addonName
     * @return array
     */
    private function updateConfigValues(array $originalConfig, array $newData, string $addonName): array
    {
        $updatedConfig = $originalConfig;
        $updatedFields = [];

        // 更新configs数组中的值
        if (isset($updatedConfig['configs']) && is_array($updatedConfig['configs'])) {
            foreach ($updatedConfig['configs'] as &$configItem) {
                if (!isset($configItem['name'])) {
                    continue;
                }

                $fieldName = $configItem['name'];
                if (isset($newData[$fieldName])) {
                    $oldValue = $configItem['value'] ?? null;
                    $fieldType = $configItem['type'] ?? 'text';

                    // 类型转换
                    $newValue = $this->convertConfigValue($newData[$fieldName], $fieldType);

                    logger()->info("[插件配置] 更新配置字段", [
                        'addonName' => $addonName,
                        'fieldName' => $fieldName,
                        'fieldType' => $fieldType,
                        'oldValue' => $oldValue,
                        'newValue' => $newValue,
                        'rawInput' => $newData[$fieldName]
                    ]);

                    $configItem['value'] = $newValue;
                    $updatedFields[] = $fieldName;
                }
            }
        }

        logger()->info("[插件配置] 配置值更新完成", [
            'addonName' => $addonName,
            'updatedFields' => $updatedFields,
            'updatedCount' => count($updatedFields)
        ]);

        return $updatedConfig;
    }

    /**
     * 生成新的配置文件内容
     *
     * @param string $originalFile
     * @param array $config
     * @return string
     */
    private function generateConfigFile(string $originalFile, array $config): string
    {
        // 读取原文件，保留头部注释
        $originalContent = file_get_contents($originalFile);
        $lines = explode("\n", $originalContent);

        // 提取文件头部的注释和PHP开始标签
        $headerLines = [];
        $codeStartIndex = -1;

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (strpos($trimmedLine, '<?php') === 0) {
                $headerLines[] = $line;
                $codeStartIndex = $index;
                break;
            }
            $headerLines[] = $line;
        }

        // 如果没找到<?php标签，添加默认头部
        if ($codeStartIndex === -1) {
            $headerLines = ['<?php', ''];
        }

        // 生成配置数组代码
        $configCode = $this->arrayToPhpCode($config);

        // 组合最终内容
        $finalContent = implode("\n", $headerLines) . "\n\nreturn " . $configCode . ";\n";

        return $finalContent;
    }

    /**
     * 转换配置值类型
     *
     * @param mixed $value 原始值
     * @param string $type 字段类型
     * @return mixed 转换后的值
     */
    private function convertConfigValue($value, string $type)
    {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? (float) $value : $value;
            case 'switch':
                return in_array($value, ['true', '1', 1, true], true);
            case 'checkbox':
            case 'multi_select':
                // 处理数组类型
                if (is_array($value)) {
                    return array_filter($value); // 过滤空值
                }
                // 如果是单个值，转换为数组
                return $value ? [$value] : [];
            case 'radio':
            case 'select':
                // 单选类型，直接返回值
                return $value;
            case 'date':
                // 验证日期格式
                if (strtotime($value) !== false) {
                    return $value;
                }
                return null;
            case 'color':
                // 验证颜色格式 (#RRGGBB 或 #RGB)
                if (preg_match('/^#[a-fA-F0-9]{3,6}$/', $value)) {
                    return $value;
                }
                return '#000000'; // 默认黑色
            case 'file':
            case 'image':
                // 文件路径，直接返回值
                return $value;
            case 'rich_text':
                // 富文本内容，直接返回值（不过滤HTML）
                return $value;
            default:
                return $value;
        }
    }

    /**
     * 安装插件资源文件
     *
     * @param string $addonName 插件目录名
     * @return bool
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
            logger()->info("[插件安装] 发现数据库配置文件，开始执行数据库表管理");
            $dbResult = $this->manageDatabaseTables($addonName, $databaseFile);
            if (!$dbResult) {
                logger()->error("[插件安装] 插件 {$addonName} 数据库表管理失败");
                return false;
            }
        } else {
            logger()->info("[插件安装] 插件 {$addonName} 没有数据库配置文件，跳过数据库表管理");
        }

        // 插件安装只处理数据库，不处理资源文件、菜单和权限
        logger()->info("[插件安装] 插件 {$addonName} 安装完成（只处理数据库）");
        return true;
    }

    /**
     * 卸载插件
     *
     * @param string $addonName 插件目录名
     * @return bool
     */
    /**
     * 卸载插件资源文件（保留数据表）
     *
     * 清理插件的文件和目录资源，但保留插件创建的数据表和数据
     *
     * @param string $addonName 插件名称
     * @return bool
     */
    public function uninstallAddonAssets(string $addonName): bool
    {
        $addonDir = $this->addonPath . '/' . $addonName;
        $assetsFile = $addonDir . '/Manager/assets.json';
        $databaseFile = $addonDir . '/Manager/database.json';

        logger()->info("[插件卸载] 开始卸载插件资源: {$addonName}");

        // 插件卸载时保留数据表，只清理其他资源
        logger()->info("[插件卸载] 插件 {$addonName} 卸载时保留数据表，跳过数据库清理");

        if (!file_exists($assetsFile)) {
            logger()->info("[插件卸载] 插件 {$addonName} 没有资产配置文件，跳过资源清理");
            return true;
        }

        try {
            $assetsConfig = json_decode(file_get_contents($assetsFile), true);
            if (!$assetsConfig) {
                logger()->error("[插件卸载] 插件 {$addonName} 的资产配置文件格式错误");
                return false;
            }

            // 处理文件移动回插件目录
            if (isset($assetsConfig['files'])) {
                $fileCount = count($assetsConfig['files']);
                logger()->info("[插件卸载] 插件 {$addonName} 开始移动文件回插件目录，共 {$fileCount} 个文件");

                foreach ($assetsConfig['files'] as $source => $target) {
                    $targetPath = BASE_PATH . '/' . $target;
                    $sourcePath = $addonDir . '/' . $source;

                    if (file_exists($targetPath)) {
                        // 确保源目录存在
                        $sourceDir = dirname($sourcePath);
                        if (!is_dir($sourceDir)) {
                            mkdir($sourceDir, 0755, true);
                            logger()->info("[插件卸载] 创建源目录: {$sourceDir}");
                        }

                        if ($this->moveFile($targetPath, $sourcePath)) {
                            logger()->info("[插件卸载] 移动文件成功: {$targetPath} -> {$sourcePath}");
                        } else {
                            logger()->warning("[插件卸载] 移动文件失败: {$targetPath} -> {$sourcePath}");
                        }
                    } else {
                        logger()->info("[插件卸载] 文件不存在，跳过: {$targetPath}");
                    }
                }
            }

            // 处理目录移动回插件目录
            if (isset($assetsConfig['directories'])) {
                $dirCount = count($assetsConfig['directories']);
                logger()->info("[插件卸载] 插件 {$addonName} 开始移动目录回插件目录，共 {$dirCount} 个目录");

                // 对目录按路径深度排序，确保先移动深层目录
                $directories = $assetsConfig['directories'];
                uksort($directories, function($a, $b) {
                    return substr_count($b, '/') - substr_count($a, '/');
                });

                foreach ($directories as $source => $target) {
                    $targetPath = BASE_PATH . '/' . $target;
                    $sourcePath = $addonDir . '/' . $source;

                    if (is_dir($targetPath)) {
                        if ($this->moveDirectory($targetPath, $sourcePath)) {
                            logger()->info("[插件卸载] 移动目录成功: {$targetPath} -> {$sourcePath}");
                        } else {
                            logger()->warning("[插件卸载] 移动目录失败: {$targetPath} -> {$sourcePath}");
                        }
                    } else {
                        logger()->info("[插件卸载] 目录不存在，跳过: {$targetPath}");
                    }
                }
            }

            logger()->info("[插件卸载] 插件 {$addonName} 资源卸载完成");
            return true;
        } catch (\Throwable $e) {
            logger()->error("[插件卸载] 插件 {$addonName} 资源卸载失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 递归删除目录及其内容
     *
     * @param string $dir 目录路径
     * @return bool
     */
    private function removeDirectory(string $dir): bool
    {
        try {
            if (!is_dir($dir)) {
                return true;
            }

            $files = array_diff(scandir($dir), ['.', '..']);

            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->removeDirectory($path);
                } else {
                    unlink($path);
                    logger()->info("[插件卸载] 删除文件: {$path}");
                }
            }

            if (rmdir($dir)) {
                logger()->info("[插件卸载] 删除目录: {$dir}");
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            logger()->error("[插件卸载] 删除目录失败 {$dir}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 执行插件钩子
     *
     * @param string $addonName 插件名称
     * @param string $hook 钩子名称 (install, enable, disable, uninstall)
     * @return bool
     */
    public function executeAddonHook(string $addonName, string $hook): bool
    {
        try {
            $addonDir = $this->addonPath . '/' . $addonName;
            $setupFile = $addonDir . '/Manager/Setup.php';

            if (!file_exists($setupFile)) {
                logger()->info("[插件钩子] 插件 {$addonName} 没有 Setup.php 文件，跳过 {$hook} 钩子");
                return true;
            }

            // 构建类名
            $className = "Addons\\{$addonName}\\Manager\\Setup";

            if (!class_exists($className)) {
                logger()->warning("[插件钩子] 插件 {$addonName} 的 Setup 类不存在: {$className}");
                return false;
            }

            logger()->info("[插件钩子] 开始执行插件 {$addonName} 的 {$hook} 钩子");

            // 实例化 Setup 类
            $setupInstance = new $className();

            // 检查方法是否存在
            if (!method_exists($setupInstance, $hook)) {
                logger()->warning("[插件钩子] 插件 {$addonName} 的 Setup 类没有 {$hook} 方法");
                return false;
            }

            // 调用钩子方法
            $result = $setupInstance->{$hook}();

            if ($result) {
                logger()->info("[插件钩子] 插件 {$addonName} 的 {$hook} 钩子执行成功");
                return true;
            } else {
                logger()->error("[插件钩子] 插件 {$addonName} 的 {$hook} 钩子执行失败");
                return false;
            }

        } catch (\Throwable $e) {
            logger()->error("[插件钩子] 执行插件 {$addonName} 的 {$hook} 钩子异常: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 管理数据库表
     *
     * 执行插件的数据库表创建和升级操作
     *
     * @param string $addonName 插件名称
     * @param string $databaseFile 数据库配置文件路径
     * @return bool
     */
    private function manageDatabaseTables(string $addonName, string $databaseFile): bool
    {
        try {
            logger()->info("[数据库管理] 开始执行插件 {$addonName} 的数据库表管理");

            // 读取数据库配置文件
            $config = json_decode(file_get_contents($databaseFile), true);
            if (!$config) {
                logger()->error("[数据库管理] 插件 {$addonName} 的数据库配置文件格式错误");
                return false;
            }

            // 执行表创建和升级
            if (isset($config['tables']) && is_array($config['tables'])) {
                foreach ($config['tables'] as $tableName => $tableConfig) {
                    $result = $this->createTableAndInsertData($addonName, $tableName, $tableConfig);
                    if (!$result) {
                        logger()->error("[数据库管理] 插件 {$addonName} 表管理失败: {$tableName}");
                        return false;
                    }
                }
            }

            logger()->info("[数据库管理] 插件 {$addonName} 的数据库表管理执行完成");
            return true;

        } catch (\Throwable $e) {
            logger()->error("[数据库管理] 执行插件 {$addonName} 的数据库表管理异常: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 创建表并插入数据（支持升级）
     */
    private function createTableAndInsertData(string $addonName, string $tableName, array $tableConfig): bool
    {
        try {
            logger()->info("[数据库管理] 创建或升级表并插入数据: {$addonName} -> {$tableName}");

            // 检查表是否已存在
            $existingTables = Db::select("SHOW TABLES LIKE '{$tableName}'");
            $tableExists = !empty($existingTables);

            if ($tableExists) {
                // 表已存在，执行升级操作
                logger()->info("[数据库管理] 表 {$tableName} 已存在，开始升级操作");
                $upgradeResult = $this->upgradeTable($addonName, $tableName, $tableConfig);
                if (!$upgradeResult) {
                    logger()->error("[数据库管理] 表 {$tableName} 升级失败");
                    return false;
                }

                // 表升级时进行种子数据的比对和更新
                if (isset($tableConfig['data']) && is_array($tableConfig['data']) && !empty($tableConfig['data'])) {
                    logger()->info("[数据库管理] 表 {$tableName} 升级完成，开始比对和更新种子数据");
                    $this->upgradeSeedData($tableName, $tableConfig['data']);
                    logger()->info("[数据库管理] 表 {$tableName} 种子数据比对和更新完成");
                } else {
                    logger()->info("[数据库管理] 表 {$tableName} 升级完成，无种子数据需要处理");
                }
            } else {
                // 表不存在，创建新表
                $sql = $this->buildCreateTableSql($tableName, $tableConfig);
                logger()->info("[数据库管理] 执行创建表SQL: {$tableName}");
                Db::statement($sql);
                logger()->info("[数据库管理] 表 {$tableName} 创建成功");

                // 创建索引
                $indexSqls = $this->buildIndexSqls($tableName, $tableConfig['columns'] ?? []);
                if (!empty($indexSqls)) {
                    logger()->info("[数据库管理] 创建表索引: {$tableName}");
                    foreach ($indexSqls as $indexSql) {
                        logger()->info("[数据库管理] 执行索引SQL: {$indexSql}");
                        Db::statement($indexSql);
                    }
                    logger()->info("[数据库管理] 表 {$tableName} 索引创建成功");
                }

                // 新建表时插入种子数据
                if (isset($tableConfig['data']) && is_array($tableConfig['data']) && !empty($tableConfig['data'])) {
                    logger()->info("[数据库管理] 插入种子数据: {$tableName}");
                    $this->insertSeedData($tableName, $tableConfig['data']);
                    logger()->info("[数据库管理] 种子数据插入成功: {$tableName}");
                }
            }

            logger()->info("[数据库管理] 表 {$tableName} 处理完成");
            return true;

        } catch (\Throwable $e) {
            logger()->error("[数据库管理] 表处理失败: {$tableName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除表
     */
    private function dropTable(string $addonName, string $tableName): bool
    {
        try {
            logger()->info("[数据库管理] 删除表: {$addonName} -> {$tableName}");

            // 检查表是否存在
            $existingTables = Db::select("SHOW TABLES LIKE '{$tableName}'");
            if (empty($existingTables)) {
                logger()->info("[数据库管理] 表 {$tableName} 不存在，跳过删除");
                return true;
            }

            // 删除表
            Db::statement("DROP TABLE `{$tableName}`");
            logger()->info("[数据库管理] 表 {$tableName} 删除成功");
            return true;

        } catch (\Throwable $e) {
            logger()->error("[数据库管理] 删除表失败: {$tableName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 升级表结构
     */
    private function upgradeTable(string $addonName, string $tableName, array $tableConfig): bool
    {
        try {
            logger()->info("[数据库管理] 开始升级表: {$addonName} -> {$tableName}");

            // 获取现有表结构
            logger()->info("[数据库管理] 获取现有表结构 (使用SHOW FULL COLUMNS): {$tableName}");
            $existingSchema = $this->getExistingTableSchema($tableName);
            if ($existingSchema === null) {
                logger()->error("[数据库管理] 无法获取表 {$tableName} 的现有结构");
                return false;
            }
            logger()->info("[数据库管理] 现有表结构获取完成: {$tableName}, 字段数: " . count($existingSchema['columns']) . ", 索引数: " . count($existingSchema['indexes']));

            // 获取期望的表结构
            logger()->info("[数据库管理] 获取期望的表结构: {$tableName}");
            $expectedSchema = $this->buildExpectedSchema($tableName, $tableConfig);
            logger()->info("[数据库管理] 期望表结构构建完成: {$tableName}, 字段数: " . count($expectedSchema['columns']) . ", 索引数: " . count($expectedSchema['indexes']));

            // 比对差异并生成升级SQL
            logger()->info("[数据库管理] 开始比对表结构差异: {$tableName}");
            $alterSqls = $this->generateAlterSqls($tableName, $existingSchema, $expectedSchema);

            if (empty($alterSqls)) {
                logger()->info("[数据库管理] 表 {$tableName} 无需升级，结构完全一致");
                return true;
            }

            logger()->info("[数据库管理] 表 {$tableName} 需要执行 " . count($alterSqls) . " 个变更SQL");

            // 执行升级SQL
            $executedCount = 0;
            foreach ($alterSqls as $sql) {
                logger()->info("[数据库管理] 执行变更SQL ({$executedCount}/" . count($alterSqls) . "): {$sql}");
                Db::statement($sql);
                $executedCount++;
            }
            logger()->info("[数据库管理] 表 {$tableName} 成功执行了 {$executedCount} 个变更SQL");

            logger()->info("[数据库管理] 表 {$tableName} 升级完成");
            return true;

        } catch (\Throwable $e) {
            logger()->error("[数据库管理] 升级表失败: {$tableName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取现有表结构
     */
    private function getExistingTableSchema(string $tableName): ?array
    {
        try {
            // 获取表字段完整信息（包含COMMENT）
            $columns = Db::select("SHOW FULL COLUMNS FROM `{$tableName}`");
            if (empty($columns)) {
                return null;
            }

            $schema = [
                'columns' => [],
                'indexes' => []
            ];

            // 处理字段信息
            foreach ($columns as $column) {
                $field = $column->Field;
                $fullType = $column->Type;

                // 获取SHOW FULL COLUMNS返回的所有字段
                $columnData = (array) $column;
                logger()->debug("[数据库管理] 处理字段 {$tableName}.{$field}", [
                    'all_fields' => array_keys($columnData),
                    'raw_type' => $fullType,
                    'null' => $column->Null,
                    'key' => $column->Key,
                    'default' => $column->Default,
                    'extra' => $column->Extra,
                    'comment' => $column->Comment ?? 'NO_COMMENT_FIELD'
                ]);

                // 使用SHOW FULL COLUMNS的Comment字段直接获取注释
                $type = $fullType;
                $comment = $column->Comment ?? '';

                logger()->debug("[数据库管理] 字段 {$field} 注释信息", [
                    'raw_type' => $fullType,
                    'comment_from_field' => "'{$comment}'",
                    'type_clean' => $type
                ]);

                // 解析类型信息
                $parsedType = $this->parseColumnType($type);

                logger()->debug("[数据库管理] 字段 {$field} 类型解析结果", [
                    'original_type' => $fullType,
                    'clean_type' => $type,
                    'parsed_type' => $parsedType['type'],
                    'parsed_length' => $parsedType['length'],
                    'parsed_unsigned' => $parsedType['unsigned']
                ]);

                $schema['columns'][$field] = [
                    'name' => $field,  // 添加 name 键
                    'type' => $parsedType['type'],
                    'length' => $parsedType['length'],
                    'unsigned' => $parsedType['unsigned'],
                    'nullable' => $column->Null === 'YES',
                    'default' => $column->Default,
                    'auto_increment' => isset($column->Extra) && stripos($column->Extra, 'auto_increment') !== false,
                    'primary' => isset($column->Key) && str_contains($column->Key, 'PRI'),
                    'comment' => $comment,
                    'removed' => str_contains($comment, '(已移除)')
                ];

                logger()->debug("[数据库管理] 字段 {$field} 最终配置", [
                    'name' => $field,
                    'type' => $parsedType['type'],
                    'length' => $parsedType['length'],
                    'unsigned' => $parsedType['unsigned'],
                    'nullable' => $column->Null === 'YES',
                    'default' => $column->Default,
                    'auto_increment' => isset($column->Extra) && stripos($column->Extra, 'auto_increment') !== false,
                    'primary' => isset($column->Key) && str_contains($column->Key, 'PRI'),
                    'comment' => "'{$comment}'", // 明确显示注释内容
                    'removed' => str_contains($comment, '(已移除)')
                ]);
            }

            // 获取索引信息
            logger()->info("[数据库管理] 获取表 {$tableName} 的索引信息");
            $indexes = Db::select("SHOW INDEX FROM `{$tableName}`");
            logger()->debug("[数据库管理] 表 {$tableName} 共有 " . count($indexes) . " 个索引记录");

            foreach ($indexes as $index) {
                $indexName = $index->Key_name;
                $columnName = $index->Column_name;

                logger()->debug("[数据库管理] 处理索引记录: {$indexName} -> {$columnName}", [
                    'non_unique' => $index->Non_unique,
                    'seq_in_index' => $index->Seq_in_index
                ]);

                if ($indexName === 'PRIMARY') {
                    logger()->debug("[数据库管理] 跳过主键索引: {$indexName}");
                    continue; // 主键索引跳过
                }

                if (!isset($schema['indexes'][$indexName])) {
                    $schema['indexes'][$indexName] = [
                        'columns' => [],
                        'unique' => !$index->Non_unique
                    ];
                    logger()->debug("[数据库管理] 创建新索引配置: {$indexName}, unique=" . (!$index->Non_unique ? 'true' : 'false'));
                }
                $schema['indexes'][$indexName]['columns'][] = $columnName;
                logger()->debug("[数据库管理] 索引 {$indexName} 添加列: {$columnName}");
            }

            logger()->info("[数据库管理] 表 {$tableName} 索引信息获取完成，共 " . count($schema['indexes']) . " 个索引");

            return $schema;

        } catch (\Throwable $e) {
            logger()->error("[数据库管理] 获取表结构失败: {$tableName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 构建期望的表结构
     */
    private function buildExpectedSchema(string $tableName, array $tableConfig): array
    {
        $columns = $tableConfig['columns'] ?? [];

        // 自动添加系统字段（如果未定义）
        $hasIdColumn = isset($columns['id']);
        $hasCreatedAt = isset($columns['created_at']);
        $hasUpdatedAt = isset($columns['updated_at']);

        if (!$hasIdColumn) {
            $columns['id'] = [
                'name' => 'id',
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
                'primary' => true,
                'comment' => '主键ID'
            ];
        }

        if (!$hasCreatedAt) {
            $columns['created_at'] = [
                'name' => 'created_at',
                'type' => 'TIMESTAMP',
                'nullable' => true,
                'comment' => '创建时间'
            ];
        }

        if (!$hasUpdatedAt) {
            $columns['updated_at'] = [
                'name' => 'updated_at',
                'type' => 'TIMESTAMP',
                'nullable' => true,
                'comment' => '更新时间'
            ];
        }

        // 转换配置格式
        logger()->debug("[数据库管理] 转换期望字段配置格式");
        $convertedColumns = $this->convertColumnsConfig($columns);

        $schema = ['columns' => [], 'indexes' => []];

        foreach ($convertedColumns as $column) {
            $fieldName = $column['name'];
            $normalizedType = $this->normalizeType($column['type']);

            $fieldConfig = [
                'name' => $fieldName,  // 添加 name 键
                'type' => $normalizedType['type'],
                'length' => $normalizedType['length'] ?? $column['length'] ?? null,
                'unsigned' => $normalizedType['unsigned'],
                'nullable' => $column['nullable'] ?? false,
                'default' => $column['default'] ?? null,
                'auto_increment' => $column['auto_increment'] ?? false,
                'primary' => $column['primary'] ?? false,
                'comment' => $column['comment'] ?? '',
                'removed' => false
            ];

            $schema['columns'][$fieldName] = $fieldConfig;
            logger()->debug("[数据库管理] 期望字段配置: {$fieldName}", $fieldConfig);
        }

        // 处理索引
        foreach ($columns as $fieldName => $config) {
            // 处理唯一索引
            if (isset($config['unique']) && $config['unique']) {
                $indexName = "uk_{$fieldName}";
                $schema['indexes'][$indexName] = [
                    'columns' => [$fieldName],
                    'unique' => true
                ];
            }
            // 处理普通索引
            elseif (isset($config['index']) && $config['index']) {
                $indexName = "idx_{$fieldName}";
                $schema['indexes'][$indexName] = [
                    'columns' => [$fieldName],
                    'unique' => false
                ];
            }
        }

        return $schema;
    }

    /**
     * 生成升级SQL
     */
    private function generateAlterSqls(string $tableName, array $existing, array $expected): array
    {
        logger()->info("[数据库管理] 开始生成表变更SQL: {$tableName}");

        $alterSqls = [];

        // 处理字段变更
        $columnSqls = $this->generateColumnAlterSqls($tableName, $existing, $expected);
        $alterSqls = array_merge($alterSqls, $columnSqls);

        // 处理索引变更
        $indexSqls = $this->generateIndexAlterSqls($tableName, $existing, $expected);
        $alterSqls = array_merge($alterSqls, $indexSqls);

        $totalChanges = count($alterSqls);
        logger()->info("[数据库管理] 表 {$tableName} 生成了 {$totalChanges} 个变更SQL (字段: " . count($columnSqls) . ", 索引: " . count($indexSqls) . ")");

        return $alterSqls;
    }

    /**
     * 生成字段变更SQL
     */
    private function generateColumnAlterSqls(string $tableName, array $existing, array $expected): array
    {
        logger()->info("[数据库管理] 开始分析字段变更: {$tableName}");

        $sqls = [];
        $existingColumns = $existing['columns'];
        $expectedColumns = $expected['columns'];

        logger()->info("[数据库管理] 表 {$tableName} 现有字段数量: " . count($existingColumns) . ", 期望字段数量: " . count($expectedColumns));

        // 找出需要添加、修改、移除的字段
        $toAdd = array_diff_key($expectedColumns, $existingColumns);
        $toModify = [];
        $toRemove = array_diff_key($existingColumns, $expectedColumns);

        logger()->info("[数据库管理] 表 {$tableName} 字段变更统计 - 添加: " . count($toAdd) . ", 修改: " . count($toModify) . ", 移除: " . count($toRemove));

        // 检查需要修改的字段
        foreach ($expectedColumns as $field => $expectedConfig) {
            if (isset($existingColumns[$field])) {
                $existingConfig = $existingColumns[$field];
                if (!$this->isColumnConfigEqual($existingConfig, $expectedConfig)) {
                    $toModify[$field] = $expectedConfig;
                    logger()->info("[数据库管理] 检测到字段配置不同: {$tableName}.{$field}", [
                        'existing' => $existingConfig,
                        'expected' => $expectedConfig
                    ]);
                } else {
                    logger()->debug("[数据库管理] 字段配置相同，跳过: {$tableName}.{$field}");
                }
            }
        }

        logger()->info("[数据库管理] 表 {$tableName} 字段变更确认 - 添加: " . count($toAdd) . ", 修改: " . count($toModify) . ", 移除: " . count($toRemove));

        // 生成添加字段的SQL
        foreach ($toAdd as $field => $config) {
            // ALTER TABLE ADD COLUMN 时不包含 PRIMARY KEY，避免冲突
            $definition = $this->buildColumnDefinition($config, false);
            $sqls[] = "ALTER TABLE `{$tableName}` ADD COLUMN {$definition}";
            logger()->info("[数据库管理] 添加字段: {$tableName}.{$field}", [
                'type' => $config['type'],
                'nullable' => $config['nullable'],
                'default' => $config['default'] ?? null,
                'comment' => $config['comment'] ?? ''
            ]);
        }

        // 生成修改字段的SQL
        foreach ($toModify as $field => $config) {
            // ALTER TABLE MODIFY COLUMN 时不包含 PRIMARY KEY，避免冲突
            $definition = $this->buildColumnDefinition($config, false);
            $sqls[] = "ALTER TABLE `{$tableName}` MODIFY COLUMN {$definition}";
            logger()->info("[数据库管理] 修改字段: {$tableName}.{$field}", [
                'type' => $config['type'],
                'nullable' => $config['nullable'],
                'default' => $config['default'] ?? null,
                'comment' => $config['comment'] ?? ''
            ]);
        }

        // 生成移除字段的SQL（标记为已移除）
        foreach ($toRemove as $field => $config) {
            if (!$config['removed']) { // 只处理未标记为移除的字段
                // 更新字段注释，添加"(已移除)"标记
                $currentComment = $config['comment'];
                if (!str_contains($currentComment, '(已移除)')) {
                    $newComment = $currentComment ? $currentComment . ' (已移除)' : '已移除';
                    $sqls[] = "ALTER TABLE `{$tableName}` MODIFY COLUMN `{$field}` {$config['type']} " .
                              "COMMENT '{$newComment}'";
                    logger()->info("[数据库管理] 标记字段为已移除: {$tableName}.{$field}");
                }
            }
        }

        return $sqls;
    }

    /**
     * 生成索引变更SQL
     */
    private function generateIndexAlterSqls(string $tableName, array $existing, array $expected): array
    {
        logger()->info("[数据库管理] 开始分析索引变更: {$tableName}");

        $sqls = [];
        $existingIndexes = $existing['indexes'];
        $expectedIndexes = $expected['indexes'];

        logger()->info("[数据库管理] 表 {$tableName} 现有索引数量: " . count($existingIndexes) . ", 期望索引数量: " . count($expectedIndexes));

        // 找出需要添加和删除的索引
        $toAdd = array_diff_key($expectedIndexes, $existingIndexes);
        $toRemove = array_diff_key($existingIndexes, $expectedIndexes);

        logger()->info("[数据库管理] 表 {$tableName} 索引变更统计 - 添加: " . count($toAdd) . ", 删除: " . count($toRemove));

        // 生成删除索引的SQL
        foreach ($toRemove as $indexName => $config) {
            $sqls[] = "ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`";
            logger()->info("[数据库管理] 删除索引: {$tableName}.{$indexName}", [
                'index_type' => $config['unique'] ? 'unique' : 'normal',
                'columns' => $config['columns']
            ]);
        }

        // 生成添加索引的SQL
        foreach ($toAdd as $indexName => $config) {
            $columns = '`' . implode('`, `', $config['columns']) . '`';
            $indexType = $config['unique'] ? 'UNIQUE INDEX' : 'INDEX';
            $sqls[] = "ALTER TABLE `{$tableName}` ADD {$indexType} `{$indexName}` ({$columns})";
            logger()->info("[数据库管理] 添加索引: {$tableName}.{$indexName}", [
                'index_type' => $config['unique'] ? 'unique' : 'normal',
                'columns' => $config['columns']
            ]);
        }

        return $sqls;
    }

    /**
     * 检查字段配置是否相等
     */
    private function isColumnConfigEqual(array $existing, array $expected): bool
    {
        $fieldName = $existing['name'] ?? 'unknown';

        // 数据标准化：将空字符串、空值等统一处理
        $existingNormalized = $this->normalizeColumnConfig($existing);
        $expectedNormalized = $this->normalizeColumnConfig($expected);

        // 记录原始数据和标准化数据用于调试
        logger()->debug("[数据库管理] 字段 {$fieldName} 原始数据比较", [
            'existing_raw' => $existing,
            'expected_raw' => $expected,
            'existing_normalized' => $existingNormalized,
            'expected_normalized' => $expectedNormalized
        ]);

        // 收集所有差异
        $differences = [];

        // 比较各个属性
        if ($existingNormalized['type'] !== $expectedNormalized['type']) {
            $differences[] = "类型: {$existingNormalized['type']} -> {$expectedNormalized['type']}";
        }

        if ($existingNormalized['length'] !== $expectedNormalized['length']) {
            $differences[] = "长度: " . var_export($existingNormalized['length'], true) . " -> " . var_export($expectedNormalized['length'], true);
        }

        if ($existingNormalized['unsigned'] !== $expectedNormalized['unsigned']) {
            $differences[] = "无符号: " . var_export($existingNormalized['unsigned'], true) . " -> " . var_export($expectedNormalized['unsigned'], true);
        }

        if ($existingNormalized['nullable'] !== $expectedNormalized['nullable']) {
            $differences[] = "可空性: " . var_export($existingNormalized['nullable'], true) . " -> " . var_export($expectedNormalized['nullable'], true);
        }

        if ($existingNormalized['default'] !== $expectedNormalized['default']) {
            $existingDefault = $existingNormalized['default'];
            $expectedDefault = $expectedNormalized['default'];

            logger()->debug("[数据库管理] 字段 {$fieldName} 默认值详细比较", [
                'existing_type' => gettype($existingDefault),
                'expected_type' => gettype($expectedDefault),
                'existing_value' => var_export($existingDefault, true),
                'expected_value' => var_export($expectedDefault, true),
                'strict_equal' => $existingDefault === $expectedDefault,
                'loose_equal' => $existingDefault == $expectedDefault
            ]);

            $differences[] = "默认值: '" . ($existingDefault ?? 'NULL') . "' -> '" . ($expectedDefault ?? 'NULL') . "'";
        }

        if ($existingNormalized['auto_increment'] !== $expectedNormalized['auto_increment']) {
            $differences[] = "自增: " . var_export($existingNormalized['auto_increment'], true) . " -> " . var_export($expectedNormalized['auto_increment'], true);
        }

        if ($existingNormalized['primary'] !== $expectedNormalized['primary']) {
            $differences[] = "主键: " . var_export($existingNormalized['primary'], true) . " -> " . var_export($expectedNormalized['primary'], true);
        }

        if ($existingNormalized['comment'] !== $expectedNormalized['comment']) {
            $differences[] = "注释: '{$existingNormalized['comment']}' -> '{$expectedNormalized['comment']}'";
        }

        // 如果有差异，记录详细信息并返回false
        if (!empty($differences)) {
            logger()->info("[数据库管理] 字段 {$fieldName} 配置差异详情", [
                'existing_normalized' => $existingNormalized,
                'expected_normalized' => $expectedNormalized,
                'differences' => $differences
            ]);

            // 在主要日志中显示差异摘要
            logger()->info("[数据库管理] 字段 {$fieldName} 配置不同: " . implode('; ', $differences));
            return false;
        }

        logger()->debug("[数据库管理] 字段 {$fieldName} 配置完全相同");
        return true;
    }

    /**
     * 标准化默认值，确保数字字符串和数字能够正确比较
     */
    private function normalizeDefaultValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        // 如果是纯数字字符串，转换为数字
        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            $numValue = (int)$value;
            // 如果转换后的数字和原字符串相等，则使用数字
            if ((string)$numValue === $value) {
                return $numValue;
            }
        }

        return $value;
    }

    /**
     * 标准化字段配置，确保数据类型一致
     */
    private function normalizeColumnConfig(array $config): array
    {
        return [
            'name' => $config['name'] ?? '',
            'type' => $this->normalizeType($config['type'] ?? '')['type'] ?? '',
            'length' => $this->normalizeType($config['type'] ?? '')['length'] ?? null,
            'unsigned' => (bool)($config['unsigned'] ?? false),
            'nullable' => (bool)($config['nullable'] ?? false),
            'default' => $this->normalizeDefaultValue($config['default'] ?? null),
            'auto_increment' => (bool)($config['auto_increment'] ?? false),
            'primary' => (bool)($config['primary'] ?? false),
            'comment' => str_replace(' (已移除)', '', $config['comment'] ?? ''),
            'removed' => (bool)($config['removed'] ?? false)
        ];
    }

    /**
     * 标准化字段类型
     */
    private function normalizeType(string $type): array
    {
        // 转换为大写并标准化常见类型
        $type = strtoupper($type);

        $result = [
            'type' => $type,
            'length' => null,
            'unsigned' => false
        ];

        // 处理长度信息
        if (preg_match('/^(\w+)\((\d+)\)/', $type, $matches)) {
            $baseType = $matches[1];
            $length = (int)$matches[2];

            // 移除 UNSIGNED 标记并记录
            if (stripos($baseType, 'UNSIGNED') !== false) {
                $result['unsigned'] = true;
                $baseType = str_ireplace(' UNSIGNED', '', $baseType);
            }

            $result['type'] = $baseType;
            $result['length'] = $length;
        }

        return $result;
    }


    /**
     * 插入种子数据
     */
    private function insertSeedData(string $tableName, array $data): void
    {
        foreach ($data as $row) {
            try {
                // 构建重复检查条件
                $whereConditions = $this->buildDuplicateCheckConditions($row);

                if (!empty($whereConditions)) {
                    // 检查是否已存在相同的记录
                    $query = Db::table($tableName);
                    foreach ($whereConditions as $condition) {
                        $query->where($condition['column'], $condition['operator'], $condition['value']);
                    }

                    $existing = $query->first();
                    if ($existing) {
                        logger()->info("[数据库管理] 跳过已存在的种子数据记录: {$tableName} - " . json_encode($whereConditions));
                        continue;
                    }
                }

                // 设置时间戳
                $now = date('Y-m-d H:i:s');
                $row['created_at'] = $row['created_at'] ?? $now;
                $row['updated_at'] = $row['updated_at'] ?? $now;

                Db::table($tableName)->insert($row);
                logger()->debug("[数据库管理] 插入种子数据: {$tableName} - " . json_encode($row));

            } catch (\Throwable $e) {
                logger()->warning("[数据库管理] 插入种子数据失败: {$tableName} - " . $e->getMessage());
                // 继续处理下一条记录，不要因为一条失败而中断整个过程
            }
        }
    }

    /**
     * 升级时的数据比对和更新（基于ID字段）
     * 遍历配置数据，根据ID查询现有记录，比较数据是否有变化，有变化则更新
     */
    private function upgradeSeedData(string $tableName, array $data): void
    {
        foreach ($data as $row) {
            try {
                // 只处理有ID字段的记录
                if (!isset($row['id']) || $row['id'] === null || $row['id'] === '') {
                    logger()->info("[数据库管理] 跳过没有ID字段的种子数据记录: {$tableName}");
                    continue;
                }

                $id = $row['id'];

                // 查询现有记录
                $existing = Db::table($tableName)->where('id', $id)->first();
                if (!$existing) {
                    logger()->info("[数据库管理] 种子数据记录不存在，将插入新记录: {$tableName} - ID: {$id}");
                    // 如果记录不存在，插入新记录
                    $now = date('Y-m-d H:i:s');
                    $row['created_at'] = $row['created_at'] ?? $now;
                    $row['updated_at'] = $row['updated_at'] ?? $now;
                    Db::table($tableName)->insert($row);
                    logger()->debug("[数据库管理] 插入新的种子数据: {$tableName} - " . json_encode($row));
                    continue;
                }

                // 将现有记录转换为数组进行比较
                $existingArray = (array) $existing;

                // 移除时间戳字段（这些字段不参与比较）
                $compareRow = $row;
                unset($compareRow['created_at'], $compareRow['updated_at']);
                $compareExisting = $existingArray;
                unset($compareExisting['created_at'], $compareExisting['updated_at']);

                // 比较数据是否有变化
                $hasChanges = false;
                foreach ($compareRow as $key => $value) {
                    // 如果配置中有这个字段，但数据库中没有，说明有变化
                    if (!array_key_exists($key, $compareExisting)) {
                        $hasChanges = true;
                        break;
                    }
                    // 如果值不一样，说明有变化
                    if ($compareExisting[$key] != $value) {
                        $hasChanges = true;
                        break;
                    }
                }

                if ($hasChanges) {
                    // 更新记录
                    $updateData = $compareRow;
                    $updateData['updated_at'] = date('Y-m-d H:i:s');

                    Db::table($tableName)->where('id', $id)->update($updateData);
                    logger()->info("[数据库管理] 更新种子数据: {$tableName} - ID: {$id} - 旧数据: " . json_encode($compareExisting) . " - 新数据: " . json_encode($compareRow));
                } else {
                    logger()->debug("[数据库管理] 种子数据无变化，跳过更新: {$tableName} - ID: {$id}");
                }

            } catch (\Throwable $e) {
                logger()->warning("[数据库管理] 处理种子数据失败: {$tableName} - ID: {$row['id']} - " . $e->getMessage());
                // 继续处理下一条记录，不要因为一条失败而中断整个过程
            }
        }
    }

    /**
     * 构建重复检查条件
     * 优先使用 id 字段，如果没有则使用 name 字段，最后使用所有字段的组合
     */
    private function buildDuplicateCheckConditions(array $row): array
    {
        // 1. 优先使用 id 字段（如果存在且不为空）
        if (isset($row['id']) && $row['id'] !== null && $row['id'] !== '') {
            return [
                ['column' => 'id', 'operator' => '=', 'value' => $row['id']]
            ];
        }

        // 2. 使用 name 字段（如果存在且不为空）
        if (isset($row['name']) && $row['name'] !== null && $row['name'] !== '') {
            return [
                ['column' => 'name', 'operator' => '=', 'value' => $row['name']]
            ];
        }

        // 3. 使用 slug 字段（如果存在且不为空）
        if (isset($row['slug']) && $row['slug'] !== null && $row['slug'] !== '') {
            return [
                ['column' => 'slug', 'operator' => '=', 'value' => $row['slug']]
            ];
        }

        // 4. 如果都没有唯一标识字段，则使用多个字段组合（避免过度检查）
        // 比如同时检查 name 和 value 字段
        $conditions = [];
        if (isset($row['name'])) {
            $conditions[] = ['column' => 'name', 'operator' => '=', 'value' => $row['name']];
        }
        if (isset($row['value'])) {
            $conditions[] = ['column' => 'value', 'operator' => '=', 'value' => $row['value']];
        }

        // 如果有至少两个条件，则使用组合检查
        if (count($conditions) >= 2) {
            return $conditions;
        }

        // 如果找不到合适的唯一标识，返回空数组（不进行重复检查）
        return [];
    }

    /**
     * 构建创建表SQL
     */
    private function buildCreateTableSql(string $table, array $tableConfig): string
    {
        $columns = $tableConfig['columns'] ?? [];
        $comment = $tableConfig['comment'] ?? '';

        // 自动添加主键字段（如果用户没有定义）
        $systemColumns = [];
        $hasIdColumn = false;
        $hasCreatedAt = false;
        $hasUpdatedAt = false;

        foreach ($columns as $name => $config) {
            if ($name === 'id') {
                $hasIdColumn = true;
            }
            if ($name === 'created_at') {
                $hasCreatedAt = true;
            }
            if ($name === 'updated_at') {
                $hasUpdatedAt = true;
            }
        }

        if (!$hasIdColumn) {
            $systemColumns[] = [
                'name' => 'id',
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
                'primary' => true,
                'comment' => '主键ID'
            ];
        }

        // 如果用户没有定义时间戳字段，自动添加
        if (!$hasCreatedAt) {
            $systemColumns[] = [
                'name' => 'created_at',
                'type' => 'TIMESTAMP',
                'nullable' => true,
                'comment' => '创建时间'
            ];
        }

        if (!$hasUpdatedAt) {
            $systemColumns[] = [
                'name' => 'updated_at',
                'type' => 'TIMESTAMP',
                'nullable' => true,
                'comment' => '更新时间'
            ];
        }

        // 合并用户字段和系统字段
        $allColumns = array_merge($systemColumns, $this->convertColumnsConfig($columns));

        $columnDefinitions = [];
        foreach ($allColumns as $column) {
            $definition = $this->buildColumnDefinition($column);
            if ($definition) {
                $columnDefinitions[] = $definition;
            }
        }

        $sql = "CREATE TABLE `{$table}` (\n  " . implode(",\n  ", $columnDefinitions) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($comment) {
            $sql .= " COMMENT '{$comment}'";
        }

        return $sql;
    }

    /**
     * 转换列配置格式
     */
    private function convertColumnsConfig(array $columns): array
    {
        $converted = [];
        foreach ($columns as $name => $config) {
            $column = [
                'name' => $name,
                'type' => strtoupper($config['type'] ?? 'VARCHAR'),
                'nullable' => $config['nullable'] ?? false,
                'comment' => $config['comment'] ?? '',
            ];

            // 处理类型和长度
            if (preg_match('/^(\w+)\((\d+)\)$/', $config['type'], $matches)) {
                $column['type'] = strtoupper($matches[1]);
                $column['length'] = (int)$matches[2];
            }

            // 处理 unsigned 属性
            if (isset($config['unsigned'])) {
                $column['unsigned'] = (bool)$config['unsigned'];
            }

            // 处理 auto_increment 属性
            if (isset($config['auto_increment'])) {
                $column['auto_increment'] = (bool)$config['auto_increment'];
            }

            // 处理 primary 属性
            if (isset($config['primary'])) {
                $column['primary'] = (bool)$config['primary'];
            }

            // 处理 unique 属性
            if (isset($config['unique'])) {
                $column['unique'] = (bool)$config['unique'];
            }

            // 处理 index 属性
            if (isset($config['index'])) {
                $column['index'] = (bool)$config['index'];
            }

            // 默认值
            if (isset($config['default'])) {
                $column['default'] = $config['default'];
            }

            // 特殊处理
            if (strtolower($column['type']) === 'text') {
                $column['nullable'] = true; // TEXT 字段默认可空
            }

            $converted[] = $column;
        }
        return $converted;
    }

    /**
     * 构建索引SQL
     */
    private function buildIndexSqls(string $table, array $columns): array
    {
        $indexSqls = [];

        foreach ($columns as $name => $config) {
            // 处理唯一索引
            if (isset($config['unique']) && $config['unique']) {
                $indexName = "uk_{$name}";
                $indexSqls[] = "ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$indexName}` (`{$name}`)";
            }
            // 处理普通索引
            elseif (isset($config['index']) && $config['index']) {
                $indexName = "idx_{$name}";
                $indexSqls[] = "ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$name}`)";
            }
        }

        return $indexSqls;
    }

    /**
     * 解析列类型
     */
    private function parseColumnType(string $type): array
    {
        $result = [
            'length' => null,
            'unsigned' => false
        ];

        // 移除 UNSIGNED 标记并记录
        if (stripos($type, 'unsigned') !== false) {
            $result['unsigned'] = true;
            $type = str_ireplace(' unsigned', '', $type);
        }

        // 提取长度信息
        if (preg_match('/^(\w+)\((\d+)\)/', $type, $matches)) {
            $result['type'] = strtoupper($matches[1]);
            $result['length'] = (int)$matches[2];
        } else {
            $result['type'] = strtoupper($type);
        }

        return $result;
    }

    /**
     * 构建列定义
     *
     * @param array $column 列配置
     * @param bool $includePrimaryKey 是否包含主键定义（用于CREATE TABLE）
     */
    private function buildColumnDefinition(array $column, bool $includePrimaryKey = true): string
    {
        $name = $column['name'];
        $type = $column['type'];
        $length = $column['length'] ?? null;
        $unsigned = $column['unsigned'] ?? false;
        $nullable = $column['nullable'] ?? true;
        $default = $column['default'] ?? null;
        $autoIncrement = $column['auto_increment'] ?? false;
        $primary = $column['primary'] ?? false;
        $comment = $column['comment'] ?? '';

        $definition = "`{$name}` {$type}";

        // 处理需要长度的类型
        $typesNeedingLength = ['VARCHAR', 'CHAR', 'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT'];
        if (in_array(strtoupper($type), $typesNeedingLength)) {
            if ($length) {
                $definition .= "({$length})";
            } else {
                // 为没有指定长度的类型设置默认长度
                $defaultLengths = [
                    'VARCHAR' => 255,
                    'CHAR' => 255,
                    'INT' => 11,
                    'TINYINT' => 4,
                    'SMALLINT' => 6,
                    'MEDIUMINT' => 9,
                    'BIGINT' => 20,
                ];
                $defaultLength = $defaultLengths[strtoupper($type)] ?? 255;
                $definition .= "({$defaultLength})";
            }
        }

        if ($unsigned) {
            $definition .= " UNSIGNED";
        }

        if ($autoIncrement) {
            $definition .= " AUTO_INCREMENT";
        }

        if (!$nullable) {
            $definition .= " NOT NULL";
        }

        if ($default !== null) {
            if (is_string($default)) {
                $definition .= " DEFAULT '{$default}'";
            } elseif (is_numeric($default)) {
                $definition .= " DEFAULT {$default}";
            }
        }

        if ($comment) {
            $definition .= " COMMENT '{$comment}'";
        }

        if ($primary && $includePrimaryKey) {
            $definition .= " PRIMARY KEY";
        }

        return $definition;
    }


    public function uninstallAddon(string $addonName): bool
    {
        logger()->info("[插件卸载] 开始卸载插件: {$addonName}");

        try {
            // 执行插件卸载钩子
            $this->executeAddonHook($addonName, 'uninstall');

            // 执行插件资源卸载
            $result = $this->uninstallAddonAssets($addonName);

            if ($result) {
                logger()->info("[插件卸载] 插件 {$addonName} 卸载完成");
            } else {
                logger()->warning("[插件卸载] 插件 {$addonName} 部分卸载失败");
            }

            return $result;
        } catch (\Throwable $e) {
            logger()->error("[插件卸载] 插件 {$addonName} 卸载失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 复制目录
     *
     * @param string $source 源目录
     * @param string $target 目标目录
     */
    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
            logger()->info("[插件安装] 创建目标目录: {$target}");
        }

        $files = scandir($source);
        $fileCount = 0;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $file;
            $targetPath = $target . '/' . $file;

            if (is_dir($sourcePath)) {
                logger()->info("[插件安装] 递归复制子目录: {$sourcePath} -> {$targetPath}");
                $this->copyDirectory($sourcePath, $targetPath);
            } else {
                $this->copyFile($sourcePath, $targetPath);
                $fileCount++;
            }
        }

        if ($fileCount > 0) {
            logger()->info("[插件安装] 目录复制完成，共复制 {$fileCount} 个文件到: {$target}");
        }
    }

    /**
     * 复制文件
     *
     * @param string $source 源文件
     * @param string $target 目标文件
     */
    private function copyFile(string $source, string $target): void
    {
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
            logger()->info("[插件安装] 创建目标目录: {$targetDir}");
        }

        if (copy($source, $target)) {
            $fileSize = filesize($source);
            logger()->info("[插件安装] 文件复制成功: {$source} -> {$target} ({$fileSize} bytes)");
        } else {
            logger()->warning("[插件安装] 文件复制失败: {$source} -> {$target}");
        }
    }

    /**
     * 移动目录
     *
     * @param string $source 源目录
     * @param string $target 目标目录
     * @return bool
     */
    private function moveDirectory(string $source, string $target): bool
    {
        if (!is_dir($source)) {
            logger()->warning("[插件操作] 源目录不存在: {$source}");
            return false;
        }

        // 创建目标目录（如果不存在）
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
            logger()->info("[插件操作] 创建目标目录: {$targetDir}");
        }

        // 使用 PHP 的 rename 函数移动目录
        if (rename($source, $target)) {
            logger()->info("[插件操作] 目录移动成功: {$source} -> {$target}");
            return true;
        } else {
            logger()->error("[插件操作] 目录移动失败: {$source} -> {$target}");
            return false;
        }
    }

    /**
     * 移动文件
     *
     * @param string $source 源文件
     * @param string $target 目标文件
     * @return bool
     */
    private function moveFile(string $source, string $target): bool
    {
        if (!file_exists($source)) {
            logger()->warning("[插件操作] 源文件不存在: {$source}");
            return false;
        }

        // 创建目标目录（如果不存在）
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
            logger()->info("[插件操作] 创建目标目录: {$targetDir}");
        }

        // 使用 PHP 的 rename 函数移动文件
        if (rename($source, $target)) {
            $fileSize = filesize($target);
            logger()->info("[插件操作] 文件移动成功: {$source} -> {$target} ({$fileSize} bytes)");
            return true;
        } else {
            logger()->error("[插件操作] 文件移动失败: {$source} -> {$target}");
            return false;
        }
    }

    /**
     * 安装插件菜单和权限
     *
     * @param string $addonName 插件名称
     * @return bool
     */
    private function installAddonMenusAndPermissions(string $addonName): bool
    {
        $addonDir = $this->addonPath . '/' . $addonName;
        $configFile = $addonDir . '/Manager/menus_permissions.json';

        if (!file_exists($configFile)) {
            logger()->info("[插件安装] 插件 {$addonName} 没有菜单权限配置文件，跳过安装");
            return true;
        }

        try {
            $configContent = file_get_contents($configFile);
            $config = json_decode($configContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                logger()->error("[插件安装] 插件 {$addonName} 的菜单权限配置文件JSON格式错误: " . json_last_error_msg());
                return false;
            }
            if (!is_array($config)) {
                logger()->error("[插件安装] 插件 {$addonName} 的菜单权限配置文件格式错误");
                return false;
            }

            // 安装权限
            if (isset($config['permissions']) && is_array($config['permissions'])) {
                $this->installAddonPermissions($addonName, $config['permissions']);
            }

            // 安装菜单
            if (isset($config['menus']) && is_array($config['menus'])) {
                $this->installAddonMenus($addonName, $config['menus']);
            }

            logger()->info("[插件安装] 插件 {$addonName} 菜单和权限安装完成");
            return true;
        } catch (\Throwable $e) {
            logger()->error("[插件安装] 插件 {$addonName} 菜单和权限安装失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 禁用插件菜单和权限
     *
     * @param string $addonName 插件名称
     * @return bool
     */
    private function disableAddonMenusAndPermissions(string $addonName): bool
    {
        $addonDir = $this->addonPath . '/' . $addonName;
        $configFile = $addonDir . '/Manager/menus_permissions.json';

        if (!file_exists($configFile)) {
            logger()->info("[插件禁用] 插件 {$addonName} 没有菜单权限配置文件，跳过禁用");
            return true;
        }

        try {
            $configContent = file_get_contents($configFile);
            $config = json_decode($configContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                logger()->error("[插件禁用] 插件 {$addonName} 的菜单权限配置文件JSON格式错误: " . json_last_error_msg());
                return false;
            }
            if (!is_array($config)) {
                logger()->error("[插件禁用] 插件 {$addonName} 的菜单权限配置文件格式错误");
                return false;
            }

            // 删除权限
            if (isset($config['permissions']) && is_array($config['permissions'])) {
                $this->deleteAddonPermissions($addonName, $config['permissions']);
            }

            // 删除菜单
            if (isset($config['menus']) && is_array($config['menus'])) {
                $this->deleteAddonMenus($addonName, $config['menus']);
            }

            logger()->info("[插件禁用] 插件 {$addonName} 菜单和权限删除完成");
            return true;
        } catch (\Throwable $e) {
            logger()->error("[插件禁用] 插件 {$addonName} 菜单和权限删除失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除插件权限
     *
     * @param string $addonName 插件名称
     * @param array $permissions 权限配置
     */
    private function deleteAddonPermissions(string $addonName, array $permissions): void
    {
        logger()->info("[插件禁用] 开始删除权限，插件: {$addonName}");

        $this->deletePermissionTree($permissions, $addonName);

        logger()->info("[插件禁用] 权限删除完成");
    }

    /**
     * 递归删除权限树
     *
     * @param array $permissions 权限配置数组
     * @param string $addonName 插件名称
     */
    private function deletePermissionTree(array $permissions, string $addonName): void
    {
        foreach ($permissions as $permission) {
            // 递归删除子权限（先删除子权限）
            if (isset($permission['children']) && is_array($permission['children'])) {
                $this->deletePermissionTree($permission['children'], $addonName);
            }

            // 删除当前权限
            $this->deletePermission($permission['slug'], $addonName);
            logger()->info("[插件禁用] 删除权限: {$permission['name']} ({$permission['slug']})");
        }
    }

    /**
     * 删除插件菜单
     *
     * @param string $addonName 插件名称
     * @param array $menus 菜单配置
     */
    private function deleteAddonMenus(string $addonName, array $menus): void
    {
        logger()->info("[插件禁用] 开始删除菜单，插件: {$addonName}");

        $this->deleteMenuTree($menus, $addonName);

        logger()->info("[插件禁用] 菜单删除完成");
    }

    /**
     * 递归删除菜单树
     *
     * @param array $menus 菜单配置数组
     * @param string $addonName 插件名称
     */
    private function deleteMenuTree(array $menus, string $addonName): void
    {
        foreach ($menus as $menu) {
            // 删除当前菜单
            $this->deleteMenu($menu['name'], $addonName);
            logger()->info("[插件禁用] 删除菜单: {$menu['title']} ({$menu['name']})");
        }
    }

    /**
     * 删除权限
     *
     * @param string $slug 权限标识
     * @param string $addonName 插件名称
     */
    private function deletePermission(string $slug, string $addonName): void
    {
        try {
            $deleted = AdminPermission::query()
                ->where('slug', $slug)
                ->delete();

            if ($deleted > 0) {
                logger()->info("[插件操作] 权限删除成功: {$slug}");
            } else {
                logger()->warning("[插件操作] 权限删除失败，未找到权限: {$slug}");
            }
        } catch (\Throwable $e) {
            logger()->error("[插件操作] 权限删除异常: {$slug} - " . $e->getMessage());
        }
    }

    /**
     * 更新权限状态
     *
     * @param string $slug 权限标识
     * @param int $status 状态
     * @param string $addonName 插件名称
     */
    private function updatePermissionStatus(string $slug, int $status, string $addonName): void
    {
        try {
            $updated = AdminPermission::query()
                ->where('slug', $slug)
                ->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                $statusText = $status === 1 ? '启用' : '禁用';
                logger()->info("[插件操作] 权限状态更新成功: {$slug} -> {$statusText}");
            } else {
                logger()->warning("[插件操作] 权限状态更新失败，未找到权限: {$slug}");
            }
        } catch (\Throwable $e) {
            logger()->error("[插件操作] 权限状态更新异常: {$slug} - " . $e->getMessage());
        }
    }

    /**
     * 删除菜单
     *
     * @param string $name 菜单名称
     * @param string $addonName 插件名称
     */
    private function deleteMenu(string $name, string $addonName): void
    {
        try {
            $deleted = AdminMenu::query()
                ->where('name', $name)
                ->delete();

            if ($deleted > 0) {
                logger()->info("[插件操作] 菜单删除成功: {$name}");
            } else {
                logger()->warning("[插件操作] 菜单删除失败，未找到菜单: {$name}");
            }
        } catch (\Throwable $e) {
            logger()->error("[插件操作] 菜单删除异常: {$name} - " . $e->getMessage());
        }
    }

    /**
     * 更新菜单状态
     *
     * @param string $name 菜单名称
     * @param int $status 状态
     * @param string $addonName 插件名称
     */
    private function updateMenuStatus(string $name, int $status, string $addonName): void
    {
        try {
            $updated = AdminMenu::query()
                ->where('name', $name)
                ->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                $statusText = $status === 1 ? '启用' : '禁用';
                logger()->info("[插件操作] 菜单状态更新成功: {$name} -> {$statusText}");
            } else {
                logger()->warning("[插件操作] 菜单状态更新失败，未找到菜单: {$name}");
            }
        } catch (\Throwable $e) {
            logger()->error("[插件操作] 菜单状态更新异常: {$name} - " . $e->getMessage());
        }
    }

    /**
     * 安装插件权限
     *
     * @param string $addonName 插件名称
     * @param array $permissions 权限配置
     */
    private function installAddonPermissions(string $addonName, array $permissions): void
    {
        logger()->info("[插件安装] 开始安装权限，插件: {$addonName}");

        $this->installPermissionTree($permissions, $addonName);

        logger()->info("[插件安装] 权限安装完成");
    }

    /**
     * 递归安装权限树
     *
     * @param array $permissions 权限配置数组
     * @param string $addonName 插件名称
     * @param int $parentId 父级权限ID
     */
    private function installPermissionTree(array $permissions, string $addonName, int $parentId = 0): void
    {
        foreach ($permissions as $permission) {
            // 检查权限是否已存在
            if ($this->permissionExists($permission['slug'])) {
                logger()->info("[插件安装] 权限已存在，跳过: {$permission['name']} ({$permission['slug']})");
                // 即使权限存在，也要处理子权限
                if (isset($permission['children']) && is_array($permission['children'])) {
                    $existingPermissionId = $this->getPermissionIdBySlug($permission['slug']);
                    if ($existingPermissionId) {
                        $this->installPermissionTree($permission['children'], $addonName, $existingPermissionId);
                    }
                }
                continue;
            }

            // 准备权限数据
            $permissionData = $permission;
            $permissionData['parent_id'] = $parentId;

            // 创建权限
            $permissionId = $this->createPermission($permissionData, $addonName);
            logger()->info("[插件安装] 成功创建权限: {$permission['name']} ({$permission['slug']}) - ID: {$permissionId}");

            // 递归处理子权限
            if (isset($permission['children']) && is_array($permission['children'])) {
                $this->installPermissionTree($permission['children'], $addonName, $permissionId);
            }
        }
    }

    /**
     * 安装插件菜单
     *
     * @param string $addonName 插件名称
     * @param array $menus 菜单配置
     */
    private function installAddonMenus(string $addonName, array $menus): void
    {
        logger()->info("[插件安装] 开始安装菜单，插件: {$addonName}");

        $this->installMenuTree($menus, $addonName);

        logger()->info("[插件安装] 菜单安装完成");
    }

    /**
     * 递归安装菜单树
     *
     * @param array $menus 菜单配置数组
     * @param string $addonName 插件名称
     * @param int $parentId 父级菜单ID
     */
    private function installMenuTree(array $menus, string $addonName, int $parentId = 0): void
    {
        foreach ($menus as $menu) {
            // 检查菜单是否已存在
            if ($this->menuExists($menu['name'])) {
                logger()->info("[插件安装] 菜单已存在，检查parent_id: {$menu['title']} ({$menu['name']})");

                // 获取现有菜单
                $existingMenuId = $this->getMenuIdByName($menu['name']);
                $existingMenu = AdminMenu::find($existingMenuId);

                // 检查parent_id是否正确，如果不正确则更新
                if ($existingMenu && $existingMenu->parent_id !== $parentId) {
                    $existingMenu->update(['parent_id' => $parentId]);
                    logger()->info("[插件安装] 更新菜单parent_id: {$menu['title']} (ID: {$existingMenuId}) parent_id: {$existingMenu->parent_id} -> {$parentId}");
                }

                // 如果菜单已存在，递归处理子菜单（获取现有菜单ID作为父ID）
                if ($existingMenuId && isset($menu['children']) && is_array($menu['children'])) {
                    logger()->info("[插件安装] 处理已存在菜单的子菜单: {$menu['title']}");
                    $this->installMenuTree($menu['children'], $addonName, $existingMenuId);
                }
                continue;
            }

            // 处理菜单数据
            $menuData = $menu;
            $menuData['parent_id'] = $parentId;

            // 移除children字段，避免保存到数据库
            $children = $menuData['children'] ?? [];
            unset($menuData['children']);

            // 设置默认值
            $menuData['site_id'] = $menuData['site_id'] ?? 0; // 默认全局站点

            // 创建菜单
            $menuId = $this->createMenu($menuData, $addonName);
            logger()->info("[插件安装] 成功创建菜单: {$menu['title']} ({$menu['name']}) - ID: {$menuId}, 路径: {$menu['path']}");

            // 递归处理子菜单
            if (!empty($children)) {
                logger()->info("[插件安装] 处理子菜单: {$menu['title']} -> " . count($children) . " 个子菜单");
                $this->installMenuTree($children, $addonName, $menuId);
            }
        }
    }

    /**
     * 检查权限是否存在
     *
     * @param string $slug 权限标识
     * @return bool
     */
    private function permissionExists(string $slug): bool
    {
        try {
            return AdminPermission::query()
                ->where('slug', $slug)
                ->exists();
        } catch (\Throwable $e) {
            logger()->error("[插件检查] 权限存在性检查失败: {$slug} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * 检查菜单是否存在
     *
     * @param string $name 菜单名称
     * @return bool
     */
    private function menuExists(string $name): bool
    {
        try {
            return AdminMenu::query()
                ->where('name', $name)
                ->exists();
        } catch (\Throwable $e) {
            logger()->error("[插件检查] 菜单存在性检查失败: {$name} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据slug获取权限ID
     *
     * @param string $slug 权限标识
     * @return int|null
     */
    private function getPermissionIdBySlug(string $slug): ?int
    {
        try {
            $permission = AdminPermission::query()
                ->where('slug', $slug)
                ->first();

            return $permission?->id;
        } catch (\Throwable $e) {
            logger()->error("[插件查询] 权限ID查询失败: {$slug} - " . $e->getMessage());
            return null;
        }
    }

    /**
     * 根据name获取菜单ID
     *
     * @param string $name 菜单名称
     * @return int|null
     */
    private function getMenuIdByName(string $name): ?int
    {
        try {
            $menu = AdminMenu::query()
                ->where('name', $name)
                ->first();

            return $menu ? $menu->id : null;
        } catch (\Throwable $e) {
            logger()->error("[插件查询] 菜单ID查询失败: {$name} - " . $e->getMessage());
            return null;
        }
    }

    /**
     * 创建权限
     *
     * @param array $permissionData 权限数据
     * @param string $addonName 插件名称
     * @return int
     */
    private function createPermission(array $permissionData, string $addonName): int
    {
        try {
            // 准备权限数据 - 只设置JSON中提供的字段，让数据库使用默认值
            $permissionAttributes = [
                'site_id' => $permissionData['site_id'] ?? 0, // 默认全局站点
                'parent_id' => $permissionData['parent_id'] ?? 0,
                'name' => $permissionData['name'] ?? '',
                'slug' => $permissionData['slug'] ?? '',
                'type' => $permissionData['type'] ?? 'menu',
                'path' => $permissionData['path'] ?? '',
                'description' => $permissionData['description'] ?? null,
                'status' => $permissionData['status'] ?? 1,
                'sort' => $permissionData['sort'] ?? 0,
            ];

            // 可选字段 - 只有在JSON中明确指定时才设置
            if (isset($permissionData['icon'])) {
                $permissionAttributes['icon'] = $permissionData['icon'];
            }
            if (isset($permissionData['method'])) {
                $permissionAttributes['method'] = $permissionData['method'];
            }
            if (isset($permissionData['component'])) {
                $permissionAttributes['component'] = $permissionData['component'];
            }

            // 检查权限是否已存在
            $existingPermission = AdminPermission::query()
                ->where('slug', $permissionAttributes['slug'])
                ->first();

            if ($existingPermission) {
                logger()->info("[插件安装] 权限已存在，跳过创建: {$permissionAttributes['name']} (ID: {$existingPermission->id})");
                return $existingPermission->id;
            }

            // 创建权限
            $permission = AdminPermission::query()->create($permissionAttributes);

            logger()->info("[插件安装] 权限创建成功: {$permissionAttributes['name']} (ID: {$permission->id})");
            return $permission->id;

        } catch (\Throwable $e) {
            logger()->error("[插件安装] 权限创建失败: {$permissionData['name']} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 创建菜单
     *
     * @param array $menuData 菜单数据
     * @param string $addonName 插件名称
     * @return int
     */
    private function createMenu(array $menuData, string $addonName): int
    {
        try {
            // 准备菜单数据
            $menuAttributes = [
                'site_id' => $menuData['site_id'] ?? 0, // 默认全局站点
                'parent_id' => $menuData['parent_id'] ?? 0,
                'name' => $menuData['name'] ?? '',
                'title' => $menuData['title'] ?? $menuData['name'] ?? '',
                'icon' => $menuData['icon'] ?? null,
                'path' => $menuData['path'] ?? '',
                'component' => $menuData['component'] ?? null,
                'redirect' => $menuData['redirect'] ?? null,
                'type' => $menuData['type'] ?? AdminMenu::TYPE_MENU,
                'target' => $menuData['target'] ?? AdminMenu::TARGET_SELF,
                'badge' => $menuData['badge'] ?? null,
                'badge_type' => $menuData['badge_type'] ?? null,
                'permission' => $menuData['permission'] ?? null,
                'visible' => $menuData['visible'] ?? 1,
                'status' => $menuData['status'] ?? 1,
                'sort' => $menuData['sort'] ?? 0,
                'cache' => $menuData['cache'] ?? 1,
                'config' => $menuData['config'] ?? null,
                'remark' => $menuData['remark'] ?? null,
            ];

            // 如果设置了site_id为0，确保不重复创建全局菜单
            if ($menuAttributes['site_id'] === 0) {
                $existingMenu = AdminMenu::query()
                    ->where('site_id', 0)
                    ->where('path', $menuAttributes['path'])
                    ->first();

                if ($existingMenu) {
                    logger()->info("[插件安装] 全局菜单已存在，跳过创建: {$menuAttributes['title']} (ID: {$existingMenu->id})");
                    return $existingMenu->id;
                }
            }

            // 创建菜单
            $menu = AdminMenu::query()->create($menuAttributes);

            logger()->info("[插件安装] 菜单创建成功: {$menuAttributes['title']} (ID: {$menu->id})");
            return $menu->id;

        } catch (\Throwable $e) {
            logger()->error("[插件安装] 菜单创建失败: {$menuData['title']} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取插件配置
     *
     * @param string $addonName 插件名称
     * @return array
     */
    public function getAddonConfig(string $addonName): array
    {
        $configFile = $this->addonPath . '/' . $addonName . '/config.php';

        if (!file_exists($configFile)) {
            return [];
        }

        try {
            $config = include $configFile;
            return is_array($config) ? $config : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 设置插件配置
     *
     * @param string $addonName 插件名称
     * @param array $config 配置数据
     * @param bool $preserveFormat 是否保持原有格式
     * @return bool
     */
    public function setAddonConfig(string $addonName, array $config, bool $preserveFormat = true): bool
    {
        logger()->info("[插件配置] 开始设置插件配置", [
            'addonName' => $addonName,
            'config_keys' => array_keys($config),
            'preserveFormat' => $preserveFormat
        ]);

        try {
            $configFile = $this->addonPath . '/' . $addonName . '/config.php';
            $configDir = dirname($configFile);

            if (!is_dir($configDir)) {
                logger()->error("[插件配置] 配置目录不存在", [
                    'addonName' => $addonName,
                    'configDir' => $configDir
                ]);
                return false;
            }

            // 始终使用重新生成模式，因为我们现在有更好的生成逻辑
            logger()->info("[插件配置] 使用重新生成模式", [
                'addonName' => $addonName,
                'configFile' => $configFile,
                'configFile_exists' => file_exists($configFile)
            ]);

            if (file_exists($configFile)) {
                // 如果原文件存在，使用我们新的生成逻辑
                $content = $this->generateConfigFile($configFile, $config);
            } else {
                // 如果是新文件，直接生成
                $content = "<?php\n\nreturn " . $this->arrayToPhpCode($config) . ";\n";
            }

            logger()->info("[插件配置] 写入配置文件", [
                'addonName' => $addonName,
                'configFile' => $configFile,
                'content_length' => strlen($content)
            ]);

            $writeResult = file_put_contents($configFile, $content);
            logger()->info("[插件配置] 配置文件写入结果", [
                'addonName' => $addonName,
                'writeResult' => $writeResult,
                'writeSuccess' => $writeResult !== false
            ]);

            return $writeResult !== false;
        } catch (\Throwable $e) {
            logger()->error("[插件配置] 设置配置异常", [
                'addonName' => $addonName,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }


    /**
     * 将值转换为PHP代码格式
     *
     * @param mixed $value
     * @return string
     */
    private function valueToPhpCode($value): string
    {
        if (is_array($value)) {
            return $this->arrayToPhpCode($value, 0);
        } elseif (is_string($value)) {
            return var_export($value, true);
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } elseif (is_numeric($value)) {
            return $value;
        } else {
            return var_export($value, true);
        }
    }

    /**
     * 将数组转换为PHP代码
     *
     * @param array $array
     * @param int $indent
     * @return string
     */
    private function arrayToPhpCode(array $array, int $indent = 0): string
    {
        $code = "[\n";
        $indentStr = str_repeat('    ', $indent + 1);

        foreach ($array as $key => $value) {
            $code .= $indentStr;

            // 键处理
            if (is_string($key)) {
                $code .= var_export($key, true) . " => ";
            } else {
                $code .= "{$key} => ";
            }

            // 值处理
            if (is_array($value)) {
                $code .= $this->arrayToPhpCode($value, $indent + 1);
            } elseif (is_string($value)) {
                // 使用 var_export 来正确处理字符串转义
                $code .= var_export($value, true);
            } elseif (is_bool($value)) {
                $code .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $code .= 'null';
            } elseif (is_numeric($value)) {
                // 数字类型保持原样
                $code .= $value;
            } else {
                // 其他类型使用 var_export
                $code .= var_export($value, true);
            }

            $code .= ",\n";
        }

        $code .= str_repeat('    ', $indent) . "]";
        return $code;
    }


    /**
     * 部署插件资源文件（目录和文件移动）
     *
     * @param string $addonName 插件目录名
     * @return bool
     */
    private function deployAddonAssets(string $addonName): bool
    {
        $addonDir = $this->addonPath . '/' . $addonName;
        $assetsFile = $addonDir . '/Manager/assets.json';

        if (!file_exists($assetsFile)) {
            logger()->info("[插件资源部署] 插件 {$addonName} 没有资产配置文件，跳过资源部署");
            return true;
        }

        try {
            $assetsConfig = json_decode(file_get_contents($assetsFile), true);
            if (!$assetsConfig) {
                logger()->error("[插件资源部署] 插件 {$addonName} 的资产配置文件格式错误");
                return false;
            }

            // 处理目录移动
            if (isset($assetsConfig['directories'])) {
                $dirCount = count($assetsConfig['directories']);
                logger()->info("[插件资源部署] 插件 {$addonName} 开始移动目录，共 {$dirCount} 个目录");

                foreach ($assetsConfig['directories'] as $source => $target) {
                    $sourcePath = $addonDir . '/' . $source;
                    $targetPath = BASE_PATH . '/' . $target;

                    if (is_dir($sourcePath)) {
                        logger()->info("[插件资源部署] 移动目录: {$sourcePath} -> {$targetPath}");
                        $this->moveDirectory($sourcePath, $targetPath);
                    } else {
                        logger()->warning("[插件资源部署] 源目录不存在: {$sourcePath}");
                    }
                }
            }

            // 处理文件移动
            if (isset($assetsConfig['files'])) {
                $fileCount = count($assetsConfig['files']);
                logger()->info("[插件资源部署] 插件 {$addonName} 开始移动文件，共 {$fileCount} 个文件");

                foreach ($assetsConfig['files'] as $source => $target) {
                    $sourcePath = $addonDir . '/' . $source;
                    $targetPath = BASE_PATH . '/' . $target;

                    if (file_exists($sourcePath)) {
                        $fileSize = filesize($sourcePath);
                        logger()->info("[插件资源部署] 移动文件: {$sourcePath} -> {$targetPath} ({$fileSize} bytes)");
                        $this->moveFile($sourcePath, $targetPath);
                    } else {
                        logger()->warning("[插件资源部署] 源文件不存在: {$sourcePath}");
                    }
                }
            }

            logger()->info("[插件资源部署] 插件 {$addonName} 资源部署完成");
            return true;

        } catch (\Throwable $e) {
            logger()->error("[插件资源部署] 插件 {$addonName} 资源部署失败: " . $e->getMessage());
            return false;
        }
    }
}
