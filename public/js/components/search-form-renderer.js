/**
 * 搜索表单渲染器
 * 用于动态渲染通用CRUD的搜索表单
 */
(function (window, document) {
    'use strict';

    class SearchFormRenderer {
        constructor(options = {}) {
            this.config = options.config || {};
            this.formId = options.formId || 'searchForm';
            this.panelId = options.panelId || 'searchPanel';
            this.tableId = options.tableId || 'dataTable';
            this.model = options.model || '';
            
            this.searchFields = this.config.search_fields || [];
            this.fieldsConfig = this.config.search_fields_config || this.config.fields || [];
            
            this.form = null;
            this.panel = null;
            
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
            // 查找搜索面板容器
            this.panel = document.getElementById(this.panelId);
            if (!this.panel) {
                console.warn('[SearchFormRenderer] 未找到搜索面板容器:', this.panelId);
                return;
            }

            // 如果没有搜索字段配置，不渲染搜索表单
            if (!this.searchFields || this.searchFields.length === 0) {
                this.panel.style.display = 'none';
                return;
            }

            // 构建搜索表单HTML
            const formHtml = this.buildFormHtml();
            this.panel.innerHTML = formHtml;
            
            // 获取表单元素
            this.form = document.getElementById(this.formId);
            
            // 初始化表单增强功能（日期选择器、下拉框等）
            this.initializeEnhancements();
            
            // 绑定表单提交事件
            this.attachFormSubmit();
        }

        buildFormHtml() {
            const fieldsHtml = this.buildFieldsHtml();
            
            return `
                <form id="${this.formId}" class="search-form">
                    <div class="row g-3">
                        ${fieldsHtml}
                        <div class="col-12">
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-secondary" onclick="resetSearchForm_${this.tableId}()">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>重置
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i>搜索
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            `;
        }

        buildFieldsHtml() {
            if (!this.fieldsConfig || this.fieldsConfig.length === 0) {
                // 如果没有字段配置，使用默认的简单输入框
                return this.searchFields.map(fieldName => {
                    return `
                        <div class="col-md-3">
                            <label class="form-label">${this.getFieldLabel(fieldName)}</label>
                            <input type="text" name="filters[${fieldName}]" class="form-control" placeholder="请输入${this.getFieldLabel(fieldName)}">
                        </div>
                    `;
                }).join('');
            }

            // 使用字段配置渲染
            return this.fieldsConfig.map(field => {
                const fieldName = field.name || '';
                if (!fieldName || !this.searchFields.includes(fieldName)) {
                    return '';
                }

                const fieldType = field.type || 'text';
                const fieldLabel = field.label || field.field_name || fieldName;
                const colClass = this.getColumnClass(field);

                let fieldHtml = '';
                
                switch (fieldType) {
                    case 'text':
                    case 'string':
                        fieldHtml = this.renderTextInput(field, fieldName, fieldLabel);
                        break;
                    case 'number':
                    case 'integer':
                        fieldHtml = this.renderNumberInput(field, fieldName, fieldLabel);
                        break;
                    case 'select':
                    case 'switch':
                        fieldHtml = this.renderSelect(field, fieldName, fieldLabel);
                        break;
                    case 'date':
                    case 'datetime':
                        fieldHtml = this.renderDateInput(field, fieldName, fieldLabel, fieldType);
                        break;
                    case 'number_range':
                        fieldHtml = this.renderNumberRange(field, fieldName, fieldLabel);
                        break;
                    case 'date_range':
                    case 'datetime_range':
                        fieldHtml = this.renderDateRange(field, fieldName, fieldLabel, fieldType);
                        break;
                    default:
                        fieldHtml = this.renderTextInput(field, fieldName, fieldLabel);
                }

                return `
                    <div class="${colClass}">
                        <label class="form-label">${fieldLabel}</label>
                        ${fieldHtml}
                    </div>
                `;
            }).filter(html => html !== '').join('');
        }

        renderTextInput(field, fieldName, fieldLabel) {
            const placeholder = field.placeholder || `请输入${fieldLabel}`;
            return `<input type="text" name="filters[${fieldName}]" class="form-control" placeholder="${placeholder}">`;
        }

        renderNumberInput(field, fieldName, fieldLabel) {
            const placeholder = field.placeholder || `请输入${fieldLabel}`;
            return `<input type="number" name="filters[${fieldName}]" class="form-control" placeholder="${placeholder}">`;
        }

        renderSelect(field, fieldName, fieldLabel) {
            const options = field.options || [];
            let optionsHtml = '';
            let hasAllOption = false;
            
            // 先检查是否已经有"全部"选项
            if (Array.isArray(options)) {
                hasAllOption = options.some(option => {
                    if (typeof option === 'object' && option !== null) {
                        const value = option.value ?? option.id ?? option.key ?? '';
                        const label = option.label ?? option.name ?? option.text ?? option.title ?? '';
                        return value === '' || label === '全部';
                    }
                    return String(option) === '' || String(option) === '全部';
                });
            } else if (typeof options === 'object' && options !== null) {
                // 检查对象格式中是否有空 key 或"全部"的选项
                hasAllOption = Object.keys(options).some(key => {
                    if (key === '') return true;
                    const optionValue = options[key];
                    if (typeof optionValue === 'object' && optionValue !== null) {
                        const label = optionValue.label ?? optionValue.name ?? optionValue.text ?? optionValue.title ?? '';
                        return label === '全部';
                    }
                    return String(optionValue) === '全部';
                });
            }
            
            // 如果没有"全部"选项，则添加一个
            if (!hasAllOption) {
                optionsHtml = '<option value="">全部</option>';
            }
            
            // 渲染选项
            if (Array.isArray(options)) {
                options.forEach((option, index) => {
                    let value = '';
                    let label = '';
                    
                    if (typeof option === 'object' && option !== null) {
                        // 对象格式：尝试多种可能的属性名
                        value = option.value ?? option.id ?? option.key ?? String(index);
                        label = option.label ?? option.name ?? option.text ?? option.title ?? value;
                        
                        // 如果 label 仍然是空字符串或无效值，尝试使用 value
                        if (!label || label === '') {
                            label = value;
                        }
                        
                        // 如果 value 和 label 都无效，跳过该选项
                        if (!value && !label) {
                            console.warn(`[SearchFormRenderer] 跳过无效选项:`, option);
                            return;
                        }
                    } else {
                        // 简单值格式：字符串或数字
                        value = String(option);
                        label = String(option);
                    }
                    
                    optionsHtml += `<option value="${this.escapeHtml(value)}">${this.escapeHtml(label)}</option>`;
                });
            } else if (typeof options === 'object' && options !== null) {
                // 对象格式：{1: '启用', 0: '禁用'} 或 {1: {value: '启用', label: '启用'}}
                // 需要确保空 key 的选项在最前面
                const keys = Object.keys(options);
                const emptyKeyIndex = keys.indexOf('');
                
                // 如果有空 key，先处理它
                if (emptyKeyIndex !== -1) {
                    const emptyKey = keys[emptyKeyIndex];
                    const optionValue = options[emptyKey];
                    let value = emptyKey;
                    let label = '';
                    
                    if (typeof optionValue === 'object' && optionValue !== null) {
                        value = optionValue.value ?? optionValue.id ?? optionValue.key ?? emptyKey;
                        label = optionValue.label ?? optionValue.name ?? optionValue.text ?? optionValue.title ?? value;
                    } else {
                        label = String(optionValue);
                    }
                    
                    optionsHtml += `<option value="${this.escapeHtml(value)}">${this.escapeHtml(label)}</option>`;
                }
                
                // 处理其他选项
                keys.forEach(key => {
                    if (key === '') return; // 空 key 已经处理过了
                    
                    const optionValue = options[key];
                    let value = key;
                    let label = '';
                    
                    if (typeof optionValue === 'object' && optionValue !== null) {
                        // 嵌套对象格式：{1: {value: '启用', label: '启用'}}
                        value = optionValue.value ?? optionValue.id ?? optionValue.key ?? key;
                        label = optionValue.label ?? optionValue.name ?? optionValue.text ?? optionValue.title ?? value;
                    } else {
                        // 简单值格式：{1: '启用'}
                        label = String(optionValue);
                    }
                    
                    optionsHtml += `<option value="${this.escapeHtml(value)}">${this.escapeHtml(label)}</option>`;
                });
            }

            return `<select name="filters[${fieldName}]" class="form-select">${optionsHtml}</select>`;
        }

        renderDateInput(field, fieldName, fieldLabel, fieldType) {
            const isDatetime = fieldType === 'datetime';
            const dataType = isDatetime ? 'datetime' : 'date';
            return `<input type="text" name="filters[${fieldName}]" class="form-control" data-flatpickr-type="${dataType}" placeholder="请选择${fieldLabel}">`;
        }

        renderNumberRange(field, fieldName, fieldLabel) {
            return `
                <div class="input-group">
                    <input type="number" name="filters[${fieldName}_min]" class="form-control" placeholder="最小值">
                    <span class="input-group-text">-</span>
                    <input type="number" name="filters[${fieldName}_max]" class="form-control" placeholder="最大值">
                </div>
            `;
        }

        renderDateRange(field, fieldName, fieldLabel, searchType) {
            // 检查搜索类型，如果是 datetime_range，则支持分钟级精度
            // searchType 参数优先，如果没有则从 field.type 获取
            const type = searchType || field.type || 'date_range';
            const isDatetime = type === 'datetime_range';
            const dataType = isDatetime ? 'datetime' : 'date';
            const placeholder = isDatetime ? '开始日期时间' : '开始日期';
            const placeholderEnd = isDatetime ? '结束日期时间' : '结束日期';
            
            return `
                <div class="input-group">
                    <input type="text" name="filters[${fieldName}_min]" class="form-control" data-flatpickr-type="${dataType}" placeholder="${placeholder}">
                    <span class="input-group-text">-</span>
                    <input type="text" name="filters[${fieldName}_max]" class="form-control" data-flatpickr-type="${dataType}" placeholder="${placeholderEnd}">
                </div>
            `;
        }

        getColumnClass(field) {
            // 根据字段配置的宽度或默认使用 col-md-3
            const width = field.width || field.col || 3;
            if (typeof width === 'number') {
                return `col-md-${width}`;
            }
            return width || 'col-md-3';
        }

        getFieldLabel(fieldName) {
            // 从字段配置中查找标签
            const field = this.fieldsConfig.find(f => f.name === fieldName);
            if (field) {
                return field.label || field.field_name || fieldName;
            }
            // 默认使用字段名（首字母大写）
            return fieldName.charAt(0).toUpperCase() + fieldName.slice(1);
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        initializeEnhancements() {
            if (!this.form) {
                console.warn('[SearchFormRenderer] 表单元素不存在，跳过增强功能初始化', this.formId);
                return;
            }

            console.log('[SearchFormRenderer] 开始初始化表单增强功能', {
                formId: this.formId,
                tableId: this.tableId,
                searchFields: this.searchFields,
                currentUrl: window.location.href
            });

            // 从URL参数填充搜索表单
            console.log('[SearchFormRenderer] 开始从URL参数填充搜索表单');
            this.populateFormFromUrlParams();

            // 初始化日期选择器（使用 Flatpickr）
            console.log('[SearchFormRenderer] 开始初始化日期选择器');
            this.initializeDateInputs();

            // 初始化关联字段的下拉框（如果使用了 Tom Select）
            if (window.TomSelect) {
                console.log('[SearchFormRenderer] 检测到TomSelect，开始初始化关联字段下拉框');
                const relationFields = this.form.querySelectorAll('select[data-relation]');
                console.log('[SearchFormRenderer] 找到关联字段数量:', relationFields.length);

                relationFields.forEach((select, index) => {
                    const fieldName = select.getAttribute('data-relation');
                    const relationUrl = `/admin/u/${this.model}/search-relation-options?field=${fieldName}`;

                    console.log(`[SearchFormRenderer] 初始化关联字段 ${index + 1}:`, {
                        fieldName: fieldName,
                        relationUrl: relationUrl,
                        selectElement: select
                    });

                    new TomSelect(select, {
                        valueField: 'value',
                        labelField: 'text',
                        searchField: ['text', 'label'],
                        load: function(query, callback) {
                            console.log(`[SearchFormRenderer] 关联字段 ${fieldName} 搜索请求:`, query);
                            if (!query.length) {
                                console.log(`[SearchFormRenderer] 关联字段 ${fieldName} 搜索查询为空，跳过`);
                                return callback();
                            }
                            fetch(`${relationUrl}&search=${encodeURIComponent(query)}`)
                                .then(response => {
                                    console.log(`[SearchFormRenderer] 关联字段 ${fieldName} 搜索响应状态:`, response.status);
                                    return response.json();
                                })
                                .then(data => {
                                    console.log(`[SearchFormRenderer] 关联字段 ${fieldName} 搜索响应数据:`, data);
                                    if (data.code === 200 && data.data && data.data.results) {
                                        console.log(`[SearchFormRenderer] 关联字段 ${fieldName} 返回 ${data.data.results.length} 条结果`);
                                        callback(data.data.results);
                                    } else {
                                        console.log(`[SearchFormRenderer] 关联字段 ${fieldName} 无有效结果`);
                                        callback();
                                    }
                                })
                                .catch(error => {
                                    console.error(`[SearchFormRenderer] 关联字段 ${fieldName} 搜索失败:`, error);
                                    callback();
                                });
                        }
                    });
                });
            } else {
                console.log('[SearchFormRenderer] TomSelect 未加载，跳过关联字段下拉框初始化');
            }

            console.log('[SearchFormRenderer] 表单增强功能初始化完成');
        }

        /**
         * 从URL参数填充搜索表单
         */
        populateFormFromUrlParams() {
            if (!this.form) {
                console.warn('[SearchFormRenderer] 表单不存在，跳过URL参数填充');
                return;
            }

            try {
                const currentUrl = window.location.href;
                const urlParams = new URLSearchParams(window.location.search);

                console.log('[SearchFormRenderer] 开始解析URL参数填充表单', {
                    currentUrl: currentUrl,
                    searchParams: window.location.search,
                    totalParams: urlParams.toString().split('&').length,
                    searchFields: this.searchFields
                });

                // 遍历所有搜索字段
                this.searchFields.forEach(fieldName => {
                    // 优先检查filters[fieldName]格式的参数（表单提交格式）
                    let paramName = `filters[${fieldName}]`;
                    let filterValue = urlParams.get(paramName);

                    // 如果没找到，尝试直接的fieldName格式（URL直接参数）
                    if (filterValue === null) {
                        paramName = fieldName;
                        filterValue = urlParams.get(paramName);
                    }

                    console.log(`[SearchFormRenderer] 检查字段 ${fieldName}:`, {
                        filtersParamName: `filters[${fieldName}]`,
                        directParamName: fieldName,
                        usedParamName: paramName,
                        filterValue: filterValue,
                        hasValue: filterValue !== null && filterValue !== ''
                    });

                    if (filterValue !== null && filterValue !== '') {
                        // 查找对应的表单字段并设置值（表单字段始终使用filters[fieldName]格式）
                        const formFieldName = `filters[${fieldName}]`;
                        const input = this.form.querySelector(`[name="${formFieldName}"]`);

                        if (input) {
                            console.log(`[SearchFormRenderer] 找到表单字段 ${fieldName}, 准备设置值:`, {
                                fieldName: fieldName,
                                filterValue: filterValue,
                                inputType: input.type || input.tagName,
                                inputElement: input
                            });

                            // 根据输入类型设置值
                            if (input.type === 'checkbox' || input.type === 'radio') {
                                const checkedValue = filterValue === '1' || filterValue === 'true';
                                input.checked = checkedValue;
                                console.log(`[SearchFormRenderer] 设置复选框/单选框 ${fieldName} 为: ${checkedValue}`);
                            } else if (input.tagName === 'SELECT') {
                                input.value = filterValue;
                                // 触发change事件，以便TomSelect等组件更新显示
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                                console.log(`[SearchFormRenderer] 设置下拉框 ${fieldName} 为: "${filterValue}", 已触发change事件`);
                            } else {
                                input.value = filterValue;
                                // 触发input事件，以便日期选择器等组件更新显示
                                input.dispatchEvent(new Event('input', { bubbles: true }));
                                console.log(`[SearchFormRenderer] 设置输入框 ${fieldName} 为: "${filterValue}", 已触发input事件`);
                            }
                        } else {
                            console.warn(`[SearchFormRenderer] 未找到表单字段: ${paramName}`);
                        }
                    } else {
                        console.log(`[SearchFormRenderer] 字段 ${fieldName} 无URL参数值，跳过`);
                    }
                });

                console.log('[SearchFormRenderer] URL参数填充完成');
            } catch (error) {
                console.error('[SearchFormRenderer] 解析URL参数失败:', error, {
                    errorMessage: error.message,
                    errorStack: error.stack,
                    currentUrl: window.location.href
                });
            }
        }

        initializeDateInputs() {
            if (typeof window.flatpickr !== 'function') {
                console.warn('[SearchFormRenderer] Flatpickr 未加载，跳过日期选择器初始化');
                return;
            }

            const dateInputs = this.form.querySelectorAll('input[data-flatpickr-type]');

            dateInputs.forEach((input) => {
                // 如果已经初始化过，跳过
                if (input._flatpickr) {
                    return;
                }

                const dataType = input.dataset.flatpickrType || 'date';
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

                // 初始化 Flatpickr
                try {
                    input._flatpickr = window.flatpickr(input, config);
                } catch (error) {
                    console.error('[SearchFormRenderer] Flatpickr 初始化失败', error, input);
                }
            });
        }

        attachFormSubmit() {
            if (!this.form) return;

            // 移除旧的事件监听器（如果存在）
            const oldHandler = window['_searchFormSubmitHandler_' + this.tableId];
            if (oldHandler) {
                this.form.removeEventListener('submit', oldHandler);
            }

            // 创建新的事件处理函数
            const handler = (e) => {
                e.preventDefault();
                this.handleSubmit();
            };

            // 保存处理函数引用，以便后续移除
            window['_searchFormSubmitHandler_' + this.tableId] = handler;
            this.form.addEventListener('submit', handler);
        }

        handleSubmit() {
            // 触发数据表刷新（调用全局的 loadData 函数）
            if (typeof window['loadData_' + this.tableId] === 'function') {
                window['loadData_' + this.tableId]();
            } else {
                console.warn('[SearchFormRenderer] 未找到数据表加载函数: loadData_' + this.tableId);
            }
        }

        reset() {
            if (!this.form) return;
            this.form.reset();
            // 重置后也触发数据表刷新
            if (typeof window['loadData_' + this.tableId] === 'function') {
                window['loadData_' + this.tableId]();
            }
        }
    }

    // 创建全局重置函数
    window.createSearchFormResetFunction = function(tableId) {
        return function() {
            const renderer = window['_searchFormRenderer_' + tableId];
            if (renderer && typeof renderer.reset === 'function') {
                renderer.reset();
            }
        };
    };

    // 导出到全局
    window.SearchFormRenderer = SearchFormRenderer;

})(window, document);

