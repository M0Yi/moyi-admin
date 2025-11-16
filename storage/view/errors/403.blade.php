<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - 禁止访问</title>
    @include('components.vendor.bootstrap-css')
    @include('components.vendor.bootstrap-icons')
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --error-color: #fa709a;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
            color: rgba(255, 255, 255, 0.95);
            text-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            margin: 0;
            line-height: 1;
        }

        .error-icon {
            font-size: 8rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 1rem;
            animation: swing 2s ease-in-out infinite;
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

        .error-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin: 1.5rem 0 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .error-message {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .error-details {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 2rem 0;
            color: rgba(255, 255, 255, 0.95);
            text-align: left;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .error-details-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: white;
        }

        .error-details-text {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            word-break: break-all;
        }

        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: white;
            color: var(--error-color);
            padding: 1rem 2.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-home:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            color: var(--error-color);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            margin-left: 1rem;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-3px);
        }

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

            .btn-home, .btn-back {
                display: block;
                margin: 0.5rem auto;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="bi bi-shield-lock"></i>
        </div>
        <h1 class="error-code">403</h1>
        <h2 class="error-title">禁止访问</h2>
        <p class="error-message">
            抱歉，您没有权限访问此页面。<br>
            如需访问，请联系管理员获取相应权限。
        </p>

        @if(isset($errorMessage) && $errorMessage)
        <div class="error-details">
            <div class="error-details-title">
                <i class="bi bi-info-circle"></i> 详细信息
            </div>
            <div class="error-details-text">
                <strong>原因:</strong> {{ $errorMessage }}<br>
                <strong>时间:</strong> {{ date('Y-m-d H:i:s') }}
            </div>
        </div>
        @endif

        <div>
            <a href="/" class="btn-home">
                <i class="bi bi-house-door"></i>
                返回首页
            </a>
            <a href="javascript:history.back()" class="btn-back">
                <i class="bi bi-arrow-left"></i>
                返回上页
            </a>
        </div>
    </div>
</body>
</html>

