<?php
// 直接使用 PDO 连接数据库
try {
    $dsn = "pgsql:host=postgres.orb.local;port=5432;dbname=postgres";
    $pdo = new PDO($dsn, 'postgres', 'postgres');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✓ 数据库连接成功\n\n";

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
            $active = $row['is_active'] == 't' || $row['is_active'] === true || $row['is_active'] == 1 ? '是' : '否';
            echo str_pad($row['id'], 5) .
                 str_pad($row['parent_id'], 8) .
                 str_pad($row['name'], 20) .
                 str_pad($row['url'], 30) .
                 str_pad($active, 8) .
                 str_pad($row['sort_order'], 8) . "\n";
        }

        // 检查顶级导航
        echo "\n顶级导航 (parent_id = 0):\n";
        $stmt = $pdo->query("SELECT * FROM jianhui_org_navigation WHERE parent_id = 0 ORDER BY sort_order");
        $rootNavs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rootNavs as $nav) {
            $childCountStmt = $pdo->prepare("SELECT COUNT(*) FROM jianhui_org_navigation WHERE parent_id = ?");
            $childCountStmt->execute([$nav['id']]);
            $childCount = $childCountStmt->fetchColumn();
            $active = $nav['is_active'] == 't' || $nav['is_active'] === true || $nav['is_active'] == 1 ? '是' : '否';
            echo "  - {$nav['name']} (ID: {$nav['id']}, 启用: {$active}, 子菜单: {$childCount})\n";
        }

    } else {
        echo "✗ 表 jianhui_org_navigation 不存在\n";
        echo "需要创建表\n";
    }

} catch (PDOException $e) {
    echo "数据库连接失败: " . $e->getMessage() . "\n";
}
