{{-- 操作按钮工具栏 --}}
@if($showToolbar ?? true)
    @if(isset($toolbarSlot))
        {{-- 完全自定义工具栏 --}}
        {!! $toolbarSlot !!}
    @else
        {{-- 使用独立的工具栏组件 --}}
        @include('admin.components.table-toolbar', [
            'tableId' => $tableId,
            'storageKey' => $storageKey,
            'columns' => $columns,
            'leftButtons' => $leftButtons,
            'rightButtons' => $rightButtons ?? [],
            'leftSlot' => $leftSlot ?? null,
            'rightSlot' => $rightSlot ?? null,
            'showColumnToggle' => $showColumnToggle ?? true,
            'showSearch' => ($showSearch ?? true) && $hasSearchConfig,
        ])
    @endif
@endif

