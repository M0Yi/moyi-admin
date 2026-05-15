<?php

/**
 * 验证抓取的文章内容
 */

// 数据库配置
$dbConfig = [
    'host' => 'postgres.orb.local',
    'port' => 5432,
    'dbname' => 'postgres',
    'user' => 'postgres',
    'password' => 'postgres',
];

try {
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ 数据库连接成功\n\n";
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage() . "\n");
}

// 统计
$stmt = $pdo->query("SELECT COUNT(*) as total FROM jianhui_org_articles");
$total = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as has_content FROM jianhui_org_articles WHERE content IS NOT NULL AND TRIM(content) != ''");
$hasContent = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as empty_content FROM jianhui_org_articles WHERE content IS NULL OR TRIM(content) = ''");
$emptyContent = $stmt->fetchColumn();

echo "=== 文章统计 ===\n";
echo "总文章数: {$total}\n";
echo "有内容: {$hasContent}\n";
echo "无内容: {$emptyContent}\n\n";

// 显示几篇有内容的文章
echo "=== 有内容的文章示例 ===\n";
$stmt = $pdo->query("
    SELECT id, title, LENGTH(content) as content_length, LEFT(content, 200) as content_preview 
    FROM jianhui_org_articles 
    WHERE content IS NOT NULL AND TRIM(content) != '' 
    ORDER BY id 
    LIMIT 5
");
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($articles as $article) {
    echo "ID {$article['id']}: {$article['title']}\n";
    echo "内容长度: {$article['content_length']} 字符\n";
    echo "内容预览:\n{$article['content_preview']}\n";
    echo str_repeat("-", 80) . "\n";
}

// 显示几篇无内容的文章
echo "\n=== 无内容的文章示例 ===\n";
$stmt = $pdo->query("
    SELECT id, title, source_url 
    FROM jianhui_org_articles 
    WHERE content IS NULL OR TRIM(content) = '' 
    ORDER BY id 
    LIMIT 5
");
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($articles as $article) {
    echo "ID {$article['id']}: {$article['title']}\n";
    echo "URL: {$article['source_url']}\n";
    echo str_repeat("-", 80) . "\n";
}
