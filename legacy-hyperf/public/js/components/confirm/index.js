/**
 * Confirm 确认对话框组件
 * 
 * 功能特性：
 * - 替代原生 confirm
 * - 自定义标题、内容、按钮文字
 * - 返回 Promise（async/await）
 * - 支持多种样式（danger、warning、info）
 * 
 * 全局暴露：window.$confirm
 * 
 * 使用方法：
 * const confirmed = await $confirm('确定删除？');
 * if (confirmed) { ... }
 */

(function(global) {
    'use strict';

    /**
     * Confirm 配置
     */
    const defaultConfig = {
        title: '确认操作',
        message: '确定要执行此操作吗？',
        confirmText: '确定',
        cancelText: '取消',
        confirmClass: 'btn-primary',
        cancelClass: 'btn-secondary',
        type: 'primary', // primary, danger, warning, info
        dangerouslyUseHTMLString: false,
        showCancelButton: true,
        showCloseButton: false,
        closeOnClickOverlay: false,
    };

    /**
     * Confirm 管理器
     */
    class ConfirmManager {
        constructor() {
            this.config = { ...defaultConfig };
            this.element = null;
            this.resolvePromise = null;
            this.init();
        }

        /**
         * 初始化 DOM 元素
         */
        init() {
            if (document.getElementById('global-confirm-modal')) {
                this.element = document.getElementById('global-confirm-modal');
                return;
            }

            // 创建 Modal 容器
            this.element = document.createElement('div');
            this.element.id = 'global-confirm-modal';
            this.element.className = 'modal fade';
            this.element.tabIndex = -1;
            this.element.style.zIndex = '10000';
            this.element.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="confirm-title"></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="confirm-close-btn" style="display: none;"></button>
                        </div>
                        <div class="modal-body" id="confirm-message"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn" id="confirm-cancel-btn"></button>
                            <button type="button" class="btn" id="confirm-ok-btn"></button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(this.element);

            // 绑定事件
            const okBtn = this.element.querySelector('#confirm-ok-btn');
            const cancelBtn = this.element.querySelector('#confirm-cancel-btn');
            const closeBtn = this.element.querySelector('#confirm-close-btn');

            okBtn.addEventListener('click', () => this.resolve(true));
            cancelBtn.addEventListener('click', () => this.resolve(false));
            closeBtn.addEventListener('click', () => this.resolve(false));

            // 点击遮罩层关闭
            if (this.config.closeOnClickOverlay) {
                this.element.addEventListener('click', (e) => {
                    if (e.target === this.element) {
                        this.resolve(false);
                    }
                });
            }
        }

        /**
         * 显示 Confirm 对话框
         */
        show(options = {}) {
            const config = { ...this.config, ...options };
            
            // 更新内容
            const titleEl = this.element.querySelector('#confirm-title');
            const messageEl = this.element.querySelector('#confirm-message');
            const okBtn = this.element.querySelector('#confirm-ok-btn');
            const cancelBtn = this.element.querySelector('#confirm-cancel-btn');
            const closeBtn = this.element.querySelector('#confirm-close-btn');

            titleEl.textContent = config.title;
            
            if (config.dangerouslyUseHTMLString) {
                messageEl.innerHTML = config.message;
            } else {
                messageEl.textContent = config.message;
            }

            okBtn.textContent = config.confirmText;
            okBtn.className = `btn ${config.confirmClass}`;

            if (config.showCancelButton) {
                cancelBtn.textContent = config.cancelText;
                cancelBtn.className = `btn ${config.cancelClass}`;
                cancelBtn.style.display = 'block';
            } else {
                cancelBtn.style.display = 'none';
            }

            closeBtn.style.display = config.showCloseButton ? 'block' : 'none';

            // 显示 Modal
            const modal = bootstrap.Modal.getOrCreateInstance(this.element);
            modal.show();

            // 返回 Promise
            return new Promise((resolve) => {
                this.resolvePromise = resolve;
            }).then((confirmed) => {
                modal.hide();
                return confirmed;
            });
        }

        /**
         * 解析 Promise
         */
        resolve(value) {
            if (this.resolvePromise) {
                this.resolvePromise(value);
                this.resolvePromise = null;
            }
        }

        /**
         * 快捷方法
         */
        async confirm(message, title = '确认操作') {
            return this.show({ message, title, type: 'primary' });
        }

        async danger(message, title = '确认删除') {
            return this.show({ 
                message, 
                title, 
                type: 'danger',
                confirmText: '删除',
                confirmClass: 'btn-danger',
            });
        }

        async warning(message, title = '确认警告') {
            return this.show({ 
                message, 
                title, 
                type: 'warning',
                confirmText: '确定',
                confirmClass: 'btn-warning',
            });
        }

        async info(message, title = '确认信息') {
            return this.show({ 
                message, 
                title, 
                type: 'info',
                confirmText: '确定',
                confirmClass: 'btn-info',
            });
        }

        /**
         * 设置默认配置
         */
        setConfig(options) {
            this.config = { ...this.config, ...options };
        }
    }

    // 创建全局实例
    global.$confirm = new ConfirmManager();

    // ES Module 导出支持
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = ConfirmManager;
    }

})(typeof window !== 'undefined' ? window : this);
