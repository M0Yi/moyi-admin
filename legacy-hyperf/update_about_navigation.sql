-- 更新"关于我们"导航菜单以匹配文章分类
-- 执行时间: 2025-03-25

-- 1. 首先查看当前"关于我们"导航菜单的父ID
SELECT id, name, url, parent_id, sort_order
FROM jianhui_org_navigation
WHERE name = '关于我们' AND parent_id IS NULL;

-- 假设父ID为3（根据之前的查询结果），获取父ID
DO $$
DECLARE
    v_parent_id INTEGER;
BEGIN
    -- 获取"关于我们"父菜单ID
    SELECT id INTO v_parent_id
    FROM jianhui_org_navigation
    WHERE name = '关于我们' AND parent_id IS NULL
    LIMIT 1;

    RAISE NOTICE '关于我们父菜单ID: %', v_parent_id;

    -- 2. 删除旧的子菜单项（保持数据一致性）
    DELETE FROM jianhui_org_navigation
    WHERE parent_id = v_parent_id;

    RAISE NOTICE '已删除旧的子菜单项';

    -- 3. 插入新的子菜单项（按照文章分类的顺序）
    INSERT INTO jianhui_org_navigation (name, url, parent_id, sort_order, created_at, updated_at) VALUES
    ('我们是谁', '/about/who_we_are', v_parent_id, 1, NOW(), NOW()),
    ('基本信息', '/about/basic_info', v_parent_id, 2, NOW(), NOW()),
    ('使命与愿景', '/about/mission_vision', v_parent_id, 3, NOW(), NOW()),
    ('大事记', '/about/milestones', v_parent_id, 4, NOW(), NOW()),
    ('理事会', '/about/council', v_parent_id, 5, NOW(), NOW()),
    ('我们的团队', '/about/our_team', v_parent_id, 6, NOW(), NOW()),
    ('媒体报道', '/about/media_coverage_about', v_parent_id, 7, NOW(), NOW());

    RAISE NOTICE '已插入7个新的子菜单项';

END $$;

-- 4. 验证更新结果
SELECT '=== 更新后的关于我们导航菜单 ===' AS info;
SELECT
    id,
    name,
    url,
    sort_order
FROM jianhui_org_navigation
WHERE parent_id = (SELECT id FROM jianhui_org_navigation WHERE name = '关于我们' AND parent_id IS NULL)
ORDER BY sort_order, id;
