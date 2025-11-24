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
    $siteName = site()?->name ?? '管理后台';
    $siteSlogan = site()?->slogan ?? '';
    $siteLogo = site()?->logo ?? null;
    $siteInitial = function_exists('mb_substr') ? mb_substr($siteName, 0, 1) : substr($siteName, 0, 1);
@endphp

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="{{ admin_route('dashboard') }}" class="sidebar-brand">
            @if($siteLogo)
                <img src="{{ $siteLogo }}" alt="{{ $siteName }}" class="sidebar-brand__logo">
            @else
                <span class="sidebar-brand__logo">{{ $siteInitial }}</span>
            @endif
            <span class="sidebar-brand__text">
                <span class="sidebar-brand__title">{{ $siteName }}</span>
                @if($siteSlogan)
                    <span class="sidebar-brand__desc">{{ $siteSlogan }}</span>
                @endif
            </span>
        </a>
    </div>
    <div class="sidebar-sticky">
        <ul class="nav flex-column">
            @forelse($sidebarMenus as $menu)
                @include('admin.components.sidebar-menu-item', ['menu' => $menu])
            @empty
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-2"
                       href="{{ admin_route('dashboard') }}"
                       data-path="dashboard"
                       data-admin-tab="1"
                       data-tab-mode="internal"
                       data-tab-title="仪表盘">
                        <i class="bi bi-house-door" style="width: 16px; height: 16px;"></i>
                        <span>仪表盘</span>
                    </a>
                </li>
            @endforelse
        </ul>
    </div>
    </div>

@push('admin_shell_scripts')
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
        const navLinks = sidebar.querySelectorAll('.nav-link:not(.has-children)');
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

    // 子菜单展开/折叠功能
    const submenuLinks = sidebar.querySelectorAll('.nav-link.has-children');
    submenuLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // 阻止 tab 管理器处理有子菜单的链接
            e.stopPropagation();
            
            const targetId = link.getAttribute('data-target');
            if (!targetId) return;

            const submenu = document.querySelector(targetId);
            if (!submenu) return;

            // 切换展开/折叠
            const isExpanded = submenu.classList.contains('show');
            const arrow = link.querySelector('.submenu-arrow');

            if (isExpanded) {
                submenu.classList.remove('show');
                if (arrow) arrow.style.transform = 'rotate(0deg)';
            } else {
                submenu.classList.add('show');
                if (arrow) arrow.style.transform = 'rotate(180deg)';
            }
        });
    });

    // 高亮当前活动菜单项（仅在非 iframe 模式下）
    if (window.self === window.top) {
        if (window.Admin && window.Admin.utils && typeof window.Admin.utils.setSidebarActiveByUrl === 'function') {
            window.Admin.utils.setSidebarActiveByUrl(window.location.pathname);
        }
    }
})();
</script>
@endpush

@push('admin_sidebar_nav_actions')
<button class="btn btn-link text-secondary sidebar-toggle-mobile me-2" type="button" id="sidebarToggleMobile">
    <i class="bi bi-list fs-4"></i>
</button>
<button class="btn btn-link text-secondary sidebar-toggle-desktop" type="button" id="sidebarToggle">
    <i class="bi bi-layout-sidebar-inset fs-5"></i>
</button>
@endpush
