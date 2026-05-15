-- 建辉慈善捐赠表
-- PostgreSQL

-- 捐赠记录表
CREATE TABLE IF NOT EXISTS jianhui_org_donations (
    id BIGSERIAL PRIMARY KEY,
    order_no VARCHAR(50) UNIQUE,
    donor_name VARCHAR(100),
    donor_phone VARCHAR(20),
    donor_email VARCHAR(100),
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    donation_type VARCHAR(20) NOT NULL DEFAULT 'online',
    project_id BIGINT,
    is_anonymous BOOLEAN NOT NULL DEFAULT FALSE,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    message TEXT,
    transaction_id VARCHAR(100),
    payment_date TIMESTAMP,
    metadata JSONB,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 捐赠披露表（公开显示）
CREATE TABLE IF NOT EXISTS jianhui_org_donation_disclosures (
    id BIGSERIAL PRIMARY KEY,
    donor_name VARCHAR(100),
    amount DECIMAL(10, 2) NOT NULL,
    donation_date DATE NOT NULL,
    project_id BIGINT,
    project_title VARCHAR(200),
    is_anonymous BOOLEAN NOT NULL DEFAULT FALSE,
    disclosed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_status ON jianhui_org_donations(status);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_project_id ON jianhui_org_donations(project_id);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_created_at ON jianhui_org_donations(created_at);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_donation_type ON jianhui_org_donations(donation_type);

CREATE INDEX IF NOT EXISTS idx_jianhui_org_donation_disclosures_date ON jianhui_org_donation_disclosures(donation_date);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donation_disclosures_project_id ON jianhui_org_donation_disclosures(project_id);

-- 添加注释
COMMENT ON TABLE jianhui_org_donations IS '捐赠记录表';
COMMENT ON TABLE jianhui_org_donation_disclosures IS '捐赠披露表（公开显示）';

COMMENT ON COLUMN jianhui_org_donations.order_no IS '订单号';
COMMENT ON COLUMN jianhui_org_donations.donor_name IS '捐赠人姓名';
COMMENT ON COLUMN jianhui_org_donations.donor_phone IS '捐赠人手机';
COMMENT ON COLUMN jianhui_org_donations.donor_email IS '捐赠人邮箱';
COMMENT ON COLUMN jianhui_org_donations.amount IS '捐赠金额';
COMMENT ON COLUMN jianhui_org_donations.donation_type IS '捐赠方式(online/wechat/alipay/bank)';
COMMENT ON COLUMN jianhui_org_donations.project_id IS '关联项目ID';
COMMENT ON COLUMN jianhui_org_donations.is_anonymous IS '是否匿名捐赠';
COMMENT ON COLUMN jianhui_org_donations.status IS '捐赠状态(pending/completed/failed)';
COMMENT ON COLUMN jianhui_org_donations.transaction_id IS '第三方交易ID';
COMMENT ON COLUMN jianhui_org_donations.payment_date IS '支付完成时间';

-- 插入一些测试数据
INSERT INTO jianhui_org_donations (
    order_no, donor_name, amount, donation_type, is_anonymous, status, created_at, updated_at
) VALUES
    ('DON20260324001', '张三', 100.00, 'wechat', FALSE, 'completed', NOW() - INTERVAL '1 day', NOW() - INTERVAL '1 day'),
    ('DON20260324002', '李四', 500.00, 'alipay', FALSE, 'completed', NOW() - INTERVAL '2 days', NOW() - INTERVAL '2 days'),
    ('DON20260324003', NULL, 1000.00, 'bank', TRUE, 'pending', NOW() - INTERVAL '3 days', NOW() - INTERVAL '3 days'),
    ('DON20260324004', '王五', 200.00, 'wechat', FALSE, 'completed', NOW() - INTERVAL '5 days', NOW() - INTERVAL '5 days'),
    ('DON20260324005', NULL, 50.00, 'online', TRUE, 'completed', NOW() - INTERVAL '7 days', NOW() - INTERVAL '7 days');

ON CONFLICT DO NOTHING;
