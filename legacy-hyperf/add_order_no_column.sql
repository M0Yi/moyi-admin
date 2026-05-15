-- 添加缺失的字段到捐赠表
ALTER TABLE jianhui_org_donations ADD COLUMN order_no VARCHAR(50) UNIQUE AFTER id;

-- 为已有的记录生成订单号
UPDATE jianhui_org_donations SET order_no = CONCAT('DON', DATE_FORMAT(created_at, '%Y%m%d'), LPAD(id, 6, '0')) WHERE order_no IS NULL;

-- 查看结果
SELECT id, order_no, donor_name, amount FROM jianhui_org_donations LIMIT 5;
