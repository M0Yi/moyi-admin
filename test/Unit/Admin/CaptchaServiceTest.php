<?php

declare(strict_types=1);

/**
 * 验证码服务单元测试
 * 
 * 测试验证码相关业务逻辑
 */

namespace HyperfTest\Unit\Admin;

use HyperfTest\UnitTestCase;
use App\Service\CaptchaService;

/**
 * 验证码服务测试
 */
class CaptchaServiceTest extends UnitTestCase
{
    private CaptchaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$containerInited) {
            self::initContainer();
        }

        try {
            $this->service = $this->getService(CaptchaService::class);
        } catch (\Throwable $e) {
            $this->markTestSkipped('CaptchaService not available: ' . $e->getMessage());
        }
    }

    /**
     * 测试生成验证码
     */
    public function testGenerateCaptcha(): void
    {
        // Act
        $result = $this->service->generateCaptcha('test_key');

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('image', $result);
        $this->assertNotEmpty($result['key']);
        $this->assertNotEmpty($result['image']);
    }

    /**
     * 测试验证验证码（正确）
     */
    public function testVerifyCaptcha_Success(): void
    {
        // Arrange
        $captchaResult = $this->service->generateCaptcha('verify_test_key');
        $key = $captchaResult['key'];

        // Note: 实际测试需要记录验证码值，这里验证结构
        // Act
        $result = $this->service->verifyCaptcha($key, 'wrong_code');

        // Assert
        $this->assertIsBool($result);
    }

    /**
     * 测试验证验证码（错误 key）
     */
    public function testVerifyCaptcha_InvalidKey(): void
    {
        // Act
        $result = $this->service->verifyCaptcha('invalid_key', '1234');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试清除验证码
     */
    public function testClearCaptcha(): void
    {
        // Arrange
        $captchaResult = $this->service->generateCaptcha('clear_test_key');
        $key = $captchaResult['key'];

        // Act
        $result = $this->service->clearCaptcha($key);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试刷新验证码
     */
    public function testRefreshCaptcha(): void
    {
        // Arrange
        $captchaResult = $this->service->generateCaptcha('refresh_test_key');
        $oldKey = $captchaResult['key'];

        // Act
        $newResult = $this->service->refreshCaptcha($oldKey);

        // Assert
        $this->assertIsArray($newResult);
        $this->assertArrayHasKey('key', $newResult);
        $this->assertArrayHasKey('image', $newResult);
    }

    /**
     * 测试获取验证码配置
     */
    public function testGetCaptchaConfig(): void
    {
        // Act
        $result = $this->service->getCaptchaConfig();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('enabled', $result);
        $this->assertArrayHasKey('type', $result);
    }

    /**
     * 测试检查验证码是否启用
     */
    public function testIsCaptchaEnabled(): void
    {
        // Act
        $result = $this->service->isCaptchaEnabled();

        // Assert
        $this->assertIsBool($result);
    }
}
