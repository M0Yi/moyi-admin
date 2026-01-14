;(function () {
    'use strict';

    const doc = document;
    const body = document.body;

    const state = {
        overlay: null,
        iframe: null,
        titleEl: null,
        loadingEl: null,
        newWindowBtn: null,
        newTabBtn: null,
        closeBtn: null,
        isOpen: false,
        config: null,
        handlers: [],
        listenersAttached: false  // 标记全局事件监听器是否已附加
    };

    const defaultOptions = {
        channel: 'admin-iframe-shell',
        autoCloseOnSuccess: true,
        hideActions: false  // 是否隐藏"新标签"和"新窗口"按钮
    };

    function getTabManager() {
        // 1. 优先从当前窗口获取
        if (window.Admin?.tabManager) {
            return window.Admin.tabManager;
        }
        if (window.adminTabManager) {
            return window.adminTabManager;
        }

        // 2. 如果在 iframe 中，向上查找主框架
        // 先尝试直接访问 top（最顶层窗口）
        try {
            if (window.top && window.top !== window) {
                if (window.top.Admin?.tabManager) {
                    return window.top.Admin.tabManager;
                }
                if (window.top.adminTabManager) {
                    return window.top.adminTabManager;
                }
            }
        } catch (error) {
            // ignore cross-origin access errors
        }

        // 3. 递归向上查找父窗口（处理多层 iframe 嵌套）
        try {
            let currentWindow = window.parent;
            let depth = 0;
            const maxDepth = 10; // 防止无限循环

            while (currentWindow && currentWindow !== window && depth < maxDepth) {
                try {
                    if (currentWindow.Admin?.tabManager) {
                        return currentWindow.Admin.tabManager;
                    }
                    if (currentWindow.adminTabManager) {
                        return currentWindow.adminTabManager;
                    }
                    // 继续向上查找
                    if (currentWindow.parent === currentWindow) {
                        // 已到达顶层
                        break;
                    }
                    currentWindow = currentWindow.parent;
                    depth++;
                } catch (error) {
                    // 跨域限制，无法继续向上查找
                    break;
                }
            }
        } catch (error) {
            // ignore errors
        }

        return null;
    }

    function getIframeDocumentTitle() {
        if (!state.iframe) {
            return null;
        }

        try {
            const iframeDoc = state.iframe.contentDocument || state.iframe.contentWindow?.document;
            if (!iframeDoc) {
                return null;
            }

            const title = iframeDoc.title || '';
            return title.trim() || null;
        } catch (error) {
            // ignore cross-origin access errors
            return null;
        }
    }

    function resolveNewTabTitle() {
        const iframeTitle = getIframeDocumentTitle();
        if (iframeTitle) {
            return iframeTitle;
        }

        if (state.config?.title) {
            return state.config.title;
        }

        if (state.titleEl?.textContent) {
            return state.titleEl.textContent.trim();
        }

        return '新标签';
    }

    function ensureElements() {
        if (state.overlay) {
            return;
        }

        state.overlay = doc.querySelector('[data-iframe-shell]');
        if (!state.overlay) {
            return;
        }

        state.iframe = state.overlay.querySelector('[data-iframe-shell-frame]');
        state.titleEl = state.overlay.querySelector('[data-iframe-shell-title]');
        state.loadingEl = state.overlay.querySelector('[data-iframe-shell-loading]');
        state.newWindowBtn = state.overlay.querySelector('[data-iframe-shell-open-new]');
        state.newTabBtn = state.overlay.querySelector('[data-iframe-shell-open-tab]');
        state.closeBtn = state.overlay.querySelector('[data-iframe-shell-close]');

        if (state.closeBtn) {
            state.closeBtn.addEventListener('click', () => close({ reason: 'manual-close' }));
        }

        if (state.newWindowBtn) {
            state.newWindowBtn.addEventListener('click', () => {
                if (state.config?.src) {
                    window.open(state.config.src, '_blank', 'noopener');
                }
            });
        }

        if (state.newTabBtn) {
            state.newTabBtn.addEventListener('click', () => {
                if (!state.config?.src) {
                    return;
                }

                const tabManager = getTabManager();
                const title = resolveNewTabTitle();
                const targetUrl = resolveTabUrl(state.config.src);

                if (tabManager && typeof tabManager.openTab === 'function') {
                    tabManager.openTab(targetUrl, title, 'internal');
                    close({ reason: 'open-tab' });
                    return;
                }

                window.open(targetUrl, '_blank', 'noopener');
            });
        }

        if (state.iframe) {
            state.iframe.addEventListener('load', () => {
                if (state.loadingEl) {
                    state.loadingEl.style.display = 'none';
                }
            });
        }

        // 只附加一次全局事件监听器（防止重复注册）
        if (!state.listenersAttached) {
        window.addEventListener('message', handleMessage, false);
        doc.addEventListener('keydown', handleEscape, false);
        doc.addEventListener('click', handleTriggerClick, false);
            state.listenersAttached = true;
        }
    }

    function buildUrl(src, channel) {
        try {
        const url = new URL(src, window.location.origin);
        url.searchParams.set('_embed', '1');
        if (channel) {
            url.searchParams.set('_iframe_channel', channel);
        }
        url.searchParams.set('_ts', Date.now().toString());
        return url.toString();
        } catch (error) {
            console.error('[IframeShell] buildUrl 失败:', error, 'src:', src);
            // 降级处理：如果 URL 解析失败，返回原始 src（可能不安全，但至少不会完全失败）
            return src;
        }
    }

    /**
     * 处理标题中的占位符替换
     * @param {string} title 原始标题
     * @param {Element} trigger 触发器元素
     * @returns {string} 处理后的标题
     */
    function resolveTitleWithPlaceholders(title, trigger) {
        if (!title || !title.includes('{')) {
            return title;
        }

        // 查找触发器所在的表格行
        const row = trigger.closest('tr');
        if (!row) {
            return title;
        }

        // 从表格行中提取数据
        const rowData = extractRowData(row);

        // 替换占位符
        return title.replace(/\{(\w+)\}/g, (match, fieldName) => {
            return rowData[fieldName] || match;
        });
    }

    /**
     * 从表格行中提取数据
     * @param {Element} row 表格行元素
     * @returns {object} 行数据对象
     */
    function extractRowData(row) {
        const data = {};

        // 尝试从行的data-row-data属性获取完整数据
        if (row.dataset && row.dataset.rowData) {
            try {
                // 数据在HTML属性中被转义，需要先解码
                const decodedData = row.dataset.rowData.replace(/&quot;/g, '"');
                return JSON.parse(decodedData);
            } catch (error) {
                console.warn('[IframeShell] Failed to parse row data:', error);
            }
        }

        // 如果没有data-row-data属性，尝试从单元格内容推断
        const cells = row.querySelectorAll('td');
        cells.forEach((cell, index) => {
            // 从data-field属性获取字段名（如果有的话）
            const fieldName = cell.dataset.field;
            if (fieldName) {
                data[fieldName] = cell.textContent.trim();
            }
        });

        return data;
    }

    function resolveTabUrl(sourceUrl) {
        if (!sourceUrl) {
            return '';
        }

        const adminEntry = window.ADMIN_ENTRY_PATH || '/admin';

        const normalizePath = (rawPath) => {
            const trimmed = rawPath.replace(/^\/+/, '');
            if (typeof window.adminRoute === 'function') {
                return window.adminRoute(trimmed);
            }
            return adminEntry + (trimmed ? '/' + trimmed : '');
        };

        const stripEmbedParam = (urlObj) => {
            urlObj.searchParams.delete('_embed');
            return urlObj;
        };

        try {
            const parsed = stripEmbedParam(new URL(sourceUrl, window.location.origin));
            const isSameOrigin = parsed.origin === window.location.origin;

            if (isSameOrigin) {
                if (parsed.pathname.startsWith(adminEntry)) {
                    return parsed.pathname + parsed.search + parsed.hash;
                }

                const normalized = normalizePath(parsed.pathname);
                return normalized + parsed.search + parsed.hash;
            }

            return parsed.toString();
        } catch (error) {
            // ignore and continue with fallback logic
        }

        let remaining = sourceUrl;
        let hash = '';

        const hashIndex = remaining.indexOf('#');
        if (hashIndex > -1) {
            hash = remaining.slice(hashIndex);
            remaining = remaining.slice(0, hashIndex);
        }

        let query = '';
        const queryIndex = remaining.indexOf('?');
        if (queryIndex > -1) {
            query = remaining.slice(queryIndex + 1);
            remaining = remaining.slice(0, queryIndex);
        }

        // 移除 _embed 参数
        if (query) {
            const params = new URLSearchParams(query);
            params.delete('_embed');
            query = params.toString();
        }

        const normalizedPath = remaining.startsWith(adminEntry)
            ? remaining
            : normalizePath(remaining);

        let finalUrl = normalizedPath;
        if (query) {
            finalUrl += '?' + query;
        }
        if (hash) {
            finalUrl += hash;
        }

        return finalUrl;
    }

    function open(config = {}) {
        ensureElements();

        if (!state.overlay || !state.iframe) {
            console.warn('[IframeShell] overlay markup not found.');
            return;
        }

        const options = Object.assign({}, defaultOptions, config);

        if (!options.src) {
            console.warn('[IframeShell] missing src in open() options.');
            return;
        }

        state.config = options;
        state.isOpen = true;

        if (state.titleEl) {
            state.titleEl.textContent = options.title || '嵌入页面';
        }

        if (state.loadingEl) {
            state.loadingEl.style.display = 'flex';
        }

        // 根据配置显示/隐藏"新标签"和"新窗口"按钮
        if (options.hideActions === true) {
            if (state.newTabBtn) {
                state.newTabBtn.style.display = 'none';
            }
            if (state.newWindowBtn) {
                state.newWindowBtn.style.display = 'none';
            }
        } else {
            if (state.newTabBtn) {
                state.newTabBtn.style.display = '';
            }
            if (state.newWindowBtn) {
                state.newWindowBtn.style.display = '';
            }
        }

        state.overlay.hidden = false;
        body.classList.add('iframe-shell-open');

        const iframeUrl = buildUrl(options.src, options.channel);
        state.iframe.src = iframeUrl;
    }

    function close(payload = null) {
        if (!state.overlay || !state.isOpen) {
            return;
        }

        state.overlay.hidden = true;
        state.isOpen = false;
        state.config = null;
        body.classList.remove('iframe-shell-open');

        if (state.iframe) {
            state.iframe.src = 'about:blank';
        }

        // 重置按钮显示状态（确保下次打开时状态正确）
        if (state.newTabBtn) {
            state.newTabBtn.style.display = '';
        }
        if (state.newWindowBtn) {
            state.newWindowBtn.style.display = '';
        }

        state.handlers.forEach(handler => {
            if (typeof handler === 'function') {
                handler({ action: 'after-close', payload });
            }
        });
    }

    function handleEscape(event) {
        if (event.key === 'Escape' && state.isOpen) {
            event.preventDefault();
            close({ reason: 'escape' });
        }
    }

    function handleTriggerClick(event) {
        const trigger = event.target.closest('[data-iframe-shell-trigger]');
        if (!trigger) {
            return;
        }

        event.preventDefault();

        const dataset = trigger.dataset;
        const src = dataset.iframeShellSrc || trigger.getAttribute('href');
        let title = dataset.iframeShellTitle || trigger.textContent.trim();
        const behavior = dataset.iframeShellBehavior || 'modal';
        const fallbackToWindow = dataset.iframeShellFallbackWindow !== 'false';
        const hideActions = dataset.iframeShellHideActions === 'true' || dataset.iframeShellHideActions === '1';

        if (!src) {
            console.warn('[IframeShell] missing src for trigger:', trigger);
            return;
        }

        // 处理标题中的占位符替换
        title = resolveTitleWithPlaceholders(title, trigger);

        if (behavior === 'tab') {
            openTab(src, title, { fallbackToWindow });
            return;
        }

        open({
            id: dataset.iframeShellTrigger || null,
            src: src,
            title: title,
            channel: dataset.iframeShellChannel || defaultOptions.channel,
            autoCloseOnSuccess: dataset.iframeShellAutoClose !== 'false',
            hideActions: hideActions
        });
    }

    function handleMessage(event) {
        if (!state.isOpen || !state.iframe) {
            return;
        }

        // 检查消息来源：确保来自当前打开的 iframe
        // 注意：如果 iframe 已关闭（src 为 'about:blank'），contentWindow 可能为 null
        if (state.iframe.contentWindow && event.source !== state.iframe.contentWindow) {
            return;
        }

        if (event.origin !== window.location.origin) {
            return;
        }

        const data = event.data;
        if (!data || typeof data !== 'object') {
            return;
        }

        const channel = state.config?.channel || defaultOptions.channel;
        if (data.channel && data.channel !== channel) {
            return;
        }

        // 详细日志输出：接收到的消息信息
        const timestamp = new Date().toLocaleTimeString();
        const actionName = data.action || '(无 action)';
        let logMessage = `[${timestamp}] [IframeShell] ← 收到 iframe 消息 [${actionName}]`;
        
        // 输出消息详情
        const messageDetails = {
            action: data.action,
            channel: data.channel || channel,
            source: data.source || '(未知来源)',
            payload: data.payload || null,
            fullData: data
        };
        
        console.group(`%c[IframeShell] 收到消息: ${actionName}`, 'color: #0d6efd; font-weight: bold;');
        console.log('%c时间:', 'color: #6c757d;', timestamp);
        console.log('%c频道:', 'color: #6c757d;', messageDetails.channel);
        console.log('%c来源:', 'color: #6c757d;', messageDetails.source);
        console.log('%cAction:', 'color: #198754; font-weight: bold;', actionName);
        
        if (data.payload) {
            if (typeof data.payload === 'object') {
                console.log('%cPayload:', 'color: #0dcaf0; font-weight: bold;', data.payload);
                console.log('%cPayload (JSON):', 'color: #0dcaf0;', JSON.stringify(data.payload, null, 2));
            } else {
                console.log('%cPayload:', 'color: #0dcaf0; font-weight: bold;', data.payload);
            }
        } else {
            console.log('%cPayload:', 'color: #6c757d;', '(无)');
        }
        
        console.log('%c完整消息数据:', 'color: #6c757d;', data);
        console.groupEnd();
        
        // 同时输出一行简洁的日志（兼容原有格式）
        if (data.payload) {
            if (typeof data.payload === 'object') {
                logMessage += ': ' + JSON.stringify(data.payload, null, 2);
            } else {
                logMessage += ': ' + data.payload;
            }
        }
        console.log(logMessage);

        // 显示消息提示（如果 payload 中包含 message，且不是 refresh-main，避免重复提示）
        const shouldShowToast = data.payload 
            && typeof data.payload === 'object' 
            && data.payload.message 
            && data.action !== 'refresh-main';
        if (shouldShowToast) {
            showMessageToast(data.action, data.payload.message, data.payload.toastType);
        }

        state.handlers.forEach(handler => {
            try {
                handler(data, state.config);
            } catch (error) {
                console.error('[IframeShell] message handler error:', error);
            }
        });

        if (data.action === 'close') {
            console.log(`[IframeShell] 处理 action: close, 关闭 iframe shell`);
            close({ reason: 'message-close', data });
        }

        if (data.action === 'success' && state.config?.autoCloseOnSuccess !== false) {
            console.log(`[IframeShell] 处理 action: success, 自动关闭 iframe shell`);
            close({ reason: 'message-success', data });
        }

        if (data.action === 'refresh-main') {
            console.log(`[IframeShell] 处理 action: refresh-main, 触发主框架刷新`);
            triggerMainFrameRefresh(data.payload || {});
        }

        // 处理 refreshParent: true 消息
        // 智能查找最近的父级列表页面并刷新，而不是一直传递到最顶层
        // 支持多种类型：true, "true", 1
        const refreshParent = data.payload && typeof data.payload === 'object' 
            ? (data.payload.refreshParent === true || 
               data.payload.refreshParent === 'true' || 
               data.payload.refreshParent === 1)
            : false;
        
        if (refreshParent) {
            console.log(`[IframeShell] 收到 refreshParent: true 消息，查找最近的父级列表页面`);
            
            // 智能检测：查找所有 loadData_* 函数（支持不同的 tableId）
            // 优先检测 loadData_dataTable（兼容旧代码），然后检测其他 loadData_* 函数
            let loadDataFunctions = [];
            try {
                loadDataFunctions = Object.keys(window).filter(key => 
                    key.startsWith('loadData_') && typeof window[key] === 'function'
                );
            } catch (error) {
                console.warn('[IframeShell] 检测 loadData_* 函数失败:', error);
                // 降级：直接检查常见的函数名
            if (typeof window.loadData_dataTable === 'function') {
                    loadDataFunctions = ['loadData_dataTable'];
                }
            }
            
            if (loadDataFunctions.length > 0) {
                // 优先使用 loadData_dataTable（如果存在）
                const functionName = loadDataFunctions.includes('loadData_dataTable') 
                    ? 'loadData_dataTable' 
                    : loadDataFunctions[0];
                
                console.log(`[IframeShell] 当前页面是列表页面，检测到刷新函数: ${functionName}`, {
                    allFunctions: loadDataFunctions,
                    selectedFunction: functionName
                });
                
                try {
                    window[functionName]();
                    console.log(`[IframeShell] 已触发当前页面的 ${functionName}() 刷新数据表`);
                    return; // 在当前页面处理完成，不再向上传递
                } catch (error) {
                    console.warn(`[IframeShell] 调用 ${functionName}() 失败:`, error);
                    // 如果调用失败，继续向上传递
                }
            }
            
            // 如果当前页面不是列表页面，或者处理失败，则向上传递到父窗口
            try {
                const channel = state.config?.channel || defaultOptions.channel;
                // 如果当前窗口不是顶层窗口，将消息转发到父窗口
                if (window.self !== window.top) {
                    window.parent.postMessage({
                        channel: channel,
                        action: data.action || 'refresh-parent',
                        payload: data.payload,
                        source: 'iframe-shell',
                        originalAction: data.action
                    }, window.location.origin);
                    console.log(`[IframeShell] 当前页面不是列表页面，已将 refreshParent 消息转发到父窗口`);
                } else {
                    // 如果当前窗口就是顶层窗口，直接触发自定义事件
                    console.log(`[IframeShell] 当前窗口是顶层窗口，触发 refreshParent 事件`);
                    window.dispatchEvent(new CustomEvent('refreshParent', {
                        detail: data.payload
                    }));
                }
            } catch (error) {
                console.warn('[IframeShell] 转发 refreshParent 消息失败:', error);
            }
        }
    }

    function onMessage(handler) {
        if (typeof handler === 'function') {
            state.handlers.push(handler);
        }
        return () => {
            const index = state.handlers.indexOf(handler);
            if (index > -1) {
                state.handlers.splice(index, 1);
            }
        };
    }

    /**
     * 直接打开新标签（不通过 iframe shell）
     * @param {string} url - 要打开的 URL
     * @param {string} title - 标签页标题（可选）
     * @param {object} options - 选项（可选）
     * @param {boolean} options.fallbackToWindow - 如果 TabManager 不可用，是否降级使用 window.open（默认：true）
     * @returns {boolean} 是否成功打开
     */
    function openTab(url, title, options = {}) {
        if (!url) {
            console.warn('[IframeShell.openTab] missing url parameter.');
            return false;
        }

        const {
            fallbackToWindow = true
        } = options;

        const tabManager = getTabManager();

        if (!tabManager) {
            const isInIframe = window.self !== window.top;
            const errorMsg = isInIframe
                ? 'TabManager 未找到。当前页面在 iframe 中，但无法访问主框架的 TabManager。'
                : 'TabManager 未找到。当前页面不在管理后台主框架中。';

            console.warn('[IframeShell.openTab]', errorMsg);

            if (fallbackToWindow) {
                const resolvedUrl = resolveTabUrl(url);
                window.open(resolvedUrl, '_blank', 'noopener');
                return true;
            }

            return false;
        }

        if (typeof tabManager.openTab !== 'function') {
            console.warn('[IframeShell.openTab] TabManager.openTab method not available.');

            if (fallbackToWindow) {
                const resolvedUrl = resolveTabUrl(url);
                window.open(resolvedUrl, '_blank', 'noopener');
                return true;
            }

            return false;
        }

        try {
            const resolvedUrl = resolveTabUrl(url);
            const resolvedTitle = title || '新标签';
            tabManager.openTab(resolvedUrl, resolvedTitle, 'internal');
            return true;
        } catch (error) {
            console.error('[IframeShell.openTab] failed to open tab:', error);

            if (fallbackToWindow) {
                try {
                    const resolvedUrl = resolveTabUrl(url);
                    window.open(resolvedUrl, '_blank', 'noopener');
                    return true;
                } catch (fallbackError) {
                    console.error('[IframeShell.openTab] fallback also failed:', fallbackError);
                }
            }

            return false;
        }
    }

    /**
     * 关闭当前标签页
     * @param {object} options - 选项（可选）
     * @param {boolean} options.fallbackToHistory - 如果 TabManager 不可用，是否降级使用 history.back()（默认：true）
     * @returns {boolean} 是否成功关闭
     */
    function closeCurrentTab(options = {}) {
        const {
            fallbackToHistory = true
        } = options;

        const tabManager = getTabManager();

        if (!tabManager) {
            const isInIframe = window.self !== window.top;
            const errorMsg = isInIframe
                ? 'TabManager 未找到。当前页面在 iframe 中，但无法访问主框架的 TabManager。'
                : 'TabManager 未找到。当前页面不在管理后台主框架中。';

            console.warn('[IframeShell.closeCurrentTab]', errorMsg);

            if (fallbackToHistory) {
                // 如果在 iframe 中，尝试关闭 iframe shell
                if (isInIframe && window.AdminIframeClient && typeof window.AdminIframeClient.close === 'function') {
                    try {
                        window.AdminIframeClient.close({ reason: 'closeCurrentTab' });
                        return true;
                    } catch (error) {
                        console.warn('[IframeShell.closeCurrentTab] AdminIframeClient.close failed:', error);
                    }
                }

                // 降级使用浏览器历史记录
                if (window.history.length > 1) {
                    window.history.back();
                    return true;
                } else {
                    console.warn('[IframeShell.closeCurrentTab] Cannot go back: no history.');
                    return false;
                }
            }

            return false;
        }

        if (typeof tabManager.closeCurrentTab !== 'function') {
            console.warn('[IframeShell.closeCurrentTab] TabManager.closeCurrentTab method not available.');

            if (fallbackToHistory) {
                // 如果在 iframe 中，尝试关闭 iframe shell
                if (window.self !== window.top && window.AdminIframeClient && typeof window.AdminIframeClient.close === 'function') {
                    try {
                        window.AdminIframeClient.close({ reason: 'closeCurrentTab' });
                        return true;
                    } catch (error) {
                        console.warn('[IframeShell.closeCurrentTab] AdminIframeClient.close failed:', error);
                    }
                }

                // 降级使用浏览器历史记录
                if (window.history.length > 1) {
                    window.history.back();
                    return true;
                } else {
                    console.warn('[IframeShell.closeCurrentTab] Cannot go back: no history.');
                    return false;
                }
            }

            return false;
        }

        try {
            tabManager.closeCurrentTab();
            return true;
        } catch (error) {
            console.error('[IframeShell.closeCurrentTab] failed to close tab:', error);

            if (fallbackToHistory) {
                // 如果在 iframe 中，尝试关闭 iframe shell
                if (window.self !== window.top && window.AdminIframeClient && typeof window.AdminIframeClient.close === 'function') {
                    try {
                        window.AdminIframeClient.close({ reason: 'closeCurrentTab' });
                        return true;
                    } catch (fallbackError) {
                        console.error('[IframeShell.closeCurrentTab] AdminIframeClient.close also failed:', fallbackError);
                    }
                }

                // 降级使用浏览器历史记录
                if (window.history.length > 1) {
                    try {
                        window.history.back();
                        return true;
                    } catch (historyError) {
                        console.error('[IframeShell.closeCurrentTab] history.back() also failed:', historyError);
                    }
                }
            }

            return false;
        }
    }

    function getCurrentIframeChannel() {
        if (window.AdminIframeClient?.channel) {
            return window.AdminIframeClient.channel;
        }

        try {
            const params = new URLSearchParams(window.location.search);
            const channel = params.get('_iframe_channel');
            if (channel) {
                return channel;
            }
        } catch (error) {
            // ignore parse errors
        }

        return defaultOptions.channel;
    }

    function bubbleRefreshToParent(options) {
        if (window.self === window.top) {
            return false;
        }

        if (window.AdminIframeClient && typeof window.AdminIframeClient.refreshMainFrame === 'function') {
            try {
                window.AdminIframeClient.refreshMainFrame(options);
                return true;
            } catch (error) {
                console.warn('[IframeShell] Bubble refresh via AdminIframeClient failed:', error);
            }
        }

        try {
            const channel = getCurrentIframeChannel();
            window.parent.postMessage({
                channel: channel,
                action: 'refresh-main',
                payload: options
            }, window.location.origin);
            return true;
        } catch (error) {
            console.warn('[IframeShell] Direct postMessage bubble failed:', error);
        }

        return false;
    }

    /**
     * 显示消息提示
     * @param {string} action - 消息 action
     * @param {string} message - 提示消息
     * @param {string} toastType - 提示类型 (success, danger, warning, info)
     */
    function showMessageToast(action, message, toastType) {
        if (!message || typeof message !== 'string') {
            return;
        }

        // 根据 action 确定默认的提示类型
        let type = toastType || 'info';
        if (!toastType) {
            switch (action) {
                case 'success':
                    type = 'success';
                    break;
                case 'error':
                case 'danger':
                    type = 'danger';
                    break;
                case 'warning':
                    type = 'warning';
                    break;
                default:
                    type = 'info';
            }
        }

        // 尝试使用全局的 showToast 函数
        try {
            if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                window.Admin.utils.showToast(type, message);
                console.log(`[IframeShell] 显示提示: [${type}] ${message}`);
                return;
            }
            
            if (window.showToast && typeof window.showToast === 'function') {
                window.showToast(type, message);
                console.log(`[IframeShell] 显示提示: [${type}] ${message}`);
                return;
            }
        } catch (e) {
            console.warn('[IframeShell] 显示提示失败:', e);
        }

        // 降级方案：使用 alert
        console.log(`[IframeShell] 提示消息: [${type}] ${message}`);
        alert(message);
    }

    function triggerMainFrameRefresh(payload) {
        const options = Object.assign({
            message: '系统配置已更新，正在刷新主框架...',
            toastType: 'info',
            showToast: true,
            delay: 0
        }, payload || {});

        if (bubbleRefreshToParent(options)) {
            return;
        }

        // 如果当前窗口就是主框架且存在 TabManager，则交由 TabManager 统一处理，避免重复提示与多次刷新
        const hasTabManager = window.self === window.top 
            && window.Admin 
            && window.Admin.tabManager;
        if (hasTabManager) {
            console.log('[IframeShell] 检测到 TabManager，refresh-main 交由其处理');
            return;
        }

        if (options.showToast) {
            try {
                const toastType = options.toastType || 'info';
                if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                    window.Admin.utils.showToast(toastType, options.message);
                } else if (window.showToast && typeof window.showToast === 'function') {
                    window.showToast(toastType, options.message);
                }
            } catch (error) {
                console.warn('[IframeShell] Failed to show refresh toast:', error);
            }
        }

        const reload = () => {
            try {
                window.location.reload();
            } catch (error) {
                console.warn('[IframeShell] window.location.reload failed, fallback to hard reload:', error);
                window.location.href = window.location.href;
            }
        };

        const delay = typeof options.delay === 'number' ? options.delay : parseInt(options.delay, 10);
        if (delay && !Number.isNaN(delay) && delay > 0) {
            setTimeout(reload, delay);
        } else {
            reload();
        }
    }

    ensureElements();

    window.Admin = window.Admin || {};
    window.Admin.iframeShell = {
        open,
        close,
        onMessage,
        openTab,
        closeCurrentTab
    };
})(); 

