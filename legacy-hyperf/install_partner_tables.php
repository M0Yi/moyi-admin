<?php
/**
 * 创建合作伙伴表并插入示例数据（使用PDO直接连接）
 */

// 数据库配置
$config = [
    'host' => getenv('PG_HOST') ?: 'postgres.orb.local',
    'port' => getenv('PG_PORT') ?: '5432',
    'dbname' => getenv('PG_DATABASE') ?: 'postgres',
    'user' => getenv('PG_USERNAME') ?: 'postgres',
    'password' => getenv('PG_PASSWORD') ?: 'postgres',
];

echo "=== 开始创建合作伙伴表结构 ===\n\n";
echo "数据库配置:\n";
echo "  主机: {$config['host']}\n";
echo "  端口: {$config['port']}\n";
echo "  数据库: {$config['dbname']}\n";
echo "  用户: {$config['user']}\n\n";

try {
    // 连接数据库
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};options='--client_encoding=UTF8'";
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "✓ 数据库连接成功\n\n";

    // 创建合作伙伴分类表
    echo "1. 创建 jianhui_org_partner_categories 表...\n";
    $sql = "CREATE TABLE IF NOT EXISTS jianhui_org_partner_categories (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) UNIQUE NOT NULL,
        description TEXT,
        sort_order INTEGER DEFAULT 0,
        is_active BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "   ✓ 分类表创建成功\n";

    // 创建合作伙伴表
    echo "2. 创建 jianhui_org_partners 表...\n";
    $sql = "CREATE TABLE IF NOT EXISTS jianhui_org_partners (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        logo_url VARCHAR(500),
        website_url VARCHAR(500),
        description TEXT,
        category_id INTEGER,
        sort_order INTEGER DEFAULT 0,
        is_active BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES jianhui_org_partner_categories(id) ON DELETE SET NULL
    )";
    $pdo->exec($sql);
    echo "   ✓ 合作伙伴表创建成功\n";

    // 创建索引
    echo "3. 创建索引...\n";
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_partners_category_id ON jianhui_org_partners(category_id)",
        "CREATE INDEX IF NOT EXISTS idx_partners_is_active ON jianhui_org_partners(is_active)",
        "CREATE INDEX IF NOT EXISTS idx_partners_sort_order ON jianhui_org_partners(sort_order)",
        "CREATE INDEX IF NOT EXISTS idx_partner_categories_slug ON jianhui_org_partner_categories(slug)",
        "CREATE INDEX IF NOT EXISTS idx_partner_categories_is_active ON jianhui_org_partner_categories(is_active)",
        "CREATE INDEX IF NOT EXISTS idx_partner_categories_sort_order ON jianhui_org_partner_categories(sort_order)"
    ];

    foreach ($indexes as $index) {
        $pdo->exec($index);
    }
    echo "   ✓ 索引创建成功\n";

    // 检查是否已有数据
    $stmt = $pdo->query("SELECT COUNT(*) FROM jianhui_org_partner_categories");
    $existingCategories = $stmt->fetchColumn();

    if ($existingCategories > 0) {
        echo "\n⚠ 表中已存在 {$existingCategories} 个分类，跳过数据插入\n";
        echo "如需重新插入数据，请先清空表：\n";
        echo "  TRUNCATE jianhui_org_partners, jianhui_org_partner_categories CASCADE;\n";
    } else {
        // 插入分类数据
        echo "\n4. 插入合作伙伴分类...\n";
        $categories = [
            ['name' => '战略合作伙伴', 'slug' => 'strategic', 'description' => '与我们深度合作的战略伙伴', 'sort_order' => 1],
            ['name' => '企业合作伙伴', 'slug' => 'enterprise', 'description' => '支持公益事业的企业伙伴', 'sort_order' => 2],
            ['name' => '媒体合作伙伴', 'slug' => 'media', 'description' => '传播公益理念的媒体伙伴', 'sort_order' => 3],
            ['name' => '公益组织', 'slug' => 'ngo', 'description' => '共同推动公益发展的组织', 'sort_order' => 4],
            ['name' => '基金会伙伴', 'slug' => 'foundation', 'description' => '其他慈善基金会', 'sort_order' => 5]
        ];

        $categoryMap = [];
        $stmt = $pdo->prepare("INSERT INTO jianhui_org_partner_categories (name, slug, description, sort_order, is_active) VALUES (?, ?, ?, ?, true) RETURNING id");

        foreach ($categories as $cat) {
            $stmt->execute([$cat['name'], $cat['slug'], $cat['description'], $cat['sort_order']]);
            $id = $stmt->fetchColumn();
            $categoryMap[$cat['slug']] = $id;
            echo "   ✓ {$cat['name']} (ID: {$id})\n";
        }

        // 插入合作伙伴数据
        echo "\n5. 插入合作伙伴数据...\n";

        $partners = [
            // 战略合作伙伴
            ['name' => '腾讯公益慈善基金会', 'logo_url' => 'https://gongyi.qq.com/favicon.ico', 'website_url' => 'https://gongyi.qq.com', 'description' => '腾讯公益慈善基金会', 'category_slug' => 'strategic', 'sort_order' => 1],
            ['name' => '阿里巴巴公益基金会', 'logo_url' => 'https://kkb.aliyun.com/favicon.ico', 'website_url' => 'https://kuangquan.aliyun.com', 'description' => '阿里巴巴公益基金会', 'category_slug' => 'strategic', 'sort_order' => 2],
            ['name' => '字节跳动公益', 'logo_url' => 'https://www.bytedance.com', 'website_url' => 'https://www.bytedance.com', 'description' => '字节跳动公益平台', 'category_slug' => 'strategic', 'sort_order' => 3],
            ['name' => '美团公益', 'logo_url' => 'https://www.meituan.com', 'website_url' => 'https://www.meituan.com', 'description' => '美团公益基金会', 'category_slug' => 'strategic', 'sort_order' => 4],

            // 企业合作伙伴
            ['name' => '华为技术有限公司', 'logo_url' => 'https://www.huawei.com', 'website_url' => 'https://www.huawei.com', 'description' => '支持公益事业的科技企业', 'category_slug' => 'enterprise', 'sort_order' => 1],
            ['name' => '小米科技', 'logo_url' => 'https://www.mi.com', 'website_url' => 'https://www.mi.com', 'description' => '科技向善', 'category_slug' => 'enterprise', 'sort_order' => 2],
            ['name' => '网易公司', 'logo_url' => 'https://www.163.com', 'website_url' => 'https://www.163.com', 'description' => '网易公益', 'category_slug' => 'enterprise', 'sort_order' => 3],
            ['name' => '京东集团', 'logo_url' => 'https://www.jd.com', 'website_url' => 'https://www.jd.com', 'description' => '京东公益基金会', 'category_slug' => 'enterprise', 'sort_order' => 4],
            ['name' => '拼多多', 'logo_url' => 'https://www.pinduoduo.com', 'website_url' => 'https://www.pinduoduo.com', 'description' => '拼多多公益', 'category_slug' => 'enterprise', 'sort_order' => 5],
            ['name' => '百度公司', 'logo_url' => 'https://www.baidu.com', 'website_url' => 'https://www.baidu.com', 'description' => '百度公益', 'category_slug' => 'enterprise', 'sort_order' => 6],

            // 媒体合作伙伴
            ['name' => '中央电视台', 'logo_url' => 'https://www.cctv.com', 'website_url' => 'https://www.cctv.com', 'description' => '国家级媒体平台', 'category_slug' => 'media', 'sort_order' => 1],
            ['name' => '人民日报', 'logo_url' => 'https://www.people.com.cn', 'website_url' => 'https://www.people.com.cn', 'description' => '权威媒体', 'category_slug' => 'media', 'sort_order' => 2],
            ['name' => '新华社', 'logo_url' => 'http://www.xinhuanet.com', 'website_url' => 'http://www.xinhuanet.com', 'description' => '国家通讯社', 'category_slug' => 'media', 'sort_order' => 3],
            ['name' => '中国青年报', 'logo_url' => 'http://www.cyol.com', 'website_url' => 'http://www.cyol.com', 'description' => '中央主流媒体', 'category_slug' => 'media', 'sort_order' => 4],

            // 公益组织
            ['name' => '中国扶贫基金会', 'logo_url' => 'https://www.fupin.org.cn', 'website_url' => 'https://www.fupin.org.cn', 'description' => '全国性公募基金会', 'category_slug' => 'ngo', 'sort_order' => 1],
            ['name' => '中华社会救助基金会', 'logo_url' => 'https://www.csaf.org.cn', 'website_url' => 'https://www.csaf.org.cn', 'description' => '全国性公募基金会', 'category_slug' => 'ngo', 'sort_order' => 2],
            ['name' => '中国青少年发展基金会', 'logo_url' => 'https://www.cydf.org.cn', 'website_url' => 'https://www.cydf.org.cn', 'description' => '希望工程实施机构', 'category_slug' => 'ngo', 'sort_order' => 3],
            ['name' => '中国儿童少年基金会', 'logo_url' => 'https://www.cctf.org.cn', 'website_url' => 'https://www.cctf.org.cn', 'description' => '全国性公募基金会', 'category_slug' => 'ngo', 'sort_order' => 4],
            ['name' => '中国红十字会', 'logo_url' => 'https://www.redcross.org.cn', 'website_url' => 'https://www.redcross.org.cn', 'description' => '人道主义救援组织', 'category_slug' => 'ngo', 'sort_order' => 5],

            // 基金会伙伴
            ['name' => '中国社会福利基金会', 'logo_url' => 'https://www.cswcf.org', 'website_url' => 'https://www.cswcf.org', 'description' => '全国性公募基金会', 'category_slug' => 'foundation', 'sort_order' => 1],
            ['name' => '中华少年儿童慈善救助基金会', 'logo_url' => 'https://www.cctf.org.cn', 'website_url' => 'https://www.cctf.org.cn', 'description' => '全国性公募基金会', 'category_slug' => 'foundation', 'sort_order' => 2],
            ['name' => '中国志愿服务基金会', 'logo_url' => 'https://www.cvsf.org.cn', 'website_url' => 'https://www.cvsf.org.cn', 'description' => '全国性公募基金会', 'category_slug' => 'foundation', 'sort_order' => 3],
            ['name' => '华侨公益基金会', 'logo_url' => 'https://www.hqgongyi.org', 'website_url' => 'https://www.hqgongyi.org', 'description' => '服务华侨华人公益事业', 'category_slug' => 'foundation', 'sort_order' => 4],
        ];

        $stmt = $pdo->prepare("INSERT INTO jianhui_org_partners (name, logo_url, website_url, description, category_id, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, true)");
        $partnerCount = 0;

        foreach ($partners as $partner) {
            $categoryId = $categoryMap[$partner['category_slug']];
            $stmt->execute([
                $partner['name'],
                $partner['logo_url'],
                $partner['website_url'],
                $partner['description'],
                $categoryId,
                $partner['sort_order']
            ]);
            $partnerCount++;
            echo "   ✓ {$partner['name']}\n";
        }

        echo "\n=== 安装完成 ===\n";
        echo "创建了 " . count($categories) . " 个分类\n";
        echo "插入了 {$partnerCount} 个合作伙伴\n";
    }

    echo "\n接下来需要：\n";
    echo "1. 创建后端API接口 (在 JianhuiOrg AdminApiController 中)\n";
    echo "2. 创建前端API调用 (在 frontend/src/api/ 中)\n";
    echo "3. 重新设计前端 Partners/Index.vue 页面\n";

} catch (PDOException $e) {
    echo "\n❌ 数据库错误: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ 脚本执行完成\n";
