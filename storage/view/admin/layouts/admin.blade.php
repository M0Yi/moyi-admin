 

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
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-color: #10b981;

            --sidebar-width: 260px; /* 侧边栏展开宽度 */
            --sidebar-collapsed-width: 80px; /* 侧边栏收起宽度 */
            --header-height: 60px; /* 顶部导航高度 */
            --primary-hover: #764ba2;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --secondary-color: #6c757d;
            --light-color: #f8f9fa;
            --dark-color: #1f2937;
            --border-color: #e5e7eb;
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ==================== 按钮样式 ==================== */
        .btn {
            border-radius: var(--border-radius);
            padding: 0.625rem 1.25rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
            border: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* 主要按钮 */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-hover) 0%, var(--primary-color) 100%);
            color: white;
        }

        /* 成功按钮 */
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background-color: #059669;
            color: white;
        }

        /* 警告按钮 */
        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background-color: #d97706;
            color: white;
        }

        /* 危险按钮 */
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
            color: white;
        }

        /* 信息按钮 */
        .btn-info {
            background-color: var(--info-color);
            color: white;
        }

        .btn-info:hover {
            background-color: #2563eb;
            color: white;
        }

        /* 次要按钮 */
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            color: white;
        }

        /* 浅色按钮 */
        .btn-light {
            background-color: var(--light-color);
            color: var(--dark-color);
            border: 1px solid var(--border-color);
        }

        .btn-light:hover {
            background-color: #e9ecef;
            border-color: #dee2e6;
            color: var(--dark-color);
        }

        /* 轮廓按钮 */
        .btn-outline-primary {
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-outline-secondary {
            color: var(--secondary-color);
            border: 1px solid var(--border-color);
        }

        .btn-outline-secondary:hover {
            background-color: var(--light-color);
            border-color: #adb5bd;
            color: #495057;
        }

        /* 小按钮 */
        .btn-sm, .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }

        /* 按钮组 */
        .btn-group .btn {
            margin: 0;
        }

        /* ==================== 卡片样式 ==================== */
        .card {
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            /* 确保下拉菜单不被裁剪 */
            overflow: visible;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
            /* 确保下拉菜单不被裁剪 */
            overflow: visible;
        }


        /* ==================== 表格样式 ==================== */
        .table-responsive {
            border-radius: var(--border-radius);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: var(--light-color);
            color: var(--dark-color);
            font-weight: 600;
            font-size: 0.875rem;
            border-bottom: 2px solid var(--border-color);
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            color: #4b5563;
        }

        .table-hover tbody tr {
            transition: background-color 0.2s;
        }

        .table-hover tbody tr:hover {
            background-color: #f9fafb;
        }

        /* 固定操作列 */
        .sticky-column {
            position: sticky !important;
            right: 0;
            background-color: #ffffff;
            z-index: 10;
            box-shadow: -2px 0 8px rgba(0, 0, 0, 0.05);
        }

        thead .sticky-column {
            background-color: var(--light-color);
            z-index: 11;
        }

        .table-hover tbody tr:hover .sticky-column {
            background-color: #f9fafb;
        }

        /* ==================== 表单样式 ==================== */
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        .form-control, .form-select {
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            padding: 0.625rem 0.875rem;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .form-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .form-check-input {
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* ==================== 徽章样式 ==================== */
        .badge {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.375rem;
        }

        .badge-menu {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-button {
            background: #fed7aa;
            color: #92400e;
        }

        .badge-link {
            background: #e8f5e9;
            color: #388e3c;
        }

        .badge-api {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        /* ==================== 模态框样式 ==================== */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            border-radius: var(--border-radius-lg);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
            border-bottom: none;
            padding: 1.25rem 1.5rem;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: none;
            padding: 1rem 1.5rem 1.5rem;
        }

        /* ==================== 固定底部操作栏样式 ==================== */
        /* 占位区域：防止内容被固定按钮遮挡 */
        .fixed-bottom-actions-spacer {
            height: 80px; /* 按钮高度 + padding，根据实际调整 */
            width: 100%;
        }

        .fixed-bottom-actions {
            position: fixed;
            bottom: 0;
            left: var(--sidebar-width, 260px);
            right: 0;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
            z-index: 1030;
            animation: slideUp 0.3s ease-out;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar.collapsed ~ main .fixed-bottom-actions,
        body:has(.sidebar.collapsed) .fixed-bottom-actions {
            left: var(--sidebar-collapsed-width, 80px);
        }

        .fixed-bottom-actions .btn {
            min-width: 100px;
        }

        .fixed-bottom-actions .btn-outline-secondary,
        .fixed-bottom-actions .btn-light {
            background: rgba(255, 255, 255, 0.9);
            border-color: rgba(255, 255, 255, 0.9);
            color: var(--primary-color);
        }

        .fixed-bottom-actions .btn-outline-secondary:hover,
        .fixed-bottom-actions .btn-light:hover {
            background: #fff;
            border-color: #fff;
            color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .fixed-bottom-actions .btn-primary {
            background: #fff;
            border-color: #fff;
            color: var(--primary-color);
            font-weight: 600;
        }

        .fixed-bottom-actions .btn-primary:hover {
            background: rgba(255, 255, 255, 0.95);
            border-color: rgba(255, 255, 255, 0.95);
            color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .fixed-bottom-actions .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        @keyframes slideUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* 响应式：移动端 */
        @media (max-width: 768px) {
            .fixed-bottom-actions-spacer {
                height: 60px; /* 移动端减少占位高度，因为隐藏了提示文字 */
            }

            .fixed-bottom-actions {
                left: 0 !important;
            }

            .fixed-bottom-actions .text-muted {
                display: none;
            }
        }

        /* ==================== 面包屑样式 ==================== */
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 0.5rem;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: #9ca3af;
        }

        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        .breadcrumb-item.active {
            color: #6b7280;
        }

        /* ==================== 分页样式 ==================== */
        .pagination {
            margin-bottom: 0;
        }

        .page-link {
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            margin: 0 0.25rem;
            transition: var(--transition);
        }

        .page-link:hover {
            background-color: var(--light-color);
            color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            border-color: var(--primary-color);
        }

        .page-item.disabled .page-link {
            color: #9ca3af;
            background-color: var(--light-color);
            border-color: var(--border-color);
        }

        /* ==================== Toast 通知样式 ==================== */
        .toast-container {
            z-index: 9999;
        }

        .toast {
            border-radius: var(--border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* ==================== 其他通用样式 ==================== */
        .text-primary {
            color: var(--primary-color) !important;
        }

        .bg-primary {
            background-color: var(--primary-color) !important;
            color: white !important;
        }

        .bg-success {
            background-color: var(--success-color) !important;
            color: white !important;
        }

        .bg-info {
            background-color: var(--info-color) !important;
            color: white !important;
        }

        .bg-warning {
            background-color: var(--warning-color) !important;
            color: white !important;
        }

        .bg-danger {
            background-color: var(--danger-color) !important;
            color: white !important;
        }

        .bg-secondary {
            background-color: var(--secondary-color) !important;
            color: white !important;
        }

        .shadow-sm {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
        }

        .rounded-pill {
            border-radius: 50rem !important;
        }

        /* 头像样式 */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }

        /* 菜单/权限层级缩进 */
        .menu-level-0, .permission-level-0 { padding-left: 1rem; }
        .menu-level-1, .permission-level-1 { padding-left: 2.5rem; }
        .menu-level-2, .permission-level-2 { padding-left: 4rem; }
        .menu-level-3, .permission-level-3 { padding-left: 5.5rem; }

        /* 图标容器 */
        .menu-icon, .permission-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: var(--light-color);
            border-radius: 6px;
            font-size: 1.1rem;
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
