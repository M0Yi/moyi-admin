<?php
// 直接使用 PDO 连接数据库
try {
    // 从配置中读取数据库信息
    $config = require __DIR__ . '/config/autoload/databases.php';
    $pgConfig = $config['default']['pgsql'];

    $dsn = "pgsql:host={$pgConfig['host']};port={$pgConfig['port']};dbname={$pgConfig['database']}";
    $pdo = new PDO($dsn, $pgConfig['username'], $pgConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "数据库连接成功\n\n";

    // 检查表是否存在
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'jianhui_org_navigation'");
    if ($stmt->fetch()) {
        echo "✓ 表 jianhui_org_navigation 存在\n\n";

        // 查询所有数据
        $stmt = $pdo->query("SELECT id, parent_id, name, url, is_active, sort_order FROM jianhui_org_navigation ORDER BY sort_order");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "导航数据数量: " . count($rows) . "\n\n";
        echo "导航列表:\n";
        echo str_pad("ID", 5) . str_pad("父ID", 8) . str_pad("名称", 20) . str_pad("URL", 30) . str_pad("启用", 8) . str_pad("排序", 8) . "\n";
        echo str_repeat("-", 100) . "\n";

        foreach ($rows as $row) {
            $active = $row['is_active'] ? '是' : '否';
            echo str_pad($row['id'], 5) .
                 str_pad($row['parent_id'], 8) .
                 str_pad($row['name'], 20) .
                 str_pad($row['url'], 30) .
                 str_pad($active, 8) .
                 str_pad($row['sort_order'], 8) . "\n";
        }

        // 检查顶级导航
        echo "\n顶级导航 (parent_id = 0):\n";
        $stmt = $pdo->query("SELECT * FROM jianhui_org_navigation WHERE parent_id = 0 AND is_active = true ORDER BY sort_order");
        $rootNavs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rootNavs as $nav) {
            $childCountStmt = $pdo->prepare("SELECT COUNT(*) FROM jianhui_org_navigation WHERE parent_id = ?");
            $childCountStmt->execute([$nav['id']]);
            $childCount = $childCountStmt->fetchColumn();
            echo "  - {$nav['name']} (ID: {$nav['id']}, 子菜单: {$childCount})\n";
        }

    } else {
        echo "✗ 表 jianhui_org_navigation 不存在\n";
        echo "需要创建表\n";
    }

} catch (PDOException $e) {
    echo "数据库连接失败: " . $e->getMessage() . "\n";
}
