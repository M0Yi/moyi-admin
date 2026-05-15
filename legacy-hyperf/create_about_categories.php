<?php

/**
 * 创建"关于我们"文章分类并更新导航菜单
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

    // 1. 创建"关于我们"主分类
    echo "=== 创建关于我们主分类 ===\n";

    // 检查是否已存在
    $stmt = $pdo->prepare("SELECT id FROM jianhui_org_categories WHERE slug = ? LIMIT 1");
    $stmt->execute(['about_us']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $aboutUsId = $existing['id'];
        echo "关于我们主分类已存在，ID: {$aboutUsId}\n";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO jianhui_org_categories (name, slug, parent_id, sort_order, created_at, updated_at)
            VALUES (?, ?, 0, ?, NOW(), NOW())
        ");
        $stmt->execute(['关于我们', 'about_us', 10]);
        $aboutUsId = $pdo->lastInsertId();
        echo "✓ 创建关于我们主分类，ID: {$aboutUsId}\n";
    }

    // 2. 创建子分类
    echo "\n=== 创建关于我们子分类 ===\n";

    $subCategories = [
        ['name' => '我们是谁', 'slug' => 'who_we_are', 'sort' => 1],
        ['name' => '基本信息', 'slug' => 'basic_info', 'sort' => 2],
        ['name' => '使命与愿景', 'slug' => 'mission_vision', 'sort' => 3],
        ['name' => '大事记', 'slug' => 'milestones', 'sort' => 4],
        ['name' => '理事会', 'slug' => 'council', 'sort' => 5],
        ['name' => '我们的团队', 'slug' => 'our_team', 'sort' => 6],
        ['name' => '媒体报道', 'slug' => 'media_coverage_about', 'sort' => 7],
    ];

    $insertCatStmt = $pdo->prepare("
        INSERT INTO jianhui_org_categories (name, slug, parent_id, sort_order, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        sort_order = VALUES(sort_order),
        updated_at = NOW()
    ");

    foreach ($subCategories as $cat) {
        $insertCatStmt->execute([$cat['name'], $cat['slug'], $aboutUsId, $cat['sort']]);
        $id = $insertCatStmt->rowCount() == 2 ? "已存在，已更新" : "新建，ID: " . $pdo->lastInsertId();
        echo "  + {$cat['name']} (slug: {$cat['slug']}, sort: {$cat['sort']}) - {$id}\n";
    }

    // 3. 更新导航菜单
    echo "\n=== 更新导航菜单 ===\n";

    // 获取关于我们父菜单ID - 使用ID直接查询（避免中文编码问题）
    $stmt = $pdo->query("SELECT id, name FROM jianhui_org_navigation WHERE id = 2 LIMIT 1");
    $aboutNav = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aboutNav) {
        echo "错误: 找不到ID=2的导航菜单\n";
        exit(1);
    }

    $navParentId = $aboutNav['id'];
    echo "找到关于我们父菜单: ID={$navParentId}, Name={$aboutNav['name']}\n";

    // 删除旧的子菜单
    $stmt = $pdo->prepare("DELETE FROM jianhui_org_navigation WHERE parent_id = ?");
    $stmt->execute([$navParentId]);
    $deletedCount = $stmt->rowCount();
    echo "✓ 已删除 {$deletedCount} 个旧子菜单\n";

    // 插入新的子菜单
    echo "\n插入新子菜单:\n";
    $insertNavStmt = $pdo->prepare("
        INSERT INTO jianhui_org_navigation (name, url, parent_id, sort_order, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");

    foreach ($subCategories as $cat) {
        $url = "/about/{$cat['slug']}";
        $insertNavStmt->execute([$cat['name'], $url, $navParentId, $cat['sort']]);
        echo "  + {$cat['name']} -> {$url} (Sort: {$cat['sort']})\n";
    }

    echo "\n✓ 已插入 " . count($subCategories) . " 个新子菜单\n";

    // 4. 验证结果
    echo "\n=== 验证结果 ===\n";

    // 验证分类
    $stmt = $pdo->prepare("
        SELECT id, name, slug, sort_order
        FROM jianhui_org_categories
        WHERE parent_id = ?
        ORDER BY sort_order, id
    ");
    $stmt->execute([$aboutUsId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "文章分类:\n";
    foreach ($categories as $cat) {
        echo "  [{$cat['id']}] {$cat['name']} ({$cat['slug']}) - Sort: {$cat['sort_order']}\n";
    }

    // 验证导航菜单
    $stmt = $pdo->prepare("
        SELECT id, name, url, sort_order
        FROM jianhui_org_navigation
        WHERE parent_id = ?
        ORDER BY sort_order, id
    ");
    $stmt->execute([$navParentId]);
    $navItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\n导航菜单:\n";
    foreach ($navItems as $item) {
        echo "  [{$item['id']}] {$item['name']} -> {$item['url']} - Sort: {$item['sort_order']}\n";
    }

    echo "\n✅ 完成！文章分类和导航菜单已同步。\n";

} catch (PDOException $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    exit(1);
}
