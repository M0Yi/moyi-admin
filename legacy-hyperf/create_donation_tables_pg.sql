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
    transaction_id VARCHAR(100) UNIQUE,
    payment_date TIMESTAMP,
    metadata JSONB,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_status ON jianhui_org_donations(status);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_project_id ON jianhui_org_donations(project_id);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_created_at ON jianhui_org_donations(created_at);
CREATE INDEX IF NOT EXISTS idx_jianhui_org_donations_donation_type ON jianhui_org_donations(donation_type);

-- 插入一些测试数据
INSERT INTO jianhui_org_donations (
    order_no, donor_name, donor_phone, donor_email, amount, donation_type, project_id, is_anonymous, status, message, created_at, updated_at
) VALUES
    ('DON20260324001', '张三', '13800138000', 'zhangsan@example.com', 100.00, 'wechat', 1, FALSE, 'completed', '支持公益', NOW() - INTERVAL '1 day', NOW() - INTERVAL '1 day'),
    ('DON20260324002', '李四', '13900139000', 'lisi@example.com', 500.00, 'alipay', 2, FALSE, 'completed', '支持乡村医疗', NOW() - INTERVAL '2 days', NOW() - INTERVAL '2 days'),
    ('DON20260324003', NULL, NULL, NULL, 1000.00, 'bank', 3, TRUE, 'pending', NULL, NOW() - INTERVAL '3 days', NOW() - INTERVAL '3 days'),
    ('DON20260324004', '王五', '13700137000', 'wangwu@example.com', 200.00, 'wechat', 1, FALSE, 'completed', '致敬困境中的行善者', NOW() - INTERVAL '5 days', NOW() - INTERVAL '5 days'),
    ('DON20260324005', NULL, NULL, NULL, 50.00, 'online', 2, TRUE, 'completed', NULL, NOW() - INTERVAL '7 days', NOW() - INTERVAL '7 days')
ON CONFLICT DO NOTHING;

-- 验证数据
SELECT id, order_no, donor_name, amount, status FROM jianhui_org_donations ORDER BY id;
