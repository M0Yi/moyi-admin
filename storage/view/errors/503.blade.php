<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - 服务维护中</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --error-color: #a8edea;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
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
            color: rgba(0, 0, 0, 0.8);
            text-shadow: 0 5px 15px rgba(255, 255, 255, 0.5);
            margin: 0;
            line-height: 1;
        }

        .error-icon {
            font-size: 8rem;
            color: rgba(0, 0, 0, 0.7);
            margin-bottom: 1rem;
            animation: spin 4s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .error-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: rgba(0, 0, 0, 0.8);
            margin: 1.5rem 0 1rem;
            text-shadow: 0 2px 4px rgba(255, 255, 255, 0.5);
        }

        .error-message {
            font-size: 1.2rem;
            color: rgba(0, 0, 0, 0.7);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

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

        .btn-reload {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            cursor: pointer;
        }

        .btn-reload:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            color: white;
            background: rgba(0, 0, 0, 0.9);
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
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="bi bi-tools"></i>
        </div>
        <h1 class="error-code">503</h1>
        <h2 class="error-title">服务维护中</h2>
        <p class="error-message">
            系统正在进行维护升级，暂时无法访问。<br>
            给您带来不便，敬请谅解。
        </p>

        @if(isset($maintenanceMessage) && $maintenanceMessage)
        <div class="maintenance-info">
            <div class="maintenance-info-title">
                <i class="bi bi-info-circle"></i> 维护信息
            </div>
            <div>
                {{ $maintenanceMessage }}
            </div>
        </div>
        @else
        <div class="maintenance-info">
            <div class="maintenance-info-title">
                <i class="bi bi-clock-history"></i> 预计恢复时间
            </div>
            <div>
                我们正在努力尽快恢复服务，请稍后再试。
            </div>
        </div>
        @endif

        <div>
            <a href="javascript:location.reload()" class="btn-reload">
                <i class="bi bi-arrow-clockwise"></i>
                刷新页面
            </a>
        </div>
    </div>
</body>
</html>

