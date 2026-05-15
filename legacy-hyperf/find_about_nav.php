<?php

/**
 * 查找关于我们导航菜单
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

    // 查询所有顶级菜单
    echo "=== 所有顶级导航菜单 ===\n";
    $stmt = $pdo->query("
        SELECT id, name, url, parent_id, sort_order
        FROM jianhui_org_navigation
        WHERE parent_id = 0 OR parent_id IS NULL
        ORDER BY sort_order, id
    ");
    $mainNavs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($mainNavs as $nav) {
        $nameHex = bin2hex($nav['name']);
        echo sprintf("ID: %d | Name: %s | Hex: %s | URL: %s | Sort: %d\n",
            $nav['id'], $nav['name'], $nameHex, $nav['url'], $nav['sort_order']);
    }

    // 尝试不同的查询方式
    echo "\n=== 查找关于我们菜单 ===\n";

    // 方式1: 直接中文名
    $stmt = $pdo->prepare("SELECT id, name FROM jianhui_org_navigation WHERE name = ? AND parent_id IS NULL LIMIT 1");
    $stmt->execute(['关于我们']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "方式1 (直接中文名): " . ($result ? "找到 ID={$result['id']}" : "未找到") . "\n";

    // 方式2: LIKE查询
    $stmt = $pdo->query("SELECT id, name FROM jianhui_org_navigation WHERE name LIKE '%关于%' AND parent_id IS NULL");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "方式2 (LIKE查询): 找到 " . count($results) . " 条\n";
    foreach ($results as $r) {
        echo "  ID: {$r['id']} | Name: {$r['name']}\n";
    }

    // 方式3: 使用ID直接查询
    $stmt = $pdo->prepare("SELECT id, name, url FROM jianhui_org_navigation WHERE id = ?");
    $stmt->execute([2]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\n方式3 (ID=2): " . ($result ? "找到 - Name: {$result['name']}" : "未找到") . "\n";

} catch (PDOException $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    exit(1);
}
