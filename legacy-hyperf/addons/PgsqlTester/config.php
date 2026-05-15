<?php

return [
    // ========================================
    // 基本配置
    // ========================================

    /**
     * 插件启用状态
     */
    'enabled' => false,

    /**
     * 插件显示名称
     */
    'display_name' => 'PostgreSQL 测试工具',

    // ========================================
    // 功能配置
    // ========================================

    /**
     * 启用详细日志记录
     * 记录每次测试的详细信息到数据库
     */
    'enable_detailed_logging' => true,

    /**
     * 启用性能监控
     * 收集和分析测试性能数据
     */
    'enable_performance_monitoring' => true,

    /**
     * 测试结果保留天数
     * 超过此天数的测试日志将被清理
     */
    'log_retention_days' => 30,

    /**
     * 启用实时统计
     * 在界面上显示实时的测试统计信息
     */
    'enable_realtime_stats' => true,

    // ========================================
    // 默认测试参数
    // ========================================

    /**
     * 默认性能测试迭代次数
     */
    'default_performance_iterations' => 100,

    /**
     * 默认性能测试查询
     */
    'default_performance_query' => 'SELECT 1',

    /**
     * 连接测试超时时间（秒）
     */
    'connection_timeout' => 10,

    /**
     * 查询执行超时时间（秒）
     */
    'query_timeout' => 30,

    // ========================================
    // 安全配置
    // ========================================

    /**
     * 允许的测试用户角色
     * 空数组表示允许所有用户
     */
    'allowed_roles' => [],

    /**
     * 启用IP白名单
     * 限制只有特定IP才能执行测试
     */
    'enable_ip_whitelist' => false,

    /**
     * IP白名单
     */
    'ip_whitelist' => [],

    /**
     * 启用速率限制
     * 限制每个用户每分钟的测试次数
     */
    'enable_rate_limiting' => true,

    /**
     * 每分钟最大测试次数
     */
    'max_tests_per_minute' => 60,

    // ========================================
    // 可配置项定义
    // ========================================

    'configs' => [
        0 => [
            'name' => 'display_name',
            'label' => '插件显示名称',
            'type' => 'text',
            'value' => 'PostgreSQL 测试工具',
            'help' => '插件在界面上显示的名称',
            'col' => 'col-md-6',
        ],
        1 => [
            'name' => 'enable_detailed_logging',
            'label' => '启用详细日志',
            'type' => 'switch',
            'value' => true,
            'help' => '是否记录每次测试的详细信息到数据库',
            'on_value' => 1,
            'off_value' => 0,
            'col' => 'col-md-6',
        ],
        2 => [
            'name' => 'enable_performance_monitoring',
            'label' => '启用性能监控',
            'type' => 'switch',
            'value' => true,
            'help' => '是否收集和分析测试性能数据',
            'on_value' => 1,
            'off_value' => 0,
            'col' => 'col-md-6',
        ],
        3 => [
            'name' => 'log_retention_days',
            'label' => '日志保留天数',
            'type' => 'number',
            'value' => 30,
            'help' => '测试日志的保留天数，超过此天数的日志将被清理',
            'min' => 7,
            'max' => 365,
            'col' => 'col-md-6',
        ],
        4 => [
            'name' => 'enable_realtime_stats',
            'label' => '启用实时统计',
            'type' => 'switch',
            'value' => true,
            'help' => '是否在界面上显示实时的测试统计信息',
            'on_value' => 1,
            'off_value' => 0,
            'col' => 'col-md-6',
        ],
        5 => [
            'name' => 'default_performance_iterations',
            'label' => '默认性能测试次数',
            'type' => 'number',
            'value' => 100,
            'help' => '性能测试的默认迭代次数',
            'min' => 10,
            'max' => 1000,
            'col' => 'col-md-6',
        ],
        6 => [
            'name' => 'connection_timeout',
            'label' => '连接超时时间',
            'type' => 'number',
            'value' => 10,
            'help' => '数据库连接的超时时间（秒）',
            'min' => 1,
            'max' => 60,
            'col' => 'col-md-6',
        ],
        7 => [
            'name' => 'query_timeout',
            'label' => '查询超时时间',
            'type' => 'number',
            'value' => 30,
            'help' => 'SQL查询的超时时间（秒）',
            'min' => 5,
            'max' => 300,
            'col' => 'col-md-6',
        ],
        8 => [
            'name' => 'enable_rate_limiting',
            'label' => '启用速率限制',
            'type' => 'switch',
            'value' => true,
            'help' => '是否限制每个用户的测试频率',
            'on_value' => 1,
            'off_value' => 0,
            'col' => 'col-md-6',
        ],
        9 => [
            'name' => 'max_tests_per_minute',
            'label' => '每分钟最大测试数',
            'type' => 'number',
            'value' => 60,
            'help' => '每个用户每分钟允许的最大测试次数',
            'min' => 10,
            'max' => 1000,
            'col' => 'col-md-6',
        ],
    ],
];
