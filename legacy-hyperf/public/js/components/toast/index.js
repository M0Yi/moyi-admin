/**
 * Toast 通知组件
 * 
 * 功能特性：
 * - 支持多种类型（success、error、warning、info）
 * - 自动消失（默认 3 秒）
 * - 手动关闭
 * - 多个 Toast 同时显示
 * - 位置可配置（top-right、top-left、bottom-right、bottom-left、top-center、bottom-center）
 * 
 * 全局暴露：window.$toast
 * 
 * 使用方法：
 * $toast.success('操作成功');
 * $toast.error('操作失败');
 * $toast.warning('警告信息');
 * $toast.info('提示信息');
 */

(function(global) {
    'use strict';

    /**
     * Toast 配置
     */
    const defaultConfig = {
        duration: 3000,        // 默认显示时间（毫秒）
        position: 'top-right', // 默认位置
        maxToasts: 5,          // 最大同时显示数量
        dismissible: true,     // 是否可手动关闭
    };

    /**
     * Toast 类
     */
    class Toast {
        constructor(message, type = 'info', options = {}) {
            this.id = Date.now() + Math.random().toString(36).substr(2, 9);
            this.message = message;
            this.type = type;
            this.options = { ...defaultConfig, ...options };
            this.show = true;
            this.timer = null;
            
            // 自动消失
            if (this.options.duration > 0) {
                this.startTimer();
            }
        }

        startTimer() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.dismiss();
            }, this.options.duration);
        }

        resetTimer() {
            if (this.options.duration > 0) {
                this.startTimer();
            }
        }

        dismiss() {
            this.show = false;
            clearTimeout(this.timer);
        }

        // 获取样式类
        getClasses() {
            const typeMap = {
                success: 'bg-success',
                error: 'bg-danger',
                warning: 'bg-warning',
                info: 'bg-info',
            };
            
            const iconMap = {
                success: 'bi-check-circle-fill',
                error: 'bi-x-circle-fill',
                warning: 'bi-exclamation-triangle-fill',
                info: 'bi-info-circle-fill',
            };

            return {
                bgClass: typeMap[this.type] || 'bg-info',
                iconClass: iconMap[this.type] || 'bi-info-circle-fill',
            };
        }
    }

    /**
     * Toast 管理器
     */
    class ToastManager {
        constructor() {
            this.toasts = [];
            this.options = { ...defaultConfig };
        }

        /**
         * 显示 Toast
         */
        show(message, type = 'info', options = {}) {
            // 限制最大数量
            if (this.toasts.length >= this.options.maxToasts) {
                this.toasts.shift(); // 移除最早的
            }

            const toast = new Toast(message, type, options);
            this.toasts.push(toast);

            // 重置计时器
            if (options.duration !== 0) {
                toast.resetTimer();
            }

            return toast;
        }

        /**
         * 快捷方法
         */
        success(message, options = {}) {
            return this.show(message, 'success', options);
        }

        error(message, options = {}) {
            return this.show(message, 'error', options);
        }

        warning(message, options = {}) {
            return this.show(message, 'warning', options);
        }

        info(message, options = {}) {
            return this.show(message, 'info', options);
        }

        /**
         * 移除 Toast
         */
        remove(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) {
                toast.dismiss();
            }
        }

        /**
         * 清除所有
         */
        clear() {
            this.toasts.forEach(toast => toast.dismiss());
            this.toasts = [];
        }

        /**
         * 设置配置
         */
        config(options) {
            this.options = { ...this.options, ...options };
        }
    }

    // 创建全局实例
    global.$toast = new ToastManager();

    // ES Module 导出支持
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = ToastManager;
    }

})(typeof window !== 'undefined' ? window : this);
