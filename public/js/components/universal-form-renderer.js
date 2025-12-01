(function (window, document) {
    'use strict';

    class UniversalFormRenderer {
        constructor(options = {}) {
            this.schema = options.schema || {};
            this.config = options.config || {};
            this.formId = options.formId || 'createForm';
            this.fieldsWrapperSelector = options.fieldsWrapperSelector || null;
            this.submitButtonId = options.submitButtonId || 'submitBtn';
            this.loadingIndicatorId = options.loadingIndicatorId || null;

            this.form = document.getElementById(this.formId);
            if (!this.form) {
                console.error('[UniversalFormRenderer] 表单节点不存在:', this.formId);
                return;
            }

            this.fieldsWrapper = this.fieldsWrapperSelector
                ? this.form.querySelector(this.fieldsWrapperSelector)
                : this.form;

            this.submitButton = document.getElementById(this.submitButtonId);
            this.endpoints = this.schema.endpoints || {};
        this.siteOptionsCache = new Map();

            this.renderFields();
            this.attachFormSubmit();
            this.ensureDefaultEndpoints();
        }

        ensureDefaultEndpoints() {
            if (!this.schema.endpoints) {
                this.schema.endpoints = {};
            }

            if (!this.schema.endpoints.uploadToken) {
                if (typeof window !== 'undefined' && window.ADMIN_ENTRY_PATH) {
                    const base = window.ADMIN_ENTRY_PATH.replace(/\/$/, '');
                    this.schema.endpoints.uploadToken = `${base}/api/admin/upload/token`;
                }
            }

            if (!this.endpoints) {
                this.endpoints = {};
            }

            this.endpoints = Object.assign({}, this.schema.endpoints, this.endpoints);
        }

        renderFields() {
            if (!this.fieldsWrapper) {
                console.warn('[UniversalFormRenderer] 未找到字段容器');
                return;
            }

            const fields = Array.isArray(this.schema.fields) ? this.schema.fields : [];
            const fragment = document.createDocumentFragment();

            fields.forEach((field) => {
                const fieldType = (field.type || '').toLowerCase();
                
                // 处理卡片分组
                if (fieldType === 'card') {
                    const cardContainer = document.createElement('div');
                    cardContainer.className = 'col-12';
                    cardContainer.innerHTML = this.buildCardMarkup(field);
                    fragment.appendChild(cardContainer);
                    return;
                }
                
                // 处理分组标题
                if (fieldType === 'group') {
                    const groupContainer = document.createElement('div');
                    groupContainer.className = 'col-12';
                    groupContainer.innerHTML = this.buildGroupMarkup(field);
                    fragment.appendChild(groupContainer);
                    return;
                }
                
                // 处理分隔线
                if (fieldType === 'divider') {
                    const dividerContainer = document.createElement('div');
                    dividerContainer.className = 'col-12';
                    dividerContainer.innerHTML = this.buildDividerMarkup(field);
                    fragment.appendChild(dividerContainer);
                    return;
                }
                
                // 普通字段
                const column = document.createElement('div');
                column.className = this.getColumnClass(field);
                column.innerHTML = this.buildFieldMarkup(field);
                fragment.appendChild(column);
            });

            this.fieldsWrapper.innerHTML = '';
            this.fieldsWrapper.appendChild(fragment);

            if (this.loadingIndicatorId) {
                const loadingEl = document.getElementById(this.loadingIndicatorId);
                if (loadingEl) {
                    loadingEl.classList.add('d-none');
                }
            }

            this.form.classList.remove('d-none');
            this.initializeEnhancements();
            this.initializeGroups();
        }

        attachFormSubmit() {
            this.form.addEventListener('submit', (event) => {
                event.preventDefault();
                this.handleSubmit();
            });
        }

        handleSubmit() {
            if (!this.schema.submitUrl) {
                this.notify('danger', '未配置提交地址');
                return;
            }

            const payload = this.buildPayload();
            this.toggleSubmitState(true);

            // 从 schema 中获取 HTTP 方法，默认为 POST
            const method = this.schema.method || 'POST';
            const successMessage = method === 'PUT' ? '更新成功' : '创建成功';

            fetch(this.schema.submitUrl, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            })
                .then((response) => response.json())
                .then((result) => {
                    if (result.code === 200) {
                        const message = result.msg || successMessage;
                        
                        // 计算重定向 URL
                            let redirectUrl = this.schema.redirectUrl || this.schema.submitUrl;
                            if (method === 'PUT' && !this.schema.redirectUrl) {
                            // 对于 PUT 请求，redirectUrl 可能需要去掉 ID 部分
                                redirectUrl = redirectUrl.replace(/\/\d+$/, '');
                            }
                        
                        // 触发表单提交成功事件（由 fixed-bottom-actions 组件处理）
                        if (this.form && window.FixedBottomActions && typeof window.FixedBottomActions.triggerSuccess === 'function') {
                            window.FixedBottomActions.triggerSuccess(this.form, {
                                message: message,
                                redirect: redirectUrl
                            });
                        } else {
                            // 降级处理：直接触发自定义事件
                            const successEvent = new CustomEvent('submit-success', {
                                bubbles: true,
                                cancelable: true,
                                detail: {
                                    message: message,
                                    redirect: redirectUrl
                                }
                            });
                            this.form.dispatchEvent(successEvent);
                        }
                        
                        return;
                    }

                    throw new Error(result.msg || '提交失败');
                })
                .catch((error) => {
                    console.error('[UniversalFormRenderer] 提交失败', error);
                    this.notify('danger', error.message || '提交失败，请稍后重试');
                })
                .finally(() => {
                    this.toggleSubmitState(false);
                });
        }

        buildPayload() {
            const formData = new FormData(this.form);
            const data = {};
            const arrayBuffer = {};

            formData.forEach((value, key) => {
                if (key.endsWith('_min') || key.endsWith('_max') || key.endsWith('_current')) {
                    return;
                }

                if (key.endsWith('[]')) {
                    const actualKey = key.slice(0, -2);
                    if (!Array.isArray(arrayBuffer[actualKey])) {
                        arrayBuffer[actualKey] = [];
                    }
                    arrayBuffer[actualKey].push(value);
                    return;
                }

                data[key] = value;
            });

            Object.keys(arrayBuffer).forEach((key) => {
                data[key] = arrayBuffer[key];
            });

            const multiImageValues = this.collectMultiImageValues();
            Object.keys(multiImageValues).forEach((key) => {
                data[key] = multiImageValues[key];
            });

            const permissionTreeValues = this.collectPermissionTreeValues();
            Object.keys(permissionTreeValues).forEach((key) => {
                data[key] = permissionTreeValues[key];
            });

            // 这里不再对 *_ids 字段进行 JSON 字符串化，保持为原始数组，
            // 以便后端（如角色权限 permission_ids）可以按数组类型接收和验证。
            return data;
        }

        collectMultiImageValues() {
            if (!this.form) {
                return {};
            }

            const result = {};
            const wrappers = this.form.querySelectorAll('[data-universal-images]');

            wrappers.forEach((wrapper) => {
                const hiddenInput = wrapper.querySelector('input[type="hidden"][name]');
                if (!hiddenInput || !hiddenInput.name) {
                    return;
                }

                result[hiddenInput.name] = this.normalizeArrayValue(hiddenInput.value);
            });

            return result;
        }

        collectPermissionTreeValues() {
            if (!this.form) {
                return {};
            }

            const result = {};
            const wrappers = this.form.querySelectorAll('[data-permission-tree]');

            wrappers.forEach((wrapper) => {
                const fieldName = wrapper.dataset.permissionTree;
                if (!fieldName) {
                    return;
                }

                const checkboxes = wrapper.querySelectorAll('input.permission-tree-checkbox');
                const values = [];
                const valueSet = new Set();

                checkboxes.forEach((checkbox) => {
                    if (!(checkbox.checked || checkbox.indeterminate)) {
                        return;
                    }
                    const { value } = checkbox;
                    if (!value || valueSet.has(value)) {
                        return;
                    }
                    valueSet.add(value);
                    values.push(value);
                });

                result[fieldName] = values;
            });

            return result;
        }

        toggleSubmitState(isLoading) {
            if (!this.submitButton) {
                return;
            }

            this.submitButton.disabled = isLoading;
            this.submitButton.innerHTML = isLoading
                ? '<span class="spinner-border spinner-border-sm me-1"></span> 提交中...'
                : '<i class="bi bi-check-lg me-1"></i> 保存';
        }

        buildGroupMarkup(field = {}) {
            const title = field.title || field.label || '分组';
            const collapsible = field.collapsible !== false; // 默认可折叠
            const collapsed = field.collapsed === true; // 默认展开
            const groupId = `group_${Math.random().toString(16).slice(2)}`;
            
            // 构建自定义属性（支持 data-* 属性）
            const customAttrs = [];
            Object.keys(field).forEach(key => {
                if (key.startsWith('data-')) {
                    customAttrs.push(`${key}="${this.escapeAttr(field[key])}"`);
                }
            });
            const customAttrsStr = customAttrs.length > 0 ? ' ' + customAttrs.join(' ') : '';
            
            if (collapsible) {
                return `
                    <div class="universal-form-group mb-4" data-group-id="${groupId}"${customAttrsStr}>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="mb-0 fw-bold text-primary">${this.escape(title)}</h5>
                            <button 
                                type="button" 
                                class="btn btn-sm btn-link p-0 text-decoration-none"
                                data-group-toggle="${groupId}"
                                aria-expanded="${!collapsed}"
                            >
                                <i class="bi bi-chevron-${collapsed ? 'down' : 'up'}"></i>
                            </button>
                        </div>
                        <div class="universal-form-group-content" data-group-content="${groupId}" style="display: ${collapsed ? 'none' : 'block'};">
                            <div class="row">
                                <!-- 分组内容将在这里动态插入 -->
                            </div>
                        </div>
                    </div>
                `;
            } else {
                return `
                    <div class="universal-form-group mb-4" data-group-id="${groupId}"${customAttrsStr}>
                        <h5 class="mb-3 fw-bold text-primary">${this.escape(title)}</h5>
                        <div class="universal-form-group-content" data-group-content="${groupId}">
                            <div class="row">
                                <!-- 分组内容将在这里动态插入 -->
                            </div>
                        </div>
                    </div>
                `;
            }
        }
        
        buildDividerMarkup(field = {}) {
            const text = field.text || field.label || '';
            const margin = field.margin || 'my-4'; // 默认上下边距
            
            if (text) {
                return `
                    <div class="universal-form-divider ${margin}">
                        <hr class="my-0">
                        <div class="text-center">
                            <span class="bg-white px-3 text-muted small">${this.escape(text)}</span>
                        </div>
                    </div>
                `;
            } else {
                return `
                    <div class="universal-form-divider ${margin}">
                        <hr>
                    </div>
                `;
            }
        }
        
        buildCardMarkup(field = {}) {
            const title = field.title || field.label || '';
            const collapsible = field.collapsible !== false; // 默认可折叠
            const collapsed = field.collapsible === false ? false : (field.collapsed === true); // 如果不可折叠，则默认展开；否则根据 collapsed 属性
            const cardId = `card_${Math.random().toString(16).slice(2)}`;
            const cardClass = field.cardClass || 'mb-4'; // 卡片外层样式类
            const cardBodyClass = field.cardBodyClass || ''; // 卡片 body 样式类
            
            // 构建自定义属性（支持 data-* 属性）
            const customAttrs = [];
            Object.keys(field).forEach(key => {
                if (key.startsWith('data-')) {
                    customAttrs.push(`${key}="${this.escapeAttr(field[key])}"`);
                }
            });
            const customAttrsStr = customAttrs.length > 0 ? ' ' + customAttrs.join(' ') : '';
            
            // 渲染卡片内的字段
            const cardFields = Array.isArray(field.fields) ? field.fields : [];
            const fieldsHtml = this.renderFieldsToHtml(cardFields);
            
            if (collapsible) {
                const isExpanded = !collapsed;
                return `
                    <div class="card ${cardClass}" data-card-id="${cardId}"${customAttrsStr}>
                        <div class="card-header d-flex align-items-center justify-content-between" style="cursor: pointer;" data-card-toggle="${cardId}" aria-expanded="${isExpanded}">
                            <h6 class="mb-0 fw-bold">${this.escape(title)}</h6>
                            <button 
                                type="button" 
                                class="btn btn-sm btn-link p-1 text-decoration-none border-0"
                                data-card-toggle="${cardId}"
                                aria-expanded="${isExpanded}"
                                style="min-width: 24px; min-height: 24px; display: flex; align-items: center; justify-content: center;"
                            >
                                <i class="bi bi-chevron-${collapsed ? 'down' : 'up'}"></i>
                            </button>
                        </div>
                        <div class="card-body ${cardBodyClass}" data-card-content="${cardId}" style="display: ${collapsed ? 'none' : 'block'};">
                            <div class="row">
                                ${fieldsHtml}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                return `
                    <div class="card ${cardClass}" data-card-id="${cardId}"${customAttrsStr}>
                        ${title ? `<div class="card-header"><h6 class="mb-0 fw-bold">${this.escape(title)}</h6></div>` : ''}
                        <div class="card-body ${cardBodyClass}" data-card-content="${cardId}">
                            <div class="row">
                                ${fieldsHtml}
                            </div>
                        </div>
                    </div>
                `;
            }
        }
        
        renderFieldsToHtml(fields = []) {
            return fields.map((field) => {
                const fieldType = (field.type || '').toLowerCase();
                
                // 卡片内不支持嵌套卡片、分组和分隔线（避免过度嵌套）
                if (fieldType === 'card' || fieldType === 'group' || fieldType === 'divider') {
                    console.warn('[UniversalFormRenderer] 卡片内不支持嵌套卡片、分组或分隔线');
                    return '';
                }
                
                const columnClass = this.getColumnClass(field);
                const fieldMarkup = this.buildFieldMarkup(field);
                
                return `<div class="${columnClass}">${fieldMarkup}</div>`;
            }).join('');
        }

        buildFieldMarkup(field = {}) {
            const name = field.name || `field_${Math.random().toString(16).slice(2)}`;
            const label = field.label || name;
            const requiredMark = field.required ? ' <span class="text-danger">*</span>' : '';
            const helpText = field.help ? `<div class="form-text">${this.escape(field.help)}</div>` : '';
            const controlHtml = this.renderFieldControl(field);

            // 所有字段都显示外层 label，保持结构统一
            const labelHtml = `<label class="form-label" for="${this.escapeAttr(this.getFieldId(field))}">
                        ${this.escape(label)}${requiredMark}
                   </label>`;

            // 构建自定义属性（支持 data-* 属性）
            const customAttrs = [];
            Object.keys(field).forEach(key => {
                // 支持 data-* 格式的属性
                if (key.startsWith('data-')) {
                    customAttrs.push(`${key}="${this.escapeAttr(field[key])}"`);
                }
            });
            const customAttrsStr = customAttrs.length > 0 ? ' ' + customAttrs.join(' ') : '';

            return `
                <div class="universal-form-field mb-3" data-field-name="${this.escapeAttr(name)}"${customAttrsStr}>
                    ${labelHtml}
                    ${controlHtml}
                    ${helpText}
                </div>
            `;
        }

        renderFieldControl(field) {
            const type = (field.type || 'text').toLowerCase();

            switch (type) {
                case 'email':
                case 'password':
                case 'text':
                    return this.renderTextInput(field, type);
                case 'number':
                    return this.renderNumberInput(field);
                case 'textarea':
                    return this.renderTextarea(field);
                case 'select':
                case 'relation':
                    return this.renderSelect(field);
                case 'site_select':
                    return this.renderSiteSelector(field);
                case 'radio':
                    return this.renderRadioGroup(field);
                case 'checkbox':
                    return this.renderCheckboxGroup(field);
                case 'date':
                case 'datetime':
                case 'datetime-local':
                case 'time':
                    return this.renderDateInput(field, type);
                case 'switch':
                    return this.renderSwitch(field);
                case 'number_range':
                    return this.renderNumberRange(field);
                case 'image':
                    return this.renderImageField(field);
                case 'images':
                    return this.renderImagesField(field);
                case 'rich_text':
                    return this.renderRichTextField(field);
                case 'permission_tree':
                    return this.renderPermissionTreeField(field);
                case 'color':
                    return this.renderColorField(field);
                case 'gradient':
                    return this.renderGradientField(field);
                case 'key_value':
                    return this.renderKeyValueField(field);
                default:
                    return this.renderTextInput(field, 'text');
            }
        }

        renderPermissionTreeField(field) {
            const fieldName = field.name || 'permission_ids';
            const treeData = Array.isArray(field.treeData) ? field.treeData : [];
            const selectedValues = this.normalizeArrayValue(this.getFieldValue(field));
            const typeMap = field.typeMap || {};

            const buildNodes = (nodes, level = 0) => {
                if (!Array.isArray(nodes) || !nodes.length) {
                    return '';
                }

                    return nodes.map((node) => {
                    const id = String(node.id ?? '');
                    if (!id) {
                        return '';
                    }
                    const hasChildren = Array.isArray(node.children) && node.children.length > 0;
                    const isChecked = selectedValues.includes(id);
                    const name = this.escape(node.name ?? '');
                    const type = String(node.type ?? '');
                    const slug = this.escape(node.slug ?? '');
                    const path = this.escape(node.path ?? '');
                    const method = String(node.method ?? '');

                    let typeBadge = '';
                    if (type) {
                        const cfg = typeMap[type] || null;
                        if (cfg) {
                            const cls = cfg.class || 'badge bg-secondary';
                            const label = cfg.label || type;
                            typeBadge = `<span class="badge ${this.escapeAttr(cls)} ms-2">${this.escape(label)}</span>`;
                        } else {
                            typeBadge = `<span class="badge bg-secondary ms-2">${this.escape(type)}</span>`;
                        }
                    }

                    const childrenHtml = hasChildren ? `<ul class="list-unstyled ms-4 permission-tree-children">
                            ${buildNodes(node.children, level + 1)}
                        </ul>` : '';

                    return `
                        <li class="permission-tree-node mb-1" data-id="${this.escapeAttr(id)}" data-parent-id="${this.escapeAttr(String(node.parent_id ?? '0'))}">
                            <div class="d-flex align-items-center">
                                ${hasChildren
                                    ? `<button type="button"
                                            class="btn btn-sm btn-link text-secondary px-0 me-1 permission-tree-toggle"
                                            data-expanded="1"
                                            aria-label="切换子节点显示">
                                            <i class="bi bi-chevron-down" style="font-size: 0.9rem;"></i>
                                       </button>`
                                    : '<span class="d-inline-block me-1" style="width: 1.25rem;"></span>'}
                                <div class="flex-grow-1">
                                    <div class="form-check m-0">
                                        <input
                                            class="form-check-input permission-tree-checkbox"
                                            type="checkbox"
                                            name="${this.escapeAttr(fieldName)}[]"
                                            value="${this.escapeAttr(id)}"
                                            data-label="${name}"
                                            ${isChecked ? 'checked' : ''}
                                        >
                                        <label class="form-check-label d-inline-flex align-items-center ms-1">
                                            <span class="fw-normal">${name || '-'}</span>
                                            ${typeBadge}
                                        </label>
                                    </div>
                                    ${(slug || path || method)
                                        ? `<div class="text-muted small mt-1">
                                                ${slug ? `<span class="me-3">标识：<code>${slug}</code></span>` : ''}
                                                ${path ? `<span class="me-3 permission-tree-meta-path">路径：<code>${path}</code></span>` : ''}
                                                ${method ? `<span>方法：<code>${this.escape(method)}</code></span>` : ''}
                                           </div>`
                                        : ''
                                    }
                                </div>
                            </div>
                            ${childrenHtml}
                        </li>
                    `;
                }).join('');
            };

            const treeHtml = buildNodes(treeData);

            return `
                <div class="permission-tree-wrapper" data-permission-tree="${this.escapeAttr(fieldName)}">
                    <div class="row g-3">
                        <div class="col-12 col-md-7">
                            <div class="border rounded p-2 h-100 d-flex flex-column">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-secondary" data-tree-action="expand-all">
                                            <i class="bi bi-arrows-expand"></i> 展开全部
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-tree-action="collapse-all">
                                            <i class="bi bi-arrows-collapse"></i> 收起全部
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm ms-1" role="group">
                                        <button type="button" class="btn btn-outline-secondary" data-tree-action="select-all">
                                            <i class="bi bi-check2-all"></i> 全选
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" data-tree-action="clear-all">
                                            <i class="bi bi-x-lg"></i> 全不选
                                        </button>
                                    </div>
                                    <div class="ms-auto d-flex align-items-center gap-2" style="min-width: 220px;">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            <input type="search" class="form-control" placeholder="搜索权限名称/标识" data-tree-search>
                                        </div>
                                    </div>
                                </div>
                                <div class="permission-tree-body flex-grow-1 overflow-auto" style="max-height: 420px;">
                                    <ul class="list-unstyled mb-0 permission-tree-root">
                                        ${treeHtml || '<li class="text-muted small">暂无可分配的权限</li>'}
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-5">
                            <div class="border rounded p-2 bg-light h-100 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold small text-muted">已选权限</span>
                                    <span class="small text-muted" data-permission-tree-summary>0 项</span>
                                </div>
                                <div class="permission-tree-selected-list small flex-grow-1 overflow-auto" data-permission-tree-selected-list style="max-height: 420px;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        renderTextInput(field, subtype) {
            const id = this.getFieldId(field);
            const value = this.getFieldValue(field);

            return `
                <input
                    type="${this.escapeAttr(subtype)}"
                    class="form-control"
                    id="${id}"
                    name="${this.escapeAttr(field.name)}"
                    value="${this.escapeAttr(value)}"
                    ${this.buildCommonAttributes(field)}
                >
            `;
        }

        renderNumberInput(field) {
            const id = this.getFieldId(field);
            const value = this.getFieldValue(field);
            const attrs = [];

            if (typeof field.min !== 'undefined') {
                attrs.push(`min="${this.escapeAttr(field.min)}"`);
            }
            if (typeof field.max !== 'undefined') {
                attrs.push(`max="${this.escapeAttr(field.max)}"`);
            }
            if (typeof field.step !== 'undefined') {
                attrs.push(`step="${this.escapeAttr(field.step)}"`);
            }

            return `
                <input
                    type="number"
                    class="form-control"
                    id="${id}"
                    name="${this.escapeAttr(field.name)}"
                    value="${this.escapeAttr(value)}"
                    ${attrs.join(' ')}
                    ${this.buildCommonAttributes(field)}
                >
            `;
        }

        renderTextarea(field) {
            const id = this.getFieldId(field);
            const value = this.getFieldValue(field);
            const rows = field.rows ? parseInt(field.rows, 10) : 4;

            return `
                <textarea
                    class="form-control"
                    id="${id}"
                    name="${this.escapeAttr(field.name)}"
                    rows="${rows}"
                    ${this.buildCommonAttributes(field)}
                >${this.escape(value)}</textarea>
            `;
        }

        renderSelect(field) {
            const id = this.getFieldId(field);
            const isRelation = field.type === 'relation';
            const multiple = this.isMultiple(field);
            const selectName = multiple ? `${field.name}[]` : field.name;
            const options = this.getOptions(field);
            const currentValue = this.getFieldValue(field);
            const selectedValues = multiple
                ? this.normalizeArrayValue(currentValue)
                : (currentValue !== null && currentValue !== '' ? [String(currentValue)] : []);
            const hasOptions = options.length > 0;
            const isAsync = isRelation && !hasOptions;
            const placeholder = field.placeholder || `请选择${field.label ?? ''}`;

            // 渲染选项
            const optionsHtml = hasOptions
                ? options
                      .map((option) => {
                          const optionValue = String(option.value ?? '');
                          const selected = selectedValues.includes(optionValue) ? 'selected' : '';
                          return `<option value="${this.escapeAttr(optionValue)}" ${selected}>
                                    ${this.escape(option.label)}
                                  </option>`;
                      })
                      .join('')
                : (isAsync && selectedValues.length > 0
                      ? this.renderSelectedPlaceholders(selectedValues)
                      : '');

            return `
                <select
                    class="form-select"
                    id="${id}"
                    name="${this.escapeAttr(selectName)}"
                    ${multiple ? 'multiple' : ''}
                    ${field.required ? 'required' : ''}
                    data-universal-select="1"
                    data-field-name="${this.escapeAttr(field.name)}"
                    data-placeholder="${this.escapeAttr(placeholder)}"
                    data-async="${isAsync ? '1' : '0'}"
                >
                    ${optionsHtml}
                </select>
            `;
        }

        renderSiteSelector(field) {
            const id = this.getFieldId(field);
            const value = this.getFieldValue(field);
            const placeholder = field.placeholder || '输入站点名称或域名搜索';
            const disabled = Boolean(field.disabled || field.readonly);
            const allowClear = !field.required && !disabled;
            const summary = value ? `当前选择：站点 #${value}` : '尚未选择站点';

            if (!this.endpoints.siteOptions) {
                return `
                    <div class="alert alert-warning mb-2">
                        <i class="bi bi-shield-lock me-1"></i> 当前账号无权选择站点
                    </div>
                    <input
                        type="hidden"
                        id="${id}"
                        name="${this.escapeAttr(field.name)}"
                        value="${this.escapeAttr(value)}"
                        ${field.required ? 'required' : ''}
                    >
                    <div class="text-muted small">
                        ${value ? `已绑定站点 ID：${this.escape(value)}` : '将使用当前登录站点'}
                    </div>
                `;
            }

            return `
                <div class="site-selector-wrapper border rounded p-3" data-site-selector="${this.escapeAttr(field.name)}" data-site-selector-disabled="${disabled ? '1' : '0'}">
                    <input
                        type="hidden"
                        id="${id}"
                        name="${this.escapeAttr(field.name)}"
                        value="${this.escapeAttr(value)}"
                        ${field.required ? 'required' : ''}
                        ${disabled ? 'disabled' : ''}
                    >
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <div class="flex-grow-1">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input
                                    type="search"
                                    class="form-control"
                                    placeholder="${this.escapeAttr(placeholder)}"
                                    data-site-selector-search
                                    ${disabled ? 'disabled' : ''}
                                >
                            </div>
                        </div>
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm"
                            data-site-selector-refresh
                            ${disabled ? 'disabled' : ''}
                        >
                            <i class="bi bi-arrow-repeat me-1"></i>刷新
                        </button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted" data-site-selector-summary>${this.escape(summary)}</small>
                        ${allowClear ? `<button type="button" class="btn btn-link btn-sm p-0" data-site-selector-clear ${value ? '' : 'disabled'}>清空</button>` : ''}
                    </div>
                    <div class="site-selector-list row g-3" data-site-selector-list>
                        ${this.buildSiteSelectorLoading()}
                    </div>
                </div>
            `;
        }

        buildSiteSelectorLoading() {
            return `
                <div class="col-12 text-center text-muted py-4" data-site-selector-loading>
                    <div class="spinner-border spinner-border-sm mb-2" role="status"></div>
                    <div>加载站点列表...</div>
                </div>
            `;
        }

        renderSelectedPlaceholders(values = []) {
            if (!values || !values.length) {
                return '';
            }

            return values
                .filter((value) => value !== undefined && value !== null && value !== '')
                .map(
                    (value) => `
                        <option value="${this.escapeAttr(value)}" selected>${this.escape(value)}</option>
                    `,
                )
                .join('');
        }

        renderRadioGroup(field) {
            const options = this.getOptions(field);
            const currentValue = this.getFieldValue(field);
            if (!options.length) {
                return '<div class="text-muted small">未配置选项</div>';
            }

            return `
                <div class="d-flex flex-wrap gap-3">
                    ${options
                        .map((option, index) => {
                            const id = `${this.getFieldId(field)}_${index}`;
                            const checked = option.value === currentValue ? 'checked' : '';
                            return `
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="${this.escapeAttr(field.name)}"
                                        id="${id}"
                                        value="${this.escapeAttr(option.value)}"
                                        ${checked}
                                        ${field.required ? 'required' : ''}
                                    >
                                    <label class="form-check-label" for="${id}">
                                        ${this.escape(option.label)}
                                    </label>
                                </div>
                            `;
                        })
                        .join('')}
                </div>
            `;
        }

        renderCheckboxGroup(field) {
            const options = this.getOptions(field);
            const groupAttr = `data-checkbox-group="${this.escapeAttr(field.name)}"`;

            if (!options.length) {
                return `<div class="text-muted small" ${groupAttr}>未配置选项</div>`;
            }

            const currentValue = this.normalizeArrayValue(this.getFieldValue(field));

            return `
                <div class="d-flex flex-wrap gap-3 universal-checkbox-group" ${groupAttr}>
                    ${options
                        .map((option, index) => {
                            const id = `${this.getFieldId(field)}_${index}`;
                            const checked = currentValue.includes(option.value) ? 'checked' : '';
                            return `
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="${this.escapeAttr(field.name)}[]"
                                        id="${id}"
                                        value="${this.escapeAttr(option.value)}"
                                        ${checked}
                                    >
                                    <label class="form-check-label" for="${id}">
                                        ${this.escape(option.label)}
                                    </label>
                                </div>
                            `;
                        })
                        .join('')}
                </div>
            `;
        }

        renderDateInput(field, subtype) {
            const id = this.getFieldId(field);
            const value = this.getFieldValue(field);
            // 使用 text 类型，Flatpickr 会接管输入框
            const isDatetime = subtype === 'datetime' || subtype === 'datetime-local';
            const isTime = subtype === 'time';
            const dataType = isDatetime ? 'datetime' : (isTime ? 'time' : 'date');

            return `
                <input
                    type="text"
                    class="form-control"
                    id="${id}"
                    name="${this.escapeAttr(field.name)}"
                    value="${this.escapeAttr(value)}"
                    data-flatpickr-type="${this.escapeAttr(dataType)}"
                    ${this.buildCommonAttributes(field)}
                >
            `;
        }

        renderSwitch(field) {
            const id = this.getFieldId(field);
            const currentValue = this.getFieldValue(field);
            const onValue = field.onValue ?? '1';
            const offValue = field.offValue ?? '0';
            // 统一转换为字符串比较，确保编辑模式下的默认值正确显示
            // 如果 currentValue 为空字符串，说明没有值，默认关闭（不选中）
            const checked = currentValue !== '' && String(currentValue) === String(onValue);

            return `
                <div class="form-check form-switch">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="${id}"
                        ${checked ? 'checked' : ''}
                        ${field.disabled ? 'disabled' : ''}
                        ${field.readonly ? 'readonly' : ''}
                    >
                    <input
                        type="hidden"
                        name="${this.escapeAttr(field.name)}"
                        value="${checked ? this.escapeAttr(onValue) : this.escapeAttr(offValue)}"
                        data-switch-target="${id}"
                        data-switch-on="${this.escapeAttr(onValue)}"
                        data-switch-off="${this.escapeAttr(offValue)}"
                    >
                </div>
            `;
        }

        renderNumberRange(field) {
            const id = this.getFieldId(field);
            const rangeValue = this.parseRangeValue(this.getFieldValue(field));

            return `
                <div class="row g-2" data-range-field="${this.escapeAttr(field.name)}">
                    <div class="col-12 col-md-6">
                        <input
                            type="number"
                            class="form-control"
                            id="${id}_min"
                            placeholder="最小值"
                            value="${this.escapeAttr(rangeValue.min ?? '')}"
                        >
                    </div>
                    <div class="col-12 col-md-6">
                        <input
                            type="number"
                            class="form-control"
                            id="${id}_max"
                            placeholder="最大值"
                            value="${this.escapeAttr(rangeValue.max ?? '')}"
                        >
                    </div>
                    <input type="hidden" name="${this.escapeAttr(field.name)}" id="${id}" value="${this.escapeAttr(rangeValue.raw)}">
                </div>
            `;
        }

        renderImageField(field) {
            const id = this.getFieldId(field);
            const value = this.getFieldValue(field);

            return `
                <div class="universal-image-field" data-universal-image="${this.escapeAttr(field.name)}">
                    <div class="input-group">
                        <input
                            type="text"
                            class="form-control"
                            id="${id}"
                            name="${this.escapeAttr(field.name)}"
                            placeholder="${this.escapeAttr(field.placeholder ?? '请输入或上传图片 URL')}"
                            value="${this.escapeAttr(value)}"
                            ${field.required ? 'required' : ''}
                        >
                        <button class="btn btn-outline-secondary" type="button" data-action="upload">
                            <i class="bi bi-upload me-1"></i>上传
                        </button>
                        <button class="btn btn-outline-secondary" type="button" data-action="preview" ${value ? '' : 'disabled'}>
                            <i class="bi bi-eye me-1"></i>预览
                        </button>
                    </div>
                    <input type="file" accept="image/*" class="d-none" data-role="image-input">
                    <div class="text-muted small mt-1">支持 JPG/PNG，单张不超过 10MB</div>
                    <div class="mt-2" data-preview></div>
                </div>
            `;
        }

        renderImagesField(field) {
            const id = this.getFieldId(field);
            const value = this.normalizeArrayValue(this.getFieldValue(field));

            return `
                <div class="universal-images-field" data-universal-images="${this.escapeAttr(field.name)}">
                    <input type="hidden" name="${this.escapeAttr(field.name)}" id="${id}" value="${this.escapeAttr(JSON.stringify(value))}">
                    <div class="d-flex flex-wrap gap-2" data-preview></div>
                    <div class="d-flex gap-2 mt-2">
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-action="upload">
                            <i class="bi bi-images me-1"></i>上传图片
                        </button>
                        <button class="btn btn-outline-danger btn-sm" type="button" data-action="clear">
                            <i class="bi bi-trash me-1"></i>清空
                        </button>
                    </div>
                    <input type="file" accept="image/*" multiple class="d-none" data-role="images-input">
                    <div class="text-muted small mt-1">可上传多张图片，将自动保存为 JSON 数组</div>
                </div>
            `;
        }

        renderRichTextField(field) {
            const id = this.getFieldId(field);
            const value = this.getFieldValue(field);

            return `
                <textarea
                    class="form-control"
                    id="${id}"
                    name="${this.escapeAttr(field.name)}"
                    rows="${field.rows ? parseInt(field.rows, 10) : 10}"
                    placeholder="${this.escapeAttr(field.placeholder ?? '请输入内容...')}"
                >${this.escape(value)}</textarea>
            `;
        }

        renderColorField(field) {
            const id = this.getFieldId(field);
            const value = this.getFieldValue(field);
            const previewId = `${id}_preview`;
            // 规范化颜色值，确保以 # 开头
            let normalizedValue = '';
            if (value) {
                normalizedValue = value.startsWith('#') ? value : `#${value}`;
                // 验证是否为有效的 HEX 颜色值
                if (!/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/.test(normalizedValue)) {
                    normalizedValue = '#f8f9fa';
                }
            } else {
                normalizedValue = '#f8f9fa';
            }

            return `
                <div class="color-input-group">
                    <div class="input-group">
                        <span class="input-group-text p-0" style="width: 40px; border-right: none;">
                            <span
                                id="${this.escapeAttr(previewId)}"
                                class="color-preview-swatch d-inline-block w-100 h-100"
                                style="background-color: ${this.escapeAttr(normalizedValue)}; border-radius: 0.375rem 0 0 0.375rem;"
                            ></span>
                        </span>
                        <input
                            type="text"
                            class="form-control"
                            id="${this.escapeAttr(id)}"
                            name="${this.escapeAttr(field.name)}"
                            placeholder="${this.escapeAttr(field.placeholder ?? '例如：#667eea')}"
                            value="${this.escapeAttr(value)}"
                            data-color-input="true"
                            data-color-preview="${this.escapeAttr(previewId)}"
                            ${field.required ? 'required' : ''}
                            ${field.disabled ? 'disabled' : ''}
                            ${field.readonly ? 'readonly' : ''}
                        >
                        <button
                            class="btn btn-outline-secondary"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#colorPickerModal"
                            data-target-input="${this.escapeAttr(id)}"
                            data-preview-target="${this.escapeAttr(previewId)}"
                            ${field.disabled || field.readonly ? 'disabled' : ''}
                        >
                            <i class="bi bi-palette2 me-1"></i>选择颜色
                        </button>
                    </div>
                </div>
            `;
        }

        renderGradientField(field) {
            const id = this.getFieldId(field);
            const value = this.getFieldValue(field);
            const previewId = `${id}_preview`;
            // 默认渐变值
            const defaultGradient = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            const gradientValue = value || defaultGradient;

            return `
                <div class="gradient-input-group">
                    <div class="input-group">
                        <span class="input-group-text p-0" style="width: 60px; border-right: none;">
                            <span
                                id="${this.escapeAttr(previewId)}"
                                class="gradient-preview-swatch d-inline-block w-100 h-100"
                                style="background: ${this.escapeAttr(gradientValue)}; border-radius: 0.375rem 0 0 0.375rem; border: 1px solid #e5e7eb;"
                            ></span>
                        </span>
                        <input
                            type="text"
                            class="form-control"
                            id="${this.escapeAttr(id)}"
                            name="${this.escapeAttr(field.name)}"
                            placeholder="${this.escapeAttr(field.placeholder ?? '例如：linear-gradient(135deg, #667eea 0%, #764ba2 100%)')}"
                            value="${this.escapeAttr(value)}"
                            data-gradient-input="true"
                            data-gradient-preview="${this.escapeAttr(previewId)}"
                            ${field.required ? 'required' : ''}
                            ${field.disabled ? 'disabled' : ''}
                            ${field.readonly ? 'readonly' : ''}
                        >
                        <button
                            class="btn btn-outline-secondary"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#gradientPickerModal"
                            data-target-input="${this.escapeAttr(id)}"
                            data-preview-target="${this.escapeAttr(previewId)}"
                            ${field.disabled || field.readonly ? 'disabled' : ''}
                        >
                            <i class="bi bi-palette2 me-1"></i>选择渐变
                        </button>
                    </div>
                    ${field.help ? `<div class="form-text">${this.escape(field.help)}</div>` : ''}
                </div>
            `;
        }

        renderKeyValueField(field) {
            const id = this.getFieldId(field);
            const fieldName = field.name || 'key_value';
            const value = this.getFieldValue(field);
            
            // 解析键值对数据：支持对象格式 {key1: value1, key2: value2} 或数组格式 [{key: 'k1', value: 'v1'}, ...]
            let keyValuePairs = [];
            if (value) {
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
                    // 解析失败，使用空数组
                    keyValuePairs = [];
                }
            }
            
            // 如果没有数据，至少显示一行空行
            if (keyValuePairs.length === 0) {
                keyValuePairs = [{ key: '', value: '' }];
            }

            const pairsHtml = keyValuePairs.map((pair, index) => {
                const pairId = `${id}_pair_${index}`;
                return `
                    <div class="key-value-pair mb-2" data-pair-index="${index}">
                        <div class="row g-2">
                            <div class="col-5">
                                <input
                                    type="text"
                                    class="form-control form-control-sm key-input"
                                    placeholder="键"
                                    value="${this.escapeAttr(pair.key || '')}"
                                    data-key-value-key
                                >
                            </div>
                            <div class="col-5">
                                <input
                                    type="text"
                                    class="form-control form-control-sm value-input"
                                    placeholder="值"
                                    value="${this.escapeAttr(pair.value || '')}"
                                    data-key-value-value
                                >
                            </div>
                            <div class="col-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger w-100 remove-pair-btn"
                                    ${keyValuePairs.length === 1 ? 'disabled' : ''}
                                    title="删除"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            return `
                <div class="key-value-field-wrapper" data-key-value-field="${this.escapeAttr(fieldName)}">
                    <div class="key-value-pairs-container">
                        ${pairsHtml}
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary add-pair-btn"
                        ${field.disabled || field.readonly ? 'disabled' : ''}
                    >
                        <i class="bi bi-plus-circle me-1"></i>添加键值对
                    </button>
                    <input
                        type="hidden"
                        id="${id}"
                        name="${this.escapeAttr(fieldName)}"
                        value="${this.escapeAttr(JSON.stringify(keyValuePairs))}"
                        data-key-value-hidden
                    >
                </div>
            `;
        }

        initializeEnhancements() {
            this.initializeSelects();
            this.initializeSwitches();
            this.initializeNumberRangeFields();
            this.initializeImageFields();
            this.initializeMultiImageFields();
            this.initializeDateInputs();
            this.initializePermissionTreeFields();
            this.initializeSiteSelectors();
            this.initializeKeyValueFields();
        }
        
        initializeGroups() {
            // 初始化分组折叠功能
            const toggleButtons = this.form.querySelectorAll('[data-group-toggle]');
            toggleButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const groupId = button.dataset.groupToggle;
                    const content = this.form.querySelector(`[data-group-content="${groupId}"]`);
                    const icon = button.querySelector('i');
                    
                    if (!content) {
                        return;
                    }
                    
                    const isExpanded = content.style.display !== 'none';
                    content.style.display = isExpanded ? 'none' : 'block';
                    button.setAttribute('aria-expanded', !isExpanded);
                    
                    if (icon) {
                        icon.className = isExpanded ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
                    }
                });
            });
            
            // 初始化卡片折叠功能
            // 使用事件委托，只绑定一次事件监听器
            const cardHeaders = this.form.querySelectorAll('.card-header[data-card-toggle]');
            cardHeaders.forEach(header => {
                header.addEventListener('click', (e) => {
                    // 如果点击的是按钮，让按钮的事件处理（但按钮没有单独的事件，所以这里统一处理）
                    const cardId = header.dataset.cardToggle;
                    const content = this.form.querySelector(`[data-card-content="${cardId}"]`);
                    
                    if (!content) {
                        return;
                    }
                    
                    const isExpanded = content.style.display !== 'none';
                    content.style.display = isExpanded ? 'none' : 'block';
                    
                    // 更新 header 的 aria-expanded
                    header.setAttribute('aria-expanded', !isExpanded);
                    
                    // 更新按钮的 aria-expanded 和图标
                    const button = header.querySelector('button[data-card-toggle]');
                    if (button) {
                        button.setAttribute('aria-expanded', !isExpanded);
                        const icon = button.querySelector('i');
                        if (icon) {
                            icon.className = isExpanded ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
                        }
                    }
                });
            });
        }

        initializeSelects() {
            if (typeof window.TomSelect !== 'function') {
                return;
            }

            const selects = this.form.querySelectorAll('select[data-universal-select="1"]');

            selects.forEach((select) => {
                if (select._tsInstance) {
                    return;
                }

                const isMultiple = select.multiple;
                const placeholder = select.dataset.placeholder || '请选择';
                const isAsync = select.dataset.async === '1';
                const fieldName = select.dataset.fieldName || '';

                const config = {
                    plugins: ['dropdown_input'],
                    placeholder,
                    allowEmptyOption: !isMultiple,
                    create: false,
                    maxOptions: null,
                    closeAfterSelect: !isMultiple,
                    render: {
                        option: function (data, escape) {
                            return `<div class="option-item">${escape(data.text)}</div>`;
                        },
                        item: function (data, escape) {
                            return `<div class="item-label">${escape(data.text)}</div>`;
                        },
                    },
                };

                if (isAsync && this.endpoints.relationSearch) {
                    config.load = (query, callback) => {
                        this.loadRelationOptions(fieldName, query)
                            .then((results) => callback(results))
                            .catch(() => callback());
                    };

                    // 对于异步加载的选项，如果有选中的值但不在选项中，需要先加载这些值
                    const selectedOptions = Array.from(select.selectedOptions);
                    const selectedValues = selectedOptions.map(opt => opt.value).filter(v => v !== '');
                    
                    if (selectedValues.length > 0) {
                        // 检查选中的值是否已经在选项中
                        const existingOptions = Array.from(select.options).map(opt => opt.value);
                        const missingValues = selectedValues.filter(v => !existingOptions.includes(v));

                        if (missingValues.length > 0) {
                            // 对于缺失的值，尝试加载它们
                            Promise.all(
                                missingValues.map(value => 
                                    this.loadRelationOptions(fieldName, value)
                                        .then(results => {
                                            // 找到匹配的选项并添加到 select 中
                                            const matched = results.find(r => String(r.value) === String(value));
                                            if (matched) {
                                                const option = document.createElement('option');
                                                option.value = String(matched.value);
                                                option.textContent = matched.text || matched.label || String(matched.value);
                                                option.selected = true;
                                                select.appendChild(option);
                                            }
                                        })
                                )
                            ).then(() => {
                                // 所有值加载完成后，初始化 TomSelect
                                select._tsInstance = new window.TomSelect(select, config);
                            });
                            return; // 提前返回，等待异步加载完成
                        }
                    }
                }

                select._tsInstance = new window.TomSelect(select, config);
            });
        }

        loadRelationOptions(fieldName, keyword) {
            if (!this.endpoints.relationSearch) {
                return Promise.resolve([]);
            }

            const url = new URL(this.endpoints.relationSearch, window.location.origin);
            url.searchParams.set('field', fieldName);
            url.searchParams.set('search', keyword || '');
            url.searchParams.set('page', '1');
            url.searchParams.set('per_page', '20');

            return fetch(url.toString())
                .then((response) => response.json())
                .then((result) => {
                    if (result.code === 200 && result.data && Array.isArray(result.data.results)) {
                        return result.data.results.map((item) => ({
                            value: item.value,
                            text: item.text || item.label || item.value,
                        }));
                    }

                    return [];
                })
                .catch((error) => {
                    console.error('[UniversalFormRenderer] 关联选项加载失败', error);
                    return [];
                });
        }

        initializeSwitches() {
            const hiddenInputs = this.form.querySelectorAll('input[type="hidden"][data-switch-target]');

            hiddenInputs.forEach((hidden) => {
                const targetId = hidden.dataset.switchTarget;
                const checkbox = document.getElementById(targetId);
                if (!checkbox) {
                    return;
                }

                checkbox.addEventListener('change', () => {
                    hidden.value = checkbox.checked
                        ? hidden.dataset.switchOn
                        : hidden.dataset.switchOff;
                });
            });
        }

        initializeNumberRangeFields() {
            const ranges = this.form.querySelectorAll('[data-range-field]');

            ranges.forEach((rangeWrapper) => {
                const fieldName = rangeWrapper.dataset.rangeField;
                const hiddenInput = rangeWrapper.querySelector(`input[type="hidden"][name="${fieldName}"]`)
                    || rangeWrapper.querySelector('input[type="hidden"]');
                const minInput = rangeWrapper.querySelector('input[id$="_min"]');
                const maxInput = rangeWrapper.querySelector('input[id$="_max"]');

                if (!hiddenInput) {
                    return;
                }

                const updateValue = () => {
                    const range = {};
                    if (minInput && minInput.value !== '') {
                        range.min = parseFloat(minInput.value);
                    }
                    if (maxInput && maxInput.value !== '') {
                        range.max = parseFloat(maxInput.value);
                    }

                    hiddenInput.value = Object.keys(range).length ? JSON.stringify(range) : '';
                };

                if (minInput) {
                    minInput.addEventListener('input', updateValue);
                }
                if (maxInput) {
                    maxInput.addEventListener('input', updateValue);
                }
            });
        }

        initializeImageFields() {
            const wrappers = this.form.querySelectorAll('[data-universal-image]');

            wrappers.forEach((wrapper) => {
                const textInput = wrapper.querySelector('input[type="text"]');
                const uploadBtn = wrapper.querySelector('[data-action="upload"]');
                const previewBtn = wrapper.querySelector('[data-action="preview"]');
                const fileInput = wrapper.querySelector('input[data-role="image-input"]');
                const previewContainer = wrapper.querySelector('[data-preview]');

                if (!textInput || !uploadBtn || !fileInput) {
                    return;
                }

                if (textInput.value) {
                    this.updateSingleImagePreview(previewContainer, textInput.value);
                    if (previewBtn) {
                        previewBtn.disabled = false;
                    }
                }

                uploadBtn.addEventListener('click', () => fileInput.click());

                fileInput.addEventListener('change', async (event) => {
                    const file = event.target.files?.[0];
                    if (!file) {
                        return;
                    }

                    uploadBtn.disabled = true;
                    if (previewBtn) {
                        previewBtn.disabled = true;
                    }
                    this.updateSingleImagePreview(previewContainer, null, true);

                    try {
                        const url = await this.uploadFile(file);
                        textInput.value = url;
                        this.updateSingleImagePreview(previewContainer, url);
                        if (previewBtn) {
                            previewBtn.disabled = false;
                        }
                        this.notify('success', '图片上传成功');
                    } catch (error) {
                        console.error('[UniversalFormRenderer] 图片上传失败', error);
                        this.notify('danger', error.message || '图片上传失败');
                        this.updateSingleImagePreview(previewContainer, null);
                    } finally {
                        uploadBtn.disabled = false;
                    }
                });

                if (previewBtn) {
                    previewBtn.addEventListener('click', () => {
                        if (!textInput.value) {
                            return;
                        }
                        window.open(textInput.value, '_blank');
                    });
                }
            });
        }

        initializeMultiImageFields() {
            const wrappers = this.form.querySelectorAll('[data-universal-images]');

            wrappers.forEach((wrapper) => {
                const hiddenInput = wrapper.querySelector('input[type="hidden"]');
                const uploadBtn = wrapper.querySelector('[data-action="upload"]');
                const clearBtn = wrapper.querySelector('[data-action="clear"]');
                const fileInput = wrapper.querySelector('input[data-role="images-input"]');
                const previewContainer = wrapper.querySelector('[data-preview]');

                if (!hiddenInput || !uploadBtn || !fileInput || !previewContainer) {
                    return;
                }

                const images = this.normalizeArrayValue(hiddenInput.value);
                this.renderMultiImagePreview(previewContainer, images, hiddenInput);

                uploadBtn.addEventListener('click', () => fileInput.click());

                fileInput.addEventListener('change', async (event) => {
                    const files = Array.from(event.target.files || []);
                    if (!files.length) {
                        return;
                    }

                    uploadBtn.disabled = true;
                    clearBtn.disabled = true;

                    for (const file of files) {
                        try {
                            const url = await this.uploadFile(file);
                            images.push(url);
                            this.renderMultiImagePreview(previewContainer, images, hiddenInput);
                        } catch (error) {
                            console.error('[UniversalFormRenderer] 多图上传失败', error);
                            this.notify('danger', error.message || '图片上传失败');
                        }
                    }

                    uploadBtn.disabled = false;
                    clearBtn.disabled = false;
                    fileInput.value = '';
                });

                clearBtn.addEventListener('click', () => {
                    images.length = 0;
                    this.renderMultiImagePreview(previewContainer, images, hiddenInput);
                });

                previewContainer.addEventListener('click', (event) => {
                    const removeBtn = event.target.closest('[data-remove-index]');
                    if (!removeBtn) {
                        return;
                    }
                    const index = parseInt(removeBtn.dataset.removeIndex, 10);
                    if (Number.isNaN(index)) {
                        return;
                    }
                    images.splice(index, 1);
                    this.renderMultiImagePreview(previewContainer, images, hiddenInput);
                });
            });
        }

        initializeDateInputs() {
            if (typeof window.flatpickr !== 'function') {
                console.warn('[UniversalFormRenderer] Flatpickr 未加载，跳过日期选择器初始化');
                return;
            }

            const dateInputs = this.form.querySelectorAll('input[data-flatpickr-type]');

            dateInputs.forEach((input) => {
                // 如果已经初始化过，跳过
                if (input._flatpickr) {
                    return;
                }

                const dataType = input.dataset.flatpickrType || 'date';
                const isRequired = input.hasAttribute('required');
                const isDisabled = input.hasAttribute('disabled');
                const isReadonly = input.hasAttribute('readonly');

                // 根据类型配置 Flatpickr
                // 检查中文语言包是否已加载
                const hasZhLocale = window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.zh;
                
                let config = {
                    locale: hasZhLocale ? 'zh' : 'default',
                    allowInput: true,
                    clickOpens: !isReadonly,
                    disableMobile: false, // 在移动设备上也使用 Flatpickr
                };

                switch (dataType) {
                    case 'datetime':
                        config = {
                            ...config,
                            enableTime: true,
                            time_24hr: true,
                            dateFormat: 'Y-m-d H:i',
                            altInput: false,
                        };
                        break;
                    case 'time':
                        config = {
                            ...config,
                            enableTime: true,
                            noCalendar: true,
                            time_24hr: true,
                            dateFormat: 'H:i',
                            altInput: false,
                        };
                        break;
                    case 'date':
                    default:
                        config = {
                            ...config,
                            dateFormat: 'Y-m-d',
                            altInput: false,
                        };
                        break;
                }

                // 如果字段是必填的，添加验证
                if (isRequired) {
                    config.onChange = function(selectedDates, dateStr, instance) {
                        // 移除之前的验证样式
                        input.classList.remove('is-invalid');
                    };
                }

                // 初始化 Flatpickr
                try {
                    input._flatpickr = window.flatpickr(input, config);
                } catch (error) {
                    console.error('[UniversalFormRenderer] Flatpickr 初始化失败', error, input);
                }
            });
        }

        updateSingleImagePreview(container, url, isLoading) {
            if (!container) {
                return;
            }

            if (isLoading) {
                container.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>上传中...</div>';
                return;
            }

            if (!url) {
                container.innerHTML = '';
                return;
            }

            container.innerHTML = `
                <div class="border rounded p-2 d-inline-flex align-items-center gap-2">
                    <img src="${this.escapeAttr(url)}" alt="预览图片" style="width: 60px; height: 60px; object-fit: cover;">
                    <div class="text-break small">${this.escape(url)}</div>
                </div>
            `;
        }

        renderMultiImagePreview(container, images, hiddenInput) {
            if (!container) {
                return;
            }

            hiddenInput.value = JSON.stringify(images);

            if (!images.length) {
                container.innerHTML = '<div class="text-muted small">暂无图片</div>';
                return;
            }

            container.innerHTML = images
                .map(
                    (url, index) => `
                        <div class="position-relative border rounded" style="width: 110px; height: 110px;">
                            <img src="${this.escapeAttr(url)}" alt="图片${index + 1}" style="width: 100%; height: 100%; object-fit: cover;">
                            <button
                                type="button"
                                class="btn btn-sm btn-danger position-absolute top-0 end-0"
                                data-remove-index="${index}"
                                style="transform: translate(25%, -25%);"
                            >
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    `,
                )
                .join('');
        }

        async uploadFile(file) {
            if (!this.endpoints.uploadToken) {
                throw new Error('未配置上传接口');
            }

            if (!file.type.startsWith('image/')) {
                throw new Error('请选择图片文件');
            }

            if (file.size > 10 * 1024 * 1024) {
                throw new Error('图片大小不能超过 10MB');
            }

            const tokenResponse = await fetch(this.endpoints.uploadToken, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    filename: file.name,
                    content_type: file.type,
                    file_size: file.size,
                    sub_path: 'images',
                }),
            });

            const tokenResult = await tokenResponse.json();
            if (tokenResult.code !== 200 || !tokenResult.data) {
                throw new Error(tokenResult.msg || '获取上传令牌失败');
            }

            const tokenData = tokenResult.data;
            const uploadResponse = await fetch(tokenData.url, {
                method: tokenData.method || 'PUT',
                headers: Object.assign({}, tokenData.headers || {}, {
                    'Content-Type': file.type,
                }),
                body: file,
            });

            if (!uploadResponse.ok) {
                throw new Error('文件上传失败');
            }

            return tokenData.final_url || tokenData.url || '';
        }

        getColumnClass(field) {
            // 如果字段配置了自定义列宽，优先使用
            if (field.col) {
                return field.col;
            }

            const fieldType = (field.type || '').toLowerCase();

            // 图片类型（单图和多图）默认使用 col-12 col-md-6（每行2个字段）
            if (fieldType === 'image' || fieldType === 'images') {
                return 'col-12 col-md-6';
            }

            // 全宽字段类型
            const fullWidthTypes = ['textarea', 'rich_text', 'number_range', 'permission_tree', 'site_select'];
            if (fullWidthTypes.includes(fieldType)) {
                return 'col-12';
            }

            // switch 类型默认使用 col-12 col-md-3（每行4个字段）
            if (fieldType === 'switch') {
                return 'col-12 col-md-3';
            }

            // 默认布局：移动端全宽，中等屏幕及以上占 1/4（每行4个字段）
            return 'col-12 col-md-3';
        }

        getFieldId(field) {
            return this.escapeAttr(field.name || `field_${Math.random().toString(16).slice(2)}`);
        }

        getFieldValue(field) {
            if (typeof field.default === 'undefined' || field.default === null) {
                return '';
            }

            return field.default;
        }

        getOptions(field) {
            const rawOptions = field.options
                || (this.schema.relationOptions ? this.schema.relationOptions[field.name] : null)
                || [];

            if (Array.isArray(rawOptions)) {
                return rawOptions.map((item) => ({
                    value: String(item.value ?? item.id ?? ''),
                    label: String(item.label ?? item.name ?? item.value ?? ''),
                }));
            }

            if (typeof rawOptions === 'object' && rawOptions !== null) {
                return Object.keys(rawOptions).map((key) => ({
                    value: key,
                    label: String(rawOptions[key]),
                }));
            }

            return [];
        }

        normalizeArrayValue(value) {
            if (Array.isArray(value)) {
                return value.map((item) => String(item)).filter((item) => item !== '');
            }

            if (typeof value === 'string' && value) {
                try {
                    const parsed = JSON.parse(value);
                    if (Array.isArray(parsed)) {
                        return parsed.map((item) => String(item)).filter((item) => item !== '');
                    }
                } catch (error) {
                    // ignore
                }

                return [value];
            }

            return [];
        }

        parseRangeValue(value) {
            if (typeof value === 'string' && value) {
                try {
                    const parsed = JSON.parse(value);
                    if (typeof parsed === 'object' && parsed !== null) {
                        return {
                            min: typeof parsed.min !== 'undefined' ? parsed.min : '',
                            max: typeof parsed.max !== 'undefined' ? parsed.max : '',
                            raw: value,
                        };
                    }
                } catch (error) {
                    // ignore
                }
            }

            return { min: '', max: '', raw: '' };
        }

        isMultiple(field) {
            if (field.relation && typeof field.relation.multiple !== 'undefined') {
                return Boolean(field.relation.multiple);
            }

            if (typeof field.multiple !== 'undefined') {
                return Boolean(field.multiple);
            }

            return String(field.name || '').endsWith('_ids');
        }

        buildCommonAttributes(field) {
            const attrs = [];

            if (field.placeholder) {
                attrs.push(`placeholder="${this.escapeAttr(field.placeholder)}"`);
            }
            if (field.required) {
                attrs.push('required');
            }
            if (field.disabled) {
                attrs.push('disabled');
            }
            if (field.readonly) {
                attrs.push('readonly');
            }

            return attrs.join(' ');
        }

        initializePermissionTreeFields() {
            const wrappers = this.form.querySelectorAll('[data-permission-tree]');
            if (!wrappers.length) {
                return;
            }

            wrappers.forEach((wrapper) => {
                if (wrapper.dataset.permissionTreeInitialized === '1') {
                    return;
                }
                wrapper.dataset.permissionTreeInitialized = '1';

                const fieldName = wrapper.dataset.permissionTree;
                const root = wrapper.querySelector('.permission-tree-root');
                const summaryEl = wrapper.querySelector('[data-permission-tree-summary]');
                const selectedListEl = wrapper.querySelector('[data-permission-tree-selected-list]');
                const searchInput = wrapper.querySelector('[data-tree-search]');

                if (!root) {
                    return;
                }

                const getAllCheckboxes = () => Array.from(root.querySelectorAll('input.permission-tree-checkbox'));

                const updateAncestors = (startLi) => {
                    let current = startLi;
                    while (current) {
                        const parentLi = current.parentElement ? current.parentElement.closest('.permission-tree-node') : null;
                        if (!parentLi) {
                            break;
                        }

                        // 只能在当前节点内查找自身复选框，不能在 Element.querySelector 中使用以 > 开头的选择器
                        const parentCheckbox = parentLi.querySelector('.d-flex .permission-tree-checkbox');
                        if (!parentCheckbox) {
                            current = parentLi;
                            continue;
                        }

                        const descendantCheckboxes = parentLi.querySelectorAll('ul input.permission-tree-checkbox');
                        let total = 0;
                        let checkedCount = 0;
                        let indeterminateCount = 0;

                        descendantCheckboxes.forEach((cb) => {
                            total += 1;
                            if (cb.checked) {
                                checkedCount += 1;
                            }
                            if (cb.indeterminate) {
                                indeterminateCount += 1;
                            }
                        });

                        if (total === 0) {
                            parentCheckbox.checked = false;
                            parentCheckbox.indeterminate = false;
                        } else if (checkedCount === total && indeterminateCount === 0) {
                            parentCheckbox.checked = true;
                            parentCheckbox.indeterminate = false;
                        } else if (checkedCount === 0 && indeterminateCount === 0) {
                            parentCheckbox.checked = false;
                            parentCheckbox.indeterminate = false;
                        } else {
                            parentCheckbox.checked = false;
                            parentCheckbox.indeterminate = true;
                        }

                        current = parentLi;
                    }
                };

                const updateSummaryAndSelectedList = () => {
                    const allCbs = getAllCheckboxes();
                    const selectedCbs = allCbs.filter((cb) => cb.checked || cb.indeterminate);

                    if (summaryEl) {
                        summaryEl.textContent = `已选 ${selectedCbs.length} / ${allCbs.length} 项`;
                    }

                    if (selectedListEl) {
                        if (!selectedCbs.length) {
                            selectedListEl.innerHTML = '<div class="text-muted small">暂无已选权限</div>';
                            return;
                        }

                        const itemsHtml = selectedCbs
                            .map((cb) => {
                                const label = cb.dataset.label || cb.value;
                                return `<span class="badge permission-tree-selected-badge" title="${this.escapeAttr(label)}">
                                            ${this.escape(label)}
                                        </span>`;
                            })
                            .join('');
                        selectedListEl.innerHTML = itemsHtml;
                    }
                };

                const handleCheckboxChange = (checkbox) => {
                    const li = checkbox.closest('.permission-tree-node');
                    if (!li) {
                        return;
                    }

                    const isChecked = checkbox.checked;

                    // 子节点联动
                    const childCheckboxes = li.querySelectorAll('ul input.permission-tree-checkbox');
                    childCheckboxes.forEach((cb) => {
                        cb.checked = isChecked;
                        cb.indeterminate = false;
                    });

                    // 向上更新父节点半选/全选状态
                    updateAncestors(li);

                    // 更新右侧统计与已选列表
                    updateSummaryAndSelectedList();
                };

                root.addEventListener('change', (event) => {
                    const checkbox = event.target;
                    if (!checkbox.matches('input.permission-tree-checkbox')) {
                        return;
                    }
                    handleCheckboxChange(checkbox);
                });

                // 展开/收起
                root.addEventListener('click', (event) => {
                    const toggleBtn = event.target.closest('.permission-tree-toggle');
                    if (!toggleBtn) {
                        return;
                    }

                    const li = toggleBtn.closest('.permission-tree-node');
                    if (!li) {
                        return;
                    }

                    const childrenContainer = li.querySelector('.permission-tree-children');
                    if (!childrenContainer) {
                        return;
                    }

                    const isExpanded = toggleBtn.dataset.expanded !== '0';
                    childrenContainer.style.display = isExpanded ? 'none' : 'block';
                    toggleBtn.dataset.expanded = isExpanded ? '0' : '1';

                    const icon = toggleBtn.querySelector('i');
                    if (icon) {
                        icon.className = isExpanded ? 'bi bi-chevron-right' : 'bi bi-chevron-down';
                    }
                });

                // 工具栏按钮
                wrapper.addEventListener('click', (event) => {
                    const actionBtn = event.target.closest('[data-tree-action]');
                    if (!actionBtn) {
                        return;
                    }

                    const action = actionBtn.getAttribute('data-tree-action');
                    const allCbs = getAllCheckboxes();

                    if (action === 'expand-all' || action === 'collapse-all') {
                        const expand = action === 'expand-all';
                        root.querySelectorAll('.permission-tree-children').forEach((childrenContainer) => {
                            childrenContainer.style.display = expand ? 'block' : 'none';
                        });
                        root.querySelectorAll('.permission-tree-toggle').forEach((btn) => {
                            btn.dataset.expanded = expand ? '1' : '0';
                            const icon = btn.querySelector('i');
                            if (icon) {
                                icon.className = expand ? 'bi bi-chevron-down' : 'bi bi-chevron-right';
                            }
                        });
                    } else if (action === 'select-all' || action === 'clear-all') {
                        const check = action === 'select-all';
                        allCbs.forEach((cb) => {
                            cb.checked = check;
                            cb.indeterminate = false;
                        });

                        // 全选/全不选后，所有父节点状态也要同步
                        const allNodes = root.querySelectorAll('.permission-tree-node');
                        Array.from(allNodes).reverse().forEach((node) => {
                            updateAncestors(node);
                        });

                        updateSummaryAndSelectedList();
                    }
                });

                // 搜索过滤
                if (searchInput) {
                    searchInput.addEventListener('input', (event) => {
                        const keyword = (event.target.value || '').trim().toLowerCase();
                        const nodes = root.querySelectorAll('.permission-tree-node');

                        nodes.forEach((node) => {
                            const labelEl = node.querySelector('.form-check-label');
                            const metaEl = node.querySelector('.text-muted.small');
                            const textParts = [];
                            if (labelEl) {
                                textParts.push(labelEl.textContent || '');
                            }
                            if (metaEl) {
                                textParts.push(metaEl.textContent || '');
                            }
                            const text = textParts.join(' ').toLowerCase();

                            const visible = !keyword || text.includes(keyword);
                            node.style.display = visible ? '' : 'none';
                        });
                    });
                }

                // 初始统计
                updateSummaryAndSelectedList();

                // 初始化父节点半选状态（根据初始选中值）
                const allNodes = root.querySelectorAll('.permission-tree-node');
                Array.from(allNodes).reverse().forEach((node) => {
                    updateAncestors(node);
                });
            });
        }

        initializeSiteSelectors() {
            const wrappers = this.form.querySelectorAll('[data-site-selector]');
            if (!wrappers.length) {
                return;
            }

            if (!this.endpoints.siteOptions) {
                wrappers.forEach((wrapper) => {
                    this.renderSiteSelectorError(wrapper, '未配置站点接口');
                });
                return;
            }

            wrappers.forEach((wrapper) => this.setupSiteSelector(wrapper));
        }

        initializeKeyValueFields() {
            const wrappers = this.form.querySelectorAll('[data-key-value-field]');
            if (!wrappers.length) {
                return;
            }

            wrappers.forEach((wrapper) => this.setupKeyValueField(wrapper));
        }

        setupKeyValueField(wrapper) {
            const fieldName = wrapper.dataset.keyValueField;
            const hiddenInput = wrapper.querySelector('input[type="hidden"][data-key-value-hidden]');
            const pairsContainer = wrapper.querySelector('.key-value-pairs-container');
            const addBtn = wrapper.querySelector('.add-pair-btn');
            const removeBtns = wrapper.querySelectorAll('.remove-pair-btn');

            if (!hiddenInput || !pairsContainer || !addBtn) {
                return;
            }

            // 更新隐藏字段的值
            const updateHiddenValue = () => {
                const pairs = [];
                const pairElements = pairsContainer.querySelectorAll('.key-value-pair');
                
                pairElements.forEach((pairEl) => {
                    const keyInput = pairEl.querySelector('[data-key-value-key]');
                    const valueInput = pairEl.querySelector('[data-key-value-value]');
                    
                    if (keyInput && valueInput) {
                        const key = keyInput.value.trim();
                        const value = valueInput.value.trim();
                        
                        // 只添加非空的键值对
                        if (key || value) {
                            pairs.push({ key: key, value: value });
                        }
                    }
                });

                // 如果没有键值对，至少保留一个空对象
                if (pairs.length === 0) {
                    pairs.push({ key: '', value: '' });
                }

                hiddenInput.value = JSON.stringify(pairs);
            };

            // 添加键值对
            addBtn.addEventListener('click', () => {
                const pairIndex = pairsContainer.querySelectorAll('.key-value-pair').length;
                const pairHtml = `
                    <div class="key-value-pair mb-2" data-pair-index="${pairIndex}">
                        <div class="row g-2">
                            <div class="col-5">
                                <input
                                    type="text"
                                    class="form-control form-control-sm key-input"
                                    placeholder="键"
                                    value=""
                                    data-key-value-key
                                >
                            </div>
                            <div class="col-5">
                                <input
                                    type="text"
                                    class="form-control form-control-sm value-input"
                                    placeholder="值"
                                    value=""
                                    data-key-value-value
                                >
                            </div>
                            <div class="col-2">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger w-100 remove-pair-btn"
                                    title="删除"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = pairHtml;
                const newPair = tempDiv.firstElementChild;
                pairsContainer.appendChild(newPair);
                
                // 更新删除按钮状态
                updateRemoveButtonsState();
                
                // 绑定新行的删除按钮事件
                const newRemoveBtn = newPair.querySelector('.remove-pair-btn');
                if (newRemoveBtn) {
                    newRemoveBtn.addEventListener('click', () => {
                        newPair.remove();
                        updateRemoveButtonsState();
                        updateHiddenValue();
                    });
                }
                
                // 绑定新行的输入事件
                const keyInput = newPair.querySelector('[data-key-value-key]');
                const valueInput = newPair.querySelector('[data-key-value-value]');
                if (keyInput) {
                    keyInput.addEventListener('input', updateHiddenValue);
                }
                if (valueInput) {
                    valueInput.addEventListener('input', updateHiddenValue);
                }
                
                updateHiddenValue();
            });

            // 更新删除按钮状态（至少保留一行）
            const updateRemoveButtonsState = () => {
                const pairElements = pairsContainer.querySelectorAll('.key-value-pair');
                const removeBtns = pairsContainer.querySelectorAll('.remove-pair-btn');
                
                removeBtns.forEach((btn) => {
                    btn.disabled = pairElements.length <= 1;
                });
            };

            // 绑定删除按钮事件
            removeBtns.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const pairEl = btn.closest('.key-value-pair');
                    if (pairEl) {
                        const pairElements = pairsContainer.querySelectorAll('.key-value-pair');
                        if (pairElements.length > 1) {
                            pairEl.remove();
                            updateRemoveButtonsState();
                            updateHiddenValue();
                        }
                    }
                });
            });

            // 绑定输入事件
            const keyInputs = pairsContainer.querySelectorAll('[data-key-value-key]');
            const valueInputs = pairsContainer.querySelectorAll('[data-key-value-value]');
            
            keyInputs.forEach((input) => {
                input.addEventListener('input', updateHiddenValue);
            });
            
            valueInputs.forEach((input) => {
                input.addEventListener('input', updateHiddenValue);
            });

            // 初始化删除按钮状态
            updateRemoveButtonsState();
        }

        setupSiteSelector(wrapper) {
            const hiddenInput = wrapper.querySelector('input[type="hidden"][name]');
            const summaryEl = wrapper.querySelector('[data-site-selector-summary]');
            const searchInput = wrapper.querySelector('[data-site-selector-search]');
            const refreshBtn = wrapper.querySelector('[data-site-selector-refresh]');
            const clearBtn = wrapper.querySelector('[data-site-selector-clear]');
            const listEl = wrapper.querySelector('[data-site-selector-list]');
            const disabled = wrapper.dataset.siteSelectorDisabled === '1';

            if (!hiddenInput || !listEl) {
                return;
            }

            let requestToken = 0;
            let searchTimer = null;

            const applySelection = (optionElement) => {
                const selectedValue = optionElement ? (optionElement.dataset.siteOptionValue || '') : '';
                hiddenInput.value = selectedValue;
                this.highlightSiteSelection(wrapper, selectedValue);
                this.updateSiteSelectorSummary(summaryEl, optionElement ? optionElement.dataset : null, selectedValue);
                if (clearBtn) {
                    clearBtn.disabled = selectedValue === '';
                }
            };

            const loadOptions = (keyword = '') => {
                requestToken += 1;
                const currentToken = requestToken;
                this.toggleSiteSelectorLoading(wrapper, true);
                this.fetchSiteOptions(keyword)
                    .then((payload) => {
                        if (currentToken !== requestToken) {
                            return;
                        }
                        this.renderSiteSelectorOptions(wrapper, payload, hiddenInput.value);
                    })
                    .catch((error) => {
                        if (currentToken !== requestToken) {
                            return;
                        }
                        this.renderSiteSelectorError(wrapper, error.message || '加载站点失败');
                    })
                    .finally(() => {
                        if (currentToken === requestToken) {
                            this.toggleSiteSelectorLoading(wrapper, false);
                        }
                    });
            };

            if (!disabled) {
                wrapper.addEventListener('click', (event) => {
                    const option = event.target.closest('[data-site-option-value]');
                    if (!option || !wrapper.contains(option)) {
                        return;
                    }

                    if (hiddenInput.value === (option.dataset.siteOptionValue || '')) {
                        return;
                    }

                    applySelection(option);
                });

                wrapper.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }
                    const option = event.target.closest('[data-site-option-value]');
                    if (!option || !wrapper.contains(option)) {
                        return;
                    }
                    event.preventDefault();
                    applySelection(option);
                });
            }

            if (clearBtn && !disabled) {
                clearBtn.addEventListener('click', () => {
                    hiddenInput.value = '';
                    this.highlightSiteSelection(wrapper, '');
                    this.updateSiteSelectorSummary(summaryEl, null, '');
                    clearBtn.disabled = true;
                });
            }

            if (searchInput && !disabled) {
                const triggerSearch = () => {
                    const keyword = searchInput.value || '';
                    loadOptions(keyword);
                };

                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(triggerSearch, 400);
                });

                searchInput.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        clearTimeout(searchTimer);
                        triggerSearch();
                    }
                });
            }

            if (refreshBtn && !disabled) {
                refreshBtn.addEventListener('click', () => {
                    const keyword = searchInput ? (searchInput.value || '') : '';
                    loadOptions(keyword);
                });
            }

            loadOptions('');
        }

        fetchSiteOptions(keyword = '') {
            if (!this.endpoints.siteOptions) {
                return Promise.reject(new Error('未配置站点接口'));
            }

            const normalizedKeyword = (keyword || '').trim();
            const cacheKey = normalizedKeyword === '' ? '__all__' : null;
            if (cacheKey && this.siteOptionsCache.has(cacheKey)) {
                return this.siteOptionsCache.get(cacheKey);
            }

            let requestUrl;
            try {
                requestUrl = new URL(this.endpoints.siteOptions, window.location.origin);
            } catch (error) {
                requestUrl = null;
            }

            if (requestUrl) {
                if (normalizedKeyword) {
                    requestUrl.searchParams.set('keyword', normalizedKeyword);
                }
            }

            let finalUrl = this.endpoints.siteOptions;
            if (requestUrl) {
                finalUrl = requestUrl.toString();
            } else if (normalizedKeyword) {
                const separator = this.endpoints.siteOptions.includes('?') ? '&' : '?';
                finalUrl = `${this.endpoints.siteOptions}${separator}keyword=${encodeURIComponent(normalizedKeyword)}`;
            }

            const request = fetch(finalUrl)
                .then((response) => response.json())
                .then((result) => {
                    if (result.code === 200 && result.data) {
                        return result.data;
                    }
                    throw new Error(result.msg || '加载站点失败');
                });

            if (cacheKey) {
                this.siteOptionsCache.set(cacheKey, request);
            }

            return request;
        }

        renderSiteSelectorOptions(wrapper, payload, selectedValue) {
            const listEl = wrapper.querySelector('[data-site-selector-list]');
            const summaryEl = wrapper.querySelector('[data-site-selector-summary]');
            if (!listEl) {
                return;
            }

            const options = Array.isArray(payload?.options) ? payload.options : [];
            const normalizedSelected = selectedValue === null || typeof selectedValue === 'undefined'
                ? ''
                : String(selectedValue);

            if (!options.length) {
                listEl.innerHTML = `
                    <div class="col-12 text-center text-muted py-4">
                        <i class="bi bi-inbox me-1"></i> 暂无可用站点
                    </div>
                `;
                this.updateSiteSelectorSummary(summaryEl, null, normalizedSelected);
                this.highlightSiteSelection(wrapper, normalizedSelected);
                return;
            }

            listEl.innerHTML = options
                .map((option) => this.buildSiteOptionCard(option, normalizedSelected))
                .join('');

            const selectedCard = Array.from(wrapper.querySelectorAll('[data-site-option-value]'))
                .find((el) => String(el.dataset.siteOptionValue) === normalizedSelected);

            this.updateSiteSelectorSummary(summaryEl, selectedCard ? selectedCard.dataset : null, normalizedSelected);
            this.highlightSiteSelection(wrapper, normalizedSelected);
        }

        renderSiteSelectorError(wrapper, message) {
            const listEl = wrapper.querySelector('[data-site-selector-list]');
            if (!listEl) {
                return;
            }

            listEl.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>${this.escape(message)}
                    </div>
                </div>
            `;
        }

        highlightSiteSelection(wrapper, selectedValue) {
            const normalized = selectedValue === null || typeof selectedValue === 'undefined'
                ? ''
                : String(selectedValue);
            const options = wrapper.querySelectorAll('[data-site-option-value]');

            options.forEach((option) => {
                const isActive = normalized !== '' && String(option.dataset.siteOptionValue) === normalized;
                option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                const card = option.querySelector('.card');
                if (card) {
                    if (isActive) {
                        card.classList.add('border-primary', 'shadow-sm');
                        card.classList.remove('border-light');
                    } else {
                        card.classList.remove('border-primary', 'shadow-sm');
                        card.classList.add('border-light');
                    }
                }
            });
        }

        updateSiteSelectorSummary(summaryEl, dataset, fallbackValue) {
            if (!summaryEl) {
                return;
            }

            const normalized = fallbackValue === null || typeof fallbackValue === 'undefined'
                ? ''
                : String(fallbackValue);

            if (dataset && (dataset.siteOptionName || dataset.siteOptionTitle || dataset.siteOptionDomain)) {
                const name = dataset.siteOptionName || dataset.siteOptionTitle || (normalized ? `站点 #${normalized}` : '站点');
                const domain = dataset.siteOptionDomain ? `（${dataset.siteOptionDomain}）` : '';
                summaryEl.textContent = `当前选择：${name}${domain}`;
                summaryEl.classList.remove('text-muted');
                summaryEl.classList.add('text-success');
                return;
            }

            if (normalized) {
                summaryEl.textContent = `当前选择：站点 #${normalized}`;
                summaryEl.classList.remove('text-muted');
                summaryEl.classList.add('text-success');
                return;
            }

            summaryEl.textContent = '尚未选择站点';
            summaryEl.classList.add('text-muted');
            summaryEl.classList.remove('text-success');
        }

        toggleSiteSelectorLoading(wrapper, isLoading) {
            if (!wrapper) {
                return;
            }

            wrapper.dataset.siteSelectorLoading = isLoading ? '1' : '0';
            if (isLoading) {
                const listEl = wrapper.querySelector('[data-site-selector-list]');
                if (listEl) {
                    listEl.innerHTML = this.buildSiteSelectorLoading();
                }
            }
        }

        buildSiteOptionCard(option, selectedValue) {
            const value = String(option?.value ?? '');
            const normalizedSelected = selectedValue === null || typeof selectedValue === 'undefined'
                ? ''
                : String(selectedValue);
            const name = option?.name || option?.label || '';
            const title = option?.title || '';
            const domain = option?.domain || '';
            const entryPath = option?.entry_path || '';
            const slogan = option?.slogan || '';
            const createdAt = option?.created_at || '';
            const status = typeof option?.status === 'number' ? option.status : null;
            const statusText = option?.status_text || (status === 1 ? '启用' : '禁用');
            const isCurrent = Boolean(option?.is_current);
            const isSelected = normalizedSelected !== '' && normalizedSelected === value;
            const badgeClass = status === 1 ? 'bg-success' : 'bg-secondary';
            const displayName = name || title || (value ? `站点 #${value}` : '站点');

            return `
                <div
                    class="col-12 col-md-6 mb-2"
                    data-site-option-value="${this.escapeAttr(value)}"
                    data-site-option-name="${this.escapeAttr(displayName)}"
                    data-site-option-domain="${this.escapeAttr(domain)}"
                    data-site-option-title="${this.escapeAttr(title)}"
                    role="button"
                    tabindex="0"
                    aria-pressed="${isSelected ? 'true' : 'false'}"
                >
                    <div class="card site-option-card border ${isSelected ? 'border-primary shadow-sm' : 'border-light'} h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-semibold">${this.escape(displayName)}</div>
                                    ${title && title !== displayName ? `<div class="text-muted small">${this.escape(title)}</div>` : ''}
                                </div>
                                <div class="text-end">
                                    <span class="badge ${badgeClass}">${this.escape(statusText)}</span>
                                    ${isCurrent ? '<span class="badge bg-info ms-1">当前站点</span>' : ''}
                                </div>
                            </div>
                            ${domain ? `<div class="text-muted small mb-1"><i class="bi bi-globe me-1"></i>${this.escape(domain)}</div>` : ''}
                            ${entryPath ? `<div class="text-muted small mb-1"><i class="bi bi-box-arrow-in-right me-1"></i>${this.escape(entryPath)}</div>` : ''}
                            ${slogan ? `<div class="text-muted small mb-1"><i class="bi bi-chat-quote me-1"></i>${this.escape(slogan)}</div>` : ''}
                            ${createdAt ? `<div class="text-muted small"><i class="bi bi-clock me-1"></i>${this.escape(createdAt)}</div>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        escape(value) {
            if (value === null || typeof value === 'undefined') {
                return '';
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        escapeAttr(value) {
            if (value === null || typeof value === 'undefined') {
                return '';
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        notify(type, message) {
            if (typeof window.showToast === 'function') {
                window.showToast(type, message);
            } else {
                // eslint-disable-next-line no-alert
                alert(message);
            }
        }
    }

    window.UniversalFormRenderer = UniversalFormRenderer;
})(window, document);

