/**
 * 权限表单专用脚本
 */
(function () {
    'use strict';

    function generateSlug(value) {
        return value
            .trim()
            .replace(/[\s\/]+/g, '.')   // 空格或 / 转为 .
            .replace(/[^\w\.\-]+/g, '') // 移除非法字符
            .replace(/\.+/g, '.')       // 折叠多个 .
            .replace(/^\./, '')
            .replace(/\.$/, '')
            .toLowerCase();
    }

    function init(options = {}) {
        const {
            nameFieldId = 'name',
            slugFieldId = 'slug',
            typeFieldId = 'type',
            pathFieldSelector = '[data-field-name="path"]'
        } = options;

        const nameInput = document.getElementById(nameFieldId);
        const slugInput = document.getElementById(slugFieldId);
        const typeSelect = document.getElementById(typeFieldId);
        const pathField = document.querySelector(pathFieldSelector);
        const typeField = document.querySelector('[data-field-name="type"]');

        if (nameInput && slugInput) {
            const setSlug = () => {
                if (slugInput.dataset.userEdited === '1') {
                    return;
                }
                const slugValue = generateSlug(nameInput.value);
                slugInput.value = slugValue;
            };

            nameInput.addEventListener('input', setSlug);

            slugInput.addEventListener('input', () => {
                if (slugInput.value.trim().length === 0) {
                    slugInput.dataset.userEdited = '';
                } else {
                    slugInput.dataset.userEdited = '1';
                }
            });

            // 初始化时生成一次
            if (!slugInput.value) {
                setSlug();
            }
        }

        if (typeSelect) {
            const typeDescriptions = {
                menu: '<i class="bi bi-list"></i> 菜单权限：通常对应前端菜单/页面访问权限',
                button: '<i class="bi bi-lightning"></i> 按钮权限：对应具体的按钮或接口操作'
            };

            let typeTip = typeField ? typeField.querySelector('[data-permission-type-tip]') : null;
            if (!typeTip && typeField) {
                typeTip = document.createElement('div');
                typeTip.className = 'form-text text-muted mt-1';
                typeTip.setAttribute('data-permission-type-tip', '1');
                typeField.appendChild(typeTip);
            }

            const updateTypeInfo = value => {
                if (typeTip) {
                    typeTip.innerHTML = typeDescriptions[value] || '';
                }
                if (pathField) {
                    const formText = pathField.querySelector('.form-text');
                    if (formText) {
                        if (value === 'button') {
                            formText.textContent = '可选：填写对应接口路径（含通配符），用于后端拦截';
                        } else {
                            formText.textContent = '路由路径，用于菜单或页面访问控制，支持 * 通配';
                        }
                    }
                }
            };

            typeSelect.addEventListener('change', event => {
                updateTypeInfo(event.target.value);
            });

            updateTypeInfo(typeSelect.value);
        }
    }

    window.PermissionForm = {
        init
    };
})();


