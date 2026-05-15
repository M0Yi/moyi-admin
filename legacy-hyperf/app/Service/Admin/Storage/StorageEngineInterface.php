<?php

declare(strict_types=1);

namespace App\Service\Admin\Storage;

use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * 存储引擎接口
 * 定义统一的存储接口，支持本地存储和S3存储
 */
interface StorageEngineInterface
{
    /**
     * 生成上传凭证（用于客户端直传）
     *
     * @param string $filename 文件名
     * @param string $contentType 文件类型（MIME类型）
     * @param int $fileSize 文件大小（字节）
     * @param string $subPath 子路径（可选，如 'images', 'avatars'）
     * @param RequestInterface|null $request 请求对象（可选，用于生成完整URL）
     * @return array 返回上传凭证信息
     *   - method: 上传方法（'PUT' 或 'POST'）
     *   - url: 上传目标URL（完整URL，包含域名和协议）
     *   - headers: 需要设置的自定义请求头（如签名、Content-Type等）
     *   - fields: POST表单字段（如果使用POST方法）
     *   - final_url: 上传成功后的文件访问URL
     */
    public function generateUploadToken(
        string $filename,
        string $contentType,
        int $fileSize,
        string $subPath = 'images',
        ?RequestInterface $request = null
    ): array;

    /**
     * 获取存储类型
     *
     * @return string 存储类型（'local' 或 's3'）
     */
    public function getType(): string;

    /**
     * 验证文件是否已存在
     *
     * @param string $path 文件路径
     * @return bool
     */
    public function fileExists(string $path): bool;

    /**
     * 获取文件访问URL
     *
     * @param string $path 文件路径
     * @return string 文件访问URL
     */
    public function getFileUrl(string $path): string;

    /**
     * 验证上传签名（用于验证客户端上传请求）
     *
     * @param string $token 上传令牌
     * @param array $params 请求参数
     * @return bool 验证是否通过
     */
    public function verifyUploadToken(string $token, array $params): bool;
}

