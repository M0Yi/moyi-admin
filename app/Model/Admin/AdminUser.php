<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;
use Hyperf\Database\Model\SoftDeletes;
use HyperfExtension\Auth\Contracts\AuthenticatableInterface;
use HyperfExtension\Jwt\Contracts\JwtSubjectInterface;

/**
 * @property int $id
 * @property int|null $site_id
 * @property string $username
 * @property string $password
 * @property string $email
 * @property string $mobile
 * @property string $avatar
 * @property string $real_name
 * @property int $status
 * @property int $is_admin
 * @property string $last_login_ip
 * @property Carbon $last_login_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 */
class AdminUser extends Model implements AuthenticatableInterface,JwtSubjectInterface
{
    use SoftDeletes;
    use \HyperfExtension\Auth\Authenticatable;
    public function getJwtIdentifier()
    {
        return $this->getKey();
    }

    /**
     * JWT自定义载荷
     * @return array
     */
    public function getJwtCustomClaims(): array
    {
        return [
            'guard' => 'users'    // 添加一个自定义载荷保存守护名称，方便后续判断
        ];
    }

    /**
     * 表名
     */
    protected ?string $table = 'admin_users';

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'site_id',
        'username',
        'password',
        'email',
        'mobile',
        'avatar',
        'real_name',
        'status',
        'is_admin',
        'last_login_ip',
        'last_login_at',
    ];

    /**
     * 隐藏的属性
     */
    protected array $hidden = [
        'password',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'status' => 'integer',
        'is_admin' => 'integer',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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
            'admin_role_user',
            'user_id',
            'role_id'
        )->withTimestamps();
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
     * 密码访问器
     */
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * 验证密码
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * 检查是否有指定权限
     */
    public function hasPermission(string $permission): bool
    {
        // 超级管理员拥有所有权限
        if ($this->is_admin) {
            return true;
        }

        // 通过角色检查权限
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('slug', $permission)
                    ->where('status', 1);
            })
            ->where('status', 1)
            ->exists();
    }

    /**
     * 检查是否有指定角色
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where('slug', $role)
            ->where('status', 1)
            ->exists();
    }
}

