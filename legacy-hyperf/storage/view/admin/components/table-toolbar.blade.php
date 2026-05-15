{{--
/**
 * 表格工具栏组件（独立组件，支持完全自定义）
 *
 * === 参数说明 ===
 *
 * @param array $leftButtons 左侧主操作按钮配置数组
 *   - type: 'link' | 'button'（默认 'button'）
 *   - href: 链接地址（type=link时必填）
 *   - text: 按钮文字
 *   - icon: Bootstrap Icon 类名
 *   - variant: 按钮样式 'primary' | 'light' | 'outline-secondary' | 'danger' | 'warning' | 'info' | 'success'
 *   - onclick: 点击事件（type=button时）
 *   - id: 按钮ID（可选）
 *   - class: 自定义CSS类（可选）
 *   - title: 提示文字（可选）
 *   - attributes: 额外属性数组（可选，如 data-iframe-shell-*）
 *
 * @param array $rightButtons 右侧辅助按钮配置数组
 *   - icon: Bootstrap Icon 类名
 *   - title: 提示文字
 *   - onclick: 点击事件
 *   - id: 按钮ID（可选）
 *   - variant: 按钮样式（默认 'outline-secondary'）
 *   - class: 自定义CSS类（可选）
 *   - attributes: 额外属性数组（可选）
 *
 * @param string $leftSlot 左侧自定义内容插槽（HTML字符串，可选）
 *   用于完全自定义左侧工具栏内容，优先级高于 leftButtons
 *
 * @param string $rightSlot 右侧自定义内容插槽（HTML字符串，可选）
 *   用于完全自定义右侧工具栏内容，优先级高于 rightButtons
 *
 * @param bool $showColumnToggle 是否显示列显示控制按钮（默认 true）
 * @param bool $showSearch 是否显示搜索按钮（默认 true）
 *
 * @param string $tableId 表格ID（用于搜索按钮和列切换按钮）
 * @param string $storageKey localStorage 存储键（用于列切换）
 * @param array $columns 列配置数组（用于列切换）
 *
 * === 使用示例 ===
 *
 * 示例1：使用默认按钮配置
 * @include('admin.components.table-toolbar', [
 *     'tableId' => 'dataTable',
 *     'leftButtons' => [
 *         ['type' => 'link', 'href' => '/admin/users/create', 'text' => '添加用户', 'icon' => 'bi-plus-lg', 'variant' => 'primary'],
 *     ],
 *     'rightButtons' => [
 *         ['icon' => 'bi-download', 'title' => '导出', 'onclick' => 'exportData()'],
 *     ],
 * ])
 *
 * 示例2：完全自定义左侧工具栏
 * @include('admin.components.table-toolbar', [
 *     'tableId' => 'dataTable',
 *     'leftSlot' => '
 *         <button class="btn btn-primary">自定义按钮1</button>
 *         <button class="btn btn-secondary">自定义按钮2</button>
 *         <div class="dropdown">
 *             <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">更多操作</button>
 *             <ul class="dropdown-menu">
 *                 <li><a class="dropdown-item" href="#">操作1</a></li>
 *                 <li><a class="dropdown-item" href="#">操作2</a></li>
 *             </ul>
 *         </div>
 *     ',
 *     'rightButtons' => [
 *         ['icon' => 'bi-arrow-repeat', 'title' => '刷新', 'onclick' => 'loadData()'],
 *     ],
 * ])
 *
 * 示例3：完全自定义整个工具栏
 * @include('admin.components.table-toolbar', [
 *     'tableId' => 'dataTable',
 *     'leftSlot' => '<div class="custom-toolbar-left">...</div>',
 *     'rightSlot' => '<div class="custom-toolbar-right">...</div>',
 *     'showColumnToggle' => false,
 *     'showSearch' => false,
 * ])
 */
--}}

