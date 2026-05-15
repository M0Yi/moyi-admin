-- 清理轮播图脚本
-- 只保留ID为1的轮播图，删除其他所有轮播图

-- 查看当前所有轮播图
SELECT id, title, is_active, sort_order FROM jianhui_org_hero_slides ORDER BY id;

-- 删除除了ID为1之外的所有轮播图
DELETE FROM jianhui_org_hero_slides WHERE id != 1;

-- 验证删除结果
SELECT id, title, is_active, sort_order FROM jianhui_org_hero_slides ORDER BY id;
