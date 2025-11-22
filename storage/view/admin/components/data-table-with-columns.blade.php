{{--
/**
 * 数据表格组件（集成操作工具栏 + 列显示控制 + 列类型渲染）
 *
 * === 必填参数 ===
 * @param string $tableId 表格唯一ID
 * @param string $storageKey localStorage 存储键
 * @param array $columns 列配置数组
 *   - index: 列索引（必填）
 *   - label: 列标签（必填）
 *   - visible: 是否默认显示（必填）
 *   - field: 数据字段名（可选，默认使用 index，支持点号访问嵌套数据）
 *   - type: 列类型（可选，默认 'text'）
 *     支持类型: text, icon, badge, switch, code, date, image, images, link, number, relation, actions, custom
 *   - actions: 操作列按钮配置数组（仅当 type='actions' 时有效，可选，不设置则使用默认编辑和删除按钮）
 *     每个操作按钮配置项：
 *       - type: 'link' | 'button' | 'custom'（必填）
 *       - href: 链接地址（type='link' 时必填，支持 {id} 和 {value} 占位符）
 *       - onclick: 点击事件（type='button' 时必填，支持 {id} 和 {value} 占位符）
 *       - html: 自定义 HTML（type='custom' 时必填，支持 {id} 和 {value} 占位符）
 *       - icon: Bootstrap Icon 类名（可选，如 'bi-pencil'）
 *       - text: 按钮文字（可选）
 *       - variant: 按钮样式（可选，默认 'primary'，可选值：primary, secondary, success, danger, warning, info, light, dark）
 *       - title: 提示文字（可选）
 *       - class: 自定义 CSS 类（可选）
 *       - visible: 是否显示（可选，默认 true，可以是布尔值或函数 function(row) { return true/false; }）
 *   - width: 列宽度（可选）
 *   - class: 列样式类（可选）
 *   - toggleable: 是否可切换显示（可选，默认 true，false 表示此列不会出现在列显示控制中）
 *   - ... 其他类型特定参数（详见文档 docs/refactoring/column-type-system.md）
 * === 必填参数 ===
 * @param string $ajaxUrl API 数据加载地址（必填，用于 AJAX 模式加载数据）
 * @param string $searchFormId 搜索表单ID（默认 'searchForm'，用于收集过滤条件）
 * @param string $searchPanelId 搜索面板ID（默认 'searchPanel'，用于切换显示/隐藏）
 * @param array $searchConfig 搜索表单配置（可选，如果设置则自动在组件内渲染搜索表单）
 *   - search_fields: 搜索字段列表（数组）
 *   - fields: 字段配置数组（可选）
 * @param string $model 模型名称（可选，用于关联模式字段的异步加载）
 * @param string $batchDestroyRoute 批量删除路由（可选，如果设置则启用批量删除功能）
 *   设置此参数后，组件会自动：
 *   - 在表头添加全选复选框
 *   - 在每行数据前添加复选框
 *   注意：批量删除按钮需要通过 leftButtons 配置，不会自动添加
 *   示例：
 *   'leftButtons' => [
 *       ['type' => 'button', 'text' => '批量删除', 'icon' => 'bi-trash', 'variant' => 'danger', 
 *        'id' => 'batchDeleteBtn_' . $tableId, 'onclick' => 'batchDelete_' . $tableId . '()'],
 *   ],
 *
 * === URL 参数支持 ===
 *
 * 组件支持从 URL 参数自动初始化搜索条件和分页/排序：
 *
 * 1. 搜索条件参数：
 *    - 普通字段：?id=1&status=1&username=admin
 *    - 区间字段：?created_at_min=2024-01-01&created_at_max=2024-12-31
 *    - 如果 URL 中有筛选条件，会自动填充到搜索表单并展开搜索面板
 *    - ⚠️ 重要：只会处理 searchConfig 中配置的可搜索字段，其他字段会被忽略
 *
 * 2. 分页参数：
 *    - ?page=2  // 跳转到第2页
 *
 * 3. 排序参数：
 *    - ?sort_field=created_at&sort_order=desc  // 按创建时间降序排序
 *
 * 使用示例：
 *   访问 /admin/users?id=1 会自动筛选 ID=1 的记录（如果 id 在 search_fields 中）
 *   访问 /admin/users?status=1&page=2 会自动筛选状态=1的记录并跳转到第2页（如果 status 在 search_fields 中）
 *   访问 /admin/users?unknown_field=123 不会进行筛选（如果 unknown_field 不在 search_fields 中）
 * @param array $ajaxParams 额外的 AJAX 请求参数（可选）
 * @param bool $showPagination 是否显示分页（默认 true）
 * @param int $defaultPageSize 默认每页数量（默认 15）
 * @param array $pageSizeOptions 可选的每页数量选项（默认 [10, 15, 20, 50, 100]）
 * @param bool $enablePageSizeStorage 是否将分页尺寸保存到 localStorage（默认 true）
 * @param string $defaultSortField 默认排序字段（默认 'id'）
 * @param string $defaultSortOrder 默认排序方向（默认 'desc'）
 * @param string $editRouteTemplate 编辑路由模板（用于操作列默认编辑按钮，需要拼接 /{id}/edit，如果操作列配置了自定义 actions 则此参数无效）
 * @param string $exportRoute 导出路由（可选，如果设置则会在组件内生成 exportData_{tableId} 函数供导出按钮使用）
 * @param callable $onDataLoaded 数据加载完成后的回调函数（可选）
 *
 * === 删除确认模态框参数（可选） ===
 * @param string $deleteModalId 删除确认模态框ID（默认：deleteModal_{tableId}，如果设置为 false 则不显示模态框）
 * @param string $deleteConfirmMessage 删除确认提示文本（默认：确定要删除这条记录吗？）
 * @param string $deleteWarningMessage 删除警告文本（默认：警告：删除后将无法恢复！）
 * @param string $deleteModalTitle 模态框标题（默认：确认删除）
 * @param string $deleteConfirmButtonText 确认按钮文本（默认：确认删除）
 * @param string $deleteCancelButtonText 取消按钮文本（默认：取消）
 * @param string $batchDeleteModalId 批量删除确认模态框ID（默认：batchDeleteModal_{tableId}，如果设置为 false 则不显示模态框）
 * @param string $batchDeleteConfirmMessage 批量删除确认提示文本（默认：确定要删除选中的 {count} 条记录吗？）
 * @param string $batchDeleteWarningMessage 批量删除警告文本（默认：警告：删除后将无法恢复！）
 * @param string $batchDeleteModalTitle 批量删除模态框标题（默认：确认批量删除）
 * @param string $batchDeleteConfirmButtonText 批量删除确认按钮文本（默认：确认删除）
 * @param string $batchDeleteCancelButtonText 批量删除取消按钮文本（默认：取消）
 *
 * === 工具栏参数（可选） ===
 * 
 * 方式1：使用按钮配置数组（推荐）
 * @param array $leftButtons 左侧主操作按钮配置数组
 *   - type: 'link' | 'button'
 *   - href: 链接地址（type=link时）
 *   - text: 按钮文字
 *   - icon: Bootstrap Icon 类名
 *   - variant: 按钮样式 'primary' | 'light' | 'outline-secondary' | 'danger' | 'warning' | 'info' | 'success'
 *   - onclick: 点击事件（type=button时）
 *   - id: 按钮ID（可选）
 *   - class: 自定义CSS类（可选）
 *
 * @param array $rightButtons 右侧辅助按钮配置数组（在列显示和刷新按钮之前）
 *   - icon: Bootstrap Icon 类名
 *   - title: 提示文字
 *   - onclick: 点击事件
 *   - id: 按钮ID（可选）
 *   - variant: 按钮样式（默认 'outline-secondary'）
 *   - class: 自定义CSS类（可选）
 *   - text: 按钮文字（可选，如果有文字则显示在图标后面）
 *
 * 方式2：完全自定义工具栏（高级用法）
 * @param string $toolbarSlot 完全自定义工具栏HTML（优先级最高，如果设置则忽略所有按钮配置）
 * @param string $leftSlot 自定义左侧工具栏HTML（优先级高于 leftButtons）
 * @param string $rightSlot 自定义右侧工具栏HTML（优先级高于 rightButtons）
 *
 * @param bool $showToolbar 是否显示工具栏（默认 true）
 * @param bool $showColumnToggle 是否显示列显示控制按钮（默认 true）
 * @param bool $showSearch 是否显示搜索按钮（默认 true）
 *
 * 使用示例 1（AJAX 模式 + 集成搜索表单 - 推荐）：
 * @include('admin.components.data-table-with-columns', [
 *     'tableId' => 'userTable',
 *     'storageKey' => 'userTableColumns',
 *     'columns' => [
 *         ['index' => 0, 'label' => 'ID', 'field' => 'id', 'type' => 'text', 'visible' => true],
 *         ['index' => 1, 'label' => '头像', 'field' => 'avatar', 'type' => 'image', 'visible' => true, 'rounded' => 'circle'],
 *         ['index' => 2, 'label' => '用户名', 'field' => 'username', 'type' => 'link', 'visible' => true, 'href' => '/admin/users/{id}'],
 *         ['index' => 3, 'label' => '状态', 'field' => 'status', 'type' => 'switch', 'visible' => true, 'onChange' => 'toggleStatus({id}, this)'],
 *         ['index' => 4, 'label' => '操作', 'type' => 'actions', 'visible' => true, 'actions' => [
 *             ['type' => 'link', 'href' => '/admin/users/{id}/edit', 'icon' => 'bi-pencil', 'variant' => 'warning', 'title' => '编辑'],
 *             ['type' => 'button', 'onclick' => 'deleteUser({id})', 'icon' => 'bi-trash', 'variant' => 'danger', 'title' => '删除'],
 *             ['type' => 'link', 'href' => '/admin/users/{id}/view', 'icon' => 'bi-eye', 'variant' => 'info', 'title' => '查看', 'visible' => function(row) { return row.status == 1; }],
 *         ]],
 *     ],
 *     'ajaxUrl' => '/admin/users',  // 必填：AJAX 数据加载地址
 *     'searchConfig' => [  // 传递搜索配置，组件会自动渲染搜索表单
 *         'search_fields' => ['username', 'email', 'status'],
 *         'fields' => [
 *             ['name' => 'username', 'label' => '用户名', 'type' => 'string'],
 *             ['name' => 'email', 'label' => '邮箱', 'type' => 'string'],
 *             ['name' => 'status', 'label' => '状态', 'type' => 'switch', 'options' => [1 => '启用', 0 => '禁用']],
 *         ],
 *     ],
 *     'editRouteTemplate' => admin_route('universal/users'),
 *     'batchDestroyRoute' => admin_route('universal/users/batch-destroy'),  // 启用批量删除（添加复选框列）
 *     'leftButtons' => [
 *         ['type' => 'link', 'href' => admin_route('universal/users/create'), 'text' => '添加', 'icon' => 'bi-plus-lg', 'variant' => 'primary'],
 *         ['type' => 'button', 'text' => '批量删除', 'icon' => 'bi-trash', 'variant' => 'danger', 
 *          'id' => 'batchDeleteBtn_userTable', 'onclick' => 'batchDelete_userTable()'],
 *     ],
 *     // 批量删除确认模态框参数（可选）
 *     'batchDeleteModalId' => 'batchDeleteModal_userTable',  // 批量删除模态框ID（默认：batchDeleteModal_{tableId}，设置为 false 则不显示模态框）
 *     'batchDeleteConfirmMessage' => '确定要删除选中的 {count} 条记录吗？',  // 确认提示文本（支持 {count} 占位符）
 *     'batchDeleteWarningMessage' => '警告：删除后将无法恢复！',  // 警告文本
 *     'batchDeleteModalTitle' => '确认批量删除',  // 模态框标题
 *     'batchDeleteConfirmButtonText' => '确认删除',  // 确认按钮文本
 *     'batchDeleteCancelButtonText' => '取消',  // 取消按钮文本
 * ])
 *
 * 使用示例 1.2（AJAX 模式 + 批量删除功能）：
 * @include('admin.components.data-table-with-columns', [
 *     'tableId' => 'userTable',
 *     'storageKey' => 'userTableColumns',
 *     'columns' => [
 *         ['index' => 0, 'label' => 'ID', 'field' => 'id', 'type' => 'text', 'visible' => true],
 *         ['index' => 1, 'label' => '用户名', 'field' => 'username', 'type' => 'text', 'visible' => true],
 *         ['index' => 2, 'label' => '操作', 'type' => 'actions', 'visible' => true],
 *     ],
 *     'ajaxUrl' => '/admin/users',
 *     'batchDestroyRoute' => '/admin/users/batch-destroy',  // 启用批量删除（添加复选框列）
 *     'leftButtons' => [
 *         ['type' => 'link', 'href' => '/admin/users/create', 'text' => '添加', 'icon' => 'bi-plus-lg', 'variant' => 'primary'],
 *         ['type' => 'button', 'text' => '批量删除', 'icon' => 'bi-trash', 'variant' => 'danger', 
 *          'id' => 'batchDeleteBtn_userTable', 'onclick' => 'batchDelete_userTable()'],
 *     ],
 *     // 批量删除确认模态框参数（可选）
 *     'batchDeleteModalId' => 'batchDeleteModal_userTable',  // 批量删除模态框ID（默认：batchDeleteModal_{tableId}，设置为 false 则不显示模态框）
 *     'batchDeleteConfirmMessage' => '确定要删除选中的 {count} 条记录吗？',  // 确认提示文本（支持 {count} 占位符）
 *     'batchDeleteWarningMessage' => '警告：删除后将无法恢复！',  // 警告文本
 *     'batchDeleteModalTitle' => '确认批量删除',  // 模态框标题
 *     'batchDeleteConfirmButtonText' => '确认删除',  // 确认按钮文本
 *     'batchDeleteCancelButtonText' => '取消',  // 取消按钮文本
 *     // 组件会自动添加：
 *     // 1. 表头的全选复选框
 *     // 2. 每行的复选框
 *     // 3. 批量删除确认模态框（如果 batchDeleteModalId !== false）
 *     // 批量删除按钮需要在 leftButtons 中手动配置
 * ])
 *
 * 使用示例 2（自定义左侧工具栏）：
 * @include('admin.components.data-table-with-columns', [
 *     'tableId' => 'dataTable',
 *     'storageKey' => 'dataTableColumns',
 *     'columns' => [...],
 *     'leftSlot' => '
 *         <button class="btn btn-primary" onclick="customAction()">自定义操作</button>
 *         <div class="dropdown">
 *             <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">更多</button>
 *             <ul class="dropdown-menu">
 *                 <li><a class="dropdown-item" href="#">操作1</a></li>
 *                 <li><a class="dropdown-item" href="#">操作2</a></li>
 *             </ul>
 *         </div>
 *     ',
 *     'rightButtons' => [
 *         ['icon' => 'bi-download', 'title' => '导出', 'onclick' => 'exportData()'],
 *     ],
 * ])
 *
 * 使用示例 3（完全自定义工具栏）：
 * @include('admin.components.data-table-with-columns', [
 *     'tableId' => 'dataTable',
 *     'storageKey' => 'dataTableColumns',
 *     'columns' => [...],
 *     'toolbarSlot' => '
 *         <div class="mb-4">
 *             <div class="d-flex justify-content-between">
 *                 <div class="custom-left-toolbar">...</div>
 *                 <div class="custom-right-toolbar">...</div>
 *             </div>
 *         </div>
 *     ',
 * ])
 *
 */
--}}

