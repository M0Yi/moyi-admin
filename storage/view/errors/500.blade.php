@php
    $errorDetails = null;
    if (isset($errorMessage) && $errorMessage && (config('app.env') === 'local' || config('app.debug'))) {
        $errorDetails = '<strong>错误信息:</strong> ' . e($errorMessage);
        if (isset($errorFile)) {
            $errorDetails .= '<br><strong>文件:</strong> ' . e($errorFile);
        }
        if (isset($errorLine)) {
            $errorDetails .= '<br><strong>行号:</strong> ' . e($errorLine);
        }
        $errorDetails .= '<br><strong>时间:</strong> ' . date('Y-m-d H:i:s');
    }
@endphp

@include('errors.layout', [
    'errorCode' => '500',
    'errorTitle' => '服务器内部错误',
    'errorIcon' => 'bi bi-exclamation-triangle',
    'errorMessage' => '抱歉，服务器遇到了一个错误，无法完成您的请求。<br>我们的技术团队已经收到通知，正在处理这个问题。',
    'gradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
    'errorColor' => '#f5576c',
    'textColor' => 'white',
    'codeAnimation' => 'shake 2s infinite',
    'iconAnimation' => 'pulse 2s ease-in-out infinite',
    'errorDetails' => $errorDetails,
    'errorDetailsTitle' => '错误详情（开发模式）',
    'buttons' => [
        [
            'url' => '/',
            'class' => 'btn-home',
            'icon' => 'bi bi-house-door',
            'text' => '返回首页'
        ],
        [
            'url' => 'javascript:location.reload()',
            'class' => 'btn-reload',
            'icon' => 'bi bi-arrow-clockwise',
            'text' => '刷新页面'
        ]
    ]
])
