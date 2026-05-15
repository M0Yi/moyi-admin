{{--
/**
 * 操作按钮工具栏组件
 *
 * @param array $buttons 主操作按钮配置数组
 *   - type: 'link' | 'button'
 *   - href: 链接地址（type=link时）
 *   - text: 按钮文字
 *   - icon: Bootstrap Icon 类名
 *   - variant: 按钮样式 'primary' | 'light' | 'outline-secondary'
 *   - onclick: 点击事件（type=button时）
 *   - attributes: 额外属性数组，如 ['data-track' => 'xxx']
 *
 * @param array $rightButtons 右侧辅助按钮配置数组（可选）
 *   - icon: Bootstrap Icon 类名
 *   - title: 提示文字
 *   - onclick: 点击事件
 *   - id: 按钮ID（可选）
 *
 * @param string $slot 自定义内容插槽（可选）
 *
 * 使用示例：
 * @include('admin.components.action-toolbar', [
 *     'buttons' => [
 *         ['type' => 'link', 'href' => admin_route('users/create'), 'text' => '新建用户', 'icon' => 'bi-plus-lg', 'variant' => 'primary'],
 *         ['type' => 'button', 'text' => '刷新缓存', 'icon' => 'bi-arrow-clockwise', 'variant' => 'light', 'onclick' => 'refreshCache()'],
 *     ],
 *     'rightButtons' => [
 *         ['icon' => 'bi-arrow-repeat', 'title' => '刷新', 'onclick' => 'window.location.reload()'],
 *     ]
 * ])
 */
--}}

<!-- 操作按钮工具栏 -->
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <!-- 主操作按钮 -->
        <div class="d-flex gap-2">
            @foreach($buttons ?? [] as $button)
                @if(($button['type'] ?? 'button') === 'link')
                    {{-- 链接类型按钮 --}}
                    @php
                        $attributes = $button['attributes'] ?? [];
                    @endphp
                    <a
                        href="{{ $button['href'] ?? '#' }}"
                        class="btn btn-{{ $button['variant'] ?? 'primary' }} px-4 py-2 shadow-sm"
                        style="border-radius: 10px;{{ ($button['variant'] ?? 'primary') === 'light' ? ' border: 1px solid #e9ecef;' : '' }}"
                        @foreach($attributes as $attr => $value)
                            {{ $attr }}="{{ htmlspecialchars($value, ENT_QUOTES, 'UTF-8') }}"
                        @endforeach
                    >
                        @if(isset($button['icon']))
                            <i class="bi {{ $button['icon'] }} me-2"></i>
                        @endif
                        <span class="{{ ($button['variant'] ?? 'primary') === 'primary' ? 'fw-medium' : '' }}">{{ $button['text'] ?? '' }}</span>
                    </a>
                @else
                    {{-- 按钮类型 --}}
                    @php
                        $attributes = $button['attributes'] ?? [];
                    @endphp
                    <button
                        type="button"
                        class="btn btn-{{ $button['variant'] ?? 'light' }} px-4 py-2 shadow-sm"
                        style="border-radius: 10px;{{ ($button['variant'] ?? 'light') === 'light' ? ' border: 1px solid #e9ecef;' : '' }}"
                        @if(isset($button['onclick'])) onclick="{{ $button['onclick'] }}" @endif
                        @foreach($attributes as $attr => $value)
                            {{ $attr }}="{{ htmlspecialchars($value, ENT_QUOTES, 'UTF-8') }}"
                        @endforeach
                    >
                        @if(isset($button['icon']))
                            <i class="bi {{ $button['icon'] }} me-2"></i>
                        @endif
                        <span>{{ $button['text'] ?? '' }}</span>
                    </button>
                @endif
            @endforeach

            {{-- 自定义主操作按钮插槽 --}}
            @if(isset($slot))
                {!! $slot !!}
            @endif
        </div>

        <!-- 辅助操作按钮 -->
        @if(isset($rightButtons) && count($rightButtons) > 0)
            <div class="d-flex gap-2">
                @foreach($rightButtons as $button)
                    <button
                        type="button"
                        class="btn btn-outline-secondary px-3 py-2"
                        style="border-radius: 10px;"
                        @if(isset($button['id'])) id="{{ $button['id'] }}" @endif
                        @if(isset($button['title'])) title="{{ $button['title'] }}" @endif
                        @if(isset($button['onclick'])) onclick="{{ $button['onclick'] }}" @endif
                    >
                        @if(isset($button['icon']))
                            <i class="bi {{ $button['icon'] }}"></i>
                        @endif
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</div>

