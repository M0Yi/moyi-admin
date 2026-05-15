<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property int $status
 * @property int $sort
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AdminRole extends Model
{
    /**
     * 表名
     */
    protected ?string $table = 'admin_roles';

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'sort',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联用户
     */
    public function users()
    {
        return $this->belongsToMany(
            AdminUser::class,
            'admin_role_user',
            'role_id',
            'user_id'
        )->withTimestamps();
    }

    /**
     * 关联权限
     */
    public function permissions()
    {
        return $this->belongsToMany(
            AdminPermission::class,
            'admin_permission_role',
            'role_id',
            'permission_id'
        )->withTimestamps();
    }

    /**
     * 查询作用域：启用状态
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 查询作用域：按排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort', 'asc')->orderBy('id', 'asc');
    }
}

