<?php

/**
 * 检查关于我们分类下的文章
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

    // 查询jianhui_org_articles表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'jianhui_org_articles'");
    $tableExists = $stmt->fetch();

    if (!$tableExists) {
        echo "⚠️  jianhui_org_articles 表不存在\n";
        echo "需要先创建文章表\n";
        exit(1);
    }

    echo "=== 关于我们分类文章统计 ===\n";

    // 获取关于我们所有子分类
    $stmt = $pdo->query("
        SELECT id, name, slug
        FROM jianhui_org_categories
        WHERE parent_id = 4
        ORDER BY sort_order
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($categories as $cat) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM jianhui_org_articles WHERE category_id = ?");
        $stmt->execute([$cat['id']]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        echo sprintf("%s (ID:%d, slug:%s): %d 篇文章\n",
            $cat['name'], $cat['id'], $cat['slug'], $count);
    }

    // 查看所有文章
    echo "\n=== 所有文章 ===\n";
    $stmt = $pdo->query("
        SELECT id, title, category_id, status
        FROM jianhui_org_articles
        ORDER BY id DESC
        LIMIT 10
    ");
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($articles) > 0) {
        echo "找到 " . count($articles) . " 篇文章:\n";
        foreach ($articles as $art) {
            echo sprintf("  [%d] %s (分类ID: %d, 状态: %s)\n",
                $art['id'], $art['title'], $art['category_id'], $art['status']);
        }
    } else {
        echo "⚠️  数据库中没有文章\n";
    }

} catch (PDOException $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    exit(1);
}
