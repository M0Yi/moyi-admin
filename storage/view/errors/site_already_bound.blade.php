@php
    $details = [];
    if (!empty($siteDomain ?? null)) {
        $details[] = ['label' => '域名', 'value' => $siteDomain];
    }
    if (!empty($siteName ?? null)) {
        $details[] = ['label' => '站点名称', 'value' => $siteName];
    }
    $details[] = ['label' => '时间', 'value' => date('Y-m-d H:i:s')];
@endphp

@include('errors.layout', [
    'errorCode' => 'SITE',
    'errorTitle' => '站点已绑定',
    'errorIcon' => 'bi bi-link-45deg',
    'errorMessage' => '当前域名已经绑定了站点，无法再次发起注册。如需创建新站点，请使用尚未绑定的域名访问本页面。',
    'gradient' => 'linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%)',
    'errorColor' => '#36d1dc',
    'textColor' => 'white',
    'codeAnimation' => 'pulse 2.5s infinite',
    'iconAnimation' => 'float 3s ease-in-out infinite',
    'errorDetails' => $details,
    'errorDetailsTitle' => '绑定信息',
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
            'text' => '返回上一页'
        ],
    ],
])

























