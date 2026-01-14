<?php

declare(strict_types=1);

namespace App\Model\AddonsStore;

use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\SoftDeletes;

/**
 * 插件商店插件信息模型
 */
class AddonsStoreAddon extends Model
{
    use SoftDeletes;

    protected ?string $table = 'addons_store_addons';

    protected array $fillable = [
        'name',
        'slug',
        'description',
        'author',
        'version',
        'category',
        'tags',
        'homepage',
        'repository',
        'license',
        'downloads',
        'rating',
        'reviews_count',
        'status',
        'is_official',
        'is_featured',
        'user_id',
    ];

    protected array $casts = [
        'id' => 'integer',
        'downloads' => 'integer',
        'rating' => 'decimal:2',
        'reviews_count' => 'integer',
        'status' => 'integer',
        'is_official' => 'boolean',
        'is_featured' => 'boolean',
        'user_id' => 'integer',
        'tags' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 获取最新版本
     */
    public function latestVersion()
    {
        return $this->hasOne(AddonsStoreVersion::class, 'addon_id')
            ->where('status', 1)
            ->orderBy('released_at', 'desc');
    }

    /**
     * 获取所有版本
     */
    public function versions()
    {
        return $this->hasMany(AddonsStoreVersion::class, 'addon_id')
            ->where('status', 1)
            ->orderBy('released_at', 'desc');
    }

    /**
     * 获取评价
     */
    public function reviews()
    {
        return $this->hasMany(AddonsStoreReview::class, 'addon_id')
            ->where('status', 1)
            ->orderBy('created_at', 'desc');
    }

    /**
     * 获取下载日志
     */
    public function downloadLogs()
    {
        return $this->hasMany(AddonsStoreDownloadLog::class, 'addon_id');
    }
}

