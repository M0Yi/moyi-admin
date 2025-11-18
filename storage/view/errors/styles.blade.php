@php
    $gradient = $gradient ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
    $errorColor = $errorColor ?? '#667eea';
    $textColor = $textColor ?? 'white';
    $codeAnimation = $codeAnimation ?? '';
    $iconAnimation = $iconAnimation ?? '';
@endphp

<style>
    :root {
        --primary-gradient: {{ $gradient }};
        --error-color: {{ $errorColor }};
        --text-color: {{ $textColor }};
    }

    body {
        margin: 0;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        background: {{ $gradient }};
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .error-container {
        text-align: center;
        padding: 2rem;
        max-width: 600px;
        margin: 0 auto;
    }

    .error-code {
        font-size: 10rem;
        font-weight: 900;
        color: {{ $textColor === 'white' ? 'rgba(255, 255, 255, 0.95)' : 'rgba(0, 0, 0, 0.8)' }};
        text-shadow: 0 5px 15px {{ $textColor === 'white' ? 'rgba(0, 0, 0, 0.2)' : 'rgba(255, 255, 255, 0.5)' }};
        margin: 0;
        line-height: 1;
        @if($codeAnimation) animation: {{ $codeAnimation }}; @endif
    }

    .error-icon {
        font-size: 8rem;
        color: {{ $textColor === 'white' ? 'rgba(255, 255, 255, 0.9)' : 'rgba(0, 0, 0, 0.7)' }};
        margin-bottom: 1rem;
        @if($iconAnimation) animation: {{ $iconAnimation }}; @endif
    }

    .error-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: {{ $textColor }};
        margin: 1.5rem 0 1rem;
        text-shadow: 0 2px 4px {{ $textColor === 'white' ? 'rgba(0, 0, 0, 0.1)' : 'rgba(255, 255, 255, 0.5)' }};
    }

    .error-message {
        font-size: 1.2rem;
        color: {{ $textColor === 'white' ? 'rgba(255, 255, 255, 0.9)' : 'rgba(0, 0, 0, 0.7)' }};
        margin-bottom: 2rem;
        line-height: 1.6;
    }

    .error-details {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border-radius: 15px;
        padding: 1.5rem;
        margin: 2rem 0;
        color: {{ $textColor === 'white' ? 'rgba(255, 255, 255, 0.95)' : 'rgba(0, 0, 0, 0.8)' }};
        text-align: left;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .error-details-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: {{ $textColor }};
    }

    .error-details-text {
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        word-break: break-all;
    }

    /* 按钮样式 */
    .btn-home, .btn-login, .btn-back, .btn-reload {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem 2.5rem;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-home, .btn-login {
        background: white;
        color: var(--error-color);
    }

    .btn-home:hover, .btn-login:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        color: var(--error-color);
    }

    .btn-back, .btn-reload {
        background: rgba(255, 255, 255, 0.2);
        color: {{ $textColor }};
        padding: 0.8rem 2rem;
        margin-left: 1rem;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .btn-back:hover, .btn-reload:hover {
        background: rgba(255, 255, 255, 0.3);
        color: {{ $textColor }};
        transform: translateY(-3px);
    }

    /* 503 特殊按钮样式 */
    .btn-reload.dark {
        background: rgba(0, 0, 0, 0.8);
        color: white;
        border: none;
    }

    .btn-reload.dark:hover {
        background: rgba(0, 0, 0, 0.9);
        color: white;
    }

    /* 维护信息样式 */
    .maintenance-info {
        background: rgba(255, 255, 255, 0.6);
        backdrop-filter: blur(10px);
        border-radius: 15px;
        padding: 1.5rem;
        margin: 2rem 0;
        color: rgba(0, 0, 0, 0.8);
        border: 2px solid rgba(255, 255, 255, 0.8);
    }

    .maintenance-info-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: rgba(0, 0, 0, 0.9);
    }

    /* 动画 */
    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% {
            transform: translateY(0);
        }
        40% {
            transform: translateY(-20px);
        }
        60% {
            transform: translateY(-10px);
        }
    }

    @keyframes float {
        0%, 100% {
            transform: translateY(0px);
        }
        50% {
            transform: translateY(-20px);
        }
    }

    @keyframes swing {
        0%, 100% {
            transform: rotate(0deg);
        }
        25% {
            transform: rotate(-10deg);
        }
        75% {
            transform: rotate(10deg);
        }
    }

    @keyframes rotate {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }

    @keyframes shake {
        0%, 100% {
            transform: translateX(0);
        }
        10%, 30%, 50%, 70%, 90% {
            transform: translateX(-5px);
        }
        20%, 40%, 60%, 80% {
            transform: translateX(5px);
        }
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }

    /* 响应式设计 */
    @media (max-width: 768px) {
        .error-code {
            font-size: 6rem;
        }

        .error-icon {
            font-size: 5rem;
        }

        .error-title {
            font-size: 1.8rem;
        }

        .error-message {
            font-size: 1rem;
        }

        .btn-home, .btn-login, .btn-back, .btn-reload {
            display: block;
            margin: 0.5rem auto;
        }

        .btn-back, .btn-reload {
            margin-left: 0;
        }
    }
</style>

