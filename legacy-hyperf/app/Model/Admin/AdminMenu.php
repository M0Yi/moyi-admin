<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
use Carbon\Carbon;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\HasMany;

/**
 * 后台菜单模型
 *
 * @property int $id
 * @property int $site_id 站点ID
 * @property int $parent_id 父级ID
 * @property string $name 菜单名称
 * @property string $title 菜单标题
 * @property string $icon 菜单图标
 * @property string $path 路由路径
 * @property string $component 组件路径
 * @property string $redirect 重定向路径
 * @property string $type 类型
 * @property string $target 打开方式
 * @property string $badge 徽章文本
 * @property string $badge_type 徽章类型
 * @property string $permission 权限标识
 * @property int $visible 是否可见
 * @property int $status 状态
 * @property int $sort 排序
 * @property int $cache 是否缓存
 * @property array $config 扩展配置
 * @property string $remark 备注
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read AdminMenu $parent 父级菜单
 * @property-read AdminMenu[] $children 子菜单
 * @property-read AdminSite $site 所属站点
 */
class AdminMenu extends Model
{
    /**
     * 表名
     */
    protected ?string $table = 'admin_menus';

    /**
     * 可批量赋值的属性
     */
    protected array $fillable = [
        'site_id',
        'parent_id',
        'name',
        'title',
        'icon',
        'path',
        'component',
        'redirect',
        'type',
        'target',
        'badge',
        'badge_type',
        'permission',
        'visible',
        'status',
        'sort',
        'cache',
        'config',
        'remark',
    ];

    /**
     * 类型转换
     */
    protected array $casts = [
        'id' => 'integer',
        'site_id' => 'integer',
        'parent_id' => 'integer',
        'visible' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
        'cache' => 'integer',
        'config' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 菜单类型常量
    public const TYPE_MENU = 'menu';        // 菜单
    public const TYPE_LINK = 'link';        // 外链
    public const TYPE_GROUP = 'group';      // 分组
    public const TYPE_DIVIDER = 'divider';  // 分割线

    // 打开方式常量
    public const TARGET_SELF = '_self';     // 当前窗口
    public const TARGET_BLANK = '_blank';   // 新窗口

    // 徽章类型常量
    public const BADGE_PRIMARY = 'primary';
    public const BADGE_SUCCESS = 'success';
    public const BADGE_WARNING = 'warning';
    public const BADGE_DANGER = 'danger';
    public const BADGE_INFO = 'info';

    /**
     * 关联父级菜单
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 关联子菜单
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort', 'asc');
    }

    /**
     * 关联所属站点
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(AdminSite::class, 'site_id');
    }

    /**
     * 查询作用域：启用状态
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * 查询作用域：可见
     */
    public function scopeVisible($query)
    {
        return $query->where('visible', 1);
    }

    /**
     * 查询作用域：顶级菜单
     */
    public function scopeTopLevel($query)
    {
        return $query->where('parent_id', 0);
    }

    /**
     * 查询作用域：指定站点
     */
    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * 查询作用域：全局菜单
     */
    public function scopeGlobal($query)
    {
        return $query->where('site_id', 0);
    }

    /**
     * 查询作用域：按排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * 获取完整路径（包含父级路径）
     */
    public function getFullPath(): string
    {
        if ($this->parent_id && $this->parent) {
            return rtrim($this->parent->getFullPath(), '/') . '/' . ltrim($this->path, '/');
        }

        return $this->path ?? '';
    }

    /**
     * 获取所有祖先菜单（从顶级到直接父级）
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($ancestors, $parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * 获取所有后代菜单（递归）
     */
    public function getDescendants(): array
    {
        $descendants = [];

        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getDescendants());
        }

        return $descendants;
    }

    /**
     * 检查是否有子菜单
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * 检查是否是顶级菜单
     */
    public function isTopLevel(): bool
    {
        return $this->parent_id === 0;
    }

    /**
     * 检查是否是某个菜单的子孙
     */
    public function isDescendantOf(self $menu): bool
    {
        $parent = $this->parent;

        while ($parent) {
            if ($parent->id === $menu->id) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }

    /**
     * 获取菜单层级深度（顶级为0）
     */
    public function getDepth(): int
    {
        $depth = 0;
        $parent = $this->parent;

        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    /**
     * 获取配置项
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        if (empty($this->config)) {
            return $default;
        }

        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 设置配置项
     */
    public function setConfig(string $key, mixed $value): void
    {
        $config = $this->config ?? [];

        $keys = explode('.', $key);
        $current = &$config;

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
        $this->config = $config;
    }

    /**
     * 构建树形结构
     * 注意：菜单已解耦站点，全局共享
     *
     * @param int $parentId 父级ID
     * @return array
     */
    public static function getTree(int $parentId = 0): array
    {
        $query = self::query()
            ->active()
            ->visible()
            ->ordered()
            ->where('parent_id', $parentId);

        $menus = $query->get();

        $tree = [];
        foreach ($menus as $menu) {
            $item = $menu->toArray();
            $children = self::getTree($menu->id);

            if (!empty($children)) {
                $item['children'] = $children;
            }

            $tree[] = $item;
        }

        return $tree;
    }

    /**
     * 获取扁平化菜单列表（带层级缩进）
     * 注意：菜单已解耦站点，全局共享
     *
     * @param int $parentId 父级ID
     * @param int $level 层级
     * @param string $prefix 前缀字符
     * @return array
     */
    public static function getList(
        int $parentId = 0,
        int $level = 0,
        string $prefix = '　'
    ): array {
        $query = self::query()
            ->active()
            ->ordered()
            ->where('parent_id', $parentId);

        $menus = $query->get();

        $list = [];
        foreach ($menus as $menu) {
            $item = $menu->toArray();
            $item['level'] = $level;
            $item['title_indent'] = str_repeat($prefix, $level) . $item['title'];

            $list[] = $item;

            // 递归获取子菜单
            $children = self::getList($menu->id, $level + 1, $prefix);
            if (!empty($children)) {
                $list = array_merge($list, $children);
            }
        }

        return $list;
    }

    /**
     * 从扁平数组构建树形结构
     *
     * @param array $items 扁平化的菜单数组
     * @param int $parentId 父级ID
     * @return array
     */
    public static function buildTree(array $items, int $parentId = 0): array
    {
        $tree = [];

        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = self::buildTree($items, $item['id']);

                if (!empty($children)) {
                    $item['children'] = $children;
                }

                $tree[] = $item;
            }
        }

        return $tree;
    }
}

