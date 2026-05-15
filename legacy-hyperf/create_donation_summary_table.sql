-- 建辉慈善捐赠汇总表
-- MySQL

CREATE TABLE IF NOT EXISTS `jianhui_org_donation_summaries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `title` VARCHAR(200) NOT NULL COMMENT '汇总标题',
    `description` TEXT COMMENT '描述说明',
    `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '捐赠总金额',
    `total_donors` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '捐赠总人数',
    `summary_date` DATE NOT NULL COMMENT '汇总日期',
    `type` VARCHAR(20) NOT NULL DEFAULT 'custom' COMMENT '汇总类型：daily-每日, monthly-每月, yearly-每年, custom-自定义',
    `project_id` BIGINT UNSIGNED NULL COMMENT '关联项目ID（为空表示全局汇总）',
    `is_published` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否发布',
    `published_at` DATETIME NULL COMMENT '发布时间',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `metadata` JSON NULL COMMENT '扩展信息',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',

    PRIMARY KEY (`id`),
    KEY `idx_summary_date` (`summary_date`),
    KEY `idx_type` (`type`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_is_published` (`is_published`),
    KEY `idx_published_at` (`published_at`),
    KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='建辉慈善捐赠汇总表';

-- 插入示例数据
INSERT INTO `jianhui_org_donation_summaries`
    (`title`, `description`, `total_amount`, `total_donors`, `summary_date`, `type`, `is_published`, `sort`)
VALUES
    ('2024年第一季度捐赠汇总', '截至2024年3月31日的累计捐赠数据', 1526800.00, 3428, '2024-03-31', 'custom', 1, 1),
    ('2024年3月捐赠汇总', '2024年3月份的月度捐赠统计', 458600.00, 1056, '2024-03-31', 'monthly', 1, 2),
    ('爱心助学项目捐赠汇总', '爱心助学专项的捐赠统计', 680000.00, 1520, '2024-03-31', 'custom', 1, 3)
ON DUPLICATE KEY UPDATE `id` = `id`;
