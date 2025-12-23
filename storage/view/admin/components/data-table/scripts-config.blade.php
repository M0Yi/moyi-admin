{{-- JavaScript 配置项（可以使用 PHP 和 Blade 语法） --}}
@php
    // 将列配置转换为 JSON（供 JavaScript 使用）
    $columnsJson = json_encode($columns);
    $editRouteTemplate = $editRouteTemplate ?? '';
    
    // 准备页面大小选项
    $pageSizeOptionsForJson = $pageSizeOptions ?? [10, 15, 20, 50, 100];
    
    // 准备可搜索字段列表
    // 从最终搜索配置中提取可搜索字段列表
    $searchableFields = [];
    if (!empty($finalSearchConfig) && !empty($finalSearchConfig['search_fields'])) {
        $searchableFields = $finalSearchConfig['search_fields'];
    }
@endphp

<script>
    // 表格配置对象（从 PHP/Blade 获取）
    window['tableConfig_{{ $tableId }}'] = {
        tableId: '{{ $tableId }}',
        storageKey: '{{ $storageKey }}',
        ajaxUrl: '{{ $ajaxUrl ?? '' }}',
        searchFormId: '{{ $searchFormId }}',
        searchPanelId: '{{ $searchPanelId ?? 'searchPanel' }}',
        batchDestroyRoute: '{{ $batchDestroyRoute ?? '' }}',
        createRoute: '{{ $createRoute ?? '' }}',
        iframeShellChannel: '{{ $iframeShellChannel ?? '' }}',
        columns: @json($columns),
        editRouteTemplate: '{{ $editRouteTemplate ?? '' }}',
        deleteModalId: '{{ $deleteModalId }}',
        defaultPageSize: {{ $defaultPageSize }},
        enablePageSizeStorage: {{ ($enablePageSizeStorage ?? true) ? 'true' : 'false' }},
        pageSizeOptions: @json($pageSizeOptionsForJson),
        defaultSortField: '{{ $defaultSortField }}',
        defaultSortOrder: '{{ $defaultSortOrder }}',
        searchableFields: @json($searchableFields),
        searchConfig: @json($finalSearchConfig),
        showBatchDeleteModal: {{ ($showBatchDeleteModal ?? false) ? 'true' : 'false' }},
        batchDeleteModalId: '{{ $batchDeleteModalId ?? '' }}',
        batchDeleteConfirmMessage: '{{ $batchDeleteConfirmMessage ?? '' }}',
        enableBatchDelete: {{ ($enableBatchDelete ?? false) ? 'true' : 'false' }},
        showDeleteModal: {{ ($showDeleteModal ?? true) ? 'true' : 'false' }},
        exportRoute: '{{ $exportRoute ?? '' }}',
        onDataLoaded: '{{ $onDataLoaded ?? '' }}',
        ajaxParams: @json($ajaxParams ?? []),
        defaultActions: @json($defaultActionsForJs ?? []),
        showActionsColumn: {{ ($showActionsColumn ?? true) ? 'true' : 'false' }},
        showSearch: {{ ($showSearch ?? true) ? 'true' : 'false' }},
        emptyMessage: '{{ $emptyMessage ?? '暂无数据' }}',
        // 工具栏配置
        toolbarConfig: {
            leftButtons: @json($leftButtons ?? []),
            rightButtons: @json($rightButtons ?? []),
            leftSlot: @json($leftSlot ?? null),
            rightSlot: @json($rightSlot ?? null),
            showColumnToggle: {{ ($showColumnToggle ?? true) ? 'true' : 'false' }},
            showSearch: {{ ($showSearch ?? true) ? 'true' : 'false' }}
        }
    };
    
    // 打印完整的搜索配置到控制台
    console.log('=== [DataTable {{ $tableId }}] 搜索配置 ===');
    console.log('showSearch 参数值:', {{ ($showSearch ?? true) ? 'true' : 'false' }}, '(PHP 变量: {{ var_export($showSearch ?? null, true) }})');
    console.log('showSearch 配置值:', window['tableConfig_{{ $tableId }}'].showSearch);
    console.log('完整搜索配置:', window['tableConfig_{{ $tableId }}'].searchConfig);
    console.log('可搜索字段列表:', window['tableConfig_{{ $tableId }}'].searchableFields);
    if (window['tableConfig_{{ $tableId }}'].searchConfig && window['tableConfig_{{ $tableId }}'].searchConfig.fields) {
        console.log('搜索字段详细配置:', window['tableConfig_{{ $tableId }}'].searchConfig.fields);
        window['tableConfig_{{ $tableId }}'].searchConfig.fields.forEach((field, index) => {
            console.log(`字段 ${index + 1}:`, {
                name: field.name,
                label: field.label,
                type: field.type,
                placeholder: field.placeholder || '(无)',
                options: field.options || '(无)',
                is_virtual: field.is_virtual || false
            });
        });
    }
    console.log('==========================================');
    
    // 设置当前表格ID，供第二个 script 标签使用
    window['_currentTableId'] = '{{ $tableId }}';
</script>

