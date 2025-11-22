<nav class="navbar admin-topbar">
    <div class="admin-topbar-inner">
        <div class="admin-topbar-layout admin-topbar-layout--desktop">
            <div class="admin-tab-toolbar">
                <div class="topbar-actions d-flex align-items-center flex-wrap gap-2">
                    @stack('admin_sidebar_nav_actions')
                </div>
                <div class="admin-tab-scroll">
                    <div class="admin-tab-scroll-list" data-role="tab-list"></div>
                    <div class="admin-tab-scroll-action">
                        <button type="button" class="admin-tab-action" data-action="refresh" title="刷新当前标签">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    </div>
                </div>
                <div class="admin-tab-actions">
                    @include('admin.components.navbar-user', ['userMenuId' => 'userMenuDesktop'])
                </div>
            </div>
        </div>
        <div class="admin-topbar-layout admin-topbar-layout--mobile">
            <div class="admin-topbar-mobile-left">
                <div class="topbar-actions d-flex align-items-center flex-wrap gap-2">
                    @stack('admin_sidebar_nav_actions')
                </div>
            </div>
            <div class="admin-topbar-mobile-right">
                @include('admin.components.navbar-user', [
                    'userMenuId' => 'userMenuMobile',
                    'dropdownClass' => 'admin-tab-user--mobile'
                ])
            </div>
        </div>
    </div>
</nav>
