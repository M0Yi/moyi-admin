<?php

declare(strict_types=1);

/**
 * 插件商店服务单元测试
 * 
 * 测试插件商店相关业务逻辑
 */

namespace HyperfTest\Unit\Addons;

use HyperfTest\UnitTestCase;
use Addons\AddonsStore\Service\AddonsStoreService;
use Addons\AddonsStore\Model\AddonsStoreAddon;

/**
 * 插件商店服务测试
 */
class AddonsStoreServiceTest extends UnitTestCase
{
    private AddonsStoreService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$containerInited) {
            self::initContainer();
        }

        try {
            $this->service = $this->getService(AddonsStoreService::class);
        } catch (\Throwable $e) {
            $this->markTestSkipped('AddonsStoreService not available: ' . $e->getMessage());
        }
    }

    /**
     * 测试获取插件列表
     */
    public function testGetAddonList_ReturnsArray(): void
    {
        // Act
        $result = $this->service->getAddonList();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('last_page', $result);
        $this->assertIsArray($result['data']);
    }

    /**
     * 测试获取插件列表（带关键词搜索）
     */
    public function testGetAddonList_WithKeyword(): void
    {
        // Arrange
        $params = [
            'keyword' => 'test',
            'page' => 1,
            'per_page' => 10,
        ];

        // Act
        $result = $this->service->getAddonList($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取插件列表（带分类筛选）
     */
    public function testGetAddonList_WithCategoryFilter(): void
    {
        // Arrange
        $params = [
            'category' => 'content',
            'page' => 1,
            'per_page' => 10,
        ];

        // Act
        $result = $this->service->getAddonList($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取插件列表（带状态筛选）
     */
    public function testGetAddonList_WithStatusFilter(): void
    {
        // Arrange
        $params = [
            'status' => 1,
            'page' => 1,
            'per_page' => 10,
        ];

        // Act
        $result = $this->service->getAddonList($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取插件详情（存在的插件）
     */
    public function testGetAddonDetail_ExistingAddon(): void
    {
        // Arrange
        $addon = AddonsStoreAddon::first();
        if (!$addon) {
            $this->markTestSkipped('No addon found for testing');
        }

        // Act
        $result = $this->service->getAddonDetail($addon->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('addon', $result);
        $this->assertArrayHasKey('versions', $result);
    }

    /**
     * 测试获取插件详情（不存在的插件）
     */
    public function testGetAddonDetail_NonExistingAddon(): void
    {
        // Act
        $result = $this->service->getAddonDetail(999999);

        // Assert
        $this->assertNull($result);
    }

    /**
     * 测试获取分类列表
     */
    public function testGetCategories_ReturnsDefinedCategories(): void
    {
        // Act
        $result = $this->service->getCategories();

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('general', $result);
        $this->assertArrayHasKey('admin', $result);
        $this->assertArrayHasKey('content', $result);
    }

    /**
     * 测试插件 ID 转目录名
     */
    public function testAddonIdToDirName(): void
    {
        // Arrange
        $testCases = [
            'addons_store' => 'AddonsStore',
            'user_manager' => 'UserManager',
            'simple_blog' => 'SimpleBlog',
        ];

        // Act & Assert
        foreach ($testCases as $input => $expected) {
            $result = AddonsStoreService::addonIdToDirName($input);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }

    /**
     * 测试目录名转插件 ID
     */
    public function testDirNameToAddonId(): void
    {
        // Arrange
        $testCases = [
            'AddonsStore' => 'addons_store',
            'UserManager' => 'user_manager',
            'SimpleBlog' => 'simple_blog',
        ];

        // Act & Assert
        foreach ($testCases as $input => $expected) {
            $result = AddonsStoreService::dirNameToAddonId($input);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }

    /**
     * 测试获取插件版本列表
     */
    public function testGetAddonVersions(): void
    {
        // Arrange
        $addon = AddonsStoreAddon::first();
        if (!$addon) {
            $this->markTestSkipped('No addon found for testing');
        }

        // Act
        $result = $this->service->getAddonVersions($addon->id);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取所有版本列表
     */
    public function testGetAllVersions(): void
    {
        // Arrange
        $params = [
            'page' => 1,
            'per_page' => 10,
        ];

        // Act
        $result = $this->service->getAllVersions($params);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
    }

    /**
     * 测试获取插件评价
     */
    public function testGetAddonReviews(): void
    {
        // Arrange
        $addon = AddonsStoreAddon::first();
        if (!$addon) {
            $this->markTestSkipped('No addon found for testing');
        }

        // Act
        $result = $this->service->getAddonReviews($addon->id);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取用户插件列表
     */
    public function testGetUserAddons(): void
    {
        // Arrange
        $userId = 1;

        // Act
        $result = $this->service->getUserAddons($userId);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取下载统计
     */
    public function testGetDownloadStats(): void
    {
        // Act
        $result = $this->service->getDownloadStats();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('today', $result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('popular_addons', $result);
    }

    /**
     * 测试获取表单字段配置
     */
    public function testGetFormFields(): void
    {
        // Act
        $result = $this->service->getFormFields('create');

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * 测试获取表单字段配置（编辑场景）
     */
    public function testGetFormFieldsForEdit(): void
    {
        // Arrange
        $addon = AddonsStoreAddon::first();
        if (!$addon) {
            $this->markTestSkipped('No addon found for testing');
        }

        // Act
        $result = $this->service->getFormFields('edit', $addon->toArray());

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }
}
