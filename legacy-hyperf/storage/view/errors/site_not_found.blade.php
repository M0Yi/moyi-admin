@php
    $details = [];
    if (!empty($requestHost ?? null)) {
        $details[] = ['label' => '访问域名', 'value' => $requestHost];
    }
    if (!empty($requestPath ?? null)) {
        $details[] = ['label' => '请求路径', 'value' => $requestPath];
    }
    $details[] = ['label' => '时间', 'value' => date('Y-m-d H:i:s')];

    $buttons = [];

    if (!empty($allowPublicSiteCreation ?? false)) {
        $buttons[] = [
            'url' => $siteCreationUrl ?? '/site/register',
            'class' => 'btn-home',
            'icon' => 'bi bi-magic',
            'text' => '创建独立站点'
        ];
    }
@endphp

@include('errors.layout', [
    'errorCode' => 'SITE',
    'errorTitle' => '站点未配置',
    'errorIcon' => 'bi bi-globe2',
    'errorMessage' => '当前访问的域名尚未绑定到任何站点，暂时无法提供服务。<br>请联系管理员或完成站点安装后再尝试访问。',
    'gradient' => 'linear-gradient(135deg, #ed6ea0 0%, #ec8c69 100%)',
    'errorColor' => '#ed6ea0',
    'textColor' => 'white',
    'codeAnimation' => 'pulse 2.5s ease-in-out infinite',
    'iconAnimation' => 'float 3s ease-in-out infinite',
    'errorDetails' => $details,
    'errorDetailsTitle' => '请求信息',
    'buttons' => $buttons,
])

