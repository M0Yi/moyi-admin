<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\Admin\BaseModelCrudController;
use App\Model\Admin\AdminUploadFile;
use App\Service\Admin\FileUploadService;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function Hyperf\Support\now;

/**
 * 文件管理控制器
 */
class UploadFileController extends BaseModelCrudController
{
    #[Inject]
    protected FileUploadService $fileUploadService;

    #[Inject]
    protected ConfigInterface $config;

    protected function getModelClass(): string
    {
        return AdminUploadFile::class;
    }

    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        return [];
    }

    /**
     * 列表页面
     */
    public function index(RequestInterface $request): ResponseInterface
    {
        // 如果是 AJAX 请求，返回 JSON 数据
        if ($request->input('_ajax') === '1' || $request->input('format') === 'json') {
            return $this->listData($request);
        }

        $searchFields = ['original_filename', 'filename', 'username', 'content_type', 'status', 'check_status'];
        $fields = [
            [
                'name' => 'original_filename',
                'label' => '原始文件名',
                'type' => 'text',
                'placeholder' => '请输入原始文件名',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'filename',
                'label' => '文件名',
                'type' => 'text',
                'placeholder' => '请输入文件名',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'username',
                'label' => '上传用户',
                'type' => 'text',
                'placeholder' => '请输入用户名',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'content_type',
                'label' => '文件类型',
                'type' => 'text',
                'placeholder' => '请输入文件类型',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'select',
                'options' => [
                    ['value' => AdminUploadFile::STATUS_PENDING, 'label' => '待上传'],
                    ['value' => AdminUploadFile::STATUS_UPLOADED, 'label' => '已上传'],
                    ['value' => AdminUploadFile::STATUS_VIOLATION, 'label' => '违规'],
                    ['value' => AdminUploadFile::STATUS_DELETED, 'label' => '已删除'],
                ],
                'placeholder' => '请选择状态',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'check_status',
                'label' => '审核状态',
                'type' => 'select',
                'options' => [
                    ['value' => AdminUploadFile::CHECK_STATUS_PENDING, 'label' => '待审核'],
                    ['value' => AdminUploadFile::CHECK_STATUS_PASSED, 'label' => '通过'],
                    ['value' => AdminUploadFile::CHECK_STATUS_VIOLATION, 'label' => '违规'],
                ],
                'placeholder' => '请选择审核状态',
                'col' => 'col-12 col-md-3',
            ],
        ];

        if (is_super_admin()) {
            $searchFields[] = 'site_id';
            $fields[] = [
                'name' => 'site_id',
                'label' => '所属站点',
                'type' => 'select',
                'options' => $this->getSiteFilterOptions(),
                'placeholder' => '请选择站点',
                'col' => 'col-12 col-md-3',
            ];
        }

        $searchConfig = [
            'search_fields' => $searchFields,
            'fields' => $fields,
        ];

        return $this->renderAdmin('admin.system.upload-file.index', [
            'searchConfig' => $searchConfig,
        ]);
    }

    /**
     * 获取列表查询构建器
     * 添加关联查询以获取用户和站点信息
     */
    protected function getListQuery()
    {
        $query = parent::getListQuery();
        
        // 添加关联查询
        $query->with(['user', 'site']);

        return $query;
    }

    /**
     * 获取可搜索字段列表
     */
    protected function getSearchableFields(): array
    {
        return ['original_filename', 'filename', 'username', 'content_type', 'upload_token'];
    }

    /**
     * 获取可排序字段列表
     */
    protected function getSortableFields(): array
    {
        return ['id', 'created_at', 'updated_at', 'uploaded_at', 'file_size', 'status', 'check_status'];
    }

    /**
     * 获取每页数量
     */
    protected function getPageSize(): int
    {
        return 20;
    }

    /**
     * 格式化列表数据
     * 重写基类方法以添加文件特定的格式化逻辑
     */
    protected function formatListData(array $data): array
    {
        $formattedData = [];
        foreach ($data as $item) {
            $itemArray = is_array($item) ? $item : $item->toArray();
            
            // 格式化文件大小
            if (isset($itemArray['file_size'])) {
                $itemArray['file_size_formatted'] = $this->formatFileSize($itemArray['file_size']);
            }
            
            // 格式化状态文本
            if (isset($itemArray['status'])) {
                $itemArray['status_text'] = $this->getStatusText($itemArray['status']);
            }
            
            // 格式化审核状态文本
            if (isset($itemArray['check_status'])) {
                $itemArray['check_status_text'] = $this->getCheckStatusText($itemArray['check_status']);
            }
            
            $formattedData[] = $itemArray;
        }

        return $formattedData;
    }

    /**
     * 上传页面
     */
    public function create(RequestInterface $request): ResponseInterface
    {
        return $this->renderAdmin('admin.system.upload-file.create');
    }

    /**
     * 获取上传凭证
     * 
     * POST /system/upload-files/token
     * 
     * 请求参数：
     * - filename: 文件名（必需）
     * - content_type: 文件MIME类型（必需）
     * - file_size: 文件大小，字节（必需）
     * - sub_path: 子路径（可选，默认：files）
     * - driver: 存储驱动（可选，默认：从配置读取）
     */
    public function getUploadToken(RequestInterface $request): ResponseInterface
    {
        try {
            // 验证参数
            $validator = $this->validatorFactory->make($request->all(), [
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
            $subPath = $request->input('sub_path', 'files');
            $driver = $request->input('driver'); // 可选，null时使用默认配置

            // 获取上传凭证（传入request用于记录用户信息和IP）
            $token = $this->fileUploadService->getUploadToken(
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
            logger()->error('[UploadFileController] 获取上传凭证失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('获取上传凭证失败：' . $e->getMessage(), null, 500);
        }
    }

    /**
     * 处理文件上传（PUT方法）
     * 
     * PUT /system/upload-files/upload/{path}
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

                if (!$this->fileUploadService->verifyUploadToken($token, $params)) {
                    return $this->error('上传令牌无效或已过期', null, 401);
                }

                // 获取存储驱动（从配置读取）
                $driver = $this->config->get('upload.driver', 'local');

                // 保存文件到本地
                $this->saveLocalFile($relativePath, $request->getBody()->getContents());

                // 获取文件访问URL
                $fileUrl = $this->fileUploadService->getFileUrl($relativePath, $driver);
            }

            // 更新文件上传状态
            $file = $this->fileUploadService->markFileAsUploaded($token, $fileUrl);

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
     * 查看详情页面
     */
    public function show(RequestInterface $request, int $id): ResponseInterface
    {
        $file = AdminUploadFile::with(['user', 'site'])->find($id);
        
        if (!$file) {
            return $this->error('文件不存在', code: 404);
        }
        
        // 格式化数据
        $fileData = $file->toArray();
        $fileData['file_size_formatted'] = $this->formatFileSize($fileData['file_size'] ?? 0);
        $fileData['status_text'] = $this->getStatusText($fileData['status'] ?? 0);
        $fileData['check_status_text'] = $this->getCheckStatusText($fileData['check_status'] ?? 0);
        
        return $this->renderAdmin('admin.system.upload-file.show', [
            'file' => $file,
            'fileData' => $fileData,
        ]);
    }

    /**
     * 文件预览页面
     */
    public function preview(RequestInterface $request, int $id): ResponseInterface
    {
        $file = AdminUploadFile::with(['user', 'site'])->find($id);
        
        if (!$file) {
            return $this->error('文件不存在', code: 404);
        }
        
        if (!$file->file_url) {
            return $this->error('文件URL不存在', code: 404);
        }
        
        // 格式化数据
        $fileData = $file->toArray();
        $fileData['file_size_formatted'] = $this->formatFileSize($fileData['file_size'] ?? 0);
        $fileData['status_text'] = $this->getStatusText($fileData['status'] ?? 0);
        $fileData['check_status_text'] = $this->getCheckStatusText($fileData['check_status'] ?? 0);
        
        return $this->renderAdmin('admin.system.upload-file.preview', [
            'file' => $file,
            'fileData' => $fileData,
        ]);
    }

    /**
     * 编辑页面（文件不支持编辑）
     */
    public function edit(RequestInterface $request, int $id): ResponseInterface
    {
        return $this->error('文件不支持编辑');
    }

    /**
     * 更新数据（文件不支持编辑）
     */
    public function update(RequestInterface $request, int $id): ResponseInterface
    {
        return $this->error('文件不支持编辑');
    }

    /**
     * 审核页面
     */
    public function check(RequestInterface $request, int $id): ResponseInterface
    {
        // POST 请求：处理审核提交
        if ($request->getMethod() === 'POST') {
            return $this->doCheck($request, $id);
        }
        
        // GET 请求：显示审核页面
        $file = AdminUploadFile::with(['user', 'site'])->find($id);
        
        if (!$file) {
            return $this->error('文件不存在', code: 404);
        }
        
        // 获取审核状态参数（如果通过 URL 传递）
        $checkStatus = (int) $request->input('status', 0);
        
        return $this->renderAdmin('admin.system.upload-file.check', [
            'file' => $file,
            'checkStatus' => $checkStatus,
        ]);
    }

    /**
     * 执行审核操作
     */
    private function doCheck(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $file = AdminUploadFile::find($id);
            
            if (!$file) {
                return $this->error('文件不存在', code: 404);
            }
            
            $checkStatus = (int) $request->input('check_status');
            $checkResult = $request->input('check_result', '');
            
            // 验证审核状态
            $allowedStatuses = [
                AdminUploadFile::CHECK_STATUS_PASSED,
                AdminUploadFile::CHECK_STATUS_VIOLATION,
            ];
            
            if (!in_array($checkStatus, $allowedStatuses, true)) {
                return $this->error('无效的审核状态');
            }
            
            if (empty($checkResult)) {
                return $this->error('审核意见不能为空');
            }
            
            // 更新审核状态
            $file->check_status = $checkStatus;
            $file->check_result = $checkResult;
            $file->checked_at = now();
            
            // 如果审核为违规，同时更新文件状态
            if ($checkStatus === AdminUploadFile::CHECK_STATUS_VIOLATION) {
                $file->status = AdminUploadFile::STATUS_VIOLATION;
                $file->violation_reason = $checkResult;
            } elseif ($file->status === AdminUploadFile::STATUS_VIOLATION) {
                // 如果之前是违规状态，审核通过后恢复为已上传
                $file->status = AdminUploadFile::STATUS_UPLOADED;
                $file->violation_reason = null;
            }
            
            $file->save();
            
            $statusText = $checkStatus === AdminUploadFile::CHECK_STATUS_PASSED ? '通过' : '违规';
            
            // 如果是 iframe 模式，返回成功消息并关闭窗口
            if ($request->input('_iframe') === '1') {
                return $this->success([
                    'id' => $file->id,
                    'refresh_parent' => true,
                    'close_current' => true,
                ], "审核{$statusText}成功");
            }
            
            return $this->success(['id' => $file->id], "审核{$statusText}成功");
        } catch (\Throwable $e) {
            logger()->error('[UploadFileController] 审核文件失败', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error($e->getMessage());
        }
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
     * 获取状态文本
     */
    private function getStatusText(int $status): string
    {
        return match ($status) {
            AdminUploadFile::STATUS_PENDING => '待上传',
            AdminUploadFile::STATUS_UPLOADED => '已上传',
            AdminUploadFile::STATUS_VIOLATION => '违规',
            AdminUploadFile::STATUS_DELETED => '已删除',
            default => '未知',
        };
    }

    /**
     * 获取审核状态文本
     */
    private function getCheckStatusText(int $checkStatus): string
    {
        return match ($checkStatus) {
            AdminUploadFile::CHECK_STATUS_PENDING => '待审核',
            AdminUploadFile::CHECK_STATUS_PASSED => '通过',
            AdminUploadFile::CHECK_STATUS_VIOLATION => '违规',
            default => '未知',
        };
    }

    /**
     * 获取站点过滤选项
     * 与 UserController 保持一致
     */
    private function getSiteFilterOptions(): array
    {
        return \App\Model\Admin\AdminSite::query()
            ->where('status', 1)
            ->orderBy('id', 'asc')
            ->get(['id', 'name'])
            ->map(function ($site) {
                return [
                    'value' => (string) $site->id,
                    'label' => $site->name . " (#{$site->id})",
                ];
            })
            ->toArray();
    }
}

