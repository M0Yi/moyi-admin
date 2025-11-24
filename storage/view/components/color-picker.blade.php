{{--
颜色选择器组件

使用方式：
@include('components.color-picker')
--}}

@php
    $defaultColors = $presetColors ?? [
        '#667eea', '#764ba2', '#0d6efd', '#6610f2', '#6f42c1',
        '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#20c997',
        '#0dcaf0', '#198754', '#212529', '#495057', '#6c757d',
        '#adb5bd', '#f8f9fa', '#343a40', '#111827', '#8b5cf6',
        '#a855f7', '#ec4899', '#f472b6', '#f97316', '#fb923c',
        '#facc15', '#fde047', '#22c55e', '#4ade80', '#14b8a6',
        '#2dd4bf', '#0ea5e9', '#38bdf8', '#1d4ed8', '#2563eb',
        '#1abc9c', '#16a085', '#2ecc71', '#27ae60', '#f39c12',
        '#e67e22', '#e74c3c', '#c0392b', '#8e44ad', '#9b59b6',
        '#34495e', '#2c3e50', '#95a5a6', '#7f8c8d', '#ecf0f1',
    ];
@endphp

<script>
window.ColorPickerPresetColors = window.ColorPickerPresetColors || @json(array_values(array_unique($defaultColors)));
</script>

<!-- 颜色选择器模态框 -->
<div class="modal fade" id="colorPickerModal" tabindex="-1" aria-labelledby="colorPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="colorPickerModalLabel">
                    <i class="bi bi-palette2 me-2"></i> 选择颜色
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="customColorInput" class="form-label fw-semibold">自定义颜色</label>
                    <div class="input-group">
                        <span class="input-group-text">#</span>
                        <input
                            type="text"
                            class="form-control"
                            id="customColorInput"
                            placeholder="例如：667eea 或 #667eea"
                            autocomplete="off"
                        >
                        <button class="btn btn-outline-secondary" type="button" id="customColorApplyBtn">
                            应用
                        </button>
                    </div>
                    <div class="form-text">支持 3/6/8 位 HEX 值，例如：#fff、#667eea、#667eea80</div>
                </div>

                <div class="mb-2 d-flex align-items-center gap-2">
                    <span class="fw-semibold">预设颜色</span>
                    <span class="text-muted small">点击颜色即可快速选择</span>
                </div>

                <div id="colorGrid" class="color-grid">
                    <div class="text-center py-5 text-muted">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 mb-0">正在加载颜色...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="me-auto small text-muted">
                    已选择：<span id="selectedColorPreviewText" class="fw-bold text-primary">未选择</span>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="confirmColorBtn" disabled>确定</button>
            </div>
        </div>
    </div>
</div>

<style>
.color-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(56px, 1fr));
    gap: 10px;
    max-height: 360px;
    overflow-y: auto;
}

.color-swatch {
    width: 100%;
    padding-top: 100%;
    position: relative;
    border-radius: 0.5rem;
    border: 2px solid transparent;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.color-swatch::after {
    content: '';
    position: absolute;
    inset: 4px;
    border-radius: 0.4rem;
    background: inherit;
}

.color-swatch:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
}

.color-swatch.selected {
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.35);
}

.color-preview-swatch {
    display: inline-block;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 0.35rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.color-input-group .input-group-text {
    background-color: #fff;
}

.color-grid::-webkit-scrollbar {
    width: 8px;
}

.color-grid::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.color-grid::-webkit-scrollbar-thumb {
    background: #0d6efd;
    border-radius: 4px;
}

.color-grid::-webkit-scrollbar-thumb:hover {
    background: #0b5ed7;
}
</style>

{{-- 引入颜色选择器 JavaScript --}}
@include('components.admin-script', ['path' => '/js/components/color-picker.js'])


