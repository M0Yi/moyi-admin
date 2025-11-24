{{--
固定底部操作栏组件（增强版 - 深度集成 iframeshell）

参数（所有参数均为可选，提供智能默认值）：
- $formId: 要提交的表单ID（默认：自动查找页面中第一个 form 元素）
- $submitBtnId: 提交按钮ID（默认："submitBtn"）
- $cancelUrl: 取消按钮的链接（默认：自动检测，iframe 中无效）
- $cancelText: 取消按钮文本（默认："取消"）
- $submitText: 提交按钮文本（默认："保存"）
- $infoText: 提示文本（默认："填写完成后点击保存按钮提交"）
- $infoIcon: 提示图标（默认："bi-info-circle"）
- $showInfo: 是否显示提示信息（默认：true）
- $autoDetectEmbed: 是否自动检测 iframe 环境（默认：true）
- $onSuccess: 提交成功后的回调配置（默认：自动处理 iframe 关闭）

使用示例（最简）：
@include('admin.components.fixed-bottom-actions', [
    'formId' => 'menuForm'
])

使用示例（完整）：
@include('admin.components.fixed-bottom-actions', [
    'formId' => 'menuForm',
    'cancelUrl' => admin_route('system/menus'),
    'submitText' => '保存',
    'infoText' => '填写完成后点击保存按钮提交'
])

特性：
- ✅ 自动检测 iframe 环境（通过 AdminIframeClient 或 URL 参数）
- ✅ 智能处理取消按钮（iframe 中自动关闭，普通页面自动跳转）
- ✅ 深度集成 iframeshell（提交成功后自动发送 success 消息）
- ✅ 自动查找表单（如果未指定 formId）
- ✅ 支持通过 data 属性配置（data-fixed-action-*）
--}}

@php
    // 基础参数
    $formId = $formId ?? null;
    $submitBtnId = $submitBtnId ?? 'submitBtn';
    $cancelUrl = $cancelUrl ?? '#';
    $cancelText = $cancelText ?? '取消';
    $submitText = $submitText ?? '保存';
    $infoText = $infoText ?? '填写完成后点击保存按钮提交';
    $infoIcon = $infoIcon ?? 'bi-info-circle';
    $showInfo = $showInfo ?? true;
    $autoDetectEmbed = $autoDetectEmbed ?? true;
    
    // 提交成功后的配置
    $onSuccess = $onSuccess ?? [];
    $onSuccessClose = $onSuccess['close'] ?? true; // 是否关闭 iframe
    $onSuccessRefresh = $onSuccess['refreshParent'] ?? true; // 是否刷新父页面
    $onSuccessMessage = $onSuccess['message'] ?? null; // 成功消息
    $onSuccessRedirect = $onSuccess['redirect'] ?? null; // 重定向 URL（非 iframe 环境）
    
    // 生成唯一 ID（如果未指定 formId，将在 JS 中自动查找）
    $componentId = 'fixed-bottom-actions-' . uniqid();
@endphp

<!-- 占位区域：防止内容被固定按钮遮挡 -->
<div class="fixed-bottom-actions-spacer" data-component-id="{{ $componentId }}"></div>

<!-- 固定在底部的操作栏 -->
<div 
    class="fixed-bottom-actions" 
    id="{{ $componentId }}"
    data-form-id="{{ $formId ?? '' }}"
    data-submit-btn-id="{{ $submitBtnId }}"
    data-cancel-url="{{ $cancelUrl }}"
    data-cancel-text="{{ $cancelText }}"
    data-submit-text="{{ $submitText }}"
    data-info-text="{{ $infoText }}"
    data-info-icon="{{ $infoIcon }}"
    data-show-info="{{ $showInfo ? '1' : '0' }}"
    data-auto-detect-embed="{{ $autoDetectEmbed ? '1' : '0' }}"
    data-on-success-close="{{ $onSuccessClose ? '1' : '0' }}"
    data-on-success-refresh="{{ $onSuccessRefresh ? '1' : '0' }}"
    @if($onSuccessMessage)
    data-on-success-message="{{ $onSuccessMessage }}"
    @endif
    @if($onSuccessRedirect)
    data-on-success-redirect="{{ $onSuccessRedirect }}"
    @endif
