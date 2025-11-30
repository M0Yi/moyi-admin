{{--
渐变颜色选择器组件

使用方式：
@include('components.gradient-picker')
--}}

<!-- 渐变颜色选择器模态框 -->
<div class="modal fade" id="gradientPickerModal" tabindex="-1" aria-labelledby="gradientPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gradientPickerModalLabel">
                    <i class="bi bi-palette2 me-2"></i> 选择渐变色
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- 渐变预览 --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold">预览</label>
                    <div
                        id="gradientPreview"
                        class="gradient-preview"
                        style="width: 100%; height: 120px; border-radius: 8px; border: 1px solid #e5e7eb; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"
                    ></div>
                </div>

                {{-- 预设渐变 --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold">预设渐变</label>
                    <div class="d-flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary preset-gradient-btn"
                            data-preset-gradient="linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
                            style="width: 80px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: 2px solid transparent;"
                            title="紫蓝渐变"
                        ></button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary preset-gradient-btn"
                            data-preset-gradient="linear-gradient(135deg, #f093fb 0%, #f5576c 100%)"
                            style="width: 80px; height: 40px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: 2px solid transparent;"
                            title="粉红渐变"
                        ></button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary preset-gradient-btn"
                            data-preset-gradient="linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)"
                            style="width: 80px; height: 40px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: 2px solid transparent;"
                            title="蓝色渐变"
                        ></button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary preset-gradient-btn"
                            data-preset-gradient="linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)"
                            style="width: 80px; height: 40px; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); border: 2px solid transparent;"
                            title="绿色渐变"
                        ></button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary preset-gradient-btn"
                            data-preset-gradient="linear-gradient(135deg, #fa709a 0%, #fee140 100%)"
                            style="width: 80px; height: 40px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); border: 2px solid transparent;"
                            title="粉黄渐变"
                        ></button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary preset-gradient-btn"
                            data-preset-gradient="linear-gradient(135deg, #30cfd0 0%, #330867 100%)"
                            style="width: 80px; height: 40px; background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); border: 2px solid transparent;"
                            title="青紫渐变"
                        ></button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary preset-gradient-btn"
                            data-preset-gradient="linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)"
                            style="width: 80px; height: 40px; background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border: 2px solid transparent;"
                            title="浅色渐变"
                        ></button>
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary preset-gradient-btn"
                            data-preset-gradient="linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)"
                            style="width: 80px; height: 40px; background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); border: 2px solid transparent;"
                            title="粉红渐变"
                        ></button>
                    </div>
                    <div class="form-text">点击预设渐变快速应用</div>
                </div>

                {{-- 渐变角度 --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label for="gradientAngleInput" class="form-label fw-semibold mb-0">渐变角度</label>
                        <span class="badge bg-primary" id="gradientAngleDisplay">135°</span>
                    </div>
                    <div class="position-relative">
                        <input
                            type="range"
                            class="form-range"
                            id="gradientAngleInput"
                            min="0"
                            max="360"
                            value="135"
                            step="1"
                            style="cursor: pointer;"
                        >
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">0°</small>
                            <small class="text-muted">90°</small>
                            <small class="text-muted">180°</small>
                            <small class="text-muted">270°</small>
                            <small class="text-muted">360°</small>
                        </div>
                    </div>
                    <div class="form-text">
                        <span id="gradientAngleDescription">从左上到右下（135°）</span>
                    </div>
                </div>

                {{-- 颜色1 --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">起始颜色</label>
                    <div class="row g-2">
                        <div class="col-8">
                            <div class="input-group">
                                <span class="input-group-text p-0" style="width: 40px; border-right: none;">
                                    <span
                                        id="gradientColor1Preview"
                                        class="color-preview-swatch d-inline-block w-100 h-100"
                                        style="background-color: #667eea; border-radius: 0.375rem 0 0 0.375rem;"
                                    ></span>
                                </span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="gradientColor1Input"
                                    placeholder="#667eea"
                                    value="#667eea"
                                >
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    id="gradientColor1PickerBtn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#colorPickerModal"
                                    data-target-input="gradientColor1Input"
                                    data-preview-target="gradientColor1Preview"
                                >
                                    <i class="bi bi-palette2 me-1"></i>选择
                                </button>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="input-group">
                                <input
                                    type="number"
                                    class="form-control"
                                    id="gradientColor1StopInput"
                                    min="0"
                                    max="100"
                                    value="0"
                                    step="1"
                                >
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-text">起始颜色和位置（0-100%）</div>
                </div>

                {{-- 颜色2 --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">结束颜色</label>
                    <div class="row g-2">
                        <div class="col-8">
                            <div class="input-group">
                                <span class="input-group-text p-0" style="width: 40px; border-right: none;">
                                    <span
                                        id="gradientColor2Preview"
                                        class="color-preview-swatch d-inline-block w-100 h-100"
                                        style="background-color: #764ba2; border-radius: 0.375rem 0 0 0.375rem;"
                                    ></span>
                                </span>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="gradientColor2Input"
                                    placeholder="#764ba2"
                                    value="#764ba2"
                                >
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    id="gradientColor2PickerBtn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#colorPickerModal"
                                    data-target-input="gradientColor2Input"
                                    data-preview-target="gradientColor2Preview"
                                >
                                    <i class="bi bi-palette2 me-1"></i>选择
                                </button>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="input-group">
                                <input
                                    type="number"
                                    class="form-control"
                                    id="gradientColor2StopInput"
                                    min="0"
                                    max="100"
                                    value="100"
                                    step="1"
                                >
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-text">结束颜色和位置（0-100%）</div>
                </div>

                {{-- 输出值（隐藏） --}}
                <input type="hidden" id="gradientOutputInput">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="confirmGradientBtn">
                    <i class="bi bi-check-lg me-1"></i>确认
                </button>
            </div>
        </div>
    </div>
</div>

{{-- 引入渐变选择器脚本 --}}
<script src="/js/components/gradient-picker.js"></script>

<style>
.gradient-preview {
    transition: background 0.3s ease;
}

.preset-gradient-btn {
    transition: transform 0.2s ease, border-color 0.2s ease;
}

.preset-gradient-btn:hover {
    transform: scale(1.05);
    border-color: var(--primary-color, #667eea) !important;
}

.preset-gradient-btn:active {
    transform: scale(0.95);
}

/* 角度滑块样式 */
#gradientAngleInput {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 8px;
    border-radius: 4px;
    background: linear-gradient(to right, #e5e7eb 0%, #667eea 25%, #764ba2 50%, #667eea 75%, #e5e7eb 100%);
    outline: none;
    opacity: 0.8;
    transition: opacity 0.2s;
}

#gradientAngleInput:hover {
    opacity: 1;
}

#gradientAngleInput::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid var(--primary-color, #667eea);
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    transition: transform 0.2s, box-shadow 0.2s;
}

#gradientAngleInput::-webkit-slider-thumb:hover {
    transform: scale(1.1);
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
}

#gradientAngleInput::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid var(--primary-color, #667eea);
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    transition: transform 0.2s, box-shadow 0.2s;
}

#gradientAngleInput::-moz-range-thumb:hover {
    transform: scale(1.1);
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
}

#gradientAngleDisplay {
    font-size: 1rem;
    padding: 0.375rem 0.75rem;
    min-width: 60px;
    text-align: center;
}
</style>

