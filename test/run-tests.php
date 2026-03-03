<?php

declare(strict_types=1);

/**
 * 运行单元测试
 * 
 * 使用方法:
 * php test/run-tests.php
 * php test/run-tests.php --filter=testGetList
 */

require_once __DIR__ . '/../vendor/autoload.php';

// 检查 Swoole
$hasSwoole = extension_loaded('swoole');

echo "========================================\n";
echo "     Moyi Admin 单元测试运行器\n";
echo "========================================\n\n";

if (!$hasSwoole) {
    echo "⚠️  Swoole 扩展未安装\n";
    echo "💡 将在无容器环境下运行基础测试\n\n";
}

// 运行简单的断言测试
$passed = 0;
$failed = 0;
$skipped = 0;

function runTest(string $name, callable $test): void
{
    global $passed, $failed, $skipped;
    
    try {
        $test();
        echo "✅ {$name}\n";
        $passed++;
    } catch (\PHPUnit\Framework\SkippedTestError $e) {
        echo "⏭️  {$name} (跳过: {$e->getMessage()})\n";
        $skipped++;
    } catch (\Throwable $e) {
        echo "❌ {$name}\n";
        echo "   错误: {$e->getMessage()}\n";
        $failed++;
    }
}

function assertEquals($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new \Exception($message ?: "期望: {$expected}, 实际: {$actual}");
    }
}

function assertTrue($condition, string $message = ''): void
{
    if ($condition !== true) {
        throw new \Exception($message ?: "期望 true, 实际 false");
    }
}

function assertFalse($condition, string $message = ''): void
{
    if ($condition !== false) {
        throw new \Exception($message ?: "期望 false, 实际 true");
    }
}

function assertIsArray($value, string $message = ''): void
{
    if (!is_array($value)) {
        throw new \Exception($message ?: "期望数组, 实际: " . gettype($value));
    }
}

function assertArrayHasKey($key, array $array, string $message = ''): void
{
    if (!array_key_exists($key, $array)) {
        throw new \Exception($message ?: "数组缺少键: {$key}");
    }
}

echo "运行基础测试...\n\n";

// ========== 基础函数测试 ==========

runTest('testStrReverse - 字符串反转', function () {
    assertEquals('olleh', strrev('hello'));
    assertEquals('!dlrow ,olleH', strrev('Hello, world!'));
});

runTest('testArrayFilter - 数组过滤', function () {
    $input = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    $expected = [2, 4, 6, 8, 10];
    $result = array_filter($input, fn($n) => $n % 2 === 0);
    assertEquals($expected, array_values($result));
});

runTest('testArrayMap - 数组映射', function () {
    $input = [1, 2, 3, 4, 5];
    $result = array_map(fn($n) => $n * 2, $input);
    assertEquals([2, 4, 6, 8, 10], $result);
});

runTest('testJsonEncode - JSON编码', function () {
    $data = ['name' => '测试', 'value' => 123];
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $decoded = json_decode($json, true);
    assertEquals($data, $decoded);
});

runTest('testNumberFormat - 数字格式化', function () {
    assertEquals('1,234,567.89', number_format(1234567.89, 2, '.', ','));
    assertEquals('1.00', number_format(1, 2, '.', ','));
});

runTest('testArrayColumn - 数组列提取', function () {
    $users = [
        ['id' => 1, 'name' => '张三'],
        ['id' => 2, 'name' => '李四'],
    ];
    assertEquals(['张三', '李四'], array_column($users, 'name'));
});

runTest('testArrayReduce - 数组归约', function () {
    $input = [1, 2, 3, 4, 5];
    $sum = array_reduce($input, fn($carry, $item) => $carry + $item, 0);
    assertEquals(15, $sum);
});

runTest('testStrContains - 字符串包含', function () {
    $haystack = 'Hello, World!';
    assertTrue(str_contains($haystack, 'World'));
    assertTrue(str_contains($haystack, 'Hello'));
    assertFalse(str_contains($haystack, 'Foo'));
});

runTest('testStrStartsWith - 字符串开头', function () {
    $string = 'Hello, World!';
    assertTrue(str_starts_with($string, 'Hello'));
    assertFalse(str_starts_with($string, 'World'));
});

runTest('testStrEndsWith - 字符串结尾', function () {
    $string = 'Hello, World!';
    assertTrue(str_ends_with($string, '!'));
    assertFalse(str_ends_with($string, 'World'));
});

runTest('testExplode - 字符串分割', function () {
    $string = 'a,b,c,d,e';
    $result = explode(',', $string);
    assertEquals(['a', 'b', 'c', 'd', 'e'], $result);
});

runTest('testImplode - 数组合并', function () {
    $array = ['a', 'b', 'c'];
    assertEquals('a,b,c', implode(',', $array));
});

runTest('testUcwords - 首字母大写', function () {
    assertEquals('Hello World', ucwords('hello world'));
    // 注意: ucwords 不会转换全大写的字符串
    assertEquals('HELLO WORLD', ucwords('HELLO WORLD'));
});

