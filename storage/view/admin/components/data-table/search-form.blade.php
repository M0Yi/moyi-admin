{{-- 搜索表单（如果提供了搜索配置，则自动渲染） --}}
@if($renderSearchForm)
    @include('admin.components.search-form', [
        'config' => $finalSearchConfig,
        'columns' => $columns,
        'model' => $model ?? '',
        'formId' => $searchFormId,
        'panelId' => $searchPanelId
    ])
@endif

