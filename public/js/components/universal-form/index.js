/**
 * UniversalForm 通用表单渲染引擎
 * 
 * 这是一个纯 JS 表单渲染引擎，通过配置自动生成表单。
 * 
 * 特性：
 * - 纯 JS 渲染，无需 Blade 组件
 * - 支持 10+ 种字段类型
 * - 内置表单验证
 * - 自动处理 $http 请求
 * - 与 $toast, $loading, $modal 无缝集成
 * 
 * 使用方法：
 * const form = UniversalForm.create({
 *     api: '/api/admin/users',
 *     method: 'POST',
 *     fields: [
 *         { name: 'username', type: 'text', label: '用户名', required: true },
 *         { name: 'password', type: 'password', label: '密码', required: true },
 *         { name: 'email', type: 'email', label: '邮箱' },
 *         { name: 'status', type: 'select', label: '状态', options: [...] },
 *         { name: 'roles', type: 'checkbox', label: '角色', options: [...] },
 *         { name: 'remark', type: 'textarea', label: '备注' }
 *     ],
 *     onSuccess: (data) => { $toast.success('保存成功！'); }
 * });
 * form.mount('#form-container');
 * form.open(); // 如果在弹窗中使用
 */

(function(global) {
    'use strict';

    /**
     * 表单字段类型定义
     */
    const FIELD_TYPES = {
        text: 'text',
        password: 'password',
        email: 'email',
        number: 'number',
        tel: 'tel',
        url: 'url',
        date: 'date',
        datetime: 'datetime-local',
        time: 'time',
        textarea: 'textarea',
        select: 'select',
        multiselect: 'multiselect',
        checkbox: 'checkbox',
        radio: 'radio',
        switch: 'switch',
        file: 'file',
        image: 'image',
        hidden: 'hidden',
        color: 'color'
    };

    /**
     * 验证规则
     */
    const VALIDATORS = {
        required: (value, field) => {
            if (field.type === 'checkbox' || field.type === 'multiselect') {
                return value && value.length > 0;
            }
            return value !== null && value !== undefined && value !== '';
        },
        email: (value) => {
            if (!value) return true;
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        },
        mobile: (value) => {
            if (!value) return true;
            return /^1[3-9]\d{9}$/.test(value);
        },
        min: (value, field) => {
            if (!value) return true;
            if (field.type === 'number') {
                return parseFloat(value) >= field.min;
            }
            if (field.type === 'text' || field.type === 'textarea') {
                return String(value).length >= field.min;
            }
            return true;
        },
        max: (value, field) => {
            if (!value) return true;
            if (field.type === 'number') {
                return parseFloat(value) <= field.max;
            }
            if (field.type === 'text' || field.type === 'textarea') {
                return String(value).length <= field.max;
            }
            return true;
        },
        pattern: (value, field) => {
            if (!value || !field.pattern) return true;
            return new RegExp(field.pattern).test(value);
        }
    };

    /**
     * UniversalForm 类
     */
    class UniversalForm {
        /**
         * 创建表单实例
         */
        static create(options) {
            return new UniversalForm(options);
        }

        /**
         * 构造函数
         */
        constructor(options) {
            this.options = {
                // 基础配置
                api: options.api || '',
                method: options.method || 'POST',
                id: options.id || 'universal-form-' + Date.now(),
                
                // 字段配置
                fields: options.fields || [],
                layout: options.layout || 'vertical', // vertical, horizontal
                labelWidth: options.labelWidth || '100px',
                
                // 按钮配置
                showSubmit: options.showSubmit !== false,
                showReset: options.showReset !== false,
                submitText: options.submitText || '保存',
                resetText: options.resetText || '重置',
                
                // 回调函数
                onSubmit: options.onSubmit || null,
                onSuccess: options.onSuccess || null,
                onError: options.onError || null,
                onReset: options.onReset || null,
                onChange: options.onChange || null,
                
                // 数据加载
                data: options.data || null,
                
                // 额外属性
                ...options
            };

            // 状态
            this.formData = {};
            this.originalData = {};
            this.errors = {};
            this.loading = false;
            this.submitted = false;

            // DOM 引用
            this.container = null;
            this.formElement = null;
            
            // 初始化
            this._init();
        }

        /**
         * 初始化
         */
        _init() {
            // 初始化表单数据
            this.options.fields.forEach(field => {
                this.formData[field.name] = this._getDefaultValue(field);
            });

            // 备份原始数据
            this.originalData = JSON.parse(JSON.stringify(this.formData));

            // 绑定方法
            this._handleSubmit = this._handleSubmit.bind(this);
            this._handleReset = this._handleReset.bind(this);
            this._handleChange = this._handleChange.bind(this);
        }

        /**
         * 获取字段默认值
         */
        _getDefaultValue(field) {
            if (field.default !== undefined) {
                return field.default;
            }
            
            switch (field.type) {
                case 'checkbox':
                case 'multiselect':
                    return [];
                case 'radio':
                case 'select':
                    return '';
                default:
                    return '';
            }
        }

        /**
         * 获取字段值（考虑 checkbox 等特殊情况）
         */
        _getFieldValue(name, type, element) {
            switch (type) {
                case 'checkbox':
                    const checkedBoxes = element.querySelectorAll(`input[name="${name}"]:checked`);
                    return Array.from(checkedBoxes).map(box => box.value);
                
                case 'radio':
                    const checkedRadio = element.querySelector(`input[name="${name}"]:checked`);
                    return checkedRadio ? checkedRadio.value : '';
                
                case 'switch':
                    const switchInput = element.querySelector(`input[name="${name}"]`);
                    return switchInput && switchInput.checked ? (element.dataset.trueValue || '1') : (element.dataset.falseValue || '0');
                
                case 'file':
                    const fileInput = element.querySelector(`input[name="${name}"]`);
                    return fileInput ? fileInput.files : null;
                
                case 'number':
                    const numInput = element.querySelector(`input[name="${name}"]`);
                    return numInput ? parseFloat(numInput.value) || 0 : 0;
                
                default:
                    const input = element.querySelector(`input[name="${name}"], textarea[name="${name}"], select[name="${name}"]`);
                    return input ? input.value : '';
            }
        }

        /**
         * 设置字段值
         */
        setValue(name, value) {
            this.formData[name] = value;
            this._updateFieldValue(name, value);
        }

        /**
         * 批量设置值
         */
        setValues(data) {
            Object.keys(data).forEach(key => {
                this.formData[key] = data[key];
            });
            this._renderValues();
        }

        /**
         * 更新单个字段的显示值
         */
        _updateFieldValue(name, value) {
            if (!this.container) return;
            
            const field = this.options.fields.find(f => f.name === name);
            if (!field) return;

            const element = this.container.querySelector(`[data-field="${name}"]`);
            if (!element) return;

            switch (field.type) {
                case 'checkbox':
                case 'multiselect':
                    const checkboxes = element.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(box => {
                        box.checked = value.includes(box.value);
                    });
                    break;
                
                case 'radio':
                    const radio = element.querySelector(`input[value="${value}"]`);
                    if (radio) radio.checked = true;
                    break;
                
                case 'switch':
                    const switchInput = element.querySelector('input[type="checkbox"]');
                    if (switchInput) {
                        switchInput.checked = value == (element.dataset.trueValue || '1');
                    }
                    break;
                
                case 'select':
                case 'multiselect':
                    const select = element.querySelector('select');
                    if (select) {
                        if (Array.isArray(value)) {
                            Array.from(select.options).forEach(opt => {
                                opt.selected = value.includes(opt.value);
                            });
                        } else {
                            select.value = value;
                        }
                    }
                    break;
                
                default:
                    const input = element.querySelector('input, textarea');
                    if (input) input.value = value;
            }

            // 清除错误
            this._clearFieldError(name);
        }

        /**
         * 渲染所有值
         */
        _renderValues() {
            Object.keys(this.formData).forEach(name => {
                this._updateFieldValue(name, this.formData[name]);
            });
        }

        /**
         * 获取表单数据
         */
        getData() {
            return { ...this.formData };
        }

        /**
         * 重置表单
         */
        reset() {
            this.formData = JSON.parse(JSON.stringify(this.originalData));
            this.errors = {};
            this._renderValues();
            this._clearAllErrors();
            
            if (this.options.onReset) {
                this.options.onReset();
            }
        }

        /**
         * 清空表单
         */
        clear() {
            this.options.fields.forEach(field => {
                this.formData[field.name] = this._getDefaultValue(field);
            });
            this.errors = {};
            this._renderValues();
            this._clearAllErrors();
        }

        /**
         * 渲染表单 HTML
         */
        _render() {
            let html = '';

            // 开始表单
            html += `<form id="${this.options.id}" class="universal-form" data-layout="${this.options.layout}">`;
            
            // 字段渲染
            this.options.fields.forEach(field => {
                html += this._renderField(field);
            });

            // 按钮组
            if (this.options.showSubmit || this.options.showReset) {
                html += '<div class="form-buttons">';
                if (this.options.showSubmit) {
                    html += `<button type="submit" class="btn btn-primary" ${this.loading ? 'disabled' : ''}>`;
                    html += `<span class="btn-text">${this.options.submitText}</span>`;
                    html += '</button>';
                }
                if (this.options.showReset) {
                    html += `<button type="button" class="btn btn-secondary" onclick="${this.options.id}.reset()">`;
                    html += this.options.resetText;
                    html += '</button>';
                }
                html += '</div>';
            }

            // 结束表单
            html += '</form>';

            return html;
        }

        /**
         * 渲染单个字段
         */
        _renderField(field) {
            // 处理分组
            if (field.type === 'group') {
                return this._renderFieldGroup(field);
            }

            // 处理分隔符
            if (field.type === 'divider') {
                return '<hr class="form-divider">';
            }

            // 处理标题
            if (field.type === 'title') {
                return `<h5 class="form-section-title">${field.label}</h5>`;
            }

            // 基础属性
            const id = `${this.options.id}-${field.name}`;
            const required = field.required ? '<span class="text-danger">*</span>' : '';
            const disabled = field.disabled ? 'disabled' : '';
            const readonly = field.readonly ? 'readonly' : '';
            const help = field.help ? `<div class="form-text">${field.help}</div>` : '';
            const errorClass = this.errors[field.name] ? 'is-invalid' : '';
            const errorMsg = this.errors[field.name] 
                ? `<div class="invalid-feedback">${this.errors[field.name]}</div>` 
                : '';

            let inputHtml = '';

            // 布局类
            const labelClass = this.options.layout === 'horizontal' ? 'col-form-label' : '';
            const wrapperClass = this.options.layout === 'horizontal' ? 'col-sm-9' : '';

            switch (field.type) {
                case 'hidden':
                    inputHtml = `<input type="hidden" name="${field.name}" id="${id}" value="${this.formData[field.name] || ''}">`;
                    return inputHtml;

                case 'text':
                case 'email':
                case 'password':
                case 'number':
                case 'tel':
                case 'url':
                case 'date':
                case 'datetime':
                case 'time':
                case 'color':
                    inputHtml = this._renderInput(field, id, errorClass, disabled, readonly);
                    break;

                case 'textarea':
                    inputHtml = this._renderTextarea(field, id, errorClass, disabled, readonly);
                    break;

                case 'select':
                    inputHtml = this._renderSelect(field, id, errorClass, disabled);
                    break;

                case 'multiselect':
                    inputHtml = this._renderMultiselect(field, id, errorClass, disabled);
                    break;

                case 'checkbox':
                    inputHtml = this._renderCheckbox(field, id, errorClass, disabled);
                    break;

                case 'radio':
                    inputHtml = this._renderRadio(field, id, errorClass, disabled);
                    break;

                case 'switch':
                    inputHtml = this._renderSwitch(field, id, errorClass, disabled);
                    break;

                case 'file':
                    inputHtml = this._renderFile(field, id, errorClass, disabled);
                    break;

                case 'image':
                    inputHtml = this._renderImage(field, id, errorClass, disabled);
                    break;

                default:
                    inputHtml = this._renderInput(field, id, errorClass, disabled, readonly);
            }

            // 组装完整字段 HTML
            let fieldHtml = `<div class="form-group" data-field="${field.name}">`;

            if (field.type !== 'hidden') {
                if (this.options.layout === 'horizontal') {
                    fieldHtml += `<label class="${labelClass}" for="${id}">${field.label}${required}</label>`;
                    fieldHtml += `<div class="${wrapperClass}">`;
                    fieldHtml += inputHtml;
                    fieldHtml += errorMsg;
                    fieldHtml += help;
                    fieldHtml += '</div>';
                } else {
                    fieldHtml += `<label class="form-label" for="${id}">${field.label}${required}</label>`;
                    fieldHtml += inputHtml;
                    fieldHtml += errorMsg;
                    fieldHtml += help;
                }
            } else {
                fieldHtml += inputHtml;
            }

            fieldHtml += '</div>';

            return fieldHtml;
        }

        /**
         * 渲染输入框
         */
        _renderInput(field, id, errorClass, disabled, readonly) {
            const value = this.formData[field.name] || '';
            const placeholder = field.placeholder || '';
            const step = field.step || 'any';
            const min = field.min !== undefined ? `min="${field.min}"` : '';
            const max = field.max !== undefined ? `max="${field.max}"` : '';
            
            return `<input 
                type="${field.type}" 
                name="${field.name}" 
                id="${id}" 
                class="form-control ${errorClass}" 
                value="${value}" 
                placeholder="${placeholder}"
                ${disabled}
                ${readonly}
                ${field.type === 'number' ? step : ''}
                ${min}
                ${max}
                data-field="${field.name}"
            >`;
        }

        /**
         * 渲染文本域
         */
        _renderTextarea(field, id, errorClass, disabled, readonly) {
            const value = this.formData[field.name] || '';
            const placeholder = field.placeholder || '';
            const rows = field.rows || 3;

            return `<textarea 
                name="${field.name}" 
                id="${id}" 
                class="form-control ${errorClass}" 
                rows="${rows}" 
                placeholder="${placeholder}"
                ${disabled}
                ${readonly}
                data-field="${field.name}"
            >${value}</textarea>`;
        }

        /**
         * 渲染下拉选择框
         */
        _renderSelect(field, id, errorClass, disabled) {
            const options = field.options || [];
            const placeholder = field.placeholder || '请选择';
            const value = this.formData[field.name] || '';

            let html = `<select name="${field.name}" id="${id}" class="form-select ${errorClass}" ${disabled} data-field="${field.name}">`;
            
            if (placeholder) {
                html += `<option value="">${placeholder}</option>`;
            }

            options.forEach(opt => {
                const selected = value == opt.value ? 'selected' : '';
                html += `<option value="${opt.value}" ${selected}>${opt.label}</option>`;
            });

            html += '</select>';
            return html;
        }

        /**
         * 渲染多选下拉框
         */
        _renderMultiselect(field, id, errorClass, disabled) {
            const options = field.options || [];
            const values = this.formData[field.name] || [];

            let html = `<select name="${field.name}" id="${id}" class="form-select ${errorClass}" multiple ${disabled} data-field="${field.name}">`;

            options.forEach(opt => {
                const selected = values.includes(opt.value) ? 'selected' : '';
                html += `<option value="${opt.value}" ${selected}>${opt.label}</option>`;
            });

            html += '</select>';
            return html;
        }

        /**
         * 渲染复选框组
         */
        _renderCheckbox(field, id, errorClass, disabled) {
            const options = field.options || [];
            const values = this.formData[field.name] || [];
            const inline = field.inline !== false;

            let html = `<div class="${inline ? 'd-flex gap-3' : ''}">`;

            options.forEach((opt, index) => {
                const checkboxId = `${id}-${index}`;
                const checked = values.includes(opt.value) ? 'checked' : '';
                
                html += `<div class="form-check">`;
                html += `<input type="checkbox" name="${field.name}[]" id="${checkboxId}" value="${opt.value}" class="form-check-input ${errorClass}" ${checked} ${disabled} data-field="${field.name}">`;
                html += `<label class="form-check-label" for="${checkboxId}">${opt.label}</label>`;
                html += `</div>`;
            });

            html += '</div>';
            return html;
        }

        /**
         * 渲染单选框组
         */
        _renderRadio(field, id, errorClass, disabled) {
            const options = field.options || [];
            const value = this.formData[field.name] || '';
            const inline = field.inline !== false;

            let html = `<div class="${inline ? 'd-flex gap-3' : ''}">`;

            options.forEach((opt, index) => {
                const radioId = `${id}-${index}`;
                const checked = value == opt.value ? 'checked' : '';
                
                html += `<div class="form-check">`;
                html += `<input type="radio" name="${field.name}" id="${radioId}" value="${opt.value}" class="form-check-input ${errorClass}" ${checked} ${disabled} data-field="${field.name}">`;
                html += `<label class="form-check-label" for="${radioId}">${opt.label}</label>`;
                html += `</div>`;
            });

            html += '</div>';
            return html;
        }

        /**
         * 渲染开关
         */
        _renderSwitch(field, id, errorClass, disabled) {
            const value = this.formData[field.name] || '0';
            const trueValue = field.trueValue || '1';
            const falseValue = field.falseValue || '0';
            const checked = value == trueValue ? 'checked' : '';
            const size = field.size || '';

            return `<div class="form-check form-switch ${size}">
                <input type="checkbox" name="${field.name}_switch" id="${id}" class="form-check-input ${errorClass}" ${checked} ${disabled} 
                    data-field="${field.name}" 
                    data-true-value="${trueValue}" 
                    data-false-value="${falseValue}"
                    role="switch">
                <input type="hidden" name="${field.name}" value="${value}" data-field="${field.name}">
            </div>`;
        }

        /**
         * 渲染文件上传
         */
        _renderFile(field, id, errorClass, disabled) {
            const accept = field.accept || '*';
            const maxSize = field.maxSize ? `data-max-size="${field.maxSize}"` : '';

            return `<input 
                type="file" 
                name="${field.name}" 
                id="${id}" 
                class="form-control ${errorClass}" 
                accept="${accept}"
                ${disabled}
                ${maxSize}
                data-field="${field.name}"
            >`;
        }

        /**
         * 渲染图片上传
         */
        _renderImage(field, id, errorClass, disabled) {
            const accept = field.accept || 'image/*';
            const maxSize = field.maxSize || 5;
            const maxWidth = field.maxWidth ? `data-max-width="${field.maxWidth}"` : '';
            const maxHeight = field.maxHeight ? `data-max-height="${field.maxHeight}"` : '';

            let html = `<input 
                type="file" 
                name="${field.name}" 
                id="${id}" 
                class="form-control ${errorClass}" 
                accept="${accept}"
                ${disabled}
                data-max-size="${maxSize}"
                ${maxWidth}
                ${maxHeight}
                data-field="${field.name}"
                data-image-preview="true"
            >`;

            // 预览容器
            if (field.showPreview !== false) {
                const previewId = `${id}-preview`;
                html += `<div id="${previewId}" class="image-preview mt-2" style="display: none;">`;
                html += `<img src="" alt="预览" class="img-thumbnail" style="max-width: 200px;">`;
                html += '</div>';
            }

            return html;
        }

        /**
         * 渲染字段分组
         */
        _renderFieldGroup(field) {
            const title = field.label ? `<h5 class="form-group-title">${field.label}</h5>` : '';
            const fields = field.fields.map(f => this._renderField(f)).join('');
            
            return `<div class="form-group-section">
                ${title}
                ${fields}
            </div>`;
        }

        /**
         * 挂载到 DOM
         */
        mount(selector) {
            this.container = typeof selector === 'string' 
                ? document.querySelector(selector) 
                : selector;

            if (!this.container) {
                console.error(`UniversalForm: Container "${selector}" not found`);
                return this;
            }

            // 渲染表单
            this.container.innerHTML = this._render();
            this.formElement = this.container.querySelector('form');

            // 绑定事件
            this._bindEvents();

            // 如果有初始数据，填充表单
            if (this.options.data) {
                this.setValues(this.options.data);
            }

            // 将实例暴露到全局
            window[this.options.id] = this;

            return this;
        }

        /**
         * 绑定事件
         */
        _bindEvents() {
            if (!this.formElement) return;

            // 表单提交
            this.formElement.addEventListener('submit', (e) => {
                e.preventDefault();
                this._handleSubmit();
            });

            // 字段变更事件（事件委托）
            this.formElement.addEventListener('change', (e) => {
                if (e.target.dataset.field) {
                    const value = this._getFieldValue(
                        e.target.dataset.field,
                        e.target.type || this._getFieldType(e.target.dataset.field),
                        e.target.closest('[data-field]')
                    );
                    this._handleChange(e.target.dataset.field, value);
                }
            });

            // 输入事件（实时验证）
            this.formElement.addEventListener('input', (e) => {
                if (e.target.dataset.field) {
                    const value = e.target.type === 'checkbox' 
                        ? this._getFieldValue(e.target.dataset.field, 'checkbox', e.target.closest('[data-field]'))
                        : e.target.value;
                    this._handleChange(e.target.dataset.field, value);
                }
            });
        }

        /**
         * 获取字段类型
         */
        _getFieldType(name) {
            const field = this.options.fields.find(f => f.name === name);
            return field ? field.type : 'text';
        }

        /**
         * 处理字段变更
         */
        _handleChange(name, value) {
            this.formData[name] = value;
            
            // 清除错误
            this._clearFieldError(name);

            // 触发回调
            if (this.options.onChange) {
                this.options.onChange(name, value, this.formData);
            }
        }

        /**
         * 处理表单提交
         */
        async _handleSubmit() {
            if (this.loading) return;
            
            // 收集表单数据
            this.options.fields.forEach(field => {
                if (field.type !== 'hidden') {
                    const element = this.container.querySelector(`[data-field="${field.name}"]`);
                    if (element) {
                        this.formData[field.name] = this._getFieldValue(field.name, field.type, element);
                    }
                }
            });

            // 验证表单
            const errors = this._validate();
            if (Object.keys(errors).length > 0) {
                this.errors = errors;
                this._renderErrors();
                
                if (this.options.onError) {
                    this.options.onError({ message: '表单验证失败', errors: errors });
                }
                return;
            }

            this.loading = true;
            this._setLoading(true);

            try {
                // 预处理数据
                let data = { ...this.formData };
                if (this.options.onSubmit) {
                    data = await this.options.onSubmit(data);
                }

                // 发送请求
                const method = this.options.method.toLowerCase();
                let result;

                if (method === 'post') {
                    result = await $http.post(this.options.api, data);
                } else if (method === 'put') {
                    result = await $http.put(this.options.api, data);
                } else if (method === 'patch') {
                    result = await $http.patch(this.options.api, data);
                } else if (method === 'delete') {
                    result = await $http.delete(this.options.api);
                } else {
                    result = await $http.request({
                        method: this.options.method,
                        url: this.options.api,
                        data: data,
                    });
                }

                // 成功回调
                if (this.options.onSuccess) {
                    this.options.onSuccess(result);
                }

                // 更新原始数据
                this.originalData = JSON.parse(JSON.stringify(this.formData));
                this.submitted = true;

            } catch (error) {
                console.error('UniversalForm Error:', error);
                
                // 处理字段错误
                if (error.code === 422 && error.errors) {
                    this.errors = error.errors;
                    this._renderErrors();
                }

                if (this.options.onError) {
                    this.options.onError(error);
                } else {
                    $toast.error(error.message || error.msg || '操作失败！');
                }
            } finally {
                this.loading = false;
                this._setLoading(false);
            }
        }

        /**
         * 表单验证
         */
        _validate() {
            const errors = {};

            this.options.fields.forEach(field => {
                if (field.type === 'hidden' || field.type === 'divider' || field.type === 'title') {
                    return;
                }

                const value = this.formData[field.name];

                // 必填验证
                if (field.required) {
                    let isEmpty = false;
                    
                    if (field.type === 'checkbox' || field.type === 'multiselect') {
                        isEmpty = !value || value.length === 0;
                    } else if (field.type === 'select' || field.type === 'multiselect') {
                        isEmpty = !value || value === '';
                    } else {
                        isEmpty = !value;
                    }

                    if (isEmpty) {
                        errors[field.name] = field.requiredMessage || `${field.label}不能为空`;
                        return;
                    }
                }

                // 跳过空值的其他验证
                if (!value && field.type !== 'checkbox' && field.type !== 'multiselect') {
                    return;
                }

                // 邮箱验证
                if (field.type === 'email' && !VALIDATORS.email(value)) {
                    errors[field.name] = field.emailMessage || '邮箱格式不正确';
                    return;
                }

                // 手机号验证
                if (field.type === 'tel' && !VALIDATORS.mobile(value)) {
                    errors[field.name] = field.mobileMessage || '手机号格式不正确';
                    return;
                }

                // 最小值/长度验证
                if (field.min !== undefined) {
                    if (!VALIDATORS.min(value, field)) {
                        const msg = field.minMessage || `${field.label}不能小于${field.min}`;
                        errors[field.name] = msg;
                        return;
                    }
                }

                // 最大值/长度验证
                if (field.max !== undefined) {
                    if (!VALIDATORS.max(value, field)) {
                        const msg = field.maxMessage || `${field.label}不能大于${field.max}`;
                        errors[field.name] = msg;
                        return;
                    }
                }

                // 自定义正则
                if (field.pattern && !VALIDATORS.pattern(value, field)) {
                    errors[field.name] = field.patternMessage || `${field.label}格式不正确`;
                    return;
                }

                // 自定义验证
                if (field.validator) {
                    const error = field.validator(value, this.formData);
                    if (error) {
                        errors[field.name] = error;
                        return;
                    }
                }
            });

            return errors;
        }

        /**
         * 渲染错误信息
         */
        _renderErrors() {
            Object.keys(this.errors).forEach(name => {
                this._renderFieldError(name, this.errors[name]);
            });
        }

        /**
         * 渲染单个字段错误
         */
        _renderFieldError(name, message) {
            if (!this.container) return;

            const element = this.container.querySelector(`[data-field="${name}"]`);
            if (!element) return;

            // 添加错误类
            const input = element.querySelector('input, select, textarea');
            if (input) {
                input.classList.add('is-invalid');
            }

            // 移除旧错误
            const oldError = element.querySelector('.invalid-feedback');
            if (oldError) {
                oldError.remove();
            }

            // 添加新错误
            let errorDiv = element.querySelector('.invalid-feedback.d-block');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback d-block';
                
                const input = element.querySelector('input, select, textarea');
                if (input && element.classList.contains('form-check')) {
                    input.parentElement.after(errorDiv);
                } else {
                    const lastChild = element.lastElementChild;
                    if (lastChild && lastChild.classList.contains('form-text')) {
                        lastChild.before(errorDiv);
                    } else {
                        element.appendChild(errorDiv);
                    }
                }
            }

            errorDiv.textContent = message;
        }

        /**
         * 清除单个字段错误
         */
        _clearFieldError(name) {
            if (!this.container) return;

            const element = this.container.querySelector(`[data-field="${name}"]`);
            if (!element) return;

            const input = element.querySelector('input, select, textarea');
            if (input) {
                input.classList.remove('is-invalid');
            }

            const errorDiv = element.querySelector('.invalid-feedback');
            if (errorDiv) {
                errorDiv.remove();
            }
        }

        /**
         * 清除所有错误
         */
        _clearAllErrors() {
            if (!this.container) return;

            const inputs = this.container.querySelectorAll('.is-invalid');
            inputs.forEach(input => input.classList.remove('is-invalid'));

            const errorDivs = this.container.querySelectorAll('.invalid-feedback');
            errorDivs.forEach(div => div.remove());

            this.errors = {};
        }

        /**
         * 设置加载状态
         */
        _setLoading(loading) {
            if (!this.container) return;

            const submitBtn = this.container.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = loading;
                
                if (loading) {
                    submitBtn.dataset.originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>保存中...';
                } else {
                    submitBtn.innerHTML = submitBtn.dataset.originalText || this.options.submitText;
                }
            }
        }

        /**
         * 显示表单（用于弹窗）
         */
        show() {
            if (this.container) {
                this.container.style.display = '';
            }
        }

        /**
         * 隐藏表单（用于弹窗）
         */
        hide() {
            if (this.container) {
                this.container.style.display = 'none';
            }
        }

        /**
         * 销毁表单
         */
        destroy() {
            if (this.formElement) {
                this.formElement.removeEventListener('submit', this._handleSubmit);
                this.formElement.removeEventListener('change', this._handleChange);
                this.formElement.removeEventListener('input', this._handleInput);
            }
            
            if (this.container) {
                this.container.innerHTML = '';
            }

            delete window[this.options.id];
        }
    }

    // 暴露到全局
    global.UniversalForm = UniversalForm;

})(typeof window !== 'undefined' ? window : this);
