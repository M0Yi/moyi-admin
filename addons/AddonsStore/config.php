<?php

return [
    'enabled' => true,
    'store_name' => '插件市场2222',
    'enable_management' => false,
    'enable_logging' => false,
    'items_per_page' => 15,
    'configs' => [
        0 => [
            'name' => 'store_name',
            'label' => '商店名称',
            'type' => 'text',
            'value' => '插件市场2222',
            'help' => '插件市场的显示名称',
            'col' => 'col-md-6',
        ],
        1 => [
            'name' => 'enable_management',
            'label' => '启用插件管理',
            'type' => 'switch',
            'value' => false,
            'help' => '是否启用插件管理功能',
            'on_value' => 1,
            'off_value' => 0,
            'col' => 'col-md-6',
        ],
        2 => [
            'name' => 'enable_logging',
            'label' => '启用操作日志',
            'type' => 'switch',
            'value' => false,
            'help' => '是否记录插件操作日志',
            'on_value' => 1,
            'off_value' => 0,
            'col' => 'col-md-6',
        ],
        3 => [
            'name' => 'items_per_page',
            'label' => '每页显示数量',
            'type' => 'number',
            'value' => 15,
            'help' => '列表页面每页显示的记录数量',
            'min' => 5,
            'max' => 100,
            'col' => 'col-md-6',
        ],
    ],
];
