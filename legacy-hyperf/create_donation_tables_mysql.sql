-- 建辉慈善捐赠表
-- MySQL

-- 捐赠记录表
CREATE TABLE IF NOT EXISTS jianhui_org_donations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(50) UNIQUE,
    donor_name VARCHAR(100),
    donor_phone VARCHAR(20),
    donor_email VARCHAR(100),
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    donation_type VARCHAR(20) NOT NULL DEFAULT 'online',
    project_id BIGINT UNSIGNED,
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    message TEXT,
    transaction_id VARCHAR(100),
    payment_date DATETIME,
    metadata JSON,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_project_id (project_id),
    INDEX idx_created_at (created_at),
    INDEX idx_donation_type (donation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 捐赠披露表（公开显示）
CREATE TABLE IF NOT EXISTS jianhui_org_donation_disclosures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_name VARCHAR(100),
    amount DECIMAL(10, 2) NOT NULL,
    donation_date DATE NOT NULL,
    project_id BIGINT UNSIGNED,
    project_title VARCHAR(200),
    is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
    disclosed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_donation_date (donation_date),
    INDEX idx_project_id (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入一些测试数据
INSERT INTO jianhui_org_donations (
    order_no, donor_name, amount, donation_type, is_anonymous, status, created_at, updated_at
) VALUES
    ('DON20260324001', '张三', 100.00, 'wechat', 0, 'completed', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
    ('DON20260324002', '李四', 500.00, 'alipay', 0, 'completed', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
    ('DON20260324003', NULL, 1000.00, 'bank', 1, 'pending', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
    ('DON20260324004', '王五', 200.00, 'wechat', 0, 'completed', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
    ('DON20260324005', NULL, 50.00, 'online', 1, 'completed', DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY))
ON DUPLICATE KEY UPDATE order_no=order_no;
