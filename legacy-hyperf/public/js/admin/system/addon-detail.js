/**
 * 插件详情页面 JavaScript
 *
 * 包含插件详情页面的所有交互逻辑
 */

(function() {
    'use strict';

    // 全局变量，存储路由配置
    let addonRoutes = {};

    /**
     * 初始化插件详情页面
     * @param {Object} options 配置选项
     */
    function initAddonDetailPage(options = {}) {
        // 保存路由配置
        addonRoutes = options.routes || {};
    }

    /**
     * 获取API路由
     * @param {string} path 路径
     * @returns {string} 完整URL
     */
    function getAddonRoute(path = '') {
        const baseUrl = addonRoutes.base || '/admin/admin/system/addons';
        return path ? `${baseUrl}/${path}` : baseUrl;
    }

    /**
     * 安装插件
     */
    window.installAddon = function(addonId) {
        if (!confirm('确定要安装此插件吗？')) {
            return;
        }

        fetch(getAddonRoute(`${addonId}/install`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                if (typeof showToast === 'function') {
                    showToast('success', '安装成功');
                }
                location.reload();
            } else {
                if (typeof showToast === 'function') {
                    showToast('danger', data.msg || '安装失败');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
                showToast('danger', '安装失败');
            }
        });
    };

    /**
     * 启用插件
     */
    window.enableAddon = function(addonId) {
        fetch(getAddonRoute(`${addonId}/enable`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                if (typeof showToast === 'function') {
                    showToast('success', '启用成功');
                }
                location.reload();
            } else {
                if (typeof showToast === 'function') {
                    showToast('danger', data.msg || '启用失败');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
                showToast('danger', '启用失败');
            }
        });
    };

    /**
     * 禁用插件
     */
    window.disableAddon = function(addonId) {
        fetch(getAddonRoute(`${addonId}/disable`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                if (typeof showToast === 'function') {
                    showToast('success', '禁用成功');
                }
                location.reload();
            } else {
                if (typeof showToast === 'function') {
                    showToast('danger', data.msg || '禁用失败');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
                showToast('danger', '禁用失败');
            }
        });
    };

    /**
     * 卸载插件
     */
    window.uninstallAddon = function(addonId) {
        if (!confirm('确定要卸载此插件吗？卸载后相关文件将被删除。')) {
            return;
        }

        fetch(getAddonRoute(`${addonId}/uninstall`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                if (typeof showToast === 'function') {
                    showToast('success', '卸载成功');
                }
                location.reload();
            } else {
                if (typeof showToast === 'function') {
                    showToast('danger', data.msg || '卸载失败');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
                showToast('danger', '卸载失败');
            }
        });
    };

    // 导出初始化函数
    window.initAddonDetailPage = initAddonDetailPage;

})();
