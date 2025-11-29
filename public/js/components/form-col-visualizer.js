/**
 * 表单列宽可视化编辑器
 * 提供实时预览和拖拽调整表单字段列宽的功能
 * 
 * @class FormColVisualizer
 * @example
 * const visualizer = new FormColVisualizer({
 *   container: '#colVisualizerContent',
 *   fields: fieldsData,
 *   onUpdate: (fieldName, colValue) => {
 *     // 更新表单中的 col 值
 *   }
 * });
 * visualizer.render();
 */

(function () {
    'use strict';

    /**
     * 预设列宽配置
     */
    const PRESET_COLS = [
        { value: '', label: '默认', span: 12, mdSpan: 3, description: '默认（col-12 col-md-3）' },
        { value: 'col-12', label: '全宽', span: 12, mdSpan: 12, description: '全宽（col-12）' },
        { value: 'col-12 col-md-6', label: '半宽', span: 12, mdSpan: 6, description: '半宽（col-12 col-md-6）' },
        { value: 'col-12 col-md-4', label: '1/3', span: 12, mdSpan: 4, description: '1/3宽（col-12 col-md-4）' },
        { value: 'col-12 col-md-3', label: '1/4', span: 12, mdSpan: 3, description: '1/4宽（col-12 col-md-3）' },
        { value: 'col-12 col-md-8', label: '2/3', span: 12, mdSpan: 8, description: '2/3宽（col-12 col-md-8）' },
        { value: 'col-12 col-md-9', label: '3/4', span: 12, mdSpan: 9, description: '3/4宽（col-12 col-md-9）' },
        { value: 'col-6 col-md-2', label: '1/6', span: 6, mdSpan: 2, description: '1/6宽（col-6 col-md-2）' },
    ];

    /**
     * 解析 col 值为 span 信息
     */
    function parseColValue(colValue) {
        if (!colValue || colValue.trim() === '') {
            return { span: 12, mdSpan: 3, preset: '', isCustom: false };
        }

        // 查找预设值
        const preset = PRESET_COLS.find(p => p.value === colValue);
        if (preset) {
            return { ...preset, isCustom: false };
        }

        // 自定义值：尝试解析
        // 例如：col-12 col-lg-4 -> { span: 12, lgSpan: 4 }
        const parts = colValue.trim().split(/\s+/);
        let span = 12;
        let mdSpan = 12;
        let lgSpan = null;

        parts.forEach(part => {
            if (part.startsWith('col-')) {
                const num = parseInt(part.replace('col-', ''));
                if (!isNaN(num)) {
                    if (part === 'col-' + num) {
                        span = num;
                    } else if (part.includes('md-')) {
                        mdSpan = num;
                    } else if (part.includes('lg-')) {
                        lgSpan = num;
                    }
                }
            }
        });

        return {
            value: colValue,
            span,
            mdSpan,
            lgSpan,
            isCustom: true,
            preset: ''
        };
    }

    /**
     * 计算字段宽度百分比（用于预览，仅桌面端）
     */
    function calculateWidthPercent(spanInfo) {
        return (spanInfo.mdSpan / 12) * 100;
    }

    /**
     * 表单列宽可视化编辑器类
     */
    class FormColVisualizer {
        constructor(options = {}) {
            this.container = typeof options.container === 'string' 
                ? document.querySelector(options.container) 
                : options.container;
            this.fields = options.fields || [];
            this.onUpdate = options.onUpdate || (() => {});
            this.fieldCols = {}; // 存储每个字段的 col 值
            // 模态框元素或 ID（用于保存成功后关闭）
            this.modal = options.modal || null;

            if (!this.container) {
                throw new Error('FormColVisualizer: container is required');
            }

            // 初始化字段 col 值
            this.fields.forEach(field => {
                if (field.name) {
                    this.fieldCols[field.name] = field.col || '';
                }
            });
        }

        /**
         * 渲染可视化编辑器
         */
        render() {
            const editableFields = this.fields.filter(f => f.editable !== false);
            
            if (editableFields.length === 0) {
                this.container.innerHTML = `
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 暂无可编辑字段，请先在字段配置中启用「编辑」功能。
                    </div>
                `;
                return;
            }

            this.container.innerHTML = `
                <div class="form-col-visualizer">
                    <!-- 工具栏 -->
                    <div class="visualizer-toolbar mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">
                                <i class="bi bi-info-circle"></i> 桌面端预览：调整字段列宽，实时查看表单布局效果
                            </span>
                        </div>
                    </div>

                    <!-- 预览区域 -->
                    <div class="visualizer-preview mb-4">
                        <div class="preview-container" id="previewContainer">
                            ${this.renderPreview(editableFields)}
                        </div>
                    </div>
                </div>
            `;

            // 绑定预览区域的事件
            this.bindPreviewEvents();
            
            // 注意：footer 中的按钮事件在模态框显示后由外部绑定
        }

        /**
         * 渲染预览区域
         */
        renderPreview(fields) {
            let html = '<div class="row g-3 preview-form">';

            fields.forEach(field => {
                const colValue = this.fieldCols[field.name] || '';
                const spanInfo = parseColValue(colValue);
                const widthPercent = calculateWidthPercent(spanInfo);
                const fieldLabel = field.field_name || field.comment || field.name;
                const fieldType = field.form_type || 'text';

                html += `
                    <div class="preview-field" 
                         data-field-name="${field.name}"
                         style="width: ${widthPercent}%;">
                        <div class="preview-field-inner">
                            <label class="preview-label">${this.escapeHtml(fieldLabel)}</label>
                            <div class="preview-input">
                                ${this.renderPreviewInput(fieldType)}
                            </div>
                            <div class="preview-info">
                                <small class="text-muted">
                                    <code>${colValue || '默认'}</code>
                                    <span class="ms-1">(${spanInfo.mdSpan}/12)</span>
                                </small>
                            </div>
                            <div class="preview-col-selector mt-2">
                                <select class="form-select form-select-sm preview-col-select" 
                                        data-field="${field.name}"
                                        title="快速选择列宽">
                                    ${PRESET_COLS.map(preset => {
                                        const isSelected = (!colValue && preset.value === '') || colValue === preset.value;
                                        return `
                                            <option value="${preset.value}" ${isSelected ? 'selected' : ''}>
                                                ${preset.label} - ${preset.description}
                                            </option>
                                        `;
                                    }).join('')}
                                    ${spanInfo.isCustom ? `
                                        <option value="${this.escapeHtml(colValue)}" selected>
                                            自定义 - ${this.escapeHtml(colValue)}
                                        </option>
                                    ` : ''}
                                    <option value="__custom__">自定义...</option>
                                </select>
                                ${spanInfo.isCustom ? `
                                    <input type="text" 
                                           class="form-control form-control-sm mt-2 preview-custom-input"
                                           data-field="${field.name}"
                                           value="${this.escapeHtml(colValue)}"
                                           placeholder="自定义列宽，如：col-12 col-lg-4">
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            return html;
        }

        /**
         * 渲染预览输入框
         */
        renderPreviewInput(fieldType) {
            const typeMap = {
                'text': '<input type="text" class="form-control" placeholder="请输入..." readonly>',
                'textarea': '<textarea class="form-control" rows="2" placeholder="请输入..." readonly></textarea>',
                'number': '<input type="number" class="form-control" placeholder="0" readonly>',
                'email': '<input type="email" class="form-control" placeholder="example@email.com" readonly>',
                'password': '<input type="password" class="form-control" placeholder="••••••" readonly>',
                'date': '<input type="date" class="form-control" readonly>',
                'datetime': '<input type="datetime-local" class="form-control" readonly>',
                'select': '<select class="form-select" disabled><option>请选择...</option></select>',
                'switch': '<div class="form-check form-switch"><input type="checkbox" class="form-check-input" disabled></div>',
                'radio': '<div class="form-check"><input type="radio" class="form-check-input" disabled> <label class="form-check-label">选项1</label></div>',
                'checkbox': '<div class="form-check"><input type="checkbox" class="form-check-input" disabled> <label class="form-check-label">选项1</label></div>',
                'image': '<input type="file" class="form-control" accept="image/*" disabled>',
                'file': '<input type="file" class="form-control" disabled>',
            };

            return typeMap[fieldType] || typeMap['text'];
        }


        /**
         * 获取表单类型标签
         */
        getFormTypeLabel(formType) {
            const typeMap = {
                'text': '文本',
                'textarea': '文本域',
                'rich_text': '富文本',
                'number': '数字',
                'email': '邮箱',
                'password': '密码',
                'date': '日期',
                'datetime': '日期时间',
                'select': '下拉',
                'switch': '开关',
                'radio': '单选',
                'checkbox': '复选',
                'image': '图片',
                'file': '文件',
            };
            return typeMap[formType] || formType;
        }

        /**
         * 绑定事件
         */
        bindEvents() {
            // 保存列宽设置（按钮在模态框的 footer 中）
            const saveBtn = document.getElementById('colVisualizerSaveBtn');
            if (saveBtn) {
                // 移除之前的事件监听器（通过移除并重新添加按钮）
                const saveBtnParent = saveBtn.parentNode;
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtnParent.replaceChild(newSaveBtn, saveBtn);
                
                newSaveBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('[列宽可视化编辑器] 点击保存按钮');
                    this.saveCols();
                });
            } else {
                console.warn('[列宽可视化编辑器] 找不到保存按钮 #colVisualizerSaveBtn');
            }

            // 重置全部（按钮在模态框的 footer 中）
            const resetBtn = document.getElementById('colVisualizerResetBtn');
            if (resetBtn) {
                // 移除之前的事件监听器（通过移除并重新添加按钮）
                const resetBtnParent = resetBtn.parentNode;
                const newResetBtn = resetBtn.cloneNode(true);
                resetBtnParent.replaceChild(newResetBtn, resetBtn);
                
                newResetBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (confirm('确定要重置所有字段的列宽为默认值吗？')) {
                        this.resetAllCols();
                    }
                });
            } else {
                console.warn('[列宽可视化编辑器] 找不到重置按钮 #colVisualizerResetBtn');
            }
        }

        /**
         * 显示预览区域的自定义输入框
         */
        showPreviewCustomInput(fieldName) {
            const previewField = this.container.querySelector(`.preview-field[data-field-name="${fieldName}"]`);
            if (!previewField) return;

            let customInput = previewField.querySelector('.preview-custom-input');
            if (!customInput) {
                const selector = previewField.querySelector('.preview-col-selector');
                const currentValue = this.fieldCols[fieldName] || '';
                
                const inputHtml = `
                    <input type="text" 
                           class="form-control form-control-sm mt-2 preview-custom-input"
                           data-field="${fieldName}"
                           value="${this.escapeHtml(currentValue)}"
                           placeholder="自定义列宽，如：col-12 col-lg-4">
                `;
                
                selector.insertAdjacentHTML('beforeend', inputHtml);
                customInput = selector.querySelector('.preview-custom-input');
                
                // 绑定输入事件
                customInput.addEventListener('input', (e) => {
                    const value = e.target.value.trim();
                    this.setFieldCol(fieldName, value);
                });
                
                customInput.addEventListener('blur', (e) => {
                    const value = e.target.value.trim();
                    if (value) {
                        this.updatePreviewSelect(fieldName, value);
                    }
                });
            }

            customInput.focus();
        }

        /**
         * 更新预览区域的下拉框选项
         */
        updatePreviewSelect(fieldName, customValue) {
            const previewField = this.container.querySelector(`.preview-field[data-field-name="${fieldName}"]`);
            if (!previewField) return;

            const select = previewField.querySelector('.preview-col-select');
            if (!select) return;

            // 检查是否已有自定义选项
            const customOption = Array.from(select.options).find(opt => opt.value === customValue);
            if (!customOption) {
                // 添加自定义选项
                const option = document.createElement('option');
                option.value = customValue;
                option.textContent = `自定义 - ${customValue}`;
                option.selected = true;
                
                // 插入到"自定义..."选项之前
                const customPlaceholder = Array.from(select.options).find(opt => opt.value === '__custom__');
                if (customPlaceholder) {
                    select.insertBefore(option, customPlaceholder);
                } else {
                    select.appendChild(option);
                }
            } else {
                customOption.selected = true;
            }
        }

        /**
         * 设置字段列宽
         */
        setFieldCol(fieldName, colValue) {
            this.fieldCols[fieldName] = colValue;
            
            // 更新表单中的值
            this.onUpdate(fieldName, colValue);

            // 更新预览字段
            this.updatePreviewField(fieldName);
        }


        /**
         * 更新预览
         */
        updatePreview() {
            const previewContainer = this.container.querySelector('#previewContainer');
            if (!previewContainer) return;

            const editableFields = this.fields.filter(f => f.editable !== false);
            previewContainer.innerHTML = this.renderPreview(editableFields);
            
            // 重新绑定预览区域的事件
            this.bindPreviewEvents();
        }

        /**
         * 绑定预览区域的事件
         */
        bindPreviewEvents() {
            // 预览区域的下拉框选择
            this.container.querySelectorAll('.preview-col-select').forEach(select => {
                select.addEventListener('change', (e) => {
                    const fieldName = e.currentTarget.getAttribute('data-field');
                    const value = e.currentTarget.value;
                    
                    if (value === '__custom__') {
                        // 显示自定义输入框
                        this.showPreviewCustomInput(fieldName);
                    } else {
                        // 设置列宽
                        this.setFieldCol(fieldName, value);
                    }
                });
            });

            // 预览区域的自定义输入框
            this.container.querySelectorAll('.preview-custom-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const fieldName = e.currentTarget.getAttribute('data-field');
                    const value = e.currentTarget.value.trim();
                    this.setFieldCol(fieldName, value);
                });
                
                input.addEventListener('blur', (e) => {
                    const fieldName = e.currentTarget.getAttribute('data-field');
                    const value = e.currentTarget.value.trim();
                    if (value) {
                        // 更新下拉框，添加自定义选项
                        this.updatePreviewSelect(fieldName, value);
                    }
                });
            });
        }

        /**
         * 更新预览字段
         */
        updatePreviewField(fieldName) {
            const previewField = this.container.querySelector(`.preview-field[data-field-name="${fieldName}"]`);
            if (!previewField) return;

            const colValue = this.fieldCols[fieldName] || '';
            const spanInfo = parseColValue(colValue);
            const widthPercent = calculateWidthPercent(spanInfo);

            // 更新宽度
            previewField.style.width = `${widthPercent}%`;

            // 更新预览信息
            const previewInfo = previewField.querySelector('.preview-info');
            if (previewInfo) {
                previewInfo.innerHTML = `
                    <small class="text-muted">
                        <code>${colValue || '默认'}</code>
                        <span class="ms-1">(${spanInfo.mdSpan}/12)</span>
                    </small>
                `;
            }

            // 更新下拉框
            const select = previewField.querySelector('.preview-col-select');
            if (select) {
                // 检查是否有匹配的选项
                const matchingOption = Array.from(select.options).find(opt => opt.value === colValue);
                if (matchingOption) {
                    matchingOption.selected = true;
                } else if (colValue && spanInfo.isCustom) {
                    // 自定义值，更新或添加选项
                    this.updatePreviewSelect(fieldName, colValue);
                } else {
                    // 设置为默认
                    select.value = '';
                }
            }

            // 更新自定义输入框
            const customInput = previewField.querySelector('.preview-custom-input');
            if (spanInfo.isCustom) {
                if (!customInput) {
                    this.showPreviewCustomInput(fieldName);
                } else {
                    customInput.value = colValue;
                }
            } else if (customInput) {
                customInput.remove();
            }
        }


        /**
         * 保存列宽设置
         */
        saveCols() {
            console.log('[列宽可视化编辑器] 开始保存列宽设置');
            
            // 确保所有字段的列宽都已同步到主表单
            const editableFields = this.fields.filter(f => f.editable !== false);
            let savedCount = 0;

            editableFields.forEach(field => {
                if (field.name) {
                    const colValue = this.fieldCols[field.name] || '';
                    console.log(`[列宽可视化编辑器] 保存字段 ${field.name} 的列宽: ${colValue}`);
                    // 调用 onUpdate 回调，同步到主表单
                    this.onUpdate(field.name, colValue);
                    savedCount++;
                }
            });

            console.log(`[列宽可视化编辑器] 已保存 ${savedCount} 个字段的列宽设置`);

            // 使用系统提示工具显示成功消息
            this.showSaveSuccess();
            
            // 保存成功后关闭模态框（延迟关闭，让用户看到成功提示）
            if (this.modal) {
                setTimeout(() => {
                    this.closeModal();
                }, 1500); // 1.5秒后关闭，让用户看到成功提示
            }
        }
        
        /**
         * 关闭模态框
         */
        closeModal() {
            if (!this.modal) return;
            
            let modalElement = null;
            if (typeof this.modal === 'string') {
                modalElement = document.querySelector(this.modal);
            } else if (this.modal instanceof Element) {
                modalElement = this.modal;
            }
            
            if (modalElement) {
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        }
        
        /**
         * 显示保存成功提示（使用系统提示工具）
         */
        showSaveSuccess() {
            const message = '成功保存即将关闭表单列宽可视化编辑器';
            
            // 优先使用 AdminIframeClient（如果在 iframe 中）
            if (window.AdminIframeClient && typeof window.AdminIframeClient.success === 'function') {
                window.AdminIframeClient.success({
                    message: message,
                    refreshParent: false,
                    closeCurrent: false
                });
            }
            // 使用系统的 showToast 函数
            else if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                window.Admin.utils.showToast('success', message, 2000);
            }
            else if (typeof window.showToast === 'function') {
                window.showToast('success', message, 2000);
            }
            // 回退方案
            else {
                console.log('[列宽可视化编辑器]', message);
            }
        }

        /**
         * 重置所有列宽
         */
        resetAllCols() {
            this.fields.forEach(field => {
                if (field.name && field.editable !== false) {
                    this.setFieldCol(field.name, '');
                }
            });
        }

        /**
         * HTML 转义
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // 导出到全局
    window.FormColVisualizer = FormColVisualizer;
})();

