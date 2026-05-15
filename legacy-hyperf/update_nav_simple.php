<?php
/**
 * 更新导航菜单：将"新闻动态"改为"项目动态" (简化版)
 */

$host = 'postgres.orb.local';
$port = 5432;
$database = 'postgres';
$username = 'postgres';
$password = 'postgres';

try {
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$database}",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 更新导航菜单 ===\n\n";

    // 1. 查看当前导航菜单
    echo "1. 当前导航菜单:\n";
    $stmt = $pdo->query("SELECT id, name, slug, type, url, sort_order FROM jianhui_org_navigation WHERE position='header' ORDER BY sort_order");
    $navItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($navItems as $item) {
        echo sprintf("  %d: %s (%s) - %s\n", $item['id'], $item['name'], $item['slug'], $item['url']);
    }

    echo "\n";

    // 2. 查找"新闻动态"或"news"相关的导航项
    $stmt = $pdo->query("SELECT * FROM jianhui_org_navigation WHERE position='header' AND (name LIKE '%新闻%' OR slug LIKE '%news%') LIMIT 1");
    $newsNav = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($newsNav) {
        echo "2. 找到导航项: {$newsNav['name']} (ID: {$newsNav['id']})\n";

        // 3. 更新导航项
        $stmt = $pdo->prepare("UPDATE jianhui_org_navigation SET name=?, slug=?, type=?, url=?, updated_at=NOW() WHERE id=?");
        $stmt->execute(['项目动态', 'project_progress', 'link', '/projects', $newsNav['id']]);

        echo "3. ✓ 已更新为: 项目动态 (/projects)\n";
    } else {
        echo "2. ✗ 未找到'新闻动态'导航项\n";
        echo "3. 尝试创建新的导航项...\n";

        // 获取当前最大的sort_order
        $stmt = $pdo->query("SELECT MAX(sort_order) as max_sort FROM jianhui_org_navigation WHERE position='header'");
        $maxSort = $stmt->fetch(PDO::FETCH_ASSOC)['max_sort'] ?: 0;

        // 插入新的导航项
        $stmt = $pdo->prepare("INSERT INTO jianhui_org_navigation (name, slug, type, url, position, sort_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, true, NOW(), NOW())");
        $stmt->execute(['项目动态', 'project_progress', 'link', '/projects', 'header', $maxSort + 1]);

        echo "3. ✓ 已创建新导航项\n";
    }

    echo "\n4. 更新后的导航菜单:\n";
    $stmt = $pdo->query("SELECT id, name, slug, type, url, sort_order FROM jianhui_org_navigation WHERE position='header' ORDER BY sort_order");
    $navItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($navItems as $item) {
        echo sprintf("  %d: %s (%s) - %s\n", $item['id'], $item['name'], $item['slug'], $item['url']);
    }

    echo "\n✓ 导航菜单更新完成！\n";

} catch (PDOException $e) {
    echo "✗ 数据库错误: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
}
