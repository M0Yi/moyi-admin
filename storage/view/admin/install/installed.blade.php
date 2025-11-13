<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统已初始化 - MoYi Admin</title>
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f7f7f7;
        }

        .container {
            max-width: 600px;
            width: 100%;
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            text-align: center;
        }

        .card-header {
            padding: 24px;
            color: #111827;
        }

        .card-header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
        }

        .card-body {
            padding: 24px;
        }

        .message {
            font-size: 14px;
            color: #374151;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .lock-file-info {
            background-color: #fafafa;
            border: 1px solid #e5e7eb;
            padding: 12px 16px;
            margin: 16px 0;
            text-align: left;
            border-radius: 6px;
        }

        .lock-file-info .label {
            font-weight: 600;
            color: #111827;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .lock-file-info .path {
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 13px;
            color: #4b5563;
            word-break: break-all;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            font-size: 14px;
            color: #fff;
            background: #2563eb;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            margin-top: 8px;
        }

        .card-footer {
            background-color: #fafafa;
            padding: 16px;
            color: #6b7280;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>系统已初始化</h1>
            </div>

            <div class="card-body">
                <p class="message">
                    {{ $message ?? '系统已经完成初始化，无需重复操作。' }}
                </p>

                @if(isset($lockFile))
                <div class="lock-file-info">
                    <div class="label">如需重新初始化，请删除以下锁文件：</div>
                    <div class="path">{{ $lockFile }}</div>
                </div>
                @endif

                <a href="/" class="btn">返回首页</a>
            </div>

            <div class="card-footer">
                Powered by MoYi Admin &copy; {{ date('Y') }}
            </div>
        </div>
    </div>
</body>
</html>

