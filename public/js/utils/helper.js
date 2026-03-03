/**
 * 通用工具函数
 * 
 * 包含：
 * - 常用验证函数
 * - 字符串处理
 * - 日期处理
 * - 浏览器检测
 * - 本地存储封装
 * - DOM 操作辅助
 */

(function(global) {
    'use strict';

    /**
     * 工具函数对象
     */
    const helper = {
        /**
         * ========== 验证相关 ==========
         */

        /**
         * 验证手机号
         */
        isMobile(value) {
            return /^1[3-9]\d{9}$/.test(value);
        },

        /**
         * 验证邮箱
         */
        isEmail(value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        },

        /**
         * 验证 URL
         */
        isUrl(value) {
            return /^(https?:\/\/)?([\w.-]+\.)+[a-z]{2,}(\/.*)?$/.test(value);
        },

        /**
         * 验证身份证号
         */
        isIdCard(value) {
            return /(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/.test(value);
        },

        /**
         * 验证纯数字
         */
        isNumber(value) {
            return /^\d+$/.test(value);
        },

        /**
         * 验证中文
         */
        isChinese(value) {
            return /^[\u4e00-\u9fa5]+$/.test(value);
        },

        /**
         * ========== 字符串处理 ==========
         */

        /**
         * 去除字符串两端空白
         */
        trim(value) {
            return String(value).trim();
        },

        /**
         * 截取字符串（支持中文）
         */
        substr(str, start, length) {
            if (!str) return '';
            str = String(str);
            // 计算中文
            let chineseCount = 0;
            let chineseStart = 0;
            for (let i = 0; i < str.length; i++) {
                if (str.charCodeAt(i) > 255) {
                    chineseCount++;
                }
                if (i < start + chineseCount) {
                    chineseStart++;
                }
            }
            return str.substr(start + chineseStart, length + chineseCount);
        },

        /**
         * 字符串替换（支持多替换）
         */
        replace(str, find, replace) {
            if (!str) return '';
            if (Array.isArray(find) && Array.isArray(replace)) {
                let result = str;
                find.forEach((item, index) => {
                    result = result.split(item).join(replace[index] || '');
                });
                return result;
            }
            return str.split(find).join(replace);
        },

        /**
         * 格式化字符串
         */
        sprintf(format) {
            const args = Array.prototype.slice.call(arguments, 1);
            return format.replace(/%(\d+)?([dfs])/g, function(match, width, type) {
                const value = args.shift();
                if (type === 'd') {
                    return parseInt(value, 10).toString().padStart(width || 1, '0');
                } else if (type === 'f') {
                    return parseFloat(value).toFixed(width || 6).replace(/\.?0+$/, '');
                }
                return value;
            });
        },

        /**
         * ========== 日期处理 ==========
         */

        /**
         * 格式化日期
         */
        formatDate(date, format = 'Y-m-d') {
            if (!date) return '';
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            const hour = String(d.getHours()).padStart(2, '0');
            const minute = String(d.getMinutes()).padStart(2, '0');
            const second = String(d.getSeconds()).padStart(2, '0');

            return format
                .replace('Y', year)
                .replace('m', month)
                .replace('d', day)
                .replace('H', hour)
                .replace('i', minute)
                .replace('s', second);
        },

        /**
         * 相对时间（如：刚刚、5分钟前）
         */
        relativeTime(date) {
            if (!date) return '';
            const now = new Date().getTime();
            const timestamp = new Date(date).getTime();
            const diff = (now - timestamp) / 1000;

            if (diff < 60) {
                return '刚刚';
            } else if (diff < 3600) {
                return Math.floor(diff / 60) + ' 分钟前';
            } else if (diff < 86400) {
                return Math.floor(diff / 3600) + ' 小时前';
            } else if (diff < 604800) {
                return Math.floor(diff / 86400) + ' 天前';
            } else {
                return this.formatDate(date, 'Y-m-d');
            }
        },

        /**
         * ========== 数字处理 ==========
         */

        /**
         * 格式化数字（千分位）
         */
        formatNumber(num) {
            if (num === null || num === undefined) return '-';
            return String(num).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * 格式化文件大小
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * 数字补零
         */
        padZero(num, length = 2) {
            return String(num).padStart(length, '0');
        },

        /**
         * ========== 浏览器检测 ==========
         */

        /**
         * 获取浏览器信息
         */
        getBrowser() {
            const ua = navigator.userAgent;
            let browser = 'Unknown';
            let version = '0';

            // Chrome
            if (ua.indexOf('Chrome') > -1) {
                browser = 'Chrome';
                version = ua.match(/Chrome\/(\d+)/)?.[1] || '0';
            }
            // Firefox
            else if (ua.indexOf('Firefox') > -1) {
                browser = 'Firefox';
                version = ua.match(/Firefox\/(\d+)/)?.[1] || '0';
            }
            // Safari
            else if (ua.indexOf('Safari') > -1) {
                browser = 'Safari';
                version = ua.match(/Version\/(\d+)/)?.[1] || '0';
            }
            // IE
            else if (ua.indexOf('MSIE') > -1 || ua.indexOf('Trident') > -1) {
                browser = 'IE';
                version = ua.match(/(?:MSIE |rv:)(\d+)/)?.[1] || '0';
            }

            return { browser, version };
        },

        /**
         * 是否为移动设备
         */
        isMobileDevice() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
                navigator.userAgent
            );
        },

        /**
         * 是否为微信内置浏览器
         */
        isWeChat() {
            return /MicroMessenger/i.test(navigator.userAgent);
        },

        /**
         * ========== 本地存储 ==========
         */

        /**
         * 设置本地存储
         */
        setStorage(key, value, expire = null) {
            const data = {
                value,
                expire: expire ? Date.now() + expire * 1000 : null,
            };
            try {
                localStorage.setItem(key, JSON.stringify(data));
            } catch (e) {
                console.warn('LocalStorage setItem failed:', e);
            }
        },

        /**
         * 获取本地存储
         */
        getStorage(key, defaultValue = null) {
            try {
                const item = localStorage.getItem(key);
                if (!item) return defaultValue;
                
                const data = JSON.parse(item);
                if (data.expire && Date.now() > data.expire) {
                    localStorage.removeItem(key);
                    return defaultValue;
                }
                return data.value;
            } catch (e) {
                return defaultValue;
            }
        },

        /**
         * 删除本地存储
         */
        removeStorage(key) {
            localStorage.removeItem(key);
        },

        /**
         * 清空本地存储
         */
        clearStorage() {
            localStorage.clear();
        },

        /**
         * ========== Cookie ==========
         */

        /**
         * 设置 Cookie
         */
        setCookie(name, value, expireDays = 7, path = '/') {
            const date = new Date();
            date.setTime(date.getTime() + expireDays * 24 * 60 * 60 * 1000);
            document.cookie = `${name}=${encodeURIComponent(JSON.stringify(value))};expires=${date.toUTCString()};path=${path}`;
        },

        /**
         * 获取 Cookie
         */
        getCookie(name, defaultValue = null) {
            const matches = document.cookie.match(new RegExp(
                '(?:^|; )' + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)'
            ));
            if (matches) {
                try {
                    return decodeURIComponent(matches[1]);
                } catch (e) {
                    return defaultValue;
                }
            }
            return defaultValue;
        },

        /**
         * 删除 Cookie
         */
        deleteCookie(name, path = '/') {
            this.setCookie(name, '', -1, path);
        },

        /**
         * ========== DOM 操作 ==========
         */

        /**
         * 获取元素相对于文档的位置
         */
        getOffset(element) {
            const rect = element.getBoundingClientRect();
            return {
                top: rect.top + window.scrollY,
                left: rect.left + window.scrollX,
                width: rect.width,
                height: rect.height,
            };
        },

        /**
         * 检查元素是否在视口内
         */
        isInViewport(element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        },

        /**
         * 滚动到元素位置
         */
        scrollToElement(element, offset = 0) {
            const top = this.getOffset(element).top - offset;
            window.scrollTo({ top, behavior: 'smooth' });
        },

        /**
         * 防抖函数
         */
        debounce(fn, delay = 300) {
            let timer = null;
            return function(...args) {
                if (timer) clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), delay);
            };
        },

        /**
         * 节流函数
         */
        throttle(fn, delay = 300) {
            let lastTime = 0;
            return function(...args) {
                const now = Date.now();
                if (now - lastTime > delay) {
                    lastTime = now;
                    fn.apply(this, args);
                }
            };
        },

        /**
         * ========== 杂项 ==========
         */

        /**
         * 生成随机字符串
         */
        randomString(length = 16) {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let result = '';
            for (let i = 0; i < length; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        },

        /**
         * 生成 UUID
         */
        uuid() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },

        /**
         * 深拷贝
         */
        deepClone(obj) {
            if (obj === null || typeof obj !== 'object') return obj;
            if (obj instanceof Date) return new Date(obj.getTime());
            if (obj instanceof Array) return obj.map(item => this.deepClone(item));
            if (typeof obj === 'object') {
                const copied = {};
                for (const key in obj) {
                    if (obj.hasOwnProperty(key)) {
                        copied[key] = this.deepClone(obj[key]);
                    }
                }
                return copied;
            }
        },

        /**
         * 对象转 URL 参数
         */
        toQueryString(obj) {
            const params = new URLSearchParams();
            Object.keys(obj).forEach(key => {
                if (obj[key] !== null && obj[key] !== undefined) {
                    params.append(key, obj[key]);
                }
            });
            return params.toString();
        },

        /**
         * URL 参数转对象
         */
        fromQueryString(str) {
            const params = new URLSearchParams(str);
            const result = {};
            for (const [key, value] of params) {
                result[key] = value;
            }
            return result;
        },

        /**
         * 下载文件
         */
        downloadFile(url, filename) {
            const link = document.createElement('a');
            link.href = url;
            link.download = filename || '';
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        /**
         * 复制到剪贴板
         */
        async copyToClipboard(text) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch (e) {
                // fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                return true;
            }
        },
    };

    // 暴露到全局
    global.$helper = helper;

    // ES Module 导出支持
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = helper;
    }

})(typeof window !== 'undefined' ? window : this);
