<?php

declare(strict_types=1);

namespace Addons\AddonsStore\Service;

use Addons\AddonsStore\Model\AddonsStoreAddon;
use Addons\AddonsStore\Model\AddonsStoreVersion;
use Addons\AddonsStore\Model\AddonsStoreDownloadLog;
use Addons\AddonsStore\Model\AddonsStoreReview;
use App\Service\Admin\AddonService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Upload\UploadedFile;
use function Hyperf\Config\config;
use function Hyperf\Support\now;
use function Hyperf\Support\today;

/**
 * 插件商店服务类
 */
class AddonsStoreService
{
    #[Inject]
    protected AddonService $addonService;
    /**
     * 获取插件列表
     */
    public function getAddonList(array $params = []): array
    {
        $query = AddonsStoreAddon::query()
            ->with(['latestVersion']);

        // 使用查询作用域进行搜索和筛选
        if (!empty($params['keyword'])) {
            $query->byKeyword($params['keyword']);
        }

        if (!empty($params['name'])) {
            $query->byName($params['name']);
        }

        if (!empty($params['author'])) {
            $query->byAuthor($params['author']);
        }

        if (!empty($params['addon_id'])) {
            $query->where('id', (int) $params['addon_id']);
        }

        if (!empty($params['identifier'])) {
            $query->byIdentifier($params['identifier']);
        }

        if (!empty($params['category'])) {
            $query->byCategory($params['category']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->byStatus((int) $params['status']);
        }

        // 排序
        $sortField = $params['sort'] ?? 'downloads';
        $sortOrder = $params['order'] ?? 'desc';

        switch ($sortField) {
            case 'downloads':
                $query->orderByDownloads($sortOrder);
                break;
            case 'rating':
                $query->orderByRating($sortOrder);
                break;
            case 'updated':
                $query->latest(); // 使用 latest 作用域
                break;
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            default:
                $query->orderByDownloads();
        }

        // 分页
        $perPage = $params['per_page'] ?? 20;
        $addons = $query->paginate($perPage);

        return [
            'data' => $addons->items(),
            'total' => $addons->total(),
            'page' => $addons->currentPage(),
            'per_page' => $addons->perPage(),
            'last_page' => $addons->lastPage(),
        ];
    }

    /**
     * 获取插件详情
     */
    public function getAddonDetail(int $id): ?array
    {
        $addon = AddonsStoreAddon::with(['versions', 'reviews'])
            ->find($id);

        return $addon ? $addon->toArray() : null;
    }

    /**
     * 获取插件信息（包含权限验证）
     */
    public function getAddonInfoById(int $addonId, ?int $userId = null, bool $isAdmin = false): array
    {
        logger()->debug("[插件信息] 开始获取插件信息", [
            'addon_id' => $addonId,
            'user_id' => $userId,
            'is_admin' => $isAdmin
        ]);

        $addon = AddonsStoreAddon::find($addonId);

        if (!$addon) {
            logger()->warning("[插件信息] 插件不存在", [
                'addon_id' => $addonId,
                'user_id' => $userId
            ]);
            throw new \Exception('插件不存在');
        }

        logger()->debug("[插件信息] 插件信息获取成功", [
            'addon_id' => $addonId,
            'addon_name' => $addon->name,
            'addon_slug' => $addon->slug,
            'user_id' => $addon->user_id
        ]);

        // 检查权限（如果提供了用户ID）
        if ($userId !== null && !$isAdmin && $addon->user_id != $userId) {
            logger()->warning("[插件信息] 无权访问此插件", [
                'addon_id' => $addonId,
                'addon_owner' => $addon->user_id,
                'request_user' => $userId,
                'is_admin' => $isAdmin
            ]);
            throw new \Exception('无权访问此插件');
        }

        // 检查插件文件是否存在
        $versions = AddonsStoreVersion::where('addon_id', $addonId)->get();
        $hasValidFiles = false;
        $checkedFiles = 0;

        logger()->debug("[插件信息] 开始检查插件文件", [
            'addon_id' => $addonId,
            'versions_count' => $versions->count()
        ]);

        foreach ($versions as $version) {
            $filePath = BASE_PATH . '/storage/' . $version->filepath;
            $checkedFiles++;

            if (file_exists($filePath)) {
                $hasValidFiles = true;
                logger()->debug("[插件信息] 插件文件存在", [
                    'addon_id' => $addonId,
                    'version_id' => $version->id,
                    'filepath' => $version->filepath
                ]);
                break;
            } else {
                logger()->debug("[插件信息] 插件文件不存在", [
                    'addon_id' => $addonId,
                    'version_id' => $version->id,
                    'filepath' => $version->filepath
                ]);
            }
        }

        if (!$hasValidFiles && !empty($versions)) {
            logger()->warning("[插件信息] 插件文件不存在", [
                'addon_id' => $addonId,
                'addon_name' => $addon->name,
                'checked_files' => $checkedFiles,
                'total_versions' => $versions->count()
            ]);
            throw new \Exception('插件文件不存在');
        }

        logger()->info("[插件信息] 插件信息获取完成", [
            'addon_id' => $addonId,
            'addon_name' => $addon->name,
            'versions_count' => $versions->count(),
            'has_valid_files' => $hasValidFiles
        ]);

        return [
            'addon' => $addon->toArray(),
            'versions' => $versions->toArray(),
            'has_valid_files' => $hasValidFiles,
        ];
    }

    /**
     * 获取插件版本列表
     */
    public function getAddonVersions(int $addonId): array
    {
        $versions = AddonsStoreVersion::byAddon($addonId)
            ->enabled()
            ->latest()
            ->get()
            ->toArray();

        // 获取插件信息以添加当前安装版本
        $addonInfo = $this->getAddonInfoById($addonId);
        $addonIdentifier = $addonInfo['addon']['identifier'] ?? '';

        // 为每个版本添加当前安装版本信息
        foreach ($versions as &$version) {
            if ($addonIdentifier) {
                $localAddon = $this->addonService->getAddonInfoById($addonIdentifier);
                $version['current_version'] = $localAddon['version'] ?? '';
            } else {
                $version['current_version'] = '';
            }
        }

        return $versions;
    }

    /**
     * 获取所有版本列表
     */
    public function getAllVersions(array $params = []): array
    {
        $query = AddonsStoreVersion::query()
            ->with(['addon'])
            ->join('addons_store_addons', 'addons_store_versions.addon_id', '=', 'addons_store_addons.id')
            ->select('addons_store_versions.*', 'addons_store_addons.name as addon_name', 'addons_store_addons.identifier as addon_identifier', 'addons_store_addons.category as addon_category');

        // 使用查询作用域进行搜索和筛选
        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->where('addons_store_addons.name', 'like', '%' . $params['keyword'] . '%')
                  ->orWhere('addons_store_versions.version', 'like', '%' . $params['keyword'] . '%');
            });
        }

        if (!empty($params['addon_name'])) {
            $query->where('addons_store_addons.name', 'like', '%' . $params['addon_name'] . '%');
        }

        if (!empty($params['addon_author'])) {
            $query->where('addons_store_addons.author', 'like', '%' . $params['addon_author'] . '%');
        }

        if (!empty($params['addon_id'])) {
            $query->where('addons_store_versions.addon_id', (int) $params['addon_id']);
        }

        if (!empty($params['identifier'])) {
            $query->where('addons_store_addons.identifier', $params['identifier']);
        }

        if (!empty($params['addon_category'])) {
            $query->where('addons_store_addons.category', $params['addon_category']);
        }

        // 版本状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->byStatus((int) $params['status']);
        }

        // 排序
        $sortField = $params['sort'] ?? 'released_at';
        $sortOrder = $params['order'] ?? 'desc';

        switch ($sortField) {
            case 'version':
                $query->orderByVersion($sortOrder);
                break;
            case 'addon_name':
                $query->orderBy('addons_store_addons.name', $sortOrder);
                break;
            case 'downloads':
                $query->orderByDownloads($sortOrder);
                break;
            case 'released_at':
            default:
                $query->latest(); // 使用 latest 作用域
                break;
        }

        // 分页
        $perPage = $params['per_page'] ?? 15;
        $versions = $query->paginate($perPage);

        // 为每个版本添加当前安装版本信息
        $items = $versions->items();
        foreach ($items as &$item) {
            $addonIdentifier = $item['addon_identifier'] ?? '';
            if ($addonIdentifier) {
                // 通过插件标识符查找本地安装的插件信息
                $localAddon = $this->addonService->getAddonInfoById($addonIdentifier);
                $item['current_version'] = $localAddon['version'] ?? '';
            } else {
                $item['current_version'] = '';
            }
        }

        return [
            'data' => $items,
            'total' => $versions->total(),
            'page' => $versions->currentPage(),
            'per_page' => $versions->perPage(),
            'last_page' => $versions->lastPage(),
        ];
    }

    /**
     * 获取插件评价
     */
    public function getAddonReviews(int $addonId): array
    {
        return AddonsStoreReview::where('addon_id', $addonId)
            ->where('status', 1)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 获取分类列表
     */
    public function getCategories(): array
    {
        return [
            'general' => '通用',
            'admin' => '管理后台',
            'api' => 'API工具',
            'content' => '内容管理',
            'commerce' => '电子商务',
            'communication' => '通信',
            'development' => '开发工具',
            'media' => '媒体',
            'security' => '安全',
            'social' => '社交',
            'utility' => '实用工具',
        ];
    }

    /**
     * 上传并处理插件安装包
     */
    public function uploadAndProcessAddon(UploadedFile $file): array
    {
        logger()->info("[插件上传] 开始处理插件安装包", [
            'filename' => $file->getClientFilename(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType()
        ]);

        // 验证文件
        $this->validateAddonPackage($file);

        // 创建临时目录
        $tempDir = $this->createTempDirectory();

        try {
            // 解压文件
            $extractedPath = $this->extractAddonPackage($file, $tempDir);

            // 解析插件信息
            $addonInfo = $this->parseAddonInfo($extractedPath);

            // 检查插件是否已存在
            $existingAddon = AddonsStoreAddon::where('name', $addonInfo['name'])->first();

            if ($existingAddon) {
                // 插件已存在，创建新版本
                assert($existingAddon instanceof \Addons\AddonsStore\Model\AddonsStoreAddon);
                $result = $this->createAddonVersion($existingAddon, $addonInfo, $extractedPath, $file);
                $message = '插件版本上传成功';
            } else {
                // 插件不存在，创建新插件
                $result = $this->createNewAddon($addonInfo, $extractedPath, $file);
                $message = '新插件上传成功';
            }

            return array_merge($result, ['message' => $message]);

        } finally {
            // 清理临时文件
            $this->cleanupTempDirectory($tempDir);
        }
    }

    /**
     * 验证插件安装包
     */
    private function validateAddonPackage(UploadedFile $file): void
    {
        // 检查文件类型
        $allowedMimeTypes = [
            'application/zip',
            'application/x-zip-compressed',
            'application/gzip',
            'application/x-tar'
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new \Exception('不支持的文件类型，请上传 ZIP 或 TAR.GZ 格式的插件包');
        }

        // 检查文件大小（限制为50MB）
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($file->getSize() > $maxSize) {
            throw new \Exception('文件大小不能超过50MB');
        }
    }

    /**
     * 创建临时目录
     */
    private function createTempDirectory(): string
    {
        $tempDir = sys_get_temp_dir() . '/addon_upload_' . uniqid();
        if (!mkdir($tempDir, 0755, true)) {
            throw new \Exception('无法创建临时目录');
        }
        return $tempDir;
    }

    /**
     * 解压插件安装包
     */
    private function extractAddonPackage(UploadedFile $file, string $tempDir): string
    {
        // 获取上传文件的临时路径
        $uploadedTempPath = $file->getPathname();
        $filePath = $tempDir . '/' . $file->getClientFilename();

        // 复制文件到临时目录，而不是移动
        if (!copy($uploadedTempPath, $filePath)) {
            throw new \Exception('无法复制文件到临时目录');
        }

        $extractedPath = $tempDir . '/extracted';

        if (!mkdir($extractedPath, 0755, true)) {
            throw new \Exception('无法创建解压目录');
        }

        $mimeType = $file->getMimeType();

        if (str_contains($mimeType, 'zip')) {
            // ZIP 文件
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $zip->extractTo($extractedPath);
                $zip->close();
            } else {
                throw new \Exception('无法解压 ZIP 文件');
            }
        } elseif (str_contains($mimeType, 'gzip') || str_contains($mimeType, 'tar')) {
            // TAR.GZ 文件
            $phar = new \PharData($filePath);
            $phar->extractTo($extractedPath);
        } else {
            throw new \Exception('不支持的压缩格式');
        }

        return $extractedPath;
    }

    /**
     * 解析插件信息
     */
    private function parseAddonInfo(string $extractedPath): array
    {
        // 查找 info.php 文件
        $infoFile = $this->findFile($extractedPath, 'info.php');

        if (!$infoFile) {
            throw new \Exception('插件包中缺少 info.php 文件');
        }

        // 读取并解析 info.php
        $infoContent = file_get_contents($infoFile);
        if ($infoContent === false) {
            throw new \Exception('无法读取插件信息文件');
        }

        // 使用简单的正则表达式解析 PHP 文件
        $addonInfo = $this->parseInfoFile($infoContent);

        // 验证必要字段
        $requiredFields = ['id', 'name', 'version', 'description', 'author'];
        foreach ($requiredFields as $field) {
            if (!isset($addonInfo[$field]) || empty($addonInfo[$field])) {
                throw new \Exception("插件信息文件缺少必要字段: {$field}");
            }
        }

        // 插件标识符ID就是 id 字段
        $addonInfo['identifier'] = $addonInfo['id'];

        return $addonInfo;
    }

    /**
     * 查找文件
     */
    private function findFile(string $directory, string $filename): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * 解析 info.php 文件内容
     */
    private function parseInfoFile(string $content): array
    {
        $info = [];

        // 使用正则表达式提取 return 语句中的数组
        if (preg_match('/return\s*\[(.*?)];/s', $content, $matches)) {
            $arrayContent = $matches[1];

            // 提取键值对
            if (preg_match_all("/'([^']+)'\s*=>\s*('([^']*)'|\"([^\"]*)\"|[^,\s]+),?/", $arrayContent, $pairs)) {
                foreach ($pairs[1] as $index => $key) {
                    $value = $pairs[3][$index] ?: $pairs[4][$index] ?: $pairs[2][$index];
                    $info[$key] = trim($value, "'\"");
                }
            }
        }

        return $info;
    }

    /**
     * 创建新插件
     */
    private function createNewAddon(array $addonInfo, string $extractedPath, UploadedFile $file): array
    {
        // 生成插件标识（slug）
        $slug = $this->generateSlug($addonInfo['name']);

        // 从插件包中读取标识符ID
        $identifier = $addonInfo['identifier'];

        // 验证标识符唯一性
        if (AddonsStoreAddon::where('identifier', $identifier)->exists()) {
            throw new \Exception("插件标识符ID '{$identifier}' 已存在，请使用不同的标识符");
        }

        logger()->info("[新插件创建] 开始创建新插件", [
            'addon_name' => $addonInfo['name'],
            'addon_version' => $addonInfo['version'],
            'generated_slug' => $slug,
            'read_identifier' => $identifier,
            'slug_type' => gettype($slug),
            'filename' => $file->getClientFilename()
        ]);

        // 保存插件文件
        $packagePath = $this->saveAddonPackage($file, $slug);

        // 创建插件记录
        $addon = AddonsStoreAddon::create([
            'name' => $addonInfo['name'],
            'slug' => $slug,
            'identifier' => $identifier,
            'description' => $addonInfo['description'] ?? '',
            'author' => $addonInfo['author'],
            'category' => $addonInfo['category'] ?? 'other',
            'version' => $addonInfo['version'],
            'status' => 1, // 默认启用
            'is_free' => 1, // 默认免费
            'downloads' => 0,
            'rating' => 0,
            'package_path' => $packagePath,
        ]);

        // 获取文件信息
        $filename = basename($packagePath);
        $fullFilePath = BASE_PATH . '/storage/' . $packagePath;
        logger()->debug("[新插件创建] 计算文件路径", [
            'package_path' => $packagePath,
            'full_file_path' => $fullFilePath,
            'file_exists' => file_exists($fullFilePath)
        ]);
        $filesize = filesize($fullFilePath);

        // 创建初始版本记录
        $this->createVersionRecord($addon->id, $addonInfo, $extractedPath, $packagePath, $filesize, $filename);

        return [
            'addon_id' => $addon->id,
            'action' => 'created'
        ];
    }

    /**
     * 创建插件版本
     */
    private function createAddonVersion(AddonsStoreAddon $existingAddon, array $addonInfo, string $extractedPath, UploadedFile $file): array
    {
        logger()->info("[插件版本创建] 开始创建插件版本", [
            'addon_id' => $existingAddon->id,
            'addon_id_type' => gettype($existingAddon->id),
            'addon_name' => $existingAddon->name,
            'new_version' => $addonInfo['version'],
            'filename' => $file->getClientFilename()
        ]);

        // 检查版本是否已存在
        $existingVersion = AddonsStoreVersion::where('addon_id', $existingAddon->id)
            ->where('version', $addonInfo['version'])
            ->first();

        if ($existingVersion) {
            logger()->warning("[插件版本创建] 插件版本已存在", [
                'addon_id' => $existingAddon->id,
                'version' => $addonInfo['version'],
                'existing_version_id' => $existingVersion->id
            ]);
            throw new \Exception("插件版本 {$addonInfo['version']} 已存在");
        }

        logger()->info("[插件版本创建] 调用 saveAddonPackage 方法", [
            'addon_id' => $existingAddon->id,
            'addon_id_type' => gettype($existingAddon->id),
            'filename' => $file->getClientFilename()
        ]);

        // 保存插件文件
        $packagePath = $this->saveAddonPackage($file, $existingAddon->id);

        // 更新插件信息（如果需要）
        $existingAddon->update([
            'description' => $addonInfo['description'] ?? $existingAddon->description,
            'author' => $addonInfo['author'] ?? $existingAddon->author,
            'category' => $addonInfo['category'] ?? $existingAddon->category,
            'package_path' => $packagePath,
        ]);

        // 获取文件信息
        $filename = basename($packagePath);
        $fullFilePath = BASE_PATH . '/storage/' . $packagePath;
        logger()->debug("[插件版本创建] 计算文件路径", [
            'package_path' => $packagePath,
            'full_file_path' => $fullFilePath,
            'file_exists' => file_exists($fullFilePath)
        ]);
        $filesize = filesize($fullFilePath);

        // 创建版本记录
        $this->createVersionRecord($existingAddon->id, $addonInfo, $extractedPath, $packagePath, $filesize, $filename);

        return [
            'addon_id' => $existingAddon->id,
            'action' => 'version_added'
        ];
    }

    /**
     * 保存插件安装包
     */
    private function saveAddonPackage(UploadedFile $file, int|string $addonId): string
    {
        logger()->info("[插件包保存] 开始保存插件安装包", [
            'addon_id' => $addonId,
            'addon_id_type' => gettype($addonId),
            'filename' => $file->getClientFilename(),
            'size' => $file->getSize()
        ]);

        $uploadDir = BASE_PATH . '/storage/app/private/addons/packages';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            logger()->debug("[插件包保存] 创建上传目录", ['upload_dir' => $uploadDir]);
        }

        $filename = $addonId . '_' . time() . '_' . $file->getClientFilename();
        logger()->debug("[插件包保存] 生成文件名", [
            'addon_id' => $addonId,
            'filename' => $filename
        ]);
        $filePath = $uploadDir . '/' . $filename;

        // 从原始上传位置复制文件，而不是移动
        $uploadedTempPath = $file->getPathname();
        logger()->debug("[插件包保存] 复制文件", [
            'from' => $uploadedTempPath,
            'to' => $filePath
        ]);

        if (!copy($uploadedTempPath, $filePath)) {
            logger()->error("[插件包保存] 文件复制失败", [
                'from' => $uploadedTempPath,
                'to' => $filePath
            ]);
            throw new \Exception('无法保存插件安装包');
        }

        logger()->info("[插件包保存] 插件安装包保存成功", [
            'addon_id' => $addonId,
            'filepath' => 'app/private/addons/packages/' . $filename,
            'full_path' => $filePath,
            'file_size' => filesize($filePath)
        ]);

        return 'app/private/addons/packages/' . $filename;
    }

    /**
     * 生成插件标识
     */
    private function generateSlug(string $name): string
    {
        // 将中文转换为拼音或英文标识
        $slug = preg_replace('/[^\w\-]+/u', '-', strtolower($name));
        $slug = trim($slug, '-');

        // 如果 slug 为空，使用时间戳
        if (empty($slug)) {
            $slug = 'addon_' . time();
        }

        // 确保唯一性
        $originalSlug = $slug;
        $counter = 1;
        while (AddonsStoreAddon::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '_' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * 创建版本记录
     */
    private function createVersionRecord(int $addonId, array $addonInfo, string $extractedPath, string $filepath, int $filesize, string $filename): void
    {
        // 查找 Controller 目录
        $controllerPath = $this->findDirectory($extractedPath, 'Controller');
        $files = $controllerPath ? $this->listDirectoryFiles($controllerPath) : [];

        // 查找 Service 目录
        $servicePath = $this->findDirectory($extractedPath, 'Service');
        $serviceFiles = $servicePath ? $this->listDirectoryFiles($servicePath) : [];

        $checksumPath = BASE_PATH . '/storage/' . $filepath;
        logger()->debug("[版本记录创建] 计算文件校验和", [
            'filepath' => $filepath,
            'checksum_path' => $checksumPath,
            'file_exists' => file_exists($checksumPath)
        ]);

        AddonsStoreVersion::create([
            'addon_id' => $addonId,
            'version' => $addonInfo['version'],
            'description' => $addonInfo['description'] ?? '',
            'filename' => $filename,
            'filepath' => $filepath,
            'filesize' => $filesize,
            'checksum' => hash_file('sha256', $checksumPath),
            'changelog' => $addonInfo['changelog'] ?? 'Initial release',
            'compatibility' => isset($addonInfo['compatibility']) ? json_encode($addonInfo['compatibility']) : null,
            'downloads' => 0,
            'status' => 1,
            'released_at' => now(),
        ]);
    }

    /**
     * 查找目录
     */
    private function findDirectory(string $basePath, string $dirName): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isDir() && $file->getFilename() === $dirName) {
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * 列出目录中的文件
     */
    private function listDirectoryFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = str_replace($directory . '/', '', $file->getPathname());
            }
        }

        return $files;
    }

    /**
     * 清理临时目录
     */
    private function cleanupTempDirectory(string $tempDir): void
    {
        if (is_dir($tempDir)) {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * 递归删除目录
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }

    /**
     * 获取表单字段配置
     */
    public function getFormFields(string $scene = 'create', ?array $addon = null): array
    {
        $categories = $this->getCategories();

        $fields = [
            [
                'name' => 'name',
                'label' => '插件名称',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入插件名称',
                'default' => $addon['name'] ?? '',
                'col' => 'col-12 col-md-6',
                'help' => '插件的显示名称',
            ],
            [
                'name' => 'identifier',
                'label' => '标识符ID',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入标识符ID',
                'default' => $addon['identifier'] ?? '',
                'col' => 'col-12 col-md-6',
                'help' => '插件的唯一标识符，由插件包的id字段决定',
                'disabled' => true,
                'class' => 'text-monospace',
            ],
            [
                'name' => 'version',
                'label' => '版本号',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：1.0.0',
                'default' => $addon['version'] ?? '1.0.0',
                'col' => 'col-12 col-md-6',
                'help' => '使用语义化版本号格式',
            ],
            [
                'name' => 'author',
                'label' => '作者',
                'type' => 'text',
                'required' => false,
                'placeholder' => '请输入作者姓名',
                'default' => $addon['author'] ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'category',
                'label' => '分类',
                'type' => 'select',
                'required' => true,
                'options' => array_map(function($key, $value) {
                    return ['value' => $key, 'label' => $value];
                }, array_keys($categories), $categories),
                'placeholder' => '请选择分类',
                'default' => $addon['category'] ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'description',
                'label' => '描述',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => '请输入插件描述',
                'default' => $addon['description'] ?? '',
                'col' => 'col-12',
                'rows' => 3,
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $addon['status'] ?? '1',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'is_free',
                'label' => '是否免费',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $addon['is_free'] ?? '1',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'price',
                'label' => '价格',
                'type' => 'number',
                'required' => false,
                'placeholder' => '0.00',
                'default' => $addon['price'] ?? '',
                'col' => 'col-12 col-md-6',
                'step' => '0.01',
                'min' => '0',
                'depends' => [
                    'field' => 'is_free',
                    'value' => '0',
                ],
            ],
        ];

        return $fields;
    }

    /**
     * 获取用户插件列表
     */
    public function getUserAddons(int $userId): array
    {
        return AddonsStoreAddon::where('user_id', $userId)
            ->with(['latestVersion'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 上传插件
     */
    public function uploadAddon(array $data, $files): array
    {
        Db::beginTransaction();

        try {
            // 处理文件上传
            $uploadedFile = $files[0] ?? null;
            if (!$uploadedFile) {
                throw new \Exception('请选择要上传的文件');
            }

            // 验证文件
            $this->validateUploadedFile($uploadedFile);

            // 生成文件名和路径
            $filename = $this->generateFilename($uploadedFile);
            $filepath = $this->storeFile($uploadedFile, $filename);

            // 创建插件记录
            $addon = new AddonsStoreAddon();
            $addon->fill([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $this->generateSlug($data['name']),
                'description' => $data['description'] ?? '',
                'author' => $data['author'] ?? 'Unknown',
                'version' => $data['version'] ?? '1.0.0',
                'category' => $data['category'] ?? 'general',
                'tags' => isset($data['tags']) ? json_encode($data['tags']) : null,
                'homepage' => $data['homepage'] ?? null,
                'repository' => $data['repository'] ?? null,
                'license' => $data['license'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'status' => config('addons_store.auto_review', false) ? 1 : 0,
            ]);
            $addon->save();

            // 创建版本记录
            $version = new AddonsStoreVersion();
            $version->fill([
                'addon_id' => $addon->id,
                'version' => $addon->version,
                'filename' => $filename,
                'filepath' => $filepath,
                'filesize' => $uploadedFile->getSize(),
                'checksum' => hash_file('sha256', BASE_PATH . '/storage/' . $filepath),
                'changelog' => $data['changelog'] ?? 'Initial release',
                'compatibility' => isset($data['compatibility']) ? json_encode($data['compatibility']) : null,
                'released_at' => now(),
            ]);
            $version->save();

            Db::commit();

            return [
                'addon' => $addon->toArray(),
                'version' => $version->toArray(),
            ];

        } catch (\Exception $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 更新插件
     */
    public function updateAddon(int $id, array $data): array
    {
        logger()->info('[AddonsStore] updateAddon 开始更新', [
            'addon_id' => $id,
            'update_data' => $data
        ]);

        $addon = AddonsStoreAddon::findOrFail($id);

        logger()->info('[AddonsStore] updateAddon 获取到插件', [
            'addon_id' => $id,
            'current_data' => $addon->toArray()
        ]);

        // 处理可以更新的字段
        $fillableData = [];

        // 基础字段
        if (isset($data['name'])) $fillableData['name'] = $data['name'];
        // identifier 字段不由编辑表单修改，由插件包的id字段决定
        if (isset($data['description'])) $fillableData['description'] = $data['description'];
        if (isset($data['category'])) $fillableData['category'] = $data['category'];
        if (isset($data['author'])) $fillableData['author'] = $data['author'];
        if (isset($data['version'])) $fillableData['version'] = $data['version'];
        if (isset($data['homepage'])) $fillableData['homepage'] = $data['homepage'];
        if (isset($data['repository'])) $fillableData['repository'] = $data['repository'];
        if (isset($data['license'])) $fillableData['license'] = $data['license'];

        // 状态字段
        if (isset($data['status'])) $fillableData['status'] = (int) $data['status'];
        if (isset($data['is_official'])) $fillableData['is_official'] = (bool) $data['is_official'];
        if (isset($data['is_featured'])) $fillableData['is_featured'] = (bool) $data['is_featured'];
        if (isset($data['is_free'])) $fillableData['is_free'] = (bool) $data['is_free'];

        // 标签字段
        if (isset($data['tags'])) {
            $fillableData['tags'] = is_array($data['tags']) ? json_encode($data['tags']) : $data['tags'];
        }

        logger()->info('[AddonsStore] updateAddon 准备更新字段', [
            'addon_id' => $id,
            'fillable_data' => $fillableData
        ]);

        $addon->fill($fillableData);
        $result = $addon->save();

        logger()->info('[AddonsStore] updateAddon 保存结果', [
            'addon_id' => $id,
            'save_result' => $result,
            'updated_data' => $addon->toArray()
        ]);

        return $addon->toArray();
    }

    /**
     * 删除插件
     */
    public function deleteAddon(int $id, ?int $userId = null, bool $isAdmin = false): void
    {
        logger()->info("[插件删除] 开始删除插件", [
            'addon_id' => $id,
            'user_id' => $userId,
            'is_admin' => $isAdmin
        ]);

        // 获取插件信息并验证权限
        $addonInfo = $this->getAddonInfoById($id, $userId, $isAdmin);
        $addon = $addonInfo['addon'];

        logger()->info("[插件删除] 获取插件信息成功", [
            'addon_id' => $id,
            'addon_name' => $addon['name'],
            'addon_slug' => $addon['slug']
        ]);

        // 检查是否为系统插件，不允许删除
        if ($this->isSystemAddon($addon)) {
            logger()->warning("[插件删除] 尝试删除系统插件，已阻止", [
                'addon_id' => $id,
                'addon_slug' => $addon['slug']
            ]);
            throw new \Exception('系统插件不允许删除');
        }

        // 删除相关文件
        $versions = AddonsStoreVersion::where('addon_id', $id)->get();
        $deletedFilesCount = 0;

        logger()->info("[插件删除] 开始删除插件文件", [
            'addon_id' => $id,
            'versions_count' => $versions->count()
        ]);

        foreach ($versions as $version) {
            $filePath = BASE_PATH . '/storage/' . $version->filepath;
            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    $deletedFilesCount++;
                    logger()->debug("[插件删除] 删除文件成功", [
                        'addon_id' => $id,
                        'version_id' => $version->id,
                        'filepath' => $version->filepath
                    ]);
                } else {
                    logger()->warning("[插件删除] 删除文件失败", [
                        'addon_id' => $id,
                        'version_id' => $version->id,
                        'filepath' => $version->filepath
                    ]);
                }
            } else {
                logger()->debug("[插件删除] 文件不存在，跳过删除", [
                    'addon_id' => $id,
                    'version_id' => $version->id,
                    'filepath' => $version->filepath
                ]);
            }
        }

        logger()->info("[插件删除] 文件删除完成", [
            'addon_id' => $id,
            'total_files' => $versions->count(),
            'deleted_files' => $deletedFilesCount
        ]);

        // 删除数据库记录
        logger()->info("[插件删除] 开始删除数据库记录", ['addon_id' => $id]);

        $deletedVersions = AddonsStoreVersion::where('addon_id', $id)->delete();
        $deletedLogs = AddonsStoreDownloadLog::where('addon_id', $id)->delete();
        $deletedReviews = AddonsStoreReview::where('addon_id', $id)->delete();

        logger()->info("[插件删除] 数据库记录删除完成", [
            'addon_id' => $id,
            'deleted_versions' => $deletedVersions,
            'deleted_logs' => $deletedLogs,
            'deleted_reviews' => $deletedReviews
        ]);

        // 删除插件记录（软删除）
        AddonsStoreAddon::where('id', $id)->delete();

        logger()->info("[插件删除] 插件删除完成", [
            'addon_id' => $id,
            'addon_name' => $addon['name'],
            'addon_slug' => $addon['slug']
        ]);
    }

    /**
     * 检查是否为系统插件
     */
    private function isSystemAddon(array $addon): bool
    {
        // 系统插件列表
        $systemAddons = [
            'addons_store',  // 插件商店本身
        ];

        return in_array($addon['slug'], $systemAddons);
    }

    /**
     * 将插件ID转换为目录名（首字母大写，下划线后首字母大写）
     * 例如: addons_store -> AddonsStore, user_manager -> UserManager
     */
    public static function addonIdToDirName(string $addonId): string
    {
        return str_replace('_', '', ucwords($addonId, '_'));
    }

    /**
     * 将目录名转换为插件ID（大写转小写，加下划线）
     * 例如: AddonsStore -> addons_store, UserManager -> user_manager
     */
    public static function dirNameToAddonId(string $dirName): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $dirName));
    }

    /**
     * 创建插件
     */
    public function createAddon(array $data): array
    {
        $addon = AddonsStoreAddon::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $this->generateSlug($data['name']),
            'description' => $data['description'] ?? '',
            'version' => $data['version'] ?? '1.0.0',
            'author' => $data['author'] ?? '未知',
            'category' => $data['category'] ?? 'other',
            'status' => $data['status'] ?? 1,
            'is_free' => $data['is_free'] ?? true,
            'price' => $data['price'] ?? 0,
            'file_path' => $data['file_path'] ?? null,
            'file_size' => $data['file_size'] ?? 0,
            'downloads' => 0,
            'rating' => 0,
            'review_count' => 0,
        ]);

        return $addon->toArray();
    }

    /**
     * 根据ID获取插件
     */
    public function getAddonById(int $id): ?array
    {
        $addon = AddonsStoreAddon::find($id);
        return $addon ? $addon->toArray() : null;
    }

    /**
     * 根据ID获取版本信息
     */
    public function getVersionById(int $versionId): ?array
    {
        $version = AddonsStoreVersion::find($versionId);
        return $version ? $version->toArray() : null;
    }


    /**
     * 下载插件
     */
    public function downloadAddon(int $addonId, string $version = null): array
    {
        $query = AddonsStoreVersion::where('addon_id', $addonId)->where('status', 1);

        if ($version) {
            $query->where('version', $version);
        } else {
            $query->orderBy('released_at', 'desc');
        }

        $versionRecord = $query->firstOrFail();

        // 记录下载日志
        if (config('addons_store.enable_download_stats', true)) {
            $this->logDownload($addonId, $versionRecord->id, $versionRecord->version);
        }

        // 更新下载次数
        $versionRecord->increment('downloads');
        AddonsStoreAddon::where('id', $addonId)->increment('downloads');

        return [
            'filepath' => $versionRecord->filepath,
            'filename' => $versionRecord->filename,
            'version' => $versionRecord->version,
        ];
    }

    /**
     * 获取下载统计
     */
    public function getDownloadStats(): array
    {
        $totalDownloads = AddonsStoreDownloadLog::count();
        $todayDownloads = AddonsStoreDownloadLog::whereDate('created_at', today())->count();
        $monthDownloads = AddonsStoreDownloadLog::whereMonth('created_at', now()->month)->count();

        $popularAddons = AddonsStoreAddon::select('id', 'name', 'downloads')
            ->orderBy('downloads', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'total' => $totalDownloads,
            'today' => $todayDownloads,
            'month' => $monthDownloads,
            'popular_addons' => $popularAddons,
        ];
    }

    /**
     * 验证上传文件
     */
    private function validateUploadedFile(UploadedFile $file): void
    {
        // 检查文件大小
        $maxSize = config('addons_store.max_file_size', 50) * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            throw new \Exception('文件大小超过限制');
        }

        // 检查文件类型
        $allowedExtensions = explode(',', config('addons_store.allowed_extensions', 'zip,tar.gz,tar.bz2'));
        $extension = strtolower($file->getExtension());

        if (!in_array($extension, array_map('strtolower', $allowedExtensions))) {
            throw new \Exception('不支持的文件类型');
        }
    }

    /**
     * 生成文件名
     */
    private function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getExtension();
        return uniqid('addon_', true) . '.' . $extension;
    }

    /**
     * 存储文件
     */
    private function storeFile(UploadedFile $file, string $filename): string
    {
        $storagePath = config('addons_store.storage_path', 'addons_store');
        $directory = 'app/' . $storagePath . '/' . date('Y/m/d');

        $fullDirectory = BASE_PATH . '/storage/' . $directory;

        if (!is_dir($fullDirectory)) {
            mkdir($fullDirectory, 0755, true);
        }

        $filepath = $directory . '/' . $filename;
        $file->moveTo(BASE_PATH . '/storage/' . $filepath);

        return $filepath;
    }

    /**
     * 记录下载日志
     */
    private function logDownload(int $addonId, int $versionId, string $version): void
    {
        $clientIp = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        AddonsStoreDownloadLog::create([
            'addon_id' => $addonId,
            'version_id' => $versionId,
            'user_id' => null, // 可以从会话中获取
            'user_ip' => $clientIp,
            'user_agent' => $userAgent,
            'referer' => $referer,
            'version' => $version,
        ]);
    }

    /**
     * 获取客户端IP
     */
    private function getClientIp(): string
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

}
