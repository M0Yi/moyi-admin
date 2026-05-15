<?php

/**
 * 使用MySQL更新"关于我们"导航菜单以匹配文章分类
 */

try {
    // MySQL数据库配置（从.env文件读取）
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

    echo "✓ 数据库连接成功 (MySQL)\n";
    echo "  主机: {$config['host']}:{$config['port']}\n";
    echo "  数据库: {$config['database']}\n\n";

    // 1. 查询关于我们 - 文章分类
    echo "=== 关于我们 - 文章分类 ===\n";

    // 先找到about_us分类
    $stmt = $pdo->prepare("SELECT id FROM jianhui_org_categories WHERE slug = ? LIMIT 1");
    $stmt->execute(['about_us']);
    $aboutCat = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($aboutCat) {
        $aboutCatId = $aboutCat['id'];
        echo "找到关于我们主分类 ID: {$aboutCatId}\n";

        $stmt = $pdo->prepare("
            SELECT id, name, slug, parent_id, sort_order
            FROM jianhui_org_categories
            WHERE parent_id = ?
            ORDER BY sort_order, id
        ");
        $stmt->execute([$aboutCatId]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "找到 " . count($categories) . " 个子分类:\n";
        foreach ($categories as $cat) {
            echo "  ID: {$cat['id']} | Name: {$cat['name']} | Slug: {$cat['slug']} | Sort: {$cat['sort_order']}\n";
        }
    } else {
        echo "⚠️  未找到关于我们主分类 (slug='about_us')\n";

        // 显示所有主分类供参考
        echo "\n所有主分类:\n";
        $stmt = $pdo->query("
            SELECT id, name, slug, parent_id, sort_order
            FROM jianhui_org_categories
            WHERE parent_id = 0 OR parent_id IS NULL
            ORDER BY sort_order, id
            LIMIT 10
        ");
        $mainCats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($mainCats as $cat) {
            echo "  ID: {$cat['id']} | Name: {$cat['name']} | Slug: {$cat['slug']}\n";
        }
        exit(1);
    }

    echo "\n";

    // 2. 查询关于我们 - 导航菜单
    echo "=== 关于我们 - 导航菜单 ===\n";
    $stmt = $pdo->query("
        SELECT id, name, url, parent_id, sort_order
        FROM jianhui_org_navigation
        WHERE name = '关于我们' AND parent_id IS NULL
        LIMIT 1
    ");
    $aboutNav = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aboutNav) {
        echo "错误: 找不到'关于我们'父菜单\n";
        exit(1);
    }

    $navParentId = $aboutNav['id'];
    echo "关于我们父菜单 ID: {$navParentId}\n";

    $stmt = $pdo->prepare("
        SELECT id, name, url, sort_order
        FROM jianhui_org_navigation
        WHERE parent_id = ?
        ORDER BY sort_order, id
    ");
    $stmt->execute([$navParentId]);
    $currentNavItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "找到 " . count($currentNavItems) . " 个子菜单:\n";
    foreach ($currentNavItems as $item) {
        echo "  ID: {$item['id']} | {$item['name']} | {$item['url']} | Sort: {$item['sort_order']}\n";
    }

    // 3. 生成更新SQL
    echo "\n=== 准备更新 ===\n";

    // 删除旧子菜单
    $stmt = $pdo->prepare("DELETE FROM jianhui_org_navigation WHERE parent_id = ?");
    $stmt->execute([$navParentId]);
    $deletedCount = $stmt->rowCount();
    echo "✓ 已删除 {$deletedCount} 个旧子菜单\n";

    // 插入新子菜单
    echo "\n插入新子菜单:\n";
    $insertStmt = $pdo->prepare("
        INSERT INTO jianhui_org_navigation (name, url, parent_id, sort_order, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");

    $sortOrder = 1;
    foreach ($categories as $cat) {
        $url = "/about/{$cat['slug']}";
        $insertStmt->execute([$cat['name'], $url, $navParentId, $sortOrder]);
        echo "  + {$cat['name']} -> {$url} (Sort: {$sortOrder})\n";
        $sortOrder++;
    }

    echo "\n✓ 已插入 " . count($categories) . " 个新子菜单\n";

    // 4. 验证结果
    echo "\n=== 验证结果 ===\n";
    $stmt = $pdo->prepare("
        SELECT id, name, url, sort_order
        FROM jianhui_org_navigation
        WHERE parent_id = ?
        ORDER BY sort_order, id
    ");
    $stmt->execute([$navParentId]);
    $finalItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "更新后的导航菜单:\n";
    foreach ($finalItems as $item) {
        echo "  ID: {$item['id']} | {$item['name']} | {$item['url']} | Sort: {$item['sort_order']}\n";
    }

    echo "\n✅ 导航菜单更新完成！现在与文章分类保持一致。\n";

} catch (PDOException $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    exit(1);
}
