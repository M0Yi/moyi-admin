/**
 * 后台多标签页管理器
 * 
 * 功能：
 * - 支持多标签页切换
 * - 使用 iframe 加载页面内容
 * - 标签页的打开、关闭、刷新、保活
 * - 支持内部路由和外部链接
 */

(function() {
    'use strict';

    // 检查是否在主框架中（非 iframe）
    if (window.self !== window.top) {
        return; // 在 iframe 中不执行
    }

    const TAB_SUFFIX_KEYWORDS = [
        '管理后台',
        '后台管理',
        'admin panel',
        'admin system',
        'admin console'
    ];

    // Tab 管理器类
    class AdminTabManager {
        constructor(container) {
            this.container = container;
            this.tabList = container.querySelector('[data-role="tab-list"]');
            this.tabPanels = container.querySelector('[data-role="tab-panels"]');
            this.emptyState = container.querySelector('[data-role="empty-state"]');
            this.tabRefreshButton = this.tabList
                ? this.tabList.querySelector('[data-role="tab-refresh"]')
                : null;
            this.tabs = new Map(); // 存储所有标签页 {id: {element, panel, url, title, iframe}}
            this.activeTabId = null;
            this.maxTabs = 20; // 最大标签页数量

            this.init();
        }

        init() {
            // 绑定工具栏按钮事件
            this.bindToolbarEvents();

            // 绑定侧边栏菜单点击事件
            this.bindSidebarEvents();

            // 处理初始 URL（如果有）
            this.handleInitialUrl();

            // 监听浏览器前进后退
            window.addEventListener('popstate', (e) => {
                if (e.state && e.state.tabId) {
                    this.switchTab(e.state.tabId);
                }
            });

            // 绑定全局点击事件，用于关闭右键菜单
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.admin-tab-context-menu') && !e.target.closest('.admin-tab-item')) {
                    this.closeAllContextMenus();
                }
            });

            // 绑定全局右键事件，用于关闭其他右键菜单
            document.addEventListener('contextmenu', (e) => {
                if (!e.target.closest('.admin-tab-item')) {
                    this.closeAllContextMenus();
                }
            });

            // 监听来自 iframe 的消息（用于处理内页交互）
            window.addEventListener('message', (e) => {
                this.handleIframeMessage(e);
            }, false);
        }

        /**
         * 绑定工具栏按钮事件
         */
        bindToolbarEvents() {
            const actions = {
                refresh: () => this.refreshCurrentTab(),
            };

            Object.entries(actions).forEach(([action, handler]) => {
                const btn = this.container.querySelector(`[data-action="${action}"]`);
                if (btn) {
                    btn.addEventListener('click', handler);
                }
            });
        }

        /**
         * 绑定侧边栏菜单点击事件
         */
        bindSidebarEvents() {
            // 使用事件委托处理所有侧边栏链接
            document.addEventListener('click', (e) => {
                const link = e.target.closest('.sidebar .nav-link[data-admin-tab="1"]');
                if (!link) return;

                // 跳过有子菜单的链接（由 sidebar 脚本处理展开/折叠）
                if (link.classList.contains('has-children')) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                const url = link.getAttribute('href');
                const title = link.getAttribute('data-tab-title') || link.textContent.trim();
                const mode = link.getAttribute('data-tab-mode') || 'internal';

                this.openTab(url, title, mode);
            });
        }

        /**
         * 处理初始 URL
         */
        handleInitialUrl() {
            const initialUrl = this.container.dataset.initialUrl;
            const initialTitle = this.container.dataset.initialTitle || '管理后台';

            if (initialUrl && initialUrl !== window.ADMIN_ENTRY_PATH) {
                // 延迟打开，确保 DOM 已完全加载
                setTimeout(() => {
                    this.openTab(initialUrl, initialTitle, 'internal');
                }, 100);
            }
        }

        /**
         * 打开新标签页
         * @param {string} url - 页面 URL
         * @param {string} title - 标签页标题
         * @param {string} mode - 打开模式：internal（内部 iframe）或 external（新窗口）
         */
        openTab(url, title, mode = 'internal') {
            // 外部链接直接打开新窗口
            if (mode === 'external' || !url.startsWith(window.ADMIN_ENTRY_PATH)) {
                window.open(url, '_blank');
                return;
            }

            // 检查是否已存在该标签页
            const existingTab = this.findTabByUrl(url);
            if (existingTab) {
                this.switchTab(existingTab.id);
                return;
            }

            // 检查标签页数量限制
            if (this.tabs.size >= this.maxTabs) {
                // 关闭最旧的标签页
                const oldestTab = Array.from(this.tabs.values())[0];
                this.closeTab(oldestTab.id);
            }

            // 生成标签页 ID
            const tabId = this.generateTabId(url);

            // 创建标签页元素
            const tabElement = this.createTabElement(tabId, title, url);
            const tabPanel = this.createTabPanel(tabId, url);

            // 添加到 DOM，确保刷新按钮始终保持在标签列表末尾
            if (this.tabList) {
                if (this.tabRefreshButton && this.tabRefreshButton.parentElement === this.tabList) {
                    this.tabList.insertBefore(tabElement, this.tabRefreshButton);
                } else {
            this.tabList.appendChild(tabElement);
                }
            }
            this.tabPanels.appendChild(tabPanel);

            // 存储标签页信息
            this.tabs.set(tabId, {
                id: tabId,
                element: tabElement,
                panel: tabPanel,
                url: url,
                title: title,
                iframe: tabPanel.querySelector('iframe'),
                createdAt: Date.now()
            });

            // 切换到新标签页
            this.switchTab(tabId);

            // 更新历史记录
            this.updateHistory(tabId, url, title);
        }

        /**
         * 创建标签页元素
         */
        createTabElement(tabId, title, url) {
            const tab = document.createElement('div');
            tab.className = 'admin-tab-item';
            tab.dataset.tabId = tabId;
            
            // 优化标题显示：移除常见的后缀
            const cleanTitle = this.cleanTabTitle(title);
            
            tab.innerHTML = `
                <span class="admin-tab-title" title="${this.escapeHtml(title)}">${this.escapeHtml(cleanTitle)}</span>
                <button type="button" class="admin-tab-close" data-action="close-tab" title="关闭">
                    <i class="bi bi-x"></i>
                </button>
                <div class="admin-tab-context-menu">
                    <div class="admin-tab-context-menu-item" data-action="refresh">
                        <span>刷新标签</span>
                    </div>
                    <div class="admin-tab-context-menu-divider"></div>
                    <div class="admin-tab-context-menu-item" data-action="close-current">
                        <span>关闭当前标签</span>
                    </div>
                    <div class="admin-tab-context-menu-item" data-action="close-others">
                        <span>关闭其他标签</span>
                    </div>
                    <div class="admin-tab-context-menu-item" data-action="close-all">
                        <span>关闭所有标签</span>
                    </div>
                </div>
            `;

            // 绑定关闭按钮事件
            tab.querySelector('[data-action="close-tab"]').addEventListener('click', (e) => {
                e.stopPropagation();
                this.closeTab(tabId);
            });

            // 绑定点击切换事件
            tab.addEventListener('click', () => {
                this.switchTab(tabId);
            });

            // 绑定右键菜单事件
            this.bindContextMenu(tab, tabId);

            return tab;
        }

        /**
         * 绑定右键菜单事件
         */
        bindContextMenu(tab, tabId) {
            const contextMenu = tab.querySelector('.admin-tab-context-menu');
            if (!contextMenu) return;

            // 右键点击显示菜单
            tab.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                e.stopPropagation();

                // 先关闭所有其他右键菜单
                this.closeAllContextMenus();

                // 显示当前右键菜单
                contextMenu.classList.add('show');

                // 计算菜单位置
                const rect = tab.getBoundingClientRect();
                const menuRect = contextMenu.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;

                let left = e.clientX;
                let top = e.clientY;

                // 防止菜单超出右边界
                if (left + menuRect.width > viewportWidth) {
                    left = viewportWidth - menuRect.width - 10;
                }

                // 防止菜单超出下边界
                if (top + menuRect.height > viewportHeight) {
                    top = viewportHeight - menuRect.height - 10;
                }

                // 防止菜单超出左边界
                if (left < 10) {
                    left = 10;
                }

                // 防止菜单超出上边界
                if (top < 10) {
                    top = 10;
                }

                contextMenu.style.left = `${left}px`;
                contextMenu.style.top = `${top}px`;
            });

            // 绑定菜单项点击事件
            const menuItems = contextMenu.querySelectorAll('.admin-tab-context-menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const action = item.dataset.action;
                    this.handleContextMenuAction(action, tabId);
                    this.closeAllContextMenus();
                });
            });
        }

        /**
         * 处理右键菜单操作
         */
        handleContextMenuAction(action, tabId) {
            switch (action) {
                case 'refresh':
                    // 刷新指定标签
                    this.refreshTab(tabId);
                    break;
                case 'close-current':
                    // 关闭当前标签（右键点击的标签）
                    this.closeTab(tabId);
                    break;
                case 'close-others':
                    // 先切换到当前标签，然后关闭其他标签
                    this.switchTab(tabId);
                    this.closeOtherTabs();
                    break;
                case 'close-all':
                    // 关闭所有标签
                    this.closeAllTabs();
                    break;
            }
        }

        /**
         * 关闭所有右键菜单
         */
        closeAllContextMenus() {
            const menus = document.querySelectorAll('.admin-tab-context-menu.show');
            menus.forEach(menu => {
                menu.classList.remove('show');
            });
        }

        /**
         * 创建标签页面板（iframe）
         */
        createTabPanel(tabId, url) {
            const panel = document.createElement('div');
            panel.className = 'admin-tab-panel';
            panel.dataset.tabId = tabId;

            // 构建 iframe URL（添加 _embed 参数）
            const iframeUrl = this.buildIframeUrl(url);

            const iframe = document.createElement('iframe');
            iframe.src = iframeUrl;
            iframe.frameBorder = '0';
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';

            // iframe 加载完成事件
            iframe.addEventListener('load', () => {
                this.onIframeLoad(tabId, iframe);
            });

            // iframe 加载错误事件
            iframe.addEventListener('error', () => {
                this.onIframeError(tabId);
            });

            panel.appendChild(iframe);
            return panel;
        }

        /**
         * 构建 iframe URL（添加 _embed 参数）
         */
        buildIframeUrl(url) {
            try {
                const urlObj = new URL(url, window.location.origin);
                urlObj.searchParams.set('_embed', '1');
                return urlObj.toString();
            } catch (e) {
                // 如果 URL 解析失败，直接拼接参数
                const separator = url.includes('?') ? '&' : '?';
                return url + separator + '_embed=1';
            }
        }

        /**
         * iframe 加载完成
         */
        onIframeLoad(tabId, iframe) {
            const tab = this.tabs.get(tabId);
            if (!tab) return;

            // 尝试从 iframe 中获取标题
            try {
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                const iframeTitle = iframeDoc.title;
                if (iframeTitle && iframeTitle !== '管理后台') {
                    // 清理 iframe 标题（移除站点名称后缀）
                    const cleanedIframeTitle = this.cleanTabTitle(iframeTitle);
                    const currentTitle = this.cleanTabTitle(tab.title);
                    
                    // 只在以下情况更新标题：
                    // 1. iframe 标题与当前标题不同
                    // 2. iframe 标题更简洁（长度更短）或者当前标题是默认值
                    // 3. 避免用更长的标题替换简洁的标题
                    if (cleanedIframeTitle !== currentTitle) {
                        // 如果 iframe 标题更简洁，或者当前标题包含"管理后台"等默认值，则更新
                        if (cleanedIframeTitle.length <= currentTitle.length || 
                            currentTitle.includes('管理后台') ||
                            currentTitle === '管理后台') {
                            this.updateTabTitle(tabId, iframeTitle);
                        }
                        // 否则保持初始标题不变
                    }
                }
            } catch (e) {
                // 跨域限制，无法获取标题
            }

            // 移除加载状态
            tab.element.classList.remove('loading');
        }

        /**
         * iframe 加载错误
         */
        onIframeError(tabId) {
            const tab = this.tabs.get(tabId);
            if (!tab) return;

            tab.element.classList.remove('loading');
            tab.element.classList.add('error');

            // 显示错误信息
            const panel = tab.panel;
            panel.innerHTML = `
                <div class="admin-tab-error">
                    <i class="bi bi-exclamation-triangle"></i>
                    <p>页面加载失败</p>
                    <button class="btn btn-primary btn-sm" onclick="window.adminTabManager.refreshTab('${tabId}')">重试</button>
                </div>
            `;
        }

        /**
         * 切换标签页
         */
        switchTab(tabId) {
            if (!this.tabs.has(tabId)) return;

            // 隐藏所有标签页
            this.tabs.forEach((tab) => {
                tab.element.classList.remove('active');
                tab.panel.classList.remove('active');
            });

            // 显示目标标签页
            const tab = this.tabs.get(tabId);
            tab.element.classList.add('active');
            tab.panel.classList.add('active');

            this.activeTabId = tabId;

            // 隐藏空状态
            if (this.emptyState) {
                this.emptyState.style.display = 'none';
            }

            // 更新历史记录
            this.updateHistory(tabId, tab.url, tab.title);

            // 同步侧边栏高亮
            try {
                if (window.Admin && window.Admin.utils && typeof window.Admin.utils.setSidebarActiveByUrl === 'function') {
                    const urlObj = new URL(tab.url, window.location.origin);
                    window.Admin.utils.setSidebarActiveByUrl(urlObj.pathname);
                }
            } catch (e) {
                // 忽略高亮错误，避免影响主流程
            }

            // 滚动到可见位置
            this.scrollTabIntoView(tab.element);
        }

        /**
         * 关闭标签页
         */
        closeTab(tabId) {
            if (!this.tabs.has(tabId)) return;

            const tab = this.tabs.get(tabId);
            const wasActive = tab.id === this.activeTabId;

            // 从 DOM 中移除
            tab.element.remove();
            tab.panel.remove();

            // 从 Map 中移除
            this.tabs.delete(tabId);

            // 如果关闭的是当前活动标签页，切换到其他标签页
            if (wasActive) {
                if (this.tabs.size > 0) {
                    // 切换到最后一个标签页
                    const remainingTabs = Array.from(this.tabs.values());
                    const lastTab = remainingTabs[remainingTabs.length - 1];
                    this.switchTab(lastTab.id);
                } else {
                    // 没有标签页了，显示空状态
                    this.activeTabId = null;
                    if (this.emptyState) {
                        this.emptyState.style.display = 'flex';
                    }
                }
            }
        }

        /**
         * 关闭当前标签页
         */
        closeCurrentTab() {
            if (this.activeTabId) {
                this.closeTab(this.activeTabId);
            }
        }

        /**
         * 关闭其他标签页
         */
        closeOtherTabs() {
            const activeId = this.activeTabId;
            this.tabs.forEach((tab, tabId) => {
                if (tabId !== activeId) {
                    this.closeTab(tabId);
                }
            });
        }

        /**
         * 关闭所有标签页
         */
        closeAllTabs() {
            const tabIds = Array.from(this.tabs.keys());
            tabIds.forEach(tabId => this.closeTab(tabId));
        }

        /**
         * 刷新当前标签页
         */
        refreshCurrentTab() {
            if (!this.activeTabId) return;
            this.refreshTab(this.activeTabId);
        }

        /**
         * 刷新指定标签页
         */
        refreshTab(tabId) {
            const tab = this.tabs.get(tabId);
            if (!tab) return;

            // 重新加载 iframe
            const iframe = tab.panel.querySelector('iframe');
            if (iframe) {
                iframe.src = iframe.src; // 重新加载
                tab.element.classList.add('loading');
            }
        }

        /**
         * 根据 URL 刷新标签页（供外部调用）
         * @param {string} url
         */
        refreshTabByUrl(url) {
            const tab = this.findTabByUrl(url);
            if (tab) {
                this.refreshTab(tab.id);
            }
        }

        /**
         * 清理标签页标题（移除常见后缀）
         */
        cleanTabTitle(title) {
            const rawTitle = typeof title === 'string' ? title.trim() : '';
            if (!rawTitle) {
                return '';
            }

            const segments = rawTitle
                .split('-')
                .map(segment => segment.trim())
                .filter(Boolean);

            if (segments.length <= 1) {
                return segments[0] || rawTitle;
            }

            const lastSegment = segments[segments.length - 1];
            if (this.shouldStripSuffix(lastSegment)) {
                segments.pop();
            }

            const cleaned = segments.join(' - ').trim();
            return cleaned || rawTitle;
        }

        shouldStripSuffix(segment) {
            if (!segment) {
                return false;
            }

            const normalizedSegment = segment.trim().toLowerCase();
            if (!normalizedSegment) {
                return false;
            }

            const siteTitle = (window.ADMIN_SITE_TITLE || '').toString().trim().toLowerCase();
            if (siteTitle && (normalizedSegment === siteTitle || normalizedSegment.includes(siteTitle))) {
                return true;
            }
            
            return TAB_SUFFIX_KEYWORDS.some(keyword => {
                return normalizedSegment.includes(keyword.toLowerCase());
            });
        }

        /**
         * 更新标签页标题
         */
        updateTabTitle(tabId, title) {
            const tab = this.tabs.get(tabId);
            if (!tab) return;

            tab.title = title;
            const titleElement = tab.element.querySelector('.admin-tab-title');
            if (titleElement) {
                // 使用清理后的标题显示，但保留完整标题在 tooltip 中
                const cleanTitle = this.cleanTabTitle(title);
                titleElement.textContent = cleanTitle;
                titleElement.setAttribute('title', title); // tooltip 显示完整标题
            }
        }

        /**
         * 根据 URL 查找标签页
         */
        findTabByUrl(url) {
            for (const tab of this.tabs.values()) {
                // 比较 URL（忽略查询参数中的 _embed）
                const normalizedUrl = this.normalizeUrl(url);
                const normalizedTabUrl = this.normalizeUrl(tab.url);
                if (normalizedUrl === normalizedTabUrl) {
                    return tab;
                }
            }
            return null;
        }

        /**
         * 标准化 URL（移除 _embed 参数）
         */
        normalizeUrl(url) {
            try {
                const urlObj = new URL(url, window.location.origin);
                urlObj.searchParams.delete('_embed');
                return urlObj.pathname + urlObj.search;
            } catch (e) {
                return url.split('?')[0];
            }
        }

        /**
         * 生成标签页 ID
         */
        generateTabId(url) {
            const normalized = this.normalizeUrl(url);
            // 使用 URL 的哈希值作为 ID（简化版）
            let hash = 0;
            for (let i = 0; i < normalized.length; i++) {
                const char = normalized.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // 转换为 32 位整数
            }
            return 'tab-' + Math.abs(hash).toString(36);
        }

        /**
         * 更新浏览器历史记录
         */
        updateHistory(tabId, url, title) {
            const state = { tabId, url, title };
            const normalizedUrl = this.normalizeUrl(url);
            window.history.pushState(state, title, normalizedUrl);
        }

        /**
         * 滚动标签页到可见位置
         */
        scrollTabIntoView(element) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
                inline: 'center'
            });
        }

        /**
         * HTML 转义
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * 处理来自 iframe 的消息
         * @param {MessageEvent} event
         */
        handleIframeMessage(event) {
            // 安全检查：只处理同源消息
            if (event.origin !== window.location.origin) {
                return;
            }

            const data = event.data;
            if (!data || typeof data !== 'object') {
                return;
            }

            // 检查消息是否来自我们的标签页 iframe
            const sourceIframe = this.findIframeByWindow(event.source);
            if (!sourceIframe) {
                // 消息可能来自 iframe shell，不是标签页，忽略
                return;
            }

            const tab = this.findTabByIframe(sourceIframe);
            if (!tab) {
                console.warn('[TabManager] Received message from unknown iframe:', data);
                return;
            }

            // 调试日志
            console.log('[TabManager] Received message from tab:', {
                tabId: tab.id,
                action: data.action,
                payload: data.payload
            });

            // 处理不同的 action
            switch (data.action) {
                case 'success':
                    this.handleSuccessMessage(tab, data.payload || {});
                    break;
                case 'close':
                    this.handleCloseMessage(tab, data.payload || {});
                    break;
                case 'notify':
                    // 自定义事件通知，可以在这里添加自定义处理逻辑
                    console.log('[TabManager] Received notify event:', data);
                    this.handleNotifyMessage(tab, data.payload || {});
                    break;
                case 'refresh-main':
                    this.handleRefreshMainMessage(tab, data.payload || {});
                    break;
            }
        }

        /**
         * 根据 window 对象查找对应的 iframe 元素
         * @param {Window} targetWindow
         * @returns {HTMLIFrameElement|null}
         */
        findIframeByWindow(targetWindow) {
            for (const tab of this.tabs.values()) {
                const iframe = tab.panel.querySelector('iframe');
                if (iframe && iframe.contentWindow === targetWindow) {
                    return iframe;
                }
            }
            return null;
        }

        /**
         * 根据 iframe 元素查找对应的标签页
         * @param {HTMLIFrameElement} iframe
         * @returns {Object|null}
         */
        findTabByIframe(iframe) {
            for (const tab of this.tabs.values()) {
                const tabIframe = tab.panel.querySelector('iframe');
                if (tabIframe === iframe) {
                    return tab;
                }
            }
            return null;
        }

        /**
         * 处理 success 消息
         * @param {Object} tab - 标签页对象
         * @param {Object} payload - 消息负载
         */
        handleSuccessMessage(tab, payload) {
            console.log('[TabManager] Handling success message:', payload);

            // 显示成功提示
            const message = payload.message || (payload.refreshParent ? '刷新成功' : '操作成功');
            try {
                if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                    window.Admin.utils.showToast('success', message);
                } else if (window.showToast && typeof window.showToast === 'function') {
                    window.showToast('success', message);
                } else {
                    console.log('[TabManager] Success:', message);
                }
            } catch (e) {
                console.warn('[TabManager] Failed to show toast:', e);
            }

            // 如果需要刷新父页（通常是刷新列表页）
            if (payload.refreshParent) {
                // 立即刷新，不延迟
                if (payload.refreshUrl) {
                    // 刷新指定的列表页
                    const refreshUrl = payload.refreshUrl;
                    console.log('[TabManager] Refreshing tab by URL:', refreshUrl);
                    this.refreshTabByUrl(refreshUrl);
                } else {
                    // 如果没有指定 refreshUrl，刷新当前标签页
                    console.log('[TabManager] Refreshing current tab:', tab.id);
                    this.refreshTab(tab.id);
                }
            }

            // 如果需要关闭当前标签页（默认关闭）
            if (payload.closeCurrent !== false) {
                const delay = payload.delay || 800;
                setTimeout(() => {
                    console.log('[TabManager] Closing tab:', tab.id);
                    this.closeTab(tab.id);
                }, delay);
            }
        }

        /**
         * 处理 close 消息
         * @param {Object} tab - 标签页对象
         * @param {Object} payload - 消息负载
         */
        handleCloseMessage(tab, payload) {
            // 关闭当前标签页
            this.closeTab(tab.id);
        }

        /**
         * 处理 notify 消息
         * @param {Object} tab - 标签页对象
         * @param {Object} payload - 消息负载
         */
        handleNotifyMessage(tab, payload) {
            const iframe = tab.panel.querySelector('iframe');
            if (!iframe || !iframe.contentWindow) {
                return;
            }

            // 发送响应消息回给 iframe
            const response = {
                action: 'custom-response',
                payload: {
                    receivedAt: Date.now(),
                    originalPayload: payload,
                    message: '父页面已收到自定义消息',
                    tabId: tab.id,
                    tabTitle: tab.title
                }
            };

            // 延迟发送响应，模拟处理时间
            setTimeout(() => {
                try {
                    iframe.contentWindow.postMessage(response, window.location.origin);
                    console.log('[TabManager] Sent response to iframe:', response);
                } catch (e) {
                    console.warn('[TabManager] Failed to send response to iframe:', e);
                }
            }, 100);
        }

        /**
         * 处理 refresh-main 消息：刷新主框架
         * @param {Object} tab
         * @param {Object} payload
         */
        handleRefreshMainMessage(tab, payload) {
            const options = Object.assign({
                message: '主框架已更新，正在刷新...',
                toastType: 'info',
                showToast: true,
                delay: 0
            }, payload || {});

            if (options.showToast) {
                try {
                    const toastType = options.toastType || 'info';
                    if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                        window.Admin.utils.showToast(toastType, options.message);
                    } else if (window.showToast && typeof window.showToast === 'function') {
                        window.showToast(toastType, options.message);
                    } else {
                        console.log('[TabManager] Refresh main frame:', options.message);
                    }
                } catch (error) {
                    console.warn('[TabManager] Failed to show refresh toast:', error);
                }
            }

            const reload = () => {
                try {
                    window.location.reload();
                } catch (error) {
                    console.warn('[TabManager] window.location.reload failed, fallback to hard reload:', error);
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
    }

    // 初始化 Tab 管理器
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('adminTabLayout');
        if (container) {
            const manager = new AdminTabManager(container);
            // 兼容旧代码
            window.adminTabManager = manager;

            // 注入到 Admin 命名空间，便于统一管理
            window.Admin = window.Admin || {};
            window.Admin.tabManager = manager;
        }
    });

})();

