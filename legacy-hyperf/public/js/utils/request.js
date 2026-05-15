/**
 * 统一 HTTP 请求封装
 * 
 * 功能特性：
 * - 自动携带 CSRF Token
 * - 自动处理 JWT Token
 * - 统一错误处理
 * - 自动显示 Toast 通知
 * - 统一 Loading 状态
 * - ⭐ 请求重试机制（可配置次数、延迟、状态码）
 * - ⭐ 请求限流（防止 API 滥用）
 * - ⭐ 错误自动重试（网络错误、服务器错误）
 * 
 * 使用方法：
 * // GET 请求
 * const data = await $http.get('/api/users');
 * 
 * // POST 请求
 * await $http.post('/api/users', { name: '张三' });
 * 
 * // 配置重试（3 次重试，2 秒间隔）
 * await $http.get('/api/users', { retry: 3, retryDelay: 2000 });
 * 
 * // 禁用限流
 * await $http.post('/api/users', data, { rateLimit: false });
 * 
 * // 配置业务错误重试
 * await $http.get('/api/users', {
 *     retry: 3,
 *     businessRetry: {
 *         enabled: true,
 *         retryCodes: ['TOKEN_EXPIRED', 'SERVER_ERROR']
 *     }
 * });
 * 
 * // 查看限流状态
 * const status = $http.getRateLimitStatus();
 * 
 * // 在 Alpine.js 中使用
 * <div x-data="{ async loadData() { const data = await $http.get('/api/users'); } }">
 */

