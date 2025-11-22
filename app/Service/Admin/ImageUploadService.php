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
 * 图片上传服务
 * 提供统一的图片上传接口，支持本地存储和S3存储
 */
class ImageUploadService
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
     * @param string $subPath 子路径（可选，如 'images', 'avatars'）
     * @param string|null $driver 存储驱动（null时使用默认配置）
     * @param RequestInterface|null $request 请求对象（可选，用于获取用户信息和IP）
     * @return array 上传凭证信息
     */
    public function getUploadToken(
        string $filename,
        string $contentType,
        int $fileSize,
        string $subPath = 'images',
        ?string $driver = null,
        ?RequestInterface $request = null
    ): array {
        // 验证文件类型（必须是图片）
        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
        if (!in_array(strtolower($contentType), $allowedImageTypes)) {
            throw new \RuntimeException("不支持的文件类型：{$contentType}");
        }

        // 获取存储引擎
        $storageEngine = $this->storageFactory->create($driver);

        // 生成上传凭证（传递请求对象，用于生成完整URL）
        $tokenData = $storageEngine->generateUploadToken($filename, $contentType, $fileSize, $subPath, $request);

        // 记录文件信息到数据库
        $this->recordFileInfo($tokenData, $filename, $contentType, $fileSize, $subPath, $driver, $request);

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

        // 获取存储驱动
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
        if($file instanceof  AdminUploadFile){
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
}

