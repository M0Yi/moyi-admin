/**
 * 用户表单专用脚本
 */
(function () {
    'use strict';

    function randomPassword(length = 12) {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    function init(options = {}) {
        const {
            passwordFieldSelector = '[data-field-name="password"] input',
            roleFieldSelector = '[data-field-name="role_ids"]',
        } = options;

        const passwordInput = document.querySelector(passwordFieldSelector);
        if (passwordInput) {
            const fieldWrapper = passwordInput.closest('[data-field-name="password"]') || passwordInput.parentElement;
            if (fieldWrapper && !fieldWrapper.querySelector('[data-user-password-actions]')) {
                const actions = document.createElement('div');
                actions.className = 'd-flex flex-wrap gap-2 mt-2';
                actions.setAttribute('data-user-password-actions', '1');
                actions.innerHTML = `
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-user-password-action="toggle">
                        <i class="bi bi-eye"></i> 显示密码
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-user-password-action="generate">
                        <i class="bi bi-magic"></i> 生成随机密码
                    </button>
                    <small class="text-muted align-self-center">安全提示：请妥善保存新密码</small>
                `;
                fieldWrapper.appendChild(actions);

                actions.addEventListener('click', event => {
                    const action = event.target.closest('[data-user-password-action]');
                    if (!action) {
                        return;
                    }
                    const type = action.getAttribute('data-user-password-action');
                    if (type === 'toggle') {
                        if (passwordInput.type === 'password') {
                            passwordInput.type = 'text';
                            action.innerHTML = '<i class="bi bi-eye-slash"></i> 隐藏密码';
                        } else {
                            passwordInput.type = 'password';
                            action.innerHTML = '<i class="bi bi-eye"></i> 显示密码';
                        }
                    } else if (type === 'generate') {
                        passwordInput.value = randomPassword();
                        passwordInput.type = 'text';
                        const toggleBtn = actions.querySelector('[data-user-password-action="toggle"]');
                        if (toggleBtn) {
                            toggleBtn.innerHTML = '<i class="bi bi-eye-slash"></i> 隐藏密码';
                        }
                    }
                });
            }
        }

        const roleField = document.querySelector(roleFieldSelector);
        if (roleField && !roleField.dataset.userRoleFieldInitialized) {
            roleField.dataset.userRoleFieldInitialized = '1';

            const summary = document.createElement('div');
            summary.className = 'text-muted small mt-2';
            summary.setAttribute('data-user-role-summary', '1');
            roleField.appendChild(summary);

            const checkboxSelector = 'input[type="checkbox"]';
            const getCheckboxes = () => Array.from(roleField.querySelectorAll(checkboxSelector));

            const updateSummary = () => {
                const total = getCheckboxes().length;
                const checked = getCheckboxes().filter(cb => cb.checked).length;
                summary.textContent = total === 0
                    ? '暂无可分配角色'
                    : `已选择 ${checked} / ${total} 个角色`;
            };

            roleField.addEventListener('change', event => {
                if (event.target.matches(checkboxSelector)) {
                    updateSummary();
                }
            });

            updateSummary();
        }
    }

    window.UserForm = {
        init,
    };
})();

