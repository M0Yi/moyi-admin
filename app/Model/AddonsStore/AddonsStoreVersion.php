<?php

declare(strict_types=1);

namespace App\Model\AddonsStore;

use Hyperf\Database\Model\Model;

/**
 * 插件商店版本信息模型
 */
class AddonsStoreVersion extends Model
{
    protected ?string $table = 'addons_store_versions';

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
    ];

    protected array $casts = [
        'id' => 'integer',
        'addon_id' => 'integer',
        'filesize' => 'integer',
        'downloads' => 'integer',
        'status' => 'integer',
        'compatibility' => 'json',
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
}

