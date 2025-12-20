<?php

declare(strict_types=1);

namespace App\Service\Admin\Storage;

use Aws\S3\S3Client;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * S3存储引擎实现
 * 支持S3预签名URL直传
 * 优先从站点配置读取S3配置，如果站点未配置则使用系统默认配置
 * 
 * 注意：需要安装 aws/aws-sdk-php
 * composer require aws/aws-sdk-php
 */
class S3StorageEngine implements StorageEngineInterface
{
    private const TOKEN_EXPIRE = 3600; // Token有效期1小时
    private const PRESIGNED_URL_EXPIRE = 3600; // 预签名URL有效期1小时

    private ?S3Client $s3Client = null;
    private ?array $siteS3Config = null;

    public function __construct(
        private ConfigInterface $config,
        private CacheInterface $cache
    ) {}

    /**
     * 初始化S3客户端
     * 优先从站点配置读取S3配置，如果站点未配置则使用系统默认配置
     */
    private function initS3Client(): void
    {
        if ($this->s3Client !== null) {
            return;
        }

        // 优先从站点配置读取S3配置
        $s3Config = $this->getS3ConfigFromSiteOrSystem();

        // 构建 S3Client 配置
        $clientConfig = [
            'version' => $s3Config['version'] ?? 'latest',
            'region' => $s3Config['region'] ?? 'us-east-1',
            'credentials' => [
                'key' => $s3Config['credentials']['key'] ?? '',
                'secret' => $s3Config['credentials']['secret'] ?? '',
            ],
        ];
        
        // 设置 endpoint（如果配置了）
        if (!empty($s3Config['endpoint'])) {
            $clientConfig['endpoint'] = $s3Config['endpoint'];
        }
        
        // 设置 Path Style（重要：影响预签名 URL 的生成格式）
        $clientConfig['use_path_style_endpoint'] = $s3Config['use_path_style_endpoint'] ?? false;
        
        // 设置 bucket_endpoint（如果配置了）
        if (isset($s3Config['bucket_endpoint'])) {
            $clientConfig['bucket_endpoint'] = $s3Config['bucket_endpoint'];
        }
        
        $this->s3Client = new S3Client($clientConfig);
    }

    /**
     * 优先从站点配置读取S3配置，如果站点未配置则使用系统默认配置
     *
     * @return array
     */
    private function getS3ConfigFromSiteOrSystem(): array
    {
        // 如果已经获取过站点配置，直接返回
        if ($this->siteS3Config !== null) {
            return $this->siteS3Config;
        }

        // 获取当前站点
        $currentSite = \site();

        // 如果站点存在且有S3配置，优先使用站点配置
        if ($currentSite && $currentSite->hasUploadConfig()) {
            $siteS3Config = $currentSite->getS3Config();
            if ($siteS3Config !== null) {
                // 转换站点配置格式为系统配置格式
                $this->siteS3Config = [
                    'version' => $siteS3Config['version'] ?? 'latest',
                    'region' => $siteS3Config['region'] ?? 'us-east-1',
                    'credentials' => [
                        'key' => $siteS3Config['key'] ?? $siteS3Config['credentials']['key'] ?? '',
                        'secret' => $siteS3Config['secret'] ?? $siteS3Config['credentials']['secret'] ?? '',
                    ],
                    'endpoint' => $siteS3Config['endpoint'] ?? null,
                    'use_path_style_endpoint' => $siteS3Config['use_path_style_endpoint'] ?? false,
                    'bucket_endpoint' => $siteS3Config['bucket_endpoint'] ?? false,
                    'bucket_name' => $siteS3Config['bucket'] ?? $siteS3Config['bucket_name'] ?? '',
                    'cdn' => $siteS3Config['cdn'] ?? '',
                ];
                return $this->siteS3Config;
            }
        }

        // 否则使用系统默认配置
        $this->siteS3Config = $this->config->get('file.storage.s3', []);
        return $this->siteS3Config;
    }

