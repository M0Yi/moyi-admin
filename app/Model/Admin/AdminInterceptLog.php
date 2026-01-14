<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int|null $site_id
 * @property string|null $admin_entry_path
 * @property string $method
 * @property string $path
 * @property string $ip
 * @property array $ip_list
 * @property string $user_agent
 * @property array $params
 * @property string $intercept_type
 * @property string $reason
 * @property int $status_code
 * @property int $duration
 * @property Carbon $created_at
 */
class AdminInterceptLog extends Model
{
    /**
     * 表名
     */
    protected ?string $table = 'admin_intercept_logs';

    /**
     * 不使用 updated_at 字段
     */
    public const UPDATED_AT = null;

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'site_id',
        'admin_entry_path',
        'method',
        'path',
        'ip',
        'ip_list',
        'user_agent',
        'params',
        'intercept_type',
        'reason',
        'status_code',
        'duration',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'ip_list' => 'array',
        'params' => 'array',
        'status_code' => 'integer',
        'duration' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * 拦截类型常量
     */
    public const TYPE_404 = '404';
    public const TYPE_INVALID_PATH = 'invalid_path';
    public const TYPE_UNAUTHORIZED = 'unauthorized';

    /**
     * 关联站点
     */
    public function site()
    {
        return $this->belongsTo(AdminSite::class, 'site_id', 'id');
    }

    /**
     * 查询作用域：指定站点
     */
    public function scopeBySite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * 查询作用域：按后台入口筛选
     */
    public function scopeByAdminEntry($query, string $adminEntryPath)
    {
        return $query->where('admin_entry_path', $adminEntryPath);
    }

    /**
     * 查询作用域：按拦截类型筛选
     */
    public function scopeByInterceptType($query, string $type)
    {
        return $query->where('intercept_type', $type);
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
     * 查询作用域：按IP筛选
     */
    public function scopeByIp($query, string $ip)
    {
        return $query->where('ip', $ip);
    }

    /**
     * 查询作用域：按日期范围筛选
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * 查询作用域：按状态码筛选
     */
    public function scopeByStatusCode($query, int $statusCode)
    {
        return $query->where('status_code', $statusCode);
    }
}


