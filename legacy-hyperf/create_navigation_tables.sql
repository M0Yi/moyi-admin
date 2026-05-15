-- 建辉慈善导航菜单表
-- MySQL

CREATE TABLE IF NOT EXISTS jianhui_org_navigation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级菜单ID，0表示顶级',
    name VARCHAR(100) NOT NULL COMMENT '菜单名称',
    slug VARCHAR(100) COMMENT 'URL别名',
    type VARCHAR(20) NOT NULL DEFAULT 'link' COMMENT '类型：link链接/page页面/category分类/external外部',
    url VARCHAR(500) COMMENT '链接地址',
    icon VARCHAR(100) COMMENT '图标',
    target VARCHAR(20) DEFAULT '_self' COMMENT '打开方式：_self当前/_blank新窗口',
    position VARCHAR(20) DEFAULT 'header' COMMENT '位置：header顶部/footer底部/mobile移动',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '排序',
    metadata JSON COMMENT '元数据',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_parent_id (parent_id),
    INDEX idx_position (position),
    INDEX idx_is_active (is_active),
    INDEX idx_sort_order (sort_order),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='导航菜单表';

-- 插入默认导航数据
INSERT INTO jianhui_org_navigation (id, parent_id, name, slug, type, url, icon, position, is_active, sort_order) VALUES
-- 顶级菜单
(1, 0, '首页', 'home', 'page', '/', 'home', 'header', 1, 1),
(2, 0, '关于我们', 'about', 'page', '/about', 'info', 'header', 1, 2),
(3, 0, '新闻动态', 'news', 'category', NULL, 'news', 'header', 1, 3),
(4, 0, '慈善项目', 'projects', 'category', NULL, 'project', 'header', 1, 4),
(5, 0, '捐赠中心', 'donate', 'page', '/donate', 'heart', 'header', 1, 5),
(6, 0, '捐赠披露', 'disclosure', 'page', '/donation-disclosure', 'file', 'header', 1, 6),
(7, 0, '联系我们', 'contact', 'page', '/contact', 'phone', 'header', 1, 7),

-- 新闻动态二级菜单
(8, 3, '机构动态', 'news-jigou', 'page', '/category/news-jigou', NULL, 'header', 1, 1),
(9, 3, '媒体报道', 'news-meiti', 'page', '/category/news-meiti', NULL, 'header', 1, 2),
(10, 3, '项目进展', 'news-jinzhan', 'page', '/category/news-jinzhan', NULL, 'header', 1, 3),

-- 慈善项目二级菜单
(11, 4, '致敬困境中的行善者', 'project-xingShanZhe', 'page', '/projects/xingShanZhe', NULL, 'header', 1, 1),
(12, 4, '乡村医疗援助计划', 'project-yiLiao', 'page', '/projects/yiLiao', NULL, 'header', 1, 2),
(13, 4, '救灾扶贫专项行动', 'project-jiukFu', 'page', '/projects/jiukFu', NULL, 'header', 1, 3),

-- 关于我们二级菜单
(14, 2, '机构简介', 'about-intro', 'page', '/about/intro', NULL, 'header', 1, 1),
(15, 2, '理事会', 'about-council', 'page', '/about/council', NULL, 'header', 1, 2),
(16, 2, '组织架构', 'about-org', 'page', '/about/organization', NULL, 'header', 1, 3),
(17, 2, '项目审核', 'about-audit', 'page', '/about/audit', NULL, 'header', 1, 4),
(18, 2, '财务公开', 'about-finance', 'page', '/about/finance', NULL, 'header', 1, 5),
(19, 2, '联系我们', 'about-contact', 'page', '/contact', NULL, 'header', 1, 6)

ON DUPLICATE KEY UPDATE name=VALUES(name);
