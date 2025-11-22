 
@php
    /**
     * iframe 模式变量说明
     * 
     * $isEmbedded 和 $normalizedUrl 变量由控制器通过 renderAdmin() 方法自动注入
     * 如果变量不存在，则使用默认值（向后兼容）
     */
    $isEmbedded = $isEmbedded ?? false;
    $normalizedUrl = $normalizedUrl ?? admin_route('');
    
    $initialTabTitle = trim($__env->yieldContent('title', '管理后台'));
@endphp

<!DOCTYPE html>
<html lang="zh-CN" data-embed="{{ $isEmbedded ? '1' : '0' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ site()?->description ?? '管理后台系统' }}">
    <title>@yield('title', '管理后台') - {{ site()?->name ?? '管理后台' }}</title>

    {{-- 外部 CSS 资源（按需引入） --}}
    @include('components.plugin.bootstrap-css')
    @include('components.plugin.bootstrap-icons')
    @include('components.plugin.tom-select-css')
    @include('components.plugin.flatpickr-css')

    {{-- 全局样式说明：为后台布局提供主题变量与基础排版
              页面级样式建议通过 @push('admin-styles') 进行扩展或覆盖 --}}
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
    </style>

    @include('components.admin-style')
    {{-- 插槽：admin-styles
        功能：按需输出样式（CSS）
        来源：页面或组件通过 @push('admin-styles') 注入（如 admin-sidebar 组件）
    --}}
    @stack('admin-styles')
</head>
<body class="{{ $isEmbedded ? 'admin-layout-embedded' : 'admin-layout-shell' }}">

    {{-- iframe-shell 组件：用于在弹窗中打开页面 --}}
    @include('components.iframe-shell')

    @if($isEmbedded)
        <main class="admin-embed-main">
            @yield('content')
        </main>
    @else
        <div id="adminTabLayout"
             class="admin-tab-layout"
             data-initial-url="{{ $normalizedUrl }}"
             data-initial-title="{{ $initialTabTitle }}">
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

            <main class="admin-tab-main">
                <div class="admin-tab-empty" data-role="empty-state">
                    <div class="admin-tab-empty__icon">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </div>
                    <div class="admin-tab-empty__text">
                        <p>请选择左侧菜单打开页面</p>
                        <small>支持多标签切换、刷新与关闭</small>
                    </div>
                </div>
                <div class="admin-tab-panels" data-role="tab-panels"></div>
            </main>
        </div>
    @endif
    {{-- <!-- @include('admin.components.floating-code-viewer') --> --}}



{{-- 全局配置和工具函数 --}}
<script>
// 全局配置：后台入口路径及站点信息
window.ADMIN_ENTRY_PATH = '{{ admin_entry_path() }}';
window.ADMIN_SITE_TITLE = @json(site()?->name ?? '管理后台');

</script>
@if ($isEmbedded)
<script>
(function () {
    const params = new URLSearchParams(window.location.search);
    const channel = params.get('_iframe_channel') || 'admin-iframe-shell';

    window.AdminIframeClient = {
        channel: channel,
        notify(action, payload) {
            if (window.parent === window) {
                return;
            }

            try {
                window.parent.postMessage({
                    channel: channel,
                    action: action,
                    payload: payload || {},
                    source: window.location.href
                }, window.location.origin);
            } catch (error) {
                console.error('[AdminIframeClient] notify error:', error);
            }
        },
        close(payload) {
            this.notify('close', payload);
        },
        success(payload) {
            this.notify('success', payload);
        },
        refreshMainFrame(payload) {
            const options = Object.assign({
                message: '系统配置已更新，即将刷新主框架...',
                delay: 0,
                toastType: 'info',
                showToast: true
            }, payload || {});
            this.notify('refresh-main', options);
        }
    };
})();
</script>
@endif
    @include('components.admin-js')
    {{-- 外部 JavaScript 资源（按需引入） --}}
    @include('components.plugin.bootstrap-js')
    @include('components.plugin.tom-select-js')
    @include('components.plugin.flatpickr-js')
    @include('components.plugin.flatpickr-zh')

    {{-- iframe-shell.js：在所有页面加载，用于处理弹窗打开功能 --}}
    @include('components.iframe-shell-js')

@if (! $isEmbedded)
    @include('components.admin-tab-manager-js')
    {{-- 插槽：admin_shell_scripts
        功能：输出仅在主框架中执行的脚本（如侧边栏行为）
    --}}
    @stack('admin_shell_scripts')
@endif

@if ($isEmbedded)
    {{-- 插槽：admin_scripts
        功能：仅在 iframe/内页中执行的脚本
    --}}
    @stack('admin_scripts')
@endif
</body>
</html>
