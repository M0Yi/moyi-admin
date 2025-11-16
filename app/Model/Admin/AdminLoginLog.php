<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;
use function Hyperf\Support\now;

/**
 * @property int $id
 * @property int|null $site_id
 * @property int $user_id
 * @property string $username
 * @property string $ip
 * @property string $user_agent
 * @property int $status
 * @property string $message
 * @property Carbon $created_at
 */
class AdminLoginLog extends Model
{
    /**
     * 表名
     */
    protected ?string $table = 'admin_login_logs';

    /**
     * 不使用 updated_at 字段
     */
    public const UPDATED_AT = null;

    /**
     * 状态常量
     */
    public const STATUS_FAILED = 0;
    public const STATUS_SUCCESS = 1;

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'site_id',
        'user_id',
        'username',
        'ip',
        'user_agent',
        'status',
        'message',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'user_id' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * 关联站点
     */
    public function site()
    {
        return $this->belongsTo(AdminSite::class, 'site_id', 'id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(AdminUser::class, 'user_id');
    }

    /**
     * 查询作用域：指定站点
     */
    public function scopeBySite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * 查询作用域：成功的登录
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * 查询作用域：失败的登录
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * 查询作用域：按用户筛选
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 查询作用域：按日期范围筛选
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * 查询作用域：最近的日志
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

