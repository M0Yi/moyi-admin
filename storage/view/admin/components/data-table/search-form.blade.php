{{-- 搜索面板容器（始终创建，即使没有搜索配置，以便JavaScript可以动态渲染） --}}
@if($showSearch ?? true)
    <div id="{{ $searchPanelId }}" style="display: none;">
        {{-- 创建空容器供JavaScript动态渲染搜索表单 --}}
        <div id="{{ $searchFormId }}"></div>
    </div>
@endif

