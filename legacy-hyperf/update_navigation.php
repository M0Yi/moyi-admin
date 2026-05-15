<?php
require_once 'vendor/autoload.php';

$env = require_once 'config/autoload/env.php';
$host = $env->get('DB_HOST', 'mysql8.orb.local');
$port = $env->get('DB_PORT', '3306');
$database = $env->get('DB_DATABASE', 'moyi');
$username = $env->get('DB_USERNAME', 'root');
$password = $env->get('DB_PASSWORD', '821121');

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database successfully.\n";

    // 查找"新闻中心"菜单项
    $stmt = $pdo->prepare("SELECT id, name, url FROM jianhui_org_navigation WHERE name LIKE '%新闻%' OR name LIKE '%文章%' OR url LIKE '%articles%'");
    $stmt->execute();
    $newsItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($newsItems) . " navigation items:\n";
    foreach ($newsItems as $item) {
        echo "  ID: {$item['id']}, Name: {$item['name']}, URL: {$item['url']}\n";
    }

    // 更新"新闻中心"为"项目动态"
    $stmt = $pdo->prepare("UPDATE jianhui_org_navigation SET name = '项目动态', url = '/articles' WHERE name LIKE '%新闻%' OR url LIKE '%articles%'");
    $affectedRows = $stmt->execute();

    if ($affectedRows) {
        echo "\nUpdated $affectedRows navigation item(s).\n";

        // 显示更新后的结果
        $stmt = $pdo->query("SELECT id, name, url FROM jianhui_org_navigation WHERE url = '/articles'");
        $updatedItem = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($updatedItem) {
            echo "Updated item:\n";
            echo "  ID: {$updatedItem['id']}\n";
            echo "  Name: {$updatedItem['name']}\n";
            echo "  URL: {$updatedItem['url']}\n";
        }
    } else {
        echo "\nNo items were updated. The menu item might not exist or already has the correct name.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
