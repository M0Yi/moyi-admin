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
        // 创建页面路由（用于批量复制时打开创建页并预填充）
        const createRoute = config.createRoute;
        // iframe shell 通道前缀（可选）
        const iframeShellChannel = config.iframeShellChannel;
        const columns = config.columns;
        const editRouteTemplate = config.editRouteTemplate;
        const deleteModalId = config.deleteModalId;
        const deleteConfirmBtnId = deleteModalId + 'ConfirmBtn';
        
        // 初始化工具栏渲染器（如果可用）
        if (window.ToolbarRenderer && config.toolbarConfig) {
            const toolbarConfig = {
                ...config.toolbarConfig,
                tableId: tableId,
                storageKey: storageKey,
                columns: columns,
                // 传递 searchConfig，用于判断是否显示搜索按钮
                searchConfig: config.searchConfig,
                // 传递 showSearch 参数（已包含 features['search'] 功能开关检查）
                showSearch: config.toolbarConfig.showSearch !== undefined 
                    ? config.toolbarConfig.showSearch 
                    : config.showSearch
            };
            
            // 延迟初始化，确保 DOM 已准备好
            function initToolbar() {
                const renderer = new window.ToolbarRenderer({
                    config: toolbarConfig,
                    containerId: 'toolbarContainer_' + tableId,
                    tableId: tableId,
                    storageKey: storageKey,
                    columns: columns
                });
                
                // 保存渲染器实例
                window['_toolbarRenderer_' + tableId] = renderer;
                console.log(`[DataTable ${tableId}] 工具栏渲染器已初始化`);
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initToolbar);
            } else {
                // 延迟一点，确保容器已创建
                setTimeout(initToolbar, 0);
            }
        }
        
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
        const showPagination = config.showPagination !== false; // 默认为 true，如果设置为 false 则关闭分页
        
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
            let form = document.getElementById(searchFormId);
            const filters = {};
            
            // 如果获取的元素不是 form，尝试在容器内查找 form 元素
            // （因为 searchFormId 可能指向容器 div，而实际的 form 在容器内）
            if (form && form.tagName !== 'FORM') {
                form = form.querySelector('form');
            }
            
            // 确保 form 是一个真正的 HTMLFormElement
            if (form && form instanceof HTMLFormElement) {
                try {
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
                } catch (error) {
                    console.warn(`[DataTable ${tableId}] 收集搜索表单数据失败:`, error);
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

            // 解析 ajaxUrl，提取基础 URL 和现有查询参数
            let baseUrl = ajaxUrl;
            let existingParams = new URLSearchParams();
            
            const queryIndex = ajaxUrl.indexOf('?');
            if (queryIndex !== -1) {
                // 分离基础 URL 和查询字符串
                baseUrl = ajaxUrl.substring(0, queryIndex);
                const queryString = ajaxUrl.substring(queryIndex + 1);
                existingParams = new URLSearchParams(queryString);
            }

            // 创建新的参数对象，优先使用新值（覆盖旧值）
            const params = new URLSearchParams();
            
            // 先添加现有参数（除了我们要覆盖的系统参数）
            const systemParams = ['_ajax', 'page', 'page_size', 'keyword', 'sort_field', 'sort_order', 'filters'];
            for (const [key, value] of existingParams.entries()) {
                if (!systemParams.includes(key)) {
                    // 保留非系统参数
                    params.append(key, value);
                }
            }
            
            // 添加/覆盖系统参数
            params.set('_ajax', '1');
            // 只有在启用分页时才传递分页参数
            if (showPagination) {
            params.set('page', String(currentPage));
            params.set('page_size', String(pageSize));
            } else {
                // 关闭分页时，传递 page_size=0 表示返回所有数据
                params.set('page_size', '0');
            }
            params.set('keyword', ''); // 保留 keyword 参数（向后兼容），但不再使用
            params.set('sort_field', sortField);
            params.set('sort_order', sortOrder);

            // 如果有过滤条件，添加到参数中
            if (Object.keys(filters).length > 0) {
                params.set('filters', JSON.stringify(filters));
            }

            // 添加额外的 AJAX 参数（会覆盖同名参数）
            if (config.ajaxParams && typeof config.ajaxParams === 'object') {
                for (const [key, value] of Object.entries(config.ajaxParams)) {
                    params.set(key, String(value));
                }
            }

            // 构建完整的 URL
            const fullUrl = baseUrl + '?' + params.toString();

            fetch(fullUrl, {
                signal: currentRequestController.signal
            })
                .then(async response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    // 检查 Content-Type，如果不是 JSON，可能是返回了 HTML 错误页面
                    const contentType = response.headers.get('content-type');
                    if (contentType && !contentType.includes('application/json')) {
                        const text = await response.text();
                        console.error('服务器返回了非 JSON 响应:', {
                            contentType: contentType,
                            preview: text.substring(0, 200)
                        });
                        throw new Error('服务器返回了非 JSON 格式的响应，可能是路由错误或服务器异常');
                    }
                    
                    return response.json();
                })
                .then(result => {
                    // 清除请求控制器
                    currentRequestController = null;
                    
                    // 调试日志
                    console.log(`[DataTable ${tableId}] 响应数据:`, result);
                    
                    if (result.code === 200) {
                        // 检查数据格式
                        if (!result.data) {
                            console.error(`[DataTable ${tableId}] 响应数据格式错误: result.data 不存在`, result);
                            const tbody = document.querySelector(`#${tableId} tbody`);
                            if (tbody) {
                                const colspan = columns.length + (batchDestroyRoute ? 1 : 0);
                                tbody.innerHTML = `
                                    <tr>
                                        <td colspan="${colspan}" class="text-center text-danger py-4">
                                            <i class="bi bi-exclamation-circle me-2"></i>
                                            数据格式错误：缺少 data 字段
                                        </td>
                                    </tr>
                                `;
                            }
                            return;
                        }
                        
                        // 确保 data.data 存在（兼容不同的响应格式）
                        if (!result.data.data) {
                            // 兼容 list 字段（向后兼容）
                            if (result.data.list && Array.isArray(result.data.list)) {
                                console.warn(`[DataTable ${tableId}] 检测到使用 list 字段，已自动转换为 data 字段`, result.data);
                                result.data.data = result.data.list;
                                // 删除 list 字段，统一使用 data
                                delete result.data.list;
                            } else if (Array.isArray(result.data)) {
                            // 如果 result.data 本身就是数组，包装一下
                                console.warn(`[DataTable ${tableId}] 响应数据格式异常: result.data 是数组，已自动包装`, result.data);
                                result.data = {
                                    data: result.data,
                                    total: result.data.length,
                                    page: 1,
                                    page_size: result.data.length
                                };
                            } else {
                                // 否则设置为空数据
                                console.warn(`[DataTable ${tableId}] 响应数据格式异常: result.data.data 不存在，使用空数据`, result.data);
                                result.data = {
                                    data: [],
                                    total: 0,
                                    page: 1,
                                    page_size: 15
                                };
                            }
                        }
                        
                        renderTable(result.data);
                        // 只有在启用分页时才渲染分页组件
                        if (showPagination) {
                        renderPagination(result.data);
                        } else {
                            // 如果关闭分页，隐藏分页相关的 DOM 元素
                            const pagination = document.getElementById(`${tableId}_pagination`);
                            if (pagination) {
                                pagination.style.display = 'none';
                            }
                        }
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
                        console.error(`[DataTable ${tableId}] 服务器返回错误:`, result);
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
                    
                    // 将 errorMessage 定义移到 if (tbody) 之前，确保作用域正确
                    let errorMessage = '加载数据失败';
                    if (error.message) {
                        if (error.message.includes('Failed to fetch')) {
                            errorMessage = '网络连接失败，请检查网络设置';
                        } else if (error.message.includes('timeout')) {
                            errorMessage = '请求超时，请稍后重试';
                        } else if (error.message.includes('非 JSON 格式')) {
                            errorMessage = '服务器返回格式错误，请检查路由配置或联系管理员';
                        } else if (error.message.includes('Unexpected token')) {
                            errorMessage = '服务器返回了非 JSON 响应，可能是路由错误或服务器异常';
                        } else {
                            errorMessage = error.message;
                        }
                    }
                    
                    const tbody = document.querySelector(`#${tableId} tbody`);
                    if (tbody) {
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

            // 防御性检查：确保 data 和 data.data 存在
            if (!data) {
                console.error(`[DataTable ${tableId}] renderTable: data 参数为空`);
                const colspan = columns.length + (batchDestroyRoute ? 1 : 0);
                const emptyMessage = config.emptyMessage || '暂无数据';
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${colspan}" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            ${emptyMessage}
                        </td>
                    </tr>
                `;
                return;
            }

            // 确保 data.data 是数组
            if (!data.data || !Array.isArray(data.data)) {
                console.error(`[DataTable ${tableId}] renderTable: data.data 不是数组`, data);
                const colspan = columns.length + (batchDestroyRoute ? 1 : 0);
                const emptyMessage = config.emptyMessage || '暂无数据';
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${colspan}" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            ${emptyMessage}
                        </td>
                    </tr>
                `;
                return;
            }

            if (data.data.length === 0) {
                const colspan = columns.length + (batchDestroyRoute ? 1 : 0);
                const emptyMessage = config.emptyMessage || '暂无数据';
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${colspan}" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            ${emptyMessage}
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

                    // 不在这里设置初始的 display 样式，统一由列显示设置函数处理
                    // 这样可以避免与 localStorage 中的设置冲突
                    const cellClass = column.class || '';

                    html += `<td data-column="${column.index}" class="${cellClass}">`;

                    // 使用列类型渲染器（复用现有的 cell 组件逻辑）
                    html += renderCell(value, column, row);

                    html += '</td>';
                });

                html += '</tr>';
            });

            tbody.innerHTML = html;

            // 应用浏览器本地保存的列显示设置
            // 优先使用 ToolbarRenderer 的 restoreColumnVisibility（如果已初始化）
            // 否则使用本地的 applyColumnVisibilityPreferences
            const toolbarRenderer = window['_toolbarRenderer_' + tableId];
            if (toolbarRenderer && typeof toolbarRenderer.restoreColumnVisibility === 'function') {
                // 延迟一点，确保 DOM 已更新
                setTimeout(() => {
                    toolbarRenderer.restoreColumnVisibility();
                }, 0);
            } else {
                // 如果没有工具栏渲染器，使用本地函数
                applyColumnVisibilityPreferences();
            }
        }

        // 渲染单元格（根据列类型）
        // 智能匹配徽章颜色（前端版本，与后端逻辑保持一致）
        function getBadgeVariantForValue(key, label, fieldName) {
            const valueStr = String(key);
            const labelLower = (label || '').toLowerCase();
            const fieldNameLower = (fieldName || '').toLowerCase();
            
            // 成功/启用状态
            const successPatterns = [
                /^(启用|激活|是|开启|正常|在线|公开|显示|已启用|已激活|已开启|已发布|已完成|已通过|已审核|已确认|有效|可用|正常|成功|通过|同意|允许|是|yes|true|enabled|active|on|open|published|completed|approved|confirmed|valid|available|normal|success|pass|agree|allow)$/i
            ];
            for (const pattern of successPatterns) {
                if (pattern.test(label)) {
                    return 'success';
                }
            }
            
            // 禁用/停用状态
            const secondaryPatterns = [
                /^(禁用|停用|否|关闭|异常|离线|隐藏|删除|已禁用|已停用|已关闭|已删除|无效|不可用|否|no|false|disabled|inactive|off|closed|deleted|invalid|unavailable|none|null)$/i
            ];
            for (const pattern of secondaryPatterns) {
                if (pattern.test(label)) {
                    return 'secondary';
                }
            }
            
            // 警告/待处理状态
            const warningPatterns = [
                /^(警告|待审核|待处理|待发布|草稿|待确认|待支付|待发货|待收货|待评价|进行中|处理中|审核中|pending|draft|reviewing|processing|in_progress|in-progress|waiting|warn|warning)$/i
            ];
            for (const pattern of warningPatterns) {
                if (pattern.test(label)) {
                    return 'warning';
                }
            }
            
            // 错误/危险状态
            const dangerPatterns = [
                /^(错误|拒绝|失败|已删除|已禁用|已拒绝|已失败|已取消|已过期|已锁定|已封禁|异常|错误|error|failed|rejected|cancelled|expired|locked|banned|exception|fail|deny|refuse)$/i
            ];
            for (const pattern of dangerPatterns) {
                if (pattern.test(label)) {
                    return 'danger';
                }
            }
            
            // 信息状态
            const infoPatterns = [
                /^(信息|默认|其他|普通|一般|info|information|default|other|normal|general|common)$/i
            ];
            for (const pattern of infoPatterns) {
                if (pattern.test(label)) {
                    return 'info';
                }
            }
            
            // 数字键值匹配
            if (/^\d+$/.test(valueStr)) {
                const numKey = parseInt(valueStr);
                if (numKey === 0) return 'secondary';
                if (numKey === 1) return 'success';
                if (numKey === 2) return 'warning';
                if (numKey >= 3) return 'info';
            }
            
            // 布尔值匹配
            if (valueStr === 'true' || valueStr === '1') return 'success';
            if (valueStr === 'false' || valueStr === '0') return 'secondary';
            
            // 字段名模式匹配
            if (/^(is_|has_|can_)/.test(fieldNameLower)) {
                if (valueStr === '1' || valueStr === 'true' || labelLower === '是') return 'success';
                if (valueStr === '0' || valueStr === 'false' || labelLower === '否') return 'secondary';
            }
            
            if (fieldNameLower.endsWith('_status') || fieldNameLower === 'status') {
                if (valueStr === '1' || labelLower.includes('启用') || labelLower.includes('active')) return 'success';
                if (valueStr === '0' || labelLower.includes('禁用') || labelLower.includes('inactive')) return 'secondary';
                if (labelLower.includes('pending') || labelLower.includes('待')) return 'warning';
                if (labelLower.includes('rejected') || labelLower.includes('拒绝')) return 'danger';
            }
            
            // 默认返回 primary（更醒目）
            return 'primary';
        }

        // HTML转义函数
        function escapeHtml(text) {
            if (text === null || text === undefined) {
                return '';
            }
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // 渲染键值类型单元格
        function renderKeyValueCell(value, column) {
            if (!value) {
                return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
            }
            
            let keyValuePairs = [];
            try {
                const parsed = typeof value === 'string' ? JSON.parse(value) : value;
                if (Array.isArray(parsed)) {
                    keyValuePairs = parsed;
                } else if (typeof parsed === 'object' && parsed !== null) {
                    // 对象格式转换为数组格式
                    keyValuePairs = Object.entries(parsed).map(([key, val]) => ({
                        key: key,
                        value: val
                    }));
                }
            } catch (e) {
                // 解析失败，返回原始值
                return `<code class="text-muted small">${escapeHtml(String(value))}</code>`;
            }
            
            if (keyValuePairs.length === 0) {
                return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
            }
            
            // 渲染为键值对列表
            let html = '<div class="key-value-list" style="max-width: 300px;">';
            keyValuePairs.forEach((pair, index) => {
                const key = escapeHtml(String(pair.key || ''));
                const val = escapeHtml(String(pair.value || ''));
                html += `
                    <div class="d-flex align-items-start gap-2 mb-1" style="font-size: 0.875rem;">
                        <span class="badge bg-secondary text-nowrap" style="min-width: 60px;">${key}</span>
                        <span class="text-break">${val}</span>
                    </div>
                `;
            });
            html += '</div>';
            
            return html;
        }

        // 渲染多键值类型单元格
        function renderMultiKeyValueCell(value, column) {
            if (!value) {
                return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
            }
            
            let multiKeyValuePairs = [];
            try {
                const parsed = typeof value === 'string' ? JSON.parse(value) : value;
                if (Array.isArray(parsed)) {
                    // 新格式：[{key1: 'v1', key2: 'v2', ...}, {key1: 'v3', key2: 'v4', ...}]
                    multiKeyValuePairs = parsed;
                } else if (typeof parsed === 'object' && parsed !== null) {
                    // 单个对象格式，转换为数组格式
                    multiKeyValuePairs = [parsed];
                }
            } catch (e) {
                // 解析失败，返回原始值
                return `<code class="text-muted small">${escapeHtml(String(value))}</code>`;
            }
            
            if (multiKeyValuePairs.length === 0) {
                return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
            }
            
            // 获取配置的键值对选项（用于显示标签）
            const options = column.options || [];
            const keyLabelMap = {};
            if (Array.isArray(options)) {
                options.forEach(opt => {
                    const key = String(opt.key ?? opt.value ?? '');
                    const label = String(opt.label ?? opt.value ?? opt.key ?? '');
                    if (key) {
                        keyLabelMap[key] = label;
                    }
                });
            }
            
            // 渲染为多键值对列表
            let html = '<div class="multi-key-value-list" style="max-width: 500px;">';
            multiKeyValuePairs.forEach((pairValues, index) => {
                const entries = Object.entries(pairValues || {});
                if (entries.length === 0) {
                    return;
                }
                
                html += `
                    <div class="mb-2 p-2 border rounded" style="font-size: 0.875rem;">
                        <div class="mb-1 fw-bold text-muted" style="font-size: 0.75rem;">组合 ${index + 1}</div>
                        <div class="d-flex flex-column gap-1">
                `;
                
                entries.forEach(([key, val]) => {
                    const label = keyLabelMap[key] || key;
                    const valueStr = escapeHtml(String(val || ''));
                    html += `
                        <div class="d-flex align-items-start gap-2">
                            <span class="badge bg-secondary text-nowrap" style="min-width: 80px; font-size: 0.75rem;">${escapeHtml(label)}</span>
                            <span class="text-break">${valueStr || '<span class="text-muted">-</span>'}</span>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            return html;
        }
        
        function renderObjectKeyValueCell(value, column) {
            if (!value) {
                return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
            }
            
            let objectKeyValue = {};
            try {
                const parsed = typeof value === 'string' ? JSON.parse(value) : value;
                if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
                    objectKeyValue = parsed;
                } else if (Array.isArray(parsed) && parsed.length > 0) {
                    // 如果是数组，取第一个元素
                    objectKeyValue = parsed[0];
                }
            } catch (e) {
                // 解析失败，返回原始值
                return `<code class="text-muted small">${escapeHtml(String(value))}</code>`;
            }
            
            if (Object.keys(objectKeyValue).length === 0) {
                return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
            }
            
            // 获取配置的键值对选项（用于显示标签）
            const options = column.options || [];
            const keyLabelMap = {};
            if (Array.isArray(options)) {
                options.forEach(opt => {
                    const key = String(opt.key ?? opt.value ?? '');
                    const label = String(opt.label ?? opt.value ?? opt.key ?? '');
                    if (key) {
                        keyLabelMap[key] = label;
                    }
                });
            }
            
            // 渲染为键值对列表
            let html = '<div class="object-key-value-list" style="max-width: 500px;">';
            html += '<div class="p-2 border rounded" style="font-size: 0.875rem;">';
            html += '<div class="d-flex flex-column gap-1">';
            
            Object.entries(objectKeyValue).forEach(([key, val]) => {
                const label = keyLabelMap[key] || key;
                const valueStr = escapeHtml(String(val || ''));
                html += `
                    <div class="d-flex align-items-start gap-2">
                        <span class="badge bg-secondary text-nowrap" style="min-width: 80px; font-size: 0.75rem;">${escapeHtml(label)}</span>
                        <span class="text-break">${valueStr || '<span class="text-muted">-</span>'}</span>
                    </div>
                `;
            });
            
            html += '</div></div></div>';
            
            return html;
        }
        
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
                
                case 'key_value':
                    return renderKeyValueCell(value, column);
                case 'multi_key_value':
                    return renderMultiKeyValueCell(value, column);
                
                case 'object_key_value':
                    return renderObjectKeyValueCell(value, column);
                
                case 'color':
                    if (!value) {
                        return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
                    }
                    // 规范化颜色值
                    let normalizedColor = value.startsWith('#') ? value : `#${value}`;
                    // 验证是否为有效的 HEX 颜色值
                    if (!/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/.test(normalizedColor)) {
                        normalizedColor = value; // 如果不是有效的 HEX，使用原始值
                    }
                    return `
                        <div class="d-flex align-items-center gap-2">
                            <span class="color-swatch d-inline-block" 
                                  style="width: 24px; height: 24px; background-color: ${escapeHtml(normalizedColor)}; border: 1px solid #dee2e6; border-radius: 4px;"></span>
                            <code class="small">${escapeHtml(value)}</code>
                        </div>
                    `;
                
                case 'gradient':
                    if (!value) {
                        return '<span class="text-muted" style="font-size: 0.875rem;">-</span>';
                    }
                    return `
                        <div class="d-flex align-items-center gap-2">
                            <span class="gradient-swatch d-inline-block" 
                                  style="width: 60px; height: 24px; background: ${escapeHtml(value)}; border: 1px solid #dee2e6; border-radius: 4px;"></span>
                            <code class="small text-break" style="max-width: 200px;">${escapeHtml(value)}</code>
                        </div>
                    `;
                
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
                        // 使用配置的 badgeMap
                        const badge = column.badgeMap[value];
                        return `<span class="badge bg-${badge.variant || 'secondary'}">${badge.text || value}</span>`;
                    } else {
                        // 没有 badgeMap 时，使用智能颜色匹配
                        const variant = getBadgeVariantForValue(value, value, column.field || column.name || '');
                        return `<span class="badge bg-${variant}">${value || ''}</span>`;
                    }
                
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
                                
                                // 处理 attributes 属性
                                let attributesHtml = '';
                                if (action.attributes && typeof action.attributes === 'object') {
                                    for (const [key, value] of Object.entries(action.attributes)) {
                                        const attrValue = replacePlaceholders(value);
                                        attributesHtml += ` ${key}="${attrValue}"`;
                                    }
                                }
                                
                                actionsHtml += `
                                    <a href="${href}"
                                       class="btn btn-sm btn-${variant} btn-action ${btnClass}"
                                       ${title ? `title="${title}"` : ''}${attributesHtml}>
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
                                
                                // 处理 attributes 属性
                                let attributesHtml = '';
                                if (action.attributes && typeof action.attributes === 'object') {
                                    for (const [key, value] of Object.entries(action.attributes)) {
                                        const attrValue = replacePlaceholders(value);
                                        attributesHtml += ` ${key}="${attrValue}"`;
                                    }
                                }
                                
                                actionsHtml += `
                                    <button class="btn btn-sm btn-${variant} btn-action ${btnClass}"
                                            ${title ? `title="${title}"` : ''}
                                            onclick="${onclick}"${attributesHtml}>
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
                
                case 'custom':
                    // 自定义类型：支持 renderFunction 或 partial
                    if (column.renderFunction && typeof window[column.renderFunction] === 'function') {
                        // 使用自定义渲染函数
                        return window[column.renderFunction](value, column, row);
                    }
                    // 如果没有自定义渲染函数，fallthrough 到 default 处理
                    // 注意：不能在这里声明 textValue，因为 default 分支也会声明
                
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
        // 注意：如果 ToolbarRenderer 已初始化，优先使用它的 restoreColumnVisibility
        // 此函数作为后备方案（当没有工具栏渲染器时使用）
        function applyColumnVisibilityPreferences() {
            const saved = localStorage.getItem(storageKey);
            
            if (!saved) {
                // 如果没有保存的设置，应用默认的列显示状态（根据 column.visible）
                applyDefaultColumnVisibility();
                return;
            }

            try {
                const visibleColumns = JSON.parse(saved);
                const table = document.getElementById(tableId);
                
                if (!table) {
                    return;
                }

                // 检查表格是否有数据
                const hasData = table.querySelector('tbody td[data-column]') !== null;
                if (!hasData) {
                    // 如果表格还没有数据，延迟重试
                    setTimeout(applyColumnVisibilityPreferences, 100);
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
                // 出错时应用默认显示状态
                applyDefaultColumnVisibility();
            }
        }

        // 应用默认的列显示状态（根据 column.visible 配置）
        function applyDefaultColumnVisibility() {
            const table = document.getElementById(tableId);
            if (!table) {
                return;
            }

            // 获取所有列索引（从表头获取）
            const allColumns = Array.from(table.querySelectorAll('thead th[data-column]'))
                .map(th => parseInt(th.getAttribute('data-column')));

            // 应用默认设置
            allColumns.forEach(index => {
                const column = columns.find(col => col.index === index);
                const isVisible = column ? (column.visible !== false) : true;

                const th = table.querySelector(`thead th[data-column="${index}"]`);
                const tds = table.querySelectorAll(`tbody td[data-column="${index}"]`);
                
                if (th) {
                    th.style.display = isVisible ? '' : 'none';
                }
                
                tds.forEach(td => {
                    td.style.display = isVisible ? '' : 'none';
                });
            });
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
                // 在显示模态框之前，重置确认按钮状态
                const confirmBtn = document.getElementById(deleteConfirmBtnId);
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    // 恢复按钮的原始 HTML（从 data-original-html 属性获取）
                    const originalHtml = confirmBtn.getAttribute('data-original-html');
                    if (originalHtml) {
                        confirmBtn.innerHTML = originalHtml;
                    } else {
                        // 如果没有保存原始 HTML，使用默认值
                        confirmBtn.innerHTML = '<i class="bi bi-trash me-1"></i> 确认删除';
                        // 保存当前 HTML 作为原始值
                        confirmBtn.setAttribute('data-original-html', confirmBtn.innerHTML);
                    }
                    console.log(`[DataTable ${tableId}] 已重置删除确认按钮状态`);
                }
                
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
                
                // 尝试从多个位置获取 executeDelete 函数
                const executeDelete = window.executeDelete || 
                                     (window.Admin && window.Admin.utils && window.Admin.utils.executeDelete) ||
                                     null;
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
                    // 保存按钮的原始 HTML（如果还没有保存）
                    if (!confirmBtn.getAttribute('data-original-html')) {
                        confirmBtn.setAttribute('data-original-html', confirmBtn.innerHTML);
                    }
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
                    // 绑定模态框关闭事件
                    bindModalCloseHandler();
                });
            } else {
                console.log(`[DataTable ${tableId}] DOM 已准备好，立即绑定单条删除确认按钮`);
                bindDeleteConfirm();
                // 绑定模态框关闭事件
                bindModalCloseHandler();
            }
            
            // 绑定模态框关闭事件处理函数（确保按钮状态被重置）
            function bindModalCloseHandler() {
                const modalElement = document.getElementById(deleteModalId);
                if (modalElement) {
                    // 移除旧的事件监听器（如果存在）
                    const oldHandler = window['_modalCloseHandler_' + tableId];
                    if (oldHandler) {
                        modalElement.removeEventListener('hidden.bs.modal', oldHandler);
                    }
                    
                    // 创建新的事件处理函数
                    window['_modalCloseHandler_' + tableId] = function() {
                        console.log(`[DataTable ${tableId}] 删除模态框已关闭，重置按钮状态`);
                        const confirmBtn = document.getElementById(deleteConfirmBtnId);
                        if (confirmBtn) {
                            confirmBtn.disabled = false;
                            // 恢复按钮的原始 HTML
                            const originalHtml = confirmBtn.getAttribute('data-original-html');
                            if (originalHtml) {
                                confirmBtn.innerHTML = originalHtml;
                            } else {
                                // 如果没有保存原始 HTML，使用默认值
                                confirmBtn.innerHTML = '<i class="bi bi-trash me-1"></i> 确认删除';
                            }
                        }
                        // 清理删除 ID
                        delete window['_deleteItemId_' + tableId];
                        delete window._deleteItemId;
                    };
                    
                    // 添加事件监听器
                    modalElement.addEventListener('hidden.bs.modal', window['_modalCloseHandler_' + tableId]);
                    console.log(`[DataTable ${tableId}] 已绑定模态框关闭事件处理器`);
                }
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
                let searchForm = document.getElementById(searchFormId);
                
                // 如果获取的元素不是 form，尝试在容器内查找 form 元素
                if (searchForm && searchForm.tagName !== 'FORM') {
                    searchForm = searchForm.querySelector('form');
                }
                
                // 确保 searchForm 是一个真正的 HTMLFormElement
                if (searchForm && searchForm instanceof HTMLFormElement) {
                    try {
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
                    } catch (error) {
                        console.warn(`[DataTable ${tableId}] 收集搜索表单数据失败:`, error);
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
            
            // 更新批量复制按钮状态
            const batchCopyBtn = document.getElementById('batchCopyBtn_' + tableId);
            if (batchCopyBtn) {
                batchCopyBtn.disabled = selectedIds.length === 0;
                if (selectedIds.length > 0) {
                    batchCopyBtn.classList.remove('disabled');
                } else {
                    batchCopyBtn.classList.add('disabled');
                }
            }
            
            // 更新批量恢复按钮状态（回收站使用）
            const batchRestoreBtn = document.getElementById('batchRestoreBtn_' + tableId);
            if (batchRestoreBtn) {
                batchRestoreBtn.disabled = selectedIds.length === 0;
                if (selectedIds.length > 0) {
                    batchRestoreBtn.classList.remove('disabled');
                } else {
                    batchRestoreBtn.classList.add('disabled');
                }
            }
            
            // 更新批量永久删除按钮状态（回收站使用）
            const batchForceDeleteBtn = document.getElementById('batchForceDeleteBtn_' + tableId);
            if (batchForceDeleteBtn) {
                batchForceDeleteBtn.disabled = selectedIds.length === 0;
                if (selectedIds.length > 0) {
                    batchForceDeleteBtn.classList.remove('disabled');
                } else {
                    batchForceDeleteBtn.classList.add('disabled');
                }
            }
        };
        
        // 获取选中的ID列表（供外部调用，如批量恢复、批量永久删除等）
        window['getSelectedIds_' + tableId] = function() {
            const checkboxes = document.querySelectorAll('#' + tableId + ' .row-check:checked');
            return Array.from(checkboxes).map(cb => parseInt(cb.value));
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
                    console.log(`[DataTable ${tableId}] 删除路由:`, batchDestroyRoute);
                    if (!batchDestroyRoute) {
                        console.error(`[DataTable ${tableId}] 删除路由未配置：batchDestroyRoute 为空`);
                        if (typeof showToast === 'function') {
                            showToast('danger', '删除路由未配置，请联系管理员');
                        } else {
                            alert('删除路由未配置，请联系管理员');
                        }
                        return;
                    }
                    
                    // 关闭模态框
                    console.log(`[DataTable ${tableId}] 关闭删除确认模态框`);
                    const modalElement = document.getElementById(batchDeleteModalId);
                    if (modalElement) {
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        }
                    }
                    
                    // 执行批量删除
                    console.log(`[DataTable ${tableId}] 执行删除操作，记录数:`, selectedIds.length);
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
                        showToast('danger', '删除路由未配置，请联系管理员');
                    } else {
                        alert('删除路由未配置，请联系管理员');
                    }
                    console.error('删除路由未配置：batchDestroyRoute 为空');
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
                            showToast('success', result.msg || '删除成功');
                        } else {
                            alert(result.msg || '删除成功');
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
                            showToast('danger', result.msg || '删除失败');
                        } else {
                            alert(result.msg || '删除失败');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (typeof showToast === 'function') {
                        showToast('danger', '删除失败');
                    } else {
                        alert('删除失败');
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
                        showToast('danger', '删除路由未配置，请联系管理员');
                    } else {
                        alert('删除路由未配置，请联系管理员');
                    }
                    console.error('删除路由未配置：batchDestroyRoute 为空');
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

        // 批量复制：始终定义全局函数，若未配置 createRoute 会提示并返回（避免 ReferenceError）
        window['batchCopy_' + tableId] = function() {
            // 如果按钮已被禁用（例如页面加载顺序导致），直接返回，避免触发提示或打开窗口
            const preBtn = document.getElementById('batchCopyBtn_' + tableId);
            if (preBtn && preBtn.disabled) {
                return;
            }
            // 重新收集选中的ID
            const checkboxes = document.querySelectorAll('#' + tableId + ' .row-check:checked');
            const currentSelectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

            if (currentSelectedIds.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('warning', '请选择要复制的记录');
                } else {
                    alert('请选择要复制的记录');
                }
                return;
            }

            if (!createRoute) {
                if (typeof showToast === 'function') {
                    showToast('danger', '创建路由未配置，无法执行批量复制');
                } else {
                    alert('创建路由未配置，无法执行批量复制');
                }
                return;
            }

            const batchCopyBtn = document.getElementById('batchCopyBtn_' + tableId);
            const originalHtml = batchCopyBtn ? batchCopyBtn.innerHTML : '';
            if (batchCopyBtn) {
                batchCopyBtn.disabled = true;
                batchCopyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 打开中...';
            }

            // 如果只选中一条，保持使用 iframe shell modal；多条则使用 openTab / window.open 打开多个标签以避免复用单一 iframe
            currentSelectedIds.forEach(id => {
                try {
                    const url = createRoute + (createRoute.includes('?') ? '&' : '?') + 'duplicate_from=' + encodeURIComponent(id);
                    const title = `复制 #${id}`;

                    if (currentSelectedIds.length === 1) {
                        if (window.Admin && window.Admin.iframeShell && typeof window.Admin.iframeShell.open === 'function') {
                            const channel = iframeShellChannel ? (iframeShellChannel + '-copy-' + id) : undefined;
                            window.Admin.iframeShell.open({
                                src: url,
                                title: title,
                                channel: channel,
                                hideActions: true,
                                autoClose: true
                            });
                        } else {
                            window.open(url, '_blank');
                        }
                    } else {
                        // 多选时：打开新标签（优先使用 TabManager via iframeShell.openTab）
                        if (window.Admin && window.Admin.iframeShell && typeof window.Admin.iframeShell.openTab === 'function') {
                            window.Admin.iframeShell.openTab(url, title, { fallbackToWindow: true });
                        } else {
                            window.open(url, '_blank');
                        }
                    }
                } catch (e) {
                    console.error('[DataTable] 复制时打开创建页失败', e);
                }
            });

            if (batchCopyBtn) {
                batchCopyBtn.disabled = false;
                batchCopyBtn.innerHTML = originalHtml;
            }
        };
        
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
                    // 初始化批量复制按钮状态
                    if (createRoute) {
                        const batchCopyBtn = document.getElementById('batchCopyBtn_' + tableId);
                        if (batchCopyBtn) {
                            batchCopyBtn.disabled = true;
                            batchCopyBtn.classList.add('disabled');
                            console.log(`[DataTable ${tableId}] 初始化批量复制按钮为禁用状态`);
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
