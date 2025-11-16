@push('admin_styles')
<style>
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
.dropdown-menu {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.5rem;
}
.dropdown-item {
    border-radius: 8px;
    color: #1e293b;
}
.dropdown-item:hover {
    background: #f1f5f9;
}
</style>
@endpush

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="{{ admin_route('dashboard') }}">
            {{ site()?->name ?? '管理后台' }}
        </a>

        @stack('admin_sidebar_nav_actions')

        <div class="ms-auto">
            <div class="dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle fs-4 me-2" style="color: #64748b;"></i>
                    <span class="text-secondary">{{ auth('admin')->user()['username'] ?? '管理员' }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenu">
                    <li><a class="dropdown-item d-flex align-items-center" href="{{ admin_route('dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>仪表盘</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item d-flex align-items-center text-danger" href="{{ admin_route('logout') }}"><i class="bi bi-box-arrow-right me-2"></i>退出登录</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
