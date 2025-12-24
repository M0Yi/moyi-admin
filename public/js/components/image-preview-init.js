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
                <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                    <div class="modal-body p-0 position-relative">
                        <div class="image-preview-stage position-relative text-center rounded-top" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                            <!-- 序号显示在左上角 -->
                            <div class="preview-counter-overlay position-absolute top-0 start-0 mt-3 ms-3" id="imagePreviewCounter"></div>

                            <button type="button" class="preview-arrow left shadow-sm" id="imagePreviewPrevBtn" aria-label="上一张">
                                <i class="bi bi-chevron-left" style="font-size: 1.5rem; color: #495057;"></i>
                            </button>
                            <div id="imagePreviewContainer" class="w-100 d-flex align-items-center justify-content-center p-4">
                                <!-- image inserted here -->
                            </div>
                            <button type="button" class="preview-arrow right shadow-sm" id="imagePreviewNextBtn" aria-label="下一张">
                                <i class="bi bi-chevron-right" style="font-size: 1.5rem; color: #495057;"></i>
                            </button>
                        </div>

                        <!-- 标题区域：动态显示 -->
                        <div id="imagePreviewCaptionContainer" class="d-none bg-white rounded-bottom px-4 py-3 border-top">
                            <div id="imagePreviewCaption" class="preview-caption text-dark text-center"></div>
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