@php
    if (!function_exists('universal_bool_value')) {
        function universal_bool_value($value, bool $default = false): bool
        {
            if (is_bool($value)) {
                return $value;
            }

            if ($value === null) {
                return $default;
            }

            if (is_numeric($value)) {
                return (int)$value !== 0;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));
                if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                    return true;
                }
                if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                    return false;
                }
            }

            return $default;
        }
    }

    if (!function_exists('universal_normalize_options')) {
        function universal_normalize_options(array $options): array
        {
            $result = [];
            foreach ($options as $key => $option) {
                if (is_array($option)) {
                    $normalizedKey = (string)($option['key'] ?? $key);
                    $result[$normalizedKey] = [
                        'label' => $option['value'] ?? $option['label'] ?? $normalizedKey,
                        'color' => $option['color'] ?? null,
                    ];
                } else {
                    $normalizedKey = (string)$key;
                    $result[$normalizedKey] = [
                        'label' => (string)$option,
                        'color' => null,
                    ];
                }
            }
            return $result;
        }
    }

    if (!function_exists('universal_get_variant_for_value')) {
        function universal_get_variant_for_value($key, string $label, array $variants, int $variantIndex): string
        {
            $labelLower = mb_strtolower($label);

            if (preg_match('/^(启用|激活|是|开启|正常|在线|公开|显示)$/u', $label)) {
                return 'success';
            }

            if (preg_match('/^(禁用|停用|否|关闭|异常|离线|隐藏|删除)$/u', $label)) {
                return 'secondary';
            }

            if (preg_match('/^(警告|待审核|待处理|待发布|草稿)$/u', $label)) {
                return 'warning';
            }

            if (preg_match('/^(错误|拒绝|失败|已删除|已禁用)$/u', $label)) {
                return 'danger';
            }

            if (preg_match('/^(信息|默认|其他)$/u', $label)) {
                return 'info';
            }

            if (is_numeric($key)) {
                $numKey = (int)$key;
                if ($numKey === 0) {
                    return 'secondary';
                }
                if ($numKey === 1) {
                    return 'success';
                }
            }

            return $variants[$variantIndex % count($variants)];
        }
    }

    if (!function_exists('universal_build_badge_map_from_options')) {
        function universal_build_badge_map_from_options(array $options, string $fieldName): array
        {
            $badgeMap = [];
            $variants = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
            $variantIndex = 0;

            $specialMappings = [
                'status' => [
                    '0' => 'secondary',
                    '1' => 'success',
                    '启用' => 'success',
                    '禁用' => 'secondary',
                    '是' => 'success',
                    '否' => 'secondary',
                    'active' => 'success',
                    'inactive' => 'secondary',
                ],
                'type' => [
                    'menu' => 'primary',
                    'button' => 'info',
                    'link' => 'warning',
                ],
            ];

            $fieldMapping = $specialMappings[$fieldName] ?? [];

            foreach ($options as $key => $option) {
                $label = $option['label'] ?? (string)$key;
                $color = $option['color'] ?? null;
                $optionKey = (string)$key;

                $variant = $color
                    ?? ($fieldMapping[$optionKey] ?? ($fieldMapping[$label] ?? universal_get_variant_for_value($optionKey, $label, $variants, $variantIndex)));

                $badgeMap[$optionKey] = [
                    'text' => $label,
                    'variant' => $variant,
                ];

                $variantIndex++;
            }

            return $badgeMap;
        }
    }

    if (!function_exists('universal_convert_db_type_to_column_type')) {
        function universal_convert_db_type_to_column_type(string $dbType, string $fieldName, ?string $formType = null): string
        {
            $supportedColumnTypes = [
                'text', 'number', 'date', 'icon', 'image', 'images',
                'switch', 'badge', 'code', 'custom', 'link', 'relation', 'columns'
            ];

            if ($formType) {
                $directSupportedTypes = [
                    'text', 'textarea', 'number', 'date', 'image', 'images',
                    'icon', 'switch', 'code', 'custom'
                ];

                $formTypeToColumnTypeMap = [
                    'datetime' => 'date',
                    'timestamp' => 'date',
                    'url' => 'link',
                    'file' => 'text',
                    'email' => 'text',
                    'color' => 'text',
                    'password' => 'text',
                    'radio' => 'badge',
                    'select' => 'badge',
                    'checkbox' => 'text',
                    'relation' => 'relation',
                    'rich_text' => 'text',
                    'number_range' => 'text',
                ];

                if (in_array($formType, $directSupportedTypes)) {
                    $columnType = $formType;
                } elseif (isset($formTypeToColumnTypeMap[$formType])) {
                    $columnType = $formTypeToColumnTypeMap[$formType];
                } else {
                    $columnType = 'text';
                }

                if (!in_array($columnType, $supportedColumnTypes)) {
                    $columnType = 'text';
                }

                return $columnType;
            }

            if (str_ends_with($fieldName, '_at') || str_ends_with($fieldName, '_time')) {
                return 'date';
            }

            if ($fieldName === 'icon' || str_ends_with($fieldName, '_icon')) {
                return 'icon';
            }

            if ($fieldName === 'avatar') {
                return 'icon';
            }

            if ($fieldName === 'image' || str_contains($fieldName, '_image') || str_contains($fieldName, 'image_')) {
                return 'image';
            }

            $typeMap = [
                'int' => 'number',
                'integer' => 'number',
                'bigint' => 'number',
                'tinyint' => 'number',
                'smallint' => 'number',
                'decimal' => 'number',
                'float' => 'number',
                'double' => 'number',
                'date' => 'date',
                'datetime' => 'date',
                'timestamp' => 'date',
                'text' => 'text',
                'longtext' => 'text',
                'json' => 'code',
            ];

            return $typeMap[strtolower($dbType)] ?? 'text';
        }
    }

    $tableId = $tableId ?? 'dataTable';
    $columns = $columns ?? [];
    $modelNameForColumns = $model ?? '';
    $toggleStatusRouteBase = $modelNameForColumns ? admin_route("u/{$modelNameForColumns}") : '';
    $fieldConfigs = $config['fields_config'] ?? [];
    $actionColumnConfig = $actionColumnConfig ?? ($config['action_column'] ?? []);
    $featuresFromInclude = $features ?? null;

    $featureDefaults = [
        'edit' => true,
        'delete' => true,
        'actions' => true,
    ];

    if (!empty($featuresFromInclude) && is_array($featuresFromInclude)) {
        $featureDefaults = array_merge($featureDefaults, $featuresFromInclude);
    } elseif (!empty($config['features']) && is_array($config['features'])) {
        $featureDefaults = array_merge($featureDefaults, $config['features']);
    }

    $showActionsColumn = $showActionsColumn ?? null;
    if ($showActionsColumn === null && !empty($actionColumnConfig)) {
        foreach (['enabled', 'visible', 'show'] as $flagKey) {
            if (array_key_exists($flagKey, $actionColumnConfig)) {
                $showActionsColumn = universal_bool_value($actionColumnConfig[$flagKey], true);
                break;
            }
        }
    }
    if ($showActionsColumn === null) {
        $showActionsColumn = universal_bool_value($featureDefaults['actions'] ?? true, true);
    }

    $editFeatureEnabled = universal_bool_value($featureDefaults['edit'] ?? true, true);
    $deleteFeatureEnabled = universal_bool_value($featureDefaults['delete'] ?? true, true);
    $editEnabled = empty($config['readonly']) && $editFeatureEnabled;
    $deleteEnabled = empty($config['readonly']) && $deleteFeatureEnabled;

    $defaultActionButtons = [];

    if ($editEnabled) {
        $defaultActionButtons[] = [
            'type' => 'link',
            'href' => ($editRouteTemplate ?? '') . '/{id}/edit',
            'icon' => 'bi-pencil',
            'variant' => 'warning',
            'title' => '编辑',
            'visible' => true,
        ];
    }

    if ($deleteEnabled) {
        $defaultActionButtons[] = [
            'type' => 'button',
            'onclick' => 'deleteRow_' . ($tableId ?? 'dataTable') . '({id})',
            'icon' => 'bi-trash',
            'variant' => 'danger',
            'title' => '删除',
            'visible' => true,
        ];
    }

    if (!empty($actionColumnConfig['actions']) && is_array($actionColumnConfig['actions'])) {
        $defaultActionButtons = $actionColumnConfig['actions'];
    }

    if (empty($columns) && !empty($fieldConfigs)) {
        $columns = [];
        $columnIndex = 0;

        foreach ($fieldConfigs as $field) {
            $name = $field['name'] ?? $field['field_name'] ?? null;
            if ($name === null) {
                continue;
            }

            $label = $field['field_name'] ?? $field['label'] ?? $name;
            $dbType = $field['data_type'] ?? $field['db_type'] ?? 'string';
            $formType = $field['form_type'] ?? null;
            $columnType = $field['column_type'] ?? $field['render_type'] ?? null;
            $type = $columnType ?: universal_convert_db_type_to_column_type($dbType, $name, $formType);

            if ($formType === 'relation' || $columnType === 'relation') {
                $type = 'relation';
            }

            $visible = universal_bool_value($field['list_default'] ?? true, true);
            $sortable = universal_bool_value($field['sortable'] ?? false, false);

            $columnConfig = [
                'index' => $columnIndex++,
                'label' => $label,
                'field' => $name,
                'name' => $name,
                'type' => $type,
                'visible' => $visible,
                'sortable' => $sortable,
                'db_type' => $dbType,
                'form_type' => $formType,
                'toggleable' => true,
            ];

            if (!empty($field['options'])) {
                $columnConfig['options'] = $field['options'];
            }

            if ($type === 'relation' && !empty($field['relation']['table'])) {
                $relation = $field['relation'];
                $relationMultiple = $relation['multiple'] ?? null;
                if ($relationMultiple === null) {
                    $relationMultiple = (($field['model_type'] ?? '') === 'array') || str_ends_with($name, '_ids');
                }

                $columnConfig['relation'] = [
                    'table' => $relation['table'] ?? '',
                    'label_field' => $relation['label_column'] ?? $relation['label_field'] ?? 'name',
                    'value_field' => $relation['value_column'] ?? $relation['value_field'] ?? 'id',
                    'multiple' => universal_bool_value($relationMultiple, false),
                ];
                $columnConfig['labelField'] = "{$name}_label";
            }

            if (($type === 'badge' || $formType === 'radio' || $formType === 'select') && !empty($field['options'])) {
                $normalizedOptions = universal_normalize_options($field['options']);
                $columnConfig['badgeMap'] = universal_build_badge_map_from_options($normalizedOptions, $name);
            } elseif ($type === 'badge' && $name === 'status' && empty($field['options'])) {
                $defaultStatusOptions = [
                    '1' => ['label' => '启用', 'color' => null],
                    '0' => ['label' => '禁用', 'color' => null],
                ];
                $columnConfig['badgeMap'] = universal_build_badge_map_from_options($defaultStatusOptions, $name);
            }

            switch ($name) {
                case 'id':
                    $columnConfig['width'] = '60';
                    if (!$formType) {
                        $columnConfig['type'] = 'number';
                    }
                    break;
                case 'icon':
                    if (!$formType || $formType === 'icon') {
                        $columnConfig['type'] = 'icon';
                        $columnConfig['size'] = '1.2rem';
                        $columnConfig['width'] = '80';
                    }
                    break;
                case 'status':
                    if (!$formType || $formType === 'switch') {
                        $columnConfig['type'] = 'switch';
                        if ($toggleStatusRouteBase) {
                            $columnConfig['onChange'] = "toggleStatus({id}, this, '{$toggleStatusRouteBase}/{id}')";
                        }
                        $columnConfig['width'] = '70';
                    } else {
                        if (!isset($columnConfig['width'])) {
                            $columnConfig['width'] = '150';
                        }
                    }
                    break;
                case 'sort':
                case 'order':
                    if (!$formType) {
                        $columnConfig['type'] = 'number';
                    }
                    $columnConfig['width'] = '70';
                    break;
                case 'created_at':
                case 'updated_at':
                    if (!$formType) {
                        $columnConfig['type'] = 'date';
                    }
                    $columnConfig['format'] = 'Y-m-d H:i:s';
                    $columnConfig['width'] = '150';
                    $columnConfig['visible'] = false;
                    break;
            }

            if ($type === 'switch') {
                if (!isset($columnConfig['onChange']) && $toggleStatusRouteBase) {
                    $columnConfig['onChange'] = "toggleStatus({id}, this, '{$toggleStatusRouteBase}/{id}')";
                }
                $columnConfig['fieldName'] = $name;
                if (!isset($columnConfig['width'])) {
                    $columnConfig['width'] = '70';
                }
            }

            if ($type === 'image') {
                $columnConfig['width'] = '150';
                $columnConfig['imageWidth'] = '80px';
                $columnConfig['imageHeight'] = '80px';
            } elseif ($type === 'images') {
                $columnConfig['width'] = '200';
                $columnConfig['imageWidth'] = '60px';
                $columnConfig['imageHeight'] = '60px';
            } elseif ($type === 'icon' && $name !== 'icon') {
                if (!isset($columnConfig['width'])) {
                    $columnConfig['width'] = '80';
                }
            }

            if (!isset($columnConfig['width'])) {
                if (in_array(strtolower($dbType), ['text', 'longtext'], true)) {
                    $columnConfig['width'] = '200';
                } elseif (in_array(strtolower($dbType), ['varchar', 'string'], true)) {
                    $columnConfig['width'] = '150';
                }
            }

            $columns[] = $columnConfig;
        }

        $actionColumnActions = $defaultActionButtons;

        if ($showActionsColumn && !empty($actionColumnActions)) {
            $actionColumn = [
                'index' => count($columns),
                'label' => $actionColumnConfig['label'] ?? '操作',
                'type' => 'actions',
                'visible' => universal_bool_value($actionColumnConfig['visible'] ?? true, true),
                'width' => (string)($actionColumnConfig['width'] ?? '120'),
                'class' => trim('sticky-column ' . ($actionColumnConfig['class'] ?? '')),
                'toggleable' => universal_bool_value($actionColumnConfig['toggleable'] ?? false, false),
                'sortable' => false,
            ];

            if (array_key_exists('readonly', $actionColumnConfig)) {
                $actionColumn['readonly'] = universal_bool_value($actionColumnConfig['readonly'], false);
            }

            $actionColumn['actions'] = $actionColumnActions;

            $columns[] = $actionColumn;
        }
    }

    $defaultActionsForJs = $defaultActionButtons;

    // AJAX 模式下的默认值
    $searchFormId = $searchFormId ?? 'searchForm';
    $searchPanelId = $searchPanelId ?? 'searchPanel';
    $defaultPageSize = $defaultPageSize ?? 15;
    
    // 统一搜索配置处理：优先从 config 中读取，否则使用 searchConfig
    $finalSearchConfig = null;
    
    // 1. 优先从 config['search_fields_config'] 读取
    if (!empty($config) && !empty($config['search_fields_config'])) {
        $finalSearchConfig = [
            'search_fields' => $config['search_fields'] ?? [],
            'fields' => $config['search_fields_config'],
        ];
    }
    // 2. 其次使用显式传入的 searchConfig
    elseif (!empty($searchConfig) && !empty($searchConfig['search_fields'])) {
        $finalSearchConfig = $searchConfig;
    }
    
    // 判断是否有搜索配置
    $hasSearchConfig = !empty($finalSearchConfig) && !empty($finalSearchConfig['search_fields']);
    // 自动渲染搜索表单（如果提供了搜索配置且启用了搜索功能）
    $showSearch = $showSearch ?? true;
    $renderSearchForm = $hasSearchConfig && $showSearch;
    $pageSizeOptions = $pageSizeOptions ?? [10, 15, 20, 50, 100];
    $enablePageSizeStorage = $enablePageSizeStorage ?? true;
    $defaultSortField = $defaultSortField ?? 'id';
    $defaultSortOrder = $defaultSortOrder ?? 'desc';
    $showPagination = $showPagination ?? true;
    
    // 左侧按钮配置
    $leftButtons = $leftButtons ?? [];
    
    // 批量删除功能参数初始化
    // 注意：批量删除按钮需要通过 leftButtons 配置，不再自动添加
    // 如果设置了 batchDestroyRoute，组件会自动添加复选框列，但按钮需要手动配置
    $batchDestroyRoute = $batchDestroyRoute ?? null;
    $enableBatchDelete = !empty($batchDestroyRoute);
    
    // 检查 leftButtons 中是否有批量删除按钮（通过 id 或 onclick 函数名判断）
    $hasBatchDeleteButton = false;
    if (!empty($leftButtons)) {
        foreach ($leftButtons as $btn) {
            // 检查是否有批量删除相关的标识
            if (isset($btn['id']) && strpos($btn['id'], 'batchDelete') !== false) {
                $hasBatchDeleteButton = true;
                break;
            }
            if (isset($btn['onclick']) && strpos($btn['onclick'], 'batchDelete') !== false) {
                $hasBatchDeleteButton = true;
                break;
            }
        }
    }
    
    // 批量删除模态框参数初始化
    $batchDeleteModalId = $batchDeleteModalId ?? 'batchDeleteModal_' . $tableId;
    $batchDeleteConfirmMessage = $batchDeleteConfirmMessage ?? '确定要删除选中的 {count} 条记录吗？';
    $batchDeleteWarningMessage = $batchDeleteWarningMessage ?? '警告：删除后将无法恢复！';
    $batchDeleteModalTitle = $batchDeleteModalTitle ?? '确认批量删除';
    $batchDeleteConfirmButtonText = $batchDeleteConfirmButtonText ?? '确认删除';
    $batchDeleteCancelButtonText = $batchDeleteCancelButtonText ?? '取消';
    $showBatchDeleteModal = ($enableBatchDelete && ($batchDeleteModalId !== false));
    
    // 删除确认模态框参数初始化
    $deleteModalId = $deleteModalId ?? 'deleteModal_' . $tableId;
    $deleteConfirmMessage = $deleteConfirmMessage ?? '确定要删除这条记录吗？';
    $deleteWarningMessage = $deleteWarningMessage ?? '警告：删除后将无法恢复！';
    $deleteModalTitle = $deleteModalTitle ?? '确认删除';
    $deleteConfirmButtonText = $deleteConfirmButtonText ?? '确认删除';
    $deleteCancelButtonText = $deleteCancelButtonText ?? '取消';
    $showDeleteModal = ($deleteModalId !== false);
    
    // 导出路由参数初始化
    $exportRoute = $exportRoute ?? null;
    
    // 批量删除按钮不再自动添加，需要通过 leftButtons 配置
    // 如果设置了 batchDestroyRoute，组件会自动添加复选框列
    // 用户需要在 leftButtons 中手动配置批量删除按钮，例如：
    // 'leftButtons' => [
    //     ['type' => 'link', 'href' => '/admin/users/create', 'text' => '添加', 'icon' => 'bi-plus-lg', 'variant' => 'primary'],
    //     ['type' => 'button', 'text' => '批量删除', 'icon' => 'bi-trash', 'variant' => 'danger', 'id' => 'batchDeleteBtn_' . $tableId, 'onclick' => 'batchDelete_' . $tableId . '()'],
    // ],
