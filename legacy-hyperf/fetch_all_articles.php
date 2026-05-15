<?php

/**
 * 建辉文章内容抓取脚本
 * 从 www.jianhuicishan.org 抓取文章内容并保存到数据库
 */

// 配置
$config = [
    'db' => [
        'host' => 'postgres.orb.local',
        'port' => 5432,
        'dbname' => 'postgres',
        'user' => 'postgres',
        'password' => 'postgres',
    ],
    'request_delay' => 500000, // 请求间隔（微秒），0.5秒
    'log_file' => '/Users/moyi/moyi-admin/fetch_articles.log',
    'progress_file' => '/Users/moyi/moyi-admin/fetch_progress.json',
];

// 连接数据库
try {
    $dsn = "pgsql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['dbname']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    logMessage("数据库连接成功");
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage() . "\n");
}

// 检查空内容的文章
$stmt = $pdo->query("SELECT COUNT(*) FROM jianhui_org_articles WHERE content IS NULL OR TRIM(content) = ''");
$emptyCount = $stmt->fetchColumn();
logMessage("空内容文章数量: {$emptyCount}");

if ($emptyCount == 0) {
    logMessage("所有文章都有内容，无需抓取");
    exit(0);
}

// 获取所有空内容的文章
$stmt = $pdo->query("
    SELECT id, title, source_url 
    FROM jianhui_org_articles 
    WHERE content IS NULL OR TRIM(content) = '' 
    ORDER BY id
");
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

logMessage("共找到 " . count($articles) . " 篇空内容文章");

// 加载进度
$progress = loadProgress();
$lastId = $progress['last_id'] ?? 0;
$successCount = $progress['success_count'] ?? 0;
$failCount = $progress['fail_count'] ?? 0;
$failList = $progress['fail_list'] ?? [];

// 过滤已处理的文章
if ($lastId > 0) {
    $articles = array_filter($articles, function($article) use ($lastId) {
        return $article['id'] > $lastId;
    });
    logMessage("从 ID {$lastId} 继续，剩余 " . count($articles) . " 篇文章");
}

// 开始抓取
logMessage("开始抓取文章内容");
$totalCount = count($articles);

foreach ($articles as $index => $article) {
    $current = $index + 1;
    $articleId = $article['id'];
    
    logMessage("[{$current}/{$totalCount}] ID {$articleId}: {$article['title']}");
    
    $content = fetchArticleContent($article['source_url']);
    
    if ($content && !empty(trim($content))) {
        // 更新数据库
        try {
            $updateStmt = $pdo->prepare("UPDATE jianhui_org_articles SET content = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$content, $articleId]);
            logMessage("  ✓ 更新成功，长度: " . strlen($content) . " 字符");
            $successCount++;
        } catch (PDOException $e) {
            logMessage("  ✗ 更新失败: " . $e->getMessage());
            $failCount++;
            $failList[] = $article;
        }
    } else {
        logMessage("  ✗ 抓取内容为空");
        $failCount++;
        $failList[] = $article;
    }
    
    // 保存进度
    saveProgress([
        'last_id' => $articleId,
        'success_count' => $successCount,
        'fail_count' => $failCount,
        'fail_list' => $failList,
        'last_update' => date('Y-m-d H:i:s'),
    ]);
    
    // 避免请求过快
    usleep($config['request_delay']);
}

// 完成
logMessage("\n=== 抓取完成 ===");
logMessage("成功: {$successCount} 篇");
logMessage("失败: {$failCount} 篇");

if (count($failList) > 0) {
    logMessage("\n失败文章列表:");
    foreach ($failList as $fail) {
        logMessage("  - ID {$fail['id']}: {$fail['title']} ({$fail['source_url']})");
    }
    
    // 保存失败列表
    file_put_contents('/Users/moyi/moyi-admin/failed_articles.json', json_encode($failList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    logMessage("\n失败文章列表已保存到 failed_articles.json");
}

// 清除进度文件
if (file_exists($config['progress_file'])) {
    unlink($config['progress_file']);
}

/**
 * 抓取文章内容
 */
function fetchArticleContent($url) {
    if (empty($url)) {
        return null;
    }
    
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
    
    $content = extractArticleContent($html);
    return $content;
}

/**
 * 从 HTML 中提取文章内容
 */
function extractArticleContent($html) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    
    $xpath = new DOMXPath($dom);
    
    // 优先查找 article-detail 类
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
            $contentNode = $contentNodes->item(0);
            // 保留 HTML 格式
            $content = $dom->saveHTML($contentNode);
            $content = cleanHtmlContent($content);
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
        
        return trim($fullContent);
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
    $allowedTags = '<p><br><br/><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><a><img><ul><ol><li><blockquote>';
    $html = strip_tags($html, $allowedTags);
    
    // 清理多余的空白
    $html = preg_replace('/\s+/', ' ', $html);
    $html = trim($html);
    
    return $html;
}

/**
 * 记录日志
 */
function logMessage($message) {
    global $config;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$message}\n";
    echo $logLine;
    file_put_contents($config['log_file'], $logLine, FILE_APPEND);
}

/**
 * 保存进度
 */
function saveProgress($progress) {
    global $config;
    file_put_contents($config['progress_file'], json_encode($progress, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * 加载进度
 */
function loadProgress() {
    global $config;
    if (file_exists($config['progress_file'])) {
        $content = file_get_contents($config['progress_file']);
        return json_decode($content, true) ?: [];
    }
    return [];
}
