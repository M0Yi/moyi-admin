{{--
验证码组件

参数:
- $name: 输入框 name 属性（默认: captcha）
- $id: 输入框 id 属性（默认: captcha）
- $label: 标签文本（默认: 验证码）
- $placeholder: 占位符（默认: 请输入验证码）
- $required: 是否必填（默认: false）
- $captchaUrl: 获取验证码的 URL（默认: /captcha，通用接口）
- $showFreeToken: 是否支持免验证码令牌（默认: false，用于登录场景）

注意：
- 验证码类型由后端随机选择（字符验证码或数学验证码）
- 所有验证码统一以图片形式返回和显示
--}}
@php
    $name = $name ?? 'captcha';
    $id = $id ?? 'captcha';
    $label = $label ?? '验证码';
    $placeholder = $placeholder ?? '请输入验证码';
    $required = $required ?? false;
    // 如果没有传递 captchaUrl，使用默认的通用验证码接口路径
    // 注意：验证码接口是通用接口，不在管理后台路径下，前端登录也可以使用
    $captchaUrl = $captchaUrl ?? '/captcha';
    $showFreeToken = $showFreeToken ?? false;
    $groupId = $id . 'Group';
    $imageId = $id . 'Image';
@endphp

<div class="form-group" id="{{ $groupId }}" style="display: none;">
    <label class="form-label" for="{{ $id }}">{{ $label }}</label>
    <div class="captcha-group">
        <input
            type="text"
            id="{{ $id }}"
            name="{{ $name }}"
            class="form-control captcha-input"
            placeholder="{{ $placeholder }}"
            maxlength="10"
            autocomplete="off"
            @if($required) required @endif
        >
        {{-- 验证码图片（统一格式，无论是字符验证码还是数学验证码都显示为图片） --}}
        <div class="captcha-image-wrapper" id="{{ $imageId }}Wrapper" style="display: none;">
            <img
                id="{{ $imageId }}"
                class="captcha-image"
                src=""
                alt="验证码"
                onclick="window.refreshCaptcha_{{ $id }} && window.refreshCaptcha_{{ $id }}()"
                title="点击刷新验证码"
            >
            <div
                class="captcha-refresh"
                onclick="window.refreshCaptcha_{{ $id }} && window.refreshCaptcha_{{ $id }}()"
                title="刷新验证码"
            >
                ↻
            </div>
        </div>
    </div>
</div>

