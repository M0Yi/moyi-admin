/**
 * 菜单表单组件
 * 处理菜单创建和编辑页面的交互逻辑
 */
(function() {
    'use strict';

    /**
     * 初始化菜单表单
     * @param {Object} options 配置选项
     * @param {boolean} options.isEditMode 是否为编辑模式（默认 false）
     * @param {boolean} options.clearHiddenFields 是否清空隐藏字段的值（默认 false，编辑模式不清空）
     * @param {boolean} options.autoSetTargetBlank 是否自动设置外链打开方式为新窗口（默认 false，编辑模式不自动修改）
     */
    function initMenuForm(options = {}) {
        const {
            isEditMode = false,
            clearHiddenFields = false,
            autoSetTargetBlank = false
        } = options;

        const typeSelect = document.getElementById('type');
        const typeHelpText = document.querySelector('[data-field-name="type"] .form-text');
        const targetFieldGroup = document.querySelector('[data-field-name="target"]');
        const targetHelpText = targetFieldGroup ? targetFieldGroup.querySelector('.form-text') : null;
        
        // 所有需要根据类型显示/隐藏的字段组（兼容 UniversalFormRenderer 生成的字段）
        // UniversalFormRenderer 生成的字段包装在 .universal-form-field 中，有 data-field-name 属性
        const fieldGroups = {
            path: document.querySelector('[data-field-name="path"]'),
            linkPath: document.querySelector('[data-field-name="linkPath"]'),
            component: document.querySelector('[data-field-name="component"]'),
            target: targetFieldGroup,
            permission: document.querySelector('[data-field-name="permission"]'),
            redirect: document.querySelector('[data-field-name="redirect"]'),
            icon: document.querySelector('[data-field-name="icon"]'),
        };
        
        // 菜单类型说明
        const typeDescriptions = {
            menu: '<i class="bi bi-info-circle"></i> <strong>菜单</strong>：系统内部菜单，需要配置路由路径和组件路径',
            link: '<i class="bi bi-link-45deg"></i> <strong>外链</strong>：外部链接，需要填写完整的外链地址（如：https://www.example.com）',
            group: '<i class="bi bi-folder"></i> <strong>分组</strong>：菜单分组，用于组织子菜单，不需要路由和组件',
            divider: '<i class="bi bi-hr"></i> <strong>分割线</strong>：仅用于视觉分隔，只需要名称和标题'
        };
        
        // 辅助函数：查找包含 col-* 类的父容器
        // 表单渲染器会将字段包装在 col-* div 中，需要隐藏整个 col-* div，而不仅仅是 .universal-form-field，避免留下空白
        function findColContainer(element) {
            if (!element) return null;
            let container = element.closest('.universal-form-field') || element;
            while (container && container.parentElement) {
                const parent = container.parentElement;
                if (parent.classList && Array.from(parent.classList).some(cls => cls.startsWith('col-'))) {
                    return parent;
                }
                container = parent;
            }
            return container;
        }
        
        // 更新字段显示/隐藏
        function updateFieldsByType(type) {
            // 更新类型说明（查找类型字段的帮助文本）
            const typeFieldGroup = document.querySelector('[data-field-name="type"]');
            if (typeFieldGroup && typeHelpText) {
                typeHelpText.innerHTML = typeDescriptions[type] || '';
            }
            
            // 根据类型显示/隐藏字段
            Object.keys(fieldGroups).forEach(key => {
                const group = fieldGroups[key];
                if (!group) return;
                
                // 查找字段的父容器（.universal-form-field 或直接使用 group）
                const fieldContainer = group.closest('.universal-form-field') || group;
                const dataType = fieldContainer.getAttribute('data-menu-type') || group.getAttribute('data-menu-type');
                
                if (dataType) {
                    const types = dataType.split(',').map(t => t.trim());
                    
                    // 查找包含 col-* 类的父容器
                    const containerToToggle = findColContainer(fieldContainer) || fieldContainer;
                    
                    if (types.includes(type)) {
                        containerToToggle.style.display = '';
                    } else {
                        containerToToggle.style.display = 'none';
                        // 清空隐藏字段的值（根据配置决定）
                        if (clearHiddenFields && key !== 'linkPath') {
                            const inputs = group.querySelectorAll('input, select, textarea');
                            inputs.forEach(input => {
                                if (input.id !== 'path' && input.id !== 'linkPath' && input.name !== 'path' && input.name !== 'linkPath') {
                                    input.value = '';
                                }
                            });
                        }
                    }
                }
            });
            
            // 特殊处理：路由路径和外链地址的切换
            
            if (type === 'link') {
                // 外链模式：显示外链输入框，隐藏路由输入框
                const pathFieldContainer = fieldGroups.path ? fieldGroups.path.closest('.universal-form-field') : null;
                const linkPathFieldContainer = fieldGroups.linkPath ? fieldGroups.linkPath.closest('.universal-form-field') : null;
                
                // 查找包含 col-* 的父容器
                const pathContainer = findColContainer(pathFieldContainer);
                const linkPathContainer = findColContainer(linkPathFieldContainer);
                
                if (pathContainer) pathContainer.style.display = 'none';
                if (linkPathContainer) {
                    linkPathContainer.style.display = '';
                    // 将路由路径的值复制到外链输入框
                    const pathInput = document.getElementById('path') || document.querySelector('[name="path"]');
                    const linkPathInput = document.getElementById('linkPath') || document.querySelector('[name="linkPath"]');
                    if (pathInput && linkPathInput && pathInput.value && !linkPathInput.value) {
                        linkPathInput.value = pathInput.value;
                    }
                }
                // 外链建议新窗口打开
                if (targetFieldGroup) {
                    const targetSelect = document.getElementById('target') || document.querySelector('[name="target"]');
                    if (targetSelect && autoSetTargetBlank && targetSelect.value === '_self') {
                        targetSelect.value = '_blank';
                    }
                }
                if (targetHelpText) {
                    targetHelpText.textContent = '外链建议在新窗口打开，避免离开当前系统';
                }
            } else {
                // 非外链模式：显示路由输入框，隐藏外链输入框
                const pathFieldContainer = fieldGroups.path ? fieldGroups.path.closest('.universal-form-field') : null;
                const linkPathFieldContainer = fieldGroups.linkPath ? fieldGroups.linkPath.closest('.universal-form-field') : null;
                
                // 查找包含 col-* 的父容器
                const pathContainer = findColContainer(pathFieldContainer);
                const linkPathContainer = findColContainer(linkPathFieldContainer);
                
                if (pathContainer) pathContainer.style.display = '';
                if (linkPathContainer) {
                    linkPathContainer.style.display = 'none';
                    // 将外链地址的值复制回路由输入框
                    const pathInput = document.getElementById('path') || document.querySelector('[name="path"]');
                    const linkPathInput = document.getElementById('linkPath') || document.querySelector('[name="linkPath"]');
                    if (pathInput && linkPathInput && linkPathInput.value && !pathInput.value) {
                        pathInput.value = linkPathInput.value;
                    }
                }
                if (targetHelpText) {
                    targetHelpText.textContent = '选择链接的打开方式';
                }
            }
        }
        
        // 监听菜单类型变化
        if (typeSelect) {
            // 初始化（根据当前菜单类型）
            updateFieldsByType(typeSelect.value);
            
            // 监听变化
            typeSelect.addEventListener('change', function() {
                updateFieldsByType(this.value);
            });
        }
        
        // 监听图标输入框变化，更新预览（如果存在图标选择器）
        const iconInput = document.getElementById('icon') || document.querySelector('[name="icon"]');
        const iconPreview = document.getElementById('iconPreview');
        
        if (iconInput && iconPreview) {
            // 更新图标预览的函数
            function updateIconPreview() {
                const value = iconInput.value.trim();
                if (value) {
                    // 提取图标类名（支持 "bi bi-icon-name" 或 "icon-name" 格式）
                    const iconName = value.replace(/^bi\s+bi-?/, '').replace(/^bi-?/, '');
                    if (iconName) {
                        iconPreview.innerHTML = `<i class="bi bi-${iconName}"></i>`;
                    } else {
                        iconPreview.innerHTML = '<i class="bi bi-emoji-smile"></i>';
                    }
                } else {
                    iconPreview.innerHTML = '<i class="bi bi-emoji-smile"></i>';
                }
            }
            
            // 初始化预览（页面加载时）
            updateIconPreview();
            
            // 监听输入变化
            iconInput.addEventListener('input', updateIconPreview);
            iconInput.addEventListener('change', updateIconPreview);
        }
    }

    /**
     * 提交菜单表单
     * @param {Event} event 表单提交事件
     * @param {Object} options 配置选项
     * @param {string} options.url 提交的 URL
     * @param {string} options.method HTTP 方法（POST 或 PUT）
     * @param {string} options.successMessage 成功消息（默认：操作成功）
     * @param {string} options.errorMessage 错误消息（默认：操作失败）
     * @param {string} options.redirectUrl 成功后的跳转 URL（可选）
     */
    async function submitMenuForm(event, options = {}) {
        event.preventDefault();

        const {
            url,
            method = 'POST',
            successMessage = '操作成功',
            errorMessage = '操作失败',
            redirectUrl = null
        } = options;

        if (!url) {
            console.error('提交 URL 不能为空');
            return false;
        }

        const form = document.getElementById('menuForm');
        if (!form) {
            console.error('找不到表单元素 #menuForm');
            return false;
        }

        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        // 处理路径字段：如果是外链类型，使用 linkPath 的值
        const type = document.getElementById('type').value;
        if (type === 'link') {
            const linkPathInput = document.getElementById('linkPath');
            if (linkPathInput && linkPathInput.value) {
                data.path = linkPathInput.value;
            }
        } else {
            const pathInput = document.getElementById('path');
            if (pathInput && pathInput.value) {
                data.path = pathInput.value;
            }
        }

        // 转换数字类型
        if (data.parent_id) data.parent_id = parseInt(data.parent_id);
        if (data.sort) data.sort = parseInt(data.sort);
        if (data.status) data.status = parseInt(data.status);
        if (data.visible) data.visible = parseInt(data.visible);
        if (data.cache) data.cache = parseInt(data.cache);

        // 空值处理
        if (!data.parent_id) data.parent_id = 0;
        if (!data.sort) data.sort = 0;
        if (!data.status) data.status = 1;
        if (!data.visible) data.visible = 1;
        if (!data.cache) data.cache = 1;
        if (!data.type) data.type = 'menu';
        if (!data.target) data.target = '_self';

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.code === 200) {
                const message = result.msg || successMessage;
                
                // 优先使用 AdminIframeClient.success（如果在 iframe 中）
                if (window.AdminIframeClient && typeof window.AdminIframeClient.success === 'function') {
                    // 对于 PUT 和 POST 请求，都刷新父页
                    window.AdminIframeClient.success({
                        message: message,
                        refreshParent: true,   // 请求父页刷新当前标签
                        closeCurrent: false    // 不关闭当前标签/弹窗
                    });
                } 
                // 回退为页面跳转（不在 iframe 中时）
                else {
                    // 使用 toast 提示（如果可用）
                    if (typeof showToast === 'function') {
                        showToast('success', message, 1500);
                } else {
                        alert(message);
                    }
                    // 延迟跳转，让用户看到成功提示
                    setTimeout(() => {
                    const finalUrl = redirectUrl || url.split('/').slice(0, -1).join('/');
                    window.location.href = finalUrl;
                    }, 800);
                }
                return;
            } else {
                if (result.data && result.data.errors) {
                    let errorMsg = result.msg || errorMessage;
                    const errors = result.data.errors;
                    const errorList = Object.values(errors).flat().join('\n');
                    if (errorList) errorMsg += '\n' + errorList;
                    alert(errorMsg);
                } else {
                    alert(result.msg || errorMessage);
                }
            }
        } catch (error) {
            console.error('Error:', error);
            alert(errorMessage);
        }

        return false;
    }

    // 导出到全局
    window.MenuForm = {
        init: initMenuForm,
        submit: submitMenuForm
    };
})();

