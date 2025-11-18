{{--
图标选择器组件

使用方式：
@include('components.icon-picker', ['targetInputId' => 'icon'])
--}}

<!-- 图标选择器模态框 -->
<div class="modal fade" id="iconPickerModal" tabindex="-1" aria-labelledby="iconPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="iconPickerModalLabel">
                    <i class="bi bi-emoji-smile me-2"></i> 选择图标
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- 搜索框 -->
                <div class="mb-3">
                    <input
                        type="text"
                        class="form-control"
                        id="iconSearchInput"
                        placeholder="搜索图标，例如：house, user, settings..."
                        autocomplete="off"
                    >
                    <div class="form-text">
                        共 <span id="iconTotalCount">0</span> 个图标
                        <span id="iconFilterCount" class="text-primary"></span>
                    </div>
                </div>

                <!-- 图标网格 -->
                <div id="iconGrid" class="icon-grid">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">加载中...</span>
                        </div>
                        <p class="mt-3 text-muted">正在加载图标...</p>
                    </div>
                </div>

                <!-- 无结果提示 -->
                <div id="iconNoResults" class="text-center py-5" style="display: none;">
                    <i class="bi bi-search" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3 text-muted">未找到匹配的图标</p>
                </div>
            </div>
            <div class="modal-footer">
                <div class="me-auto">
                    <small class="text-muted">
                        已选择：<span id="selectedIconPreview" class="text-primary fw-bold">未选择</span>
                    </small>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="confirmIconBtn" disabled>确定</button>
            </div>
        </div>
    </div>
</div>

<style>
/* 图标网格样式 */
.icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 10px;
    max-height: 400px;
    overflow-y: auto;
}

.icon-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 15px 10px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
}

.icon-item:hover {
    border-color: #667eea;
    background: #f8f9ff;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.1);
}

.icon-item.selected {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.icon-item i {
    font-size: 24px;
    margin-bottom: 5px;
}

.icon-item.selected i {
    color: white;
}

.icon-item .icon-name {
    font-size: 10px;
    text-align: center;
    word-break: break-word;
    opacity: 0.8;
}

/* 自定义滚动条 */
.icon-grid::-webkit-scrollbar {
    width: 8px;
}

.icon-grid::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.icon-grid::-webkit-scrollbar-thumb {
    background: #667eea;
    border-radius: 4px;
}

.icon-grid::-webkit-scrollbar-thumb:hover {
    background: #764ba2;
}

/* 加载动画 */
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

{{-- 引入图标选择器 JavaScript --}}
<script src="/js/components/icon-picker.js"></script>

