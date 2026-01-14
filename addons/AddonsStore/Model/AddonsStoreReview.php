<?php

declare(strict_types=1);

namespace Addons\AddonsStore\Model;

use Hyperf\Database\Model\Model;

/**
 * 插件商店评价模型
 */
class AddonsStoreReview extends Model
{
    protected ?string $table = 'addons_store_reviews';

    protected array $fillable = [
        'addon_id',
        'user_id',
        'rating',
        'comment',
        'status',
    ];

    protected array $casts = [
        'id' => 'integer',
        'addon_id' => 'integer',
        'user_id' => 'integer',
        'rating' => 'integer',
        'status' => 'integer',
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
     * 获取关联的用户
     */
    public function user()
    {
        return $this->belongsTo(\App\Model\AdminUser::class, 'user_id');
    }
}
