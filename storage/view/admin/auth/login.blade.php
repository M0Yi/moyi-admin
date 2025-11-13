<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#667eea">
    <title>登录 - 管理后台</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #6366f1;
            --primary-hover: #575bf0;
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
                <div class="login-logo">A</div>
                <h1 class="login-title">管理后台</h1>
                <p class="login-subtitle">{{ site()?->name ?? '欢迎登录' }}</p>
            </div>

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

                <div class="form-check">
                    <input
                        type="checkbox"
                        id="remember"
                        name="remember"
                        class="form-check-input"
                    >
                    <label class="form-check-label" for="remember">记住我</label>
                </div>

                <button type="submit" class="btn btn-primary" id="loginBtn">
                    登录
                </button>
            </form>

            <div class="login-footer">
                <p>&copy; 2024 管理系统. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
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

            // 禁用按钮并显示加载状态
            btn.disabled = true;
            btn.innerHTML = '<span class="loading"></span>登录中...';

            try {
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        username: formData.get('username'),
                        password: formData.get('password'),
                        remember: formData.get('remember') ? 1 : 0,
                    }),
                });

                const data = await response.json();

                if (data.code === 200 || data.code === 0) {
                    // 登录成功
                    showAlert('登录成功，正在跳转...', 'success');
                    setTimeout(() => {
                        window.location.href = data.data.redirect;
                    }, 1000);
                } else {
                    // 登录失败
                    showAlert(data.message || '登录失败');
                    btn.disabled = false;
                    btn.innerHTML = '登录';
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
