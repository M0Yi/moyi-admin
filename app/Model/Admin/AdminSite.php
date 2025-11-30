<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;
use Hyperf\Database\Model\SoftDeletes;

/**
 * 站点模型
 *
 * @property int $id 站点ID
 * @property string $domain 域名
 * @property string $admin_entry_path 后台入口路径
 * @property string $name 站点名称
 * @property string|null $title 站点标题
 * @property string|null $slogan 站点口号
 * @property string|null $logo Logo路径
 * @property string|null $favicon Favicon路径
 * @property string|null $description 站点描述
 * @property string|null $keywords SEO关键词
 * @property string|null $contact_email 联系邮箱
 * @property string|null $contact_phone 联系电话
 * @property string|null $address 地址
 * @property string|null $icp_number ICP备案号
 * @property string|null $analytics_code 统计代码
 * @property string|null $custom_css 自定义CSS
 * @property string|null $custom_js 自定义JavaScript
 * @property string|null $resource_cdn 资源CDN地址
 * @property array|null $config 扩展配置(JSON)
 * @property string|null $upload_driver 上传驱动类型（local/s3），null则使用系统默认
 * @property array|null $upload_config 上传配置JSON，包含S3密钥、本地存储路径等配置
 * @property int|null $default_brand_id 默认品牌ID
 * @property int|null $default_wechat_provider_id 默认微信服务商ID
 * @property int $status 状态：0=禁用，1=启用
 * @property int $sort 排序
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 * @property Carbon|null $deleted_at 删除时间
 */
class AdminSite extends Model
{
    use SoftDeletes;

    /**
     * 表名（必须使用 admin_ 前缀）
     */
    protected ?string $table = 'admin_sites';

    /**
     * 主键类型
     */
    protected string $keyType = 'int';

    /**
     * 是否自增主键
     */
    public bool $incrementing = true;

    /**
     * 是否自动维护时间戳
     */
    public bool $timestamps = true;

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'domain',
        'admin_entry_path',
        'name',
        'title',
        'slogan',
        'logo',
        'favicon',
        'description',
        'keywords',
        'contact_email',
        'contact_phone',
        'address',
        'icp_number',
        'analytics_code',
        'custom_css',
        'custom_js',
        'resource_cdn',
        'config',
        'upload_driver',
        'upload_config',
        'default_brand_id',
        'default_wechat_provider_id',
        'status',
        'sort',
    ];

    /**
     * 隐藏的属性
     */
    protected array $hidden = [];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'config' => 'array',
        'upload_config' => 'array',
        'default_brand_id' => 'integer',
        'default_wechat_provider_id' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 状态常量
     */
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;

    /**
     * 获取所有可用状态
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DISABLED => '禁用',
            self::STATUS_ENABLED => '启用',
        ];
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? '未知';
    }

    /**
     * 查询作用域：启用状态
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 查询作用域：禁用状态
     */
    public function scopeDisabled($query)
    {
        return $query->where('status', self::STATUS_DISABLED);
    }

    /**
     * 查询作用域：根据域名查找
     */
    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    /**
     * 查询作用域：排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort', 'asc')->orderBy('id', 'asc');
    }

    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 检查是否禁用
     */
    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    /**
     * 启用站点
     */
    public function enable(): bool
    {
        return $this->update(['status' => self::STATUS_ENABLED]);
    }

    /**
     * 禁用站点
     */
    public function disable(): bool
    {
        return $this->update(['status' => self::STATUS_DISABLED]);
    }

    /**
     * 生成随机后台入口路径
     *
     * @param int $length 随机字符串长度
     * @return string
     */
    public static function generateRandomAdminPath(int $length = 16): string
    {
        // 生成安全的随机字符串
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $random = '';

        for ($i = 0; $i < $length; $i++) {
            $random .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $random;
    }

    /**
     * 验证后台入口路径格式
     *
     * @param string $path
     * @return bool
     */
    public static function isValidAdminPath(string $path): bool
    {
        // 必须以 / 开头
        if (!str_starts_with($path, '/')) {
            return false;
        }

        // 只允许字母、数字、连字符、下划线
        if (!preg_match('/^\/[a-zA-Z0-9\-_]+$/', $path)) {
            return false;
        }

        // 长度限制：5-100 字符
        if (strlen($path) < 5 || strlen($path) > 100) {
            return false;
        }

        return true;
    }

    /**
     * 根据后台入口路径查找站点
     *
     * @param string $path
     * @return AdminSite|null
     */
    public static function findByAdminPath(string $path): ?AdminSite
    {
        return self::query()
            ->where('admin_entry_path', $path)
            ->where('status', self::STATUS_ENABLED)
            ->first();
    }

    /**
     * 获取上传驱动类型
     * 如果站点未配置，返回null（使用系统默认配置）
     *
     * @return string|null
     */
    public function getUploadDriver(): ?string
    {
        return $this->upload_driver;
    }

    /**
     * 获取上传配置
     * 如果站点未配置，返回null（使用系统默认配置）
     *
     * @return array|null
     */
    public function getUploadConfig(): ?array
    {
        return $this->upload_config;
    }

    /**
     * 获取S3配置
     * 如果站点未配置S3配置，返回null（使用系统默认配置）
     *
     * @return array|null
     */
    public function getS3Config(): ?array
    {
        if (!$this->upload_config || !isset($this->upload_config['s3'])) {
            return null;
        }

        return $this->upload_config['s3'];
    }

    /**
     * 获取本地存储配置
     * 如果站点未配置本地存储配置，返回null（使用系统默认配置）
     *
     * @return array|null
     */
    public function getLocalStorageConfig(): ?array
    {
        if (!$this->upload_config || !isset($this->upload_config['local'])) {
            return null;
        }

        return $this->upload_config['local'];
    }

    /**
     * 检查是否配置了上传信息
     *
     * @return bool
     */
    public function hasUploadConfig(): bool
    {
        return $this->upload_driver !== null || $this->upload_config !== null;
    }

    /**
     * 获取主题配置
     * 主题配置存储在 config JSON 字段的 theme 键中
     *
     * @return array
     */
    public function getThemeConfig(): array
    {
        $config = $this->config ?? [];
        $theme = $config['theme'] ?? [];

        // 默认主题配置
        $defaults = [
            'primary_color' => '#6366f1',
            'secondary_color' => '#8b5cf6',
            'primary_gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'primary_hover' => '#764ba2',
            'success_color' => '#10b981',
            'warning_color' => '#f59e0b',
            'danger_color' => '#ef4444',
            'info_color' => '#3b82f6',
            'light_color' => '#f8f9fa',
            'dark_color' => '#1f2937',
            'border_color' => '#e5e7eb',
        ];

        // 合并用户配置和默认值
        return array_merge($defaults, $theme);
    }

    /**
     * 获取主题颜色值
     *
     * @param string $key 颜色键名
     * @param string|null $default 默认值
     * @return string
     */
    public function getThemeColor(string $key, ?string $default = null): string
    {
        $theme = $this->getThemeConfig();
        return $theme[$key] ?? $default ?? '';
    }

    /**
     * 设置主题配置
     *
     * @param array $themeConfig 主题配置数组
     * @return void
     */
    public function setThemeConfig(array $themeConfig): void
    {
        $config = $this->config ?? [];
        $config['theme'] = $themeConfig;
        $this->config = $config;
    }
}


