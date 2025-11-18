@php
    $errorDetails = null;
    if (isset($errorMessage) && $errorMessage) {
        $errorDetails = '<strong>原因:</strong> ' . e($errorMessage) . '<br><strong>时间:</strong> ' . date('Y-m-d H:i:s');
    }
@endphp

@include('errors.layout', [
    'errorCode' => '403',
    'errorTitle' => '禁止访问',
    'errorIcon' => 'bi bi-shield-lock',
    'errorMessage' => '抱歉，您没有权限访问此页面。<br>如需访问，请联系管理员获取相应权限。',
    'gradient' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
    'errorColor' => '#fa709a',
    'textColor' => 'white',
    'iconAnimation' => 'swing 2s ease-in-out infinite',
    'errorDetails' => $errorDetails,
    'errorDetailsTitle' => '详细信息',
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
