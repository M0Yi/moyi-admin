;(function () {
    'use strict';

    // 依赖检查：确保 image-preview.js 已加载
    if (typeof window.openImagePreview === 'undefined') {
        console.warn('[ImagePreview] image-preview.js not loaded, skipping initialization');
        return;
    }

    // 检查 modal 是否已存在
    const existingModal = document.getElementById('imagePreviewModal');
    if (existingModal) {
        console.log('[ImagePreview] modal already exists, skipping initialization');
        return;
    }

    console.log('[ImagePreview] initializing modal...');

    // 创建 modal HTML
    const modalHtml = `
        <div class="modal fade image-preview-modal" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content bg-dark border-0">
                    <div class="modal-body p-0 position-relative">
                        <div class="image-preview-stage position-relative text-center">
                            <button type="button" class="preview-arrow left" id="imagePreviewPrevBtn" aria-label="上一张">
                                <i class="bi bi-chevron-left" style="font-size: 1.5rem; color: #fff;"></i>
                            </button>
                            <div id="imagePreviewContainer" class="w-100 d-flex align-items-center justify-content-center p-3">
                                <!-- image inserted here -->
                            </div>
                            <button type="button" class="preview-arrow right" id="imagePreviewNextBtn" aria-label="下一张">
                                <i class="bi bi-chevron-right" style="font-size: 1.5rem; color: #fff;"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-center align-items-center ">
                            <div class="preview-controls d-flex align-items-center gap-3">
                                <span class="preview-counter" id="imagePreviewCounter"></span>
                                <div id="imagePreviewCaption" class="preview-caption"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // 将 modal 插入到 body 末尾
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    console.log('[ImagePreview] modal HTML inserted, initialization complete');

})();
