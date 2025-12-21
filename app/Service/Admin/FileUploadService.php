<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Model\Admin\AdminUploadFile;
use App\Service\Admin\Storage\StorageEngineInterface;
use App\Service\Admin\Storage\StorageEngineFactory;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * 文件上传服务
 * 提供统一的文件上传接口，支持所有文件类型，支持本地存储和S3存储
 */
class FileUploadService
{
    #[Inject]
    protected StorageEngineFactory $storageFactory;

    #[Inject]
    protected ConfigInterface $config;

    /**
     * 获取上传凭证
     *
     * @param string $filename 原始文件名
     * @param string $contentType 文件MIME类型
     * @param int $fileSize 文件大小（字节）
     * @param string $subPath 子路径（可选，如 'files', 'documents'）
     * @param string|null $driver 存储驱动（null时使用默认配置）
     * @param RequestInterface|null $request 请求对象（可选，用于获取用户信息和IP）
     * @return array 上传凭证信息
     */
    public function getUploadToken(
        string $filename,
        string $contentType,
        int $fileSize,
        string $subPath = 'files',
        ?string $driver = null,
        ?RequestInterface $request = null
    ): array {
        // 验证文件大小（优先从站点配置读取，其次从系统配置读取）
        $maxSize = $this->getMaxUploadSize();
        if ($fileSize > $maxSize) {
            throw new \RuntimeException("文件大小超过限制：" . $this->formatFileSize($maxSize));
        }

        // 获取允许的文件类型（优先从站点配置读取，其次从系统配置读取）
        $allowedTypes = $this->getAllowedMimeTypes();
        $allowedExtensions = $this->getAllowedExtensions();
        
        // 记录日志
        $logData = [
            'filename' => $filename,
            'content_type' => $contentType,
            'file_size' => $fileSize,
            'allowed_mime_types' => $allowedTypes,
            'allowed_extensions' => $allowedExtensions,
            'site_id' => \site_id(),
        ];
        logger()->info('[FileUploadService] 文件上传验证', $logData);
        
        // 验证 MIME 类型
        if (!empty($allowedTypes)) {
            $contentTypeLower = strtolower(trim($contentType));
            // 对数组中的每个值也进行 trim 和 strtolower 处理
            $allowedTypesLower = array_map(function($type) {
                return strtolower(trim($type));
            }, $allowedTypes);
            // 移除空值
            $allowedTypesLower = array_filter($allowedTypesLower);
            $allowedTypesLower = array_values($allowedTypesLower); // 重新索引数组
            
            // 记录验证过程
            $checkResult = in_array($contentTypeLower, $allowedTypesLower, true);
            logger()->info('[FileUploadService] MIME 类型验证过程', [
                'content_type_original' => $contentType,
                'content_type_lower' => $contentTypeLower,
                'content_type_length' => strlen($contentType),
                'content_type_lower_length' => strlen($contentTypeLower),
                'allowed_types_count' => count($allowedTypes),
                'allowed_types_lower' => $allowedTypesLower,
                'check_result' => $checkResult,
                'in_array_strict' => $checkResult,
            ]);
            
            if (!$checkResult) {
                logger()->warning('[FileUploadService] MIME 类型验证失败', [
                    'filename' => $filename,
                    'content_type' => $contentType,
                    'content_type_lower' => $contentTypeLower,
                    'allowed_types' => $allowedTypes,
                    'allowed_types_lower' => $allowedTypesLower,
                    'site_id' => \site_id(),
                ]);
                throw new \RuntimeException("不支持的文件类型：{$contentType}");
            }
        }
        
        // 验证文件扩展名
        if (!empty($allowedExtensions)) {
            $extension = strtolower(trim(pathinfo($filename, PATHINFO_EXTENSION)));
            // 对数组中的每个值也进行 trim 和 strtolower 处理
            $allowedExtensionsLower = array_map(function($ext) {
                return strtolower(trim($ext));
            }, $allowedExtensions);
            // 移除空值
            $allowedExtensionsLower = array_filter($allowedExtensionsLower);
            $allowedExtensionsLower = array_values($allowedExtensionsLower); // 重新索引数组
            
            if (!in_array($extension, $allowedExtensionsLower, true)) {
                logger()->warning('[FileUploadService] 文件扩展名验证失败', [
                    'filename' => $filename,
                    'extension' => $extension,
                    'allowed_extensions' => $allowedExtensions,
                    'allowed_extensions_lower' => $allowedExtensionsLower,
                    'site_id' => \site_id(),
                ]);
                throw new \RuntimeException("不支持的文件扩展名：{$extension}");
            }
        }

        // 获取存储引擎（如果 driver 为 null，会从站点配置或系统配置自动获取）
        $storageEngine = $this->storageFactory->create($driver);

        // 生成上传凭证（传递请求对象，用于生成完整URL）
        $tokenData = $storageEngine->generateUploadToken($filename, $contentType, $fileSize, $subPath, $request);

        // 获取实际使用的存储驱动类型（从存储引擎获取，确保与使用的引擎一致）
        $actualDriver = $driver ?? $storageEngine->getType();

        // 记录文件信息到数据库
        $this->recordFileInfo($tokenData, $filename, $contentType, $fileSize, $subPath, $actualDriver, $request);

        return $tokenData;
    }

