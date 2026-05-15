<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;
use Hyperf\Database\Model\SoftDeletes;
use function Hyperf\Support\now;

/**
 * 文件上传管理模型
 *
 * @property int $id
 * @property int|null $site_id
 * @property string $upload_token
 * @property int|null $user_id
 * @property string|null $username
 * @property string $original_filename
 * @property string $filename
 * @property string $file_path
 * @property string|null $file_url
 * @property string $content_type
 * @property int $file_size
 * @property string $storage_driver
 * @property int $status
 * @property string|null $violation_reason
 * @property Carbon $token_expire_at
 * @property Carbon|null $uploaded_at
 * @property Carbon|null $checked_at
 * @property int $check_status
 * @property string|null $check_result
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property AdminUser|null $user
 * @property AdminSite|null $site
 */
class AdminUploadFile extends Model
{
    use SoftDeletes;


    /**
     * 表名
     */
    protected ?string $table = 'admin_upload_files';

    /**
     * 状态：待上传
     */
    public const STATUS_PENDING = 0;

    /**
     * 状态：已上传
     */
    public const STATUS_UPLOADED = 1;

    /**
     * 状态：违规
     */
    public const STATUS_VIOLATION = 2;

    /**
     * 状态：已删除
     */
    public const STATUS_DELETED = 3;

    /**
     * 检查状态：未检查
     */
    public const CHECK_STATUS_PENDING = 0;

    /**
     * 检查状态：通过
     */
    public const CHECK_STATUS_PASSED = 1;

    /**
     * 检查状态：违规
     */
    public const CHECK_STATUS_VIOLATION = 2;

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'site_id',
        'upload_token',
        'user_id',
        'username',
        'original_filename',
        'filename',
        'file_path',
        'file_url',
        'content_type',
        'file_size',
        'storage_driver',
        'status',
        'violation_reason',
        'token_expire_at',
        'uploaded_at',
        'checked_at',
        'check_status',
        'check_result',
        'ip_address',
        'user_agent',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'user_id' => 'integer',
        'file_size' => 'integer',
        'status' => 'integer',
        'check_status' => 'integer',
        'token_expire_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'checked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'check_result' => 'array', // JSON字段自动转换为数组
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(AdminUser::class, 'user_id', 'id');
    }

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
     * 查询作用域：待上传（令牌未过期）
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('token_expire_at', '>', now());
    }

    /**
     * 查询作用域：令牌已过期
     */
    public function scopeExpired($query)
    {
        return $query->where('token_expire_at', '<=', now());
    }

    /**
     * 查询作用域：已上传
     */
    public function scopeUploaded($query)
    {
        return $query->where('status', self::STATUS_UPLOADED);
    }

    /**
     * 查询作用域：违规
     */
    public function scopeViolation($query)
    {
        return $query->where('status', self::STATUS_VIOLATION);
    }

    /**
     * 查询作用域：需要检查（令牌已过期且未上传）
     */
    public function scopeNeedCheck($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('token_expire_at', '<=', now());
    }

    /**
     * 标记为已上传
     */
    public function markAsUploaded(): void
    {
        $this->status = self::STATUS_UPLOADED;
        $this->uploaded_at = now();
        $this->save();
    }

    /**
     * 标记为违规
     */
    public function markAsViolation(string $reason, array $checkResult = []): void
    {
        $this->status = self::STATUS_VIOLATION;
        $this->check_status = self::CHECK_STATUS_VIOLATION;
        $this->violation_reason = $reason;
        $this->check_result = $checkResult;
        $this->checked_at = now();
        $this->save();
    }

    /**
     * 标记检查通过
     */
    public function markAsPassed(array $checkResult = []): void
    {
        $this->check_status = self::CHECK_STATUS_PASSED;
        $this->check_result = $checkResult;
        $this->checked_at = now();
        $this->save();
    }

    /**
     * 判断令牌是否已过期
     */
    public function isTokenExpired(): bool
    {
        return $this->token_expire_at <= now();
    }

    /**
     * 判断文件是否已上传
     */
    public function isUploaded(): bool
    {
        return $this->status === self::STATUS_UPLOADED;
    }

    /**
     * 判断是否为违规文件
     */
    public function isViolation(): bool
    {
        return $this->status === self::STATUS_VIOLATION;
    }
}

