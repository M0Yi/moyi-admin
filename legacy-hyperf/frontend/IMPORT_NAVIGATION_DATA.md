-- 导航菜单完整数据
-- 先清空现有数据
TRUNCATE TABLE jianhui_org_navigations;

-- 插入完整的导航菜单数据
INSERT INTO jianhui_org_navigations (id, parent_id, name, url, icon, target, sort_order, is_active, created_at, updated_at) VALUES
-- 1. 首页
(1, 0, '首页', '/', NULL, '_self', 1, 1, NOW(), NOW()),

-- 2. 关于我们
(2, 0, '关于我们', '/about', NULL, '_self', 2, 1, NOW(), NOW()),
(21, 2, '发起人介绍', '/about/founder', NULL, '_self', 1, 1, NOW(), NOW()),
(22, 2, '基金会介绍', '/about', NULL, '_self', 2, 1, NOW(), NOW()),
(23, 2, '理事会及监事会', '/about/council', NULL, '_self', 3, 1, NOW(), NOW()),
(24, 2, '基金会章程', '/about/constitution', NULL, '_self', 4, 1, NOW(), NOW()),
(25, 2, '管理制度', '/about/management', NULL, '_self', 5, 1, NOW(), NOW()),
(26, 2, '资质证书', '/about/certificates', NULL, '_self', 6, 1, NOW(), NOW()),

-- 3. 公益项目
(3, 0, '公益项目', '/projects', NULL, '_self', 3, 1, NOW(), NOW()),
(31, 3, '非定向', '/projects?type=undesignated', NULL, '_self', 1, 1, NOW(), NOW()),
(32, 3, '应急响应与救援', '/projects?type=emergency', NULL, '_self', 2, 1, NOW(), NOW()),
(33, 3, '医疗援助与发展', '/projects?type=medical', NULL, '_self', 3, 1, NOW(), NOW()),
(34, 3, '健康社会关怀', '/projects?type=health', NULL, '_self', 4, 1, NOW(), NOW()),

-- 4. 爱心捐赠
(4, 0, '爱心捐赠', '/donate', NULL, '_self', 4, 1, NOW(), NOW()),
(41, 4, '捐赠方式', '/donate', NULL, '_self', 1, 1, NOW(), NOW()),
(42, 4, '捐赠披露', '/donation-disclosure', NULL, '_self', 2, 1, NOW(), NOW()),
(43, 4, '票据开具', '/donate/invoice', NULL, '_self', 3, 1, NOW(), NOW()),
(44, 4, '证书申领', '/donate/certificate', NULL, '_self', 4, 1, NOW(), NOW()),
(45, 4, '抵扣说明', '/donate/deduction', NULL, '_self', 5, 1, NOW(), NOW()),
(46, 4, '爱心传递', '/donate/share', NULL, '_self', 6, 1, NOW(), NOW()),

-- 5. 新闻中心
(5, 0, '新闻中心', '/articles', NULL, '_self', 5, 1, NOW(), NOW()),
(51, 5, '网站公告', '/articles?category=notice', NULL, '_self', 1, 1, NOW(), NOW()),
(52, 5, '项目动态', '/articles?category=project', NULL, '_self', 2, 1, NOW(), NOW()),
(53, 5, '视频动态', '/articles?category=video', NULL, '_self', 3, 1, NOW(), NOW()),
(54, 5, '志愿者动态', '/articles?category=volunteer', NULL, '_self', 4, 1, NOW(), NOW()),
(55, 5, '行业动态', '/articles?category=industry', NULL, '_self', 5, 1, NOW(), NOW()),
(56, 5, '社会评价', '/articles?category=social', NULL, '_self', 6, 1, NOW(), NOW()),

-- 6. 信息公开
(6, 0, '信息公开', '/disclosure', NULL, '_self', 6, 1, NOW(), NOW()),
(61, 6, '年度报告', '/disclosure/annual', NULL, '_self', 1, 1, NOW(), NOW()),
(62, 6, '工作报告', '/disclosure/work', NULL, '_self', 2, 1, NOW(), NOW()),
(63, 6, '审计报告', '/disclosure/audit', NULL, '_self', 3, 1, NOW(), NOW()),
(64, 6, '季度报告', '/disclosure/quarterly', NULL, '_self', 4, 1, NOW(), NOW()),
(65, 6, '投资活动', '/disclosure/investment', NULL, '_self', 5, 1, NOW(), NOW()),

-- 7. 党建专栏
(7, 0, '党建专栏', '/disclosure/party', NULL, '_self', 7, 1, NOW(), NOW()),

-- 8. 加入我们
(8, 0, '加入我们', '/join', NULL, '_self', 8, 1, NOW(), NOW()),
(81, 8, '人员招聘', '/join/recruitment', NULL, '_self', 1, 1, NOW(), NOW()),
(82, 8, '志愿者招募', '/join/volunteer', NULL, '_self', 2, 1, NOW(), NOW()),
(83, 8, '联系我们', '/about/contact', NULL, '_self', 3, 1, NOW(), NOW());
