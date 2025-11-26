/**
 * 权限树组件
 * 支持可折叠、多级别、自适应布局的权限分配
 */
(function () {
    'use strict';

    class PermissionTree {
        constructor(container, options = {}) {
            this.container = container;
            this.options = {
                tree: options.tree || [],
                selectedIds: options.selectedIds || [],
                onSelectionChange: options.onSelectionChange || null,
                showSearch: options.showSearch !== false,
                showToolbar: options.showToolbar !== false,
                expandLevel: options.expandLevel || 1, // 默认展开层级
                ...options
            };

        this.selectedIds = new Set(this.options.selectedIds.map(String));
        this.totalNodes = this.countTotalNodes(this.options.tree);
        this.nodeMap = {};
        this.buildNodeMap(this.options.tree);
            this.render();
            this.bindEvents();
        }

        /**
         * 渲染组件
         */
        render() {
            const { showSearch, showToolbar } = this.options;
            this.container.classList.add('permission-tree-wrapper');

            const toolbarHtml = showToolbar ? this.renderToolbar() : '';
            const searchHtml = showSearch ? this.renderSearchBox() : '';
            const treeHtml = `
                <div class="permission-tree-container">
                    ${this.renderTree(this.options.tree, 0)}
                </div>
            `;

            const mainColumn = `
                <div class="permission-tree-main">
                    ${toolbarHtml}
                    ${searchHtml}
                    ${treeHtml}
                </div>
            `;

            const layoutHtml = `
                <div class="permission-tree-layout">
                    ${mainColumn}
                    ${this.renderSidePanel()}
                </div>
            `;

        this.container.innerHTML = layoutHtml;
        this.refreshCheckboxStates();
        this.updateSummary();
        }

        /**
         * 渲染工具栏
         */
        renderToolbar() {
            return `
                <div class="permission-tree-toolbar d-flex flex-wrap align-items-center gap-2 mb-3">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" data-action="expand-all">
                            <i class="bi bi-chevron-down"></i> 展开全部
                        </button>
                        <button type="button" class="btn btn-outline-primary" data-action="collapse-all">
                            <i class="bi bi-chevron-up"></i> 收起全部
                        </button>
                    </div>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-success" data-action="select-all">
                            <i class="bi bi-check2-all"></i> 全选
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-action="clear-all">
                            <i class="bi bi-x-lg"></i> 全不选
                        </button>
                        <button type="button" class="btn btn-outline-info" data-action="select-parents">
                            <i class="bi bi-arrow-up"></i> 仅选父级
                        </button>
                        <button type="button" class="btn btn-outline-warning" data-action="select-leaves">
                            <i class="bi bi-arrow-down"></i> 仅选子级
                        </button>
                    </div>
                </div>
            `;
        }

        /**
         * 渲染搜索框
         */
        renderSearchBox() {
            return `
                <div class="permission-tree-search mb-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input 
                            type="search" 
                            class="form-control" 
                            placeholder="搜索权限名称、标识或路径..." 
                            data-permission-search
                        >
                        <button class="btn btn-outline-secondary" type="button" data-action="clear-search">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            `;
        }

        /**
         * 渲染树形结构
         */
        renderTree(nodes, level = 0) {
            if (!nodes || nodes.length === 0) {
                return '<div class="text-muted small p-2">暂无权限数据</div>';
            }

            let html = '<ul class="permission-tree-list list-unstyled mb-0">';
            
            nodes.forEach((node, index) => {
                const hasChildren = node.children && node.children.length > 0;
                const nodeId = `permission-node-${node.id}`;
                const checkboxId = `permission-checkbox-${node.id}`;
                const isSelected = this.selectedIds.has(String(node.id));
                const isExpanded = level < this.options.expandLevel;

                // 节点类型标识
                const typeBadge = node.type === 'menu' 
                    ? '<span class="badge bg-primary ms-2">菜单</span>' 
                    : '<span class="badge bg-secondary ms-2">按钮</span>';

                // 图标 - 有子节点时使用文件夹图标，更明显
                const icon = node.icon 
                    ? `<i class="${node.icon} me-2"></i>` 
                    : (hasChildren ? '<i class="bi bi-folder-fill me-2 text-primary"></i>' : '<i class="bi bi-file-earmark me-2 text-muted"></i>');

                html += `
                    <li class="permission-tree-node" data-node-id="${node.id}" data-level="${level}">
                        <div class="permission-tree-item d-flex align-items-center py-1 px-2 rounded">
                            <div class="form-check flex-grow-1">
                                <input 
                                    class="form-check-input permission-checkbox" 
                                    type="checkbox" 
                                    id="${checkboxId}"
                                    value="${node.id}"
                                    ${isSelected ? 'checked' : ''}
                                    data-node-id="${node.id}"
                                >
                                <label class="form-check-label d-flex align-items-center flex-grow-1" for="${checkboxId}">
                                    ${icon}
                                    <span class="permission-name">${this.escapeHtml(node.name)}</span>
                                    ${typeBadge}
                                    ${node.description ? `<small class="text-muted ms-2">${this.escapeHtml(node.description)}</small>` : ''}
                                </label>
                            </div>

                            ${hasChildren ? `
                                <button 
                                    type="button" 
                                    class="permission-tree-toggle" 
                                    data-toggle-id="${nodeId}"
                                    aria-expanded="${isExpanded ? 'true' : 'false'}"
                                    title="${isExpanded ? '收起' : '展开'}"
                                >
                                    <i class="bi ${isExpanded ? 'bi-chevron-down' : 'bi-chevron-right'}"></i>
                                </button>
                            ` : '<span class="permission-tree-spacer"></span>'}
                        </div>
                        ${hasChildren ? `
                            <div class="permission-tree-children ${isExpanded ? '' : 'd-none'}" id="${nodeId}">
                                ${this.renderTree(node.children, level + 1)}
                            </div>
                        ` : ''}
                    </li>
                `;
            });

            html += '</ul>';
            return html;
        }

        renderSidePanel() {
            return `
                <div class="permission-tree-side">
                    <div class="permission-side-header">
                        <div>
                            <h6 class="mb-1">已选权限</h6>
                            <small class="text-muted">管理已勾选的菜单与按钮</small>
                        </div>
                        <span class="badge bg-light text-secondary">实时更新</span>
                    </div>

                    <div class="permission-selected-summary" data-permission-summary>
                        <div class="permission-summary-count">
                            <div>
                                <div class="summary-label">已选择</div>
                                <div class="summary-value" data-selected-count>0</div>
                            </div>
                            <div class="summary-divider"></div>
                            <div>
                                <div class="summary-label">总权限</div>
                                <div class="summary-total" data-total-count>${this.totalNodes}</div>
                            </div>
                        </div>
                        <div class="permission-summary-types">
                            <div class="type-item">
                                <span class="type-label">菜单</span>
                                <span class="type-value text-primary" data-menu-count>0</span>
                            </div>
                            <div class="type-item">
                                <span class="type-label">按钮</span>
                                <span class="type-value text-secondary" data-button-count>0</span>
                            </div>
                        </div>
                    </div>

                    <div class="permission-selected-list" data-selected-list>
                        <div class="permission-selected-empty">
                            <i class="bi bi-info-circle me-2"></i>尚未选择任何权限
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * 统计总节点数
         */
        countTotalNodes(nodes) {
            let count = 0;
            if (!nodes) return count;
            
            nodes.forEach(node => {
                count++;
                if (node.children) {
                    count += this.countTotalNodes(node.children);
                }
            });
            return count;
        }

        buildNodeMap(nodes = [], parent = null) {
            if (!nodes) {
                return;
            }

            nodes.forEach(node => {
                this.nodeMap[String(node.id)] = { ...node, parent };
                if (node.children && node.children.length > 0) {
                    this.buildNodeMap(node.children, node);
                }
            });
        }

        /**
         * 按类型统计已选节点
         */
        countByType(type) {
            let count = 0;
            const traverse = (nodes) => {
                if (!nodes) return;
                nodes.forEach(node => {
                    if (node.type === type && this.selectedIds.has(String(node.id))) {
                        count++;
                    }
                    if (node.children) {
                        traverse(node.children);
                    }
                });
            };
            traverse(this.options.tree);
            return count;
        }

        getSelectedNodes() {
            return Array.from(this.selectedIds)
                .map((id) => this.nodeMap[id])
                .filter(Boolean);
        }

        selectNodeWithDescendants(nodeId) {
            const node = this.nodeMap[nodeId];
            if (!node) {
                return;
            }

            const stack = [node];
            while (stack.length > 0) {
                const current = stack.pop();
                this.selectedIds.add(String(current.id));
                if (current.children && current.children.length > 0) {
                    current.children.forEach((child) => stack.push(child));
                }
            }
        }

        deselectNodeWithDescendants(nodeId) {
            const node = this.nodeMap[nodeId];
            if (!node) {
                return;
            }

            const stack = [node];
            while (stack.length > 0) {
                const current = stack.pop();
                this.selectedIds.delete(String(current.id));
                if (current.children && current.children.length > 0) {
                    current.children.forEach((child) => stack.push(child));
                }
            }
        }

        refreshCheckboxStates() {
            const traverse = (node) => {
                const checkbox = this.container.querySelector(`#permission-checkbox-${node.id}`);
                const hasChildren = node.children && node.children.length > 0;
                const selfId = String(node.id);
                const selfChecked = this.selectedIds.has(selfId);

                if (!hasChildren) {
                    if (checkbox) {
                        checkbox.checked = selfChecked;
                        checkbox.indeterminate = false;
                    }
                    return {
                        hasAny: selfChecked,
                        allSelected: selfChecked,
                    };
                }

                const childInfo = node.children.map((child) => traverse(child));
                const hasChildSelected = childInfo.some((info) => info.hasAny);
                const allChildrenFullySelected = childInfo.every((info) => info.allSelected);

                if (checkbox) {
                    checkbox.checked = selfChecked;
                    checkbox.indeterminate = hasChildSelected && (!selfChecked || !allChildrenFullySelected);
                }

                return {
                    hasAny: selfChecked || hasChildSelected,
                    allSelected: selfChecked && allChildrenFullySelected,
                };
            };

            (this.options.tree || []).forEach((node) => traverse(node));
        }

        /**
         * 绑定事件
         */
        bindEvents() {
            // 工具栏按钮
            this.container.addEventListener('click', (e) => {
                const action = e.target.closest('[data-action]');
                if (action) {
                    e.preventDefault();
                    this.handleAction(action.dataset.action);
                    return;
                }

                const removeBtn = e.target.closest('[data-remove-id]');
                if (removeBtn) {
                    e.preventDefault();
                    const removeId = removeBtn.dataset.removeId;
                    const checkbox = this.container.querySelector(`#permission-checkbox-${removeId}`);
                    if (checkbox) {
                        checkbox.checked = false;
                        this.handleCheckboxChange(checkbox);
                    }
                    return;
                }

                // 复选框变化（优先处理，避免触发展开/收起）
                const checkbox = e.target.closest('.permission-checkbox');
                if (checkbox) {
                    this.handleCheckboxChange(checkbox);
                    return;
                }

                // 展开/收起切换按钮
                const toggle = e.target.closest('.permission-tree-toggle');
                if (toggle) {
                    e.preventDefault();
                    this.toggleNode(toggle.dataset.toggleId);
                    return;
                }

                // 点击整个节点项也可以展开/收起（排除复选框和标签）
                const treeItem = e.target.closest('.permission-tree-item');
                if (treeItem) {
                    // 如果点击的是复选框、标签、徽章或描述，不触发展开/收起
                    // 复选框和标签点击会触发复选框的选中/取消
                    // 徽章和描述是信息展示，点击它们也不应该触发展开/收起
                    if (e.target.closest('.permission-checkbox') || 
                        e.target.closest('.form-check-label') ||
                        e.target.closest('.badge') ||
                        e.target.closest('small.text-muted')) {
                        return;
                    }
                    
                    // 查找对应的子节点容器
                    const node = treeItem.closest('.permission-tree-node');
                    if (node) {
                        const children = node.querySelector('.permission-tree-children');
                        if (children) {
                            e.preventDefault();
                            e.stopPropagation();
                            this.toggleNode(children.id);
                        }
                    }
                }
            });

            // 搜索
            const searchInput = this.container.querySelector('[data-permission-search]');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.handleSearch(e.target.value);
                    }, 300);
                });
            }
        }

        /**
         * 处理工具栏操作
         */
        handleAction(action) {
            switch (action) {
                case 'expand-all':
                    this.expandAll();
                    break;
                case 'collapse-all':
                    this.collapseAll();
                    break;
                case 'select-all':
                    this.selectAll();
                    break;
                case 'clear-all':
                    this.clearAll();
                    break;
                case 'select-parents':
                    this.selectParents();
                    break;
                case 'select-leaves':
                    this.selectLeaves();
                    break;
                case 'clear-search':
                    const searchInput = this.container.querySelector('[data-permission-search]');
                    if (searchInput) {
                        searchInput.value = '';
                        this.handleSearch('');
                    }
                    break;
            }
        }

        /**
         * 展开全部
         */
        expandAll() {
            const children = this.container.querySelectorAll('.permission-tree-children');
            children.forEach(el => {
                el.classList.remove('d-none');
                const toggle = this.container.querySelector(`[data-toggle-id="${el.id}"]`);
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                    toggle.setAttribute('title', '收起');
                    const icon = toggle.querySelector('i');
                    if (icon) {
                        icon.className = 'bi bi-chevron-down';
                    }
                }
            });
        }

        /**
         * 收起全部
         */
        collapseAll() {
            const children = this.container.querySelectorAll('.permission-tree-children');
            children.forEach(el => {
                el.classList.add('d-none');
                const toggle = this.container.querySelector(`[data-toggle-id="${el.id}"]`);
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('title', '展开');
                    const icon = toggle.querySelector('i');
                    if (icon) {
                        icon.className = 'bi bi-chevron-right';
                    }
                }
            });
        }

        /**
         * 全选
         */
        selectAll() {
            this.selectedIds = new Set(Object.keys(this.nodeMap));
            this.refreshCheckboxStates();
            this.updateSummary();
            this.notifyChange();
        }

        /**
         * 全不选
         */
        clearAll() {
            this.selectedIds.clear();
            this.refreshCheckboxStates();
            this.updateSummary();
            this.notifyChange();
        }

        /**
         * 仅选父级（有子节点的节点）
         */
        selectParents() {
            this.selectedIds.clear();
            const traverse = (nodes) => {
                if (!nodes) return;
                nodes.forEach(node => {
                    if (node.children && node.children.length > 0) {
                        this.selectedIds.add(String(node.id));
                        traverse(node.children);
                    }
                });
            };
            traverse(this.options.tree);
            this.refreshCheckboxStates();
            this.updateSummary();
            this.notifyChange();
        }

        /**
         * 仅选子级（叶子节点）
         */
        selectLeaves() {
            this.selectedIds.clear();
            const traverse = (nodes) => {
                if (!nodes) return;
                nodes.forEach(node => {
                    if (!node.children || node.children.length === 0) {
                        this.selectedIds.add(String(node.id));
                    } else {
                        traverse(node.children);
                    }
                });
            };
            traverse(this.options.tree);
            this.refreshCheckboxStates();
            this.updateSummary();
            this.notifyChange();
        }

        /**
         * 切换节点展开/收起
         */
        toggleNode(nodeId) {
            const children = this.container.querySelector(`#${nodeId}`);
            if (!children) return;

            const isExpanded = !children.classList.contains('d-none');
            const toggle = this.container.querySelector(`[data-toggle-id="${nodeId}"]`);
            
            if (isExpanded) {
                children.classList.add('d-none');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('title', '展开');
                    const icon = toggle.querySelector('i');
                    if (icon) {
                        icon.className = 'bi bi-chevron-right';
                    }
                }
            } else {
                children.classList.remove('d-none');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                    toggle.setAttribute('title', '收起');
                    const icon = toggle.querySelector('i');
                    if (icon) {
                        icon.className = 'bi bi-chevron-down';
                    }
                }
            }
        }

        /**
         * 处理复选框变化
         */
        handleCheckboxChange(checkbox) {
            const nodeId = String(checkbox.value);

            if (checkbox.checked) {
                this.selectNodeWithDescendants(nodeId);
            } else {
                this.deselectNodeWithDescendants(nodeId);
            }

            this.refreshCheckboxStates();
            this.updateSummary();
            this.notifyChange();
        }

        /**
         * 处理搜索
         */
        handleSearch(keyword) {
            const lowerKeyword = keyword.trim().toLowerCase();
            const nodes = this.container.querySelectorAll('.permission-tree-node');

            if (!lowerKeyword) {
                nodes.forEach(node => {
                    node.style.display = '';
                });
                return;
            }

            nodes.forEach(node => {
                const checkbox = node.querySelector('.permission-checkbox');
                const label = node.querySelector('.permission-name');
                const nodeData = this.findNodeById(parseInt(checkbox.value));
                
                if (!nodeData) {
                    node.style.display = 'none';
                    return;
                }

                const matchName = nodeData.name.toLowerCase().includes(lowerKeyword);
                const matchSlug = nodeData.slug.toLowerCase().includes(lowerKeyword);
                const matchPath = (nodeData.path || '').toLowerCase().includes(lowerKeyword);
                const matchDesc = (nodeData.description || '').toLowerCase().includes(lowerKeyword);
                const hasMatchingChild = this.hasMatchingChild(nodeData, lowerKeyword);

                if (matchName || matchSlug || matchPath || matchDesc || hasMatchingChild) {
                    node.style.display = '';
                    // 如果有匹配的子节点，展开父节点
                    if (hasMatchingChild) {
                        const children = node.querySelector('.permission-tree-children');
                        if (children) {
                            children.classList.remove('d-none');
                            const toggle = node.querySelector('.permission-tree-toggle');
                            if (toggle) {
                                const icon = toggle.querySelector('i');
                                if (icon) {
                                    icon.className = 'bi bi-chevron-down';
                                }
                            }
                        }
                    }
                } else {
                    node.style.display = 'none';
                }
            });
        }

        /**
         * 检查是否有匹配的子节点
         */
        hasMatchingChild(node, keyword) {
            if (!node.children) return false;
            return node.children.some(child => {
                const matchName = child.name.toLowerCase().includes(keyword);
                const matchSlug = child.slug.toLowerCase().includes(keyword);
                const matchPath = (child.path || '').toLowerCase().includes(keyword);
                const matchDesc = (child.description || '').toLowerCase().includes(keyword);
                return matchName || matchSlug || matchPath || matchDesc || this.hasMatchingChild(child, keyword);
            });
        }

        /**
         * 根据ID查找节点
         */
        findNodeById(id) {
            const traverse = (nodes) => {
                if (!nodes) return null;
                for (const node of nodes) {
                    if (node.id === id) {
                        return node;
                    }
                    if (node.children) {
                        const found = traverse(node.children);
                        if (found) return found;
                    }
                }
                return null;
            };
            return traverse(this.options.tree);
        }

        /**
         * 更新统计信息
         */
        updateSummary() {
            const summary = this.container.querySelector('[data-permission-summary]');
            if (summary) {
                const selected = this.selectedIds.size;
                const selectedCountEl = summary.querySelector('[data-selected-count]');
                if (selectedCountEl) {
                    selectedCountEl.textContent = selected;
                }

                const totalCountEl = summary.querySelector('[data-total-count]');
                if (totalCountEl) {
                    totalCountEl.textContent = this.totalNodes;
                }

                const menuCountEl = summary.querySelector('[data-menu-count]');
                if (menuCountEl) {
                    menuCountEl.textContent = this.countByType('menu');
                }

                const buttonCountEl = summary.querySelector('[data-button-count]');
                if (buttonCountEl) {
                    buttonCountEl.textContent = this.countByType('button');
                }
            }

            const listContainer = this.container.querySelector('[data-selected-list]');
            if (listContainer) {
                const nodes = this.getSelectedNodes();
                if (!nodes.length) {
                    listContainer.innerHTML = `
                        <div class="permission-selected-empty">
                            <i class="bi bi-info-circle me-2"></i>尚未选择任何权限
                        </div>
                    `;
                } else {
                    listContainer.innerHTML = nodes
                        .map((node) => `
                            <div class="permission-selected-item">
                                <div class="selected-item-info">
                                    <div class="selected-item-name">${this.escapeHtml(node.name)}</div>
                                    <small class="badge ${node.type === 'menu' ? 'bg-primary' : 'bg-secondary'}">
                                        ${node.type === 'menu' ? '菜单' : '按钮'}
                                    </small>
                                </div>
                                <button type="button" class="btn btn-link btn-sm text-muted p-0" data-remove-id="${node.id}" title="取消选择">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        `)
                        .join('');
                }
            }
        }

        /**
         * 通知选择变化
         */
        notifyChange() {
            if (this.options.onSelectionChange) {
                const selectedArray = Array.from(this.selectedIds).map(id => parseInt(id));
                this.options.onSelectionChange(selectedArray, this.selectedIds);
            }
        }

        /**
         * 获取选中的ID数组
         */
        getSelectedIds() {
            return Array.from(this.selectedIds).map(id => parseInt(id));
        }

        /**
         * 设置选中的ID
         */
        setSelectedIds(ids) {
            this.selectedIds = new Set(ids.map(String));
            const checkboxes = this.container.querySelectorAll('.permission-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.selectedIds.has(String(cb.value));
            });
            this.updateSummary();
        }

        /**
         * HTML转义
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // 导出
    window.PermissionTree = PermissionTree;
})();

