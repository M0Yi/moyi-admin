@php
    $errorDetails = [];
    if (! empty($requestPath)) {
        $errorDetails[] = ['label' => '请求路径', 'value' => $requestPath ?? '未知'];
    }
    if (! empty($requestMethod)) {
        $errorDetails[] = ['label' => '请求方法', 'value' => $requestMethod];
    }
    if (! empty($errorDetails)) {
        $errorDetails[] = ['label' => '时间', 'value' => date('Y-m-d H:i:s')];
    } else {
        $errorDetails = null;
    }
@endphp

@include('errors.layout', [
    'errorCode' => '405',
    'errorTitle' => '请求方法不被允许',
    'errorIcon' => 'bi bi-shield-exclamation',
    'errorMessage' => '当前页面不支持您使用的请求方法。<br>请返回上一页或使用支持的方法重新尝试。',
    'gradient' => 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%)',
    'errorColor' => '#ff758c',
    'textColor' => 'white',
    'codeAnimation' => 'pulse 1.6s ease-in-out infinite',
    'iconAnimation' => 'swing 2s ease-in-out infinite',
    'errorDetails' => $errorDetails,
    'errorDetailsTitle' => '请求信息',
    'buttons' => [
        [
            'url' => '/',
            'class' => 'btn-home',
            'icon' => 'bi bi-house-door',
            'text' => '返回首页'
        ],
        [
            'url' => 'javascript:history.back()',
            'class' => 'btn-back',
            'icon' => 'bi bi-arrow-left',
            'text' => '返回上页'
        ]
    ]
])

