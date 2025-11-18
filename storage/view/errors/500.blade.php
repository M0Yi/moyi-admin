@php
    $errorDetails = [];
    $shouldShowDetails = config('app.env') === 'local' || config('app.debug');
    if ($shouldShowDetails && !empty($errorMessage)) {
        $errorDetails[] = ['label' => '错误信息', 'value' => $errorMessage];
        if (!empty($errorFile)) {
            $errorDetails[] = ['label' => '文件', 'value' => $errorFile];
        }
        if (!empty($errorLine)) {
            $errorDetails[] = ['label' => '行号', 'value' => $errorLine];
        }
        $errorDetails[] = ['label' => '时间', 'value' => date('Y-m-d H:i:s')];
    }
    if (empty($errorDetails)) {
        $errorDetails = null;
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
