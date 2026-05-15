/**
 * 用户管理模块前端逻辑
 */
(function () {
    'use strict';

    /**
     * 初始化用户列表页面
     * @param {Object} options
     * @param {string} options.tableId
     * @param {string} options.destroyRoute
     * @param {string} options.logPrefix
     */
    function initList(options = {}) {
        const {
            tableId = 'userTable',
            destroyRoute = '',
            logPrefix = '[User]'
        } = options;

        if (!tableId) {
            console.warn('[UserPage] tableId 不能为空');
            return;
        }

        if (destroyRoute) {
            window['destroyRouteTemplate_' + tableId] = destroyRoute;
        }

        if (typeof initRefreshParentListener === 'function') {
            initRefreshParentListener(tableId, { logPrefix });
        } else {
            console.warn('[UserPage] initRefreshParentListener 未加载');
        }
    }

    /**
     * 渲染用户角色
     * @param {Array} value
     * @returns {string}
     */
    function renderUserRoles(value) {
        if (!value || !value.length) {
            return '<span class="text-muted">-</span>';
        }
        return value
            .map(role => `<span class="badge bg-info me-1">${role.name}</span>`)
            .join('');
    }

    window.UserPage = {
        initList,
        renderUserRoles
    };

    window.renderUserRoles = renderUserRoles;
})();


