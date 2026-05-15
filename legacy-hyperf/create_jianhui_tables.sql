-- JianhuiOrg 插件新表创建脚本
-- 执行方式: psql -U postgres -d moyi -f create_jianhui_tables.sql

-- ========================================
-- 创建自定义枚举类型
-- ========================================

-- 项目状态类型
DO $$ BEGIN
    CREATE TYPE project_status AS ENUM ('active', 'completed', 'suspended');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- 项目类型
DO $$ BEGIN
    CREATE TYPE project_type AS ENUM ('medical', 'health', 'emergency', 'undirected');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- 捐赠状态类型
DO $$ BEGIN
    CREATE TYPE donation_status AS ENUM ('pending', 'completed', 'failed');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- 捐赠方式类型
DO $$ BEGIN
    CREATE TYPE donation_type AS ENUM ('online', 'bank', 'wechat', 'alipay');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- 报告类型
DO $$ BEGIN
    CREATE TYPE report_type AS ENUM ('annual', 'quarterly', 'work', 'investment');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- ========================================
-- 1. 公益项目表
-- ========================================
CREATE TABLE IF NOT EXISTS jianhui_org_projects (
    id BIGSERIAL PRIMARY KEY,
    category_id BIGINT,
    original_id VARCHAR(50) UNIQUE,
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) UNIQUE,
    description TEXT,
    content TEXT,
    content_vector TSVECTOR,
    cover_image VARCHAR(500),
    project_type project_type NOT NULL,
    start_date DATE,
    end_date DATE,
    target_amount DECIMAL(15,2) DEFAULT 0,
    raised_amount DECIMAL(15,2) DEFAULT 0,
    beneficiary_count INTEGER DEFAULT 0,
    status project_status DEFAULT 'active',
    is_featured BOOLEAN DEFAULT false,
    is_pinned BOOLEAN DEFAULT false,
    sort_order INTEGER DEFAULT 0,
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT chk_projects_title_not_empty CHECK (length(trim(title)) > 0),
    CONSTRAINT chk_projects_amount_non_negative CHECK (target_amount >= 0 AND raised_amount >= 0),
    CONSTRAINT chk_projects_beneficiary_non_negative CHECK (beneficiary_count >= 0)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_jianhui_org_projects_category_id ON jianhui_org_projects(category_id);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_projects_project_type ON jianhui_org_projects(project_type);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_projects_status ON jianhui_org_projects(status);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_projects_featured ON jianhui_org_projects(is_featured) WHERE is_featured = true;
CREATE INDEX IF NOT EXISTS idx_jianhui_org_projects_pinned ON jianhui_org_projects(is_pinned) WHERE is_pinned = true;
CREATE INDEX IF NOT EXISTS idx_jianhui_org_projects_content_vector ON jianhui_org_projects USING gin(content_vector);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_projects_start_date ON jianhui_org_projects(start_date);

