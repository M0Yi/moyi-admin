/**
 * AddonsStore 插件商店前端脚本
 */

// 插件商店命名空间
window.AddonsStore = {
    // 配置
    config: {
        apiBaseUrl: '/api/addons_store',
        adminPath: window.adminPath || 'admin',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || ''
    },

    // 工具函数
    utils: {
        /**
         * 显示加载状态
         */
        showLoading: function(element, text = '加载中...') {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }

            if (element) {
                element.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">${text}</span>
                        </div>
                        <p class="text-muted mt-2">${text}</p>
                    </div>
                `;
            }
        },

        /**
         * 隐藏加载状态
         */
        hideLoading: function(element) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }

            if (element) {
                element.innerHTML = '';
            }
        },

        /**
         * 显示消息提示
         */
        showMessage: function(message, type = 'info') {
            const alertClass = {
                success: 'alert-success',
                error: 'alert-danger',
                warning: 'alert-warning',
                info: 'alert-info'
            }[type] || 'alert-info';

            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;

            // 查找消息容器，如果没有则创建
            let container = document.querySelector('.message-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'message-container position-fixed top-0 end-0 p-3';
                container.style.zIndex = '9999';
                document.body.appendChild(container);
            }

            container.insertAdjacentHTML('beforeend', alertHtml);

            // 自动消失
            setTimeout(() => {
                const alert = container.lastElementChild;
                if (alert) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            }, 5000);
        },

        /**
         * 格式化文件大小
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * 格式化数字
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * 防抖函数
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    },

    // API 调用
    api: {
        /**
         * 通用 API 请求方法
         */
        request: async function(endpoint, options = {}) {
            const defaultOptions = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.config.csrfToken
                }
            };

            const url = `${this.config.apiBaseUrl}${endpoint}`;
            const response = await fetch(url, { ...defaultOptions, ...options });
            const data = await response.json();

            if (data.code !== 200) {
                throw new Error(data.message || '请求失败');
            }

            return data.data;
        },

        /**
         * 获取插件列表
         */
        getAddonList: async function(params = {}) {
            const queryString = new URLSearchParams(params).toString();
            const endpoint = `/list${queryString ? '?' + queryString : ''}`;
            return await this.request(endpoint);
        },

        /**
         * 获取插件详情
         */
        getAddonDetail: async function(addonId) {
            return await this.request(`/detail/${addonId}`);
        },

        /**
         * 获取插件版本
         */
        getAddonVersions: async function(addonId) {
            return await this.request(`/versions/${addonId}`);
        },

        /**
         * 下载插件
         */
        downloadAddon: async function(addonId, version = null) {
            const versionParam = version ? `/${version}` : '';
            return await this.request(`/download/${addonId}${versionParam}`);
        },

        /**
         * 获取分类列表
         */
        getCategories: async function() {
            return await this.request('/categories');
        },

        /**
         * 获取下载统计
         */
        getDownloadStats: async function() {
            return await this.request('/download-stats');
        }
    },

    // UI 组件
    ui: {
        /**
         * 创建插件卡片
         */
        createAddonCard: function(addon) {
            const downloads = this.utils.formatNumber(addon.downloads || 0);
            const rating = addon.rating || 0;
            const reviewsCount = addon.reviews_count || 0;

            return `
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="addon-card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start mb-3">
                                <div class="addon-icon me-3">
                                    ${addon.name.substring(0, 2).toUpperCase()}
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">
                                        <a href="/${this.config.adminPath}/addons_store/show/${addon.id}" class="text-decoration-none">
                                            ${addon.name}
                                        </a>
                                    </h5>
                                    <p class="text-muted small mb-1">v${addon.version}</p>
                                    <div class="d-flex align-items-center">
                                        <div class="rating-stars me-2">
                                            ${this.createRatingStars(rating)}
                                        </div>
                                        <small class="text-muted">(${reviewsCount})</small>
                                    </div>
                                </div>
                            </div>

                            <p class="card-text text-muted small mb-3">
                                ${addon.description ? addon.description.substring(0, 100) + (addon.description.length > 100 ? '...' : '') : ''}
                            </p>

                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">${addon.author}</small>
                                    <div class="download-badge">
                                        <i class="bi bi-download"></i> ${downloads}
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="/${this.config.adminPath}/addons_store/show/${addon.id}"
                                       class="btn btn-outline-primary btn-sm flex-grow-1">
                                        查看详情
                                    </a>
                                    <button class="btn btn-success btn-sm download-btn"
                                            data-addon-id="${addon.id}"
                                            data-addon-name="${addon.name}">
                                        <i class="bi bi-download"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * 创建评分星星
         */
        createRatingStars: function(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += `<i class="bi bi-star${i <= rating ? '-fill' : ''}"></i>`;
            }
            return stars;
        },

        /**
         * 创建分页组件
         */
        createPagination: function(paginationData) {
            if (!paginationData || paginationData.last_page <= 1) {
                return '';
            }

            let html = '<nav aria-label="插件分页"><ul class="pagination justify-content-center">';

            // 上一页
            if (paginationData.page > 1) {
                html += `<li class="page-item">
                    <a class="page-link" href="#" data-page="${paginationData.page - 1}">上一页</a>
                </li>`;
            } else {
                html += '<li class="page-item disabled"><span class="page-link">上一页</span></li>';
            }

            // 页码
            const start = Math.max(1, paginationData.page - 2);
            const end = Math.min(paginationData.last_page, paginationData.page + 2);

            for (let i = start; i <= end; i++) {
                const activeClass = i === paginationData.page ? ' active' : '';
                html += `<li class="page-item${activeClass}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>`;
            }

            // 下一页
            if (paginationData.page < paginationData.last_page) {
                html += `<li class="page-item">
                    <a class="page-link" href="#" data-page="${paginationData.page + 1}">下一页</a>
                </li>`;
            } else {
                html += '<li class="page-item disabled"><span class="page-link">下一页</span></li>';
            }

            html += '</ul></nav>';
            return html;
        }
    },

    // 页面功能
    pages: {
        /**
         * 插件市场页面功能
         */
        addonMarket: {
            init: function() {
                this.bindEvents();
                this.loadAddons();
            },

            bindEvents: function() {
                // 搜索功能
                const searchInput = document.getElementById('searchInput');
                const searchBtn = document.getElementById('searchBtn');

                if (searchBtn) {
                    searchBtn.addEventListener('click', () => this.performSearch());
                }

                if (searchInput) {
                    searchInput.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            this.performSearch();
                        }
                    });
                }

                // 排序功能
                const sortSelect = document.getElementById('sortSelect');
                if (sortSelect) {
                    sortSelect.addEventListener('change', () => {
                        this.currentParams.sort = sortSelect.value;
                        this.currentParams.page = 1;
                        this.loadAddons();
                    });
                }

                // 分类筛选
                const categoryBtns = document.querySelectorAll('.category-btn');
                categoryBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        categoryBtns.forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        this.currentParams.category = btn.dataset.category;
                        this.currentParams.page = 1;
                        this.loadAddons();
                    });
                });
            },

            currentParams: {
                keyword: '',
                category: '',
                sort: 'downloads',
                page: 1
            },

            performSearch: function() {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    this.currentParams.keyword = searchInput.value.trim();
                    this.currentParams.page = 1;
                    this.loadAddons();
                }
            },

            loadAddons: function() {
                const container = document.getElementById('addonsContainer');
                const loadingSpinner = document.getElementById('loadingSpinner');

                if (!container) return;

                // 显示加载状态
                AddonsStore.utils.showLoading(container, '正在加载插件列表...');

                // 隐藏分页
                const paginationContainer = document.querySelector('.pagination-wrapper');
                if (paginationContainer) {
                    paginationContainer.style.display = 'none';
                }

                AddonsStore.api.getAddonList(this.currentParams)
                    .then(data => {
                        this.renderAddons(data);
                        this.updateURL();
                    })
                    .catch(error => {
                        console.error('Load addons error:', error);
                        AddonsStore.utils.showMessage('加载插件列表失败，请稍后重试', 'error');
                    });
            },

            renderAddons: function(data) {
                const container = document.getElementById('addonsContainer');
                const paginationContainer = document.querySelector('.pagination-wrapper');

                if (!data.data || data.data.length === 0) {
                    container.innerHTML = `
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                                <h4 class="text-muted">暂无插件</h4>
                                <p class="text-muted">还没有插件被上传到商店</p>
                            </div>
                        </div>
                    `;
                    return;
                }

                let html = '';
                data.data.forEach(addon => {
                    html += AddonsStore.ui.createAddonCard(addon);
                });

                container.innerHTML = html;

                // 渲染分页
                if (paginationContainer && data.last_page > 1) {
                    paginationContainer.innerHTML = AddonsStore.ui.createPagination(data);
                    paginationContainer.style.display = 'block';

                    // 绑定分页事件
                    const pageLinks = paginationContainer.querySelectorAll('.page-link[data-page]');
                    pageLinks.forEach(link => {
                        link.addEventListener('click', (e) => {
                            e.preventDefault();
                            const page = parseInt(link.dataset.page);
                            if (page && page !== this.currentParams.page) {
                                this.currentParams.page = page;
                                this.loadAddons();
                            }
                        });
                    });
                }

                // 绑定下载按钮事件
                this.bindDownloadEvents();
            },

            bindDownloadEvents: function() {
                const downloadBtns = document.querySelectorAll('.download-btn');
                downloadBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        const addonId = this.dataset.addonId;
                        const addonName = this.dataset.addonName;

                        if (confirm(`确定要下载插件 "${addonName}" 吗？`)) {
                            AddonsStore.pages.addonMarket.downloadAddon(addonId);
                        }
                    });
                });
            },

            downloadAddon: function(addonId) {
                AddonsStore.api.downloadAddon(addonId)
                    .then(data => {
                        if (data.download_url) {
                            window.location.href = data.download_url;
                        }
                    })
                    .catch(error => {
                        console.error('Download error:', error);
                        AddonsStore.utils.showMessage('下载失败，请稍后重试', 'error');
                    });
            },

            updateURL: function() {
                const params = new URLSearchParams();
                Object.keys(this.currentParams).forEach(key => {
                    if (this.currentParams[key]) {
                        params.append(key, this.currentParams[key]);
                    }
                });

                const newURL = `${window.location.pathname}?${params.toString()}`;
                window.history.replaceState({}, '', newURL);
            }
        }
    },

    // 初始化
    init: function() {
        // 设置管理员路径
        this.config.adminPath = window.adminPath || 'admin';

        // 页面特定的初始化
        const bodyClass = document.body.className;

        if (bodyClass.includes('addon-market')) {
            this.pages.addonMarket.init();
        }
    }
};

// DOM 加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    AddonsStore.init();
});
