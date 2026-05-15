-- 建辉慈善开票系统表
-- MySQL

-- 开票申请表
CREATE TABLE IF NOT EXISTS jianhui_org_invoice_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donation_id BIGINT UNSIGNED NOT NULL COMMENT '关联的捐赠记录ID',
    application_no VARCHAR(50) UNIQUE COMMENT '申请编号',
    invoice_type VARCHAR(20) NOT NULL DEFAULT 'individual' COMMENT '发票类型：individual个人/enterprise企业',
    invoice_title VARCHAR(200) COMMENT '发票抬头（企业开票必填）',
    tax_no VARCHAR(50) COMMENT '税号（企业开票必填）',

    -- 联系信息
    recipient_name VARCHAR(100) NOT NULL COMMENT '收票人姓名',
    recipient_phone VARCHAR(20) NOT NULL COMMENT '收票人电话',
    recipient_email VARCHAR(100) NOT NULL COMMENT '收票人邮箱（电子发票）',
    recipient_address VARCHAR(500) COMMENT '收票地址（纸质发票）',

    -- 发票内容
    invoice_amount DECIMAL(10, 2) NOT NULL COMMENT '开票金额',
    invoice_content VARCHAR(200) DEFAULT '捐赠' COMMENT '发票内容',

    -- 状态
    status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '申请状态：pending待审核/approved已开具/rejected已拒绝/cancelled已取消',
    reject_reason TEXT COMMENT '拒绝原因',

    -- 审核信息
    reviewed_by BIGINT UNSIGNED COMMENT '审核人ID',
    reviewed_at TIMESTAMP NULL COMMENT '审核时间',
    review_memo TEXT COMMENT '审核备注',

    -- 备注
    applicant_memo TEXT COMMENT '申请人备注',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_donation_id (donation_id),
    INDEX idx_status (status),
    INDEX idx_invoice_type (invoice_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (donation_id) REFERENCES jianhui_org_donations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='开票申请表';

-- 发票表
CREATE TABLE IF NOT EXISTS jianhui_org_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL COMMENT '关联的开票申请ID',
    invoice_no VARCHAR(50) UNIQUE COMMENT '发票号码',
    invoice_code VARCHAR(50) COMMENT '发票代码',

    -- 发票类型
    invoice_type VARCHAR(20) NOT NULL COMMENT '发票类型：electronic电子/paper纸质',
    invoice_form VARCHAR(20) DEFAULT 'ordinary' COMMENT '发票种类：ordinary普通发票/vat专用发票',

    -- 发票信息
    invoice_title VARCHAR(200) NOT NULL COMMENT '发票抬头',
    tax_no VARCHAR(50) COMMENT '税号',
    invoice_amount DECIMAL(10, 2) NOT NULL COMMENT '发票金额',
    invoice_content VARCHAR(200) DEFAULT '捐赠' COMMENT '发票内容',

    -- 开票日期
    invoice_date TIMESTAMP NOT NULL COMMENT '开票日期',

    -- 发票文件
    invoice_file_url VARCHAR(500) COMMENT '电子发票文件URL',
    invoice_file_path VARCHAR(500) COMMENT '电子发票文件路径',

    -- 快递信息（纸质发票）
    express_company VARCHAR(100) COMMENT '快递公司',
    express_no VARCHAR(50) COMMENT '快递单号',
    express_fee DECIMAL(10, 2) DEFAULT 0 COMMENT '快递费用',
    sent_at TIMESTAMP NULL COMMENT '寄出时间',

    -- 状态
    status VARCHAR(20) NOT NULL DEFAULT 'valid' COMMENT '发票状态：valid有效/voided已作废/invalid无效',

    -- 备注
    memo TEXT COMMENT '备注',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_application_id (application_id),
    INDEX idx_invoice_no (invoice_no),
    INDEX idx_invoice_type (invoice_type),
    INDEX idx_status (status),
    INDEX idx_invoice_date (invoice_date),
    FOREIGN KEY (application_id) REFERENCES jianhui_org_invoice_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票表';

-- 插入测试数据
INSERT INTO jianhui_org_invoice_applications (
    donation_id, application_no, invoice_type, invoice_title, tax_no,
    recipient_name, recipient_phone, recipient_email, invoice_amount, status
) VALUES
    (1, 'INVAPP20260325001', 'enterprise', '科技有限公司', '91110000000000000X',
     '张三', '13800138000', 'zhangsan@example.com', 100.00, 'pending'),
    (2, 'INVAPP20260325002', 'individual', NULL, NULL,
     '李四', '13900139000', 'lisi@example.com', 500.00, 'approved')
ON DUPLICATE KEY UPDATE application_no=application_id;
