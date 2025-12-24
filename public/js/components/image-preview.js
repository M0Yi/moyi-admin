;(function () {
    'use strict';

    class ImagePreview {
        constructor() {
            console.log('[ImagePreview] 初始化开始，查找 modal 元素...');
            console.log('[ImagePreview] 当前页面 URL:', window.location.href);
            console.log('[ImagePreview] DOM ready state:', document.readyState);

            this.modalElement = document.getElementById('imagePreviewModal');
            this.container = document.getElementById('imagePreviewContainer');
            this.prevBtn = document.getElementById('imagePreviewPrevBtn');
            this.nextBtn = document.getElementById('imagePreviewNextBtn');
            this.counterEl = document.getElementById('imagePreviewCounter');
            this.captionEl = document.getElementById('imagePreviewCaption');
            this.modal = null;
            this.images = [];
            this.currentIndex = 0;

            console.log('[ImagePreview] modal element 查找结果:', {
                modalElement: !!this.modalElement,
                container: !!this.container,
                prevBtn: !!this.prevBtn,
                nextBtn: !!this.nextBtn,
                counterEl: !!this.counterEl,
                captionEl: !!this.captionEl
            });

            if (!this.modalElement) {
                console.warn('[ImagePreview] modal element not found');
                console.warn('[ImagePreview] 检查是否存在 imagePreviewModal 元素:', !!document.getElementById('imagePreviewModal'));

                // 输出所有 modal 相关的元素，帮助调试
                const allModals = document.querySelectorAll('.modal');
                console.log('[ImagePreview] 页面中所有 .modal 元素:', Array.from(allModals).map(m => ({
                    id: m.id,
                    className: m.className,
                    hidden: m.hidden
                })));

                const allDivsWithId = document.querySelectorAll('div[id]');
                const modalIds = Array.from(allDivsWithId)
                    .filter(div => div.id.includes('modal') || div.classList.contains('modal'))
                    .map(div => div.id);
                console.log('[ImagePreview] 页面中可能相关的 modal ID:', modalIds);

                return;
            }

            console.log('[ImagePreview] modal element 找到，开始绑定事件...');
            this._bindEvents();
            console.log('[ImagePreview] ImagePreview 初始化完成');
        }

        _bindEvents() {
            if (!this.modalElement) return;
            this.modal = new bootstrap.Modal(this.modalElement, { keyboard: true });

            this.prevBtn?.addEventListener('click', () => this.prev());
            this.nextBtn?.addEventListener('click', () => this.next());
            this.modalElement.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') this.prev();
                if (e.key === 'ArrowRight') this.next();
            });
        }

        open(images, options = {}) {
            if (!this.modalElement) {
                console.warn('[ImagePreview] modal element not found');
                return;
            }

            if (!images) return;
            // normalize to array of {src, caption?}
            this.images = Array.isArray(images) ? images.slice() : [images];
            this.images = this.images.map(it => {
                if (typeof it === 'string') return { src: it, caption: '' };
                if (it && typeof it === 'object') return { src: it.src || it.url || '', caption: it.caption || it.title || '' };
                return { src: String(it), caption: '' };
            }).filter(it => it.src);

            if (!this.images.length) return;

            this.currentIndex = parseInt(options.startIndex || 0, 10) || 0;
            if (this.currentIndex < 0) this.currentIndex = 0;
            if (this.currentIndex >= this.images.length) this.currentIndex = this.images.length - 1;

            this._render();
            this.modal.show();
        }

        _render() {
            // clear
            this.container.innerHTML = '';

            const imgInfo = this.images[this.currentIndex];
            const img = document.createElement('img');
            img.src = imgInfo.src;
            img.alt = imgInfo.caption || '';
            img.style.maxWidth = '100%';
            img.style.maxHeight = '80vh';
            img.style.objectFit = 'contain';
            img.style.objectPosition = 'center';
            img.style.cursor = 'pointer';
            img.addEventListener('click', () => {
                window.open(imgInfo.src, '_blank');
            });

            // preload next & prev
            this._preload(this.currentIndex + 1);
            this._preload(this.currentIndex - 1);

            this.container.appendChild(img);

            // update controls
            // 当只有一张图片时，不显示序号
            if (this.images.length === 1) {
                this.counterEl.style.display = 'none';
            } else {
                this.counterEl.style.display = '';
                this.counterEl.textContent = `${this.currentIndex + 1} / ${this.images.length}`;
            }

            this.captionEl.textContent = imgInfo.caption || '';

            // 动态显示/隐藏标题区域
            const captionContainer = document.getElementById('imagePreviewCaptionContainer');
            if (captionContainer) {
                if (imgInfo.caption && imgInfo.caption.trim()) {
                    captionContainer.classList.remove('d-none');
                } else {
                    captionContainer.classList.add('d-none');
                }
            }

            // 当只有一张图片时，隐藏左右按钮
            if (this.images.length === 1) {
                this.prevBtn.style.display = 'none';
                this.nextBtn.style.display = 'none';
            } else {
                this.prevBtn.style.display = '';
                this.nextBtn.style.display = '';
                this.prevBtn.disabled = this.currentIndex <= 0;
                this.nextBtn.disabled = this.currentIndex >= this.images.length - 1;
            }
        }

        _preload(index) {
            if (index < 0 || index >= this.images.length) return;
            const src = this.images[index].src;
            const img = new Image();
            img.src = src;
        }

        next() {
            if (this.currentIndex < this.images.length - 1) {
                this.currentIndex++;
                this._render();
            }
        }

        prev() {
            if (this.currentIndex > 0) {
                this.currentIndex--;
                this._render();
            }
        }
    }

    // singleton holder and pending queue
    window._imagePreviewInstance = window._imagePreviewInstance || null;
    window._imagePreviewPending = window._imagePreviewPending || [];

    function initImagePreviewWhenReady() {
        // initialize only once
        if (window._imagePreviewInstance) return;

        // create instance when bootstrap.Modal is available and DOM is ready
        const tryInit = () => {
            const hasBootstrapModal = typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function';
            const domReady = document.readyState === 'interactive' || document.readyState === 'complete';
            if (hasBootstrapModal && domReady) {
                try {
                    window._imagePreviewInstance = new ImagePreview();
                    // drain pending calls
                    while (window._imagePreviewPending.length) {
                        const { images, options } = window._imagePreviewPending.shift();
                        try {
                            window._imagePreviewInstance.open(images, options || {});
                        } catch (e) {
                            console.error('[ImagePreview] pending open failed', e);
                        }
                    }
                    return true;
                } catch (e) {
                    console.error('[ImagePreview] init failed', e);
                    return false;
                }
            }
            return false;
        };

        // first try immediately
        if (tryInit()) return;

        // then wait for DOMContentLoaded and try again
        document.addEventListener('DOMContentLoaded', () => {
            if (tryInit()) return;
            // poll for bootstrap up to timeout
            const start = Date.now();
            const interval = setInterval(() => {
                if (tryInit()) {
                    clearInterval(interval);
                    return;
                }
                if (Date.now() - start > 10000) { // 10s timeout
                    clearInterval(interval);
                    console.warn('[ImagePreview] bootstrap.Modal not found within timeout; preview unavailable until bootstrap loads.');
                }
            }, 200);
        });
    }

    // Global helper: openImagePreview(images, options)
    window.openImagePreview = function (images, options) {
        console.log('[ImagePreview] openImagePreview 被调用:', { images, options });

        if (window._imagePreviewInstance) {
            console.log('[ImagePreview] 单例实例存在，尝试打开预览...');
            try {
                return window._imagePreviewInstance.open(images, options || {});
            } catch (e) {
                console.error('[ImagePreview] open failed', e);
                return;
            }
        }

        console.log('[ImagePreview] 单例实例不存在，加入队列...');
        // queue if not initialized yet
        window._imagePreviewPending.push({ images, options: options || {} });
        console.log('[ImagePreview] 当前队列长度:', window._imagePreviewPending.length);

        // trigger initialization routine
        initImagePreviewWhenReady();
    };

    // kick off init attempt
    console.log('[ImagePreview] 启动初始化检查...');
    initImagePreviewWhenReady();
    console.log('[ImagePreview] 初始化检查已启动');

})();


