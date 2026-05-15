-- 为披露表添加 updated_at 字段
ALTER TABLE jianhui_org_donation_disclosures ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- 查看表结构
DESCRIBE jianhui_org_donation_disclosures;
