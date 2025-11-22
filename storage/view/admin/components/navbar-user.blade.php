@php
    $menuId = $userMenuId ?? ('userMenu' . uniqid());
    $dropdownClasses = trim('admin-tab-user dropdown ' . ($dropdownClass ?? ''));
@endphp

<div class="{{ $dropdownClasses }}">
    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="{{ $menuId }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="admin-tab-user__avatar">
            <i class="bi bi-person"></i>
        </span>
        <span class="admin-tab-user__name">{{ auth('admin')->user()['username'] ?? '管理员' }}</span>
    </a>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="{{ $menuId }}">
        <li>
            <a class="dropdown-item d-flex align-items-center" href="{{ admin_route('dashboard') }}">
                <i class="bi bi-speedometer2 me-2"></i>仪表盘
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item d-flex align-items-center text-danger" href="{{ admin_route('logout') }}">
                <i class="bi bi-box-arrow-right me-2"></i>退出登录
            </a>
        </li>
    </ul>
</div>
















