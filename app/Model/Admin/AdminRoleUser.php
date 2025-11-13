<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;

/**
 * @property int $id
 * @property int $role_id
 * @property int $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AdminRoleUser extends Model
{
    /**
     * 表名
     */
    protected ?string $table = 'admin_role_user';

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'role_id',
        'user_id',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'role_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联角色
     */
    public function role()
    {
        return $this->belongsTo(AdminRole::class, 'role_id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(AdminUser::class, 'user_id');
    }
}

