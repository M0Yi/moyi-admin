@php
    $maintenanceInfo = [
        'title' => '维护信息',
        'items' => [],
    ];

    if (!empty($maintenanceMessage)) {
        $maintenanceInfo['items'][] = ['value' => $maintenanceMessage];
    } else {
        $maintenanceInfo['title'] = '预计恢复时间';
        $maintenanceInfo['items'][] = ['value' => '我们正在努力尽快恢复服务，请稍后再试。'];
    }
@endphp

@include('errors.layout', [
    'errorCode' => '503',
    'errorTitle' => '服务维护中',
    'errorIcon' => 'bi bi-tools',
    'errorMessage' => '系统正在进行维护升级，暂时无法访问。<br>给您带来不便，敬请谅解。',
    'gradient' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
    'errorColor' => '#a8edea',
    'textColor' => 'rgba(0, 0, 0, 0.8)',
    'iconAnimation' => 'spin 4s linear infinite',
    'customDetails' => $maintenanceInfo,
    'buttons' => [
        [
            'url' => 'javascript:location.reload()',
            'class' => 'btn-reload dark',
            'icon' => 'bi bi-arrow-clockwise',
            'text' => '刷新页面'
        ]
    ]
])
