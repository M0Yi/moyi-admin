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
 * @param array $statusFilterConfig 状态筛选卡项配置（可选，如果设置则在表格上方显示状态筛选卡项）
 *   - filter_field: 用于筛选的字段名（必填）
 *   - options: 筛选选项数组，每个选项包含：
 *     - value: 选项值（必填）
 *     - label: 显示标签（必填）
 *     - variant: 按钮样式变体（可选，默认 'outline-secondary'）
 *     - icon: 图标类名（可选）
 *     - count: 计数显示（可选，如果设置则在标签后显示数量）
 *   - show_all: 是否显示"全部"选项（可选，默认 true）
 *   - all_label: "全部"选项的标签（可选，默认 '全部'）
 *   - all_variant: "全部"选项的样式变体（可选，默认 'outline-secondary'）
 *   - multiple: 是否支持多选（可选，默认 false）
 *   - default_value: 默认选中的值（可选，可以是单个值或数组）
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
            $labelLowerEn = strtolower($label); // 英文小写

            // 成功/启用/积极状态 - success (绿色)
            $successPatterns = [
                '/^(启用|激活|是|开启|正常|在线|公开|显示|已启用|已激活|已开启|已发布|已完成|已通过|已审核|已确认|有效|可用|正常|成功|通过|同意|允许|是|yes|true|enabled|active|on|open|published|completed|approved|confirmed|valid|available|normal|success|pass|agree|allow)$/ui',
            ];
            foreach ($successPatterns as $pattern) {
                if (preg_match($pattern, $label)) {
                    return 'success';
                }
            }

            // 禁用/停用/消极状态 - secondary (灰色)
            $secondaryPatterns = [
                '/^(禁用|停用|否|关闭|异常|离线|隐藏|删除|已禁用|已停用|已关闭|已删除|无效|不可用|否|no|false|disabled|inactive|off|closed|deleted|invalid|unavailable|none|null)$/ui',
            ];
            foreach ($secondaryPatterns as $pattern) {
                if (preg_match($pattern, $label)) {
                    return 'secondary';
                }
            }

            // 警告/待处理状态 - warning (黄色)
            $warningPatterns = [
                '/^(警告|待审核|待处理|待发布|草稿|待确认|待支付|待发货|待收货|待评价|进行中|处理中|审核中|pending|draft|reviewing|processing|in_progress|in-progress|waiting|warn|warning)$/ui',
            ];
            foreach ($warningPatterns as $pattern) {
                if (preg_match($pattern, $label)) {
                    return 'warning';
                }
            }

            // 错误/危险状态 - danger (红色)
            $dangerPatterns = [
                '/^(错误|拒绝|失败|已删除|已禁用|已拒绝|已失败|已取消|已过期|已锁定|已封禁|异常|错误|error|failed|rejected|cancelled|expired|locked|banned|exception|fail|deny|refuse)$/ui',
            ];
            foreach ($dangerPatterns as $pattern) {
                if (preg_match($pattern, $label)) {
                    return 'danger';
                }
            }

            // 信息/默认状态 - info (蓝色)
            $infoPatterns = [
                '/^(信息|默认|其他|普通|一般|info|information|default|other|normal|general|common)$/ui',
            ];
            foreach ($infoPatterns as $pattern) {
                if (preg_match($pattern, $label)) {
                    return 'info';
                }
            }

            // 数字键值匹配
            if (is_numeric($key)) {
                $numKey = (int)$key;
                if ($numKey === 0) {
                    return 'secondary';
                }
                if ($numKey === 1) {
                    return 'success';
                }
                if ($numKey === 2) {
                    return 'warning';
                }
                if ($numKey >= 3) {
                    return 'info';
                }
            }

            // 布尔值匹配
            if (is_bool($key)) {
                return $key ? 'success' : 'secondary';
            }

            // 字符串键值匹配（常见状态值）
            $keyStr = (string)$key;
            $keyLower = mb_strtolower($keyStr);
            if (in_array($keyLower, ['1', 'true', 'yes', 'on', 'enabled', 'active', '启用', '是', '开启'])) {
                return 'success';
            }
            if (in_array($keyLower, ['0', 'false', 'no', 'off', 'disabled', 'inactive', '禁用', '否', '关闭'])) {
                return 'secondary';
            }

            // 如果都不匹配，使用循环分配颜色
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
                // 状态字段
                'status' => [
                    '0' => 'secondary',
                    '1' => 'success',
                    '启用' => 'success',
                    '禁用' => 'secondary',
                    '是' => 'success',
                    '否' => 'secondary',
                    'active' => 'success',
                    'inactive' => 'secondary',
                    'enabled' => 'success',
                    'disabled' => 'secondary',
                ],
                // 类型字段
                'type' => [
                    'menu' => 'primary',
                    'button' => 'info',
                    'link' => 'warning',
                    'page' => 'primary',
                    'api' => 'info',
                    'file' => 'warning',
                ],
                // 是否字段（is_* 开头）
                'is_active' => [
                    '0' => 'secondary',
                    '1' => 'success',
                    'false' => 'secondary',
                    'true' => 'success',
                ],
                'is_enabled' => [
                    '0' => 'secondary',
                    '1' => 'success',
                ],
                'is_admin' => [
                    '0' => 'secondary',
                    '1' => 'danger',
                ],
                'is_visible' => [
                    '0' => 'secondary',
                    '1' => 'success',
                ],
                'is_deleted' => [
                    '0' => 'success',
                    '1' => 'danger',
                ],
                // 审核状态
                'audit_status' => [
                    '0' => 'warning',
                    '1' => 'success',
                    '2' => 'danger',
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    '待审核' => 'warning',
                    '已通过' => 'success',
                    '已拒绝' => 'danger',
                ],
                'review_status' => [
                    '0' => 'warning',
                    '1' => 'success',
                    '2' => 'danger',
                ],
                // 支付状态
                'payment_status' => [
                    '0' => 'warning',
                    '1' => 'success',
                    '2' => 'danger',
                    'unpaid' => 'warning',
                    'paid' => 'success',
                    'refunded' => 'info',
                    '未支付' => 'warning',
                    '已支付' => 'success',
                    '已退款' => 'info',
                ],
                // 订单状态
                'order_status' => [
                    '0' => 'warning',
                    '1' => 'info',
                    '2' => 'primary',
                    '3' => 'success',
                    '4' => 'danger',
                    'pending' => 'warning',
                    'paid' => 'info',
                    'shipped' => 'primary',
                    'completed' => 'success',
                    'cancelled' => 'danger',
                ],
                // 性别
                'gender' => [
                    '0' => 'info',
                    '1' => 'primary',
                    '2' => 'warning',
                    'male' => 'primary',
                    'female' => 'warning',
                    'other' => 'info',
                    '男' => 'primary',
                    '女' => 'warning',
                    '其他' => 'info',
                ],
                // 优先级
                'priority' => [
                    '0' => 'secondary',
                    '1' => 'info',
                    '2' => 'warning',
                    '3' => 'danger',
                    'low' => 'info',
                    'medium' => 'warning',
                    'high' => 'danger',
                    '低' => 'info',
                    '中' => 'warning',
                    '高' => 'danger',
                ],
                // 级别
                'level' => [
                    '1' => 'secondary',
                    '2' => 'info',
                    '3' => 'primary',
                    '4' => 'warning',
                    '5' => 'danger',
                ],
            ];

            // 获取精确字段名映射
            $fieldMapping = $specialMappings[$fieldName] ?? [];
            
            // 智能字段名匹配：根据字段名模式自动识别
            $fieldNameLower = mb_strtolower($fieldName);
            
            // 布尔字段（is_*, has_*, can_* 开头）
            if (preg_match('/^(is_|has_|can_)/', $fieldNameLower)) {
                // 如果没有精确映射，使用默认布尔映射
                if (empty($fieldMapping)) {
                    $fieldMapping = [
                        '0' => 'secondary',
                        '1' => 'success',
                        'false' => 'secondary',
                        'true' => 'success',
                        '否' => 'secondary',
                        '是' => 'success',
                    ];
                }
            }
            
            // 状态字段（*_status 结尾）
            if (str_ends_with($fieldNameLower, '_status') || $fieldNameLower === 'status') {
                if (empty($fieldMapping)) {
                    $fieldMapping = [
                        '0' => 'secondary',
                        '1' => 'success',
                        '2' => 'warning',
                        '3' => 'danger',
                        'pending' => 'warning',
                        'active' => 'success',
                        'inactive' => 'secondary',
                        'disabled' => 'secondary',
                        '待处理' => 'warning',
                        '启用' => 'success',
                        '禁用' => 'secondary',
                    ];
                }
            }
            
            // 类型字段（*_type 结尾）
            if (str_ends_with($fieldNameLower, '_type') || $fieldNameLower === 'type') {
                if (empty($fieldMapping)) {
                    // 类型字段使用循环颜色，但优先使用 primary
                    $fieldMapping = [];
                }
            }

            foreach ($options as $key => $option) {
                $label = $option['label'] ?? (string)$key;
                $color = $option['color'] ?? null;
                $optionKey = (string)$key;

                // 优先级：1. 显式设置的颜色 2. 字段名精确映射 3. 字段名模式匹配 4. 智能值匹配
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
    $toggleStatusRouteBase = $modelNameForColumns ? admin_route("u/{$modelNameForColumns}") . '/{id}/toggle-status' : '';
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
            } elseif ($type === 'badge' && empty($field['options'])) {
                // 自动为 badge 类型字段生成默认 badgeMap（智能匹配）
                $fieldNameLower = mb_strtolower($name);
                
                // 状态字段（status, *_status）
                if ($name === 'status' || str_ends_with($fieldNameLower, '_status')) {
                    $defaultStatusOptions = [
                        '1' => ['label' => '启用', 'color' => null],
                        '0' => ['label' => '禁用', 'color' => null],
                    ];
                    $columnConfig['badgeMap'] = universal_build_badge_map_from_options($defaultStatusOptions, $name);
                }
                // 布尔字段（is_*, has_*, can_*）
                elseif (preg_match('/^(is_|has_|can_)/', $fieldNameLower)) {
                    $defaultBoolOptions = [
                        '1' => ['label' => '是', 'color' => null],
                        '0' => ['label' => '否', 'color' => null],
                    ];
                    $columnConfig['badgeMap'] = universal_build_badge_map_from_options($defaultBoolOptions, $name);
                }
                // 类型字段（type, *_type）
                elseif ($name === 'type' || str_ends_with($fieldNameLower, '_type')) {
                    // 类型字段如果没有 options，保持为空，让前端根据实际值智能匹配
                    // 前端会使用 universal_get_variant_for_value 进行智能匹配
                }
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
                            $columnConfig['onChange'] = "toggleStatus({id}, this, '{$toggleStatusRouteBase}')";
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
                    $columnConfig['onChange'] = "toggleStatus({id}, this, '{$toggleStatusRouteBase}')";
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
    // 搜索功能开关（搜索表单由JavaScript动态渲染）
    $showSearch = $showSearch ?? true;

    // 状态筛选配置处理
    $statusFilterConfig = $statusFilterConfig ?? null;
    $hasStatusFilter = !empty($statusFilterConfig) && !empty($statusFilterConfig['filter_field']) && !empty($statusFilterConfig['options']);
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
    $batchDeleteModalTitle = $batchDeleteModalTitle ?? '确认删除';
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

@if($hasStatusFilter)
    @include('admin.components.data-table.status-filter', [
        'tableId' => $tableId,
        'statusFilterConfig' => $statusFilterConfig
    ])
@endif

@include('admin.components.data-table.search-form', [
    'showSearch' => $showSearch,
    'searchPanelId' => $searchPanelId,
    'searchFormId' => $searchFormId
])

@include('admin.components.data-table.toolbar')

@include('admin.components.data-table.table')

@include('admin.components.data-table.pagination')



@include('admin.components.data-table.modals')

{{-- 引入工具栏渲染器脚本 --}}
@include('components.admin-script', ['path' => '/js/components/toolbar-renderer.js'])

@include('admin.components.data-table.scripts-config')

{{-- 引入数据表格组件 JS 脚本 --}}
@include('components.data-table-with-columns-js')
