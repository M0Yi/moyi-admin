/**
 * Loading 组件
 * 
 * 功能特性：
 * - 全屏 Loading
 * - 按钮 Loading
 * - 自动控制（与 $http 集成）
 * 
 * 全局暴露：window.$loading
 * 
 * 使用方法：
 * $loading.show();    // 显示全屏 Loading
 * $loading.hide();    // 隐藏全屏 Loading
 */

(function(global) {
    'use strict';

    /**
     * Loading 管理器
     */
    class LoadingManager {
        constructor() {
            this.count = 0;  // 引用计数，用于支持嵌套调用
            this.element = null;
            this.spinner = null;
            this.init();
        }

        /**
         * 初始化 DOM 元素
         */
        init() {
            // 如果已存在，不重复创建
            if (document.getElementById('global-loading-overlay')) {
                this.element = document.getElementById('global-loading-overlay');
                this.spinner = this.element.querySelector('.loading-spinner');
                return;
            }

            // 创建 Loading 容器
            this.element = document.createElement('div');
            this.element.id = 'global-loading-overlay';
            this.element.className = 'loading-overlay';
            this.element.innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">加载中...</span>
                    </div>
                    <div class="loading-text mt-2">加载中...</div>
                </div>
            `;

            // 添加样式
            const style = document.createElement('style');
            style.textContent = `
                .loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.5);
                    display: none;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                    backdrop-filter: blur(2px);
                }
                .loading-overlay.show {
                    display: flex;
                }
                .loading-spinner {
                    text-align: center;
                    color: white;
                }
                .loading-text {
                    font-size: 14px;
                    color: #fff;
                }
                .spinner-border {
                    width: 3rem;
                    height: 3rem;
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(this.element);
            this.spinner = this.element.querySelector('.loading-spinner');
        }

        /**
         * 显示 Loading
         */
        show(text = '加载中...') {
            this.count++;
            this.element.classList.add('show');
            
            // 更新文本
            const textEl = this.element.querySelector('.loading-text');
            if (textEl) {
                textEl.textContent = text;
            }
        }

        /**
         * 隐藏 Loading
         */
        hide() {
            this.count = Math.max(0, this.count - 1);
            if (this.count === 0) {
                this.element.classList.remove('show');
            }
        }

        /**
         * 强制隐藏（重置计数）
         */
        forceHide() {
            this.count = 0;
            this.element.classList.remove('show');
        }

        /**
         * 设置 Loading 文本
         */
        setText(text) {
            const textEl = this.element.querySelector('.loading-text');
            if (textEl) {
                textEl.textContent = text;
            }
        }

        /**
         * 检查是否正在显示
         */
        isShowing() {
            return this.count > 0;
        }
    }

    // 创建全局实例
    global.$loading = new LoadingManager();

    // ES Module 导出支持
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = LoadingManager;
    }

})(typeof window !== 'undefined' ? window : this);
