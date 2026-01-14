<?php

declare(strict_types=1);

namespace App\Support;

use App\Service\Admin\AddonService;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Router\Router;

/**
 * 插件路由加载器
 *
 * 自动扫描并加载 addons/ 目录下每个子文件夹中的 routes.php 文件
 * 支持插件系统的路由自动发现和加载
 *
 * 功能特性：
 * - 只加载启用的插件路由
 * - 检查插件 config.php 中的 enabled 状态
 * - 禁用的插件不会加载路由，提高性能
 * - 支持错误日志记录
 */
class RouteLoader
{
    /**
     * 插件目录路径
     */
    private string $addonsPath;

    /**
     * 插件服务实例
     */
    private ?AddonService $addonService = null;

    public function __construct()
    {
        $this->addonsPath = BASE_PATH . '/addons';
    }

    /**
     * 获取插件服务实例
     */
    private function getAddonService(): AddonService
    {
        if ($this->addonService === null) {
            // 使用ApplicationContext获取AddonService实例
            if (class_exists(ApplicationContext::class) && ApplicationContext::hasContainer()) {
                $container = ApplicationContext::getContainer();
                $this->addonService = $container->get(AddonService::class);
            } else {
                // 如果容器不可用，直接实例化（用于测试环境）
                $this->addonService = new AddonService();
            }
        }

        return $this->addonService;
    }

    /**
     * 加载所有插件路由文件
     */
    public function loadRoutes(): void
    {
        if (!is_dir($this->addonsPath)) {
            return;
        }

        $routeFiles = $this->scanRouteFiles();

        foreach ($routeFiles as $file) {
            // 检查插件是否启用，只有启用的插件才加载路由
            $addonName = $this->getAddonNameFromRouteFile($file);
            if ($this->isAddonEnabled($addonName)) {
                $this->loadRouteFile($file);
            } else {
                // 记录跳过加载的插件（可选）
                $this->logSkippedAddon($addonName);
            }
        }
    }

    /**
     * 扫描插件路由文件
     *
     * 只加载 addons/ 目录下每个子文件夹中的 routes.php 文件
     */
    private function scanRouteFiles(): array
    {
        $files = [];

        // 检查 addons 目录是否存在
        if (!is_dir($this->addonsPath)) {
            return $files;
        }

        // 获取 addons 目录下的所有子目录
        $addonsDir = new \DirectoryIterator($this->addonsPath);

        foreach ($addonsDir as $addon) {
            // 只处理目录，跳过 . 和 ..
            if (!$addon->isDir() || $addon->isDot()) {
                continue;
            }

            // 检查是否存在 routes.php 文件
            $routesFile = $addon->getPathname() . '/routes.php';
            if (file_exists($routesFile) && is_file($routesFile)) {
                $files[] = $routesFile;
            }
        }

        // 按文件名排序，确保加载顺序一致
        sort($files);

        return $files;
    }

    /**
     * 从路由文件路径提取插件名称
     */
    private function getAddonNameFromRouteFile(string $routeFilePath): string
    {
        // 路径格式：/path/to/addons/PluginName/routes.php
        // 提取PluginName部分
        $pathParts = explode('/', $routeFilePath);
        $addonsIndex = array_search('addons', $pathParts);

        if ($addonsIndex !== false && isset($pathParts[$addonsIndex + 1])) {
            return $pathParts[$addonsIndex + 1];
        }

        // 如果无法提取，返回文件名（去掉.php扩展名）
        return pathinfo($routeFilePath, PATHINFO_FILENAME);
    }

    /**
     * 检查插件是否启用
     */
    private function isAddonEnabled(string $addonName): bool
    {
        try {
            $addonService = $this->getAddonService();
            return $addonService->isAddonEnabled($addonName);
        } catch (\Throwable $e) {
            // 如果检查失败，默认启用（避免破坏现有功能）
            $this->logCheckError($addonName, $e);
            return true;
        }
    }

