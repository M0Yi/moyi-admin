@include('errors.layout', [
    'errorCode' => '401',
    'errorTitle' => '未授权',
    'errorIcon' => 'bi bi-key',
    'errorMessage' => '抱歉，您需要登录才能访问此页面。<br>请先登录您的账号。',
    'gradient' => 'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
    'errorColor' => '#30cfd0',
    'textColor' => 'white',
    'iconAnimation' => 'rotate 3s linear infinite',
    'buttons' => [
        [
            'url' => '/login',
            'class' => 'btn-login',
            'icon' => 'bi bi-box-arrow-in-right',
            'text' => '立即登录'
        ],
        [
            'url' => '/',
            'class' => 'btn-home',
            'icon' => 'bi bi-house-door',
            'text' => '返回首页'
        ]
    ]
])
