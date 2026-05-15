-- 清理 jianhui_org_donations 表中的空字符串，转换为 NULL
UPDATE jianhui_org_donations SET transaction_id = NULL WHERE transaction_id = '';
UPDATE jianhui_org_donations SET donor_phone = NULL WHERE donor_phone = '';
UPDATE jianhui_org_donations SET donor_email = NULL WHERE donor_email = '';
UPDATE jianhui_org_donations SET message = NULL WHERE message = '';

-- 查看结果
SELECT id, order_no,
  COALESCE(transaction_id, 'NULL') as transaction_id,
  COALESCE(donor_phone, 'NULL') as donor_phone,
  COALESCE(donor_email, 'NULL') as donor_email
FROM jianhui_org_donations
LIMIT 5;
