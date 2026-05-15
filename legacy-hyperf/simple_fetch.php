<?php

/**
 * 简化的文章抓取脚本
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

// 测试抓取一篇文章
$url = 'http://www.jianhuicishan.org/article/MechanismDynamics/detail/10974';
echo "测试抓取: {$url}\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: {$httpCode}\n";
echo "HTML Length: " . strlen($html) . "\n\n";

// 使用正则表达式提取文章内容
$pattern = '/<div class="article-detail"[^>]*>(.*?)<\/div>\s*<div class="side-content"/s';
if (preg_match($pattern, $html, $matches)) {
    $articleHtml = $matches[1];
    echo "找到文章内容\n";
    echo "原始 HTML 长度: " . strlen($articleHtml) . "\n\n";
    
    // 提取标题
    $titlePattern = '/<div class="tit"[^>]*>(.*?)<\/div>/s';
    if (preg_match($titlePattern, $articleHtml, $titleMatches)) {
        $title = strip_tags($titleMatches[1]);
        echo "标题: {$title}\n";
    }
    
    // 提取时间
    $timePattern = '/<div class="time"[^>]*>(.*?)<\/div>/s';
    if (preg_match($timePattern, $articleHtml, $timeMatches)) {
        $time = strip_tags($timeMatches[1]);
        echo "时间: {$time}\n";
    }
    
    // 提取描述
    $descPattern = '/<div class="desc"[^>]*>(.*?)<\/div>/s';
    if (preg_match($descPattern, $articleHtml, $descMatches)) {
        $desc = strip_tags($descMatches[1]);
        echo "描述: {$desc}\n";
    }
    
    // 提取正文内容
    $contentPattern = '/<div class="content"[^>]*>(.*?)<\/div>/s';
    if (preg_match($contentPattern, $articleHtml, $contentMatches)) {
        $content = $contentMatches[1];
        // 清理 HTML
        $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content);
        $content = preg_replace('/<!--.*?-->/s', '', $content);
        
        // 保留基本标签
        $allowedTags = '<p><br><br/><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><a><img><ul><ol><li><blockquote>';
        $content = strip_tags($content, $allowedTags);
        
        // 清理空白
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        echo "\n正文内容:\n{$content}\n";
        echo "\n正文长度: " . strlen($content) . " 字符\n";
    }
    
    // 组合完整内容
    $fullContent = '';
    if (!empty($title)) {
        $fullContent .= "<h1>{$title}</h1>\n\n";
    }
    if (!empty($time)) {
        $fullContent .= "<p class=\"time\">{$time}</p>\n\n";
    }
    if (!empty($desc)) {
        $fullContent .= "<p class=\"description\">{$desc}</p>\n\n";
    }
    if (!empty($content)) {
        $fullContent .= $content;
    }
    
    echo "\n=== 完整内容 ===\n";
    echo $fullContent . "\n";
    echo "\n完整内容长度: " . strlen($fullContent) . " 字符\n";
    
    // 更新数据库
    echo "\n是否更新数据库？(yes/no): ";
    $handle = fopen("php://stdin", "r");
    $answer = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($answer) == 'yes') {
        try {
            $stmt = $pdo->prepare("UPDATE jianhui_org_articles SET content = ?, updated_at = NOW() WHERE id = 1");
            $stmt->execute([$fullContent]);
            echo "✓ 更新成功\n";
        } catch (PDOException $e) {
            echo "✗ 更新失败: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "未找到文章内容\n";
}
