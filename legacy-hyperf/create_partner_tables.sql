-- 创建合作伙伴表
CREATE TABLE IF NOT EXISTS jianhui_org_partners (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT '合作伙伴名称',
    logo_url VARCHAR(500) COMMENT 'logo图片URL',
    website_url VARCHAR(500) COMMENT '网站链接',
    description TEXT COMMENT '合作伙伴描述',
    category_id INTEGER COMMENT '分类ID',
    sort_order INTEGER DEFAULT 0 COMMENT '排序',
    is_active BOOLEAN DEFAULT true COMMENT '是否启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 创建合作伙伴分类表
CREATE TABLE IF NOT EXISTS jianhui_org_partner_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT '分类名称',
    slug VARCHAR(255) UNIQUE NOT NULL COMMENT 'URL别名',
    description TEXT COMMENT '分类描述',
    sort_order INTEGER DEFAULT 0 COMMENT '排序',
    is_active BOOLEAN DEFAULT true COMMENT '是否启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_partners_category_id ON jianhui_org_partners(category_id);
CREATE INDEX IF NOT EXISTS idx_partners_is_active ON jianhui_org_partners(is_active);
CREATE INDEX IF NOT EXISTS idx_partners_sort_order ON jianhui_org_partners(sort_order);
CREATE INDEX IF NOT EXISTS idx_partner_categories_slug ON jianhui_org_partner_categories(slug);
CREATE INDEX IF NOT EXISTS idx_partner_categories_is_active ON jianhui_org_partner_categories(is_active);
CREATE INDEX IF NOT EXISTS idx_partner_categories_sort_order ON jianhui_org_partner_categories(sort_order);

-- 插入默认分类
INSERT INTO jianhui_org_partner_categories (name, slug, description, sort_order) VALUES
('战略合作伙伴', 'strategic', '与我们深度合作的战略伙伴', 1),
('企业合作伙伴', 'enterprise', '支持公益事业的企业伙伴', 2),
('媒体合作伙伴', 'media', '传播公益理念的媒体伙伴', 3),
('公益组织', 'ngo', '共同推动公益发展的组织', 4),
('基金会伙伴', 'foundation', '其他慈善基金会', 5)
ON CONFLICT (slug) DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    sort_order = EXCLUDED.sort_order,
    updated_at = CURRENT_TIMESTAMP;

-- 示例合作伙伴数据（需要根据实际情况调整）
INSERT INTO jianhui_org_partners (name, logo_url, website_url, description, category_id, sort_order) VALUES
-- 战略合作伙伴
('腾讯公益', 'https://www.tencent.com/logo.png', 'https://gongyi.qq.com', '腾讯公益慈善基金会', 1, 1),
('阿里巴巴公益', 'https://www.aliyun.com/logo.png', 'https://gongyi.aliyun.com', '阿里巴巴公益', 1, 2),
-- 企业合作伙伴
('华为', 'https://www.huawei.com/logo.png', 'https://www.huawei.com', '支持公益事业的科技企业', 2, 1),
('小米', 'https://www.mi.com/logo.png', 'https://www.mi.com', '科技向善', 2, 2),
-- 媒体合作伙伴
('央视网', 'https://www.cctv.com/logo.png', 'https://www.cctv.com', '国家级媒体平台', 3, 1),
('人民日报', 'https://www.people.com.cn/logo.png', 'https://www.people.com.cn', '权威媒体', 3, 2),
-- 公益组织
('中国扶贫基金会', 'https://www.fupin.org.cn/logo.png', 'https://www.fupin.org.cn', '公益组织', 4, 1),
('中华社会救助基金会', 'https://www.csaf.org.cn/logo.png', 'https://www.csaf.org.cn', '公益组织', 4, 2)
ON CONFLICT (name, category_id) DO NOTHING;
