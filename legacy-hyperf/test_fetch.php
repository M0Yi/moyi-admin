<?php

// 测试抓取单篇文章并检查编码
$url = 'http://www.jianhuicishan.org/article/MechanismDynamics/detail/10974';

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

// 检查编码
preg_match('/<meta[^>]*charset=["\']?([^"\'\s>]+)/i', $html, $matches);
if (isset($matches[1])) {
    echo "Detected charset: {$matches[1]}\n\n";
}

// 尝试转换编码
$html = mb_convert_encoding($html, 'UTF-8', mb_detect_encoding($html, ['UTF-8', 'GBK', 'GB2312', 'ISO-8859-1'], true));

// 保存到文件以便查看
file_put_contents('/Users/moyi/moyi-admin/test_article.html', $html);
echo "HTML saved to test_article.html\n";

// 尝试提取文章内容
$dom = new DOMDocument();
@$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

$xpath = new DOMXPath($dom);

// 查找所有 div
$nodes = $xpath->query('//div');
echo "\nFound " . $nodes->length . " div elements\n";

// 查找可能包含文章内容的 div
$selectors = [
    'article-content' => '//div[contains(@class, "article-content")]',
    'content' => '//div[contains(@class, "content")]',
    'detail' => '//div[contains(@class, "detail")]',
    'main' => '//div[contains(@class, "main")]',
];

foreach ($selectors as $name => $selector) {
    $nodes = $xpath->query($selector);
    echo "{$name}: {$nodes->length} matches\n";
    if ($nodes->length > 0) {
        foreach ($nodes as $node) {
            $content = $dom->saveHTML($node);
            echo "  Length: " . strlen($content) . "\n";
            echo "  Preview: " . mb_substr($content, 0, 100) . "...\n";
        }
    }
}

// 查找所有文本内容
$allText = $xpath->query('//body//text()');
$textContent = '';
foreach ($allText as $textNode) {
    $textContent .= $textNode->textContent . ' ';
}
$textContent = preg_replace('/\s+/', ' ', $textContent);
$textContent = trim($textContent);

echo "\n=== Text Content ===\n";
echo "Length: " . strlen($textContent) . "\n";
echo "Preview: " . mb_substr($textContent, 0, 300) . "...\n";
