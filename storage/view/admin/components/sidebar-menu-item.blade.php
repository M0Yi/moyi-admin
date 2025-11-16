{{--
菜单项组件
支持多级嵌套、图标、徽章、分割线等
--}}

@if($menu['type'] === 'divider')
    {{-- 分割线 --}}
    <hr class="my-3 mx-3" style="border-color: #e2e8f0;">

@elseif($menu['type'] === 'group')
    {{-- 菜单分组 --}}
    @if(!empty($menu['title']) && $menu['title'] !== '-')
        <div class="sidebar-heading">{{ $menu['title'] }}</div>
    @endif

    {{-- 渲染子菜单 --}}
    @if(isset($menu['children']) && count($menu['children']) > 0)
        @foreach($menu['children'] as $child)
            @include('admin.components.sidebar-menu-item', ['menu' => $child])
        @endforeach
    @endif

@else
    {{-- 普通菜单项 或 外部链接 --}}
    <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-2"
           href="{{ $menu['type'] === 'link' ? $menu['path'] : admin_route($menu['path']) }}"
           target="{{ $menu['target'] ?? '_self' }}"
           data-path="{{ $menu['path'] }}">

            {{-- 图标 --}}
            @if(!empty($menu['icon']))
                @if(str_starts_with($menu['icon'], 'bi '))
                    {{-- Bootstrap Icons --}}
                    <i class="{{ $menu['icon'] }}" style="width: 16px; height: 16px; flex-shrink: 0;"></i>
                @elseif(str_starts_with($menu['icon'], '#'))
                    {{-- SVG Symbol --}}
                    <svg class="bi" width="16" height="16" style="flex-shrink: 0;">
                        <use xlink:href="{{ $menu['icon'] }}"/>
                    </svg>
                @else
                    {{-- 其他图标 --}}
                    <i class="{{ $menu['icon'] }}" style="width: 16px; height: 16px; flex-shrink: 0;"></i>
                @endif
            @else
                {{-- 默认图标占位 --}}
                <i class="bi bi-circle" style="width: 16px; height: 16px; flex-shrink: 0; opacity: 0.3;"></i>
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
            @if(isset($menu['children']) && count($menu['children']) > 0)
                <i class="bi bi-chevron-down ms-auto" style="font-size: 0.75rem;"></i>
            @endif
        </a>

        {{-- 子菜单 --}}
        @if(isset($menu['children']) && count($menu['children']) > 0)
            <ul class="nav flex-column ms-3">
                @foreach($menu['children'] as $child)
                    @include('admin.components.sidebar-menu-item', ['menu' => $child])
                @endforeach
            </ul>
        @endif
    </li>
@endif

