 

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ site()?->description ?? '管理后台系统' }}">
    <title>@yield('title', '管理后台') - {{ site()?->name ?? '管理后台' }}</title>

    {{-- 外部 CSS 资源（按需引入） --}}
    @include('components.vendor.bootstrap-css')
    @include('components.vendor.bootstrap-icons')
    @include('components.vendor.tom-select-css')
    @include('components.vendor.flatpickr-css')

    {{-- 全局样式说明：为后台布局提供主题变量与基础排版
              页面级样式建议通过 @push('admin_styles') 进行扩展或覆盖 --}}
    <style>
        :root {
            --primary-color: {{ site()?->primary_color ?? '#6366f1' }}; /* 主色（品牌色） */
            --secondary-color: {{ site()?->secondary_color ?? '#8b5cf6' }}; /* 辅助色 */
            --sidebar-width: 260px; /* 侧边栏展开宽度 */
            --sidebar-collapsed-width: 80px; /* 侧边栏收起宽度 */
            --header-height: 60px; /* 顶部导航高度 */
        }

        /* 全局重置：统一盒模型与基础间距 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* 页面基础排版与背景设置 */
        body {
            font-size: 0.875rem; /* 14px 基础字号 */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; /* 系统字体栈，提升渲染性能 */
            background: #f8fafc; /* 浅灰背景，降低视觉噪音 */
            overflow-x: hidden; /* 禁止横向滚动，避免布局抖动 */
        }
    </style>
    {{-- 插槽：admin_styles
        功能：按需输出样式（CSS）
        来源：页面或组件通过 @push('admin_styles') 注入（如 admin_sidebar 组件）
    --}}
    @stack('admin_styles')
</head>
<body>

    {{-- 插槽：admin_navbar
        功能：按需挂载顶部导航栏
        来源：页面通过 @push('admin_navbar') 包含 admin.components.navbar
    --}}
    @stack('admin_navbar')

    {{-- 插槽：admin_sidebar
        功能：按需挂载侧边栏 DOM
        来源：页面通过 @push('admin_sidebar') 包含 admin.components.sidebar
    --}}
    @stack('admin_sidebar')

    {{-- 主内容区 --}}
    <main>
        @yield('content')
    </main>
    {{-- <!-- @include('admin.components.floating-code-viewer') --> --}}


{{-- 外部 JavaScript 资源（按需引入） --}}
@include('components.vendor.bootstrap-js')
@include('components.vendor.tom-select-js')
@include('components.vendor.flatpickr-js')
@include('components.vendor.flatpickr-zh')

{{-- 全局配置和工具函数 --}}
<script>
// 全局配置：后台入口路径
window.ADMIN_ENTRY_PATH = '{{ admin_entry_path() }}';

/**
 * 生成后台路由路径
 * 自动拼接当前站点的后台入口路径
 *
 * @param {string} path - 相对路径（不带前缀），例如 'dashboard' 或 'users/create'
 * @returns {string} 完整的后台路由，例如 '/admin/dashboard' 或 '/manage/users/create'
 *
 * @example
 * adminRoute('dashboard')           // 返回 '/admin/dashboard'
 * adminRoute('users/create')        // 返回 '/admin/users/create'
 * adminRoute('/users')              // 返回 '/admin/users' (自动去除前导斜杠)
 * adminRoute('logs/operations')     // 返回 '/admin/logs/operations'
 */
window.adminRoute = function(path) {
    // 移除路径前后的斜杠
    path = path.replace(/^\/+|\/+$/g, '');

    // 拼接完整路径
    return window.ADMIN_ENTRY_PATH + (path ? '/' + path : '');
};

/**
 * 检查当前路径是否匹配指定的后台路由
 *
 * @param {string} path - 要匹配的路径（相对路径）
 * @param {boolean} exact - 是否精确匹配（默认为false，前缀匹配）
 * @returns {boolean}
 *
 * @example
 * isAdminRoute('dashboard')         // 当前在 /admin/dashboard 返回 true
 * isAdminRoute('users', false)      // 当前在 /admin/users/123 返回 true
 * isAdminRoute('users', true)       // 当前在 /admin/users/123 返回 false
 */
window.isAdminRoute = function(path, exact = false) {
    const currentPath = window.location.pathname;
    const targetPath = window.adminRoute(path);

    if (exact) {
        return currentPath === targetPath;
    }

    return currentPath === targetPath || currentPath.startsWith(targetPath + '/');
};
</script>



{{-- 插槽：admin_scripts
    功能：按需输出脚本（JS）
    来源：页面或组件通过 @push('admin_scripts') 注入（如 admin_sidebar 组件）
--}}
@stack('admin_scripts')
</body>
</html>
