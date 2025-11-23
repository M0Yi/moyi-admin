{{-- 操作按钮工具栏容器（由 JavaScript 动态渲染） --}}
@if($showToolbar ?? true)
    @if(isset($toolbarSlot))
        {{-- 完全自定义工具栏（向后兼容） --}}
        {!! $toolbarSlot !!}
    @else
        {{-- 空容器，由 JavaScript 工具栏渲染器动态渲染 --}}
        <div id="toolbarContainer_{{ $tableId }}"></div>
    @endif
@endif

