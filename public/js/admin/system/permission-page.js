/**
 * 权限管理模块前端逻辑
 */
(function () {
    'use strict';

    /**
     * 初始化权限列表
     * @param {Object} options
     * @param {string} options.tableId
     * @param {string} options.destroyRoute
     * @param {string} options.logPrefix
     */
    function initList(options = {}) {
        const {
            tableId = 'permissionTable',
            destroyRoute = '',
            logPrefix = '[Permission]'
        } = options;

        if (!tableId) {
            console.warn('[PermissionPage] tableId 不能为空');
            return;
        }

        if (destroyRoute) {
            window['destroyRouteTemplate_' + tableId] = destroyRoute;
        }

        if (typeof initRefreshParentListener === 'function') {
            initRefreshParentListener(tableId, { logPrefix });
        } else {
            console.warn('[PermissionPage] initRefreshParentListener 未加载');
        }
    }

    /**
     * 渲染带缩进的权限名称
     * @param {string} value
     * @param {Object} column
     * @param {Object} row
     * @returns {string}
     */
    function renderPermissionName(value, column, row) {
        const level = row.level || 0;
        const indent = level > 0 ? '└─'.repeat(level) + ' ' : '';
        return `<div class="d-flex align-items-center permission-level-${level}">
            ${level > 0 ? `<span class="text-muted me-2">${indent}</span>` : ''}
            <span class="fw-medium">${value || '-'}</span>
        </div>`;
    }

    window.PermissionPage = {
        initList,
        renderPermissionName
    };

    window.renderPermissionName = renderPermissionName;
})();


