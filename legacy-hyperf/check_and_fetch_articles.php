<?php

/**
 * 检查并抓取建辉文章内容的脚本
 * 针对 www.jianhuicishan.org 网站优化
 */

// 数据库配置
$dbConfig = [
    'host' => 'postgres.orb.local',
    'port' => 5432,
    'dbname' => 'postgres',
    'user' => 'postgres',
    'password' => 'postgres',
];

// 连接数据库
try {
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ 数据库连接成功\n\n";
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage() . "\n");
}

// 检查空内容的文章
echo "=== 检查空内容文章 ===\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM jianhui_org_articles WHERE content IS NULL OR TRIM(content) = ''");
$emptyCount = $stmt->fetchColumn();
echo "空内容文章数量: {$emptyCount}\n\n";

if ($emptyCount == 0) {
    echo "所有文章都有内容，无需抓取。\n";
    exit(0);
}

// 获取所有空内容的文章
echo "=== 获取空内容文章列表 ===\n";
$stmt = $pdo->query("
    SELECT id, title, source_url 
    FROM jianhui_org_articles 
    WHERE content IS NULL OR TRIM(content) = '' 
    ORDER BY id
");
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "共找到 " . count($articles) . " 篇空内容文章\n\n";

// 测试抓取第一篇文章
echo "=== 测试抓取第一篇文章 ===\n";
$testArticle = $articles[0];
echo "文章ID: {$testArticle['id']}\n";
echo "标题: {$testArticle['title']}\n";
echo "URL: {$testArticle['source_url']}\n\n";

$content = fetchArticleContent($testArticle['source_url']);
if ($content) {
    echo "✓ 成功抓取内容，长度: " . strlen($content) . " 字符\n";
    echo "内容预览:\n" . mb_substr($content, 0, 300) . "...\n\n";
} else {
    echo "✗ 抓取内容失败\n\n";
}

// 询问是否继续抓取所有文章
echo "是否继续抓取所有文章？(yes/no): ";
$handle = fopen("php://stdin", "r");
$answer = trim(fgets($handle));
fclose($handle);

if (strtolower($answer) != 'yes') {
    echo "已取消抓取\n";
    exit(0);
}

// 开始抓取所有文章
echo "\n=== 开始抓取所有文章 ===\n";
$successCount = 0;
$failCount = 0;
$failList = [];

foreach ($articles as $index => $article) {
    $current = $index + 1;
    echo "[{$current}/{$emptyCount}] 抓取: {$article['title']}\n";
    
    $content = fetchArticleContent($article['source_url']);
    
    if ($content && !empty(trim($content))) {
        // 更新数据库
        try {
            $updateStmt = $pdo->prepare("UPDATE jianhui_org_articles SET content = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$content, $article['id']]);
            echo "  ✓ 更新成功，长度: " . strlen($content) . " 字符\n";
            $successCount++;
        } catch (PDOException $e) {
            echo "  ✗ 更新失败: " . $e->getMessage() . "\n";
            $failCount++;
            $failList[] = $article;
        }
    } else {
        echo "  ✗ 抓取内容为空\n";
        $failCount++;
        $failList[] = $article;
    }
    
    // 避免请求过快
    usleep(500000); // 0.5秒延迟
}

echo "\n=== 抓取完成 ===\n";
echo "成功: {$successCount} 篇\n";
echo "失败: {$failCount} 篇\n";

if (count($failList) > 0) {
    echo "\n失败文章列表:\n";
    foreach ($failList as $fail) {
        echo "  - ID {$fail['id']}: {$fail['title']} ({$fail['source_url']})\n";
    }
}

// 保存失败列表到文件
if (count($failList) > 0) {
    file_put_contents('/Users/moyi/moyi-admin/failed_articles.json', json_encode($failList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n失败文章列表已保存到 failed_articles.json\n";
}

/**
 * 抓取文章内容
 */
function fetchArticleContent($url) {
    if (empty($url)) {
        return null;
    }
    
    // 使用 curl 获取页面内容
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode != 200 || !$html) {
        return null;
    }
    
    // 解析 HTML 获取文章内容
    $content = extractArticleContent($html);
    
    return $content;
}

/**
 * 从 HTML 中提取文章内容
 * 针对 www.jianhuicishan.org 网站结构优化
 */
function extractArticleContent($html) {
    // 使用 DOMDocument 解析 HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    
    $xpath = new DOMXPath($dom);
    
    // 优先查找 article-detail 类（这是该网站的文章内容容器）
    $articleDetail = $xpath->query('//div[contains(@class, "article-detail")]');
    if ($articleDetail->length > 0) {
        $node = $articleDetail->item(0);
        
        // 提取标题
        $titleNodes = $xpath->query('.//div[contains(@class, "tit")]', $node);
        $title = '';
        if ($titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent);
        }
        
        // 提取时间
        $timeNodes = $xpath->query('.//div[contains(@class, "time")]', $node);
        $time = '';
        if ($timeNodes->length > 0) {
            $time = trim($timeNodes->item(0)->textContent);
        }
        
        // 提取描述
        $descNodes = $xpath->query('.//div[contains(@class, "desc")]', $node);
        $desc = '';
        if ($descNodes->length > 0) {
            $desc = trim($descNodes->item(0)->textContent);
        }
        
        // 提取正文内容
        $contentNodes = $xpath->query('.//div[contains(@class, "content")]', $node);
        $content = '';
        if ($contentNodes->length > 0) {
            $content = $dom->saveHTML($contentNodes->item(0));
            $content = cleanHtmlContent($content);
        }
        
        // 组合完整内容
        $fullContent = '';
        if (!empty($title)) {
            $fullContent .= $title . "\n\n";
        }
        if (!empty($time)) {
            $fullContent .= $time . "\n\n";
        }
        if (!empty($desc)) {
            $fullContent .= $desc . "\n\n";
        }
        if (!empty($content)) {
            $fullContent .= $content;
        }
        
        return trim($fullContent);
    }
    
    // 如果没有找到 article-detail，尝试其他选择器
    $selectors = [
        '//div[contains(@class, "content")]',
        '//article',
        '//div[contains(@class, "main")]',
    ];
    
    foreach ($selectors as $selector) {
        $nodes = $xpath->query($selector);
        if ($nodes->length > 0) {
            $content = '';
            foreach ($nodes as $node) {
                $nodeContent = cleanHtmlContent($dom->saveHTML($node));
                if (!empty(trim($nodeContent)) && strlen($nodeContent) > 100) {
                    $content .= $nodeContent . "\n\n";
                }
            }
            if (!empty(trim($content))) {
                return trim($content);
            }
        }
    }
    
    return null;
}

/**
 * 清理 HTML 内容
 */
function cleanHtmlContent($html) {
    // 移除 script 和 style 标签
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
    
    // 移除 HTML 注释
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    
    // 保留基本格式标签
    $allowedTags = '<p><br><br/><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><a><img><ul><ol><li><blockquote><div><span><table><tr><td><th><thead><tbody>';
    $html = strip_tags($html, $allowedTags);
    
    // 清理多余的空白
    $html = preg_replace('/\s+/', ' ', $html);
    $html = trim($html);
    
    return $html;
}
