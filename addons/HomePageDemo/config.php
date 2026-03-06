<?php

return [
    'enabled' => false,  // 默认启用，用于演示
    'demo_title' => '插件首页演示',
    'demo_description' => '这是通过插件系统替换的首页内容',

    // ========================================
    // 首页替换配置
    // ========================================
    'replace_homepage' => [
        'enabled' => false,                    // 启用首页替换
        'controller' => 'HomePageController', // 控制器类名
        'action' => 'index',                  // 方法名
        'priority' => 10,                     // 优先级（数字越大优先级越高）
        'middleware' => [                     // 额外中间件（可选）
            // 可以添加插件特定的中间件
        ]
    ],

    // ========================================
    // 配置项定义（用于后台管理界面）
    // ========================================
    'configs' => [
        0 => [
            'name' => 'demo_title',
            'label' => '演示标题',
            'type' => 'text',
            'value' => '插件首页演示',
            'help' => '首页显示的标题',
            'col' => 'col-md-6',
        ],
        1 => [
            'name' => 'demo_description',
            'label' => '演示描述',
            'type' => 'textarea',
            'value' => '这是通过插件系统替换的首页内容',
            'help' => '首页显示的描述信息',
            'col' => 'col-md-6',
        ],
    ],
];
