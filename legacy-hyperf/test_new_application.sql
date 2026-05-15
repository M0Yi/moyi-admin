-- 插入新的测试数据（捐赠3）
INSERT INTO jianhui_org_donations (
    order_no, donor_name, amount, donation_type, is_anonymous, status, created_at, updated_at
) VALUES (
    'DON20260325003', '测试用户', 200.00, 'wechat', 0, 'completed', NOW(), NOW()
);

-- 尝试为同一捐赠创建第二个开票申请（应该失败）
INSERT INTO jianhui_org_invoice_applications (
    donation_id,
    application_no,
    invoice_type,
    invoice_title,
    tax_no,
    recipient_name,
    recipient_phone,
    recipient_email,
    invoice_amount,
    invoice_content,
    status
) VALUES (
    1,
    'INVAPP20260325003',
    'individual',
    NULL,
    NULL,
    '测试用户',
    '13800138000',
    'test@example.com',
    200.00,
    '捐赠',
    'pending'
);