    /**
     * 记录文件信息到数据库
     */
    private function recordFileInfo(
        array $tokenData,
        string $originalFilename,
        string $contentType,
        int $fileSize,
        string $subPath,
        ?string $driver,
        ?RequestInterface $request
    ): void {
        // 获取用户信息（支持对象和数组两种格式）
        $user = Context::get('admin_user');
        if (is_object($user)) {
            $userId = $user->id ?? null;
            $username = $user->username ?? null;
        } else {
            $userId = $user['id'] ?? null;
            $username = $user['username'] ?? null;
        }
        
        // 如果从 Context 获取不到，尝试从 admin_user_id 获取
        if (!$userId) {
            $userId = Context::get('admin_user_id');
        }

        // 获取站点ID
        $siteId = \site_id();

        // 获取存储驱动（使用传入的 driver，应该已经是实际使用的驱动类型）
        $storageDriver = $driver ?? $this->config->get('upload.driver', 'local');

        // 从tokenData中提取文件路径和文件名
        // 优先从path字段获取（如果存储引擎返回）
        // 否则从final_url中提取路径
        $filePath = $tokenData['path'] ?? '';
        if (empty($filePath)) {
            // 从final_url中提取路径
            $finalUrl = $tokenData['final_url'] ?? '';
            if (!empty($finalUrl)) {
                // 移除域名和公共路径前缀，获取相对路径
                $publicPath = $this->config->get('upload.public_path', '/storage/uploads');
                if (str_starts_with($finalUrl, $publicPath)) {
                    $filePath = substr($finalUrl, strlen($publicPath) + 1);
                } else {
                    // 如果是完整URL，提取路径部分
                    $parsedUrl = parse_url($finalUrl);
                    $filePath = ltrim($parsedUrl['path'] ?? '', '/');
                }
            }
        }
        
        // 如果仍然没有路径，生成一个（基于subPath和日期）
        if (empty($filePath)) {
            $datePath = date('Y/m/d');
            $safeFilename = $this->generateSafeFilename($originalFilename);
            $filePath = "{$subPath}/{$datePath}/{$safeFilename}";
        }
        
        $filename = basename($filePath);

        // 获取IP和User Agent
        $ipAddress = null;
        $userAgent = null;
        if ($request) {
            $serverParams = $request->getServerParams();
            $ipAddress = $serverParams['remote_addr'] ?? null;
            if (isset($serverParams['http_x_forwarded_for'])) {
                $ips = explode(',', $serverParams['http_x_forwarded_for']);
                $ipAddress = trim($ips[0]);
            }
            $userAgent = $request->getHeaderLine('User-Agent');
        }

        // 创建文件记录
        AdminUploadFile::create([
            'site_id' => $siteId,
            'upload_token' => $tokenData['token'],
            'user_id' => $userId,
            'username' => $username,
            'original_filename' => $originalFilename,
            'filename' => $filename,
            'file_path' => $filePath,
            'file_url' => $tokenData['final_url'] ?? null,
            'content_type' => $contentType,
            'file_size' => $fileSize,
            'storage_driver' => $storageDriver,
            'status' => AdminUploadFile::STATUS_PENDING,
            'token_expire_at' => date('Y-m-d H:i:s', $tokenData['expire_at'] ?? time() + 3600),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * 更新文件上传状态
     *
     * @param string $token 上传令牌
     * @param string $fileUrl 文件访问URL
     * @return AdminUploadFile|null 文件记录
     */
    public function markFileAsUploaded(string $token, string $fileUrl): ?AdminUploadFile
    {
        $file = AdminUploadFile::where('upload_token', $token)->first();
        if ($file) {
            $file->file_url = $fileUrl;
            $file->markAsUploaded();
        }
        if($file instanceof AdminUploadFile){
            return $file;
        }
        return null;
    }

    /**
     * 验证上传令牌
     *
     * @param string $token 上传令牌
     * @param array $params 请求参数
     * @param string|null $driver 存储驱动
     * @return bool
     */
    public function verifyUploadToken(string $token, array $params, ?string $driver = null): bool
    {
        $storageEngine = $this->storageFactory->create($driver);
        return $storageEngine->verifyUploadToken($token, $params);
    }

    /**
     * 获取文件访问URL
     *
     * @param string $path 文件路径
     * @param string|null $driver 存储驱动
     * @return string
     */
    public function getFileUrl(string $path, ?string $driver = null): string
    {
        $storageEngine = $this->storageFactory->create($driver);
        return $storageEngine->getFileUrl($path);
    }

    /**
     * 获取当前存储引擎类型
     *
     * @param string|null $driver 存储驱动
     * @return string
     */
    public function getStorageType(?string $driver = null): string
    {
        $storageEngine = $this->storageFactory->create($driver);
        return $storageEngine->getType();
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

    /**
     * 格式化文件大小
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 获取允许的 MIME 类型列表
     * 优先从站点配置读取，其次从系统配置读取
     *
     * @return array 如果为空数组，表示允许所有类型
     */
    private function getAllowedMimeTypes(): array
    {
        // 优先从站点配置读取
        $site = site();
        if ($site) {
            $siteMimeTypes = $site->getAllowedMimeTypes();
            $logData = [
                'site_id' => $site->id,
                'site_mime_types' => $siteMimeTypes,
                'upload_allowed_mime_types_field' => $site->upload_allowed_mime_types,
            ];
            logger()->info('[FileUploadService] 从站点配置读取 MIME 类型', $logData);
            if (!empty($siteMimeTypes)) {
                return $siteMimeTypes;
            }
        }

        // 从系统配置读取
        $systemTypes = $this->config->get('upload.allowed_types', []);
        $logData = [
            'system_mime_types' => $systemTypes,
        ];
        logger()->info('[FileUploadService] 从系统配置读取 MIME 类型', $logData);
        return $systemTypes;
    }

    /**
     * 获取允许的文件扩展名列表
     * 优先从站点配置读取，其次从系统配置读取
     *
     * @return array 如果为空数组，表示允许所有扩展名
     */
    private function getAllowedExtensions(): array
    {
        // 优先从站点配置读取
        $site = site();
        if ($site) {
            $siteExtensions = $site->getAllowedExtensions();
            $logData = [
                'site_id' => $site->id,
                'site_extensions' => $siteExtensions,
                'upload_allowed_extensions_field' => $site->upload_allowed_extensions,
            ];
            logger()->info('[FileUploadService] 从站点配置读取文件扩展名', $logData);
            if (!empty($siteExtensions)) {
                return $siteExtensions;
            }
        }

        // 从系统配置读取（如果配置了扩展名）
        $systemExtensions = $this->config->get('upload.allowed_extensions', []);
        $logData = [
            'system_extensions' => $systemExtensions,
        ];
        logger()->info('[FileUploadService] 从系统配置读取文件扩展名', $logData);
        return $systemExtensions;
    }

    /**
     * 获取最大上传文件大小（字节）
     * 优先从站点配置读取，其次从系统配置读取
     *
     * @return int 最大文件大小（字节），默认 50MB
     */
    private function getMaxUploadSize(): int
    {
        // 优先从站点配置读取
        $site = site();
        if ($site) {
            $siteMaxSize = $site->getMaxUploadSize();
            if ($siteMaxSize !== null && $siteMaxSize > 0) {
                return $siteMaxSize;
            }
        }

        // 从系统配置读取
        return $this->config->get('upload.max_size', 50 * 1024 * 1024);
    }
}

