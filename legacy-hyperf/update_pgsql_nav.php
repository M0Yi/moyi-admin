<?php
/**
 * 更新导航菜单：将"新闻动态"改为"项目动态"
 */

// PostgreSQL连接配置
$host = 'postgres.orb.local';
$port = 5432;
$database = 'postgres';
$username = 'postgres';
$password = 'postgres';

echo "尝试连接PostgreSQL...\n";
echo "Host: {$host}:{$port}\n";
echo "Database: {$database}\n\n";

try {
    // 尝试使用不同的连接方式
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    echo "DSN: {$dsn}\n";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);

    echo "✓ 数据库连接成功！\n\n";

    // 1. 查看当前导航菜单
    echo "=== 当前导航菜单 ===\n";
    $stmt = $pdo->query("SELECT id, name, slug, type, url FROM jianhui_org_navigation WHERE position='header' ORDER BY sort_order");
    $navItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($navItems as $item) {
        echo sprintf("%d: %s (%s) -> %s\n", $item['id'], $item['name'], $item['slug'], $item['url']);
    }

    echo "\n";

    // 2. 查找"新闻"相关的导航项
    $stmt = $pdo->prepare("SELECT id, name, slug, url FROM jianhui_org_navigation WHERE position='header' AND (name LIKE '%新闻%' OR slug LIKE '%news%') LIMIT 1");
    $stmt->execute();
    $newsNav = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($newsNav) {
        echo "=== 找到导航项 ===\n";
        echo "ID: {$newsNav['id']}\n";
        echo "名称: {$newsNav['name']}\n";
        echo "标识: {$newsNav['slug']}\n";
        echo "URL: {$newsNav['url']}\n\n";

        // 3. 更新导航项
        echo "=== 更新导航项 ===\n";
        $stmt = $pdo->prepare("UPDATE jianhui_org_navigation SET name=?, slug=?, type=?, url=?, updated_at=NOW() WHERE id=?");
        $result = $stmt->execute(['项目动态', 'project_progress', 'link', '/projects', $newsNav['id']]);

        if ($result) {
            echo "✓ 已更新导航项！\n";
            echo "  新名称: 项目动态\n";
            echo "  新标识: project_progress\n";
            echo "  新URL: /projects\n";
        } else {
            echo "✗ 更新失败\n";
        }
    } else {
        echo "=== 未找到导航项 ===\n";
        echo "未找到包含'新闻'的导航项，将创建新导航项...\n";

        // 获取当前最大的sort_order
        $stmt = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) as max_sort FROM jianhui_org_navigation WHERE position='header'");
        $maxSort = $stmt->fetch(PDO::FETCH_ASSOC)['max_sort'];

        // 插入新的导航项
        $stmt = $pdo->prepare("INSERT INTO jianhui_org_navigation (name, slug, type, url, position, sort_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, true, NOW(), NOW())");
        $result = $stmt->execute(['项目动态', 'project_progress', 'link', '/projects', 'header', $maxSort + 1]);

        if ($result) {
            echo "✓ 已创建新导航项！\n";
        } else {
            echo "✗ 创建失败\n";
        }
    }

    echo "\n=== 更新后的导航菜单 ===\n";
    $stmt = $pdo->query("SELECT id, name, slug, type, url FROM jianhui_org_navigation WHERE position='header' ORDER BY sort_order");
    $navItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($navItems as $item) {
        echo sprintf("%d: %s (%s) -> %s\n", $item['id'], $item['name'], $item['slug'], $item['url']);
    }

    echo "\n✓ 操作完成！\n";

} catch (PDOException $e) {
    echo "✗ 数据库连接失败！\n";
    echo "错误: " . $e->getMessage() . "\n";
    echo "\n请检查：\n";
    echo "1. PostgreSQL服务是否运行\n";
    echo "2. 主机地址是否正确: {$host}\n";
    echo "3. 数据库名称是否正确: {$database}\n";
    echo "4. 用户名密码是否正确\n";
}
