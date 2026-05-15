<?php

/**
 * 查询关于我们 - 文章分类和导航菜单
 */

try {
    // 数据库配置
    $config = [
        'host' => getenv('PG_HOST') ?: 'postgres.orb.local',
        'port' => getenv('PG_PORT') ?: 5432,
        'dbname' => getenv('PG_DATABASE') ?: 'postgres',
        'user' => getenv('PG_USERNAME') ?: 'postgres',
        'password' => getenv('PG_PASSWORD') ?: 'postgres',
    ];

    // 连接数据库
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "✓ 数据库连接成功\n\n";

    // 查询关于我们 - 文章分类
    echo "=== 关于我们 - 文章分类 ===\n";
    $stmt = $pdo->query("
        SELECT c.id, c.name, c.slug, c.parent_id, c.sort_order
        FROM jianhui_org_categories c
        WHERE c.parent_id = (
          SELECT id FROM jianhui_org_categories WHERE slug = 'about_us'
        )
        ORDER BY c.sort_order, c.id
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo sprintf("找到 %d 个子分类:\n", count($categories));
    foreach ($categories as $cat) {
        echo sprintf("  ID: %d | Name: %s | Slug: %s | Sort: %d\n",
            $cat['id'], $cat['name'], $cat['slug'], $cat['sort_order']);
    }

    echo "\n";

    // 查询关于我们 - 导航菜单
    echo "=== 关于我们 - 导航菜单 ===\n";
    $stmt = $pdo->query("
        SELECT n.id, n.name, n.url, n.parent_id, n.sort_order
        FROM jianhui_org_navigation n
        WHERE n.name = '关于我们' OR n.parent_id = (
          SELECT id FROM jianhui_org_navigation WHERE name = '关于我们'
        )
        ORDER BY n.parent_id, n.sort_order, n.id
    ");
    $navItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo sprintf("找到 %d 个菜单项:\n", count($navItems));
    foreach ($navItems as $item) {
        $indent = $item['name'] === '关于我们' ? '' : '  ';
        echo sprintf("%sID: %d | Name: %s | URL: %s | Parent: %s | Sort: %d\n",
            $indent,
            $item['id'],
            $item['name'],
            $item['url'],
            $item['parent_id'] ?? 'NULL',
            $item['sort_order']
        );
    }

    // 生成SQL更新语句
    echo "\n=== 建议的SQL更新语句 ===\n";

    // 获取关于我们父菜单ID
    $stmt = $pdo->query("SELECT id FROM jianhui_org_navigation WHERE name = '关于我们' AND parent_id IS NULL");
    $aboutNavParent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($aboutNavParent) {
        $parentId = $aboutNavParent['id'];
        echo "-- 关于我们父菜单ID: {$parentId}\n";

        $sortOrder = 1;
        foreach ($categories as $cat) {
            $navName = $cat['name'];
            $navUrl = "/about/{$cat['slug']}";

            // 检查是否已存在该菜单项
            $stmt = $pdo->prepare("SELECT id FROM jianhui_org_navigation WHERE parent_id = ? AND name = ?");
            $stmt->execute([$parentId, $navName]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                echo "-- 更新现有菜单项: {$navName}\n";
                echo sprintf("UPDATE jianhui_org_navigation SET url = '%s', sort_order = %d WHERE id = %d;\n",
                    $navUrl, $sortOrder, $existing['id']);
            } else {
                echo "-- 新增菜单项: {$navName}\n";
                echo sprintf("INSERT INTO jianhui_org_navigation (name, url, parent_id, sort_order, created_at, updated_at) VALUES ('%s', '%s', %d, %d, NOW(), NOW());\n",
                    $navName, $navUrl, $parentId, $sortOrder);
            }
            $sortOrder++;
        }
    }

} catch (PDOException $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    exit(1);
}
