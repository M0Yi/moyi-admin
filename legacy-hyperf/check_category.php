<?php
$host = 'mysql8.orb.local';
$dbname = 'moyi';
$username = 'root';
$password = '821121';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT id, name, slug, is_single_article, linked_article_id FROM jianhui_org_categories WHERE slug='who_we_are'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo "Found category:\n";
        print_r($result);
    } else {
        echo "Category not found\n";
    }

    // Check all columns
    $stmt = $pdo->query("DESCRIBE jianhui_org_categories");
    echo "\nTable structure:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
