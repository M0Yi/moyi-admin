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

            fetch(this.schema.submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            })
                .then((response) => response.json())
                .then((result) => {
                    if (result.code === 200) {
                        this.notify('success', result.msg || '创建成功');
                        const redirectUrl = this.schema.redirectUrl || this.schema.submitUrl;
                        setTimeout(() => {
                            window.location.href = redirectUrl;
                        }, 800);
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

        buildFieldMarkup(field = {}) {
            const name = field.name || `field_${Math.random().toString(16).slice(2)}`;
            const label = field.label || name;
            const requiredMark = field.required ? ' <span class="text-danger">*</span>' : '';
            const helpText = field.help ? `<div class="form-text">${this.escape(field.help)}</div>` : '';
            const controlHtml = this.renderFieldControl(field);

            const showLabel = field.type !== 'switch';
            const labelHtml = showLabel
                ? `<label class="form-label" for="${this.escapeAttr(this.getFieldId(field))}">
                        ${this.escape(label)}${requiredMark}
                   </label>`
                : '';

            return `
                <div class="universal-form-field mb-3" data-field-name="${this.escapeAttr(name)}">
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
                : [currentValue];
            const hasOptions = options.length > 0;
            const isAsync = isRelation && !hasOptions;
            const placeholder = field.placeholder || `请选择${field.label ?? ''}`;

            const optionsHtml = hasOptions
                ? options
                      .map((option) => {
                          const selected = selectedValues.includes(option.value) ? 'selected' : '';
                          return `<option value="${this.escapeAttr(option.value)}" ${selected}>
                                    ${this.escape(option.label)}
                                  </option>`;
                      })
                      .join('')
                : this.renderSelectedPlaceholders(selectedValues);

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
                    ${!multiple ? '<option value="">请选择</option>' : ''}
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
            const supportedType = subtype === 'datetime' ? 'datetime-local' : subtype;

            return `
                <input
                    type="${this.escapeAttr(supportedType)}"
                    class="form-control"
                    id="${id}"
                    name="${this.escapeAttr(field.name)}"
                    value="${this.escapeAttr(value)}"
                    ${this.buildCommonAttributes(field)}
                >
            `;
        }

        renderSwitch(field) {
            const id = `${this.getFieldId(field)}_switch`;
            const currentValue = this.getFieldValue(field);
            const onValue = field.onValue ?? '1';
            const offValue = field.offValue ?? '0';
            const checked = currentValue === '' ? field.default === onValue : currentValue === onValue;
            const label = field.label || '';

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
                    <label class="form-check-label" for="${id}">
                        ${this.escape(label)}
                    </label>
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
            const fullWidthTypes = ['textarea', 'rich_text', 'number_range', 'images'];
            if (fullWidthTypes.includes((field.type || '').toLowerCase())) {
                return 'col-12';
            }

            return 'col-12 col-md-6';
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

