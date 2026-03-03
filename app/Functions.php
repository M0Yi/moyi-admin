<?php

use App\Model\Admin\AdminSite;
use App\Service\Admin\AddonService;
use Hyperf\Contract\StdoutLoggerInterface;
use HyperfExtension\Auth\Contracts\GuardInterface;
use HyperfExtension\Auth\Contracts\StatefulGuardInterface;
use HyperfExtension\Auth\Contracts\StatelessGuardInterface;
use HyperfExtension\Auth\Contracts\AuthManagerInterface;
use Hyperf\Context\Context;
use Psr\Log\LoggerInterface;
use function Hyperf\Config\config;
use function Hyperf\Support\make;

// 全局 PHP 常量：APP_VERSION，优先从配置读取（config/autoload/version.php）
if (! defined('APP_VERSION')) {
    $ver = null;
    // 尝试通过框架的 config() 读取（在多数运行时可用）
    try {
        $ver = config('version.framework_version');
    } catch (\Throwable $e) {
        $ver = null;
    }

    // 如果通过 config() 未能读取到（例如在非常早的 bootstrap 阶段），尝试直接包含配置文件作为后备
    if (empty($ver)) {
        try {
            if (defined('BASE_PATH')) {
                $file = BASE_PATH . '/config/autoload/version.php';
            } else {
                // 如果没有 BASE_PATH，尝试相对路径回退
                $file = __DIR__ . '/../config/autoload/version.php';
            }

            if (file_exists($file)) {
                $cfg = include $file;
                if (is_array($cfg) && isset($cfg['framework_version'])) {
                    $ver = $cfg['framework_version'];
                }
            }
        } catch (\Throwable $e) {
            // 忽略错误，后续会使用默认值
            $ver = $ver ?? null;
        }
    }

    if (! is_string($ver)) {
        $ver = $ver === null ? '' : (string) $ver;
    }

    define('APP_VERSION', $ver !== '' ? $ver : '0.0.0');
}

if (! function_exists('auth')) {
    /**
     * Auth认证辅助方法
     *
     * @param string $guard 守护名称
     *
     * @return GuardInterface|StatefulGuardInterface|StatelessGuardInterface
     */
    function auth(?string $guard = null): StatelessGuardInterface|StatefulGuardInterface|GuardInterface
    {
        if ($guard === null) $guard = config('auth.default.guard') ?? 'web';
        return make(AuthManagerInterface::class)->guard($guard);
    }
}

if (! function_exists('site')) {
    /**
     * 获取当前站点实例
     *
     * @return AdminSite|null
     */
    function site(): ?AdminSite
    {
        $value = Context::get('site');
        return $value instanceof AdminSite ? $value : null;
    }
}

if (! function_exists('site_id')) {
    /**
     * 获取当前站点ID
     *
     * @return int|null
     */
    function site_id(): ?int
    {
        $value = Context::get('site_id');
        if (is_int($value)) return $value;
        if (is_string($value) && is_numeric($value)) return (int) $value;
        return null;
    }
}

