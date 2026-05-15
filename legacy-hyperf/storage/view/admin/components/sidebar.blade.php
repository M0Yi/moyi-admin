@php
    use App\Model\Admin\AdminMenu;
    use App\Model\Admin\AdminPermission;
    use App\Model\Admin\AdminUser;
    use Hyperf\Context\Context;

    // 菜单已解耦站点，全局共享
    $sidebarMenus = AdminMenu::query()
        ->where('status', 1)
        ->where('visible', 1)
        ->orderBy('sort', 'asc')
        ->orderBy('id', 'asc')
        ->get()
        ->toArray();
    $sidebarMenus = AdminMenu::buildTree($sidebarMenus);

    $currentUser = Context::get('admin_user');
    $userId = null;
    $isSuperAdmin = false;

    if ($currentUser instanceof AdminUser) {
        $userId = (int) $currentUser->id;
        $isSuperAdmin = (int) $currentUser->is_admin === 1;
    } elseif (is_array($currentUser)) {
        $userId = isset($currentUser['id']) ? (int) $currentUser['id'] : null;
        $isSuperAdmin = (int) ($currentUser['is_admin'] ?? 0) === 1;
    }

    if ($userId === null) {
        try {
            $guard = auth('admin');
            if ($guard && $guard->check()) {
                $authUser = $guard->user();
                if ($authUser instanceof AdminUser) {
                    $userId = (int) $authUser->id;
                    $isSuperAdmin = (int) $authUser->is_admin === 1;
                } elseif (is_array($authUser)) {
                    $userId = isset($authUser['id']) ? (int) $authUser['id'] : null;
                    $isSuperAdmin = (int) ($authUser['is_admin'] ?? 0) === 1;
                }
            }
        } catch (\Throwable $exception) {
            $userId = null;
        }
    }

    $allowedPermissions = [];
    if (! $isSuperAdmin && $userId !== null) {
        // 角色和权限已解耦站点，全局共享
        $allowedPermissions = AdminPermission::query()
            ->select('slug')
            ->where('status', 1)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->whereHas('roles', function ($roleQuery) use ($userId) {
                $roleQuery->where('status', 1)
                    ->whereHas('users', static function ($userQuery) use ($userId) {
                        $userQuery->where('admin_users.id', $userId);
                    });
            })
            ->pluck('slug')
            ->toArray();

        $allowedPermissions = array_values(array_unique(array_filter($allowedPermissions)));
    }

    if (! $isSuperAdmin && $sidebarMenus !== []) {
        $permissionLookup = $allowedPermissions !== [] ? array_fill_keys($allowedPermissions, true) : [];

        $normalizePermissionSlugs = static function (mixed $raw): array {
            if ($raw === null || $raw === '') {
                return [];
            }

            if (is_array($raw)) {
                $normalized = array_map(static fn ($value) => trim((string) $value), $raw);
            } else {
                $parts = preg_split('/[,\|]+/', (string) $raw) ?: [];
                $normalized = array_map(static fn ($value) => trim($value), $parts);
            }

            return array_values(array_filter($normalized, static fn ($value) => $value !== ''));
        };

        $hasMenuPermission = static function (array $slugs) use ($permissionLookup): bool {
            if ($slugs === []) {
                return true;
            }

            if ($permissionLookup === []) {
                return false;
            }

            foreach ($slugs as $slug) {
                if (isset($permissionLookup[$slug])) {
                    return true;
                }
            }

            return false;
        };

        $filterMenusByPermission = static function (array $menus) use (
            &$filterMenusByPermission,
            $normalizePermissionSlugs,
            $hasMenuPermission
        ): array {
            $filtered = [];

            foreach ($menus as $menu) {
                $children = $menu['children'] ?? [];
                if (! empty($children)) {
                    $menu['children'] = $filterMenusByPermission($children);
                }

                $menuSlugs = $normalizePermissionSlugs($menu['permission'] ?? null);
                $hasPermission = $hasMenuPermission($menuSlugs);
                $hasVisibleChildren = isset($menu['children']) && count($menu['children']) > 0;

                if ($menu['type'] === AdminMenu::TYPE_GROUP) {
                    if ($hasVisibleChildren) {
                        $filtered[] = $menu;
                    }
                    continue;
                }

                if ($menu['type'] === AdminMenu::TYPE_DIVIDER) {
                    $filtered[] = $menu;
                    continue;
                }

                if ($hasPermission || $hasVisibleChildren) {
                    $filtered[] = $menu;
                }
            }

            $cleaned = [];
            foreach ($filtered as $item) {
                if ($item['type'] === AdminMenu::TYPE_DIVIDER) {
                    if ($cleaned === []) {
                        continue;
                    }

                    if ($cleaned[count($cleaned) - 1]['type'] === AdminMenu::TYPE_DIVIDER) {
                        continue;
                    }
                }

                $cleaned[] = $item;
            }

            while (! empty($cleaned) && end($cleaned)['type'] === AdminMenu::TYPE_DIVIDER) {
                array_pop($cleaned);
            }

            return $cleaned;
        };

        $sidebarMenus = $filterMenusByPermission($sidebarMenus);
    }

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
                <li class="nav-item w-100">
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