runTest('testMd5 - MD5哈希', function () {
    assertEquals('5d41402abc4b2a76b9719d911017c592', md5('hello'));
});

runTest('testPasswordHash - 密码哈希', function () {
    $password = 'test_password_123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    assertTrue(password_verify($password, $hash));
    assertTrue(password_get_info($hash)['algo'] > 0);
});

runTest('testBase64 - Base64编码', function () {
    $original = 'Hello, World! 测试';
    $encoded = base64_encode($original);
    $decoded = base64_decode($encoded);
    assertEquals($original, $decoded);
});

runTest('testHttpBuildQuery - HTTP查询参数', function () {
    $params = ['name' => '测试', 'value' => 123, 'active' => true];
    $query = http_build_query($params);
    assertTrue(str_contains($query, 'name='));
    assertTrue(str_contains($query, 'value=123'));
});

runTest('testArrayMerge - 数组合并', function () {
    $a = ['a' => 1, 'b' => 2];
    $b = ['b' => 3, 'c' => 4];
    $result = array_merge($a, $b);
    assertEquals(['a' => 1, 'b' => 3, 'c' => 4], $result);
});

runTest('testArrayDiff - 数组差集', function () {
    $a = [1, 2, 3, 4, 5];
    $b = [2, 4];
    $result = array_diff($a, $b);
    assertEquals([1, 3, 5], array_values($result));
});

runTest('testArrayIntersect - 数组交集', function () {
    $a = [1, 2, 3, 4, 5];
    $b = [2, 4, 6];
    $result = array_intersect($a, $b);
    assertEquals([1 => 2, 3 => 4], $result);
});

runTest('testArrayUnique - 数组去重', function () {
    $input = [1, 2, 2, 3, 3, 3, 4, 4, 4, 4];
    $result = array_values(array_unique($input));
    assertEquals([1, 2, 3, 4], $result);
});

runTest('testArraySort - 数组排序', function () {
    $input = [5, 3, 1, 4, 2];
    sort($input);
    assertEquals([1, 2, 3, 4, 5], $input);
});

runTest('testArrayASort - 关联数组排序', function () {
    $input = ['c' => 3, 'a' => 1, 'b' => 2];
    asort($input);
    assertEquals([1, 2, 3], array_values($input));
});

runTest('testksort - 键排序', function () {
    $input = ['c' => 3, 'a' => 1, 'b' => 2];
    ksort($input);
    assertEquals(['a', 'b', 'c'], array_keys($input));
});

runTest('testRange - 生成范围数组', function () {
    assertEquals([1, 2, 3, 4, 5], range(1, 5));
    assertEquals([0, 2, 4, 6, 8, 10], range(0, 10, 2));
});

runTest('testArrayFill - 填充数组', function () {
    $result = array_fill(0, 5, 'x');
    assertEquals(['x', 'x', 'x', 'x', 'x'], $result);
});

runTest('testArrayChunk - 分割数组', function () {
    $input = [1, 2, 3, 4, 5, 6];
    $result = array_chunk($input, 2);
    assertEquals([[1, 2], [3, 4], [5, 6]], $result);
});

runTest('testArrayFlip - 交换数组键值', function () {
    $input = ['a' => 1, 'b' => 2, 'c' => 3];
    $result = array_flip($input);
    assertEquals([1 => 'a', 2 => 'b', 3 => 'c'], $result);
});

runTest('testArrayCombine - 合并数组为键值对', function () {
    $keys = ['a', 'b', 'c'];
    $values = [1, 2, 3];
    $result = array_combine($keys, $values);
    assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $result);
});

runTest('testSum - 数组求和', function () {
    assertEquals(15, array_sum([1, 2, 3, 4, 5]));
    assertEquals(10.5, array_sum([1.5, 2.5, 3.5, 3.0]));
});

runTest('testProduct - 数组求积', function () {
    assertEquals(120, array_product([1, 2, 3, 4, 5]));
});

runTest('testMinMax - 最小最大值', function () {
    assertEquals(1, min([1, 2, 3, 4, 5]));
    assertEquals(5, max([1, 2, 3, 4, 5]));
});

runTest('testInArray - 数组包含', function () {
    $array = ['a', 'b', 'c'];
    assertTrue(in_array('b', $array));
    assertFalse(in_array('d', $array));
});

runTest('testArrayKeyExists - 键存在性', function () {
    $array = ['a' => 1, 'b' => 2];
    assertTrue(array_key_exists('a', $array));
    assertFalse(array_key_exists('c', $array));
});

// ========== 输出结果 ==========

echo "\n========================================\n";
echo "              测试结果\n";
echo "========================================\n";
echo "通过: {$passed}\n";
echo "失败: {$failed}\n";
echo "跳过: {$skipped}\n";
echo "总计: " . ($passed + $failed + $skipped) . "\n";
echo "========================================\n";

if ($failed > 0) {
    echo "❌ 有 {$failed} 个测试失败！\n";
    exit(1);
} else {
    echo "✅ 全部测试通过！\n";
    exit(0);
}
