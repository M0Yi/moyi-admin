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
        handlers: []
    };

    const defaultOptions = {
        channel: 'admin-iframe-shell',
        autoCloseOnSuccess: true
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

        window.addEventListener('message', handleMessage, false);
        doc.addEventListener('keydown', handleEscape, false);
        doc.addEventListener('click', handleTriggerClick, false);
    }

    function buildUrl(src, channel) {
        const url = new URL(src, window.location.origin);
        url.searchParams.set('_embed', '1');
        if (channel) {
            url.searchParams.set('_iframe_channel', channel);
        }
        url.searchParams.set('_ts', Date.now().toString());
        return url.toString();
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
        const title = dataset.iframeShellTitle || trigger.textContent.trim();
        const behavior = dataset.iframeShellBehavior || 'modal';
        const fallbackToWindow = dataset.iframeShellFallbackWindow !== 'false';

        if (!src) {
            console.warn('[IframeShell] missing src for trigger:', trigger);
            return;
        }

        if (behavior === 'tab') {
            openTab(src, title, { fallbackToWindow });
            return;
        }

        open({
            id: dataset.iframeShellTrigger || null,
            src: src,
            title: title,
            channel: dataset.iframeShellChannel || defaultOptions.channel,
            autoCloseOnSuccess: dataset.iframeShellAutoClose !== 'false'
        });
    }

    function handleMessage(event) {
        if (!state.isOpen || !state.iframe) {
            return;
        }

        if (event.source !== state.iframe.contentWindow) {
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

        state.handlers.forEach(handler => {
            try {
                handler(data, state.config);
            } catch (error) {
                console.error('[IframeShell] message handler error:', error);
            }
        });

        if (data.action === 'close') {
            close({ reason: 'message-close', data });
        }

        if (data.action === 'success' && state.config?.autoCloseOnSuccess !== false) {
            close({ reason: 'message-success', data });
        }

        if (data.action === 'refresh-main') {
            triggerMainFrameRefresh(data.payload || {});
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

