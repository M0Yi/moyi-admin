<?php

declare(strict_types=1);

namespace App\Service\Admin\Addons;

use Throwable;

/**
 * 插件配置服务
 *
 * 负责插件配置文件的读取、写入和管理
 */
class AddonsConfigService
{
    /**
     * 获取插件配置.
     *
     * @param string $addonName 插件名称
     * @return array 配置数据
     */
    public function getAddonConfig(string $addonName): array
    {
        $configFile = BASE_PATH . '/addons/' . $addonName . '/config.php';

        if (! file_exists($configFile)) {
            return [];
        }

        try {
            $config = include $configFile;
            return is_array($config) ? $config : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * 设置插件配置.
     *
     * @param string $addonName 插件名称
     * @param array $config 配置数据
     * @param bool $preserveFormat 是否保持原有格式
     * @return bool 是否成功
     */
    public function setAddonConfig(string $addonName, array $config, bool $preserveFormat = true): bool
    {
        logger()->info('[插件配置] 开始设置插件配置', [
            'addonName' => $addonName,
            'config_keys' => array_keys($config),
            'preserveFormat' => $preserveFormat,
        ]);

        try {
            $configFile = BASE_PATH . '/addons/' . $addonName . '/config.php';
            $configDir = dirname($configFile);

            if (! is_dir($configDir)) {
                logger()->error('[插件配置] 配置目录不存在', [
                    'addonName' => $addonName,
                    'configDir' => $configDir,
                ]);
                return false;
            }

            // 始终使用重新生成模式，因为我们现在有更好的生成逻辑
            logger()->info('[插件配置] 使用重新生成模式', [
                'addonName' => $addonName,
                'configFile' => $configFile,
                'configFile_exists' => file_exists($configFile),
            ]);

            if (file_exists($configFile)) {
                // 如果原文件存在，使用我们新的生成逻辑
                $content = $this->generateConfigFile($configFile, $config);
            } else {
                // 如果是新文件，直接生成
                $content = "<?php\n\nreturn " . $this->arrayToPhpCode($config) . ";\n";
            }

            logger()->info('[插件配置] 写入配置文件', [
                'addonName' => $addonName,
                'configFile' => $configFile,
                'content_length' => strlen($content),
            ]);

            $writeResult = file_put_contents($configFile, $content);
            logger()->info('[插件配置] 配置文件写入结果', [
                'addonName' => $addonName,
                'writeResult' => $writeResult,
                'writeSuccess' => $writeResult !== false,
            ]);

            return $writeResult !== false;
        } catch (Throwable $e) {
            logger()->error('[插件配置] 设置配置异常', [
                'addonName' => $addonName,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * 保存插件配置.
     *
     * @param string $addonId 插件ID
     * @param array $configData 配置数据
     * @return bool 是否成功
     */
    public function saveAddonConfig(string $addonId, array $configData): bool
    {
        logger()->info('[插件配置] 开始处理配置保存', [
            'addonId' => $addonId,
            'configData_keys' => array_keys($configData),
            'configData_count' => count($configData),
        ]);

        // 获取正确的插件目录名（将ID转换为目录名）
        $addonDirName = $this->convertIdToDirName($addonId);
        $addonDir = BASE_PATH . '/addons/' . $addonDirName;
        $configFile = $addonDir . '/config.php';

        if (! file_exists($configFile)) {
            logger()->error('[插件配置] 配置文件不存在', [
                'addonId' => $addonId,
                'addonDirName' => $addonDirName,
                'configFile' => $configFile,
            ]);
            return false;
        }

        try {
            // 采用更优雅的配置保存方案：重新生成配置文件
            logger()->info('[插件配置] 采用重新生成方案保存配置', ['addonId' => $addonId, 'addonDirName' => $addonDirName]);

            // 读取并解析原始配置文件
            $originalConfig = $this->parseConfigFile($configFile);
            if (! is_array($originalConfig)) {
                logger()->warning('[插件配置] 原始配置解析失败，使用默认配置', [
                    'addonId' => $addonId,
                    'addonDirName' => $addonDirName,
                    'originalConfig_type' => gettype($originalConfig),
                ]);
                $originalConfig = ['enabled' => true, 'configs' => []];
            }

            // 更新配置项的值
            $updatedConfig = $this->updateConfigValues($originalConfig, $configData, $addonId);

            // 重新生成配置文件
            $newContent = $this->generateConfigFile($configFile, $updatedConfig);

            // 备份原文件（可选）
            $backupFile = $configFile . '.backup.' . date('YmdHis');
            if (copy($configFile, $backupFile)) {
                logger()->info('[插件配置] 原配置文件已备份', [
                    'addonId' => $addonId,
                    'addonDirName' => $addonDirName,
                    'backupFile' => $backupFile,
                ]);
            }

            // 写入新配置
            $writeResult = file_put_contents($configFile, $newContent);
            if ($writeResult === false) {
                logger()->error('[插件配置] 配置文件写入失败', [
                    'addonId' => $addonId,
                    'addonDirName' => $addonDirName,
                    'configFile' => $configFile,
                ]);
                return false;
            }

            logger()->info('[插件配置] 配置保存成功', [
                'addonId' => $addonId,
                'addonDirName' => $addonDirName,
                'configFile' => $configFile,
                'written_bytes' => $writeResult,
            ]);

            return true;
        } catch (Throwable $e) {
            logger()->error('[插件配置] 保存配置异常', [
                'addonId' => $addonId,
                'addonDirName' => $addonDirName,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * 更新插件配置中的单个值
     *
     * @param string $addonName 插件名称
     * @param string $key 配置键（支持点号分隔的嵌套键，如 'test.debug_mode'）
     * @param mixed $value 配置值
     * @return bool 是否成功
     */
    public function updateAddonConfigValue(string $addonName, string $key, $value): bool
    {
        try {
            $configFile = BASE_PATH . '/addons/' . $addonName . '/config.php';

            if (! file_exists($configFile)) {
                return false;
            }

            $content = file_get_contents($configFile);

            // 处理嵌套键
            $keys = explode('.', $key);
            $configKey = array_shift($keys);

            if (empty($keys)) {
                // 单层键值更新
                return $this->updateSimpleConfigValue($content, $configKey, $value, $configFile);
            }
            // 嵌套键值更新（暂时不支持，未来可以扩展）
            return false;
        } catch (Throwable $e) {
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
     * @return bool 是否成功
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
        $pattern = "/(['\"]?{$key}['\"]?\\s*=>\\s*)(true|false|null|'[^']*'|[^,\n]+)(,?\\s*(?:\\/\\/.*)?)/m";
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
        logger()->error('[插件配置] 更新配置失败', [
            'key' => $key,
            'value' => $value,
            'configFile' => $configFile,
            'content_sample' => substr($content, 0, 200),
        ]);

        return false;
    }

    /**
     * 解析配置文件内容.
     *
     * @param string $configFile 配置文件路径
     * @return array|null 配置数组
     */
    private function parseConfigFile(string $configFile): ?array
    {
        if (! file_exists($configFile)) {
            return null;
        }

        // 安全地包含配置文件
        $config = include $configFile;

        return is_array($config) ? $config : null;
    }

    /**
     * 更新配置项的值
     *
     * @param array $originalConfig 原始配置
     * @param array $newData 新数据
     * @param string $addonId 插件ID
     * @return array 更新后的配置
     */
    private function updateConfigValues(array $originalConfig, array $newData, string $addonId): array
    {
        $updatedConfig = $originalConfig;
        $updatedFields = [];

        // 更新configs数组中的值
        if (isset($updatedConfig['configs']) && is_array($updatedConfig['configs'])) {
            foreach ($updatedConfig['configs'] as &$configItem) {
                if (! isset($configItem['name'])) {
                    continue;
                }

                $fieldName = $configItem['name'];
                if (isset($newData[$fieldName])) {
                    $oldValue = $configItem['value'] ?? null;
                    $fieldType = $configItem['type'] ?? 'text';

                    // 类型转换
                    $newValue = $this->convertConfigValue($newData[$fieldName], $fieldType);

                    logger()->info('[插件配置] 更新配置字段', [
                        'addonId' => $addonId,
                        'fieldName' => $fieldName,
                        'fieldType' => $fieldType,
                        'oldValue' => $oldValue,
                        'newValue' => $newValue,
                        'rawInput' => $newData[$fieldName],
                    ]);

                    $configItem['value'] = $newValue;

                    // 同时更新一级数组中的对应值
                    $updatedConfig[$fieldName] = $newValue;

                    $updatedFields[] = $fieldName;
                }
            }
        }

        logger()->info('[插件配置] 配置值更新完成', [
            'addonId' => $addonId,
            'updatedFields' => $updatedFields,
            'updatedCount' => count($updatedFields),
        ]);

        return $updatedConfig;
    }

    /**
     * 生成新的配置文件内容.
     *
     * @param string $originalFile 原始文件路径
     * @param array $config 配置数组
     * @return string 生成的文件内容
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
        return implode("\n", $headerLines) . "\n\nreturn " . $configCode . ";\n";
    }

    /**
     * 转换配置值类型.
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
            case 'tinymce':
                // TinyMCE 富文本内容，直接返回值（不过滤HTML）
                return $value;
            default:
                return $value;
        }
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
     * 将数组转换为PHP代码.
     *
     * @param array $array 数组
     * @param int $indent 缩进级别
     * @return string PHP代码
     */
    private function arrayToPhpCode(array $array, int $indent = 0): string
    {
        $indentStr = str_repeat('    ', $indent);
        $lines = [];

        $lines[] = '[';

        foreach ($array as $key => $value) {
            $keyStr = is_string($key) ? "'{$key}'" : $key;

            if (is_array($value)) {
                $valueStr = $this->arrayToPhpCode($value, $indent + 1);
            } elseif (is_string($value)) {
                $valueStr = "'{$value}'";
            } elseif (is_bool($value)) {
                $valueStr = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $valueStr = 'null';
            } else {
                $valueStr = (string) $value;
            }

            $lines[] = "{$indentStr}    {$keyStr} => {$valueStr},";
        }

        $lines[] = "{$indentStr}]";

        return implode("\n", $lines);
    }
}
