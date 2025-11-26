/**
 * 角色表单专用脚本
 */
(function () {
    'use strict';

    const DATA_INITIALIZED = 'roleFormInitialized';

    function init(options = {}) {
        const {
            permissionFieldSelector = '[data-field-name="permission_ids"]'
        } = options;

        const container = document.querySelector(permissionFieldSelector);
        if (!container || container.dataset[DATA_INITIALIZED]) {
            return;
        }
        container.dataset[DATA_INITIALIZED] = '1';

        const checkboxSelector = 'input[type="checkbox"]';

        const toolbar = document.createElement('div');
        toolbar.className = 'role-permission-toolbar d-flex flex-wrap align-items-center gap-2 mb-2';
        toolbar.innerHTML = `
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary" data-role-action="select-all">
                    <i class="bi bi-check2-all"></i> 全选
                </button>
                <button type="button" class="btn btn-outline-secondary" data-role-action="clear-all">
                    <i class="bi bi-x-lg"></i> 全不选
                </button>
                <button type="button" class="btn btn-outline-secondary" data-role-action="invert">
                    <i class="bi bi-arrow-repeat"></i> 反选
                </button>
            </div>
            <div class="d-flex flex-grow-1 flex-wrap align-items-center gap-2 ms-auto">
                <small class="text-muted" data-role-summary>已选择 0 项</small>
                <div class="input-group input-group-sm" style="max-width: 220px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" placeholder="搜索权限" data-role-permission-search>
                </div>
            </div>
        `;

        container.insertBefore(toolbar, container.firstChild);

        const searchInput = toolbar.querySelector('[data-role-permission-search]');
        const summaryEl = toolbar.querySelector('[data-role-summary]');

        const getCheckboxes = () => Array.from(container.querySelectorAll(checkboxSelector));

        function updateSummary() {
            const total = getCheckboxes().length;
            const checked = getCheckboxes().filter(cb => cb.checked).length;
            if (summaryEl) {
                summaryEl.textContent = `已选择 ${checked} / ${total} 项`;
            }
        }

        function applyFilter(keyword) {
            const lowerKeyword = keyword.trim().toLowerCase();
            getCheckboxes().forEach(cb => {
                const wrapper = cb.closest('.form-check');
                if (!wrapper) {
                    return;
                }
                if (!lowerKeyword) {
                    wrapper.style.display = '';
                    return;
                }
                const label = wrapper.querySelector('.form-check-label');
                const text = label ? label.textContent.toLowerCase() : '';
                wrapper.style.display = text.includes(lowerKeyword) ? '' : 'none';
            });
        }

        toolbar.addEventListener('click', event => {
            const action = event.target.closest('[data-role-action]');
            if (!action) {
                return;
            }
            const type = action.getAttribute('data-role-action');
            if (type === 'select-all') {
                getCheckboxes().forEach(cb => {
                    if (!cb.disabled) {
                        cb.checked = true;
                        cb.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            } else if (type === 'clear-all') {
                getCheckboxes().forEach(cb => {
                    if (!cb.disabled) {
                        cb.checked = false;
                        cb.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            } else if (type === 'invert') {
                getCheckboxes().forEach(cb => {
                    if (!cb.disabled) {
                        cb.checked = !cb.checked;
                        cb.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            }
            updateSummary();
        });

        if (searchInput) {
            searchInput.addEventListener('input', event => {
                applyFilter(event.target.value);
            });
        }

        container.addEventListener('change', event => {
            if (event.target.matches(checkboxSelector)) {
                updateSummary();
            }
        });

        updateSummary();
    }

    window.RoleForm = {
        init
    };
})();


