<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property int|null $user_id
 * @property string|null $username
 * @property string|null $request_id
 * @property string $error_hash
 * @property string $exception_class
 * @property string|null $error_code
 * @property string $error_message
 * @property string|null $error_file
 * @property int|null $error_line
 * @property string $error_level
 * @property int|null $status_code
 * @property string|null $request_method
 * @property string|null $request_path
 * @property string|null $request_ip
 * @property string|null $user_agent
 * @property array|null $request_query
 * @property array|null $request_body
 * @property array|null $request_headers
 * @property string|null $error_trace
 * @property array|null $context
 * @property int $occurrence_count
 * @property Carbon|null $first_occurred_at
 * @property Carbon|null $last_occurred_at
 * @property int $status
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AdminErrorStatistic extends Model
{
    /**
     * 表名
     */
    protected ?string $table = 'admin_error_statistics';

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'site_id',
        'user_id',
        'username',
        'request_id',
        'error_hash',
        'exception_class',
        'error_code',
        'error_message',
        'error_file',
        'error_line',
        'error_level',
        'status_code',
        'request_method',
        'request_path',
        'request_ip',
        'user_agent',
        'request_query',
        'request_body',
        'request_headers',
        'error_trace',
        'context',
        'occurrence_count',
        'first_occurred_at',
        'last_occurred_at',
        'status',
        'resolved_at',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'user_id' => 'integer',
        'error_line' => 'integer',
        'status_code' => 'integer',
        'request_query' => 'array',
        'request_body' => 'array',
        'request_headers' => 'array',
        'context' => 'array',
        'occurrence_count' => 'integer',
        'first_occurred_at' => 'datetime',
        'last_occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}


