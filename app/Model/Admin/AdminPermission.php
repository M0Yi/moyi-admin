<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;

/**
 * @property int $id
 * @property int|null $site_id
 * @property int $parent_id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string $icon
 * @property string $path
 * @property string $component
 * @property string $description
 * @property int $status
 * @property int $sort
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AdminPermission extends Model
{
    /**
     * 表名
     */
    protected ?string $table = 'admin_permissions';

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'site_id',
        'parent_id',
        'name',
        'slug',
        'type',
        'icon',
        'path',
        'component',
        'description',
        'status',
        'sort',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'parent_id' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联站点
     */
    public function site()
    {
        return $this->belongsTo(AdminSite::class, 'site_id', 'id');
    }

    /**
     * 关联角色
     */
    public function roles()
    {
        return $this->belongsToMany(
            AdminRole::class,
            'admin_permission_role',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }

    /**
     * 关联父级权限
     */
    public function parent()
    {
        return $this->belongsTo(AdminPermission::class, 'parent_id');
    }

    /**
     * 关联子级权限
     */
    public function children()
    {
        return $this->hasMany(AdminPermission::class, 'parent_id');
    }

    /**
     * 查询作用域：指定站点
     */
    public function scopeBySite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
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

    /**
     * 查询作用域：菜单类型
     */
    public function scopeMenu($query)
    {
        return $query->where('type', 'menu');
    }

    /**
     * 查询作用域：按钮类型
     */
    public function scopeButton($query)
    {
        return $query->where('type', 'button');
    }

    /**
     * 查询作用域：顶级权限
     */
    public function scopeTopLevel($query)
    {
        return $query->where('parent_id', 0);
    }

    /**
     * 构建树形结构
     */
    public static function buildTree(array $permissions = null, int $parentId = 0): array
    {
        if ($permissions === null) {
            $permissions = self::active()->ordered()->get()->toArray();
        }

        $tree = [];
        foreach ($permissions as $permission) {
            if ($permission['parent_id'] == $parentId) {
                $children = self::buildTree($permissions, $permission['id']);
                if ($children) {
                    $permission['children'] = $children;
                }
                $tree[] = $permission;
            }
        }

        return $tree;
    }
}

