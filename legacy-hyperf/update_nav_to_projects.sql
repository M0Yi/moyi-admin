-- 更新导航菜单：将"新闻动态"改为"项目动态"

-- 查看当前导航菜单
SELECT id, name, slug, type, url, sort_order
FROM jianhui_org_navigation
WHERE position = 'header'
ORDER BY sort_order;

-- 更新"新闻动态"导航为"项目动态"
-- 根据实际的name或slug进行匹配
UPDATE jianhui_org_navigation
SET
    name = '项目动态',
    slug = 'project_progress',
    type = 'link',
    url = '/projects'
WHERE name = '新闻动态' OR slug = 'news';

-- 如果上述UPDATE没有匹配到记录，可以使用以下INSERT语句添加新的导航项
-- INSERT INTO jianhui_org_navigation (name, slug, type, url, position, sort_order, is_active)
-- VALUES ('项目动态', 'project_progress', 'link', '/projects', 'header', 2, true);

-- 验证更新结果
SELECT id, name, slug, type, url, sort_order
FROM jianhui_org_navigation
WHERE position = 'header'
ORDER BY sort_order;