@if($showFreeToken)
<script>
(function() {
    // 初始化验证码组件
    const captchaId = '{{ $id }}';
    const groupId = '{{ $groupId }}';
    const imageId = '{{ $imageId }}';
    const imageWrapperId = '{{ $imageId }}Wrapper';
    const captchaUrl = '{{ $captchaUrl }}';
    
    // 全局变量：保存免验证码令牌
    let freeToken = null;
    
    // 页面加载时获取验证码（同时获取 free_token）
    document.addEventListener('DOMContentLoaded', () => {
        loadCaptcha();
    });
    
    /**
     * 加载验证码（从验证码接口获取验证码和 free_token）
     * 
     * 逻辑说明：
     * - 验证码类型由后端随机选择（字符验证码或数学验证码）
     * - 所有验证码统一以图片形式返回和显示
     * - 如果 free_token 存在（不为 null），表示不需要验证码，隐藏验证码输入框
     * - 如果 free_token 为 null，表示需要验证码，显示验证码输入框
     */
    async function loadCaptcha() {
        const captchaImage = document.getElementById(imageId);
        const imageWrapper = document.getElementById(imageWrapperId);
        const captchaInput = document.getElementById(captchaId);
        
        try {
            // 请求验证码（不传递 type 参数，由后端随机选择）
            const response = await fetch(captchaUrl);
            const data = await response.json();
            
            // 调试信息
            console.log('验证码接口返回:', {
                code: data.code,
                type: data.data?.type,
                has_free_token: !!data.data?.free_token,
                has_image: !!data.data?.image
            });
            
            if (data.code === 200 && data.data) {
                // 保存免验证码令牌（可能为 null）
                freeToken = data.data.free_token || null;
                
                // 统一显示图片（无论是字符验证码还是数学验证码）
                if (imageWrapper) {
                    imageWrapper.style.display = 'flex';
                }
                if (captchaImage && data.data.image) {
                    captchaImage.src = data.data.image;
                }
                
                // 根据 free_token 是否存在决定是否显示验证码输入框
                if (freeToken === null) {
                    // free_token 为 null，需要验证码，显示验证码输入框
                    console.log('需要验证码（free_token 为 null），显示验证码输入框');
                    showCaptchaGroup();
                } else {
                    // free_token 存在，不需要验证码，隐藏验证码输入框
                    console.log('不需要验证码（free_token 存在），隐藏验证码输入框');
                    hideCaptchaGroup();
                }
            } else {
                // 出错时默认需要验证码
                console.error('获取验证码失败:', data.msg || data.message || '未知错误');
                freeToken = null;
                showCaptchaGroup();
            }
        } catch (error) {
            console.error('加载验证码错误:', error);
            // 出错时默认需要验证码
            freeToken = null;
            showCaptchaGroup();
        }
    }
    
    /**
     * 显示验证码输入框
     */
    function showCaptchaGroup() {
        const captchaGroup = document.getElementById(groupId);
        const captchaInput = document.getElementById(captchaId);
        
        if (captchaGroup) {
            captchaGroup.style.display = 'block';
            // 设置为必填
            if (captchaInput) {
                captchaInput.required = true;
            }
        }
    }
    
    /**
     * 隐藏验证码输入框
     */
    function hideCaptchaGroup() {
        const captchaGroup = document.getElementById(groupId);
        const captchaInput = document.getElementById(captchaId);
        
        if (captchaGroup) {
            captchaGroup.style.display = 'none';
            // 取消必填
            if (captchaInput) {
                captchaInput.required = false;
                captchaInput.value = '';
            }
        }
    }
    
    /**
     * 刷新验证码（重新从接口获取）
     */
    async function refreshCaptcha() {
        await loadCaptcha();
    }
    
    // 暴露全局函数供外部调用
    window['refreshCaptcha_' + captchaId] = refreshCaptcha;
    window['showCaptchaGroup_' + captchaId] = showCaptchaGroup;
    window['hideCaptchaGroup_' + captchaId] = hideCaptchaGroup;
    window['getFreeToken_' + captchaId] = () => freeToken;
})();
</script>
@else
<script>
// 简单的验证码刷新函数（不需要检查是否需要验证码的场景）
(function() {
    const captchaId = '{{ $id }}';
    const imageId = '{{ $imageId }}';
    const imageWrapperId = '{{ $imageId }}Wrapper';
    const captchaUrl = '{{ $captchaUrl }}';
    
    /**
     * 刷新验证码
     * 
     * 注意：验证码类型由后端随机选择，统一以图片形式返回
     */
    async function refreshCaptcha() {
        const captchaImage = document.getElementById(imageId);
        const imageWrapper = document.getElementById(imageWrapperId);
        const captchaInput = document.getElementById(captchaId);
        
        try {
            // 请求验证码（不传递 type 参数，由后端随机选择）
            const response = await fetch(captchaUrl);
            const data = await response.json();
            
            if (data.code === 200 && data.data) {
                // 统一显示图片（无论是字符验证码还是数学验证码）
                if (imageWrapper) {
                    imageWrapper.style.display = 'flex';
                }
                if (captchaImage && data.data.image) {
                    captchaImage.src = data.data.image;
                }
                
                // 清空输入框
                if (captchaInput) {
                    captchaInput.value = '';
                }
            } else {
                console.error('获取验证码失败:', data.msg || data.message || '未知错误');
            }
        } catch (error) {
            console.error('获取验证码错误:', error);
        }
    }
    
    // 暴露全局函数供外部调用
    window['refreshCaptcha_' + captchaId] = refreshCaptcha;
    
    // 页面加载时显示验证码
    document.addEventListener('DOMContentLoaded', () => {
        const captchaGroup = document.getElementById('{{ $groupId }}');
        if (captchaGroup) {
            captchaGroup.style.display = 'block';
            refreshCaptcha();
        }
    });
})();
</script>
@endif

