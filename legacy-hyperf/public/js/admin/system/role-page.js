/**
 * 角色管理模块前端逻辑
 */
(function () {
    'use strict';

    /**
     * 初始化角色列表页面
     * @param {Object} options
     * @param {string} options.tableId
     * @param {string} options.destroyRoute
     * @param {string} options.logPrefix
     */
    function initList(options = {}) {
        const {
            tableId = 'roleTable',
            destroyRoute = '',
            logPrefix = '[Role]'
        } = options;

        if (!tableId) {
            console.warn('[RolePage] tableId 不能为空');
            return;
        }

        // 设置删除路由模板（供通用表格组件使用）
        if (destroyRoute) {
            window['destroyRouteTemplate_' + tableId] = destroyRoute;
        }

        if (typeof initRefreshParentListener === 'function') {
            initRefreshParentListener(tableId, { logPrefix });
        } else {
            console.warn('[RolePage] initRefreshParentListener 未加载');
        }
    }

    /**
     * 渲染权限数量
     * @param {Array} value
     * @returns {string}
     */
    function renderPermissionsCount(value) {
        if (!value || !value.length) {
            return '<span class="badge bg-secondary">0</span>';
        }
        return `<span class="badge bg-info">${value.length}</span>`;
    }

    window.RolePage = {
        initList,
        renderPermissionsCount
    };

    // 数据表格自定义渲染函数需要挂载到全局
    window.renderRolePermissionsCount = renderPermissionsCount;
})();


