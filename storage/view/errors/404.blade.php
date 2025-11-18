@php
    $errorDetails = null;
    if (isset($requestPath) && $requestPath) {
        $errorDetails = '<strong>请求路径:</strong> ' . e($requestPath ?? '未知');
        if (isset($requestMethod)) {
            $errorDetails .= '<br><strong>请求方法:</strong> ' . e($requestMethod);
        }
        $errorDetails .= '<br><strong>时间:</strong> ' . date('Y-m-d H:i:s');
    }
@endphp

@include('errors.layout', [
    'errorCode' => '404',
    'errorTitle' => '页面不存在',
    'errorIcon' => 'bi bi-compass',
    'errorMessage' => '抱歉，您访问的页面不存在或已被删除。<br>请检查网址是否正确，或返回首页。',
    'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    'errorColor' => '#667eea',
    'textColor' => 'white',
    'codeAnimation' => 'bounce 2s infinite',
    'iconAnimation' => 'float 3s ease-in-out infinite',
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
