<!DOCTYPE html>
<html lang="zh-CN">
<head>
    @php
        $site = site();
        $siteName = $site?->name ?? '管理系统';
        $siteTitle = $site?->title ?? $siteName;
        $siteSlogan = $site?->slogan ?? '欢迎登录';
        $siteLogo = $site?->logo;
        $theme = $site?->getThemeConfig() ?? [];
        $primaryColor = $theme['primary_color'] ?? '#6366f1';
        $primaryHover = $theme['primary_hover'] ?? '#575bf0';
        $initialSource = $siteName !== '' ? $siteName : 'A';
        if (function_exists('mb_substr')) {
            $initialSource = mb_substr($initialSource, 0, 1);
        } else {
            $initialSource = substr($initialSource, 0, 1);
        }
        $siteInitial = function_exists('mb_strtoupper')
            ? mb_strtoupper($initialSource)
            : strtoupper($initialSource);
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ $primaryColor }}">
    <title>{{ $siteTitle }} - 登录</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: {{ $primaryColor }};
            --primary-hover: {{ $primaryHover }};
            --card-bg: #ffffff;
            --card-border: #e5e7eb;
            --text: #0f172a;
            --muted: #64748b;
            --input-bg: #f8fafc;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 16px;
            padding-top: max(16px, env(safe-area-inset-top));
            padding-right: max(16px, env(safe-area-inset-right));
            padding-bottom: max(16px, env(safe-area-inset-bottom));
            padding-left: max(16px, env(safe-area-inset-left));
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
            position: relative;
            z-index: 10;
            transform: translateY(0);
        }

        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 40px;
            position: relative;
        }

        .quick-login-box {
            border: 1px dashed rgba(99,102,241,0.4);
            border-radius: 12px;
            padding: 16px;
            background: #eef2ff;
            margin-bottom: 20px;
        }

        .quick-login-text {
            font-size: 14px;
            color: #4338ca;
            margin-bottom: 12px;
            font-weight: 500;
        }


        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo {
            width: 64px;
            height: 64px;
            background: var(--primary);
            border-radius: 16px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: 700;
            box-shadow: 0 6px 18px rgba(99, 102, 241, 0.35);
        }
        .login-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 14px;
        }

        .login-title {
            font-size: clamp(22px, 4vw, 28px);
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            font-size: clamp(13px, 2.8vw, 15px);
            color: var(--muted);
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-size: clamp(13px, 2.8vw, 14px);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: var(--input-bg);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
            transform: translateY(-2px);
        }

        .input-wrapper { position: relative; }

        .form-control.is-invalid {
            border-color: #dc3545;
        }

        .invalid-feedback {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
        }

        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-check-input {
            margin-right: 8px;
        }

        .form-check-label {
            font-size: 14px;
            color: #666;
        }

        .captcha-group {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .captcha-input {
            flex: 1;
        }

        .captcha-image-wrapper {
            position: relative;
            flex-shrink: 0;
        }

        .captcha-image {
            width: 120px;
            height: 40px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            object-fit: cover;
        }

        .captcha-image:hover {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }

        .captcha-image:active {
            transform: scale(0.98);
        }

        .captcha-refresh {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            background: var(--primary);
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .captcha-refresh:hover {
            background: var(--primary-hover);
            transform: rotate(90deg);
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: clamp(15px, 3.5vw, 16px);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .quick-login-btn {
            background: #4338ca;
            color: #fff;
        }

        .quick-login-btn:hover {
            background: #3730a3;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(99,102,241,0.35);
        }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #94a3b8;
        }

        .feature-tags { display: none; }

        .loading {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spinner 0.6s linear infinite;
            margin-right: 8px;
        }

        @keyframes spinner {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .login-card {
                padding: 32px;
                border-radius: 20px;
            }
            .login-header {
                margin-bottom: 24px;
            }
            .login-logo {
                width: 64px;
                height: 64px;
                font-size: 28px;
                border-radius: 18px;
            }
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 24px;
                border-radius: 16px;
            }
            .login-title {
                margin-bottom: 6px;
            }
            .form-group {
                margin-bottom: 16px;
            }
            .btn {
                padding: 14px;
            }
            .feature-tags {
                gap: 8px;
                margin-top: 16px;
            }
        }

        @media (max-height: 700px) {
            .login-container {
                padding-top: 8px;
                padding-bottom: 8px;
            }
            .login-card {
                padding: 28px;
            }
            .login-header {
                margin-bottom: 16px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }

        
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    @if ($siteLogo)
                        <img src="{{ $siteLogo }}" alt="{{ $siteTitle }}">
                    @else
                        {{ $siteInitial }}
                    @endif
                </div>
                <h1 class="login-title">{{ $siteTitle }}</h1>
                <p class="login-subtitle">{{ $siteSlogan }}</p>
            </div>

            <div id="demoAlert" class="alert alert-success" style="display: none;">
                体验账号：demo 密码：moyi123456
            </div>

            @if (!empty($isLoggedIn) && !empty($quickLoginUrl))
                <div class="quick-login-box">
                    <div class="quick-login-text">
                        已检测到您已登录{{ $loggedInUserName ? '：' . $loggedInUserName : '' }}
                    </div>
                    <button type="button" class="btn quick-login-btn" onclick="window.location.href='{{ $quickLoginUrl }}'">
                        快速进入后台
                    </button>
                </div>
            @endif

            <div id="alert" style="display: none;"></div>

            <form id="loginForm" onsubmit="return handleLogin(event)">
                <div class="form-group">
                    <label class="form-label" for="username">用户名</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        placeholder="请输入用户名"
                        inputmode="text"
                        autocomplete="username"
                        autocapitalize="none"
                        autocorrect="off"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">密码</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="请输入密码"
                        autocomplete="current-password"
                        required
                    >
                </div>

                {{-- 使用验证码组件 --}}
                @include('components.captcha', [
                    'name' => 'captcha',
                    'id' => 'captcha',
                    'label' => '验证码',
                    'placeholder' => '请输入验证码',
                    'required' => false,
                    'captchaUrl' => $captchaUrl ?? '/captcha',
                    'showFreeToken' => true,
                ])

                <button type="submit" class="btn btn-primary" id="loginBtn">
                    登录
                </button>
            </form>

            <div class="login-footer">
                <p>&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        // 页面加载时
        document.addEventListener('DOMContentLoaded', () => {
            const demoAlert = document.getElementById('demoAlert');
            if (demoAlert && window.location.pathname === '/admin/demo/login') {
                demoAlert.style.display = 'block';
            }
        });

        function showAlert(message, type = 'danger') {
            const alertDiv = document.getElementById('alert');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            alertDiv.style.display = 'block';

            // 3秒后自动隐藏
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 3000);
        }

        async function handleLogin(event) {
            event.preventDefault();

            const btn = document.getElementById('loginBtn');
            const form = document.getElementById('loginForm');
            const formData = new FormData(form);
            const captchaGroup = document.getElementById('captchaGroup');

            // 禁用按钮并显示加载状态
            btn.disabled = true;
            btn.innerHTML = '<span class="loading"></span>登录中...';

            try {
                // 构建请求数据
                const requestData = {
                    username: formData.get('username'),
                    password: formData.get('password'),
                };

                // 如果有免验证码令牌，带上令牌（使用组件提供的函数）
                const freeToken = window.getFreeToken_captcha ? window.getFreeToken_captcha() : null;
                if (freeToken) {
                    requestData.free_token = freeToken;
                }

                // 只有在显示验证码输入框时才发送验证码
                if (captchaGroup && captchaGroup.style.display !== 'none') {
                    requestData.captcha = formData.get('captcha') || '';
                }

                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData),
                });

                const data = await response.json();

                if (data.code === 200) {
                    window.location.replace(data.data.redirect);
                    return;
                } else {
                    // 登录失败
                    const errorMsg = data.msg || data.message || '登录失败';
                    showAlert(errorMsg);
                    btn.disabled = false;
                    btn.innerHTML = '登录';
                    
                    // 登录失败后，刷新验证码（刷新时会自动从接口获取最新的 free_token 状态）
                    // 如果之前不需要验证码（有 free_token），失败后 free_token 会被清除，需要验证码
                    if (window.refreshCaptcha_captcha) {
                        await window.refreshCaptcha_captcha();
                    }
                }
            } catch (error) {
                console.error('登录错误:', error);
                showAlert('网络错误，请稍后重试');
                btn.disabled = false;
                btn.innerHTML = '登录';
            }

            return false;
        }
    </script>
</body>
</html>
