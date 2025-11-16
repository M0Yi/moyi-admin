<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $permission_id
 * @property int $role_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AdminPermissionRole extends Model
{
    /**
     * 表名
     */
    protected ?string $table = 'admin_permission_role';

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'permission_id',
        'role_id',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'permission_id' => 'integer',
        'role_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联权限
     */
    public function permission()
    {
        return $this->belongsTo(AdminPermission::class, 'permission_id');
    }

    /**
     * 关联角色
     */
    public function role()
    {
        return $this->belongsTo(AdminRole::class, 'role_id');
    }
}

