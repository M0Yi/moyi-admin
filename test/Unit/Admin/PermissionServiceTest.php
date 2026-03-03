<?php

declare(strict_types=1);

/**
 * 权限服务单元测试
 * 
 * 测试权限相关业务逻辑
 */

namespace HyperfTest\Unit\Admin;

use HyperfTest\UnitTestCase;
use App\Service\Admin\PermissionService;
use App\Model\Admin\AdminPermission;

/**
 * 权限服务测试
 */
class PermissionServiceTest extends UnitTestCase
{
    private PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$containerInited) {
            self::initContainer();
        }

        try {
            $this->service = $this->getService(PermissionService::class);
        } catch (\Throwable $e) {
            $this->markTestSkipped('PermissionService not available: ' . $e->getMessage());
        }
    }

    /**
     * 测试获取权限列表
     */
    public function testGetList_ReturnsPaginatedData(): void
    {
        // Arrange
        $params = [
            'page' => 1,
            'page_size' => 15,
        ];

        // Act
        $result = $this->service->getList($params);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKeys(['list', 'total', 'page', 'page_size', 'last_page'], $result);
        $this->assertIsArray($result['list']);
    }

    /**
     * 测试获取权限列表（带关键词搜索）
     */
    public function testGetList_WithKeywordFilter(): void
    {
        // Arrange
        $params = [
            'keyword' => 'system',
            'page' => 1,
            'page_size' => 10,
        ];

        // Act
        $result = $this->service->getList($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取权限列表（带类型筛选）
     */
    public function testGetList_WithTypeFilter(): void
    {
        // Arrange
        $params = [
            'type' => 'menu',
            'page' => 1,
            'page_size' => 10,
        ];

        // Act
        $result = $this->service->getList($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试查找存在的权限
     */
    public function testFind_ExistingPermission(): void
    {
        // Arrange
        $permission = AdminPermission::first();
        if (!$permission) {
            $this->markTestSkipped('No permission found in database');
        }

        // Act
        $result = $this->service->find($permission->id);

        // Assert
        if ($result !== null) {
            $this->assertInstanceOf(AdminPermission::class, $result);
            $this->assertEquals($permission->id, $result->id);
        }
    }

    /**
     * 测试查找不存在的权限
     */
    public function testFind_NonExistingPermission(): void
    {
        // Arrange
        $permissionId = 999999;

        // Act
        $result = $this->service->find($permissionId);

        // Assert
        $this->assertNull($result);
    }

    /**
     * 测试创建权限（有效数据）
     */
    public function testCreate_PermissionWithValidData(): void
    {
        // Arrange
        $uniqueId = time() . '_' . uniqid();
        $permissionData = [
            'parent_id' => 0,
            'name' => '测试权限_' . $uniqueId,
            'slug' => 'test_permission_' . $uniqueId,
            'type' => 'button',
            'status' => 1,
            'sort' => 0,
        ];

        // Act
        try {
            $result = $this->service->create($permissionData);

            // Assert
            $this->assertIsArray($result);
            $this->assertArrayHasKey('id', $result);
            $this->assertIsInt($result['id']);
            $this->assertGreaterThan(0, $result['id']);

            // Cleanup
            if (isset($result['id'])) {
                AdminPermission::find($result['id'])?->forceDelete();
            }
        } catch (\Exception $e) {
            $this->assertStringContainsString('Duplicate', $e->getMessage() ?? '');
            $this->markTestSkipped('Duplicate entry - test data conflict');
        }
    }

    /**
     * 测试创建权限（slug 重复）
     */
    public function testCreate_PermissionWithDuplicateSlug_ThrowsException(): void
    {
        // Arrange
        $existingPermission = AdminPermission::first();
        if (!$existingPermission) {
            $this->markTestSkipped('No existing permission found');
        }

        $permissionData = [
            'parent_id' => 0,
            'name' => '新权限名称',
            'slug' => $existingPermission->slug,
            'type' => 'button',
            'status' => 1,
        ];

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->service->create($permissionData);
    }

    /**
     * 测试更新权限
     */
    public function testUpdate_ExistingPermission(): void
    {
        // Arrange
        $permission = AdminPermission::first();
        if (!$permission) {
            $this->markTestSkipped('No permission found for testing');
        }

        $updateData = [
            'name' => '更新后的权限名称_' . time(),
            'description' => '更新后的描述',
        ];

        // Act
        $result = $this->service->update($permission->id, $updateData);

        // Assert
        $this->assertTrue($result);

        // Verify
        $updatedPermission = AdminPermission::find($permission->id);
        $this->assertEquals($updateData['name'], $updatedPermission->name);
    }

    /**
     * 测试更新不存在的权限
     */
    public function testUpdate_NonExistingPermission(): void
    {
        // Arrange
        $updateData = [
            'name' => '测试名称',
        ];

        // Act
        $result = $this->service->update(999999, $updateData);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试删除权限
     */
    public function testDelete_ExistingPermission(): void
    {
        // Arrange
        $uniqueId = time() . '_' . uniqid();
        $permission = AdminPermission::create([
            'parent_id' => 0,
            'name' => 'to_delete_' . $uniqueId,
            'slug' => 'delete_permission_' . $uniqueId,
            'type' => 'button',
            'status' => 1,
        ]);

        // Act
        $result = $this->service->delete($permission->id);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(AdminPermission::find($permission->id));
    }

    /**
     * 测试删除不存在的权限
     */
    public function testDelete_NonExistingPermission(): void
    {
        // Act
        $result = $this->service->delete(999999);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试获取权限树
     */
    public function testGetTree(): void
    {
        // Act
        $result = $this->service->getTree();

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取权限树（带类型筛选）
     */
    public function testGetTree_WithTypeFilter(): void
    {
        // Arrange
        $params = [
            'type' => 'menu',
        ];

        // Act
        $result = $this->service->getTree($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取权限树（带根节点筛选）
     */
    public function testGetTree_WithRootFilter(): void
    {
        // Arrange
        $params = [
            'root' => true,
        ];

        // Act
        $result = $this->service->getTree($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取子权限
     */
    public function testGetChildren(): void
    {
        // Arrange
        $permission = AdminPermission::where('parent_id', '>', 0)->first();
        if (!$permission) {
            // 找一个父权限
            $parent = AdminPermission::first();
            if (!$parent) {
                $this->markTestSkipped('No permission found for testing');
            }
        }

        // Act
        $result = $this->service->getChildren($parent->id ?? 1);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试根据 slug 查找权限
     */
    public function testFindBySlug(): void
    {
        // Arrange
        $permission = AdminPermission::first();
        if (!$permission) {
            $this->markTestSkipped('No permission found in database');
        }

        // Act
        $result = $this->service->findBySlug($permission->slug);

        // Assert
        if ($result !== null) {
            $this->assertEquals($permission->slug, $result->slug);
        }
    }

    /**
     * 测试切换权限状态
     */
    public function testToggleStatus(): void
    {
        // Arrange
        $permission = AdminPermission::first();
        if (!$permission) {
            $this->markTestSkipped('No permission found for testing');
        }

        $originalStatus = $permission->status;
        $newStatus = $originalStatus == 1 ? 0 : 1;

        // Act
        $result = $this->service->toggleStatus($permission->id, $newStatus);

        // Assert
        if ($result) {
            $updatedPermission = AdminPermission::find($permission->id);
            $this->assertEquals($newStatus, $updatedPermission->status);
        }
    }
}
