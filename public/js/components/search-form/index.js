/**
 * SearchForm 搜索表单组件
 * 
 * 功能特性：
 * - 支持多种字段类型（text、select、date、daterange）
 * - 表单验证
 * - 自动重置
 * - 与 DataTable 集成
 * 
 * 使用方法：
 * const form = SearchForm.create({
 *     fields: [
 *         { name: 'keyword', type: 'text', label: '关键词', placeholder: '搜索...' },
 *         { name: 'status', type: 'select', label: '状态', options: [...] },
 *         { name: 'date_range', type: 'daterange', label: '日期范围' }
 *     ],
 *     onSearch: (data) => { ... },
 *     onReset: () => { ... }
 * });
 */

(function(global) {
    'use strict';

    /**
     * SearchForm 类
     */
    class SearchForm {
        constructor(options) {
            this.options = {
                fields: options.fields || [],
                showReset: options.showReset !== false,
                showSearch: options.showSearch !== false,
                layout: options.layout || 'horizontal', // horizontal, vertical, inline
                placeholder: options.placeholder || '请输入搜索关键词',
                onSearch: options.onSearch || null,
                onReset: options.onReset || null,
                ...options,
            };

            this.formData = {};
            this.init();
        }

        /**
         * 初始化
         */
        init() {
            // 初始化表单数据
            this.options.fields.forEach(field => {
                if (field.type === 'daterange') {
                    this.formData[field.name] = ['', ''];
                } else {
                    this.formData[field.name] = field.default || '';
                }
            });

            // 绑定方法
            this.handleSearch = this.handleSearch.bind(this);
            this.handleReset = this.handleReset.bind(this);
            this.handleChange = this.handleChange.bind(this);
        }

        /**
         * 获取字段默认值
         */
        getDefaultValue(field) {
            if (field.type === 'daterange') {
                return ['', ''];
            }
            return field.default || '';
        }

        /**
         * 处理搜索
         */
        handleSearch() {
            if (this.options.onSearch) {
                this.options.onSearch({ ...this.formData });
            }
        }

        /**
         * 处理重置
         */
        handleReset() {
            // 重置表单数据
            this.options.fields.forEach(field => {
                this.formData[field.name] = this.getDefaultValue(field);
            });

            if (this.options.onReset) {
                this.options.onReset();
            }

            // 触发搜索
            this.handleSearch();
        }

        /**
         * 处理表单变更
         */
        handleChange(name, value) {
            this.formData[name] = value;
        }

        /**
         * 渲染字段
         */
        renderField(field) {
            const value = this.formData[field.name] || '';
            let inputHtml = '';

            switch (field.type) {
                case 'text':
                case 'search':
                    inputHtml = `
                        <input 
                            type="text" 
                            class="form-control" 
                            placeholder="${field.placeholder || this.options.placeholder}"
                            x-model="formData.${field.name}"
                            @keyup.enter="handleSearch()"
                        >
                    `;
                    break;

                case 'select':
                    const options = field.options || [];
                    const optionsHtml = options.map(opt => 
                        `<option value="${opt.value}" ${value == opt.value ? 'selected' : ''}>${opt.label}</option>`
                    ).join('');
                    inputHtml = `
                        <select class="form-select" x-model="formData.${field.name}">
                            <option value="">${field.placeholder || '请选择'}</option>
                            ${optionsHtml}
                        </select>
                    `;
                    break;

                case 'date':
                    inputHtml = `
                        <input 
                            type="date" 
                            class="form-control" 
                            x-model="formData.${field.name}"
                        >
                    `;
                    break;

                case 'daterange':
                    inputHtml = `
                        <div class="input-group">
                            <input 
                                type="date" 
                                class="form-control" 
                                x-model="formData.${field.name}[0]"
                            >
                            <span class="input-group-text">至</span>
                            <input 
                                type="date" 
                                class="form-control" 
                                x-model="formData.${field.name}[1]"
                            >
                        </div>
                    `;
                    break;

                case 'checkbox':
                    inputHtml = `
                        <div class="form-check">
                            <input 
                                type="checkbox" 
                                class="form-check-input" 
                                id="${field.name}"
                                x-model="formData.${field.name}"
                            >
                            <label class="form-check-label" for="${field.name}">${field.label}</label>
                        </div>
                    `;
                    break;

                default:
                    inputHtml = `
                        <input 
                            type="text" 
                            class="form-control" 
                            placeholder="${field.placeholder || ''}"
                            x-model="formData.${field.name}"
                        >
                    `;
            }

            // 渲染标签
            let labelHtml = '';
            if (field.type !== 'checkbox' && field.label) {
                labelHtml = `<label class="${this.options.layout === 'inline' ? 'form-label' : 'col-form-label'}">${field.label}</label>`;
            }

            // 组合 HTML
            if (this.options.layout === 'horizontal') {
                return `
                    <div class="row mb-3">
                        <div class="col-auto">
                            ${labelHtml}
                        </div>
                        <div class="col">
                            ${inputHtml}
                        </div>
                    </div>
                `;
            } else if (this.options.layout === 'inline') {
                return `
                    <div class="row g-2 align-items-center">
                        ${labelHtml ? `<div class="col-auto"><label class="col-form-label">${field.label}</label></div>` : ''}
                        <div class="col-auto">
                            ${inputHtml}
                        </div>
                    </div>
                `;
            } else {
                return `
                    <div class="mb-3">
                        ${labelHtml}
                        ${inputHtml}
                    </div>
                `;
            }
        }

        /**
         * 渲染按钮组
         */
        renderButtons() {
            let buttons = '';

            if (this.options.showSearch) {
                buttons += `
                    <button type="button" class="btn btn-primary me-2" @click="handleSearch()">
                        <i class="bi bi-search"></i> 搜索
                    </button>
                `;
            }

            if (this.options.showReset) {
                buttons += `
                    <button type="button" class="btn btn-outline-secondary" @click="handleReset()">
                        <i class="bi bi-arrow-clockwise"></i> 重置
                    </button>
                `;
            }

            return `
                <div class="mt-3">
                    ${buttons}
                </div>
            `;
        }

        /**
         * 获取 Alpine.js 数据对象
         */
        getAlpineData() {
            const data = {
                formData: this.formData,
                handleSearch: this.handleSearch,
                handleReset: this.handleReset,
            };

            // 为每个字段绑定 handleChange
            this.options.fields.forEach(field => {
                data[`handleChange_${field.name}`] = (value) => {
                    this.handleChange(field.name, value);
                };
            });

            return data;
        }

        /**
         * 获取模板
         */
        getTemplate() {
            const layoutClass = this.options.layout === 'inline' ? 'row g-2' : '';
            const formClass = this.options.layout === 'inline' ? 'd-flex align-items-center flex-wrap' : '';

            return `
                <div class="search-form" x-data="formData">
                    <form class="${formClass}" onsubmit="return false;">
                        <div class="${layoutClass}">
                            ${this.options.fields.map(field => this.renderField(field)).join('')}
                        </div>
                        ${this.renderButtons()}
                    </form>
                </div>
            `;
        }

        /**
         * 获取纯 HTML 模板（不依赖 Alpine.js）
         */
        getStaticTemplate() {
            const fieldsHtml = this.options.fields.map(field => {
                const value = this.formData[field.name] || '';
                let inputHtml = '';

                switch (field.type) {
                    case 'text':
                    case 'search':
                        inputHtml = `
                            <input 
                                type="text" 
                                name="${field.name}"
                                class="form-control" 
                                placeholder="${field.placeholder || this.options.placeholder}"
                                value="${value}"
                            >
                        `;
                        break;

                    case 'select':
                        const options = field.options || [];
                        const optionsHtml = options.map(opt => 
                            `<option value="${opt.value}" ${value == opt.value ? 'selected' : ''}>${opt.label}</option>`
                        ).join('');
                        inputHtml = `
                            <select name="${field.name}" class="form-select">
                                <option value="">${field.placeholder || '请选择'}</option>
                                ${optionsHtml}
                            </select>
                        `;
                        break;

                    case 'date':
                        inputHtml = `
                            <input 
                                type="date" 
                                name="${field.name}"
                                class="form-control" 
                                value="${value}"
                            >
                        `;
                        break;

                    default:
                        inputHtml = `
                            <input 
                                type="text" 
                                name="${field.name}"
                                class="form-control" 
                                placeholder="${field.placeholder || ''}"
                                value="${value}"
                            >
                        `;
                }

                return `
                    <div class="col-auto">
                        ${field.label ? `<label class="col-form-label">${field.label}</label>` : ''}
                        ${inputHtml}
                    </div>
                `;
            }).join('');

            return `
                <form action="" method="GET" class="row g-3 align-items-center mb-3">
                    ${fieldsHtml}
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> 搜索
                        </button>
                    </div>
                    <div class="col-auto">
                        <a href="" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise"></i> 重置
                        </a>
                    </div>
                </form>
            `;
        }

        /**
         * 挂载到容器
         */
        mount(selector, useAlpine = true) {
            const container = typeof selector === 'string' 
                ? document.querySelector(selector) 
                : selector;
            
            if (!container) {
                console.error('SearchForm: 容器不存在');
                return;
            }

            // 渲染模板
            container.innerHTML = useAlpine ? this.getTemplate() : this.getStaticTemplate();

            // 初始化 Alpine.js
            if (useAlpine && window.Alpine) {
                const formData = this.getAlpineData();
                window.Alpine.data('formData', () => formData);
                window.Alpine.initTree(container);
            }

            return this;
        }

        /**
         * 获取查询参数
         */
        getQueryParams() {
            const params = {};
            Object.keys(this.formData).forEach(key => {
                const value = this.formData[key];
                if (value !== '' && value !== null && value !== undefined) {
                    params[key] = value;
                }
            });
            return params;
        }

        /**
         * 设置表单值
         */
        setValue(name, value) {
            this.formData[name] = value;
        }

        /**
         * 获取表单值
         */
        getValue(name) {
            return this.formData[name];
        }

        /**
         * 重置表单
         */
        reset() {
            this.handleReset();
        }
    }

    /**
     * 创建 SearchForm 实例
     */
    SearchForm.create = function(options) {
        return new SearchForm(options);
    };

    // 暴露到全局
    global.SearchForm = SearchForm;

    // ES Module 导出支持
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = SearchForm;
    }

})(typeof window !== 'undefined' ? window : this);