(function(global) {
    'use strict';

    // ========== 配置 ==========
    const defaultConfig = {
        // 重试配置
        retry: 1,              // 重试次数（0 = 不重试）
        retryDelay: 1000,      // 重试间隔（毫秒）
        retryOn: [             // 需要重试的 HTTP 状态码
            0,                  // 网络错误
            408,                // 请求超时
            429,                // 限流
            500,                // 服务器内部错误
            502,                // 网关错误
            503,                // 服务不可用
            504,                // 网关超时
        ],
        
        // 限流配置
        rateLimit: {
            enabled: true,      // 是否启用限流
            maxRequests: 10,    // 最大并发请求数
            windowMs: 1000,     // 时间窗口（毫秒）
            queueSize: 50,      // 队列最大长度
        },
        
        // 业务错误重试
        businessRetry: {
            enabled: true,       // 是否启用业务错误重试
            retryCodes: [        // 需要重试的业务错误码
                'TOKEN_EXPIRED',     // Token 过期
                'TOKEN_INVALID',     // Token 无效
                'SERVER_ERROR',      // 服务器错误
                'MAINTENANCE',      // 维护中
            ],
            maxRetries: 2,       // 最大重试次数
        },
    };

    // ========== 请求限流器 ==========
    class RateLimiter {
        constructor(config) {
            this.enabled = config.enabled !== false;
            this.maxRequests = config.maxRequests || 10;
            this.windowMs = config.windowMs || 1000;
            this.queueSize = config.queueSize || 50;
            
            this.requestQueue = [];
            this.processing = 0;
            this.lastWindowTime = Date.now();
            this.requestCount = 0;
        }

        /**
         * 等待可用槽位
         */
        async acquire() {
            if (!this.enabled) {
                return true;
            }

            // 检查队列是否已满
            if (this.requestQueue.length >= this.queueSize) {
                throw new Error('请求队列已满，请稍后再试');
            }

            // 将请求加入队列
            return new Promise((resolve, reject) => {
                this.requestQueue.push({ resolve, reject });
                this.processQueue();
            });
        }

        /**
         * 处理请求队列
         */
        processQueue() {
            if (this.requestQueue.length === 0) return;

            // 检查时间窗口
            const now = Date.now();
            if (now - this.lastWindowTime >= this.windowMs) {
                this.lastWindowTime = now;
                this.requestCount = 0;
            }

            // 检查是否超过限制
            if (this.requestCount >= this.maxRequests) {
                // 等待下一个时间窗口
                const waitTime = this.windowMs - (now - this.lastWindowTime);
                setTimeout(() => this.processQueue(), Math.min(waitTime, 100));
                return;
            }

            // 处理队列中的请求
            while (this.requestQueue.length > 0 && this.requestCount < this.maxRequests) {
                const { resolve } = this.requestQueue.shift();
                this.requestCount++;
                resolve(true);
            }
        }

        /**
         * 释放槽位
         */
        release() {
            this.processQueue();
        }
    }

    // ========== 请求重试器 ==========
    class RetryHandler {
        constructor(config) {
            this.retry = config.retry || 1;
            this.retryDelay = config.retryDelay || 1000;
            this.retryOn = config.retryOn || [0, 408, 429, 500, 502, 503, 504];
            this.businessRetry = config.businessRetry || {};
        }

        /**
         * 判断是否应该重试
         */
        shouldRetry(error, retryCount) {
            // 检查重试次数
            if (retryCount >= this.retry) {
                return false;
            }

            // HTTP 状态码重试
            if (error.response) {
                const status = error.response.status;
                if (this.retryOn.includes(status)) {
                    return true;
                }
            }

            // 网络错误（无响应）
            if (error.code === 0 || !error.response) {
                return true;
            }

            // 业务错误码重试
            if (this.businessRetry.enabled && error.code) {
                const retryCodes = this.businessRetry.retryCodes || [];
                if (retryCodes.includes(error.code)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * 计算重试延迟（指数退避）
         */
        getDelay(retryCount) {
            // 基础延迟 + 随机抖动（防止同时重试）
            const baseDelay = this.retryDelay * Math.pow(2, retryCount);
            const jitter = Math.random() * 0.3 * baseDelay; // 30% 随机抖动
            return Math.min(baseDelay + jitter, 10000); // 最大 10 秒
        }
    }

    // ========== 全局实例 ==========
    const rateLimiter = new RateLimiter(defaultConfig.rateLimit);
    const retryHandler = new RetryHandler(defaultConfig);

    // ========== 工具函数 ==========
    
    /**
     * 获取 CSRF Token
     */
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    /**
     * 获取 JWT Token
     */
    function getJwtToken() {
        return localStorage.getItem('admin_token');
    }

    /**
     * 睡眠（用于重试延迟）
     */
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * 处理错误响应
     */
    function handleError(response) {
        const contentType = response.headers.get('content-type');
        
        if (contentType && contentType.indexOf('application/json') !== -1) {
            return response.json().then(data => {
                const error = new Error(data.msg || data.message || '请求失败');
                error.code = data.code;
                error.data = data.data;
                error.response = response;
                throw error;
            });
        }
        
        const error = new Error(response.statusText || '请求失败');
        error.code = response.status;
        error.response = response;
        throw error;
    }

    /**
     * 创建请求配置
     */
    function createConfig(method, url, data, options = {}) {
        const config = {
            method,
            url,
            headers: {
                'Content-Type': 'application/json',
                ...options.headers,
            },
        };

        // 携带 CSRF Token
        const csrfToken = getCsrfToken();
        if (csrfToken) {
            config.headers['X-CSRF-TOKEN'] = csrfToken;
        }

        // 携带 JWT Token
        const token = getJwtToken();
        if (token) {
            config.headers['Authorization'] = `Bearer ${token}`;
        }

        // 携带数据
        if (data) {
            config.body = JSON.stringify(data);
        }

        return config;
    }

    /**
     * 显示/隐藏 Loading
     */
    function toggleLoading(show) {
        if (typeof window.$loading !== 'undefined' && window.$loading) {
            if (show) {
                window.$loading.show();
            } else {
                window.$loading.hide();
            }
        }
    }

    /**
     * 显示 Toast 通知
     */
    function showToast(message, type = 'error') {
        if (typeof window.$toast !== 'undefined' && window.$toast) {
            window.$toast[type]?.(message);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    /**
     * 执行请求（带重试和限流）
     */
    async function executeRequest(method, url, data, options = {}) {
        const retryCount = options._retryCount || 0;
        const rateLimit = options.rateLimit !== false;
        
        // 获取限流槽位
        if (rateLimit) {
            await rateLimiter.acquire();
        }

        const config = createConfig(method, url, data, options);

        // 过滤空值的 headers
        if (config.headers) {
            Object.keys(config.headers).forEach(key => {
                if (config.headers[key] === undefined || config.headers[key] === null) {
                    delete config.headers[key];
                }
            });
        }

        // 显示 Loading（可选）
        if (options.showLoading !== false) {
            toggleLoading(true);
        }

        try {
            const response = await fetch(config.url, config);

            // 释放限流槽位
            if (rateLimit) {
                rateLimiter.release();
            }

            // 隐藏 Loading
            toggleLoading(false);

            // 处理错误响应
            if (!response.ok) {
                handleError(response);
            }

            // 解析 JSON
            const result = await response.json();

            // 业务逻辑错误处理
            if (result.code !== 200) {
                const error = new Error(result.msg || result.message || '请求失败');
                error.code = result.code;
                error.data = result.data;
                error.response = response;

                // 检查是否应该重试
                if (retryHandler.shouldRetry(error, retryCount)) {
                    const delay = retryHandler.getDelay(retryCount);
                    await sleep(delay);
                    
                    // 递归重试
                    return executeRequest(method, url, data, {
                        ...options,
                        _retryCount: retryCount + 1,
                        showLoading: false, // 重试时不显示 loading
                    });
                }

                showToast(result.msg || result.message || '请求失败', 'error');
                throw error;
            }

            return result.data;

        } catch (error) {
            // 释放限流槽位
            if (rateLimit) {
                rateLimiter.release();
            }

            // 隐藏 Loading
            toggleLoading(false);

            // 检查是否应该重试
            if (retryHandler.shouldRetry(error, retryCount)) {
                const delay = retryHandler.getDelay(retryCount);
                await sleep(delay);
                
                // 递归重试
                return executeRequest(method, url, data, {
                    ...options,
                    _retryCount: retryCount + 1,
                    showLoading: false, // 重试时不显示 loading
                });
            }

            // 如果是业务错误，已经显示 toast，跳过
            if (error.code !== undefined) {
                throw error;
            }

            // 网络错误或其他错误
            showToast(error.message || '网络错误，请稍后重试', 'error');
            throw error;
        }
    }

    // ========== HTTP 请求封装对象 ==========
    const http = {
        /**
         * 配置
         */
        config: { ...defaultConfig },

        /**
         * GET 请求
         */
        async get(url, options = {}) {
            return this._request('GET', url, null, options);
        },

        /**
         * POST 请求
         */
        async post(url, data, options = {}) {
            return this._request('POST', url, data, options);
        },

        /**
         * PUT 请求
         */
        async put(url, data, options = {}) {
            return this._request('PUT', url, data, options);
        },

        /**
         * PATCH 请求
         */
        async patch(url, data, options = {}) {
            return this._request('PATCH', url, data, options);
        },

        /**
         * DELETE 请求
         */
        async delete(url, options = {}) {
            return this._request('DELETE', url, null, options);
        },

        /**
         * 发送 JSON 请求
         */
        async json(url, data, options = {}) {
            return this._request('POST', url, data, {
                ...options,
                headers: {
                    ...options.headers,
                    'Content-Type': 'application/json',
                },
            });
        },

        /**
         * 统一请求方法
         */
        async _request(method, url, data, options = {}) {
            return executeRequest(method, url, data, options);
        },

        /**
         * 下载文件
         */
        download(url, data = {}, method = 'POST') {
            const form = document.createElement('form');
            form.method = method;
            form.action = url;
            form.style.display = 'none';

            // 携带 CSRF
            const csrf = getCsrfToken();
            if (csrf) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_token';
                input.value = csrf;
                form.appendChild(input);
            }

            // 携带数据
            Object.keys(data).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = typeof data[key] === 'object' 
                    ? JSON.stringify(data[key]) 
                    : data[key];
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        },

        /**
         * 上传文件
         */
        async upload(url, file, options = {}) {
            const formData = new FormData();
            formData.append('file', file);

            // 携带其他数据
            if (options.data) {
                Object.keys(options.data).forEach(key => {
                    formData.append(key, options.data[key]);
                });
            }

            // 显示 Loading
            if (options.showLoading !== false) {
                toggleLoading(true);
            }

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Authorization': `Bearer ${getJwtToken()}`,
                        ...options.headers,
                    },
                    body: formData,
                });

                toggleLoading(false);

                if (!response.ok) {
                    handleError(response);
                }

                const result = await response.json();

                if (result.code !== 200) {
                    showToast(result.msg || result.message || '上传失败', 'error');
                    throw new Error(result.msg || result.message || '上传失败');
                }

                return result.data;

            } catch (error) {
                toggleLoading(false);
                showToast(error.message || '上传失败', 'error');
                throw error;
            }
        },

        /**
         * 获取请求实例（用于需要自定义配置的场景）
         */
        create(options = {}) {
            const instance = Object.create(this);
            
            // 合并配置
            instance.config = {
                ...this.config,
                ...options,
                rateLimit: {
                    ...this.config.rateLimit,
                    ...(options.rateLimit || {}),
                },
                businessRetry: {
                    ...this.config.businessRetry,
                    ...(options.businessRetry || {}),
                },
            };

            // 创建自定义配置的请求方法
            const customRequest = async (method, url, data, customOptions = {}) => {
                return executeRequest(method, url, data, {
                    ...this.config,
                    ...options,
                    ...customOptions,
                });
            };

            // 绑定方法
            instance.get = (url, opts) => customRequest('GET', url, null, opts);
            instance.post = (url, data, opts) => customRequest('POST', url, data, opts);
            instance.put = (url, data, opts) => customRequest('PUT', url, data, opts);
            instance.patch = (url, data, opts) => customRequest('PATCH', url, data, opts);
            instance.delete = (url, opts) => customRequest('DELETE', url, null, opts);

            return instance;
        },

        /**
         * 配置限流器
         */
        configureRateLimiter(options) {
            Object.assign(rateLimiter, options);
        },

        /**
         * 配置重试处理器
         */
        configureRetry(options) {
            Object.assign(retryHandler, options);
        },

        /**
         * 获取限流状态
         */
        getRateLimitStatus() {
            return {
                queueLength: rateLimiter.requestQueue.length,
                processing: rateLimiter.processing,
                requestsInWindow: rateLimiter.requestCount,
                windowMs: rateLimiter.windowMs,
            };
        },
    };

    // 暴露到全局
    global.$http = http;

    // ES Module 导出支持
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = http;
    }

})(typeof window !== 'undefined' ? window : this);
