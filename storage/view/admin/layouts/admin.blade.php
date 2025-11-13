@php
    // 获取当前站点的菜单树
    use App\Model\Admin\AdminMenu;

    $sidebarMenus = AdminMenu::query()
        ->where('status', 1)
        ->where('visible', 1)
        ->whereIn('site_id', [0, site_id()])
        ->orderBy('sort', 'asc')
        ->orderBy('id', 'asc')
        ->get()
        ->toArray();

    // 构建树形结构
    $sidebarMenus = AdminMenu::buildTree($sidebarMenus);
@endphp

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ site()?->description ?? '管理后台系统' }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '管理后台') - {{ site()?->name ?? '管理后台' }}</title>

    {{-- Bootstrap 5 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    {{-- Tom Select CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css" rel="stylesheet">
    {{-- Flatpickr CSS (Bootstrap 5 兼容的日期选择器) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    {{-- SVG Icons --}}
    <svg xmlns="http://www.w3.org/2000/svg" class="d-none">
        <symbol id="house-fill" viewBox="0 0 16 16">
            <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L8 2.207l6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.707 1.5Z"/>
            <path d="m8 3.293 6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293l6-6Z"/>
        </symbol>
        <symbol id="people" viewBox="0 0 16 16">
            <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
        </symbol>
        <symbol id="shield-lock" viewBox="0 0 16 16">
            <path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.481.481 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.725 10.725 0 0 0 2.287 2.233c.346.244.652.42.893.533.12.057.218.095.293.118a.55.55 0 0 0 .101.025.615.615 0 0 0 .1-.025c.076-.023.174-.061.294-.118.24-.113.547-.29.893-.533a10.726 10.726 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.775 11.775 0 0 1-2.517 2.453 7.159 7.159 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7.158 7.158 0 0 1-1.048-.625 11.777 11.777 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 62.456 62.456 0 0 1 5.072.56z"/>
            <path d="M9.5 6.5a1.5 1.5 0 0 1-1 1.415l.385 1.99a.5.5 0 0 1-.491.595h-.788a.5.5 0 0 1-.49-.595l.384-1.99a1.5 1.5 0 1 1 2-1.415z"/>
        </symbol>
        <symbol id="globe" viewBox="0 0 16 16">
            <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm7.5-6.923c-.67.204-1.335.82-1.887 1.855A7.97 7.97 0 0 0 5.145 4H7.5V1.077zM4.09 4a9.267 9.267 0 0 1 .64-1.539 6.7 6.7 0 0 1 .597-.933A7.025 7.025 0 0 0 2.255 4H4.09zm-.582 3.5c.03-.877.138-1.718.312-2.5H1.674a6.958 6.958 0 0 0-.656 2.5h2.49zM4.847 5a12.5 12.5 0 0 0-.338 2.5H7.5V5H4.847zM8.5 5v2.5h2.99a12.495 12.495 0 0 0-.337-2.5H8.5zM4.51 8.5a12.5 12.5 0 0 0 .337 2.5H7.5V8.5H4.51zm3.99 0V11h2.653c.187-.765.306-1.608.338-2.5H8.5zM5.145 12c.138.386.295.744.468 1.068.552 1.035 1.218 1.65 1.887 1.855V12H5.145zm.182 2.472a6.696 6.696 0 0 1-.597-.933A9.268 9.268 0 0 1 4.09 12H2.255a7.024 7.024 0 0 0 3.072 2.472zM3.82 11a13.652 13.652 0 0 1-.312-2.5h-2.49c.062.89.291 1.733.656 2.5H3.82zm6.853 3.472A7.024 7.024 0 0 0 13.745 12H11.91a9.27 9.27 0 0 1-.64 1.539 6.688 6.688 0 0 1-.597.933zM8.5 12v2.923c.67-.204 1.335-.82 1.887-1.855.173-.324.33-.682.468-1.068H8.5zm3.68-1h2.146c.365-.767.594-1.61.656-2.5h-2.49a13.65 13.65 0 0 1-.312 2.5zm2.802-3.5a6.959 6.959 0 0 0-.656-2.5H12.18c.174.782.282 1.623.312 2.5h2.49zM11.27 2.461c.247.464.462.98.64 1.539h1.835a7.024 7.024 0 0 0-3.072-2.472c.218.284.418.598.597.933zM10.855 4a7.966 7.966 0 0 0-.468-1.068C9.835 1.897 9.17 1.282 8.5 1.077V4h2.355z"/>
        </symbol>
        <symbol id="gear" viewBox="0 0 16 16">
            <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
            <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
        </symbol>
        <symbol id="file-text" viewBox="0 0 16 16">
            <path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H5zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zM5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H5zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1H5z"/>
            <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1z"/>
        </symbol>
        <symbol id="box-arrow-right" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
            <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
        </symbol>
    </svg>

    <style>
        :root {
            --primary-color: {{ site()?->primary_color ?? '#6366f1' }};
            --secondary-color: {{ site()?->secondary_color ?? '#8b5cf6' }};
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 80px;
            --header-height: 60px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-size: 0.875rem;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f8fafc;
            overflow-x: hidden;
        }

        /* 顶部导航栏优化 */
        .navbar {
            height: var(--header-height);
            background: #fff !important;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            z-index: 1030;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: -0.5px;
            background: transparent !important;
            box-shadow: none !important;
            padding: 0.75rem 1rem !important;
            color: #1e293b !important;
        }

        /* 侧边栏切换按钮 */
        .sidebar-toggle {
            background: transparent;
            border: none;
            color: #64748b;
            font-size: 1.25rem;
            padding: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .sidebar-toggle:hover {
            color: #1e293b;
        }

        /* 移动端菜单按钮 */
        @media (max-width: 768px) {
            .sidebar-toggle-mobile {
                display: block;
            }
        }

        @media (min-width: 769px) {
            .sidebar-toggle-mobile {
                display: none;
            }
        }

        /* 侧边栏现代化设计 - Bootstrap 5 风格 */
        .sidebar {
            position: fixed;
            top: var(--header-height);
            bottom: 0;
            left: 0;
            width: var(--sidebar-width);
            z-index: 1020;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            transition: transform 0.3s ease, width 0.3s ease;
            overflow-x: hidden;
        }

        /* PC端侧边栏折叠状态 */
        @media (min-width: 769px) {
            .sidebar.collapsed {
                width: var(--sidebar-collapsed-width);
            }

            .sidebar.collapsed .nav-link span {
                display: none;
            }

            .sidebar.collapsed .nav-link {
                justify-content: center;
                padding: 0.75rem;
            }

            .sidebar.collapsed .nav-link svg {
                margin-right: 0;
            }

            .sidebar.collapsed .sidebar-heading {
                display: none;
            }

            /* PC端侧边栏折叠时主内容区域调整 */
            .sidebar.collapsed ~ main {
                margin-left: var(--sidebar-collapsed-width);
            }
        }

        /* 移动端侧边栏 - 默认隐藏，点击展开 */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
                box-shadow: 4px 0 12px rgba(0, 0, 0, 0.15);
            }
        }

        /* 遮罩层 */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: var(--header-height);
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1019;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        @media (max-width: 768px) {
            .sidebar-backdrop.show {
                display: block;
                opacity: 1;
            }
        }

        .sidebar-sticky {
            height: calc(100vh - var(--header-height));
            overflow-x: hidden;
            overflow-y: auto;
            padding: 1.5rem 0;
        }

        /* 自定义滚动条 */
        .sidebar-sticky::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-sticky::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-sticky::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .sidebar-sticky::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* 菜单项样式 */
        .sidebar .nav-link {
            position: relative;
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 0.75rem;
            font-weight: 500;
            color: #64748b;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .sidebar .nav-link:hover {
            color: #1e293b;
            background: #e2e8f0;
        }

        .sidebar .nav-link.active {
            color: #fff;
            background: #3b82f6;
        }

        .sidebar .nav-link svg {
            margin-right: 0.75rem;
            color: currentColor;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .sidebar .nav-link span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: all 0.3s ease;
        }

        /* 侧边栏标题 */
        .sidebar-heading {
            padding: 1rem 1.5rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
        }

        /* 子菜单样式 */
        .sidebar .nav .nav {
            padding-left: 0;
            margin-top: 0.25rem;
            margin-bottom: 0.25rem;
        }

        .sidebar .nav .nav .nav-link {
            padding-left: 3rem;
            font-size: 0.85rem;
        }

        .sidebar .nav .nav .nav .nav-link {
            padding-left: 4rem;
        }

        /* 徽章样式 */
        .sidebar .badge {
            font-size: 0.65rem;
            padding: 0.25rem 0.5rem;
        }

        /* 折叠时隐藏子菜单 */
        .sidebar.collapsed .nav .nav {
            display: none;
        }

        /* 主内容区域 */
        main {
            margin-left: var(--sidebar-width);
            padding: 1rem 2rem 2rem;
            min-height: calc(100vh - var(--header-height));
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* 侧边栏折叠时主内容区域 */
        main.sidebar-collapsed {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* 移动端主内容区域 */
        @media (max-width: 768px) {
            main {
                margin-left: 0;
                padding: 0.75rem 1rem 1.5rem;
            }

            main.sidebar-collapsed {
                margin-left: 0;
            }
        }

        /* 页面标题优化 */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            margin: -1rem -2rem 1.5rem -2rem;
            border-radius: 0 0 24px 24px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.2);
        }

        .page-header h1 {
            font-weight: 700;
            margin: 0;
            font-size: 1.75rem;
        }

        /* 卡片优化 */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            /* 只对 box-shadow 和 transform 应用过渡，避免影响 modal 等子元素 */
            transition: box-shadow 0.3s ease, transform 0.3s ease;
            /* 改为 visible，确保 modal 等子元素不被裁剪 */
            overflow: visible;
        }

        .card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            transform: translateY(-4px);
        }
        
        /* 当 card 内包含 modal 时，禁用 hover 效果，避免闪动 */
        .card:has(.modal) {
            transition: box-shadow 0.3s ease;
        }
        
        .card:has(.modal):hover {
            transform: none;
        }

        .card-header {
            background: transparent;
            border: none;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* 按钮优化 */
        .btn {
            padding: 0.6rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-outline-secondary:hover {
            transform: translateY(-2px);
        }

        /* 统计卡片动画 */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stats-card {
            animation: fadeInUp 0.6s ease-out;
        }

        /* 移动端响应式 */
        @media (max-width: 768px) {
            main {
                margin-left: 0 !important;
                padding: 1rem;
            }
        }

        /* 移动端遮罩层 */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: var(--header-height);
            left: 0;
            width: 100%;
            height: calc(100vh - var(--header-height));
            background: rgba(0, 0, 0, 0.5);
            z-index: 1019;
            transition: opacity 0.3s ease;
        }

        @media (max-width: 768px) {
            .sidebar-backdrop.show {
                display: block;
            }
        }

        /* 导航栏按钮 */
        .navbar .nav-link {
            color: #64748b !important;
            transition: all 0.2s ease;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
        }

        .navbar .nav-link:hover {
            background: #f1f5f9;
            color: #1e293b !important;
        }
    </style>

    @stack('styles')
</head>
<body>

{{-- 顶部导航栏 --}}
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm sticky-top">
    <div class="container-fluid">
        {{-- 移动端菜单按钮 --}}
        <button class="btn btn-link text-secondary d-lg-none me-2" type="button" id="sidebarToggleMobile">
            <i class="bi bi-list fs-4"></i>
        </button>

        <a class="navbar-brand fw-bold" href="{{ admin_route('dashboard') }}">
        {{ site()?->name ?? '管理后台' }}
    </a>

        {{-- 桌面端折叠按钮 --}}
        <button class="btn btn-link text-secondary d-none d-lg-block me-auto" type="button" id="sidebarToggle">
            <i class="bi bi-layout-sidebar-inset fs-5"></i>
        </button>

        <div class="d-flex align-items-center ms-auto">
            {{-- 用户信息 - 显示当前登录管理员的用户名 --}}
            <div class="d-flex align-items-center">
                <i class="bi bi-person-circle fs-4 me-2" style="color: #64748b;"></i>
                <span class="text-secondary">{{ auth('admin')->user()['username'] ?? '管理员' }}</span>
            </div>
        </div>
    </div>
</nav>

{{-- 遮罩层 - 移动端用 --}}
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        {{-- 侧边栏 --}}
<div class="sidebar" id="sidebar">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    @forelse($sidebarMenus as $menu)
                        @include('components.sidebar-menu-item', ['menu' => $menu])
                    @empty
                        {{-- 如果没有菜单数据，显示默认菜单 --}}
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center gap-2"
                               href="{{ admin_route('dashboard') }}"
                               data-path="dashboard">
                                <i class="bi bi-house-door" style="width: 16px; height: 16px;"></i>
                                <span>仪表盘</span>
                            </a>
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>

        {{-- 主内容区 --}}
        <main>
            @yield('content')
        </main>
        {{-- <!-- @include('admin.components.floating-code-viewer') --> --}}

{{-- Bootstrap Bundle JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
{{-- Tom Select JS --}}
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
{{-- Flatpickr JS (Bootstrap 5 兼容的日期选择器) --}}
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
{{-- Flatpickr 中文语言包 --}}
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/zh.js"></script>

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

{{-- 侧边栏响应式控制和自动高亮 --}}
<script>
(function() {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarToggleMobile = document.getElementById('sidebarToggleMobile');

    // PC端：侧边栏折叠/展开
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            // 保存状态到 localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed ? '1' : '0');
        });

        // 恢复上次的折叠状态
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === '1') {
            sidebar.classList.add('collapsed');
        }
    }

    // 移动端：侧边栏显示/隐藏
    if (sidebarToggleMobile) {
        sidebarToggleMobile.addEventListener('click', function() {
            sidebar.classList.add('show');
            sidebarBackdrop.classList.add('show');
        });
    }

    // 点击遮罩层或侧边栏内的链接关闭移动端侧边栏
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarBackdrop.classList.remove('show');
        });
    }

    // 移动端点击菜单项后关闭侧边栏
    if (window.innerWidth <= 768) {
        const navLinks = sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarBackdrop.classList.remove('show');
            });
        });
    }

    // 窗口大小改变时处理
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('show');
            sidebarBackdrop.classList.remove('show');
        }
    });

    // 自动高亮当前激活的菜单项
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.sidebar .nav-link[data-path]');

    navLinks.forEach(link => {
        const linkPath = link.getAttribute('data-path');

        // 使用 adminRoute 函数生成完整路径
        const fullPath = window.adminRoute(linkPath);

        // 精确匹配或前缀匹配（用于子页面）
        if (currentPath === fullPath ||
            (linkPath !== 'dashboard' && currentPath.startsWith(fullPath + '/'))) {
            link.classList.add('active');
        }
    });

    // 特殊处理：如果没有激活项且在后台路径下，激活仪表盘
    const hasActive = document.querySelector('.sidebar .nav-link.active');
    if (!hasActive && currentPath.startsWith(window.ADMIN_ENTRY_PATH)) {
        const dashboardLink = document.querySelector('.sidebar .nav-link[data-path="dashboard"]');
        if (dashboardLink) {
            dashboardLink.classList.add('active');
        }
    }
})();
</script>

@stack('scripts')
</body>
</html>

