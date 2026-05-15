<?php

// 测试数据库连接和内容存储
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

// 测试更新一篇文章
$testContent = "任前公示

2016-09-01

按照建辉基金会章程规定，经过第一届第一次理事会选举通过，同意聘任黄晓丹同志为深圳市建辉慈善基金会秘书长。现对黄晓丹同志进行任前公示。黄晓丹：2015年8月1日-至今，筹备深圳市建辉慈善基金会；2012年11月1日--2015年4月1日，深圳市龙越慈善基金会，任秘书长；2006年6月--2012年10月，创立长沙市芙蓉区连泰电子商行，法人；2001年5月--2006年4月，湖南联通公司，大客户经理；2000年1月--2001年4月，广州润讯通信公司，营销经理。";

echo "测试内容:\n";
echo $testContent . "\n\n";

// 更新数据库
try {
    $stmt = $pdo->prepare("UPDATE jianhui_org_articles SET content = ?, updated_at = NOW() WHERE id = 1");
    $stmt->execute([$testContent]);
    echo "✓ 更新成功\n\n";
} catch (PDOException $e) {
    echo "✗ 更新失败: " . $e->getMessage() . "\n\n";
}

// 验证更新
$stmt = $pdo->query("SELECT id, title, content FROM jianhui_org_articles WHERE id = 1");
$article = $stmt->fetch(PDO::FETCH_ASSOC);

echo "验证结果:\n";
echo "ID: {$article['id']}\n";
echo "标题: {$article['title']}\n";
echo "内容长度: " . strlen($article['content']) . " 字符\n";
echo "内容预览:\n" . mb_substr($article['content'], 0, 200) . "...\n";