>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            @if($showInfo)
            <div class="text-muted small" data-role="fixed-action-info">
                <i class="bi {{ $infoIcon }} me-1"></i>
                <span data-role="fixed-action-info-text">{{ $infoText }}</span>
            </div>
            @else
            <div></div>
            @endif
            <div class="d-flex gap-2">
                <a
                    href="{{ $cancelUrl }}"
                    class="btn btn-outline-secondary"
                    data-role="fixed-action-cancel"
                >
                    <i class="bi bi-x-lg me-1"></i> <span data-role="fixed-action-cancel-text">{{ $cancelText }}</span>
                </a>
                <button 
                    type="button" 
                    class="btn btn-primary" 
                    id="{{ $submitBtnId }}"
                    data-role="fixed-action-submit"
                >
                    <i class="bi bi-check-lg me-1"></i> <span data-role="fixed-action-submit-text">{{ $submitText }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

@once
    @push('admin_scripts')
        <script>
        (function () {
            'use strict';

            /**
             * 检测是否在 iframe 环境中
             */
            function isEmbedded() {
                // 方法1：检查 AdminIframeClient（最可靠）
                if (window.AdminIframeClient) {
                    return true;
                }
                
                // 方法2：检查 URL 参数
                try {
                    const params = new URLSearchParams(window.location.search);
                    if (params.get('_embed') === '1') {
                        return true;
                    }
                } catch (e) {
                    // ignore
                }
                
                // 方法3：检查 window.frameElement（如果不在跨域环境中）
                try {
                    if (window.frameElement !== null) {
                        return true;
                }
                } catch (e) {
                    // ignore cross-origin errors
                }
                
                return false;
            }

            /**
             * 初始化固定底部操作栏
             */
            function initFixedBottomActions() {
                const components = document.querySelectorAll('.fixed-bottom-actions[id]');
                
                components.forEach(function(component) {
                    if (component.dataset.initialized === '1') {
                        return; // 已初始化，跳过
                    }
                    
                    component.dataset.initialized = '1';
                    
                    const config = {
                        formId: component.dataset.formId || null,
                        submitBtnId: component.dataset.submitBtnId || 'submitBtn',
                        cancelUrl: component.dataset.cancelUrl || '#',
                        cancelText: component.dataset.cancelText || '取消',
                        submitText: component.dataset.submitText || '保存',
                        infoText: component.dataset.infoText || '填写完成后点击保存按钮提交',
                        infoIcon: component.dataset.infoIcon || 'bi-info-circle',
                        showInfo: component.dataset.showInfo !== '0',
                        autoDetectEmbed: component.dataset.autoDetectEmbed !== '0',
                        onSuccess: {
                            close: component.dataset.onSuccessClose !== '0',
                            refreshParent: component.dataset.onSuccessRefresh !== '0',
                            message: component.dataset.onSuccessMessage || null,
                            redirect: component.dataset.onSuccessRedirect || null
                        }
                    };
                    
                    // 自动检测 iframe 环境
                    const isEmbeddedPage = config.autoDetectEmbed ? isEmbedded() : false;
                    
                    console.log('[FixedBottomActions] ========== 初始化 FixedBottomActions ==========');
                    console.log('[FixedBottomActions] 组件ID:', component.id);
                    console.log('[FixedBottomActions] 配置:', config);
                    console.log('[FixedBottomActions] 是否在 iframe 环境:', isEmbeddedPage);
                    console.log('[FixedBottomActions] onSuccess 配置:', config.onSuccess);
                    console.log('[FixedBottomActions] onSuccess.close:', config.onSuccess.close);
                    console.log('[FixedBottomActions] onSuccess.refreshParent:', config.onSuccess.refreshParent);
                    
                    // 更新组件标记
                    component.setAttribute('data-embed', isEmbeddedPage ? '1' : '0');
                    const spacer = document.querySelector(`.fixed-bottom-actions-spacer[data-component-id="${component.id}"]`);
                    if (spacer) {
                        spacer.setAttribute('data-embed', isEmbeddedPage ? '1' : '0');
                    }
                    
                    // 使用 requestAnimationFrame 确保样式更新后再触发动画
                    requestAnimationFrame(function() {
                        // 设置初始化标记，触发显示动画
                        component.setAttribute('data-initialized', '1');
                    });
                    
                    // 查找表单
                    let form = null;
                    if (config.formId) {
                        form = document.getElementById(config.formId);
                        console.log('[FixedBottomActions] 通过 formId 查找表单:', config.formId, '结果:', form);
                    } else {
                        // 自动查找第一个表单
                        form = component.closest('form') || document.querySelector('form');
                        console.log('[FixedBottomActions] 自动查找表单，结果:', form);
                    }
                    
                    if (!form) {
                        console.warn('[FixedBottomActions] 未找到表单元素，提交功能可能无法正常工作');
                    } else {
                        console.log('[FixedBottomActions] 找到表单:', form.id || form);
                    }
                    
                    // 初始化取消按钮
                    const cancelBtn = component.querySelector('[data-role="fixed-action-cancel"]');
                    if (cancelBtn) {
                        cancelBtn.addEventListener('click', function(event) {
                            if (isEmbeddedPage) {
                                // iframe 环境：关闭 iframe
                        event.preventDefault();

                                if (window.AdminIframeClient && typeof window.AdminIframeClient.close === 'function') {
                                    window.AdminIframeClient.close({
                                        reason: 'user-cancelled',
                                        source: 'fixed-bottom-actions'
                                    });
                                } else {
                                    window.history.back();
                                }
                            } else {
                                // 普通页面：跳转到 cancelUrl（如果设置了且不是 #）
                                if (config.cancelUrl && config.cancelUrl !== '#') {
                                    // 允许默认行为（跳转）
                                } else {
                                    event.preventDefault();
                                    window.history.back();
                                }
                            }
                        });
                    }
                    
                    // 初始化提交按钮
                    const submitBtn = component.querySelector('[data-role="fixed-action-submit"]');
                    if (submitBtn) {
                        if (!form) {
                            console.warn('[FixedBottomActions] 提交按钮已找到，但表单未找到，formId:', config.formId);
                        }
                        
                        submitBtn.addEventListener('click', function(event) {
                            event.preventDefault();
                            
                            // 重新查找表单（防止动态加载的情况）
                            let targetForm = form;
                            if (!targetForm && config.formId) {
                                targetForm = document.getElementById(config.formId);
                            }
                            if (!targetForm) {
                                targetForm = component.closest('form') || document.querySelector('form');
                            }
                            
                            if (!targetForm) {
                                console.error('[FixedBottomActions] 无法找到表单元素，无法提交');
                                alert('表单未找到，无法提交');
                                return;
                            }
                            
                            // 先进行 HTML5 表单验证
                            if (!targetForm.checkValidity()) {
                                // 如果验证失败，显示浏览器默认的验证提示
                                targetForm.reportValidity();
                                return;
                            }
                            
                            // 触发表单提交事件
                            const submitEvent = new Event('submit', {
                                bubbles: true,
                                cancelable: true
                            });
                            
                            // 触发事件，让表单的 submit 事件处理器处理
                            // 注意：如果事件处理器调用了 preventDefault()，eventDispatched 会返回 false，这是正常的
                            const eventDispatched = targetForm.dispatchEvent(submitEvent);
                            
                            if (!eventDispatched) {
                                console.log('[FixedBottomActions] 表单提交事件被 preventDefault() 阻止（这是正常的，表示事件处理器已接管提交逻辑）');
                            } else {
                                console.log('[FixedBottomActions] 表单提交事件已触发，但未调用 preventDefault()');
                            }
                            
                            // 无论事件是否被阻止，都认为提交已开始（由事件处理器负责实际的提交逻辑）
                            console.log('[FixedBottomActions] 提交按钮点击处理完成，等待表单提交事件处理器执行');
                        });
                    } else {
                        console.warn('[FixedBottomActions] 未找到提交按钮');
                    }
                    
                    // 监听表单提交成功事件
                    if (form) {
                        console.log('[FixedBottomActions] 绑定 submit-success 事件监听器到表单:', form.id);
                        form.addEventListener('submit-success', function(event) {
                            console.log('[FixedBottomActions] ========== 收到 submit-success 事件 ==========');
                            console.log('[FixedBottomActions] 事件对象:', event);
                            console.log('[FixedBottomActions] 事件详情:', event.detail);
                            
                            const detail = event.detail || {};
                            console.log('[FixedBottomActions] 解析的事件详情:', detail);
                            
                            console.log('[FixedBottomActions] 是否在 iframe 环境:', isEmbeddedPage);
                            console.log('[FixedBottomActions] AdminIframeClient 是否存在:', !!window.AdminIframeClient);
                            
                            if (isEmbeddedPage && window.AdminIframeClient) {
                                console.log('[FixedBottomActions] 在 iframe 环境中，准备发送 success 消息');
                                
                                // iframe 环境：发送 success 消息
                                const payload = {
                                    message: detail.message || config.onSuccess.message || '操作成功',
                                    refreshParent: config.onSuccess.refreshParent,
                                    closeCurrent: config.onSuccess.close
                                };
                                
                                console.log('[FixedBottomActions] 初始 payload:', payload);
                                console.log('[FixedBottomActions] config.onSuccess:', config.onSuccess);
                                
                                // 传递 refreshUrl，让 TabManager 刷新正确的标签页
                                const refreshUrl = detail.redirect || config.onSuccess.redirect;
                                if (refreshUrl) {
                                    payload.refreshUrl = refreshUrl;
                                    console.log('[FixedBottomActions] 添加 refreshUrl:', refreshUrl);
                                }

                                if (detail.data) {
                                    payload.data = detail.data;
                                    console.log('[FixedBottomActions] 添加 data:', detail.data);
                                }
                                
                                console.log('[FixedBottomActions] 最终 payload:', payload);
                                console.log('[FixedBottomActions] 准备调用 AdminIframeClient.success()...');
                                
                                try {
                                    window.AdminIframeClient.success(payload);
                                    console.log('[FixedBottomActions] AdminIframeClient.success() 调用成功');
                                } catch (error) {
                                    console.error('[FixedBottomActions] AdminIframeClient.success() 调用失败:', error);
                                    console.error('[FixedBottomActions] 错误堆栈:', error.stack);
                                }
                                
                                console.log('[FixedBottomActions] ========== submit-success 事件处理完成 ==========');
                            } else {
                                console.log('[FixedBottomActions] 不在 iframe 环境或 AdminIframeClient 不存在，使用普通页面处理');
                                // 普通页面：显示消息并重定向
                                const message = detail.message || config.onSuccess.message || '操作成功';
                                
                                console.log('[FixedBottomActions] 成功消息:', message);
                                
                                // 显示成功消息
                                if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                                    window.Admin.utils.showToast('success', message);
                                    console.log('[FixedBottomActions] 使用 Admin.utils.showToast 显示消息');
                                } else if (window.showToast && typeof window.showToast === 'function') {
                                    window.showToast('success', message);
                                    console.log('[FixedBottomActions] 使用 window.showToast 显示消息');
                                } else {
                                    alert(message);
                                    console.log('[FixedBottomActions] 使用 alert 显示消息');
                                }
                                
                                // 重定向
                                const redirectUrl = detail.redirect || config.onSuccess.redirect;
                                if (redirectUrl) {
                                    console.log('[FixedBottomActions] 准备重定向到:', redirectUrl);
                                    setTimeout(function() {
                                        console.log('[FixedBottomActions] 执行重定向...');
                                        window.location.href = redirectUrl;
                                    }, 500);
                                } else {
                                    console.log('[FixedBottomActions] 没有重定向URL');
                                }
                            }
                        });
                        console.log('[FixedBottomActions] submit-success 事件监听器绑定完成');
                    } else {
                        console.warn('[FixedBottomActions] 表单未找到，无法绑定 submit-success 事件监听器');
                    }
                    
                    // 提供全局辅助函数，让表单处理器可以轻松触发成功事件
                    if (form && !window.FixedBottomActions) {
                        window.FixedBottomActions = {
                            /**
                             * 触发表单提交成功事件
                             * @param {string|HTMLElement} formOrId - 表单元素或表单ID
                             * @param {object} options - 成功选项
                             * @param {string} options.message - 成功消息
                             * @param {string} options.redirect - 重定向URL（非iframe环境）
                             * @param {object} options.data - 附加数据
                             */
                            triggerSuccess: function(formOrId, options) {
                                const targetForm = typeof formOrId === 'string' 
                                    ? document.getElementById(formOrId) 
                                    : formOrId;
                                
                                if (!targetForm) {
                                    console.warn('[FixedBottomActions] 未找到表单元素');
                            return;
                        }

                                const event = new CustomEvent('submit-success', {
                                    bubbles: true,
                                    cancelable: true,
                                    detail: options || {}
                                });
                                
                                targetForm.dispatchEvent(event);
                            }
                        };
                    }
                    });
            }

            // DOM 加载完成后初始化
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initFixedBottomActions);
            } else {
                initFixedBottomActions();
            }
        })();
        </script>
    @endpush
@endonce
