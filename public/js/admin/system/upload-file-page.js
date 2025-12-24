/**
 * 文件管理模块前端逻辑
 */
(function () {
    'use strict';

    /**
     * 初始化文件列表页面
     * @param {Object} options
     * @param {string} options.tableId
     * @param {string} options.destroyRoute
     * @param {string} options.logPrefix
     */
    function initList(options = {}) {
        const {
            tableId = 'uploadFileTable',
            destroyRoute = '',
            logPrefix = '[UploadFile]'
        } = options;

        if (!tableId) {
            console.warn('[UploadFilePage] tableId 不能为空');
            return;
        }

        if (destroyRoute) {
            window['destroyRouteTemplate_' + tableId] = destroyRoute;
        }

        if (typeof initRefreshParentListener === 'function') {
            initRefreshParentListener(tableId, { logPrefix });
        } else {
            console.warn('[UploadFilePage] initRefreshParentListener 未加载');
        }
    }

    /**
     * 渲染文件预览
     * @param {string} fileUrl
     * @param {string} contentType
     * @param {string} originalFilename
     * @returns {string}
     */
    function renderFilePreview(fileUrl, contentType, originalFilename) {
        if (!fileUrl) {
            return '<span class="text-muted">-</span>';
        }

        const isImage = contentType && contentType.startsWith('image/');

        if (isImage) {
            return `<img src="${fileUrl}" alt="${originalFilename}" 
                         class="img-thumbnail" 
                         style="max-width: 60px; max-height: 60px; cursor: pointer; object-fit: cover;"
                         onclick="viewFile_uploadFileTable('${fileUrl}', '${originalFilename}')"
                         title="点击查看大图">`;
        }

        // 根据文件类型显示图标
        let icon = 'bi-file-earmark';
        if (contentType) {
            if (contentType.includes('pdf')) {
                icon = 'bi-file-pdf';
            } else if (contentType.includes('word') || contentType.includes('document')) {
                icon = 'bi-file-word';
            } else if (contentType.includes('excel') || contentType.includes('spreadsheet')) {
                icon = 'bi-file-excel';
            } else if (contentType.includes('zip') || contentType.includes('rar')) {
                icon = 'bi-file-zip';
            } else if (contentType.includes('video')) {
                icon = 'bi-file-play';
            } else if (contentType.includes('audio')) {
                icon = 'bi-file-music';
            }
        }

        return `<i class="bi ${icon} fs-4 text-secondary" 
                   style="cursor: pointer;"
                   onclick="viewFile_uploadFileTable('${fileUrl}', '${originalFilename}')"
                   title="点击查看文件"></i>`;
    }

    window.UploadFilePage = {
        initList,
        renderFilePreview
    };
})();