@push('admin_sidebar_scripts')
<script>
(function() {
    'use strict';

    // 调试日志函数
    const log = {
        info: (...args) => console.log('[Sidebar-Mobile]', new Date().toISOString(), ...args),
        warn: (...args) => console.warn('[Sidebar-Mobile]', new Date().toISOString(), ...args),
        error: (...args) => console.error('[Sidebar-Mobile]', new Date().toISOString(), ...args)
    };

    log.info('移动端侧边栏脚本初始化');

    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggleMobile = document.getElementById('sidebarToggleMobile');

    log.info('DOM 元素检查:', {
        sidebar: !!sidebar,
        sidebarBackdrop: !!sidebarBackdrop,
        sidebarToggleMobile: !!sidebarToggleMobile
    });

    // 移动端侧边栏切换按钮点击事件
    if (sidebarToggleMobile) {
        log.info('移动端侧边栏切换按钮已找到');
        sidebarToggleMobile.addEventListener('click', function(e) {
            log.info('移动端侧边栏切换点击');
            if (sidebar) {
                sidebar.classList.add('show');
            }
            if (sidebarBackdrop) {
                sidebarBackdrop.classList.add('show');
            }
            log.info('移动端侧边栏已显示');
        });
    } else {
        log.warn('移动端侧边栏切换按钮未找到');
        // 尝试查找其他可能的元素
        const fallbackBtn = document.querySelector('.sidebar-toggle-mobile');
        log.info('备选查找 .sidebar-toggle-mobile:', !!fallbackBtn);
        if (fallbackBtn) {
            fallbackBtn.addEventListener('click', function(e) {
                log.info('备选按钮点击');
                if (sidebar) {
                    sidebar.classList.add('show');
                }
                if (sidebarBackdrop) {
                    sidebarBackdrop.classList.add('show');
                }
            });
        }
    }

    // 遮罩层点击事件
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function() {
            log.info('遮罩层点击，关闭侧边栏');
            if (sidebar) {
                sidebar.classList.remove('show');
            }
            sidebarBackdrop.classList.remove('show');
        });
    }

    // 窗口大小变化事件
    window.addEventListener('resize', function() {
        const width = window.innerWidth;
        log.info('窗口尺寸变化:', width);
        if (width > 768) {
            // 进入桌面端：隐藏移动端侧边栏
            if (sidebar) {
                sidebar.classList.remove('show');
            }
            if (sidebarBackdrop) {
                sidebarBackdrop.classList.remove('show');
            }
            log.info('窗口大于 768px，隐藏移动端侧边栏');
        } else {
            // 进入移动端：移除 .collapsed 类
            if (sidebar && sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                log.info('进入移动端，移除 .collapsed 类');
            }
        }
    });

    // 移动端：点击菜单项后关闭侧边栏
    if (sidebar && window.innerWidth <= 768) {
        const navLinks = sidebar.querySelectorAll('.nav-link');
        log.info('移动端菜单项数量:', navLinks.length);
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const hasChildren = link.classList.contains('has-children');
                const title = link.getAttribute('data-tab-title') || link.textContent?.trim() || '未知';
                log.info('菜单项点击:', title, 'hasChildren:', hasChildren);

                // 对于有子菜单的父级链接，只展开子菜单，不关闭侧边栏
                if (hasChildren) {
                    log.info('父级菜单，只展开子菜单');
                    return;
                }
                // 延迟关闭，让导航先发生
                setTimeout(() => {
                    if (sidebar) {
                        sidebar.classList.remove('show');
                    }
                    if (sidebarBackdrop) {
                        sidebarBackdrop.classList.remove('show');
                    }
                    log.info('移动端侧边栏已关闭');
                }, 150);
            });
        });
    }

    log.info('移动端侧边栏脚本初始化完成');
})();
</script>
@endpush