@endphp

{{-- 组件样式 --}}
@include('admin.components.data-table.styles')

@include('admin.components.data-table.search-form')

@include('admin.components.data-table.toolbar')

@include('admin.components.data-table.table')

@include('admin.components.data-table.pagination')



@include('admin.components.data-table.modals')

@include('admin.components.data-table.scripts-config')

{{-- 纯 JavaScript 代码（不能使用 PHP 和 Blade 语法） --}}
<script>
    (function() {
        // 创建独立的作用域，避免变量污染
        // 从第一个 script 标签中获取配置对象
        // 注意：这里需要通过 tableId 来获取配置，tableId 在第一个 script 标签中已经设置到 window 对象
        const tableIdKey = window['_currentTableId'] || 'dataTable';
        const config = window['tableConfig_' + tableIdKey];
        if (!config) {
            console.error('Table config not found for table:', tableIdKey);
            return;
        }
        
        const tableId = config.tableId;
        const storageKey = config.storageKey;
        const ajaxUrl = config.ajaxUrl || '';  // AJAX 数据加载地址（必填）
        if (!ajaxUrl) {
            console.error(`[DataTable ${tableId}] ajaxUrl 未设置，组件需要 AJAX 模式才能正常工作`);
        }
        const searchFormId = config.searchFormId;
        const searchPanelId = config.searchPanelId;
        const batchDestroyRoute = config.batchDestroyRoute;
        const columns = config.columns;
        const editRouteTemplate = config.editRouteTemplate;
        const deleteModalId = config.deleteModalId;
        const deleteConfirmBtnId = deleteModalId + 'ConfirmBtn';
        
        let batchDeleteModalId, batchDeleteConfirmBtnId, batchDeleteMessageId, batchDeleteConfirmMessageTemplate;
        if (config.showBatchDeleteModal) {
            batchDeleteModalId = config.batchDeleteModalId;
            batchDeleteConfirmBtnId = batchDeleteModalId + 'ConfirmBtn';
            batchDeleteMessageId = batchDeleteModalId + 'Message';
            batchDeleteConfirmMessageTemplate = config.batchDeleteConfirmMessage;
        }
        
        // 选中行ID数组（用于批量操作）
        let selectedIds = [];
        
        // 操作列默认操作按钮配置（可通过 PHP 层传入）
        const defaultActions = Array.isArray(config.defaultActions) ? config.defaultActions : [];
        
        // 分页和排序状态
        let currentPage = 1;
        const defaultPageSize = config.defaultPageSize;
        const pageSizeStorageKey = storageKey + '_pageSize';
        const enablePageSizeStorage = config.enablePageSizeStorage;
        
        // 从 localStorage 读取保存的分页尺寸（如果启用）
        let pageSize = defaultPageSize;
        if (enablePageSizeStorage) {
            try {
                const savedPageSize = localStorage.getItem(pageSizeStorageKey);
                if (savedPageSize) {
                    const parsedSize = parseInt(savedPageSize, 10);
                    const pageSizeOptions = config.pageSizeOptions;
                    // 验证保存的值是否在可选范围内
                    if (pageSizeOptions.includes(parsedSize)) {
                        pageSize = parsedSize;
                    }
                }
            } catch (e) {
                console.warn('Failed to load page size from localStorage:', e);
            }
        }
        
        let sortField = config.defaultSortField;
        let sortOrder = config.defaultSortOrder;
        
        // 同步排序状态到 window 对象（供导出函数等外部使用）
        function syncSortState() {
            window['currentSortField_' + tableId] = sortField;
            window['currentSortOrder_' + tableId] = sortOrder;
        }
        
        // 初始化时同步一次
        syncSortState();
        
        // 当前请求的 AbortController（用于取消请求）
        let currentRequestController = null;
        
        // 获取可搜索字段列表（从 searchConfig 中获取）
        const searchableFields = config.searchableFields;
        
        // 检查字段是否可搜索
        function isSearchableField(fieldName) {
            if (!searchableFields || searchableFields.length === 0) {
                // 如果没有配置可搜索字段，则允许所有字段（向后兼容）
                return true;
            }
            
            // 检查是否是区间字段（以 _min 或 _max 结尾）
            if (fieldName.endsWith('_min') || fieldName.endsWith('_max')) {
                const baseFieldName = fieldName.replace(/_min$/, '').replace(/_max$/, '');
                return searchableFields.includes(baseFieldName);
            }
            
            // 普通字段
            return searchableFields.includes(fieldName);
        }
        
        // 从 URL 参数初始化搜索表单
        function initSearchFormFromURL() {
            // 如果搜索功能被禁用，跳过初始化
            const showSearch = config.showSearch !== false; // 默认为 true
            if (!showSearch) {
                return;
            }
            
            const form = document.getElementById(searchFormId);
            if (!form) return;
            
            // 获取 URL 参数
            const urlParams = new URLSearchParams(window.location.search);
            let hasUrlFilters = false;
            
            // 遍历所有 URL 参数
            for (const [key, value] of urlParams.entries()) {
                // 跳过系统参数
                if (['_ajax', 'page', 'page_size', 'keyword', 'sort_field', 'sort_order', 'filters'].includes(key)) {
                    continue;
                }
                
                // 只处理可搜索字段
                if (!isSearchableField(key)) {
                    continue;
                }
                
                // 检查是否是区间字段（以 _min 或 _max 结尾）
                if (key.endsWith('_min') || key.endsWith('_max')) {
                    const input = form.querySelector(`input[name="filters[${key}]"]`);
                    if (input && value.trim() !== '') {
                        input.value = value.trim();
                        hasUrlFilters = true;
                    }
                } else {
                    // 普通字段
                    const input = form.querySelector(`input[name="filters[${key}]"], select[name="filters[${key}]"]`);
                    if (input && value.trim() !== '') {
                        input.value = value.trim();
                        hasUrlFilters = true;
                    }
                }
            }
            
            // 如果有 URL 筛选条件，自动展开搜索面板
            if (hasUrlFilters) {
                const searchPanel = document.getElementById(searchPanelId);
                const searchBtn = document.getElementById('searchToggleBtn_' + tableId);
                const searchIcon = document.getElementById('searchToggleIcon_' + tableId);
                
                if (searchPanel && searchPanel.style.display === 'none') {
                    // 直接显示搜索面板
                    searchPanel.style.display = 'block';
                    searchPanel.style.opacity = '1';
                    searchPanel.style.transform = 'translateY(0)';
                    
                    // 更新按钮状态
                    if (searchIcon) {
                        searchIcon.className = 'bi bi-x-lg';
                    }
                    if (searchBtn) {
                        searchBtn.classList.add('active');
                        searchBtn.title = '收起搜索';
                    }
                }
            }
        }
        
        // 显示加载状态
        function showLoadingState() {
                    const tbody = document.querySelector(`#${tableId} tbody`);
                    if (tbody) {
                        const colspan = columns.length + (batchDestroyRoute ? 1 : 0);
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="${colspan}" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm me-2" role="status">
                                        <span class="visually-hidden">加载中...</span>
                                    </div>
                                    <span>加载中...</span>
                                </td>
                            </tr>
                        `;
                    }
        }
        
        // 加载数据
        window['loadData_' + tableId] = function() {
            // 检查 ajaxUrl 是否设置
            if (!ajaxUrl) {
                console.error(`[DataTable ${tableId}] ajaxUrl 未设置，无法加载数据`);
                return;
            }
            
            // 取消之前的请求（如果存在）
            if (currentRequestController) {
                currentRequestController.abort();
            }
            
            // 创建新的 AbortController
            currentRequestController = new AbortController();
            
            // 显示加载状态
            showLoadingState();
            
            // 收集搜索表单的所有字段值
            const form = document.getElementById(searchFormId);
            const filters = {};
            
            if (form) {
                const formData = new FormData(form);
                // 收集所有 filters 字段
                for (const [key, value] of formData.entries()) {
                    if (key.startsWith('filters[') && key.endsWith(']')) {
                        // 提取字段名：filters[field_name] -> field_name
                        const fieldName = key.replace('filters[', '').replace(']', '');
                        const fieldValue = value.trim();
                        // 只添加非空值
                        if (fieldValue !== '') {
                            filters[fieldName] = fieldValue;
                        }
                    }
                }
            }
            
            // 如果搜索表单中没有筛选条件，尝试从 URL 参数读取（只读取可搜索字段）
            if (Object.keys(filters).length === 0) {
                const urlParams = new URLSearchParams(window.location.search);
                for (const [key, value] of urlParams.entries()) {
                    // 跳过系统参数
                    if (['_ajax', 'page', 'page_size', 'keyword', 'sort_field', 'sort_order', 'filters'].includes(key)) {
                        continue;
                    }
                    
                    // 只处理可搜索字段
                    if (!isSearchableField(key)) {
                        continue;
                    }
                    
                    const fieldValue = value.trim();
                    if (fieldValue !== '') {
                        filters[key] = fieldValue;
                    }
                }
            }

            const params = new URLSearchParams({
                _ajax: '1',
                page: currentPage,
                page_size: pageSize,
                keyword: '', // 保留 keyword 参数（向后兼容），但不再使用
                sort_field: sortField,
                sort_order: sortOrder,
            });

            // 如果有过滤条件，添加到参数中
            if (Object.keys(filters).length > 0) {
                params.append('filters', JSON.stringify(filters));
            }

            // 添加额外的 AJAX 参数
            if (config.ajaxParams && typeof config.ajaxParams === 'object') {
                for (const [key, value] of Object.entries(config.ajaxParams)) {
                    params.append(key, String(value));
                }
            }

            fetch(`${ajaxUrl}?${params}`, {
                signal: currentRequestController.signal
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(result => {
                    // 清除请求控制器
                    currentRequestController = null;
                    
                    if (result.code === 200) {
                        renderTable(result.data);
                        renderPagination(result.data);
                        updateSortIcons();
                        
                        // 同步排序状态
                        syncSortState();
                        
                        // 重置批量删除按钮状态（数据刷新后）
                        if (batchDestroyRoute) {
                            selectedIds = [];
                            const checkAll = document.getElementById('checkAll_' + tableId);
                            if (checkAll) {
                                checkAll.checked = false;
                                checkAll.indeterminate = false;
                            }
                            const batchDeleteBtn = document.getElementById('batchDeleteBtn_' + tableId);
                            if (batchDeleteBtn) {
                                batchDeleteBtn.disabled = true;
                                batchDeleteBtn.classList.add('disabled');
                            }
                        }
                        
                        // 调用回调函数
                        if (config.onDataLoaded && typeof window[config.onDataLoaded] === 'function') {
                            window[config.onDataLoaded](result.data);
                        }
                    } else {
                        const tbody = document.querySelector(`#${tableId} tbody`);
                        if (tbody) {
                            const colspan = columns.length + (batchDestroyRoute ? 1 : 0);
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="${colspan}" class="text-center text-danger py-4">
                                        <i class="bi bi-exclamation-circle me-2"></i>
                                        ${result.msg || '加载数据失败'}
                                    </td>
                                </tr>
                            `;
                        }
                        if (typeof showToast === 'function') {
                            showToast('danger', result.msg || '加载数据失败');
                        }
                    }
                })
                .catch(error => {
                    // 清除请求控制器
                    currentRequestController = null;
                    
                    // 如果是取消请求，不显示错误
                    if (error.name === 'AbortError') {
                        return;
                    }
                    
                    console.error('Error:', error);
                    
                    const tbody = document.querySelector(`#${tableId} tbody`);
                    if (tbody) {
                        let errorMessage = '加载数据失败';
                        if (error.message) {
                            if (error.message.includes('Failed to fetch')) {
                                errorMessage = '网络连接失败，请检查网络设置';
                            } else if (error.message.includes('timeout')) {
                                errorMessage = '请求超时，请稍后重试';
                            } else {
                                errorMessage = error.message;
                            }
                        }
                        
                        const colspan = columns.length + (batchDestroyRoute ? 1 : 0);
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="${colspan}" class="text-center text-danger py-4">
                                    <i class="bi bi-exclamation-circle me-2"></i>
                                    ${errorMessage}
                                </td>
                            </tr>
                        `;
                    }
                    
                    if (typeof showToast === 'function') {
                        showToast('danger', errorMessage);
                    }
                });
        };

        // 渲染表格
        function renderTable(data) {
            const tbody = document.querySelector(`#${tableId} tbody`);
            if (!tbody) return;

            if (data.data.length === 0) {
                const colspan = columns.length + (batchDestroyRoute ? 1 : 0);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${colspan}" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            {{ $emptyMessage ?? '暂无数据' }}
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            data.data.forEach(row => {
                html += '<tr>';

                // 批量删除复选框列（如果启用了批量删除）
                if (config.enableBatchDelete) {
                    html += `<td style="white-space: nowrap;">
                        <div class="form-check">
                            <input class="form-check-input row-check" type="checkbox" value="${row.id}" 
                                   data-id="${row.id}"
                                   onchange="toggleRowCheck_${tableId}()">
                        </div>
                    </td>`;
                }

                // 渲染每一列（根据列配置）
                columns.forEach(column => {
                    const field = column.field || '';
                    let value = '';
                    
                    // 支持点号语法访问嵌套数据
                    if (field.includes('.')) {
                        const keys = field.split('.');
                        value = row;
                        for (const key of keys) {
                            value = value?.[key] ?? null;
                            if (value === null) break;
                        }
                    } else {
                        value = row[field] ?? '';
                    }
                    
                    const columnType = column.type || 'text';

                    // 构建单元格样式
                    const cellStyle = column.visible === false ? 'display: none;' : '';
                    const cellClass = column.class || '';

                    html += `<td data-column="${column.index}" class="${cellClass}" style="${cellStyle}">`;

                    // 使用列类型渲染器（复用现有的 cell 组件逻辑）
                    html += renderCell(value, column, row);

                    html += '</td>';
                });

                html += '</tr>';
            });

            tbody.innerHTML = html;

            // 应用浏览器本地保存的列显示设置
            applyColumnVisibilityPreferences();
        }

        // 渲染单元格（根据列类型）
        // 所有列类型渲染都在前端 JavaScript 中完成
        function renderCell(value, column, row) {
            const columnType = column.type || 'text';
            
            switch (columnType) {
                case 'number':
                    return value || '0';
                
                case 'date':
                    return value ? formatDate(value) : '';
                
                case 'icon':
                    if (value) {
                        const size = column.size || '1rem';
                        return `<i class="${value}" style="font-size: ${size};"></i>`;
                    }
                    return '';
                
                case 'image':
                    if (value) {
                        const imageWidth = column.imageWidth || column.width || '80px';
                        const imageHeight = column.imageHeight || column.height || '80px';
                        return `<img src="${value}" 
                                     alt="" 
                                     style="width: ${imageWidth}; height: ${imageHeight}; object-fit: cover; max-width: 100%; cursor: pointer;" 
                                     onclick="window.open('${value}', '_blank')" 
                                     title="点击查看大图">`;
                    }
                    return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
                
                case 'images':
                    if (value) {
                        // 如果是 JSON 字符串，先解析
                        let images = [];
                        try {
                            images = typeof value === 'string' ? JSON.parse(value) : value;
                            if (!Array.isArray(images)) {
                                images = [images];
                            }
                        } catch (e) {
                            images = [value];
                        }
                        
                        if (images.length > 0) {
                            const imageWidth = column.imageWidth || '60px';
                            const imageHeight = column.imageHeight || '60px';
                            let html = '<div class="d-flex gap-1 flex-wrap">';
                            images.forEach(img => {
                                if (img) {
                                    html += `<img src="${img}" 
                                                 alt="" 
                                                 style="width: ${imageWidth}; height: ${imageHeight}; object-fit: cover; cursor: pointer;" 
                                                 onclick="window.open('${img}', '_blank')" 
                                                 title="点击查看大图">`;
                                }
                            });
                            html += '</div>';
                            return html;
                        }
                    }
                    return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
                
                case 'relation':
                    // 关联类型：显示关联名称而不是 ID
                    // 后端会返回 {field}_label 字段，包含关联名称
                    const labelField = column.labelField || (column.field + '_label');
                    const relationValue = row[labelField] || '';
                    
                    if (relationValue) {
                        // 如果有多个值（多选），显示为多个标签
                        if (relationValue.includes(', ')) {
                            const labels = relationValue.split(', ');
                            let html = '<div class="d-flex gap-1 flex-wrap">';
                            labels.forEach(label => {
                                html += `<span class="badge bg-primary">${label}</span>`;
                            });
                            html += '</div>';
                            return html;
                        } else {
                            // 单选，显示单个标签
                            return `<span class="badge bg-primary">${relationValue}</span>`;
                        }
                    }
                    return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
                
                case 'switch':
                    const onValue = column.onValue !== undefined ? column.onValue : 1;
                    const offValue = column.offValue !== undefined ? column.offValue : 0;
                    const isChecked = String(value) === String(onValue);
                    const checked = isChecked ? 'checked' : '';
                    const switchClass = isChecked ? 'bg-success' : 'bg-secondary';
                    const fieldName = column.fieldName || column.field || 'status';
                    const onChangeHandler = column.onChange ? column.onChange.replace('{id}', row.id) : '';
                    
                    // 记录 switch 渲染日志
                    console.log(`[DataTable ${tableId}] Switch 列渲染:`, {
                        rowId: row.id,
                        field: fieldName,
                        currentValue: value,
                        onValue: onValue,
                        offValue: offValue,
                        isChecked: isChecked,
                        onChangeHandler: onChangeHandler
                    });
                    
                    return `
                        <div class="form-check form-switch">
                            <input class="form-check-input ${switchClass}"
                                   type="checkbox"
                                   ${checked}
                                   data-field="${fieldName}"
                                   data-on-value="${onValue}"
                                   data-off-value="${offValue}"
                                   onchange="${onChangeHandler}"
                                   style="cursor: pointer; width: 40px; height: 20px;">
                        </div>
                    `;
                
                case 'badge':
                    if (column.badgeMap && column.badgeMap[value]) {
                        const badge = column.badgeMap[value];
                        return `<span class="badge bg-${badge.variant || 'secondary'}">${badge.text || value}</span>`;
                    }
                    return `<span class="badge bg-secondary">${value}</span>`;
                
                case 'code':
                    return `<code class="text-muted small">${value || ''}</code>`;
                
                case 'link':
                    const href = column.href ? column.href.replace('{id}', row.id).replace('{value}', value) : '#';
                    return `<a href="${href}">${value || ''}</a>`;
                
                case 'actions':
                    // 操作列：支持自定义操作按钮
                    // 从列配置中获取 actions 数组，如果没有则使用默认配置
                    const actions = column.actions || defaultActions;
                    let actionsHtml = '<div class="d-flex gap-1">';
                    
                    // 如果列被标记为只读，则不显示任何操作
                    if (column.readonly) {
                        return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
                    }
                    
                    // 遍历操作按钮配置
                    actions.forEach(action => {
                        // 检查是否可见（支持函数和布尔值）
                        let isVisible = true;
                        if (typeof action.visible === 'function') {
                            isVisible = action.visible(row);
                        } else if (action.visible !== undefined) {
                            isVisible = action.visible;
                        }
                        
                        if (!isVisible) {
                            return; // 跳过不可见的按钮
                        }
                        
                        // 替换占位符 {id} 和 {value}
                        const replacePlaceholders = (str) => {
                            if (!str) return '';
                            return str.replace(/{id}/g, row.id)
                                      .replace(/{value}/g, value || '');
                        };
                        
                        if (action.type === 'link') {
                            // 链接类型按钮
                            const href = replacePlaceholders(action.href);
                            if (href && href !== '#') {
                                const variant = action.variant || 'primary';
                                const icon = action.icon ? `<i class="bi ${action.icon}"></i>` : '';
                                const text = action.text || '';
                                const title = action.title || '';
                                const btnClass = action.class || '';
                                
                                actionsHtml += `
                                    <a href="${href}"
                                       class="btn btn-sm btn-${variant} btn-action ${btnClass}"
                                       ${title ? `title="${title}"` : ''}>
                                        ${icon}${text ? ' ' + text : ''}
                                    </a>
                                `;
                            }
                        } else if (action.type === 'button') {
                            // 按钮类型
                            const onclick = replacePlaceholders(action.onclick);
                            if (onclick) {
                                const variant = action.variant || 'secondary';
                                const icon = action.icon ? `<i class="bi ${action.icon}"></i>` : '';
                                const text = action.text || '';
                                const title = action.title || '';
                                const btnClass = action.class || '';
                                
                                actionsHtml += `
                                    <button class="btn btn-sm btn-${variant} btn-action ${btnClass}"
                                            ${title ? `title="${title}"` : ''}
                                            onclick="${onclick}">
                                        ${icon}${text ? ' ' + text : ''}
                                    </button>
                                `;
                            }
                        } else if (action.type === 'custom') {
                            // 自定义 HTML
                            const html = replacePlaceholders(action.html);
                            if (html) {
                                actionsHtml += html;
                            }
                        }
                    });
                    
                    actionsHtml += '</div>';
                    return actionsHtml || '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
                
                case 'columns':
                    // 列组类型：渲染嵌套表格
                    if (!value || !Array.isArray(value)) {
                        return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
                    }
                    
                    // 如果没有配置子列，使用默认列配置
                    const subColumns = column.columns || [];
                    if (subColumns.length === 0) {
                        // 如果没有配置子列，尝试从数据中推断
                        if (value.length > 0 && typeof value[0] === 'object') {
                            // 从第一条数据中获取字段名作为列
                            const keys = Object.keys(value[0]);
                            subColumns.push(...keys.map((key, idx) => ({
                                index: idx,
                                label: key,
                                field: key,
                                type: 'text',
                                visible: true
                            })));
                        } else {
                            return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
                        }
                    }
                    
                    // 构建嵌套表格 HTML
                    let nestedTableHtml = '<div class="table-responsive" style="max-height: 300px; overflow-y: auto;">';
                    nestedTableHtml += '<table class="table table-sm table-bordered table-hover mb-0">';
                    
                    // 表头
                    nestedTableHtml += '<thead><tr>';
                    subColumns.forEach(subCol => {
                        if (subCol.visible !== false) {
                            nestedTableHtml += `<th style="font-size: 0.875rem;">${subCol.label || subCol.field || ''}</th>`;
                        }
                    });
                    nestedTableHtml += '</tr></thead>';
                    
                    // 表体
                    nestedTableHtml += '<tbody>';
                    value.forEach((subRow, rowIdx) => {
                        nestedTableHtml += '<tr>';
                        subColumns.forEach(subCol => {
                            if (subCol.visible !== false) {
                                const subField = subCol.field || '';
                                let subValue = '';
                                
                                // 支持点号语法访问嵌套数据
                                if (subField.includes('.')) {
                                    const keys = subField.split('.');
                                    subValue = subRow;
                                    for (const key of keys) {
                                        subValue = subValue?.[key] ?? null;
                                        if (subValue === null) break;
                                    }
                                } else {
                                    subValue = subRow[subField] ?? '';
                                }
                                
                                // 使用子列的渲染类型
                                const subColumnType = subCol.type || 'text';
                                nestedTableHtml += '<td style="font-size: 0.875rem;">';
                                nestedTableHtml += renderCell(subValue, subCol, subRow);
                                nestedTableHtml += '</td>';
                            }
                        });
                        nestedTableHtml += '</tr>';
                    });
                    nestedTableHtml += '</tbody>';
                    nestedTableHtml += '</table>';
                    nestedTableHtml += '</div>';
                    
                    return nestedTableHtml;
                
                default: // text
                    let textValue = value || '';
                    // 支持 truncate 截断
                    if (column.truncate && textValue.length > column.truncate) {
                        const truncated = textValue.substring(0, column.truncate) + '...';
                        return `<span title="${textValue.replace(/"/g, '&quot;')}" style="cursor: help;">${truncated}</span>`;
                    }
                    return textValue;
            }
        }

        // 应用浏览器本地保存的列显示设置
        function applyColumnVisibilityPreferences() {
            const saved = localStorage.getItem(storageKey);
            
            if (!saved) {
                return;
            }

            try {
                const visibleColumns = JSON.parse(saved);
                const table = document.getElementById(tableId);
                
                if (!table) {
                    return;
                }

                // 获取列配置，用于判断哪些列不可切换（toggleable: false）
                const nonToggleableColumns = columns
                    .filter(col => (col.toggleable ?? true) === false)
                    .map(col => col.index);

                // 获取所有列索引（从表头获取）
                const allColumns = Array.from(table.querySelectorAll('thead th[data-column]'))
                    .map(th => parseInt(th.getAttribute('data-column')));

                // 应用保存的设置
                allColumns.forEach(index => {
                    const isNonToggleable = nonToggleableColumns.includes(index);
                    const isVisible = isNonToggleable || visibleColumns.includes(index);

                    const th = table.querySelector(`thead th[data-column="${index}"]`);
                    const tds = table.querySelectorAll(`tbody td[data-column="${index}"]`);
                    
                    if (th) {
                        th.style.display = isVisible ? '' : 'none';
                    }
                    
                    tds.forEach(td => {
                        td.style.display = isVisible ? '' : 'none';
                    });
                });
            } catch (e) {
                console.error('Failed to apply column visibility preferences:', e);
            }
        }

        // 渲染分页
        function renderPagination(data) {
            const pagination = document.getElementById(`${tableId}_pagination`);
            const pageInfo = document.getElementById(`${tableId}_pageInfo`);
            const pageLinks = document.getElementById(`${tableId}_pageLinks`);
            const pageSizeSelect = document.getElementById(`${tableId}_pageSizeSelect`);
            const pageJump = document.getElementById(`${tableId}_pageJump`);
            const pageInput = document.getElementById(`${tableId}_pageInput`);

            if (!data || !data.total) {
                if (pagination) pagination.style.display = 'none';
                if (pageJump) pageJump.style.display = 'none';
                return;
            }

            if (pagination) pagination.style.display = 'flex';
            
            // 显示页码跳转输入框（总页数大于1时显示）
            if (pageJump) {
                pageJump.style.display = data.last_page > 1 ? 'flex' : 'none';
            }

            // 更新分页尺寸选择器的值
            if (pageSizeSelect && data.page_size) {
                pageSizeSelect.value = data.page_size;
            }

            // 更新页码输入框的最大值和当前值
            if (pageInput) {
                pageInput.max = data.last_page;
                pageInput.value = data.page;
            }

            // 分页信息
            const start = (data.page - 1) * data.page_size + 1;
            const end = Math.min(data.page * data.page_size, data.total);
            if (pageInfo) {
                pageInfo.innerHTML = `显示 ${start} 到 ${end}，共 ${data.total} 条`;
            }

            // 分页链接
            let html = '';

            // 上一页
            html += `
                <li class="page-item ${data.page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="goToPage_${tableId}(${data.page - 1}); return false;">上一页</a>
                </li>
            `;

            // 页码
            for (let i = 1; i <= data.last_page; i++) {
                if (i === 1 || i === data.last_page || (i >= data.page - 2 && i <= data.page + 2)) {
                    html += `
                        <li class="page-item ${i === data.page ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="goToPage_${tableId}(${i}); return false;">${i}</a>
                        </li>
                    `;
                } else if (i === data.page - 3 || i === data.page + 3) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            // 下一页
            html += `
                <li class="page-item ${data.page === data.last_page ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="goToPage_${tableId}(${data.page + 1}); return false;">下一页</a>
                </li>
            `;

            if (pageLinks) pageLinks.innerHTML = html;
            
            // 保存总页数到全局变量，供跳转函数使用
            window['lastPage_' + tableId] = data.last_page;
        }

        // 跳转页码
        window['goToPage_' + tableId] = function(page) {
            currentPage = page;
            // 同步更新页码输入框的值
            const pageInput = document.getElementById(`${tableId}_pageInput`);
            if (pageInput) {
                pageInput.value = page;
            }
            window['loadData_' + tableId]();
        };

        // 跳转到指定页码（通过输入框）
        window['jumpToPage_' + tableId] = function() {
            const pageInput = document.getElementById(`${tableId}_pageInput`);
            if (!pageInput) return;
            
            const inputValue = pageInput.value.trim();
            if (!inputValue) {
                // 如果输入为空，恢复当前页码
                const pagination = document.getElementById(`${tableId}_pagination`);
                if (pagination && pagination.style.display !== 'none') {
                    // 从分页链接中获取当前页码
                    const activePageItem = document.querySelector(`#${tableId}_pageLinks .page-item.active`);
                    if (activePageItem) {
                        const activeLink = activePageItem.querySelector('.page-link');
                        if (activeLink) {
                            const match = activeLink.textContent.match(/^\d+$/);
                            if (match) {
                                pageInput.value = match[0];
                            }
                        }
                    }
                }
                return;
            }
            
            const targetPage = parseInt(inputValue, 10);
            const lastPage = window['lastPage_' + tableId] || 1;
            
            // 验证页码范围
            if (isNaN(targetPage) || targetPage < 1) {
                alert('请输入有效的页码（最小为1）');
                pageInput.value = currentPage;
                pageInput.focus();
                return;
            }
            
            if (targetPage > lastPage) {
                alert(`页码不能超过总页数 ${lastPage}`);
                pageInput.value = lastPage;
                pageInput.focus();
                return;
            }
            
            // 跳转到指定页码
            window['goToPage_' + tableId](targetPage);
        };

        // 切换分页尺寸
        window['changePageSize_' + tableId] = function(newSize) {
            pageSize = parseInt(newSize, 10);
            currentPage = 1; // 重置到第一页
            
            // 保存到 localStorage（如果启用）
            if (enablePageSizeStorage) {
                try {
                    localStorage.setItem(pageSizeStorageKey, pageSize.toString());
                } catch (e) {
                    console.warn('Failed to save page size to localStorage:', e);
                }
            }
            
            // 重新加载数据
            window['loadData_' + tableId]();
        };

        // 排序
        window['sortBy_' + tableId] = function(field) {
            if (sortField === field) {
                sortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                sortField = field;
                sortOrder = 'desc';
            }
            // 同步排序状态
            syncSortState();
            currentPage = 1; // 重置到第一页
            window['loadData_' + tableId]();
            updateSortIcons();
        };

        // 处理表头排序点击
        window['handleSort_' + tableId] = function(th) {
            const field = th.getAttribute('data-field');
            if (!field) {
                return;
            }
            window['sortBy_' + tableId](field);
        };

        // 更新排序图标状态
        function updateSortIcons() {
            // 清除所有排序图标状态
            const table = document.getElementById(tableId);
            if (!table) return;
            
            table.querySelectorAll('.sort-icons').forEach(icons => {
                icons.classList.remove('active');
                icons.querySelector('.sort-asc')?.classList.remove('text-primary', 'fw-bold');
                icons.querySelector('.sort-desc')?.classList.remove('text-primary', 'fw-bold');
                icons.style.opacity = '';
            });
            
            // 高亮当前排序字段
            const currentSortTh = table.querySelector(`th[data-field="${sortField}"]`);
            if (currentSortTh) {
                const icons = currentSortTh.querySelector('.sort-icons');
                if (icons) {
                    icons.classList.add('active');
                    if (sortOrder === 'asc') {
                        icons.querySelector('.sort-asc')?.classList.add('text-primary', 'fw-bold');
                    } else {
                        icons.querySelector('.sort-desc')?.classList.add('text-primary', 'fw-bold');
                    }
                }
            }
        }

        // 更新表头排序点击事件（防止重复初始化）
        const tableInitHandlerName = '_tableInitHandler_' + tableId;
        const tableInitBoundName = '_tableInitBound_' + tableId;
        
        console.log(`[DataTable ${tableId}] 开始初始化表格事件绑定`);
        console.log(`[DataTable ${tableId}] 是否已初始化:`, window[tableInitBoundName]);
        console.log(`[DataTable ${tableId}] DOM 状态:`, document.readyState);
        
        // 如果已经初始化过，跳过
        if (window[tableInitBoundName]) {
            console.log(`[DataTable ${tableId}] 表格已初始化，跳过重复绑定`);
        } else {
            // 创建初始化函数
            window[tableInitHandlerName] = function() {
                console.log(`[DataTable ${tableId}] 执行表格初始化函数`);
                const table = document.getElementById(tableId);
                if (table) {
                    console.log(`[DataTable ${tableId}] 找到表格元素，开始绑定事件`);
                    
                    // 更新排序点击事件（使用 onclick 会覆盖，不会重复绑定）
                    const sortableHeaders = table.querySelectorAll('th[data-sortable="1"]');
                    console.log(`[DataTable ${tableId}] 找到 ${sortableHeaders.length} 个可排序列`);
                    sortableHeaders.forEach(th => {
                        th.onclick = function() {
                            console.log(`[DataTable ${tableId}] 点击排序列:`, this.dataset.sortField || this.textContent);
                            window['handleSort_' + tableId](this);
                        };
                    });
                    
                    // 绑定搜索表单提交事件（防止重复绑定）
                    // 只有当 showSearch 为 true 时才尝试查找和绑定搜索表单
                    const showSearch = config.showSearch !== false; // 默认为 true
                    if (showSearch) {
                        const searchForm = document.getElementById(searchFormId);
                        if (searchForm) {
                            console.log(`[DataTable ${tableId}] 绑定搜索表单提交事件`);
                            // 移除旧的事件监听器
                            const oldHandler = window['_searchFormSubmitHandler_' + tableId];
                            if (oldHandler) {
                                console.log(`[DataTable ${tableId}] 移除旧的搜索表单事件监听器`);
                                searchForm.removeEventListener('submit', oldHandler);
                            }
                            // 创建新的事件处理函数
                            window['_searchFormSubmitHandler_' + tableId] = function(e) {
                                console.log(`[DataTable ${tableId}] 搜索表单提交事件触发`);
                                e.preventDefault();
                                currentPage = 1;
                                window['loadData_' + tableId]();
                            };
                            // 添加新的事件监听器
                            searchForm.addEventListener('submit', window['_searchFormSubmitHandler_' + tableId]);
                            console.log(`[DataTable ${tableId}] 搜索表单事件绑定成功`);
                        } else {
                            console.warn(`[DataTable ${tableId}] 未找到搜索表单元素:`, searchFormId);
                        }
                    } else {
                        console.log(`[DataTable ${tableId}] 搜索功能已禁用，跳过搜索表单绑定`);
                    }
                    
                    // 分页尺寸选择器事件（防止重复绑定）
                    const pageSizeSelect = document.getElementById(`${tableId}_pageSizeSelect`);
                    if (pageSizeSelect) {
                        console.log(`[DataTable ${tableId}] 绑定分页尺寸选择器事件`);
                        // 设置初始值
                        pageSizeSelect.value = pageSize;
                        
                        // 移除旧的事件监听器
                        const oldPageSizeHandler = window['_pageSizeChangeHandler_' + tableId];
                        if (oldPageSizeHandler) {
                            console.log(`[DataTable ${tableId}] 移除旧的分页尺寸事件监听器`);
                            pageSizeSelect.removeEventListener('change', oldPageSizeHandler);
                        }
                        // 创建新的事件处理函数
                        window['_pageSizeChangeHandler_' + tableId] = function(e) {
                            console.log(`[DataTable ${tableId}] 分页尺寸变更:`, e.target.value);
                            window['changePageSize_' + tableId](e.target.value);
                        };
                        // 添加新的事件监听器
                        pageSizeSelect.addEventListener('change', window['_pageSizeChangeHandler_' + tableId]);
                        console.log(`[DataTable ${tableId}] 分页尺寸事件绑定成功`);
                    } else {
                        console.log(`[DataTable ${tableId}] 未找到分页尺寸选择器元素`);
                    }
                    
                    // 从 URL 参数初始化搜索表单和分页/排序
                    console.log(`[DataTable ${tableId}] 初始化搜索表单和分页/排序`);
                    // 只有当 showSearch 为 true 时才初始化搜索表单
                    if (config.showSearch !== false) {
                        initSearchFormFromURL();
                    }
                    
                    // 从 URL 参数读取分页信息
                    const urlParams = new URLSearchParams(window.location.search);
                    const urlPage = urlParams.get('page');
                    if (urlPage && !isNaN(parseInt(urlPage))) {
                        currentPage = parseInt(urlPage);
                        console.log(`[DataTable ${tableId}] 从 URL 读取页码:`, currentPage);
                    }
                    
                    // 从 URL 参数读取排序信息
                    const urlSortField = urlParams.get('sort_field');
                    const urlSortOrder = urlParams.get('sort_order');
                    if (urlSortField) {
                        sortField = urlSortField;
                        console.log(`[DataTable ${tableId}] 从 URL 读取排序字段:`, sortField);
                    }
                    if (urlSortOrder && ['asc', 'desc'].includes(urlSortOrder.toLowerCase())) {
                        sortOrder = urlSortOrder.toLowerCase();
                        console.log(`[DataTable ${tableId}] 从 URL 读取排序方向:`, sortOrder);
                    }
                    
                    // 同步排序状态
                    syncSortState();
                    
                    // 初始加载数据
                    console.log(`[DataTable ${tableId}] 开始初始加载数据`);
                    window['loadData_' + tableId]();
                    
                    // 标记已初始化
                    window[tableInitBoundName] = true;
                    console.log(`[DataTable ${tableId}] 表格初始化完成`);
                } else {
                    console.error(`[DataTable ${tableId}] 未找到表格元素:`, tableId);
                }
            };
            
            // 如果 DOM 已准备好，立即执行；否则等待 DOMContentLoaded
            if (document.readyState === 'loading') {
                console.log(`[DataTable ${tableId}] DOM 未准备好，等待 DOMContentLoaded 事件`);
                document.addEventListener('DOMContentLoaded', function() {
                    console.log(`[DataTable ${tableId}] DOMContentLoaded 事件触发，执行初始化`);
                    window[tableInitHandlerName]();
                });
            } else {
                console.log(`[DataTable ${tableId}] DOM 已准备好，立即执行初始化`);
                window[tableInitHandlerName]();
            }
        }
        
        // 删除单条记录函数（全局函数，供操作列调用）
        if (config.showDeleteModal) {
        window['deleteRow_' + tableId] = function(id) {
            console.log(`[DataTable ${tableId}] deleteRow 函数被调用，ID:`, id);
            // 使用带 tableId 后缀的变量，避免多表格冲突
            window['_deleteItemId_' + tableId] = id;
            const modalElement = document.getElementById(deleteModalId);
            if (modalElement) {
                console.log(`[DataTable ${tableId}] 显示删除确认模态框`);
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                console.error(`[DataTable ${tableId}] 未找到删除模态框元素:`, deleteModalId);
            }
        };
        
        // 绑定确认删除按钮事件（防止重复绑定）
        const deleteConfirmHandlerName = '_deleteConfirmHandler_' + tableId;
        const deleteConfirmBoundName = '_deleteConfirmBound_' + tableId;
        
        console.log(`[DataTable ${tableId}] 开始绑定单条删除确认按钮事件`);
        console.log(`[DataTable ${tableId}] 是否已绑定:`, window[deleteConfirmBoundName]);
        
        // 如果已经绑定过，跳过
        if (window[deleteConfirmBoundName]) {
            console.log(`[DataTable ${tableId}] 单条删除确认按钮已绑定，跳过重复绑定`);
        } else {
            // 创建事件处理函数
            window[deleteConfirmHandlerName] = function() {
                console.log(`[DataTable ${tableId}] 单条删除确认按钮点击事件触发`);
                // 获取要删除的 ID（使用带 tableId 后缀的变量）
                const deleteItemId = window['_deleteItemId_' + tableId];
                console.log(`[DataTable ${tableId}] 要删除的记录ID:`, deleteItemId);
                
                if (!deleteItemId) {
                    console.warn(`[DataTable ${tableId}] 未找到要删除的记录ID`);
                    if (typeof showToast === 'function') {
                        showToast('warning', '未找到要删除的记录');
                    } else {
                        alert('未找到要删除的记录');
                    }
                    return;
                }
                
                // 获取删除路由模板
                const destroyRouteTemplate = window['destroyRouteTemplate_' + tableId];
                console.log(`[DataTable ${tableId}] 删除路由模板:`, destroyRouteTemplate);
                console.log(`[DataTable ${tableId}] executeDelete 函数存在:`, typeof executeDelete === 'function');
                
                if (destroyRouteTemplate && typeof executeDelete === 'function') {
                    console.log(`[DataTable ${tableId}] 执行删除操作，ID:`, deleteItemId);
                    // 临时设置全局变量供 executeDelete 使用（向后兼容）
                    window._deleteItemId = deleteItemId;
                    
                    executeDelete(
                        (id) => destroyRouteTemplate + '/' + id,
                        deleteModalId,
                        deleteConfirmBtnId,
                        () => {
                            console.log(`[DataTable ${tableId}] 删除成功回调执行`);
                            // 清除临时变量
                            delete window._deleteItemId;
                            delete window['_deleteItemId_' + tableId];
                            
                            // 调用组件的加载函数刷新数据
                            if (typeof window['loadData_' + tableId] === 'function') {
                                console.log(`[DataTable ${tableId}] 刷新表格数据`);
                                window['loadData_' + tableId]();
                            }
                        }
                    );
                } else if (!destroyRouteTemplate) {
                    console.error(`[DataTable ${tableId}] 删除路由未配置：destroyRouteTemplate_${tableId} 或 destroyRouteTemplate 不存在`);
                    // 如果没有配置 destroyRouteTemplate，显示错误提示
                    if (typeof showToast === 'function') {
                        showToast('danger', '删除路由未配置，请联系管理员');
                    } else {
                        alert('删除路由未配置，请联系管理员');
                    }
                } else {
                    console.error(`[DataTable ${tableId}] 删除功能未正确初始化：executeDelete 函数不存在`);
                    // 如果 executeDelete 函数不存在，显示错误提示
                    if (typeof showToast === 'function') {
                        showToast('danger', '删除功能未正确初始化，请刷新页面重试');
                    } else {
                        alert('删除功能未正确初始化，请刷新页面重试');
                    }
                }
            };
            
            // 绑定事件（使用立即执行或 DOMContentLoaded）
            function bindDeleteConfirm() {
                console.log(`[DataTable ${tableId}] 执行单条删除确认按钮绑定函数`);
                const confirmBtn = document.getElementById(deleteConfirmBtnId);
                if (confirmBtn) {
                    console.log(`[DataTable ${tableId}] 找到确认删除按钮元素`);
                    // 移除旧的事件监听器（如果存在）
                    confirmBtn.removeEventListener('click', window[deleteConfirmHandlerName]);
                    // 添加新的事件监听器
                    confirmBtn.addEventListener('click', window[deleteConfirmHandlerName]);
                    // 标记已绑定
                    window[deleteConfirmBoundName] = true;
                    console.log(`[DataTable ${tableId}] 单条删除确认按钮事件绑定成功`);
                } else {
                    console.warn(`[DataTable ${tableId}] 未找到确认删除按钮元素:`, deleteConfirmBtnId);
                }
            }
            
            // 如果 DOM 已准备好，立即绑定；否则等待 DOMContentLoaded
            if (document.readyState === 'loading') {
                console.log(`[DataTable ${tableId}] DOM 未准备好，等待 DOMContentLoaded 事件（单条删除）`);
                document.addEventListener('DOMContentLoaded', function() {
                    console.log(`[DataTable ${tableId}] DOMContentLoaded 事件触发，绑定单条删除确认按钮`);
                    bindDeleteConfirm();
                });
            } else {
                console.log(`[DataTable ${tableId}] DOM 已准备好，立即绑定单条删除确认按钮`);
                bindDeleteConfirm();
            }
        }
        }
        
        // ========== 表格相关辅助函数 ==========
        
        // 重置搜索
        window['resetSearch_' + tableId] = function() {
            const form = document.getElementById(searchFormId);
            if (form) {
                // 重置所有表单字段
                const inputs = form.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = false;
                    } else {
                        input.value = '';
                    }
                });
            }
            // 调用组件的加载函数
            if (typeof window['loadData_' + tableId] === 'function') {
                window['loadData_' + tableId]();
            }
        };
        
        // 导出数据函数（如果提供了 exportRoute）
        if (config.exportRoute) {
        window['exportData_' + tableId] = function() {
            // 获取搜索表单数据（使用与 loadData 函数相同的逻辑）
            const filters = {};
            const showSearch = config.showSearch !== false; // 默认为 true
            
            if (showSearch) {
                const searchForm = document.getElementById(searchFormId);
                if (searchForm) {
                    // 收集搜索表单的所有字段值（与 loadData 函数保持一致）
                    const formData = new FormData(searchForm);
                    
                    // 收集所有 filters 字段
                    for (const [key, value] of formData.entries()) {
                        if (key.startsWith('filters[') && key.endsWith(']')) {
                            // 提取字段名：filters[field_name] -> field_name
                            const fieldName = key.replace('filters[', '').replace(']', '');
                            const fieldValue = value.trim();
                            // 只添加非空值
                            if (fieldValue !== '') {
                                filters[fieldName] = fieldValue;
                            }
                        }
                    }
                    
                    // 添加搜索关键词（向后兼容）
                    const keyword = formData.get('keyword') || '';
                    if (keyword) {
                        filters['keyword'] = keyword;
                    }
                }
            }
            
            // 构建查询参数
            const params = new URLSearchParams();
            
            // 如果有过滤条件，添加到参数中
            if (Object.keys(filters).length > 0) {
                params.append('filters', JSON.stringify(filters));
            }
            
            // 添加排序字段和排序方向（从组件的内部变量获取，已通过 syncSortState 同步）
            const currentSortField = window['currentSortField_' + tableId] || sortField || '';
            const currentSortOrder = window['currentSortOrder_' + tableId] || sortOrder || 'desc';
            if (currentSortField) {
                params.append('sort_field', currentSortField);
                params.append('sort_order', currentSortOrder);
            }
            
            // 添加时间戳参数，避免微信内浏览器缓存导致无法下载新文件
            params.append('_t', Date.now().toString());
            
            // 构建导出 URL
            const exportUrl = config.exportRoute + '?' + params.toString();
            
            // 打开新窗口下载文件
            window.open(exportUrl, '_blank');
        };
        }
        
        // 切换搜索面板显示/隐藏
        window['toggleSearchPanel_' + tableId] = function() {
            const searchPanel = document.getElementById(searchPanelId);
            const searchBtn = document.getElementById('searchToggleBtn_' + tableId);
            const searchIcon = document.getElementById('searchToggleIcon_' + tableId);
            
            if (!searchPanel) return;
            
            const isVisible = searchPanel.style.display !== 'none';
            
            if (isVisible) {
                // 隐藏搜索面板
                searchPanel.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                searchPanel.style.opacity = '0';
                searchPanel.style.transform = 'translateY(-10px)';
                
                setTimeout(() => {
                    searchPanel.style.display = 'none';
                }, 300);
                
                if (searchIcon) {
                    searchIcon.className = 'bi bi-search';
                }
                if (searchBtn) {
                    searchBtn.classList.remove('active');
                    searchBtn.title = '搜索';
                }
            } else {
                // 显示搜索面板
                searchPanel.style.display = 'block';
                // 添加展开动画
                searchPanel.style.opacity = '0';
                searchPanel.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    searchPanel.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    searchPanel.style.opacity = '1';
                    searchPanel.style.transform = 'translateY(0)';
                }, 10);
                
                if (searchIcon) {
                    searchIcon.className = 'bi bi-x-lg';
                }
                if (searchBtn) {
                    searchBtn.classList.add('active');
                    searchBtn.title = '收起搜索';
                }
                
                // 聚焦到搜索输入框
                const keywordInput = searchPanel.querySelector('input[name="keyword"]');
                if (keywordInput) {
                    setTimeout(() => keywordInput.focus(), 300);
                }
            }
        };
        
        // 全选/取消全选
        window['toggleCheckAll_' + tableId] = function(checkbox) {
            const checkboxes = document.querySelectorAll('#' + tableId + ' .row-check');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            window['toggleRowCheck_' + tableId]();
        };
        
        // 更新选中状态
        window['toggleRowCheck_' + tableId] = function() {
            const checkboxes = document.querySelectorAll('#' + tableId + ' .row-check:checked');
            selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

            const checkAll = document.getElementById('checkAll_' + tableId);
            const totalCheckboxes = document.querySelectorAll('#' + tableId + ' .row-check');
            if (checkAll && totalCheckboxes.length > 0) {
                checkAll.checked = selectedIds.length > 0 && selectedIds.length === totalCheckboxes.length;
                checkAll.indeterminate = selectedIds.length > 0 && selectedIds.length < totalCheckboxes.length;
            }
            
            // 更新批量删除按钮状态
            const batchDeleteBtn = document.getElementById('batchDeleteBtn_' + tableId);
            if (batchDeleteBtn) {
                batchDeleteBtn.disabled = selectedIds.length === 0;
                if (selectedIds.length > 0) {
                    batchDeleteBtn.classList.remove('disabled');
                } else {
                    batchDeleteBtn.classList.add('disabled');
                }
            }
        };
        
        // 批量删除
        if (batchDestroyRoute) {
            window['batchDelete_' + tableId] = function() {
                // 重新收集选中的ID（因为数据可能已更新）
                const checkboxes = document.querySelectorAll('#' + tableId + ' .row-check:checked');
                const currentSelectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
                
                if (currentSelectedIds.length === 0) {
                    if (typeof showToast === 'function') {
                        showToast('warning', '请选择要删除的记录');
                    } else {
                        alert('请选择要删除的记录');
                    }
                    return;
                }

                if (config.showBatchDeleteModal) {
                // 使用模态框确认
                // 更新模态框消息，显示选中的记录数量
                const messageElement = document.getElementById(batchDeleteMessageId);
                if (messageElement) {
                    messageElement.textContent = batchDeleteConfirmMessageTemplate.replace('{count}', currentSelectedIds.length);
                }
                
                // 存储选中的ID到全局变量，供确认按钮使用
                window['_batchDeleteIds_' + tableId] = currentSelectedIds;
                
                // 显示模态框
                const modalElement = document.getElementById(batchDeleteModalId);
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                }
                } else {
                // 使用 confirm 确认（向后兼容）
                if (!confirm(`确定要删除选中的 ${currentSelectedIds.length} 条记录吗？\n\n警告：删除后将无法恢复！`)) {
                    return;
                }
                
                // 执行批量删除
                executeBatchDelete(currentSelectedIds);
                }
            };
            
            if (config.showBatchDeleteModal) {
            // 绑定批量删除确认按钮事件（防止重复绑定）
            const batchDeleteConfirmHandlerName = '_batchDeleteConfirmHandler_' + tableId;
            const batchDeleteConfirmBoundName = '_batchDeleteConfirmBound_' + tableId;
            
            console.log(`[DataTable ${tableId}] 开始绑定批量删除确认按钮事件`);
            console.log(`[DataTable ${tableId}] 是否已绑定:`, window[batchDeleteConfirmBoundName]);
            
            // 如果已经绑定过，跳过
            if (window[batchDeleteConfirmBoundName]) {
                console.log(`[DataTable ${tableId}] 批量删除确认按钮已绑定，跳过重复绑定`);
            } else {
                // 创建事件处理函数
                window[batchDeleteConfirmHandlerName] = function() {
                    console.log(`[DataTable ${tableId}] 批量删除确认按钮点击事件触发`);
                    const selectedIds = window['_batchDeleteIds_' + tableId] || [];
                    console.log(`[DataTable ${tableId}] 要删除的记录ID列表:`, selectedIds);
                    
                    if (selectedIds.length === 0) {
                        console.warn(`[DataTable ${tableId}] 未找到要删除的记录ID列表`);
                        if (typeof showToast === 'function') {
                            showToast('warning', '未找到要删除的记录');
                        } else {
                            alert('未找到要删除的记录');
                        }
                        return;
                    }
                    
                    // 检查批量删除路由是否配置
                    console.log(`[DataTable ${tableId}] 批量删除路由:`, batchDestroyRoute);
                    if (!batchDestroyRoute) {
                        console.error(`[DataTable ${tableId}] 批量删除路由未配置：batchDestroyRoute 为空`);
                        if (typeof showToast === 'function') {
                            showToast('danger', '批量删除路由未配置，请联系管理员');
                        } else {
                            alert('批量删除路由未配置，请联系管理员');
                        }
                        return;
                    }
                    
                    // 关闭模态框
                    console.log(`[DataTable ${tableId}] 关闭批量删除确认模态框`);
                    const modalElement = document.getElementById(batchDeleteModalId);
                    if (modalElement) {
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        }
                    }
                    
                    // 执行批量删除
                    console.log(`[DataTable ${tableId}] 执行批量删除操作，记录数:`, selectedIds.length);
                    executeBatchDelete(selectedIds);
                };
                
                // 绑定事件（使用立即执行或 DOMContentLoaded）
                function bindBatchDeleteConfirm() {
                    console.log(`[DataTable ${tableId}] 执行批量删除确认按钮绑定函数`);
                    const confirmBtn = document.getElementById(batchDeleteConfirmBtnId);
                    if (confirmBtn) {
                        console.log(`[DataTable ${tableId}] 找到批量删除确认按钮元素`);
                        // 移除旧的事件监听器（如果存在）
                        confirmBtn.removeEventListener('click', window[batchDeleteConfirmHandlerName]);
                        // 添加新的事件监听器
                        confirmBtn.addEventListener('click', window[batchDeleteConfirmHandlerName]);
                        // 标记已绑定
                        window[batchDeleteConfirmBoundName] = true;
                        console.log(`[DataTable ${tableId}] 批量删除确认按钮事件绑定成功`);
                    } else {
                        console.warn(`[DataTable ${tableId}] 未找到批量删除确认按钮元素:`, batchDeleteConfirmBtnId);
                    }
                }
                
                // 如果 DOM 已准备好，立即绑定；否则等待 DOMContentLoaded
                if (document.readyState === 'loading') {
                    console.log(`[DataTable ${tableId}] DOM 未准备好，等待 DOMContentLoaded 事件（批量删除）`);
                    document.addEventListener('DOMContentLoaded', function() {
                        console.log(`[DataTable ${tableId}] DOMContentLoaded 事件触发，绑定批量删除确认按钮`);
                        bindBatchDeleteConfirm();
                    });
                } else {
                    console.log(`[DataTable ${tableId}] DOM 已准备好，立即绑定批量删除确认按钮`);
                    bindBatchDeleteConfirm();
                }
            }
            
            // 批量删除执行函数
            function executeBatchDelete(idsToDelete) {
                // 检查参数
                if (!idsToDelete || idsToDelete.length === 0) {
                    if (typeof showToast === 'function') {
                        showToast('warning', '请选择要删除的记录');
                    } else {
                        alert('请选择要删除的记录');
                    }
                    return;
                }
                
                // 检查批量删除路由
                if (!batchDestroyRoute) {
                    if (typeof showToast === 'function') {
                        showToast('danger', '批量删除路由未配置，请联系管理员');
                    } else {
                        alert('批量删除路由未配置，请联系管理员');
                    }
                    console.error('批量删除路由未配置：batchDestroyRoute 为空');
                    return;
                }
                
                const batchDeleteBtn = document.getElementById('batchDeleteBtn_' + tableId);
                const originalHtml = batchDeleteBtn ? batchDeleteBtn.innerHTML : '';

                if (batchDeleteBtn) {
                    batchDeleteBtn.disabled = true;
                    batchDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 删除中...';
                }

                fetch(batchDestroyRoute, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ids: idsToDelete })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.code === 200) {
                        if (typeof showToast === 'function') {
                            showToast('success', result.msg || '批量删除成功');
                        } else {
                            alert(result.msg || '批量删除成功');
                        }
                        selectedIds = [];
                        const checkAll = document.getElementById('checkAll_' + tableId);
                        if (checkAll) {
                            checkAll.checked = false;
                        }
                        // 调用组件的加载函数刷新数据
                        if (typeof window['loadData_' + tableId] === 'function') {
                            window['loadData_' + tableId]();
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast('danger', result.msg || '批量删除失败');
                        } else {
                            alert(result.msg || '批量删除失败');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (typeof showToast === 'function') {
                        showToast('danger', '批量删除失败');
                    } else {
                        alert('批量删除失败');
                    }
                })
                .finally(() => {
                    if (batchDeleteBtn) {
                        batchDeleteBtn.disabled = false;
                        batchDeleteBtn.innerHTML = originalHtml;
                    }
                    // 清除存储的ID
                    window['_batchDeleteIds_' + tableId] = [];
                });
            }
            } else {
            // 批量删除执行函数（使用 confirm 确认的旧版本）
            function executeBatchDelete(idsToDelete) {
                // 检查参数
                if (!idsToDelete || idsToDelete.length === 0) {
                    if (typeof showToast === 'function') {
                        showToast('warning', '请选择要删除的记录');
                    } else {
                        alert('请选择要删除的记录');
                    }
                    return;
                }
                
                // 检查批量删除路由
                if (!batchDestroyRoute) {
                    if (typeof showToast === 'function') {
                        showToast('danger', '批量删除路由未配置，请联系管理员');
                    } else {
                        alert('批量删除路由未配置，请联系管理员');
                    }
                    console.error('批量删除路由未配置：batchDestroyRoute 为空');
                    return;
                }
                
                const batchDeleteBtn = document.getElementById('batchDeleteBtn_' + tableId);
                const originalHtml = batchDeleteBtn ? batchDeleteBtn.innerHTML : '';

                if (batchDeleteBtn) {
                    batchDeleteBtn.disabled = true;
                    batchDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 删除中...';
                }

                fetch(batchDestroyRoute, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ids: idsToDelete })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.code === 200) {
                        if (typeof showToast === 'function') {
                            showToast('success', result.msg || '批量删除成功');
                        } else {
                            alert(result.msg || '批量删除成功');
                        }
                        selectedIds = [];
                        const checkAll = document.getElementById('checkAll_' + tableId);
                        if (checkAll) {
                            checkAll.checked = false;
                        }
                        // 调用组件的加载函数刷新数据
                        if (typeof window['loadData_' + tableId] === 'function') {
                            window['loadData_' + tableId]();
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast('danger', result.msg || '批量删除失败');
                        } else {
                            alert(result.msg || '批量删除失败');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (typeof showToast === 'function') {
                        showToast('danger', '批量删除失败');
                    } else {
                        alert('批量删除失败');
                    }
                })
                .finally(() => {
                    if (batchDeleteBtn) {
                        batchDeleteBtn.disabled = false;
                        batchDeleteBtn.innerHTML = originalHtml;
                    }
                });
            }
            }
        }
        
        // 格式化日期（全局函数，不需要 tableId 后缀）
        if (typeof window.formatDate === 'undefined') {
            window.formatDate = function(dateStr) {
                if (!dateStr) return '';
                const date = new Date(dateStr);
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                const seconds = String(date.getSeconds()).padStart(2, '0');
                return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
            };
        }
        
        // 注意：toggleStatus 函数定义在 admin.common.scripts 中
        // 这里不再重复定义，避免冲突
        
        // 绑定行复选框事件（用于更新选中状态，防止重复绑定）
        const rowCheckHandlerName = '_rowCheckHandler_' + tableId;
        const rowCheckBoundName = '_rowCheckBound_' + tableId;
        
        console.log(`[DataTable ${tableId}] 开始绑定行复选框事件`);
        console.log(`[DataTable ${tableId}] 是否已绑定:`, window[rowCheckBoundName]);
        
        // 如果已经绑定过，跳过
        if (window[rowCheckBoundName]) {
            console.log(`[DataTable ${tableId}] 行复选框事件已绑定，跳过重复绑定`);
        } else {
            // 创建事件处理函数
            window[rowCheckHandlerName] = function(e) {
                if (e.target.classList.contains('row-check')) {
                    console.log(`[DataTable ${tableId}] 行复选框变更事件触发，目标:`, e.target);
                    window['toggleRowCheck_' + tableId]();
                }
            };
            
            // 绑定事件（使用立即执行或 DOMContentLoaded）
            function bindRowCheck() {
                console.log(`[DataTable ${tableId}] 执行行复选框绑定函数`);
                const table = document.getElementById(tableId);
                if (table) {
                    console.log(`[DataTable ${tableId}] 找到表格元素，绑定行复选框事件`);
                    // 移除旧的事件监听器（如果存在）
                    table.removeEventListener('change', window[rowCheckHandlerName]);
                    // 添加新的事件监听器（使用事件委托，支持动态添加的行）
                    table.addEventListener('change', window[rowCheckHandlerName]);
                    console.log(`[DataTable ${tableId}] 行复选框事件绑定成功`);
                    
                    // 初始化批量删除按钮状态
                    if (batchDestroyRoute) {
                        const batchDeleteBtn = document.getElementById('batchDeleteBtn_' + tableId);
                        if (batchDeleteBtn) {
                            batchDeleteBtn.disabled = true;
                            batchDeleteBtn.classList.add('disabled');
                            console.log(`[DataTable ${tableId}] 初始化批量删除按钮为禁用状态`);
                        }
                    }
                    
                    // 标记已绑定
                    window[rowCheckBoundName] = true;
                } else {
                    console.warn(`[DataTable ${tableId}] 未找到表格元素:`, tableId);
                }
            }
            
            // 如果 DOM 已准备好，立即绑定；否则等待 DOMContentLoaded
            if (document.readyState === 'loading') {
                console.log(`[DataTable ${tableId}] DOM 未准备好，等待 DOMContentLoaded 事件（行复选框）`);
                document.addEventListener('DOMContentLoaded', function() {
                    console.log(`[DataTable ${tableId}] DOMContentLoaded 事件触发，绑定行复选框事件`);
                    bindRowCheck();
                });
            } else {
                console.log(`[DataTable ${tableId}] DOM 已准备好，立即绑定行复选框事件`);
                bindRowCheck();
            }
        }
    })();
    </script>
