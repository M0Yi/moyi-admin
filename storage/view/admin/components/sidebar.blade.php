@php
    use App\Model\Admin\AdminMenu;
    $sidebarMenus = AdminMenu::query()
        ->where('status', 1)
        ->where('visible', 1)
        ->whereIn('site_id', [0, site_id()])
        ->orderBy('sort', 'asc')
        ->orderBy('id', 'asc')
        ->get()
        ->toArray();
    $sidebarMenus = AdminMenu::buildTree($sidebarMenus);
@endphp

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-sticky">
        <ul class="nav flex-column">
            @forelse($sidebarMenus as $menu)
                @include('admin.components.sidebar-menu-item', ['menu' => $menu])
            @empty
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-2" href="{{ admin_route('dashboard') }}" data-path="dashboard">
                        <i class="bi bi-house-door" style="width: 16px; height: 16px;"></i>
                        <span>仪表盘</span>
                    </a>
                </li>
            @endforelse
        </ul>
    </div>
    </div>

@push('admin_scripts')
<script>
(function() {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarToggleMobile = document.getElementById('sidebarToggleMobile');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed ? '1' : '0');
        });
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === '1') sidebar.classList.add('collapsed');
    }

    if (sidebarToggleMobile) {
        sidebarToggleMobile.addEventListener('click', function() {
            sidebar.classList.add('show');
            sidebarBackdrop.classList.add('show');
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            sidebarBackdrop.classList.remove('show');
        });
    }

    if (window.innerWidth <= 768) {
        const navLinks = sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarBackdrop.classList.remove('show');
            });
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('show');
            sidebarBackdrop.classList.remove('show');
        }
    });

    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.sidebar .nav-link[data-path]');
    navLinks.forEach(link => {
        const linkPath = link.getAttribute('data-path');
        const fullPath = window.adminRoute(linkPath);
        if (currentPath === fullPath || (linkPath !== 'dashboard' && currentPath.startsWith(fullPath + '/'))) {
            link.classList.add('active');
        }
    });

    const hasActive = document.querySelector('.sidebar .nav-link.active');
    if (!hasActive && currentPath.startsWith(window.ADMIN_ENTRY_PATH)) {
        const dashboardLink = document.querySelector('.sidebar .nav-link[data-path="dashboard"]');
        if (dashboardLink) dashboardLink.classList.add('active');
    }
})();
</script>
@endpush
@push('admin_styles')
<style>
/* 侧边栏主体与折叠/展开 */
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
@media (min-width: 769px) {
    .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
    .sidebar.collapsed .nav-link span { display: none; }
    .sidebar.collapsed .nav-link { justify-content: center; padding: 0.75rem; }
    .sidebar.collapsed .nav-link svg { margin-right: 0; }
    .sidebar.collapsed .sidebar-heading { display: none; }
    .sidebar.collapsed ~ main { margin-left: var(--sidebar-collapsed-width); }
}
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.show { transform: translateX(0); box-shadow: 4px 0 12px rgba(0, 0, 0, 0.15); }
}

/* 遮罩层 */
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
@media (max-width: 768px) { .sidebar-backdrop.show { display: block; } }

/* 侧边栏内部滚动与样式 */
.sidebar-sticky { height: calc(100vh - var(--header-height)); overflow-x: hidden; overflow-y: auto; padding: 1.5rem 0; }
.sidebar-sticky::-webkit-scrollbar { width: 6px; }
.sidebar-sticky::-webkit-scrollbar-track { background: transparent; }
.sidebar-sticky::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
.sidebar-sticky::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
.sidebar .nav-link { position: relative; display: flex; align-items: center; padding: 0.75rem 1.5rem; margin: 0.25rem 0.75rem; font-weight: 500; color: #64748b; border-radius: 8px; transition: all 0.2s ease; }
.sidebar .nav-link:hover { color: #1e293b; background: #e2e8f0; }
.sidebar .nav-link.active { color: #fff; background: #3b82f6; }
.sidebar .nav-link svg { margin-right: 0.75rem; color: currentColor; transition: all 0.2s ease; flex-shrink: 0; }
.sidebar .nav-link span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: all 0.3s ease; }
.sidebar-heading { padding: 1rem 1.5rem 0.5rem; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; }
.sidebar .nav .nav { padding-left: 0; margin-top: 0.25rem; margin-bottom: 0.25rem; }
.sidebar .nav .nav .nav-link { padding-left: 3rem; font-size: 0.85rem; }
.sidebar .nav .nav .nav .nav-link { padding-left: 4rem; }
.sidebar .badge { font-size: 0.65rem; padding: 0.25rem 0.5rem; }
.sidebar.collapsed .nav .nav { display: none; }

/* 主内容区域与折叠联动 */
main { margin-left: var(--sidebar-width); padding: 1rem 2rem 2rem; min-height: calc(100vh - var(--header-height)); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
main.sidebar-collapsed { margin-left: var(--sidebar-collapsed-width); }
@media (max-width: 768px) { main { margin-left: 0; padding: 0.75rem 1rem 1.5rem; } main.sidebar-collapsed { margin-left: 0; } }

/* 侧边栏切换按钮样式（桌面/移动端） */
.sidebar-toggle { background: transparent; border: none; color: #64748b; font-size: 1.25rem; padding: 0.5rem; cursor: pointer; transition: all 0.2s ease; }
.sidebar-toggle:hover { color: #1e293b; }
@media (max-width: 768px) { .sidebar-toggle-mobile { display: block; } }
@media (min-width: 769px) { .sidebar-toggle-mobile { display: none; } }
</style>
@endpush

@push('admin_sidebar_nav_actions')
<button class="btn btn-link text-secondary d-lg-none me-2" type="button" id="sidebarToggleMobile">
    <i class="bi bi-list fs-4"></i>
</button>
<button class="btn btn-link text-secondary d-none d-lg-block me-auto" type="button" id="sidebarToggle">
    <i class="bi bi-layout-sidebar-inset fs-5"></i>
</button>
@endpush
