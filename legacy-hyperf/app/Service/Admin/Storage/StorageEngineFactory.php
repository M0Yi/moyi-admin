<?php

declare(strict_types=1);

namespace App\Service\Admin\Storage;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use function Hyperf\Support\make;

/**
 * 存储引擎工厂类
 * 根据配置创建对应的存储引擎实例
 * 优先从站点配置读取，如果站点未配置则使用系统默认配置
 */
class StorageEngineFactory
{
    public function __construct(
        private ContainerInterface $container,
        private ConfigInterface $config
    ) {}

    /**
     * 创建存储引擎实例
     *
     * @param string|null $driver 驱动名称（null时从站点配置或系统配置读取）
     * @return StorageEngineInterface
     */
    public function create(?string $driver = null): StorageEngineInterface
    {
        // 如果没有指定驱动，优先从站点配置读取
        if ($driver === null) {
            $driver = $this->getDriverFromSiteOrConfig();
        }

        return match ($driver) {
            'local' => make(LocalStorageEngine::class),
            's3' => make(S3StorageEngine::class),
            default => throw new \RuntimeException("不支持的存储驱动：{$driver}"),
        };
    }

    /**
     * 获取当前配置的存储引擎
     */
    public function getDefault(): StorageEngineInterface
    {
        return $this->create();
    }

    /**
     * 优先从站点配置读取驱动类型，如果站点未配置则使用系统默认配置
     *
     * @return string
     */
    private function getDriverFromSiteOrConfig(): string
    {
        // 获取当前站点
        $currentSite = \site();

        // 如果站点存在且有上传驱动配置，优先使用站点配置
        if ($currentSite && $currentSite->getUploadDriver() !== null) {
            return $currentSite->getUploadDriver();
        }

        // 否则使用系统默认配置
        return $this->config->get('upload.driver', 'local');
    }
}