if (! function_exists('site_config')) {
    /**
     * 获取当前站点配置项
     *
     * @param string $key 配置键名（支持点号分隔）
     * @param mixed $default 默认值
     * @return mixed
     */
    function site_config(string $key, mixed $default = null): mixed
    {
        $site = site();

        if (! $site || ! $site->config) {
            return $default;
        }

        // 支持点号分隔的键名，如 'theme.color'
        $keys = explode('.', $key);
        $value = $site->config;

        // 兼容字符串/对象配置
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        } elseif (is_object($value)) {
            $value = (array) $value;
        }

        foreach ($keys as $k) {
            if (! is_array($value) || ! array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}


if (! function_exists('admin_route')) {
    /**
     * 生成后台路由路径
     * 自动拼接当前站点的后台入口路径
     *
     * 路由结构：/admin/{adminPath}/xxx
     * - admin_entry_path 存储：'xyz123'（路径标识，不含 /admin 前缀）
     * - 实际访问路径：'/admin/xyz123/dashboard'
     *
     * @param string $path 相对路径（不带前缀），例如 'dashboard' 或 'users/create'
     * @return string 完整的后台路由，例如 '/admin/xyz123/dashboard'
     *
     * @example
     * // 假设 site()->admin_entry_path = 'xyz123'
     * admin_route('dashboard') // 返回 '/admin/xyz123/dashboard'
     * admin_route('users/create') // 返回 '/admin/xyz123/users/create'
     * admin_route('') // 返回 '/admin/xyz123'
     */
    function admin_route(string $path = ''): string
    {
        $site = site();

        // 获取后台入口路径标识（存储格式：'admin', 'xyz123', 'secure' 等）
        $adminPath = $site?->admin_entry_path ?? 'admin';

        // 移除可能存在的前后斜杠
        $adminPath = trim($adminPath, '/');
        if (str_starts_with($adminPath, 'admin/')) {
            $adminPath = substr($adminPath, 6);
        }
        $path = trim($path, '/');

        // 构建完整路径：/admin/{adminPath}/xxx
        $fullPath = '/admin/' . $adminPath;
        $fullPath = rtrim($fullPath, '/');

        // 如果有子路径，追加
        if ($path !== '') {
            $fullPath .= '/' . $path;
        }

        return $fullPath;
    }
}

if (! function_exists('admin_entry_path')) {
    /**
     * 获取当前站点的后台入口完整路径
     * 
     * 注意：此函数是 admin_route('') 的别名，为了向后兼容而保留
     * 建议直接使用 admin_route('') 替代
     *
     * @return string 完整后台入口路径，例如 '/admin/xyz123' 或 '/admin/admin'
     *
     * @example
     * // 假设 site()->admin_entry_path = 'xyz123'
     * admin_entry_path() // 返回 '/admin/xyz123'
     * admin_route('')    // 等价写法
     */
    function admin_entry_path(): string
    {
        return admin_route('');
    }
}

if (! function_exists('csrf_token')) {
    /**
     * 获取CSRF Token
     * 注意：本项目不使用 CSRF 保护，此函数仅用于模板兼容性
     *
     * @return string
     */
    function csrf_token(): string
    {
        // 返回空字符串（不使用 CSRF）
        return '';
    }
}

if (! function_exists('logger')) {
    /**
     * 获取日志记录器实例
     *
     * @param string|null $name 日志通道名称
     * @return LoggerInterface
     */
    function logger(): LoggerInterface
    {
        return make(StdoutLoggerInterface::class);
    }
}

if (! function_exists('is_super_admin')) {
    /**
     * 检查当前登录用户是否是超级管理员（ID为1）
     *
     * @return bool
     */
    function is_super_admin(): bool
    {
        try {
            $userId = auth('admin')->id();
            return (int) $userId === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (! function_exists('is_watcher_running')) {
    /**
     * 检查 Hyperf Watcher 进程是否在运行
     * 用于判断当前是否处于热更新模式
     *
     * @return bool
     */
    function is_watcher_running(): bool
    {
        // 方法1：尝试执行 ps 命令检查是否有 watcher 进程在运行
        // 查找包含 "hyperf.php server:watch" 的进程
        $command = 'ps aux | grep -v grep | grep "hyperf\.php server:watch"';

        try {
            $output = [];
            $returnCode = 0;

            // 使用 exec 执行命令
            exec($command, $output, $returnCode);

            // 如果找到了进程且命令执行成功，返回 true
            if ($returnCode === 0 && !empty($output)) {
                return true;
            }
        } catch (\Throwable $e) {
            // 如果执行命令失败，尝试其他方法
        }

        // 方法2：检查是否有 Watcher 的 PID 文件
        $pidFile = BASE_PATH . '/runtime/hyperf_watcher.pid';
        if (file_exists($pidFile)) {
            try {
                $pid = (int) file_get_contents($pidFile);
                if ($pid > 0) {
                    // 检查进程是否存在（Unix/Linux 系统）
                    if (function_exists('posix_kill')) {
                        return posix_kill($pid, 0);
                    }
                    // Windows 系统或其他备选方案
                    return true; // 假设 PID 文件存在且有效
                }
            } catch (\Throwable $e) {
                // PID 文件可能损坏，删除它
                @unlink($pidFile);
            }
        }

        // 方法3：检查是否有 Watcher 的状态文件
        $statusFile = BASE_PATH . '/runtime/hyperf_watcher.status';
        if (file_exists($statusFile)) {
            try {
                $status = trim(file_get_contents($statusFile));
                return $status === 'running';
            } catch (\Throwable $e) {
                // 状态文件可能损坏，删除它
                @unlink($statusFile);
            }
        }

        // 如果所有方法都失败，返回 false
        return false;
    }
}

if (! function_exists('addons_config')) {
    /**
     * 获取插件配置项
     *
     * @param string $addonName 插件名称
     * @param string $key 配置键名（支持点号分隔，如 'database.host'）
     * @param mixed $default 默认值
     * @return mixed
     *
     * @example
     * // 获取 AddonsStore 插件的启用状态
     * $enabled = addons_config('AddonsStore', 'enabled');
     *
     * // 获取嵌套配置项
     * $host = addons_config('MyPlugin', 'database.host', 'localhost');
     *
     * // 获取配置数组
     * $configs = addons_config('MyPlugin', 'configs');
     *
     * // 不存在的插件或配置项会返回默认值
     * $value = addons_config('UnknownPlugin', 'any.key', 'default');
     */
    function addons_config(string $addonName, string $key, mixed $default = null): mixed
    {
        try {
            $addonService = make(AddonService::class);
            $config = $addonService->getAddonConfig($addonName);

            if (empty($config)) {
                return $default;
            }

            // 支持点号分隔的键名，如 'database.host'
            $keys = explode('.', $key);
            $value = $config;

            foreach ($keys as $k) {
                if (! is_array($value) || ! array_key_exists($k, $value)) {
                    return $default;
                }
                $value = $value[$k];
            }

            return $value;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (! function_exists('e')) {
    /**
     * HTML 实体转义
     * 替代 Laravel 的 e() 辅助函数
     *
     * @param mixed $value 待转义的值
     * @return string
     */
    function e(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('h')) {
    /**
     * HTML 实体转义（e() 函数的别名）
     * 提供更直观的中文命名
     *
     * @param mixed $value 待转义的值
     * @return string
     */
    function h(mixed $value): string
    {
        return e($value);
    }
}

if (! function_exists('get_current_path')) {
    /**
     * 获取当前请求路径
     * 纯 PHP 实现，避免 Laravel 的 request() 辅助函数
     *
     * @return string
     */
    function get_current_path(): string
    {
        try {
            $request = make(\Hyperf\HttpServer\Contract\RequestInterface::class);
            return '/' . ltrim($request->getPathInfo(), '/');
        } catch (\Throwable $e) {
            return '/';
        }
    }
}

if (! function_exists('render_nav_item')) {
    /**
     * 渲染导航菜单项（支持多级）
     *
     * @param array $menu 菜单数据
     * @param array $allMenus 全部菜单数据
     * @param string $position 菜单位置（header/footer）
     * @return string
     */
    function render_nav_item(array $menu, array $allMenus = [], string $position = 'header'): string
    {
        $hasChildren = isset($menu['children']) && !empty($menu['children']);
        $url = $menu['url'] ?? '#';
        $name = $menu['name'] ?? '菜单项';
        $target = isset($menu['target']) && $menu['target'] === '_blank' ? 'target="_blank"' : '';
        $activeClass = '';

        // 检查是否当前激活
        $currentPath = get_current_path();
        if ($currentPath === rtrim($url, '/') || ($url !== '#' && str_starts_with($currentPath, $url))) {
            $activeClass = 'active';
        }

        $html = '<li class="nav-item' . ($hasChildren ? ' has-dropdown' : '') . '">';
        $html .= '<a href="' . e($url) . '" class="nav-link ' . e($activeClass) . '" ' . $target . '>';

        // 如果有图标，显示图标
        if (!empty($menu['icon'])) {
            $html .= '<i class="' . e($menu['icon']) . '" style="margin-right: 6px;"></i>';
        }

        $html .= e($name);

        if ($hasChildren) {
            $html .= '<svg class="dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= '<polyline points="6 9 12 15 18 9"></polyline>';
            $html .= '</svg>';
        }

        $html .= '</a>';

        // 渲染子菜单
        if ($hasChildren) {
            $html .= '<ul class="dropdown-menu">';
            foreach ($menu['children'] as $child) {
                $html .= render_nav_item($child, $allMenus, $position);
            }
            $html .= '</ul>';
        }

        $html .= '</li>';
        return $html;
    }
}

if (! function_exists('render_mobile_nav_item')) {
    /**
     * 渲染移动端导航菜单项（支持多级）
     *
     * @param array $menu 菜单数据
     * @param array $allMenus 全部菜单数据
     * @return string
     */
    function render_mobile_nav_item(array $menu, array $allMenus = []): string
    {
        $hasChildren = isset($menu['children']) && !empty($menu['children']);
        $url = $menu['url'] ?? '#';
        $name = $menu['name'] ?? '菜单项';
        $target = isset($menu['target']) && $menu['target'] === '_blank' ? 'target="_blank"' : '';

        $html = '<li class="mobile-nav-item' . ($hasChildren ? ' has-children' : '') . '">';
        $html .= '<a href="' . e($url) . '" class="mobile-nav-link" ' . $target . '>';

        // 如果有图标，显示图标
        if (!empty($menu['icon'])) {
            $html .= '<i class="' . e($menu['icon']) . '" style="margin-right: 8px;"></i>';
        }

        $html .= e($name);

        // 如果有子菜单，显示展开图标
        if ($hasChildren) {
            $html .= '<svg class="dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= '<polyline points="6 9 12 15 18 9"></polyline>';
            $html .= '</svg>';
        }

        $html .= '</a>';

        // 渲染子菜单
        if ($hasChildren) {
            $html .= '<ul class="dropdown-menu">';
            foreach ($menu['children'] as $child) {
                $html .= render_mobile_nav_item($child, $allMenus);
            }
            $html .= '</ul>';
        }

        $html .= '</li>';
        return $html;
    }
}
