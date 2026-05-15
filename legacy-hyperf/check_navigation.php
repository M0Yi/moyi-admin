<?php

/**
 * 查看所有导航菜单数据
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

    // 查询所有导航菜单
    echo "=== 所有导航菜单 ===\n";
    $stmt = $pdo->query("
        SELECT id, name, url, parent_id, sort_order
        FROM jianhui_org_navigation
        ORDER BY COALESCE(parent_id, 0), sort_order, id
    ");
    $navItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 按层级显示
    $mainItems = array_filter($navItems, function($item) {
        return $item['parent_id'] == 0 || $item['parent_id'] === null;
    });

    foreach ($mainItems as $main) {
        echo sprintf("主菜单: [%d] %s -> %s (sort: %d)\n",
            $main['id'], $main['name'], $main['url'], $main['sort_order']);

        // 查找子菜单
        $subItems = array_filter($navItems, function($item) use ($main) {
            return $item['parent_id'] == $main['id'];
        });

        foreach ($subItems as $sub) {
            echo sprintf("  └─ 子菜单: [%d] %s -> %s (sort: %d)\n",
                $sub['id'], $sub['name'], $sub['url'], $sub['sort_order']);
        }
        echo "\n";
    }

    echo "总计: " . count($navItems) . " 个菜单项\n";

    // 查找关于我们菜单
    echo "\n=== 关于我们菜单详情 ===\n";
    $stmt = $pdo->query("
        SELECT id, name, url, parent_id, sort_order
        FROM jianhui_org_navigation
        WHERE name LIKE '%关于%'
        ORDER BY parent_id, sort_order, id
    ");
    $aboutNav = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($aboutNav) > 0) {
        foreach ($aboutNav as $item) {
            $parentInfo = $item['parent_id'] ? " (parent: {$item['parent_id']})" : " (顶级)";
            echo sprintf("[%d] %s -> %s%s (sort: %d)\n",
                $item['id'], $item['name'], $item['url'], $parentInfo, $item['sort_order']);
        }
    } else {
        echo "未找到关于我们相关的菜单\n";
    }

} catch (PDOException $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    exit(1);
}
