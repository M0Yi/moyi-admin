-- 建辉慈善文章分类表
-- MySQL

CREATE TABLE IF NOT EXISTS jianhui_org_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级分类ID，0表示顶级',
    name VARCHAR(100) NOT NULL COMMENT '分类名称',
    slug VARCHAR(100) NOT NULL COMMENT 'URL别名',
    type VARCHAR(20) NOT NULL DEFAULT 'article' COMMENT '类型：article文章/story故事/project项目',
    description TEXT COMMENT '分类描述',
    icon VARCHAR(100) COMMENT '图标',
    cover_image VARCHAR(500) COMMENT '封面图片',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '排序',
    is_single_article TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否为单页文章分类',
    linked_article_id BIGINT UNSIGNED COMMENT '关联的文章ID（单页分类时使用）',
    metadata JSON COMMENT '元数据',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_parent_id (parent_id),
    INDEX idx_type (type),
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active),
    INDEX idx_sort_order (sort_order),
    UNIQUE KEY uk_type_slug (type, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章分类表';

-- 插入默认分类数据（与导航菜单对应）
INSERT INTO jianhui_org_categories (name, slug, type, description, sort_order) VALUES
('新闻动态', 'news-jigou', 'article', '机构动态分类', 1),
('媒体报道', 'news-meiti', 'article', '媒体报道分类', 2),
('项目进展', 'news-jinzhan', 'article', '项目进展分类', 3)
ON DUPLICATE KEY UPDATE name=VALUES(name);
