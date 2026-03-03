/**
 * Modal 弹窗管理器
 * 
 * 功能特性：
 * - 程序化打开/关闭 Modal
 * - 支持自定义内容
 * - 事件监听（onOpen、onClose、onConfirm）
 * - 支持拖拽（可选）
 * 
 * 全局暴露：window.$modal
 * 
 * 使用方法：
 * $modal.show({ title: '标题', content: '内容' });
 * $modal.hide();
 */

(function(global) {
    'use strict';

    /**
     * Modal 配置
     */
    const defaultConfig = {
        title: '弹窗标题',
        content: '',
        size: 'md', // sm, md, lg, xl, full
        centered: true,
        scrollable: false,
        closeOnClickOverlay: true,
        showCloseButton: true,
        showFooter: true,
        confirmText: '确定',
        cancelText: '取消',
        onOpen: null,
        onClose: null,
        onConfirm: null,
    };

    /**
     * Modal 管理器
     */
    class ModalManager {
        constructor() {
            this.config = { ...defaultConfig };
            this.element = null;
            this.isOpen = false;
            this.resolvePromise = null;
            this.init();
        }

        /**
         * 初始化 DOM 元素
         */
        init() {
            if (document.getElementById('global-modal-container')) {
                this.element = document.getElementById('global-modal-container');
                return;
            }

            // 创建 Modal 容器
            this.element = document.createElement('div');
            this.element.id = 'global-modal-container';
            this.element.className = 'modal fade';
            this.element.tabIndex = -1;
            this.element.style.zIndex = '10001';
            this.element.innerHTML = `
                <div class="modal-dialog" id="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modal-title"></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="modal-close-btn"></button>
                        </div>
                        <div class="modal-body" id="modal-body"></div>
                        <div class="modal-footer" id="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="modal-cancel-btn"></button>
                            <button type="button" class="btn btn-primary" id="modal-confirm-btn"></button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(this.element);

            // 绑定事件
            const confirmBtn = this.element.querySelector('#modal-confirm-btn');
            confirmBtn.addEventListener('click', () => this.handleConfirm());
            
            // 关闭事件
            this.element.addEventListener('hidden.bs.modal', () => {
                this.isOpen = false;
                this.handleClose();
            });

            this.element.addEventListener('shown.bs.modal', () => {
                this.isOpen = true;
                this.handleOpen();
            });
        }

        /**
         * 显示 Modal
         */
        show(options = {}) {
            const config = { ...this.config, ...options };
            
            // 更新内容
            const dialog = this.element.querySelector('#modal-dialog');
            const titleEl = this.element.querySelector('#modal-title');
            const bodyEl = this.element.querySelector('#modal-body');
            const footerEl = this.element.querySelector('#modal-footer');
            const confirmBtn = this.element.querySelector('#modal-confirm-btn');
            const cancelBtn = this.element.querySelector('#modal-cancel-btn');

            // 设置大小
            dialog.className = 'modal-dialog';
            if (config.size !== 'md') {
                dialog.classList.add(`modal-${config.size}`);
            }
            if (config.centered) {
                dialog.classList.add('modal-dialog-centered');
            }
            if (config.scrollable) {
                dialog.classList.add('modal-dialog-scrollable');
            }

            // 设置标题
            // 支持 HTML 内容
            if (typeof config.title === 'string') {
                titleEl.innerHTML = config.title;
            } else {
                titleEl.textContent = config.title;
            }

            // 设置内容
            if (typeof config.content === 'string') {
                bodyEl.innerHTML = config.content;
            } else if (config.content instanceof HTMLElement) {
                bodyEl.innerHTML = '';
                bodyEl.appendChild(config.content);
            } else if (typeof config.content === 'function') {
                const content = config.content();
                if (content instanceof HTMLElement) {
                    bodyEl.innerHTML = '';
                    bodyEl.appendChild(content);
                } else {
                    bodyEl.innerHTML = content;
                }
            }

            // 设置按钮
            if (config.showFooter) {
                footerEl.style.display = 'flex';
                confirmBtn.textContent = config.confirmText;
                cancelBtn.textContent = config.cancelText;
            } else {
                footerEl.style.display = 'none';
            }

            // 显示 Modal
            const modal = bootstrap.Modal.getOrCreateInstance(this.element, {
                backdrop: config.closeOnClickOverlay ? true : 'static',
                keyboard: !config.closeOnClickOverlay,
            });
            modal.show();

            // 返回 Promise（用于 onConfirm）
            return new Promise((resolve) => {
                this.resolvePromise = resolve;
            });
        }

        /**
         * 隐藏 Modal
         */
        hide() {
            const modal = bootstrap.Modal.getInstance(this.element);
            if (modal) {
                modal.hide();
            }
        }

        /**
         * 设置 Modal 内容
         */
        setContent(content) {
            const bodyEl = this.element.querySelector('#modal-body');
            if (typeof content === 'string') {
                bodyEl.innerHTML = content;
            } else if (content instanceof HTMLElement) {
                bodyEl.innerHTML = '';
                bodyEl.appendChild(content);
            }
        }

        /**
         * 设置标题
         */
        setTitle(title) {
            const titleEl = this.element.querySelector('#modal-title');
            titleEl.textContent = title;
        }

        /**
         * 处理确认
         */
        handleConfirm() {
            if (this.config.onConfirm) {
                const result = this.config.onConfirm();
                if (result instanceof Promise) {
                    result.then((confirmed) => {
                        if (confirmed !== false) {
                            this.hide();
                        }
                    });
                } else if (result !== false) {
                    this.hide();
                }
            } else {
                this.resolvePromise?.(true);
                this.hide();
            }
        }

        /**
         * 处理打开
         */
        handleOpen() {
            if (this.config.onOpen) {
                this.config.onOpen();
            }
        }

        /**
         * 处理关闭
         */
        handleClose() {
            this.resolvePromise?.(false);
            if (this.config.onClose) {
                this.config.onClose();
            }
        }

        /**
         * 快捷方法
         */
        alert(message, title = '提示') {
            return this.show({
                title,
                content: `<div class="alert alert-info mb-0">${message}</div>`,
                showFooter: false,
                showCloseButton: true,
            });
        }

        success(message, title = '成功') {
            return this.show({
                title,
                content: `<div class="alert alert-success mb-0">${message}</div>`,
                confirmText: '确定',
                showFooter: true,
            });
        }

        error(message, title = '错误') {
            return this.show({
                title,
                content: `<div class="alert alert-danger mb-0">${message}</div>`,
                confirmText: '确定',
                confirmClass: 'btn-danger',
                showFooter: true,
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
    global.$modal = new ModalManager();

    // ES Module 导出支持
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = ModalManager;
    }

})(typeof window !== 'undefined' ? window : this);
