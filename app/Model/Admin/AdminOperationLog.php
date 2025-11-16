<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int|null $site_id
 * @property int $user_id
 * @property string $username
 * @property string $method
 * @property string $path
 * @property string $ip
 * @property string $user_agent
 * @property array $params
 * @property array $response
 * @property int $status_code
 * @property int $duration
 * @property Carbon $created_at
 */
class AdminOperationLog extends Model
{
    /**
     * 表名
     */
    protected ?string $table = 'admin_operation_logs';

    /**
     * 不使用 updated_at 字段
     */
    public const UPDATED_AT = null;

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'site_id',
        'user_id',
        'username',
        'method',
        'path',
        'ip',
        'user_agent',
        'params',
        'response',
        'status_code',
        'duration',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'user_id' => 'integer',
        'params' => 'array',
        'response' => 'array',
        'status_code' => 'integer',
        'duration' => 'integer',
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
     * 查询作用域：按用户筛选
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 查询作用域：按路径筛选
     */
    public function scopeByPath($query, string $path)
    {
        return $query->where('path', 'like', "%{$path}%");
    }

    /**
     * 查询作用域：按方法筛选
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', strtoupper($method));
    }

    /**
     * 查询作用域：按日期范围筛选
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}