    /**
     * 生成上传凭证（S3预签名URL）
     */
    public function generateUploadToken(
        string $filename,
        string $contentType,
        int $fileSize,
        string $subPath = 'images',
        ?RequestInterface $request = null
    ): array {
        // 初始化S3客户端（延迟初始化，确保能获取到站点配置）
        $this->initS3Client();

        // 验证文件大小
        $maxSize = $this->config->get('upload.max_size', 10 * 1024 * 1024);
        if ($fileSize > $maxSize) {
            throw new \RuntimeException("文件大小超过限制：{$maxSize} 字节");
        }

        // 验证文件类型
        $allowedTypes = $this->config->get('upload.allowed_types', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        if (!in_array($contentType, $allowedTypes)) {
            throw new \RuntimeException("不支持的文件类型：{$contentType}");
        }

        // 生成安全的文件名
        $safeFilename = $this->generateSafeFilename($filename);

        // 生成文件路径（按日期分目录，不包含 bucket 名称）
        $datePath = date('Y/m/d');
        $key = "{$subPath}/{$datePath}/{$safeFilename}";

        // 获取Bucket名称和配置
        $bucket = $this->getBucketName();
        if (empty($bucket)) {
            throw new \RuntimeException('S3 Bucket未配置');
        }

        $s3Config = $this->getS3ConfigFromSiteOrSystem();
        $usePathStyle = $s3Config['use_path_style_endpoint'] ?? false;
        $endpoint = $s3Config['endpoint'] ?? '';

        // 生成预签名URL（PUT方法）
        $command = $this->s3Client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $contentType,
            'ContentLength' => $fileSize,
        ]);

        $presignedUrl = $this->s3Client->createPresignedRequest(
            $command,
            '+' . self::PRESIGNED_URL_EXPIRE . ' seconds'
        )->getUri();

        // 注意：不要修改预签名 URL，因为签名是基于原始 URL 计算的
        // 修改 URL 会导致签名失效，返回 403 Forbidden
        // 如果 URL 格式不对，应该检查 S3Client 的配置（use_path_style_endpoint 等）
        
        // 记录生成的 URL 用于调试（可选）
        // logger()->debug('[S3StorageEngine] 生成的预签名 URL', [
        //     'url' => (string) $presignedUrl,
        //     'bucket' => $bucket,
        //     'key' => $key,
        //     'use_path_style' => $usePathStyle,
        //     'endpoint' => $endpoint,
        // ]);

        // 生成上传令牌（用于服务端验证）
        $token = bin2hex(random_bytes(32));
        $tokenKey = "upload_token:{$token}";

        // 存储令牌信息
        $tokenData = [
            'key' => $key,
            'bucket' => $bucket,
            'filename' => $safeFilename,
            'content_type' => $contentType,
            'file_size' => $fileSize,
            'expire_at' => time() + self::TOKEN_EXPIRE,
        ];

        $this->cache->set($tokenKey, $tokenData, self::TOKEN_EXPIRE);

        // 生成文件访问URL
        $finalUrl = $this->getFileUrl($key);