@if(isset($leftSlot) || isset($rightSlot) || !empty($leftButtons) || !empty($rightButtons) || ($showColumnToggle ?? true) || ($showSearch ?? true))
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <!-- 左侧工具栏 -->
            <div class="d-flex gap-2">
                @if(isset($leftSlot))
                    {{-- 完全自定义左侧工具栏 --}}
                    {!! $leftSlot !!}
                @else
                    {{-- 使用 leftButtons 配置渲染按钮 --}}
                    @foreach($leftButtons ?? [] as $button)
                        @php
                            $buttonType = $button['type'] ?? 'button';
                            $buttonVariant = $button['variant'] ?? ($buttonType === 'link' ? 'primary' : 'light');
                            $buttonClass = $button['class'] ?? '';
                            $buttonId = $button['id'] ?? '';
                            $buttonTitle = $button['title'] ?? '';
                            $buttonAttributes = [];
                            if (!empty($button['attributes']) && is_array($button['attributes'])) {
                                $buttonAttributes = $button['attributes'];
                            }
                        @endphp

                        @if($buttonType === 'link')
                            {{-- 链接类型按钮 --}}
                            <a
                                href="{{ $button['href'] ?? '#' }}"
                                class="btn btn-{{ $buttonVariant }} px-4 py-2 shadow-sm {{ $buttonClass }}"
                                style="border-radius: 10px;{{ $buttonVariant === 'light' ? ' border: 1px solid #e9ecef;' : '' }}"
                                @if($buttonId) id="{{ $buttonId }}" @endif
                                @if($buttonTitle) title="{{ $buttonTitle }}" @endif
                                @foreach($buttonAttributes as $attr => $value)
                                    {{ $attr }}="{{ htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') }}"
                                @endforeach
                            >
                                @if(isset($button['icon']))
                                    <i class="bi {{ $button['icon'] }} me-2"></i>
                                @endif
                                <span class="{{ $buttonVariant === 'primary' ? 'fw-medium' : '' }}">{{ $button['text'] ?? '' }}</span>
                            </a>
                        @else
                            {{-- 按钮类型 --}}
                            <button
                                type="button"
                                class="btn btn-{{ $buttonVariant }} px-4 py-2 shadow-sm {{ $buttonClass }}"
                                style="border-radius: 10px;{{ $buttonVariant === 'light' ? ' border: 1px solid #e9ecef;' : '' }}"
                                @if($buttonId) id="{{ $buttonId }}" @endif
                                @if($buttonTitle) title="{{ $buttonTitle }}" @endif
                                @if(isset($button['onclick'])) onclick="{{ $button['onclick'] }}" @endif
                                @foreach($buttonAttributes as $attr => $value)
                                    {{ $attr }}="{{ htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') }}"
                                @endforeach
                            >
                                @if(isset($button['icon']))
                                    <i class="bi {{ $button['icon'] }} me-2"></i>
                                @endif
                                <span>{{ $button['text'] ?? '' }}</span>
                            </button>
                        @endif
                    @endforeach
                @endif
            </div>

            <!-- 右侧工具栏 -->
            <div class="d-flex gap-2 align-items-center">
                @if(isset($rightSlot))
                    {{-- 完全自定义右侧工具栏 --}}
                    {!! $rightSlot !!}
                @else
                    {{-- 使用 rightButtons 配置渲染按钮 --}}
                    @foreach($rightButtons ?? [] as $button)
                        @php
                            $buttonVariant = $button['variant'] ?? 'outline-secondary';
                            $buttonClass = $button['class'] ?? '';
                            $buttonId = $button['id'] ?? '';
                            $buttonTitle = $button['title'] ?? '';
                            $buttonAttributes = [];
                            if (!empty($button['attributes']) && is_array($button['attributes'])) {
                                $buttonAttributes = $button['attributes'];
                            }
                        @endphp
                        <button
                            type="button"
                            class="btn btn-{{ $buttonVariant }} px-3 py-2 {{ $buttonClass }}"
                            style="border-radius: 10px;"
                            @if($buttonId) id="{{ $buttonId }}" @endif
                            @if($buttonTitle) title="{{ $buttonTitle }}" @endif
                            @if(isset($button['onclick'])) onclick="{{ $button['onclick'] }}" @endif
                            @foreach($buttonAttributes as $attr => $value)
                                {{ $attr }}="{{ htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') }}"
                            @endforeach
                        >
                            @if(isset($button['icon']))
                                <i class="bi {{ $button['icon'] }}"></i>
                            @endif
                            @if(isset($button['text']))
                                <span class="ms-1">{{ $button['text'] }}</span>
                            @endif
                        </button>
                    @endforeach

                    {{-- 列显示控制 Dropdown（默认显示） --}}
                    @if($showColumnToggle ?? true)
                        @php
                            $toggleableColumns = array_values(array_filter($columns ?? [], function($col) {
                                return ($col['toggleable'] ?? true) === true;
                            }));
                        @endphp
                        @if(!empty($toggleableColumns))
                            @include('admin.components.table-column-toggle', [
                                'tableId' => $tableId ?? 'dataTable',
                                'storageKey' => $storageKey ?? '',
                                'columns' => $toggleableColumns
                            ])
                        @endif
                    @endif

                    {{-- 搜索按钮（默认显示，放在列显示控制后面） --}}
                    @php
                        $shouldShowSearch = ($showSearch ?? true) !== false;
                        $currentTableId = $tableId ?? 'dataTable';
                        $toggleFunction = "toggleSearchPanel_{$currentTableId}";
                    @endphp
                    @if($shouldShowSearch)
                        <script>
                            console.log('[TableToolbar] 搜索按钮显示检查', {
                                tableId: '{{ $currentTableId }}',
                                showSearch: {{ var_export($showSearch ?? null, true) }},
                                shouldShowSearch: {{ $shouldShowSearch ? 'true' : 'false' }},
                                toggleFunction: '{{ $toggleFunction }}'
                            });
                        </script>
                        <button
                            type="button"
                            class="btn btn-outline-secondary px-3 py-2"
                            style="border-radius: 10px;"
                            id="searchToggleBtn_{{ $currentTableId }}"
                            title="搜索"
                            data-toggle-function="{{ $toggleFunction }}"
                            onclick="const fnName = this.getAttribute('data-toggle-function'); const fn = window[fnName]; if (typeof fn === 'function') { fn(); } else { console.error('Function ' + fnName + ' not found. Please ensure the data-table component script is loaded.'); }"
                        >
                            <i class="bi bi-search" id="searchToggleIcon_{{ $currentTableId }}"></i>
                        </button>
                    @else
                        <script>
                            console.log('[TableToolbar] 搜索按钮被隐藏', {
                                tableId: '{{ $currentTableId }}',
                                showSearch: {{ var_export($showSearch ?? null, true) }},
                                shouldShowSearch: {{ $shouldShowSearch ? 'true' : 'false' }}
                            });
                        </script>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endif

