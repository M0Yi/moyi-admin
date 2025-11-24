/**
 * 通用刷新父页面监听器
 * 
 * 功能：
 * 1. 监听来自 iframe 的 refreshParent 消息
 * 2. 自动检测并调用当前页面的 loadData_* 函数刷新数据表
 * 3. 支持频道过滤（可选）
 * 4. 支持自定义事件（用于 iframe-shell 在顶层窗口时触发）
 * 
 * 使用方法：
 * 1. 在列表页面引入此文件
 * 2. 调用 initRefreshParentListener(tableId, options) 初始化
 * 
 * 示例：
 * ```javascript
 * // 方式1：自动检测（推荐）
 * initRefreshParentListener('menuTable');
 * 
 * // 方式2：指定多个 tableId
 * initRefreshParentListener(['menuTable', 'dataTable']);
 * 
 * // 方式3：带选项
 * initRefreshParentListener('menuTable', {
 *     channel: 'menu-channel',  // 可选：频道过滤
 *     logPrefix: '[Menu]'       // 可选：日志前缀
 * });
 * ```
 */

(function() {
    'use strict';

    /**
     * 初始化刷新父页面监听器
     * 
     * @param {string|string[]} tableId - 数据表格的 ID，可以是单个字符串或数组
     * @param {Object} options - 配置选项
     * @param {string} options.channel - 消息频道（可选，如果设置则只处理该频道的消息）
     * @param {string} options.logPrefix - 日志前缀（可选，用于调试）
     * @param {Function} options.onRefresh - 刷新前的回调函数（可选）
     */
    window.initRefreshParentListener = function(tableId, options = {}) {
        // 标准化参数
        const tableIds = Array.isArray(tableId) ? tableId : [tableId];
        const channel = options.channel || null;
        const logPrefix = options.logPrefix || '[RefreshParentListener]';
        const onRefresh = options.onRefresh || null;

        /**
         * 查找并调用刷新函数
         * @returns {boolean} 是否成功调用了刷新函数
         */
        function refreshDataTable() {
            // 遍历所有可能的 tableId，查找对应的 loadData_* 函数
            for (const id of tableIds) {
                const functionName = 'loadData_' + id;
                if (typeof window[functionName] === 'function') {
                    console.log(`${logPrefix} 找到刷新函数 ${functionName}，开始刷新数据表`);
                    
                    // 执行刷新前的回调
                    if (onRefresh && typeof onRefresh === 'function') {
                        try {
                            onRefresh(id, functionName);
                        } catch (error) {
                            console.warn(`${logPrefix} 执行 onRefresh 回调失败:`, error);
                        }
                    }
                    
                    // 调用刷新函数
                    try {
                        window[functionName]();
                        console.log(`${logPrefix} 已触发 ${functionName}() 刷新数据表`);
                        return true;
                    } catch (error) {
                        console.error(`${logPrefix} 调用 ${functionName}() 失败:`, error);
                        return false;
                    }
                }
            }
            
            // 如果没有找到对应的函数，尝试自动检测所有 loadData_* 函数
            const allLoadDataFunctions = Object.keys(window).filter(key => 
                key.startsWith('loadData_') && typeof window[key] === 'function'
            );
            
            if (allLoadDataFunctions.length > 0) {
                console.log(`${logPrefix} 未找到指定的刷新函数，但检测到以下函数:`, allLoadDataFunctions);
                // 使用第一个找到的函数
                const functionName = allLoadDataFunctions[0];
                try {
                    window[functionName]();
                    console.log(`${logPrefix} 已触发 ${functionName}() 刷新数据表（自动检测）`);
                    return true;
                } catch (error) {
                    console.error(`${logPrefix} 调用 ${functionName}() 失败:`, error);
                    return false;
                }
            } else {
                console.warn(`${logPrefix} 未找到任何 loadData_* 函数，无法刷新数据表`);
                console.warn(`${logPrefix} 期望的函数名:`, tableIds.map(id => 'loadData_' + id));
                return false;
            }
        }

        /**
         * 处理 refreshParent 消息
         * @param {Object} payload - 消息负载
         * @param {string} source - 消息来源（用于日志）
         */
        function handleRefreshParent(payload, source = 'unknown') {
            if (payload && typeof payload === 'object' && payload.refreshParent === true) {
                console.log(`${logPrefix} 收到 refreshParent 消息，刷新数据表`, {
                    source: source,
                    payload: payload,
                    tableIds: tableIds
                });
                
                refreshDataTable();
            }
        }

        // 监听 postMessage 消息
        window.addEventListener('message', function(event) {
            // 安全检查：只处理同源消息
            if (event.origin !== window.location.origin) {
                return;
            }

            const data = event.data;
            if (!data || typeof data !== 'object') {
                return;
            }

            // 检查频道是否匹配（如果设置了频道则必须匹配）
            if (channel && data.channel && data.channel !== channel) {
                return;
            }

            // 检查 payload 中是否包含 refreshParent: true
            // 支持两种消息格式：
            // 1. { action: 'success', payload: { refreshParent: true, ... } }
            // 2. { action: 'refresh-parent', payload: { refreshParent: true, ... } }
            const payload = data.payload;
            if (payload && typeof payload === 'object' && payload.refreshParent === true) {
                handleRefreshParent(payload, data.source || 'postMessage');
            }
        });

        // 同时监听自定义事件（用于 iframe-shell 在顶层窗口时触发）
        window.addEventListener('refreshParent', function(event) {
            const payload = event.detail;
            handleRefreshParent(payload, 'CustomEvent');
        });

        console.log(`${logPrefix} 刷新父页面监听器已初始化`, {
            tableIds: tableIds,
            channel: channel || 'all',
            logPrefix: logPrefix
        });
    };

    // 如果页面已经加载完成，可以自动初始化（可选）
    // 通过 data-refresh-parent-table-id 属性自动初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            autoInitFromDataAttribute();
        });
    } else {
        autoInitFromDataAttribute();
    }

    /**
     * 从 data 属性自动初始化（可选功能）
     */
    function autoInitFromDataAttribute() {
        const container = document.querySelector('[data-refresh-parent-table-id]');
        if (container) {
            const tableId = container.getAttribute('data-refresh-parent-table-id');
            const channel = container.getAttribute('data-refresh-parent-channel');
            const logPrefix = container.getAttribute('data-refresh-parent-log-prefix');
            
            if (tableId) {
                const options = {};
                if (channel) options.channel = channel;
                if (logPrefix) options.logPrefix = logPrefix;
                
                initRefreshParentListener(tableId, options);
            }
        }
    }
})();

