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

            this.renderFields();
            this.attachFormSubmit();
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

            Object.keys(data).forEach((key) => {
                if (key.endsWith('_ids') && Array.isArray(data[key])) {
                    data[key] = JSON.stringify(data[key]);
                }
            });

            return data;
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
                default:
                    return this.renderTextInput(field, 'text');
            }
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
            if (!options.length) {
                return '<div class="text-muted small">未配置选项</div>';
            }

            const currentValue = this.normalizeArrayValue(this.getFieldValue(field));

            return `
                <div class="d-flex flex-wrap gap-3">
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

        initializeEnhancements() {
            this.initializeSelects();
            this.initializeSwitches();
            this.initializeNumberRangeFields();
            this.initializeImageFields();
            this.initializeMultiImageFields();
            this.initializeDateInputs();
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
            const fullWidthTypes = ['textarea', 'rich_text', 'number_range'];
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