        return [
            'method' => 'PUT',
            'url' => (string) $presignedUrl,
            'headers' => [
                'Content-Type' => $contentType,
                'Content-Length' => (string) $fileSize,
            ],
            'fields' => [],
            'final_url' => $finalUrl,
            'token' => $token,
            'expire_at' => $tokenData['expire_at'],
            'path' => $key, // S3 Key（不包含 bucket 名称）
        ];
    }

    /**
     * 获取Bucket名称（优先从站点配置读取）
     *
     * @return string
     */
    private function getBucketName(): string
    {
        $s3Config = $this->getS3ConfigFromSiteOrSystem();
        return $s3Config['bucket_name'] ?? '';
    }

    /**
     * 获取存储类型
     */
    public function getType(): string
    {
        return 's3';
    }

    /**
     * 验证文件是否存在
     */
    public function fileExists(string $path): bool
    {
        // 初始化S3客户端（延迟初始化）
        $this->initS3Client();

        $bucket = $this->getBucketName();
        if (empty($bucket)) {
            return false;
        }

        try {
            return $this->s3Client->doesObjectExist($bucket, $path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取文件访问URL
     * 优先从站点配置读取CDN和端点配置
     * 注意：path 参数应该是 S3 Key（不包含 bucket 名称），例如：images/2024/01/01/file.jpg
     */
    public function getFileUrl(string $path): string
    {
        $s3Config = $this->getS3ConfigFromSiteOrSystem();
        $bucket = $s3Config['bucket_name'] ?? '';
        $cdn = $s3Config['cdn'] ?? '';

        // 确保 path 不包含 bucket 名称（移除开头的 bucket 名称）
        $path = $this->normalizePath($path, $bucket);

        // 如果配置了CDN，使用CDN域名
        // CDN 域名应该已经指向 bucket 根目录，直接拼接路径即可（不包含 bucket）
        if (!empty($cdn)) {
            // 移除 CDN 域名末尾可能包含的 bucket 名称（如果用户错误配置了）
            $cdn = rtrim($cdn, '/');
            if (str_ends_with($cdn, '/' . $bucket)) {
                $cdn = substr($cdn, 0, -strlen('/' . $bucket));
            }
            
            // 拼接路径（path 已经规范化，不包含 bucket）
            return $cdn . '/' . ltrim($path, '/');
        }

        // 否则使用S3标准URL
        $region = $s3Config['region'] ?? 'us-east-1';
        $endpoint = $s3Config['endpoint'] ?? '';
        $usePathStyle = $s3Config['use_path_style_endpoint'] ?? false;
        
        if (!empty($endpoint)) {
            // 自定义端点（如MinIO、阿里云OSS、七牛云等）
            if ($usePathStyle) {
                // Path Style: https://endpoint/bucket/path
                return rtrim($endpoint, '/') . "/{$bucket}/" . ltrim($path, '/');
            }
            // Virtual Hosted Style: https://bucket.endpoint/path
            // 注意：某些服务商的 endpoint 格式可能不同，这里假设 endpoint 是基础端点
            return rtrim($endpoint, '/') . '/' . ltrim($path, '/');
        }

        // AWS S3标准URL（Virtual Hosted Style）
        // 格式：https://bucket.s3.region.amazonaws.com/path
        return "https://{$bucket}.s3.{$region}.amazonaws.com/" . ltrim($path, '/');
    }

    /**
     * 规范化路径，移除可能包含的 bucket 名称
     * 
     * @param string $path 原始路径
     * @param string $bucket Bucket 名称
     * @return string 规范化后的路径
     */
    private function normalizePath(string $path, string $bucket): string
    {
        // 移除开头的斜杠
        $path = ltrim($path, '/');
        
        // 如果路径以 bucket 名称开头，移除它
        if (!empty($bucket) && str_starts_with($path, $bucket . '/')) {
            $path = substr($path, strlen($bucket) + 1);
        }
        
        return $path;
    }

    /**
     * 验证上传令牌
     */
    public function verifyUploadToken(string $token, array $params): bool
    {
        $tokenKey = "upload_token:{$token}";
        $tokenData = $this->cache->get($tokenKey);

        if (!$tokenData) {
            return false;
        }

        // 检查是否过期
        if ($tokenData['expire_at'] < time()) {
            $this->cache->delete($tokenKey);
            return false;
        }

        // 验证文件大小
        if (isset($params['file_size']) && $params['file_size'] != $tokenData['file_size']) {
            return false;
        }

        // 验证Content-Type
        if (isset($params['content_type']) && $params['content_type'] != $tokenData['content_type']) {
            return false;
        }

        return true;
    }

    /**
     * 生成安全的文件名
     */
    private function generateSafeFilename(string $originalFilename): string
    {
        // 获取文件扩展名
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        // 生成随机文件名（使用时间戳+随机字符串）
        $randomString = bin2hex(random_bytes(8));
        $timestamp = time();
        return "{$timestamp}_{$randomString}.{$extension}";
    }
}
