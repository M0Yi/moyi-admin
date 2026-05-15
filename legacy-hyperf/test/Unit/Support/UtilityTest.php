<?php

declare(strict_types=1);

/**
 * 简单单元测试示例
 * 
 * 不依赖 Swoole 的基础测试
 */

namespace HyperfTest\Unit\Support;

use PHPUnit\Framework\TestCase;

/**
 * 工具函数测试
 */
class UtilityTest extends TestCase
{
    /**
     * 测试字符串反转
     */
    public function testStrReverse(): void
    {
        // Arrange
        $input = 'hello';
        $expected = 'olleh';

        // Act
        $result = strrev($input);

        // Assert
        $this->assertEquals($expected, $result);
    }

    /**
     * 测试数组过滤
     */
    public function testArrayFilter(): void
    {
        // Arrange
        $input = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $expected = [2, 4, 6, 8, 10];

        // Act
        $result = array_filter($input, fn($n) => $n % 2 === 0);

        // Assert
        $this->assertEquals($expected, array_values($result));
    }

    /**
     * 测试 JSON 编码
     */
    public function testJsonEncode(): void
    {
        // Arrange
        $data = [
            'name' => '测试',
            'value' => 123,
            'active' => true,
        ];

        // Act
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertJsonStringEqualsJsonString(json_encode($data), $json);
        $this->assertEquals($data, $decoded);
    }

    /**
     * 测试数组转换
     */
    public function testArrayColumn(): void
    {
        // Arrange
        $users = [
            ['id' => 1, 'name' => '张三'],
            ['id' => 2, 'name' => '李四'],
            ['id' => 3, 'name' => '王五'],
        ];

        // Act
        $names = array_column($users, 'name');

        // Assert
        $this->assertEquals(['张三', '李四', '王五'], $names);
    }

    /**
     * 测试数字格式化
     */
    public function testNumberFormat(): void
    {
        // Arrange
        $number = 1234567.89;

        // Act
        $formatted = number_format($number, 2, '.', ',');

        // Assert
        $this->assertEquals('1,234,567.89', $formatted);
    }

    /**
     * 测试日期格式化
     */
    public function testDateFormat(): void
    {
        // Arrange
        $date = '2024-01-15 10:30:00';

        // Act
        $timestamp = strtotime($date);
        $formatted = date('Y年m月d日', $timestamp);

        // Assert
        $this->assertEquals('2024年01月15日', $formatted);
    }
}
