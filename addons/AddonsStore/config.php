<?php

/**
 * AddonsStore 插件配置文件
 *
 * 插件商店的配置项定义
 */

return [
    /**
     * 是否启用插件
     * true: 启用插件功能
     * false: 禁用插件功能
     */
    'enabled' => true,

    /**
     * 插件配置项
     */
    'configs' => [
        [
            'name' => 'store_name',
            'label' => '商店名称',
            'type' => 'text',
            'value' => '插件市场',
            'help' => '插件市场的显示名称',
            'col' => 'col-md-6',
        ],
        [
            'name' => 'enable_management',
            'label' => '启用插件管理',
            'type' => 'switch',
            'value' => true,
            'help' => '是否启用插件管理功能',
            'on_value' => 1,
            'off_value' => 0,
            'col' => 'col-md-6',
        ],
        [
            'name' => 'enable_logging',
            'label' => '启用操作日志',
            'type' => 'switch',
            'value' => true,
            'help' => '是否记录插件操作日志',
            'on_value' => 1,
            'off_value' => 0,
            'col' => 'col-md-6',
        ],
        [
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
