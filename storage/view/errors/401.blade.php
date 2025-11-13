<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>401 - 未授权</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            --error-color: #30cfd0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
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
            animation: rotate 3s linear infinite;
        }

        @keyframes rotate {
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

        .btn-login {
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

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            color: var(--error-color);
        }

        .btn-home {
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

        .btn-home:hover {
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

            .btn-login, .btn-home {
                display: block;
                margin: 0.5rem auto;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="bi bi-key"></i>
        </div>
        <h1 class="error-code">401</h1>
        <h2 class="error-title">未授权访问</h2>
        <p class="error-message">
            抱歉，您需要登录才能访问此页面。<br>
            请先登录您的账号。
        </p>

        <div>
            <a href="/login" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i>
                立即登录
            </a>
            <a href="/" class="btn-home">
                <i class="bi bi-house-door"></i>
                返回首页
            </a>
        </div>
    </div>
</body>
</html>

