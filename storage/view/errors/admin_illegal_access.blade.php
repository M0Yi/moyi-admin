@php
    $securitySuggestions = [
        'title' => '安全建议',
        'items' => [
            ['label' => '提示', 'value' => '系统检测到您尝试访问受保护的后台区域，该操作已被自动拦截并记录。'],
            ['label' => '下一步', 'value' => '如需合法访问，请先输入正确的后台地址登录或联系系统管理员开通对应权限。'],
        ],
    ];
@endphp

@include('errors.layout', [
    'errorCode' => 'SECURITY',
    'errorTitle' => '非法访问警告',
    'errorIcon' => 'bi bi-exclamation-triangle',
    'errorMessage' => '系统检测到疑似非法的后台访问请求。<br>为保障平台安全，该操作已被阻止。',
    'gradient' => 'linear-gradient(135deg, #ff512f 0%, #dd2476 100%)',
    'errorColor' => '#ff512f',
    'textColor' => '#fff',
    'codeAnimation' => 'pulse 2.5s ease-in-out infinite',
    'iconAnimation' => 'shake 1.8s ease-in-out infinite',
    'errorDetailsTitle' => '访问记录',
    'customDetails' => $securitySuggestions,
    'buttons' => [
        [
            'url' => '/',
            'class' => 'btn-home',
            'icon' => 'bi bi-house-door',
            'text' => '返回首页',
        ],
    ],
])

