<?php

declare(strict_types=1);

namespace App\Service\Admin\Storage;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Context\Context;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\SimpleCache\CacheInterface;
use function Hyperf\Support\make;
use function admin_route;
use function site_id;

/**
 * 本地存储引擎实现
 * 支持客户端PUT直传到服务器
 * 优先从站点配置读取本地存储配置，如果站点未配置则使用系统默认配置
 */
class LocalStorageEngine implements StorageEngineInterface
{
    private const TOKEN_EXPIRE = 3600; // Token有效期1小时

    public function __construct(
        private ConfigInterface $config,
        private FilesystemFactory $filesystemFactory,
        private CacheInterface $cache
    ) {}

    /**
     * 生成上传凭证（本地存储）
     */
    public function generateUploadToken(
        string $filename,
        string $contentType,
        int $fileSize,
        string $subPath = 'images',
        ?RequestInterface $request = null
    ): array {
        // 验证文件大小（默认最大10MB）
        $maxSize = $this->config->get('upload.max_size', 10 * 1024 * 1024);
        if ($fileSize > $maxSize) {
            throw new \RuntimeException("文件大小超过限制：{$maxSize} 字节");
        }

        // 验证文件类型
        $allowedTypes = $this->config->get('upload.allowed_types', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        if (!in_array($contentType, $allowedTypes)) {
            throw new \RuntimeException("不支持的文件类型：{$contentType}");
        }

        // 获取站点ID和管理员ID
        $siteId = site_id();
        $adminId = $this->getAdminId();
        
        if (!$siteId) {
            throw new \RuntimeException('无法获取站点ID，请确保已登录且站点已配置');
        }
        
        if (!$adminId) {
            throw new \RuntimeException('无法获取管理员ID，请确保已登录');
        }

        // 生成安全的文件名
        $safeFilename = $this->generateSafeFilename($filename);

        // 生成文件路径（站点ID/管理员ID/子路径/日期/文件名）
        $datePath = date('Y/m/d');
        $relativePath = "{$siteId}/{$adminId}/{$subPath}/{$datePath}/{$safeFilename}";
        $fullPath = $this->getStoragePath() . '/' . $relativePath;

        // 确保目录存在
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 生成上传令牌
        $token = bin2hex(random_bytes(32));
        $tokenKey = "upload_token:{$token}";

        // 存储令牌信息（1小时有效期）
        $tokenData = [
            'path' => $relativePath,
            'filename' => $safeFilename,
            'content_type' => $contentType,
            'file_size' => $fileSize,
            'expire_at' => time() + self::TOKEN_EXPIRE,
        ];

        $this->cache->set($tokenKey, $tokenData, self::TOKEN_EXPIRE);

        // 生成上传URL（PUT方法，包含完整域名）
        $uploadUrl = $this->getUploadUrl($relativePath, $token, $request);

        // 生成文件访问URL
        $finalUrl = $this->getFileUrl($relativePath);

        return [
            'method' => 'PUT',
            'url' => $uploadUrl,
            'headers' => [
                'Content-Type' => $contentType,
                'X-Upload-Token' => $token,
            ],
            'fields' => [],
            'final_url' => $finalUrl,
            'token' => $token,
            'expire_at' => $tokenData['expire_at'],
            'path' => $relativePath, // 添加路径信息，方便记录到数据库
        ];
    }

    /**
     * 获取存储类型
     */
    public function getType(): string
    {
        return 'local';
    }

    /**
     * 验证文件是否存在
     */
    public function fileExists(string $path): bool
    {
        $fullPath = $this->getStoragePath() . '/' . ltrim($path, '/');
        return file_exists($fullPath);
    }

    /**
     * 获取文件访问URL
     * 优先从站点配置读取公共访问路径，如果站点未配置则使用系统默认配置
     */
    public function getFileUrl(string $path): string
    {
        // 路径格式：{site_id}/{admin_id}/images/2024/01/01/xxx.jpg
        // 访问URL：/uploads/{site_id}/{admin_id}/images/2024/01/01/xxx.jpg
        
        // 优先从站点配置读取公共访问路径
        $publicPath = $this->getPublicPath();
        $path = ltrim($path, '/');
        return rtrim($publicPath, '/') . '/' . $path;
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

    /**
     * 获取存储根目录（public/uploads）
     * 优先从站点配置读取，如果站点未配置则使用系统默认配置
     */
    private function getStoragePath(): string
    {
        $currentSite = \site();

        // 如果站点存在且有本地存储配置，优先使用站点配置
        if ($currentSite && $currentSite->hasUploadConfig()) {
            $siteLocalConfig = $currentSite->getLocalStorageConfig();
            if ($siteLocalConfig !== null && isset($siteLocalConfig['storage_path'])) {
                $root = $siteLocalConfig['storage_path'];
            } else {
                // 使用系统默认配置
                $root = $this->config->get('upload.storage_path', BASE_PATH . '/public/uploads');
            }
        } else {
            // 使用系统默认配置
            $root = $this->config->get('upload.storage_path', BASE_PATH . '/public/uploads');
        }
        
        // 如果是相对路径，转换为绝对路径
        if (!str_starts_with($root, '/')) {
            $root = BASE_PATH . '/' . $root;
        }

        return $root;
    }

    /**
     * 获取公共访问路径
     * 优先从站点配置读取，如果站点未配置则使用系统默认配置
     */
    private function getPublicPath(): string
    {
        $currentSite = \site();

        // 如果站点存在且有本地存储配置，优先使用站点配置
        if ($currentSite && $currentSite->hasUploadConfig()) {
            $siteLocalConfig = $currentSite->getLocalStorageConfig();
            if ($siteLocalConfig !== null && isset($siteLocalConfig['public_path'])) {
                return $siteLocalConfig['public_path'];
            }
        }

        // 否则使用系统默认配置
        return $this->config->get('upload.public_path', '/uploads');
    }
    
    /**
     * 获取当前管理员ID
     */
    private function getAdminId(): ?int
    {
        // 优先从 Context 获取
        $adminId = Context::get('admin_user_id');
        if ($adminId) {
            return (int) $adminId;
        }
        
        // 从 admin_user 对象获取
        $adminUser = Context::get('admin_user');
        if ($adminUser) {
            if (is_array($adminUser)) {
                return isset($adminUser['id']) ? (int) $adminUser['id'] : null;
            }
            if (is_object($adminUser) && isset($adminUser->id)) {
                return (int) $adminUser->id;
            }
        }
        
        // 尝试从认证守卫获取
        try {
            $guard = make(\HyperfExtension\Auth\Contracts\AuthManagerInterface::class)->guard('admin');
            if ($guard->check()) {
                $user = $guard->user();
                if ($user) {
                    if (is_array($user)) {
                        return isset($user['id']) ? (int) $user['id'] : null;
                    }
                    if (is_object($user) && isset($user->id)) {
                        return (int) $user->id;
                    }
                }
            }
        } catch (\Exception $e) {
            // 忽略异常，继续返回 null
        }
        
        return null;
    }

    /**
     * 生成上传URL（包含完整域名和协议）
     */
    private function getUploadUrl(string $relativePath, string $token, ?RequestInterface $request = null): string
    {
        // 使用 admin_route() 函数生成包含管理路径的相对路径
        // 例如：/admin/xyz123/api/admin/upload/images/2024/01/01/xxx.jpg
        $basePath = admin_route('api/admin/upload');
        $relativePath = ltrim($relativePath, '/');
        
        // URL编码路径（处理特殊字符）
        $encodedPath = str_replace(['%2F', '%5C'], ['/', '\\'], urlencode($relativePath));
        
        $fullPath = "{$basePath}/{$encodedPath}?token={$token}";
        
        // 如果提供了请求对象，生成完整的URL（包含域名和协议）
        if ($request !== null) {
            $uri = $request->getUri();
            $scheme = $uri->getScheme();
            $host = $uri->getHost();
            
            // 检查是否有端口号
            $port = $uri->getPort();
            if ($port !== null && !in_array($port, [80, 443])) {
                $host .= ':' . $port;
            }
            
            // 确保使用 HTTPS（如果是生产环境）
            // 如果外层使用了 HTTPS 代理，但内部是 HTTP，需要强制使用 HTTPS
            if ($scheme === 'http' && ($request->getHeaderLine('X-Forwarded-Proto') === 'https' || 
                $request->getHeaderLine('Forwarded') !== '')) {
                $scheme = 'https';
            }
            
            return "{$scheme}://{$host}{$fullPath}";
        }
        
        // 如果没有请求对象，返回相对路径（向后兼容）
        return $fullPath;
    }
}

