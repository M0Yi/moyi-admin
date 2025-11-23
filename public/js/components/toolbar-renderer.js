/**
 * 表格工具栏渲染器
 * 用于动态渲染通用CRUD的表格工具栏
 */
(function (window, document) {
    'use strict';

    class ToolbarRenderer {
        constructor(options = {}) {
            this.config = options.config || {};
            this.containerId = options.containerId || 'toolbarContainer';
            this.tableId = options.tableId || 'dataTable';
            this.storageKey = options.storageKey || '';
            this.columns = options.columns || [];
            
            // 保存默认可见的列（用于重置功能）
            this.defaultVisibleColumns = this.getDefaultVisibleColumns();
            
            // 工具栏配置（确保是数组）
            this.leftButtons = Array.isArray(this.config.leftButtons) ? this.config.leftButtons : [];
            this.rightButtons = Array.isArray(this.config.rightButtons) ? this.config.rightButtons : [];
            this.leftSlot = this.config.leftSlot || null;
            this.rightSlot = this.config.rightSlot || null;
            this.showColumnToggle = this.config.showColumnToggle !== false;
            
            // 搜索按钮显示条件：
            // 1. 优先使用 toolbarConfig.showSearch（已包含 features['search'] 功能开关检查）
            // 2. 如果没有 showSearch 配置，则从 searchConfig 判断（向后兼容）
            // 3. 必须同时满足：showSearch 为 true 且 searchConfig 存在且有 search_fields
            const searchConfig = this.config.searchConfig;
            const hasSearchConfig = searchConfig && 
                                   searchConfig.search_fields && 
                                   Array.isArray(searchConfig.search_fields) && 
                                   searchConfig.search_fields.length > 0;
            
            // 优先使用配置中的 showSearch（已包含功能开关检查）
            const configShowSearch = this.config.showSearch !== undefined 
                ? this.config.showSearch !== false 
                : true; // 默认 true（向后兼容）
            
            // 最终判断：必须同时满足配置开关和搜索字段存在
            this.showSearch = configShowSearch && hasSearchConfig;
            
            this.container = null;
            
            this.init();
        }

        init() {
            // 等待DOM加载完成
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.render());
            } else {
                this.render();
            }
        }

        render() {
            // 查找工具栏容器
            this.container = document.getElementById(this.containerId);
            if (!this.container) {
                console.warn('[ToolbarRenderer] 未找到工具栏容器:', this.containerId);
                return;
            }

            // 调试信息：检查 rightButtons 数组
            if (Array.isArray(this.rightButtons)) {
                console.log('[ToolbarRenderer] rightButtons 数组:', this.rightButtons, '长度:', this.rightButtons.length);
            }

            // 检查是否需要显示工具栏
            // 注意：即使 rightButtons 为空，只要其他条件满足，工具栏也应该显示
            const hasContent = this.leftSlot || this.rightSlot || 
                             this.leftButtons.length > 0 || 
                             this.rightButtons.length > 0 || 
                             this.showColumnToggle || 
                             this.showSearch;

            if (!hasContent) {
                this.container.style.display = 'none';
                return;
            }

            // 构建工具栏HTML
            const toolbarHtml = this.buildToolbarHtml();
            this.container.innerHTML = toolbarHtml;
            
            // 绑定事件
            this.attachEvents();
        }

        buildToolbarHtml() {
            const leftHtml = this.buildLeftToolbar();
            const rightHtml = this.buildRightToolbar();
            
            return `
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <!-- 左侧工具栏 -->
                        <div class="d-flex gap-2">
                            ${leftHtml}
                        </div>
                        
                        <!-- 右侧工具栏 -->
                        <div class="d-flex gap-2 align-items-center">
                            ${rightHtml}
                        </div>
                    </div>
                </div>
            `;
        }

        buildLeftToolbar() {
            // 如果提供了 leftSlot，直接使用
            if (this.leftSlot !== null && this.leftSlot !== undefined && this.leftSlot !== '') {
                return this.leftSlot;
            }

            // 使用 leftButtons 配置渲染按钮（确保是数组）
            if (!Array.isArray(this.leftButtons) || this.leftButtons.length === 0) {
                return '';
            }

            return this.leftButtons.map(button => {
                return this.renderButton(button, 'left');
            }).join('');
        }

        buildRightToolbar() {
            let html = '';

            // 如果提供了 rightSlot，直接使用
            if (this.rightSlot !== null && this.rightSlot !== undefined && this.rightSlot !== '') {
                html = this.rightSlot;
            } else {
                // 使用 rightButtons 配置渲染按钮（确保是数组）
                if (Array.isArray(this.rightButtons)) {
                    // 检查是否已有刷新按钮（通过 icon 判断）
                    const hasRefreshButton = this.rightButtons.some(btn => 
                        btn && btn.icon === 'bi-arrow-repeat'
                    );
                    
                    // 如果数组为空或没有刷新按钮，确保添加刷新按钮
                    let buttonsToRender = [...this.rightButtons];
                    if (buttonsToRender.length === 0 || !hasRefreshButton) {
                        // 添加默认的刷新按钮
                        buttonsToRender.push({
                            'icon': 'bi-arrow-repeat',
                            'title': '刷新',
                            'onclick': 'loadData_' + this.tableId + '()'
                        });
                    }
                    html = buttonsToRender.map(button => {
                        return this.renderButton(button, 'right');
                    }).join('');
                }
            }

            // 列显示控制按钮
            if (this.showColumnToggle) {
                html += this.renderColumnToggle();
            }

            // 搜索按钮
            if (this.showSearch) {
                html += this.renderSearchButton();
            }

            return html;
        }

        renderButton(button, position = 'left') {
            const buttonType = button.type || 'button';
            const variant = button.variant || (buttonType === 'link' ? 'primary' : (position === 'left' ? 'light' : 'outline-secondary'));
            const buttonClass = button.class || '';
            const buttonId = button.id || '';
            const buttonTitle = button.title || '';
            const attributes = button.attributes || {};
            
            // 构建属性字符串
            const attrs = [];
            if (buttonId) attrs.push(`id="${this.escapeHtml(buttonId)}"`);
            if (buttonTitle) attrs.push(`title="${this.escapeHtml(buttonTitle)}"`);
            
            // 添加自定义属性
            Object.keys(attributes).forEach(key => {
                attrs.push(`${key}="${this.escapeHtml(String(attributes[key]))}"`);
            });

            const baseClass = position === 'left' 
                ? `btn btn-${variant} px-4 py-2 shadow-sm ${buttonClass}`
                : `btn btn-${variant} px-3 py-2 ${buttonClass}`;
            
            const style = `border-radius: 10px;${variant === 'light' ? ' border: 1px solid #e9ecef;' : ''}`;
            
            const iconHtml = button.icon ? `<i class="bi ${this.escapeHtml(button.icon)} ${position === 'left' ? 'me-2' : ''}"></i>` : '';
            const textHtml = button.text ? `<span class="${variant === 'primary' && position === 'left' ? 'fw-medium' : ''}">${this.escapeHtml(button.text)}</span>` : '';
            
            if (buttonType === 'link') {
                const href = button.href || '#';
                return `
                    <a
                        href="${this.escapeHtml(href)}"
                        class="${baseClass}"
                        style="${style}"
                        ${attrs.join(' ')}
                    >
                        ${iconHtml}
                        ${textHtml}
                    </a>
                `;
            } else {
                const onclick = button.onclick ? `onclick="${this.escapeHtml(button.onclick)}"` : '';
                return `
                    <button
                        type="button"
                        class="${baseClass}"
                        style="${style}"
                        ${onclick}
                        ${attrs.join(' ')}
                    >
                        ${iconHtml}
                        ${textHtml}
                    </button>
                `;
            }
        }

        renderColumnToggle() {
            // 获取可切换的列
            const toggleableColumns = this.columns.filter(col => {
                return (col.toggleable !== false);
            });

            if (toggleableColumns.length === 0) {
                return '';
            }

            // 生成列选项HTML
            const columnOptions = toggleableColumns.map(col => {
                // 优先使用 index，即使它是 0（因为 0 是 falsy，需要用 !== undefined 判断）
                const colId = (col.index !== undefined && col.index !== null) ? col.index : (col.field || '');
                const colLabel = col.label || colId;
                // 根据列的 visible 属性设置初始状态（默认为可见）
                const isChecked = col.visible !== false;
                return `
                    <li>
                        <label class="dropdown-item-text">
                            <input type="checkbox" 
                                   class="form-check-input me-2 column-toggle-checkbox" 
                                   data-column="${this.escapeHtml(colId)}"
                                   ${isChecked ? 'checked' : ''}>
                            ${this.escapeHtml(colLabel)}
                        </label>
                    </li>
                `;
            }).join('');

            return `
                <div class="dropdown">
                    <button
                        type="button"
                        class="btn btn-outline-secondary px-3 py-2 dropdown-toggle"
                        style="border-radius: 10px;"
                        id="columnToggleBtn_${this.tableId}"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        title="列显示控制"
                    >
                        <i class="bi bi-list-columns"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="columnToggleBtn_${this.tableId}">
                        ${columnOptions}
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" 
                                    class="dropdown-item" 
                                    id="resetColumnsBtn_${this.tableId}"
                                    title="重置为默认显示">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>
                                重置为默认
                            </button>
                        </li>
                    </ul>
                </div>
            `;
        }

        renderSearchButton() {
            const toggleFunction = `toggleSearchPanel_${this.tableId}`;
            return `
                <button
                    type="button"
                    class="btn btn-outline-secondary px-3 py-2"
                    style="border-radius: 10px;"
                    id="searchToggleBtn_${this.tableId}"
                    title="搜索"
                    data-toggle-function="${toggleFunction}"
                >
                    <i class="bi bi-search" id="searchToggleIcon_${this.tableId}"></i>
                </button>
            `;
        }

        attachEvents() {
            // 绑定搜索按钮事件
            if (this.showSearch) {
                const searchBtn = document.getElementById(`searchToggleBtn_${this.tableId}`);
                if (searchBtn) {
                    const toggleFunction = searchBtn.getAttribute('data-toggle-function');
                    searchBtn.addEventListener('click', () => {
                        const fn = window[toggleFunction];
                        if (typeof fn === 'function') {
                            fn();
                        } else {
                            console.error(`[ToolbarRenderer] 函数 ${toggleFunction} 未找到。请确保数据表格组件脚本已加载。`);
                        }
                    });
                }
            }

                // 绑定列切换事件
                if (this.showColumnToggle) {
                    const checkboxes = this.container.querySelectorAll('.column-toggle-checkbox');
                    checkboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', (e) => {
                            const columnId = e.target.getAttribute('data-column');
                            const isVisible = e.target.checked;
                            // 用户操作时保存到 localStorage
                            this.toggleColumn(columnId, isVisible, true);
                        });
                    });

                    // 绑定重置按钮事件
                    const resetBtn = document.getElementById(`resetColumnsBtn_${this.tableId}`);
                    if (resetBtn) {
                        resetBtn.addEventListener('click', () => {
                            this.resetToDefault();
                        });
                    }

                    // 从 localStorage 恢复列显示状态
                    // 延迟执行，确保表格数据已加载
                    // 如果表格还没有数据，restoreColumnVisibility 会自己重试
                    setTimeout(() => {
                        this.restoreColumnVisibility();
                    }, 100);
                }
        }

        toggleColumn(columnId, isVisible, saveToStorage = true) {
            const table = document.getElementById(this.tableId);
            if (!table) {
                console.warn('[ToolbarRenderer] 未找到表格元素:', this.tableId);
                return;
            }

            const display = isVisible ? '' : 'none';
            
            // 尝试多种格式的列 ID（支持数字和字符串）
            // 1. 直接使用 columnId
            // 2. 如果 columnId 是字符串，尝试转换为数字
            // 3. 如果 columnId 是数字，尝试转换为字符串
            const columnIdVariants = [columnId];
            
            // 如果是字符串，尝试转换为数字
            if (typeof columnId === 'string' && !isNaN(columnId) && columnId !== '') {
                columnIdVariants.push(parseInt(columnId, 10));
            }
            // 如果是数字，尝试转换为字符串
            if (typeof columnId === 'number') {
                columnIdVariants.push(String(columnId));
            }
            
            // 切换表头（尝试所有可能的格式）
            let headerFound = false;
            for (const variant of columnIdVariants) {
                const header = table.querySelector(`thead th[data-column="${variant}"]`);
                if (header) {
                    header.style.display = display;
                    headerFound = true;
                    break;
                }
            }
            
            // 如果表头没找到，尝试通过列配置查找对应的 field
            if (!headerFound) {
                const column = this.columns.find(col => {
                    const colId = (col.index !== undefined && col.index !== null) ? col.index : (col.field || '');
                    return colId === columnId || String(colId) === String(columnId);
                });
                
                if (column) {
                    const actualColId = (column.index !== undefined && column.index !== null) ? column.index : (column.field || '');
                    const header = table.querySelector(`thead th[data-column="${actualColId}"]`);
                    if (header) {
                        header.style.display = display;
                        headerFound = true;
                    }
                }
            }
            
            // 切换表体单元格（尝试所有可能的格式）
            let cellsFound = false;
            for (const variant of columnIdVariants) {
                const cells = table.querySelectorAll(`tbody td[data-column="${variant}"]`);
                if (cells.length > 0) {
                    cells.forEach(td => {
                        td.style.display = display;
                    });
                    cellsFound = true;
                    break;
                }
            }
            
            // 如果单元格没找到，尝试通过列配置查找
            if (!cellsFound) {
                const column = this.columns.find(col => {
                    const colId = (col.index !== undefined && col.index !== null) ? col.index : (col.field || '');
                    return colId === columnId || String(colId) === String(columnId);
                });
                
                if (column) {
                    const actualColId = (column.index !== undefined && column.index !== null) ? column.index : (column.field || '');
                    const cells = table.querySelectorAll(`tbody td[data-column="${actualColId}"]`);
                    cells.forEach(td => {
                        td.style.display = display;
                    });
                }
            }

            // 保存到 localStorage（使用与 table-column-toggle 组件相同的格式）
            // saveToStorage 参数用于区分是用户操作还是恢复操作
            if (this.storageKey && saveToStorage) {
                // 获取所有选中的列
                const checkboxes = this.container.querySelectorAll('.column-toggle-checkbox:checked');
                const visibleColumns = Array.from(checkboxes).map(cb => {
                    const colId = cb.getAttribute('data-column');
                    return typeof colId === 'string' && colId.includes('.') ? colId : parseInt(colId, 10);
                });
                
                localStorage.setItem(this.storageKey, JSON.stringify(visibleColumns));
            }
        }

        restoreColumnVisibility() {
            if (!this.storageKey) return;

            const table = document.getElementById(this.tableId);
            if (!table) {
                // 如果表格不存在，延迟重试
                setTimeout(() => this.restoreColumnVisibility(), 100);
                return;
            }

            // 检查表格是否有数据（至少有一个 tbody td）
            const hasData = table.querySelector('tbody td[data-column]') !== null;
            if (!hasData) {
                // 如果表格还没有数据，延迟重试
                setTimeout(() => this.restoreColumnVisibility(), 100);
                return;
            }

            let visibleColumns = [];
            try {
                const stored = localStorage.getItem(this.storageKey);
                if (stored) {
                    visibleColumns = JSON.parse(stored);
                }
            } catch (e) {
                console.warn('[ToolbarRenderer] 读取列显示状态失败:', e);
                return;
            }

            // 获取列配置，用于判断哪些列不可切换（toggleable: false）
            const nonToggleableColumns = this.columns
                .filter(col => (col.toggleable ?? true) === false)
                .map(col => {
                    const colId = col.index ?? col.field ?? '';
                    return isNaN(colId) ? colId : parseInt(colId, 10);
                });

            // 恢复复选框状态
            const checkboxes = this.container.querySelectorAll('.column-toggle-checkbox');
            checkboxes.forEach(checkbox => {
                const columnId = checkbox.getAttribute('data-column');
                // 将 columnId 转换为数字进行比较（如果可能）
                const colIdNum = isNaN(columnId) ? columnId : parseInt(columnId, 10);
                
                // 检查是否是不可切换的列
                const isNonToggleable = nonToggleableColumns.includes(colIdNum) || 
                                       nonToggleableColumns.includes(columnId);
                
                // 如果不可切换，则始终可见；否则从 localStorage 读取
                const isVisible = isNonToggleable || 
                                 visibleColumns.includes(colIdNum) || 
                                 visibleColumns.includes(columnId);
                
                checkbox.checked = isVisible;
                // 触发列切换（但不保存到 localStorage，因为这是恢复操作）
                this.toggleColumn(columnId, isVisible, false);
            });
        }

        getDefaultVisibleColumns() {
            // 获取默认可见的列ID列表
            const visibleColumns = [];
            this.columns.forEach(col => {
                if (col.toggleable !== false) {
                    const colId = col.index ?? col.field ?? '';
                    // 如果 visible 为 true 或未定义（默认为可见），则加入默认可见列表
                    if (col.visible !== false && colId !== '') {
                        // 统一转换为数字格式（如果可能），否则保持原值
                        const colIdNum = isNaN(colId) ? colId : parseInt(colId, 10);
                        visibleColumns.push(colIdNum);
                    }
                }
            });
            return visibleColumns;
        }

        resetToDefault() {
            // 重置所有列到默认显示状态
            const checkboxes = this.container.querySelectorAll('.column-toggle-checkbox');
            checkboxes.forEach(checkbox => {
                const columnId = checkbox.getAttribute('data-column');
                // 将 columnId 转换为数字进行比较（如果可能）
                const colIdNum = isNaN(columnId) ? columnId : parseInt(columnId, 10);
                // 检查是否在默认可见列表中（支持数字和字符串匹配）
                const isDefaultVisible = this.defaultVisibleColumns.includes(colIdNum) || 
                                       this.defaultVisibleColumns.includes(columnId);
                
                checkbox.checked = isDefaultVisible;
                this.toggleColumn(columnId, isDefaultVisible);
            });

            // 保存到 localStorage（与现有逻辑保持一致）
            if (this.storageKey) {
                // 只保存数字格式的列ID（与 toggleColumn 方法中的逻辑一致）
                const defaultVisibleNumeric = this.defaultVisibleColumns.map(id => {
                    return typeof id === 'number' ? id : (isNaN(id) ? id : parseInt(id, 10));
                });
                localStorage.setItem(this.storageKey, JSON.stringify(defaultVisibleNumeric));
            }

            // 显示提示消息（如果存在 toast 函数）
            if (typeof window.showToast === 'function') {
                window.showToast('success', '已重置为默认显示');
            } else {
                console.log('[ToolbarRenderer] 已重置为默认显示');
            }
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // 导出到全局
    window.ToolbarRenderer = ToolbarRenderer;

})(window, document);