-- ========================================
-- 2. 项目进展记录表
-- ========================================
CREATE TABLE IF NOT EXISTS jianhui_org_project_progress (
    id BIGSERIAL PRIMARY KEY,
    project_id BIGINT NOT NULL,
    title VARCHAR(500) NOT NULL,
    content TEXT,
    images JSONB,
    progress_date DATE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT chk_project_progress_title_not_empty CHECK (length(trim(title)) > 0)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_jianhui_org_project_progress_project_id ON jianhui_org_project_progress(project_id);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_project_progress_date ON jianhui_org_project_progress(progress_date);

-- ========================================
-- 3. 捐赠记录表
-- ========================================
CREATE TABLE IF NOT EXISTS jianhui_org_donations (
    id BIGSERIAL PRIMARY KEY,
    donor_name VARCHAR(200),
    donor_email VARCHAR(200),
    donor_phone VARCHAR(50),
    donation_type donation_type NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    project_id BIGINT,
    is_anonymous BOOLEAN DEFAULT false,
    status donation_status DEFAULT 'pending',
    payment_date TIMESTAMPTZ,
    transaction_id VARCHAR(100) UNIQUE,
    message TEXT,
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT chk_donations_amount_positive CHECK (amount > 0)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_donor_name ON jianhui_org_donations(donor_name);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_project_id ON jianhui_org_donations(project_id);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_status ON jianhui_org_donations(status);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_payment_date ON jianhui_org_donations(payment_date);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_is_anonymous ON jianhui_org_donations(is_anonymous);

-- ========================================
-- 4. 捐赠披露表
-- ========================================
CREATE TABLE IF NOT EXISTS jianhui_org_donation_disclosures (
    id BIGSERIAL PRIMARY KEY,
    donor_name VARCHAR(200),
    amount DECIMAL(15,2) NOT NULL,
    donation_date DATE NOT NULL,
    project_id BIGINT,
    project_title VARCHAR(500),
    is_anonymous BOOLEAN DEFAULT false,
    disclosed_at TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT chk_donation_disclosures_amount_positive CHECK (amount > 0)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donation_disclosures_donation_date ON jianhui_org_donation_disclosures(donation_date);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donation_disclosures_project_id ON jianhui_org_donation_disclosures(project_id);

-- ========================================
-- 5. 年度报告表
-- ========================================
CREATE TABLE IF NOT EXISTS jianhui_org_annual_reports (
    id BIGSERIAL PRIMARY KEY,
    year INTEGER NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    cover_image VARCHAR(500),
    file_path VARCHAR(500),
    file_size BIGINT,
    report_date DATE,
    report_type report_type DEFAULT 'annual',
    quarter INTEGER,
    status article_status DEFAULT 'published',
    sort_order INTEGER DEFAULT 0,
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT chk_annual_reports_title_not_empty CHECK (length(trim(title)) > 0),
    CONSTRAINT chk_annual_reports_year_valid CHECK (year >= 2000 AND year <= 2100),
    CONSTRAINT chk_annual_reports_quarter_valid CHECK (quarter IS NULL OR (quarter >= 1 AND quarter <= 4))
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_jianhui_org_annual_reports_year ON jianhui_org_annual_reports(year);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_annual_reports_report_type ON jianhui_org_annual_reports(report_type);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_annual_reports_status ON jianhui_org_annual_reports(status);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_annual_reports_report_date ON jianhui_org_annual_reports(report_date);

-- ========================================
-- 创建触发器函数（如果不存在）
-- ========================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 创建中文全文搜索向量更新函数
CREATE OR REPLACE FUNCTION update_content_vector_zh()
RETURNS TRIGGER AS $$
BEGIN
    NEW.content_vector = to_tsvector('chinese',
        COALESCE(NEW.title, '') || ' ' ||
        COALESCE(NEW.description, '') || ' ' ||
        COALESCE(NEW.content, '')
    );
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ========================================
-- 为项目表创建触发器
-- ========================================
DROP TRIGGER IF EXISTS update_jianhui_org_projects_updated_at ON jianhui_org_projects;
CREATE TRIGGER update_jianhui_org_projects_updated_at
    BEFORE UPDATE ON jianhui_org_projects
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

DROP TRIGGER IF EXISTS update_jianhui_org_projects_content_vector ON jianhui_org_projects;
CREATE TRIGGER update_jianhui_org_projects_content_vector
    BEFORE INSERT OR UPDATE ON jianhui_org_projects
    FOR EACH ROW
    EXECUTE FUNCTION update_content_vector_zh();

-- ========================================
-- 为项目进展表创建触发器
-- ========================================
DROP TRIGGER IF EXISTS update_jianhui_org_project_progress_updated_at ON jianhui_org_project_progress;
CREATE TRIGGER update_jianhui_org_project_progress_updated_at
    BEFORE UPDATE ON jianhui_org_project_progress
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- ========================================
-- 为捐赠记录表创建触发器
-- ========================================
DROP TRIGGER IF EXISTS update_jianhui_org_donations_updated_at ON jianhui_org_donations;
CREATE TRIGGER update_jianhui_org_donations_updated_at
    BEFORE UPDATE ON jianhui_org_donations
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- ========================================
-- 为年度报告表创建触发器
-- ========================================
DROP TRIGGER IF EXISTS update_jianhui_org_annual_reports_updated_at ON jianhui_org_annual_reports;
CREATE TRIGGER update_jianhui_org_annual_reports_updated_at
    BEFORE UPDATE ON jianhui_org_annual_reports
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- ========================================
-- 插入示例数据
-- ========================================

-- 插入示例项目
INSERT INTO jianhui_org_projects (
    title, slug, description, project_type, target_amount, raised_amount,
    beneficiary_count, status, is_featured, cover_image
) VALUES
(
    '致敬困境中的行善者',
    'respect-heroes',
    '为那些身处困境但依然坚持行善的人们提供关怀和支持',
    'undirected',
    1000000.00,
    685000.00,
    1258,
    'active',
    true,
    '/storage/jianhui/covers/project1.jpg'
),
(
    '乡村医疗援助计划',
    'medical-aid-rural',
    '为偏远乡村地区提供医疗援助和健康关怀',
    'medical',
    500000.00,
    320000.00,
    450,
    'active',
    false,
    '/storage/jianhui/covers/project2.jpg'
),
(
    '应急救援项目',
    'emergency-relief',
    '为突发灾害提供紧急救援支持',
    'emergency',
    300000.00,
    300000.00,
    200,
    'completed',
    true,
    '/storage/jianhui/covers/project3.jpg'
)
ON CONFLICT (original_id) DO NOTHING;

-- 插入项目进展示例
INSERT INTO jianhui_org_project_progress (
    project_id, title, content, progress_date
) VALUES
(
    1,
    '第一季度项目进展',
    '本季度我们共走访了126名困境行善者，为他们提供了生活补助和节日慰问。',
    '2026-03-15'
),
(
    2,
    '乡村医疗援助启动',
    '项目正式启动，首批医疗物资已送达3个村庄。',
    '2026-02-20'
),
(
    3,
    '应急救援完成',
    '救援任务圆满完成，所有受灾群众得到妥善安置。',
    '2026-01-10'
);

-- 插入示例捐赠记录
INSERT INTO jianhui_org_donations (
    donor_name, donor_email, donation_type, amount, project_id, is_anonymous, status, payment_date
) VALUES
(
    '张三',
    'zhangsan@example.com',
    'online',
    1000.00,
    1,
    false,
    'completed',
    NOW()
),
(
    '李四',
    'lisi@example.com',
    'wechat',
    500.00,
    2,
    false,
    'completed',
    NOW()
),
(
    NULL,
    'anonymous@example.com',
    'alipay',
    200.00,
    1,
    true,
    'completed',
    NOW()
);

-- 插入捐赠披露
INSERT INTO jianhui_org_donation_disclosures (
    donor_name, amount, donation_date, project_id, is_anonymous
)
SELECT
    CASE WHEN is_anonymous THEN '爱心人士' ELSE donor_name END,
    amount,
    DATE(payment_date),
    project_id,
    is_anonymous
FROM jianhui_org_donations
WHERE status = 'completed';

-- 插入年度报告示例
INSERT INTO jianhui_org_annual_reports (
    year, title, description, report_type, report_date, status
) VALUES
(
    2024,
    '2024年度工作报告',
    '2024年度基金会工作总结和成果展示',
    'annual',
    '2025-03-30',
    'published'
),
(
    2024,
    '2024年第一季度报告',
    '第一季度工作进展',
    'quarterly',
    '2024-04-15',
    'published'
);

COMMENT ON TABLE jianhui_org_projects IS '建辉慈善官网公益项目表';
COMMENT ON TABLE jianhui_org_project_progress IS '建辉慈善官网项目进展记录表';
COMMENT ON TABLE jianhui_org_donations IS '建辉慈善官网捐赠记录表';
COMMENT ON TABLE jianhui_org_donation_disclosures IS '建辉慈善官网捐赠披露表（公开显示）';
COMMENT ON TABLE jianhui_org_annual_reports IS '建辉慈善官网年度报告表';

SELECT '✅ JianhuiOrg 插件数据库表创建完成！' AS result;
SELECT COUNT(*) AS total_tables FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE 'jianhui_org%';
