<?php

/**
 * 创建jianhui_org_articles表并添加关于我们示例文章
 */

try {
    $config = [
        'host' => getenv('DB_HOST') ?: 'mysql8.orb.local',
        'port' => getenv('DB_PORT') ?: 3306,
        'database' => getenv('DB_DATABASE') ?: 'moyi',
        'username' => getenv('DB_USERNAME') ?: 'moyi',
        'password' => getenv('DB_PASSWORD') ?: 'moyi123',
        'charset' => 'utf8mb4',
    ];

    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    echo "✓ 数据库连接成功\n\n";

    // 1. 创建jianhui_org_articles表
    echo "=== 创建jianhui_org_articles表 ===\n";

    $sql = "CREATE TABLE IF NOT EXISTS jianhui_org_articles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category_id BIGINT UNSIGNED NOT NULL COMMENT '分类ID',
        title VARCHAR(200) NOT NULL COMMENT '标题',
        slug VARCHAR(200) NOT NULL COMMENT 'URL别名',
        summary TEXT COMMENT '摘要',
        description TEXT COMMENT '描述',
        content LONGTEXT COMMENT '内容',
        cover_image VARCHAR(500) COMMENT '封面图片',
        author VARCHAR(100) COMMENT '作者',
        source VARCHAR(200) COMMENT '来源',
        view_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '浏览次数',
        like_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点赞数',
        is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否精选',
        is_pinned TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否置顶',
        status VARCHAR(20) NOT NULL DEFAULT 'published' COMMENT '状态：draft草稿/published已发布',
        published_at TIMESTAMP NULL COMMENT '发布时间',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_category_id (category_id),
        INDEX idx_slug (slug),
        INDEX idx_status (status),
        INDEX idx_is_featured (is_featured),
        INDEX idx_is_pinned (is_pinned),
        INDEX idx_published_at (published_at),
        UNIQUE KEY uk_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文章表'";

    $pdo->exec($sql);
    echo "✓ jianhui_org_articles表创建成功\n\n";

    // 2. 获取关于我们分类列表
    echo "=== 获取关于我们分类列表 ===\n";
    $stmt = $pdo->query("
        SELECT id, name, slug
        FROM jianhui_org_categories
        WHERE parent_id = 4
        ORDER BY sort_order
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "找到 " . count($categories) . " 个关于我们子分类\n\n";

    // 3. 为每个分类添加示例文章
    echo "=== 添加示例文章 ===\n";

    $sampleArticles = [
        [
            'category_id' => 5, // 我们是谁
            'title' => '深圳市建辉慈善基金会简介',
            'slug' => 'jianhui-foundation-introduction',
            'summary' => '深圳市建辉慈善基金会是一家致力于发现和支持身处困境但仍坚持行善的普通人的公益组织。',
            'content' => '<h2>机构概况</h2>
<p>深圳市建辉慈善基金会（以下简称"建辉基金会"）成立于2016年，是一家在深圳市民政局注册成立的非公募基金会。</p>

<h2>我们的使命</h2>
<p>建辉基金会的使命是"让行善者更有力量"。我们致力于发现那些身处困境但仍坚持行善的普通人，通过资金支持、媒体宣传和社会倡导等方式，为他们提供持续的帮助和关注。</p>

<h2>核心价值观</h2>
<ul>
<li>尊重每一位行善者的尊严和价值</li>
<li>透明、公正地使用每一笔善款</li>
<li>用专业的方法提供有效的帮助</li>
<li>倡导全社会关注和支持行善者</li>
</ul>

<h2>组织定位</h2>
<p>建辉基金会是一家专注于支持个体行善者的公益组织，我们不直接运营公益项目，而是通过支持那些在一线默默行善的个人，间接推动社会公益事业的发展。</p>',
            'author' => '建辉基金会',
            'status' => 'published'
        ],
        [
            'category_id' => 6, // 基本信息
            'title' => '基金会基本信息',
            'slug' => 'foundation-basic-info',
            'summary' => '建辉慈善基金会的登记信息、宗旨、业务范围等基本信息。',
            'content' => '<h2>登记信息</h2>
<table>
<tr><td>名称</td><td>深圳市建辉慈善基金会</td></tr>
<tr><td>统一社会信用代码</td><td>53440300MJL0167883</td></tr>
<tr><td>登记管理机关</td><td>深圳市民政局</td></tr>
<tr><td>业务主管单位</td><td>深圳市相关职能部门</td></tr>
<tr><td>成立时间</td><td>2016年</td></tr>
<tr><td>类型</td><td>非公募基金会</td></tr>
</table>

<h2>宗旨</h2>
<p>遵守宪法、法律、法规和国家政策，遵守社会道德风尚，弘扬慈善精神，救助社会困难群体，促进社会和谐进步。</p>

<h2>业务范围</h2>
<ul>
<li>资助困境中的行善者</li>
<li>开展公益慈善宣传</li>
<li>资助公益慈善项目</li>
<li>开展慈善交流与合作</li>
</ul>

<h2>联系方式</h2>
<p>地址：深圳市南山区粤兴五道9号北理工创新大厦1103室</p>
<p>电话：0755-83239875</p>',
            'author' => '建辉基金会',
            'status' => 'published'
        ],
        [
            'category_id' => 7, // 使命与愿景
            'title' => '我们的使命与愿景',
            'slug' => 'mission-and-vision',
            'summary' => '让行善者更有力量，成为最受信赖的行善者支持平台。',
            'content' => '<h2>使命</h2>
<p><strong>让行善者更有力量</strong></p>
<p>我们相信，每一个行善者都是社会的一盏灯。当他们在困境中依然坚持行善时，理应得到社会的关注、尊重和支持。我们的使命就是让这些行善者更有力量，让他们的善行能够持续下去。</p>

<h2>愿景</h2>
<p><strong>成为最受信赖的行善者支持平台</strong></p>
<p>我们希望建立一个透明、高效、可持续的公益平台，连接社会爱心资源与困境中的行善者，让每一份善意都能发挥最大的价值。</p>

<h2>价值观</h2>
<h3>尊重</h3>
<p>尊重每一位行善者的选择和尊严，不以居高临下的态度施舍，而是以平等的姿态支持。</p>

<h3>透明</h3>
<p>坚持财务公开、项目透明，让捐赠者清楚地知道自己的善款用在了哪里。</p>

<h3>专业</h3>
<p>用专业的项目管理方法和严格的评估体系，确保每一分善款都用在刀刃上。</p>

<h3>可持续</h3>
<p>不仅提供一次性的资助，更关注行善者的长期发展和可持续性。</p>

<h2>我们关注的行善者类型</h2>
<ul>
<li>长期助养孤儿、残障儿童、孤寡老人的人</li>
<li>义务救助流浪动物的人</li>
<li>坚持无偿献血的人</li>
<li>在危急时刻挺身而出救人的人</li>
<li>义务支教、助学的人</li>
<li>其他在困境中坚持行善的普通人</li>
</ul>',
            'author' => '建辉基金会',
            'status' => 'published'
        ],
        [
            'category_id' => 8, // 大事记
            'title' => '建辉基金会发展历程',
            'slug' => 'foundation-milestones',
            'summary' => '回顾建辉基金会成立以来的重要发展节点。',
            'content' => '<h2>发展历程</h2>

<h3>2016年</h3>
<ul>
<li>深圳市建辉慈善基金会在深圳市民政局正式注册成立</li>
<li>确立"致敬困境中的行善者"为核心使命</li>
<li>启动首个行善者支持项目</li>
</ul>

<h3>2017年</h3>
<ul>
<li>首批受助行善者名单确定，开始提供持续支持</li>
<li>建立行善者发现和评估体系</li>
<li>开展首次大规模公众倡导活动</li>
</ul>

<h3>2018年</h3>
<ul>
<li>开发"行善者故事"栏目，通过媒体传播行善者事迹</li>
<li>与多家媒体建立合作关系，扩大社会影响力</li>
<li>受助行善者数量突破100位</li>
</ul>

<h3>2019年</h3>
<ul>
<li>启动互联网募捐平台，拓展捐赠渠道</li>
<li>完善项目管理流程，提高运营效率</li>
<li>获得公益行业多项荣誉和认可</li>
</ul>

<h3>2020年</h3>
<ul>
<li>在新冠疫情期间，为奋战一线的行善者提供特别支持</li>
<li>开展线上公益活动，创新公益参与方式</li>
<li>累计捐赠金额突破1000万元</li>
</ul>

<h3>至今</h3>
<ul>
<li>持续发现和支持更多困境中的行善者</li>
<li>不断完善项目管理和服务体系</li>
<li>努力成为行善者最可信赖的支持平台</li>
</ul>',
            'author' => '建辉基金会',
            'status' => 'published'
        ],
        [
            'category_id' => 9, // 理事会
            'title' => '理事会成员介绍',
            'slug' => 'council-members',
            'summary' => '建辉基金会理事会由一群热心公益、经验丰富的专业人士组成。',
            'content' => '<h2>理事会</h2>
<p>深圳市建辉慈善基金会理事会是基金会的决策机构，负责制定基金会的发展战略、审议重大事项、监督基金会的运营管理。</p>

<h2>理事会成员</h2>
<p>理事会成员来自不同行业和领域，包括企业家、公益从业者、法律专家、财务专家等，他们以志愿者的身份为基金会的发展贡献力量。</p>

<h2>理事会职责</h2>
<ul>
<li>制定和修改基金会章程</li>
<li>选举和罢免理事长、副理事长</li>
<li>审议基金会年度工作计划和预算</li>
<li>审议基金会年度工作报告和财务报告</li>
<li>决定基金会的重大业务活动</li>
<li>制定基金会管理制度</li>
<li>决定基金会的分立、合并或终止</li>
</ul>

<h2>理事会会议</h2>
<p>理事会每年至少召开2次会议，会议由理事长负责召集和主持。理事会决议须经全体理事过半数通过方为有效。</p>

<p><em>具体理事会成员信息请参考基金会官方披露的年度报告。</em></p>',
            'author' => '建辉基金会',
            'status' => 'published'
        ],
        [
            'category_id' => 10, // 我们的团队
            'title' => '我们的团队',
            'slug' => 'our-team',
            'summary' => '建辉基金会拥有一支专业、热情、富有爱心的团队。',
            'content' => '<h2>团队介绍</h2>
<p>深圳市建辉慈善基金会的团队由一群热爱公益事业、充满理想和热情的专业人士组成。我们来自不同的背景，但有着共同的使命——让行善者更有力量。</p>

<h2>核心团队</h2>
<p>基金会的核心团队包括项目管理、筹款、财务、宣传、行政等多个部门，各部门协同合作，确保基金会各项工作顺利开展。</p>

<h2>团队文化</h2>
<h3>以使命为导向</h3>
<p>我们始终牢记"让行善者更有力量"的使命，将行善者的需求放在第一位。</p>

<h3>专业高效</h3>
<p>我们以专业的态度和方法开展工作，追求高效的项目执行和资金使用。</p>

<h3>持续学习</h3>
<p>我们不断学习公益领域的先进理念和方法，提升自身的专业能力。</p>

<h3>开放包容</h3>
<p>我们欢迎不同背景的人才加入，尊重多元化的观点和建议。</p>

<h2>志愿者团队</h2>
<p>除了核心团队，建辉基金会还拥有一大批优秀的志愿者。他们在项目调研、行善者发现、宣传推广等方面发挥着重要作用。</p>

<h2>加入我们</h2>
<p>如果你也认同我们的使命，愿意为公益事业贡献力量，欢迎加入我们的团队。请关注"加入我们"页面了解招聘信息和志愿者招募详情。</p>',
            'author' => '建辉基金会',
            'status' => 'published'
        ],
        [
            'category_id' => 11, // 媒体报道
            'title' => '建辉基金会获媒体广泛关注',
            'slug' => 'media-coverage-highlights',
            'summary' => '建辉基金会的公益实践获得社会各界和媒体的广泛关注和认可。',
            'content' => '<h2>媒体关注</h2>
<p>深圳市建辉基金会在致敬困境中的行善者方面的实践和探索，获得了社会各界的广泛关注和认可。</p>

<h2>报道亮点</h2>
<h3>专题报道</h3>
<p>多家主流媒体对建辉基金会及其支持的行善者进行了深入报道，包括：</p>
<ul>
<li>《人民日报》报道了建辉基金会支持的行善者感人故事</li>
<li>《南方日报》专题报道"致敬困境中的行善者"项目</li>
<li>《深圳特区报》连续报道建辉基金会公益实践</li>
</ul>

<h3>电视节目</h3>
<ul>
<li>央视社会与法频道专题节目报道建辉基金会行善者故事</li>
<li>深圳电视台《第一现场》栏目专题报道</li>
</ul>

<h3>新媒体传播</h3>
<ul>
<li>微信公众号、微博、抖音等新媒体平台广泛传播</li>
<li>多个行善者故事短视频获得百万级播放量</li>
<li>网友自发转发，形成良好的社会反响</li>
</ul>

<h2>荣誉与认可</h2>
<ul>
<li>获得深圳市社会组织评估等级认证</li>
<li>入选中国公益慈善项目大赛</li>
<li>获得多个公益行业创新奖项</li>
</ul>

<h2>社会影响</h2>
<p>通过媒体的广泛传播，建辉基金会所倡导的"致敬行善者"理念逐渐被更多人了解和认同，带动了更多社会力量关注和支持困境中的行善者。</p>',
            'author' => '建辉基金会',
            'status' => 'published'
        ]
    ];

    $insertStmt = $pdo->prepare("
        INSERT INTO jianhui_org_articles
        (category_id, title, slug, summary, content, author, status, published_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
    ");

    $addedCount = 0;
    foreach ($sampleArticles as $index => $article) {
        try {
            $insertStmt->execute([
                $article['category_id'],
                $article['title'],
                $article['slug'],
                $article['summary'],
                $article['content'],
                $article['author'],
                $article['status']
            ]);
            $articleId = $pdo->lastInsertId();

            // 找到对应的分类名称
            $catName = '';
            foreach ($categories as $cat) {
                if ($cat['id'] == $article['category_id']) {
                    $catName = $cat['name'];
                    break;
                }
            }

            echo "✓ 添加文章: [{$articleId}] {$article['title']} (分类: {$catName})\n";
            $addedCount++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "! 文章已存在: {$article['title']}\n";
            } else {
                echo "✗ 添加失败: {$article['title']} - {$e->getMessage()}\n";
            }
        }
    }

    echo "\n✓ 成功添加 {$addedCount} 篇示例文章\n\n";

    // 4. 验证结果
    echo "=== 验证结果 ===\n";
    foreach ($categories as $cat) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM jianhui_org_articles WHERE category_id = ?");
        $stmt->execute([$cat['id']]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        echo sprintf("%s: %d 篇文章\n", $cat['name'], $count);
    }

    echo "\n✅ 完成！所有关于我们分类都已添加示例文章。\n";

} catch (PDOException $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    exit(1);
}