    /**
     * 记录跳过加载的插件
     */
    private function logSkippedAddon(string $addonName): void
    {
        if (class_exists(ApplicationContext::class) && ApplicationContext::hasContainer()) {
            try {
                $logger = ApplicationContext::getContainer()->get(\Hyperf\Contract\StdoutLoggerInterface::class);
                $logger->info("Skipped loading routes for disabled addon: {$addonName}");
            } catch (\Throwable $loggerError) {
                // logger不可用时静默跳过
            }
        }
    }

    /**
     * 记录检查错误
     */
    private function logCheckError(string $addonName, \Throwable $e): void
    {
        if (class_exists(ApplicationContext::class) && ApplicationContext::hasContainer()) {
            try {
                $logger = ApplicationContext::getContainer()->get(\Hyperf\Contract\StdoutLoggerInterface::class);
                $logger->warning("Failed to check addon status for {$addonName}, loading routes anyway", [
                    'error' => $e->getMessage()
                ]);
            } catch (\Throwable $loggerError) {
                // logger不可用时使用error_log
                error_log("Failed to check addon status for {$addonName}: {$e->getMessage()}");
            }
        } else {
            error_log("Failed to check addon status for {$addonName}: {$e->getMessage()}");
        }
    }

    /**
     * 加载单个路由文件
     */
    private function loadRouteFile(string $filePath): void
    {
        try {
            // 获取相对路径（用于错误信息）
            $relativePath = str_replace(BASE_PATH . '/', '', $filePath);

            // 包含路由文件
            require_once $filePath;

        } catch (\Throwable $e) {
            // 记录错误但不中断整个路由加载过程
            $relativePath = str_replace(BASE_PATH . '/', '', $filePath);

            // 检查ApplicationContext是否可用
            if (class_exists(ApplicationContext::class) && ApplicationContext::hasContainer()) {
                try {
                    $logger = ApplicationContext::getContainer()->get(\Hyperf\Contract\StdoutLoggerInterface::class);
                    $logger->error("Failed to load route file: {$relativePath}", [
                        'error' => $e->getMessage(),
                        'file' => $filePath
                    ]);
                } catch (\Throwable $loggerError) {
                    // 如果logger也无法获取，使用error_log
                    error_log("Failed to load route file: {$relativePath} - Error: {$e->getMessage()}");
                }
            } else {
                // ApplicationContext不可用时使用error_log
                error_log("Failed to load route file: {$relativePath} - Error: {$e->getMessage()}");
            }
        }
    }

    /**
     * 获取插件路由文件列表（用于调试）
     */
    public function getRouteFiles(): array
    {
        return $this->scanRouteFiles();
    }

    /**
     * 获取启用的插件列表（用于调试）
     */
    public function getEnabledAddons(): array
    {
        $routeFiles = $this->scanRouteFiles();
        $enabledAddons = [];

        foreach ($routeFiles as $file) {
            $addonName = $this->getAddonNameFromRouteFile($file);
            if ($this->isAddonEnabled($addonName)) {
                $enabledAddons[] = $addonName;
            }
        }

        return $enabledAddons;
    }

    /**
     * 获取禁用的插件列表（用于调试）
     */
    public function getDisabledAddons(): array
    {
        $routeFiles = $this->scanRouteFiles();
        $disabledAddons = [];

        foreach ($routeFiles as $file) {
            $addonName = $this->getAddonNameFromRouteFile($file);
            if (!$this->isAddonEnabled($addonName)) {
                $disabledAddons[] = $addonName;
            }
        }

        return $disabledAddons;
    }

    /**
     * 检查插件路由文件是否存在
     */
    public function hasRouteFile(string $filename): bool
    {
        $files = $this->scanRouteFiles();
        $basename = pathinfo($filename, PATHINFO_BASENAME);

        foreach ($files as $file) {
            if (basename($file) === $basename) {
                return true;
            }
        }

        return false;
    }
}
