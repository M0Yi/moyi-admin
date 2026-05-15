{{--
菜单项组件
支持多级嵌套、图标、徽章、分割线等
--}}

@php
    $level = isset($level) ? (int) $level : 0;
    $nextLevel = $level + 1;
@endphp

@if($menu['type'] === 'divider')
    {{-- 分割线 --}}
    <hr class="my-3 mx-3" style="border-color: #e2e8f0;">

@elseif($menu['type'] === 'group')
    {{-- 菜单分组（支持展开/收缩） --}}
    @php
        $hasGroupChildren = isset($menu['children']) && count($menu['children']) > 0;
        $groupId = $hasGroupChildren ? 'group-' . md5(($menu['title'] ?? '') . '-' . ($menu['id'] ?? uniqid('', true))) : null;
        // 默认展开分组（可以通过 localStorage 控制）
        $defaultExpanded = true;
    @endphp
    
    @if(!empty($menu['title']) && $menu['title'] !== '-')
        @if($hasGroupChildren && $groupId)
            {{-- 可展开/收缩的分组标题 --}}
            <div class="sidebar-group-header" 
                 data-group-toggle
                 data-target="#{{ $groupId }}"
                 role="button"
                 aria-expanded="true"
                 aria-controls="{{ $groupId }}">
                {{-- 分组图标（优先显示自定义图标） --}}
                @if(!empty($menu['icon']))
                    @if(str_starts_with($menu['icon'], 'bi '))
                        <i class="{{ $menu['icon'] }} group-icon" aria-hidden="true"></i>
                    @elseif(str_starts_with($menu['icon'], '#'))
                        <svg class="bi group-icon" width="18" height="18" aria-hidden="true">
                            <use xlink:href="{{ $menu['icon'] }}"/>
                        </svg>
                    @else
                        <i class="{{ $menu['icon'] }} group-icon" aria-hidden="true"></i>
                    @endif
                @else
                    {{-- 如果没有图标，保留一个占位以保证排版稳定 --}}
                    <i class="bi bi-circle group-icon" aria-hidden="true" style="opacity:0.12;"></i>
                @endif

                <span class="sidebar-group-title">{{ $menu['title'] }}</span>
                <i class="bi bi-chevron-down sidebar-group-arrow" style="font-size: 0.75rem; transition: transform 0.2s ease;"></i>
            </div>
        @else
            {{-- 不可展开的分组标题（没有子菜单） --}}
            <div class="sidebar-heading">{{ $menu['title'] }}</div>
        @endif
    @endif

    {{-- 渲染子菜单 --}}
    @if($hasGroupChildren)
        <ul class="nav flex-column sidebar-group-content {{ $groupId ? 'collapse show' : '' }}" 
            id="{{ $groupId }}"
            data-group-id="{{ $groupId }}">
            @foreach($menu['children'] as $child)
                @include('admin.components.sidebar-menu-item', ['menu' => $child])
            @endforeach
        </ul>
    @endif

@else
    {{-- 普通菜单项 或 外部链接 --}}
    <li class="nav-item w-100" data-menu-level="{{ $level }}" style="--menu-level: {{ $level }};">
        @php
            $isExternalLink = $menu['type'] === 'link';
            $hasChildren = isset($menu['children']) && count($menu['children']) > 0;
            $linkHref = $isExternalLink ? ($menu['path'] ?? '#') : admin_route((string) ($menu['path'] ?? ''));
            // 有子菜单的项不应该打开 tab，而是展开/折叠
            $shouldOpenTab = !$hasChildren && !$isExternalLink;
            $submenuId = $hasChildren ? 'submenu-' . md5(($menu['path'] ?? '') . '-' . ($menu['id'] ?? uniqid('', true))) : null;
        @endphp
        <a class="nav-link d-flex align-items-center gap-2 {{ $hasChildren ? 'has-children' : '' }}"
           href="{{ $hasChildren ? 'javascript:void(0);' : $linkHref }}"
           target="{{ $menu['target'] ?? '_self' }}"
           data-path="{{ $menu['path'] }}"
           data-admin-tab="{{ $shouldOpenTab ? '1' : '0' }}"
           data-tab-mode="{{ $isExternalLink ? 'external' : 'internal' }}"
           data-tab-title="{{ $menu['title'] ?? '' }}"
           data-title="{{ $menu['title'] ?? '' }}"
           @if($hasChildren)
               data-toggle="collapse"
               data-target="#{{ $submenuId }}"
               role="button"
               aria-expanded="false"
               aria-controls="{{ $submenuId }}"
           @endif>

            {{-- 图标 --}}
            @if(!empty($menu['icon']))
                @if(str_starts_with($menu['icon'], 'bi '))
                    {{-- Bootstrap Icons --}}
                    <i class="{{ $menu['icon'] }}" ></i>
                @elseif(str_starts_with($menu['icon'], '#'))
                    {{-- SVG Symbol --}}
                    <svg class="bi" width="16" height="16" style="flex-shrink: 0;">
                        <use xlink:href="{{ $menu['icon'] }}"/>
                    </svg>
                @else
                    {{-- 其他图标 --}}
                    <i class="{{ $menu['icon'] }}"></i>
                @endif
            @else
                {{-- 默认图标占位 --}}
                <i class="bi bi-circle" ></i>
            @endif

            {{-- 菜单标题 --}}
            <span>{{ $menu['title'] }}</span>

            {{-- 徽章 --}}
            @if(!empty($menu['badge']))
                <span class="badge bg-{{ $menu['badge_type'] ?? 'primary' }} ms-auto">
                    {{ $menu['badge'] }}
                </span>
            @endif

            {{-- 子菜单箭头 --}}
            @if($hasChildren)
                <i class="bi bi-chevron-down ms-auto submenu-arrow" style="font-size: 0.75rem; transition: transform 0.2s ease;"></i>
            @endif
        </a>

        {{-- 子菜单 --}}
        @if($hasChildren && $submenuId)
            <ul class="nav flex-column ms-3 collapse sidebar-submenu"
                id="{{ $submenuId }}"
                data-menu-level="{{ $nextLevel }}"
                style="--menu-level: {{ $nextLevel }};">
                @foreach($menu['children'] as $child)
                    @include('admin.components.sidebar-menu-item', ['menu' => $child, 'level' => $nextLevel])
                @endforeach
            </ul>
        @endif
    </li>
@endif

