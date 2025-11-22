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
    $cancelBtnId = $cancelBtnId ?? 'cancelBtn';
    $formId = $formId ?? 'defaultForm';
    $showInfo = $showInfo ?? true;
    $isEmbeddedPage = isset($isEmbedded) ? (bool) $isEmbedded : false;

    $embedCancelBehavior = $embedCancelBehavior ?? 'auto'; // auto | close | navigate | none
    $embedCancelPayload = $embedCancelPayload ?? ['action' => 'form-cancelled'];

    if (! in_array($embedCancelBehavior, ['auto', 'close', 'navigate', 'none'], true)) {
        $embedCancelBehavior = 'auto';
    }

    if ($embedCancelBehavior === 'auto') {
        $embedCancelBehavior = $isEmbeddedPage ? 'close' : 'navigate';
    }

    $embedCancelPayloadAttr = htmlspecialchars(
        json_encode($embedCancelPayload, JSON_UNESCAPED_UNICODE) ?: '{}',
        ENT_QUOTES,
        'UTF-8'
    );
@endphp

<!-- 占位区域：防止内容被固定按钮遮挡 -->
<div class="fixed-bottom-actions-spacer" data-embed="{{ $isEmbeddedPage ? '1' : '0' }}"></div>

<!-- 固定在底部的操作栏 -->
<div class="fixed-bottom-actions" data-embed="{{ $isEmbeddedPage ? '1' : '0' }}">
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
                <a
                    href="{{ $cancelUrl }}"
                    class="btn btn-outline-secondary"
                    id="{{ $cancelBtnId }}"
                    data-role="fixed-action-cancel"
                    data-embed-behavior="{{ $embedCancelBehavior }}"
                    data-embed-payload="{{ $embedCancelPayloadAttr }}"
                >
                    <i class="bi bi-x-lg me-1"></i> {{ $cancelText }}
                </a>
                <button type="button" class="btn btn-primary" id="{{ $submitBtnId }}" onclick="document.getElementById('{{ $formId }}').dispatchEvent(new Event('submit'))">
                    <i class="bi bi-check-lg me-1"></i> {{ $submitText }}
                </button>
            </div>
        </div>
    </div>
</div>

@once
    @push('admin_scripts')
        <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                var root = document.documentElement;
                if (!root || root.dataset.embed !== '1') {
                    return;
                }

                var bars = document.querySelectorAll('.fixed-bottom-actions[data-embed="1"]');
                bars.forEach(function (bar) {
                    var cancelBtn = bar.querySelector('[data-role="fixed-action-cancel"]');
                    if (!cancelBtn || cancelBtn.dataset.embedHandlerAttached === '1') {
                        return;
                    }

                    cancelBtn.dataset.embedHandlerAttached = '1';

                    cancelBtn.addEventListener('click', function (event) {
                        var behavior = cancelBtn.dataset.embedBehavior || 'close';

                        if (behavior !== 'close') {
                            return;
                        }

                        event.preventDefault();

                        var payload = {};
                        var payloadRaw = cancelBtn.dataset.embedPayload;

                        if (payloadRaw) {
                            try {
                                payload = JSON.parse(payloadRaw);
                            } catch (error) {
                                console.warn('[FixedBottomActions] Invalid embed payload:', error);
                            }
                        }

                        if (window.AdminIframeClient && typeof window.AdminIframeClient.close === 'function') {
                            window.AdminIframeClient.close(payload);
                            return;
                        }

                        window.history.back();
                    });
                });
            });
        })();
        </script>
    @endpush
@endonce
