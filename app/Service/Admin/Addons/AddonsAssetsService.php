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

namespace App\Service\Admin\Addons;

/**
 * 插件资源管理服务
 *
 * 负责插件的资源文件部署、卸载、文件和目录操作等功能
 */
class AddonsAssetsService
{
    /**
     * 插件目录路径.
     */
    private string $addonPath;

    public function __construct()
    {
        $this->addonPath = BASE_PATH . '/addons';
    }

    /**
     * 部署插件资源文件.
     *
     * @param string $addonName 插件目录名
     */
    public function deployAddonAssets(string $addonName): bool
    {
        $addonDir = $this->addonPath . '/' . $addonName;
        $assetsFile = $addonDir . '/Manager/assets.json';

        if (! file_exists($assetsFile)) {
            logger()->info("[插件资源部署] 插件 {$addonName} 没有资产配置文件，跳过资源部署");
            return true;
        }

        try {
            $assetsConfig = json_decode(file_get_contents($assetsFile), true);
            if (! $assetsConfig) {
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

    /**
     * 卸载插件资源文件（保留数据表）.
     *
     * 清理插件的文件和目录资源，但保留插件创建的数据表和数据
     *
     * @param string $addonName 插件名称
     */
    public function uninstallAddonAssets(string $addonName): bool
    {
        $addonDir = $this->addonPath . '/' . $addonName;
        $assetsFile = $addonDir . '/Manager/assets.json';

        logger()->info("[插件卸载] 开始卸载插件资源: {$addonName}");

        // 插件卸载时保留数据表，只清理其他资源
        logger()->info("[插件卸载] 插件 {$addonName} 卸载时保留数据表，跳过数据库清理");

        if (! file_exists($assetsFile)) {
            logger()->info("[插件卸载] 插件 {$addonName} 没有资产配置文件，跳过资源清理");
            return true;
        }

        try {
            $assetsConfig = json_decode(file_get_contents($assetsFile), true);
            if (! $assetsConfig) {
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
                        if (! is_dir($sourceDir)) {
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
                uksort($directories, function ($a, $b) {
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
     * 复制目录.
     *
     * @param string $source 源目录
     * @param string $target 目标目录
     */
    public function copyDirectory(string $source, string $target): void
    {
        if (! is_dir($target)) {
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
                ++$fileCount;
            }
        }

        if ($fileCount > 0) {
            logger()->info("[插件安装] 目录复制完成，共复制 {$fileCount} 个文件到: {$target}");
        }
    }

    /**
     * 复制文件.
     *
     * @param string $source 源文件
     * @param string $target 目标文件
     */
    public function copyFile(string $source, string $target): void
    {
        $targetDir = dirname($target);
        if (! is_dir($targetDir)) {
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
     * 移动目录.
     *
     * @param string $source 源目录
     * @param string $target 目标目录
     */
    public function moveDirectory(string $source, string $target): bool
    {
        if (! is_dir($source)) {
            logger()->warning("[插件操作] 源目录不存在: {$source}");
            return false;
        }

        // 创建目标目录（如果不存在）
        $targetDir = dirname($target);
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
            logger()->info("[插件操作] 创建目标目录: {$targetDir}");
        }

        // 使用 PHP 的 rename 函数移动目录
        if (rename($source, $target)) {
            logger()->info("[插件操作] 目录移动成功: {$source} -> {$target}");
            return true;
        }
        logger()->error("[插件操作] 目录移动失败: {$source} -> {$target}");
        return false;
    }

    /**
     * 移动文件.
     *
     * @param string $source 源文件
     * @param string $target 目标文件
     */
    public function moveFile(string $source, string $target): bool
    {
        if (! file_exists($source)) {
            logger()->warning("[插件操作] 源文件不存在: {$source}");
            return false;
        }

        // 创建目标目录（如果不存在）
        $targetDir = dirname($target);
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
            logger()->info("[插件操作] 创建目标目录: {$targetDir}");
        }

        // 使用 PHP 的 rename 函数移动文件
        if (rename($source, $target)) {
            $fileSize = filesize($target);
            logger()->info("[插件操作] 文件移动成功: {$source} -> {$target} ({$fileSize} bytes)");
            return true;
        }
        logger()->error("[插件操作] 文件移动失败: {$source} -> {$target}");
        return false;
    }

    /**
     * 删除目录（递归删除）.
     *
     * @param string $dir 目录路径
     */
    public function removeDirectory(string $dir): bool
    {
        if (! is_dir($dir)) {
            return true;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * 删除文件.
     *
     * @param string $file 文件路径
     */
    public function removeFile(string $file): bool
    {
        if (! file_exists($file)) {
            return true;
        }

        return unlink($file);
    }

    /**
     * 创建目录（递归创建）.
     *
     * @param string $dir 目录路径
     * @param int $mode 权限模式
     */
    public function createDirectory(string $dir, int $mode = 0755): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        return mkdir($dir, $mode, true);
    }

    /**
     * 检查文件或目录是否存在.
     *
     * @param string $path 路径
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * 获取文件大小.
     *
     * @param string $file 文件路径
     */
    public function getFileSize(string $file): int
    {
        if (! file_exists($file)) {
            return 0;
        }

        return filesize($file);
    }

    /**
     * 获取目录大小.
     *
     * @param string $dir 目录路径
     */
    public function getDirectorySize(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }

        $size = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