@push('admin_shell_scripts')
<script>
(function() {
    'use strict';

    // 调试日志函数
    const log = {
        info: (...args) => console.log('[Sidebar]', new Date().toISOString(), ...args),
        warn: (...args) => console.warn('[Sidebar]', new Date().toISOString(), ...args),
        error: (...args) => console.error('[Sidebar]', new Date().toISOString(), ...args)
    };

    log.info('侧边栏脚本初始化（桌面端）');

    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggle = document.getElementById('sidebarToggle');

    log.info('DOM 元素检查:', {
        sidebar: !!sidebar,
        sidebarBackdrop: !!sidebarBackdrop,
        sidebarToggle: !!sidebarToggle
    });

    // 桌面端侧边栏切换按钮
    if (sidebarToggle) {
        log.info('桌面端侧边栏切换按钮已找到');
        sidebarToggle.addEventListener('click', function() {
            log.info('桌面端侧边栏切换点击');
            sidebar.classList.toggle('collapsed');
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed ? '1' : '0');
            log.info('侧边栏状态:', isCollapsed ? '收起' : '展开');
        });
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === '1') {
            sidebar.classList.add('collapsed');
            log.info('恢复侧边栏收起状态');
        }
    } else {
        log.warn('桌面端侧边栏切换按钮未找到');
    }

    const getSubmenuFromLink = (link) => {
        let targetSelector = link.getAttribute('data-target');
        if (!targetSelector) {
            return null;
        }
        if (!targetSelector.startsWith('#')) {
            targetSelector = `#${targetSelector}`;
        }
        return sidebar.querySelector(targetSelector);
    };

    const rotateArrow = (link, expanded) => {
        const arrow = link.querySelector('.submenu-arrow');
        if (arrow) {
            arrow.style.transform = expanded ? 'rotate(180deg)' : 'rotate(0deg)';
        }
        link.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };

    const collapseNestedSubmenus = (submenuElement) => {
        const nestedLinks = submenuElement.querySelectorAll('.nav-link.has-children');
        nestedLinks.forEach(nestedLink => {
            const nestedSubmenu = getSubmenuFromLink(nestedLink);
            if (nestedSubmenu) {
                nestedSubmenu.classList.remove('show');
            }
            rotateArrow(nestedLink, false);
        });
    };

    const closeSubmenu = (link, options = {}) => {
        const { collapseChildren = true } = options;
        const submenu = getSubmenuFromLink(link);
        if (!submenu) {
            return;
        }
        submenu.classList.remove('show');
        if (collapseChildren) {
            collapseNestedSubmenus(submenu);
        }
        rotateArrow(link, false);
    };

    const collapseSiblingSubmenus = (link, options = {}) => {
        const parentList = link.closest('ul');
        if (!parentList) {
            return;
        }
        Array.from(parentList.children).forEach(listItem => {
            const siblingLink = listItem.querySelector('.nav-link.has-children');
            if (siblingLink && siblingLink !== link) {
                closeSubmenu(siblingLink, options);
            }
        });
    };

    // 分组展开/折叠功能
    const groupHeaders = sidebar.querySelectorAll('[data-group-toggle]');
    log.info('分组标题数量:', groupHeaders.length);
    groupHeaders.forEach(header => {
        const groupId = header.getAttribute('data-target');
        if (!groupId) return;

        // 从 localStorage 读取分组状态
        const savedState = localStorage.getItem(`sidebarGroup_${groupId.replace('#', '')}`);
        const shouldExpand = savedState !== 'collapsed';
        
        const groupContent = sidebar.querySelector(groupId);
        if (groupContent) {
            if (!shouldExpand) {
                groupContent.classList.remove('show');
                header.setAttribute('aria-expanded', 'false');
                const arrow = header.querySelector('.sidebar-group-arrow');
                if (arrow) {
                    arrow.style.transform = 'rotate(-90deg)';
                }
            }
        }

        header.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const groupContent = sidebar.querySelector(groupId);
            if (!groupContent) {
                log.warn('未找到分组内容:', groupId);
                return;
            }

            const isExpanded = groupContent.classList.contains('show');
            const groupTitle = header.querySelector('.sidebar-group-title')?.textContent || '未知分组';
            log.info('分组点击:', groupTitle, '当前状态:', isExpanded ? '展开' : '收起');
            const arrow = header.querySelector('.sidebar-group-arrow');
            
            if (isExpanded) {
                groupContent.classList.remove('show');
                header.setAttribute('aria-expanded', 'false');
                if (arrow) {
                    arrow.style.transform = 'rotate(-90deg)';
                }
                localStorage.setItem(`sidebarGroup_${groupId.replace('#', '')}`, 'collapsed');
            } else {
                groupContent.classList.add('show');
                header.setAttribute('aria-expanded', 'true');
                if (arrow) {
                    arrow.style.transform = 'rotate(0deg)';
                }
                localStorage.removeItem(`sidebarGroup_${groupId.replace('#', '')}`);
            }
        });
    });

    // 子菜单展开/折叠功能
    const submenuLinks = sidebar.querySelectorAll('.nav-link.has-children');
    log.info('子菜单父级链接数量:', submenuLinks.length);
    submenuLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const submenu = getSubmenuFromLink(link);
            if (!submenu) {
                log.warn('未找到子菜单');
                return;
            }

            const isExpanded = submenu.classList.contains('show');
            const menuTitle = link.getAttribute('data-tab-title') || link.textContent?.trim() || '未知';
            log.info('子菜单点击:', menuTitle, '当前状态:', isExpanded ? '展开' : '收起');

            if (isExpanded) {
                closeSubmenu(link);
            } else {
                collapseSiblingSubmenus(link, { collapseChildren: false });
                submenu.classList.add('show');
                rotateArrow(link, true);
            }
        });
    });

    // 高亮当前活动菜单项（仅在非 iframe 模式下）
    if (window.self === window.top) {
        log.info('非 iframe 模式，设置活动菜单高亮');
        if (window.Admin && window.Admin.utils && typeof window.Admin.utils.setSidebarActiveByUrl === 'function') {
            window.Admin.utils.setSidebarActiveByUrl(window.location.pathname);
        }
    } else {
        log.info('iframe 模式，跳过高亮设置');
    }

    log.info('侧边栏脚本初始化完成');
})();
</script>
@endpush

@push('admin_sidebar_nav_actions_desktop')
<button class="btn btn-link text-secondary sidebar-toggle-desktop" type="button" id="sidebarToggle">
    <i class="bi bi-layout-sidebar-inset fs-5"></i>
</button>
@endpush

@push('admin_sidebar_nav_actions_mobile')
<button class="btn btn-link text-secondary sidebar-toggle-mobile me-2" type="button" id="sidebarToggleMobile">
    <i class="bi bi-list fs-4"></i>
</button>
@endpush
