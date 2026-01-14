<?php

declare(strict_types=1);

namespace Addons\AddonsStore\Model;

use Hyperf\Database\Model\Model;

/**
 * 插件商店版本信息模型
 *
 * @property int $id
 * @property int $addon_id 插件ID
 * @property string $version 版本号
 * @property string|null $filename 文件名
 * @property string|null $filepath 文件路径
 * @property int $filesize 文件大小（字节）
 * @property string|null $checksum 文件校验和
 * @property string|null $changelog 更新日志
 * @property array|null $compatibility 兼容性信息
 * @property int $downloads 下载次数
 * @property int $status 状态：0=禁用，1=启用
 * @property \Carbon\Carbon|null $released_at 发布时间
 * @property string|null $description 版本描述
 * @property array|null $files 文件列表
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AddonsStoreVersion extends Model
{
    /**
     * 表名
     */
    protected ?string $table = 'addons_store_versions';

    /**
     * 状态常量
     */
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'addon_id',
        'version',
        'filename',
        'filepath',
        'filesize',
        'checksum',
        'changelog',
        'compatibility',
        'downloads',
        'status',
        'released_at',
        'description',
        'files',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'addon_id' => 'integer',
        'filesize' => 'integer',
        'downloads' => 'integer',
        'status' => 'integer',
        'compatibility' => 'json',
        'files' => 'json',
        'released_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取关联的插件
     */
    public function addon()
    {
        return $this->belongsTo(AddonsStoreAddon::class, 'addon_id');
    }

    /**
     * 获取下载日志
     */
    public function downloadLogs()
    {
        return $this->hasMany(AddonsStoreDownloadLog::class, 'version_id');
    }

    /**
     * 查询作用域：按插件ID筛选
     */
    public function scopeByAddon($query, int $addonId)
    {
        return $query->where('addon_id', $addonId);
    }

    /**
     * 查询作用域：按版本号筛选
     */
    public function scopeByVersion($query, string $version)
    {
        return $query->where('version', $version);
    }

    /**
     * 查询作用域：按状态筛选
     */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 查询作用域：启用状态
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 查询作用域：禁用状态
     */
    public function scopeDisabled($query)
    {
        return $query->where('status', self::STATUS_DISABLED);
    }

    /**
     * 查询作用域：按发布时间排序（降序）
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('released_at', 'desc');
    }

    /**
     * 查询作用域：按下载量排序（降序）
     */
    public function scopeOrderByDownloads($query)
    {
        return $query->orderBy('downloads', 'desc');
    }

    /**
     * 查询作用域：按文件大小排序
     */
    public function scopeOrderBySize($query, string $direction = 'asc')
    {
        return $query->orderBy('filesize', $direction);
    }

    /**
     * 查询作用域：按版本号排序（语义化版本）
     */
    public function scopeOrderByVersion($query, string $direction = 'desc')
    {
        return $query->orderByRaw("INET_ATON(SUBSTRING_INDEX(CONCAT(version,'.0.0.0'),'.',1)) {$direction},
                                    INET_ATON(SUBSTRING_INDEX(SUBSTRING_INDEX(CONCAT(version,'.0.0.0'),'.',2),'.',-1)) {$direction},
                                    INET_ATON(SUBSTRING_INDEX(SUBSTRING_INDEX(CONCAT(version,'.0.0.0'),'.',3),'.',-1)) {$direction},
                                    INET_ATON(SUBSTRING_INDEX(SUBSTRING_INDEX(CONCAT(version,'.0.0.0'),'.',4),'.',-1)) {$direction}");
    }
}
