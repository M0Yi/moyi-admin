<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - 服务器错误</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --error-color: #f5576c;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            animation: shake 2s infinite;
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

        .error-icon {
            font-size: 8rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 1rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
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

        .btn-reload {
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
            cursor: pointer;
        }

        .btn-reload:hover {
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

            .btn-home, .btn-reload {
                display: block;
                margin: 0.5rem auto;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <h1 class="error-code">500</h1>
        <h2 class="error-title">服务器内部错误</h2>
        <p class="error-message">
            抱歉，服务器遇到了一个错误，无法完成您的请求。<br>
            我们的技术团队已经收到通知，正在处理这个问题。
        </p>

        @if(isset($errorMessage) && $errorMessage && (config('app.env') === 'local' || config('app.debug')))
        <div class="error-details">
            <div class="error-details-title">
                <i class="bi bi-bug"></i> 错误详情（开发模式）
            </div>
            <div class="error-details-text">
                <strong>错误信息:</strong> {{ $errorMessage }}<br>
                @if(isset($errorFile))
                <strong>文件:</strong> {{ $errorFile }}<br>
                @endif
                @if(isset($errorLine))
                <strong>行号:</strong> {{ $errorLine }}<br>
                @endif
                <strong>时间:</strong> {{ date('Y-m-d H:i:s') }}
            </div>
        </div>
        @endif

        <div>
            <a href="/" class="btn-home">
                <i class="bi bi-house-door"></i>
                返回首页
            </a>
            <a href="javascript:location.reload()" class="btn-reload">
                <i class="bi bi-arrow-clockwise"></i>
                刷新页面
            </a>
        </div>
    </div>
</body>
</html>

