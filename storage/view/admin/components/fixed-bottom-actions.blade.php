{{--
固定底部操作栏组件

参数：
- $infoText: 提示文本（默认："填写完成后点击保存按钮提交"）
- $infoIcon: 提示图标（默认："bi-info-circle"）
- $cancelUrl: 取消按钮的链接（必填）
- $cancelText: 取消按钮文本（默认："取消"）
- $submitText: 提交按钮文本（默认："保存"）
- $submitBtnId: 提交按钮ID（默认："submitBtn"）
- $formId: 要提交的表单ID（必填）
- $showInfo: 是否显示提示信息（默认：true）

使用示例：
@include('admin.components.fixed-bottom-actions', [
    'infoText' => '填写完成后点击保存按钮提交',
    'cancelUrl' => admin_route('system/menus'),
    'submitText' => '保存',
    'formId' => 'menuForm'
])

注意：
- 组件会自动添加占位区域，确保主内容不被固定按钮遮挡
- 在使用此组件的页面中，主内容会自动预留底部空间
--}}

@php
    $infoText = $infoText ?? '填写完成后点击保存按钮提交';
    $infoIcon = $infoIcon ?? 'bi-info-circle';
    $cancelUrl = $cancelUrl ?? '#';
    $cancelText = $cancelText ?? '取消';
    $submitText = $submitText ?? '保存';
    $submitBtnId = $submitBtnId ?? 'submitBtn';
    $formId = $formId ?? 'defaultForm';
    $showInfo = $showInfo ?? true;
@endphp

<!-- 占位区域：防止内容被固定按钮遮挡 -->
<div class="fixed-bottom-actions-spacer"></div>

<!-- 固定在底部的操作栏 -->
<div class="fixed-bottom-actions">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            @if($showInfo)
            <div class="text-muted small">
                <i class="bi {{ $infoIcon }} me-1"></i>
                {{ $infoText }}
            </div>
            @else
            <div></div>
            @endif
            <div class="d-flex gap-2">
                <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> {{ $cancelText }}
                </a>
                <button type="button" class="btn btn-primary" id="{{ $submitBtnId }}" onclick="document.getElementById('{{ $formId }}').dispatchEvent(new Event('submit'))">
                    <i class="bi bi-check-lg me-1"></i> {{ $submitText }}
                </button>
            </div>
        </div>
    </div>
</div>
