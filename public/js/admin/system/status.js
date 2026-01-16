/**
 * 系统状态管理 JavaScript
 * 用于检查和显示系统状态信息，包括热更新状态
 */

(function () {
    'use strict';

    /**
     * 检查热更新状态
     */
    function checkWatcherStatus() {
        return fetch('/admin/api/system/sites/status')
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络请求失败');
                }
                return response.json();
            })
            .then(data => {
                if (data.code !== 200) {
                    throw new Error(data.msg || data.message || '获取状态失败');
                }
                return data.data;
            });
    }

    /**
     * 显示热更新状态
     * @param {boolean} isRunning - 是否正在运行
     */
    function displayWatcherStatus(isRunning) {
        const statusElement = document.getElementById('watcher-status');
        const statusBadge = document.getElementById('watcher-badge');

        if (!statusElement || !statusBadge) {
            return;
        }

        if (isRunning) {
            statusElement.textContent = '热更新监听器正在运行';
            statusBadge.className = 'badge bg-success';
            statusBadge.textContent = '运行中';
        } else {
            statusElement.textContent = '热更新监听器未运行';
            statusBadge.className = 'badge bg-warning';
            statusBadge.textContent = '未运行';
        }
    }

    /**
     * 初始化系统状态检查
     */
    function initSystemStatus() {
        // 检查热更新状态
        checkWatcherStatus()
            .then(status => {
                displayWatcherStatus(status.watcher_running);

                // 可以在这里添加其他状态检查
                console.log('系统状态:', status);
            })
            .catch(error => {
                console.error('检查系统状态失败:', error);
                displayWatcherStatus(false);
            });
    }

    /**
     * 定期检查状态（可选）
     * 每30秒检查一次
     */
    function startPeriodicCheck() {
        setInterval(() => {
            checkWatcherStatus()
                .then(status => {
                    displayWatcherStatus(status.watcher_running);
                })
                .catch(error => {
                    console.error('定期检查失败:', error);
                });
        }, 30000); // 30秒
    }

    // 页面加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSystemStatus);
    } else {
        initSystemStatus();
    }

    // 导出函数供其他模块使用
    window.SystemStatus = {
        checkWatcherStatus: checkWatcherStatus,
        displayWatcherStatus: displayWatcherStatus,
        startPeriodicCheck: startPeriodicCheck
    };

})();