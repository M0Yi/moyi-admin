<?php

declare(strict_types=1);

namespace App\Model\AddonsStore;

use Hyperf\Database\Model\Model;

/**
 * 插件商店下载日志模型
 */
class AddonsStoreDownloadLog extends Model
{
    protected ?string $table = 'addons_store_download_logs';

    protected array $fillable = [
        'addon_id',
        'version_id',
        'user_id',
        'user_ip',
        'user_agent',
        'referer',
        'version',
    ];

    protected array $casts = [
        'id' => 'integer',
        'addon_id' => 'integer',
        'version_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * 获取关联的插件
     */
    public function addon()
    {
        return $this->belongsTo(AddonsStoreAddon::class, 'addon_id');
    }

    /**
     * 获取关联的版本
     */
    public function version()
    {
        return $this->belongsTo(AddonsStoreVersion::class, 'version_id');
    }
}

