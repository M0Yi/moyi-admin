<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\AbstractController;
use App\Model\Admin\AdminUploadFile;
use App\Service\Admin\FileUploadService;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 文件上传控制器（统一上传接口）
 * 提供客户端直传PUT方案的上传接口
 * 支持所有文件类型（图片、视频、文档等），文件类型验证由站点配置控制
 */
class ImageUploadController extends AbstractController
{
    #[Inject]
    protected FileUploadService $uploadService;

    #[Inject]
    protected ConfigInterface $config;

    /**
     * 获取上传凭证
     * 
     * POST /api/admin/upload/token
     * 
     * 请求参数：
     * - filename: 文件名（必需）
     * - content_type: 文件MIME类型（必需）
     * - file_size: 文件大小，字节（必需）
     * - sub_path: 子路径（可选，默认：images）
     * - driver: 存储驱动（可选，默认：从配置读取）
     */
    public function getUploadToken(RequestInterface $request): ResponseInterface
    {
        try {
            // 验证参数
            $validator = $this->validationFactory->make($request->all(), [
                'filename' => 'required|string|max:255',
                'content_type' => 'required|string',
                'file_size' => 'required|integer|min:1',
                'sub_path' => 'nullable|string|max:100',
                'driver' => 'nullable|string|in:local,s3',
            ], [
                'filename.required' => '文件名不能为空',
                'content_type.required' => '文件类型不能为空',
                'file_size.required' => '文件大小不能为空',
                'file_size.integer' => '文件大小必须是整数',
                'file_size.min' => '文件大小必须大于0',
            ]);

            if ($validator->fails()) {
                return $this->error('参数验证失败', [
                    'errors' => $validator->errors()->toArray(),
                ], 400);
            }

            $filename = $request->input('filename');
            $contentType = $request->input('content_type');
            $fileSize = (int) $request->input('file_size');
            $subPath = $request->input('sub_path', 'images'); // 默认使用 images 子路径，保持向后兼容
            $driver = $request->input('driver'); // 可选，null时使用默认配置

            // 获取上传凭证（传入request用于记录用户信息和IP）
            $token = $this->uploadService->getUploadToken(
                $filename,
                $contentType,
                $fileSize,
                $subPath,
                $driver,
                $request
            );

            return $this->success($token, '获取上传凭证成功');
        } catch (\RuntimeException $e) {
            // 捕获业务异常（如不支持的文件类型），返回友好的错误响应
            return $this->error($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            // 记录其他异常到日志
            logger()->error('[ImageUploadController] 获取上传凭证失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('获取上传凭证失败：' . $e->getMessage(), null, 500);
        }
    }

    /**
     * 处理文件上传（PUT方法）
     * 
     * PUT /api/admin/upload/{path}
     * 
     * 请求头：
     * - Content-Type: 文件MIME类型
     * - X-Upload-Token: 上传令牌
     * - Content-Length: 文件大小
     */
    public function upload(RequestInterface $request, string $path): ResponseInterface
    {
        try {
            // 解码路径
            $relativePath = urldecode($path);

            // 获取上传令牌
            $token = $request->getHeaderLine('X-Upload-Token');
            if (empty($token)) {
                return $this->error('缺少上传令牌', null, 400);
            }

            // 获取Content-Type
            $contentType = $request->getHeaderLine('Content-Type');
            
            // 判断是文件上传还是状态更新通知（S3上传后）
            $isStatusUpdate = str_contains($contentType, 'application/json');
            
            if ($isStatusUpdate) {
                // S3 上传后的状态更新通知
                $body = json_decode($request->getBody()->getContents(), true);
                $fileUrl = $body['file_url'] ?? null;
                
                if (empty($fileUrl)) {
                    return $this->error('缺少文件URL', null, 400);
                }
                
                // 更新文件上传状态
                $file = $this->uploadService->markFileAsUploaded($token, $fileUrl);
            } else {
                // 本地存储的文件上传
                if (empty($contentType)) {
                    return $this->error('缺少Content-Type', null, 400);
                }

                // 获取文件大小
                $contentLength = $request->getHeaderLine('Content-Length');
                $fileSize = !empty($contentLength) ? (int) $contentLength : 0;

                // 验证令牌
                $params = [
                    'content_type' => $contentType,
                    'file_size' => $fileSize,
                ];

                if (!$this->uploadService->verifyUploadToken($token, $params)) {
                    return $this->error('上传令牌无效或已过期', null, 401);
                }

                // 从文件记录获取存储驱动（优先从数据库记录获取，确保与实际使用的驱动一致）
                $file = AdminUploadFile::where('upload_token', $token)->first();
                $driver = $file?->storage_driver ?? null;

                // 如果文件记录中没有驱动信息，从站点配置或系统配置获取
                if ($driver === null) {
                    $currentSite = \site();
                    $driver = $currentSite?->getUploadDriver() ?? $this->config->get('upload.driver', 'local');
                }

                // 如果是本地存储，保存文件
                if ($driver === 'local') {
                    $this->saveLocalFile($relativePath, $request->getBody()->getContents());
                }
                // S3存储由客户端直接上传到S3，服务器端不需要处理

                // 获取文件访问URL
                $fileUrl = $this->uploadService->getFileUrl($relativePath, $driver);

                // 更新文件上传状态
                $file = $this->uploadService->markFileAsUploaded($token, $fileUrl);
            }

            return $this->success([
                'url' => $fileUrl,
                'path' => $relativePath,
                'file_id' => $file ? $file->id : null,
                'token' => $token,
            ], '上传成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), null, 500);
        }
    }

    /**
     * 保存文件到本地存储
     * 文件路径格式：{site_domain}/{admin_id}/images/2024/01/01/xxx.jpg
     * 存储位置：public/uploads/{site_domain}/{admin_id}/images/2024/01/01/xxx.jpg
     */
    private function saveLocalFile(string $relativePath, string $content): void
    {
        // 使用新的存储路径配置（public/uploads）
        $storagePath = $this->config->get('upload.storage_path', BASE_PATH . '/public/uploads');
        
        // 如果是相对路径，转换为绝对路径
        if (!str_starts_with($storagePath, '/')) {
            $storagePath = BASE_PATH . '/' . $storagePath;
        }

        $fullPath = $storagePath . '/' . ltrim($relativePath, '/');

        // 确保目录存在
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 写入文件
        file_put_contents($fullPath, $content);
    }

    /**
     * 获取图片库列表
     * 
     * GET /api/admin/upload/images
     * 
     * 查询参数：
     * - page: 页码（默认：1）
     * - page_size: 每页数量（默认：20）
     * - keyword: 搜索关键词（可选，搜索文件名）
     */
    public function getImageLibrary(RequestInterface $request): ResponseInterface
    {
        try {
            $page = (int) $request->input('page', 1);
            $pageSize = (int) $request->input('page_size', 20);
            $keyword = $request->input('keyword', '');

            // 获取当前站点ID和用户ID
            $siteId = Context::get('site_id');
            $userId = Context::get('admin_user_id');

            // 构建查询
            $query = AdminUploadFile::query()
                ->where('status', AdminUploadFile::STATUS_UPLOADED)
                ->whereIn('content_type', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);

            // 按站点过滤
            if ($siteId) {
                $query->where('site_id', $siteId);
            }

            // 按用户过滤（可选，如果用户ID存在则只显示该用户上传的图片）
            // 注释掉下面这行，让所有用户都能看到所有图片
            // if ($userId) {
            //     $query->where('user_id', $userId);
            // }

            // 关键词搜索
            if (!empty($keyword)) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('original_filename', 'like', "%{$keyword}%")
                      ->orWhere('filename', 'like', "%{$keyword}%");
                });
            }

            // 按创建时间倒序排列
            $query->orderBy('created_at', 'desc');

            // 分页处理：如果 page_size 为 0，返回所有数据
            if ($pageSize > 0) {
                $paginator = $query->paginate($pageSize, ['*'], 'page', $page);
                
                // 格式化数据
                $images = [];
                foreach ($paginator->items() as $file) {
                    $images[] = [
                        'id' => $file->id,
                        'url' => $file->file_url,
                        'filename' => $file->original_filename,
                        'size' => $file->file_size,
                        'size_formatted' => $this->formatFileSize($file->file_size),
                        'content_type' => $file->content_type,
                        'created_at' => $file->created_at ? $file->created_at->format('Y-m-d H:i:s') : null,
                    ];
                }

                return $this->success([
                    'data' => $images,
                    'total' => $paginator->total(),
                    'page' => $paginator->currentPage(),
                    'page_size' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                ], '获取图片库成功');
            } else {
                // page_size 为 0，返回所有数据
                $files = $query->get();
                $total = $files->count();
                
                // 格式化数据
                $images = [];
                foreach ($files as $file) {
                    $images[] = [
                        'id' => $file->id,
                        'url' => $file->file_url,
                        'filename' => $file->original_filename,
                        'size' => $file->file_size,
                        'size_formatted' => $this->formatFileSize($file->file_size),
                        'content_type' => $file->content_type,
                        'created_at' => $file->created_at ? $file->created_at->format('Y-m-d H:i:s') : null,
                    ];
                }

                return $this->success([
                    'data' => $images,
                    'total' => $total,
                    'page' => 1,
                    'page_size' => $total > 0 ? $total : 0,
                    'last_page' => 1,
                ], '获取图片库成功');
            }
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), null, 500);
        }
    }

    /**
     * 删除图片
     * 
     * DELETE /api/admin/upload/images/{id}
     */
    public function deleteImage(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            // 获取当前站点ID和用户ID
            $siteId = Context::get('site_id');
            $userId = Context::get('admin_user_id');

            // 查找文件
            $file = AdminUploadFile::find($id);
            if (!$file) {
                return $this->error('图片不存在', null, 404);
            }

            // 验证权限（只能删除自己站点或自己上传的图片）
            if ($siteId && $file->site_id != $siteId) {
                return $this->error('无权删除此图片', null, 403);
            }

            // 如果是本地存储，删除物理文件
            if ($file->storage_driver === 'local' && $file->file_path) {
                $storagePath = $this->config->get('upload.storage_path', BASE_PATH . '/public/uploads');
                if (!str_starts_with($storagePath, '/')) {
                    $storagePath = BASE_PATH . '/' . $storagePath;
                }
                $fullPath = $storagePath . '/' . ltrim($file->file_path, '/');
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }

            // 软删除记录
            $file->delete();

            return $this->success(null, '删除成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), null, 500);
        }
    }

    /**
     * 确认图片已关联到图库
     * 
     * POST /api/admin/upload/images/confirm
     * 
     * 请求参数：
     * - file_id: 文件ID（可选，优先使用）
     * - token: 上传令牌（可选，file_id不存在时使用）
     */
    public function confirmImageLibrary(RequestInterface $request): ResponseInterface
    {
        try {
            $fileId = $request->input('file_id');
            $token = $request->input('token');

            if (empty($fileId) && empty($token)) {
                return $this->error('文件ID或令牌不能为空', null, 400);
            }

            // 查找文件记录
            $file = null;
            if ($fileId) {
                $file = AdminUploadFile::find($fileId);
            } elseif ($token) {
                $file = AdminUploadFile::where('upload_token', $token)->first();
            }

            if (!$file) {
                return $this->error('文件不存在', null, 404);
            }

            // 验证文件状态
            if ($file->status !== AdminUploadFile::STATUS_UPLOADED) {
                return $this->error('文件尚未上传完成', null, 400);
            }

            // 验证权限（只能确认自己站点的文件）
            $siteId = Context::get('site_id');
            if ($siteId && $file->site_id != $siteId) {
                return $this->error('无权访问此文件', null, 403);
            }

            return $this->success([
                'file_id' => $file->id,
                'url' => $file->file_url,
                'status' => $file->status,
                'message' => '图片已成功关联到图库',
            ], '确认成功');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), null, 500);
        }
    }

    /**
     * 格式化文件大小
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
}


