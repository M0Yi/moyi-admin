<?php
require __DIR__ . '/vendor/autoload.php';

$host = 'postgres.orb.local';
$port = 5432;
$database = 'postgres';
$username = 'postgres';
$password = 'postgres';

try {
    $pdo = new PDO(
        "pgsql:host=" . $host . ";port=" . $port . ";dbname=" . $database,
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 查看导航菜单
    echo "=== 导航菜单 ===\n";
    $stmt = $pdo->query("SELECT id, name, slug, type, url FROM jianhui_org_navigation WHERE position='header' ORDER BY sort_order");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%d: %s (%s) - type: %s, url: %s\n",
            $row['id'], $row['name'], $row['slug'], $row['type'], $row['url']);
    }

    echo "\n=== 文章分类 ===\n";
    $stmt = $pdo->query("SELECT id, name, slug, type, parent_id FROM jianhui_org_categories WHERE type='article' AND parent_id=0 ORDER BY sort_order");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%d: %s (%s) - parent_id: %d\n",
            $row['id'], $row['name'], $row['slug'], $row['parent_id']);

        // 查看子分类
        $childStmt = $pdo->prepare("SELECT id, name, slug FROM jianhui_org_categories WHERE parent_id=? ORDER BY sort_order");
        $childStmt->execute([$row['id']]);
        while ($child = $childStmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  %d: %s (%s)\n", $child['id'], $child['name'], $child['slug'];
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
