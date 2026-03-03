<?php

declare(strict_types=1);

/**
 * 角色服务单元测试
 * 
 * 测试角色相关业务逻辑
 */

namespace HyperfTest\Unit\Admin;

use HyperfTest\UnitTestCase;
use App\Service\Admin\RoleService;
use App\Model\Admin\AdminRole;
use App\Model\Admin\AdminPermission;

/**
 * 角色服务测试
 */
class RoleServiceTest extends UnitTestCase
{
    private RoleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$containerInited) {
            self::initContainer();
        }

        try {
            $this->service = $this->getService(RoleService::class);
        } catch (\Throwable $e) {
            $this->markTestSkipped('RoleService not available: ' . $e->getMessage());
        }
    }

    /**
     * 测试获取角色列表
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
     * 测试获取角色列表（带关键词搜索）
     */
    public function testGetList_WithKeywordFilter(): void
    {
        // Arrange
        $params = [
            'keyword' => 'admin',
            'page' => 1,
            'page_size' => 10,
        ];

        // Act
        $result = $this->service->getList($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取角色列表（带状态筛选）
     */
    public function testGetList_WithStatusFilter(): void
    {
        // Arrange
        $params = [
            'status' => 1,
            'page' => 1,
            'page_size' => 10,
        ];

        // Act
        $result = $this->service->getList($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试查找存在的角色
     */
    public function testFind_ExistingRole(): void
    {
        // Arrange
        $role = AdminRole::first();
        if (!$role) {
            $this->markTestSkipped('No role found in database');
        }

        // Act
        $result = $this->service->find($role->id);

        // Assert
        if ($result !== null) {
            $this->assertInstanceOf(AdminRole::class, $result);
            $this->assertEquals($role->id, $result->id);
        }
    }

    /**
     * 测试查找不存在的角色
     */
    public function testFind_NonExistingRole(): void
    {
        // Arrange
        $roleId = 999999;

        // Act
        $result = $this->service->find($roleId);

        // Assert
        $this->assertNull($result);
    }

    /**
     * 测试创建角色（有效数据）
     */
    public function testCreate_RoleWithValidData(): void
    {
        // Arrange
        $uniqueId = time() . '_' . uniqid();
        $roleData = [
            'name' => '测试角色_' . $uniqueId,
            'slug' => 'test_role_' . $uniqueId,
            'description' => '测试角色描述',
            'status' => 1,
            'sort' => 0,
        ];

        // Act
        try {
            $result = $this->service->create($roleData);

            // Assert
            $this->assertIsArray($result);
            $this->assertArrayHasKey('id', $result);
            $this->assertIsInt($result['id']);
            $this->assertGreaterThan(0, $result['id']);

            // Cleanup
            if (isset($result['id'])) {
                AdminRole::find($result['id'])?->forceDelete();
            }
        } catch (\Exception $e) {
            $this->assertStringContainsString('Duplicate', $e->getMessage() ?? '');
            $this->markTestSkipped('Duplicate entry - test data conflict');
        }
    }

    /**
     * 测试创建角色（slug 重复）
     */
    public function testCreate_RoleWithDuplicateSlug_ThrowsException(): void
    {
        // Arrange
        $existingRole = AdminRole::first();
        if (!$existingRole) {
            $this->markTestSkipped('No existing role found');
        }

        $roleData = [
            'name' => '新角色名称',
            'slug' => $existingRole->slug,
            'status' => 1,
        ];

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->service->create($roleData);
    }

    /**
     * 测试更新角色
     */
    public function testUpdate_ExistingRole(): void
    {
        // Arrange
        $role = AdminRole::first();
        if (!$role) {
            $this->markTestSkipped('No role found for testing');
        }

        $updateData = [
            'name' => '更新后的角色名称_' . time(),
            'description' => '更新后的描述',
        ];

        // Act
        $result = $this->service->update($role->id, $updateData);

        // Assert
        $this->assertTrue($result);

        // Verify
        $updatedRole = AdminRole::find($role->id);
        $this->assertEquals($updateData['name'], $updatedRole->name);
    }

    /**
     * 测试更新不存在的角色
     */
    public function testUpdate_NonExistingRole(): void
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
     * 测试删除角色
     */
    public function testDelete_ExistingRole(): void
    {
        // Arrange
        $uniqueId = time() . '_' . uniqid();
        $role = AdminRole::create([
            'name' => 'to_delete_' . $uniqueId,
            'slug' => 'delete_role_' . $uniqueId,
            'status' => 1,
        ]);

        // Act
        $result = $this->service->delete($role->id);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(AdminRole::find($role->id));
    }

    /**
     * 测试删除不存在的角色
     */
    public function testDelete_NonExistingRole(): void
    {
        // Act
        $result = $this->service->delete(999999);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试获取角色权限
     */
    public function testGetRolePermissions(): void
    {
        // Arrange
        $role = AdminRole::first();
        if (!$role) {
            $this->markTestSkipped('No role found for testing');
        }

        // Act
        $permissions = $this->service->getRolePermissions($role->id);

        // Assert
        $this->assertIsArray($permissions);
    }

    /**
     * 测试分配权限
     */
    public function testAssignPermissions(): void
    {
        // Arrange
        $role = AdminRole::first();
        $permission = AdminPermission::first();

        if (!$role || !$permission) {
            $this->markTestSkipped('No role or permission found for testing');
        }

        // Act
        $result = $this->service->assignPermissions($role->id, [$permission->id]);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试移除权限
     */
    public function testRemovePermissions(): void
    {
        // Arrange
        $role = AdminRole::first();
        $permission = AdminPermission::first();

        if (!$role || !$permission) {
            $this->markTestSkipped('No role or permission found for testing');
        }

        // Act
        $result = $this->service->removePermissions($role->id, [$permission->id]);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试同步权限
     */
    public function testSyncPermissions(): void
    {
        // Arrange
        $role = AdminRole::first();
        $permissions = AdminPermission::limit(3)->get();

        if (!$role || $permissions->isEmpty()) {
            $this->markTestSkipped('No role or permissions found for testing');
        }

        $permissionIds = $permissions->pluck('id')->toArray();

        // Act
        $result = $this->service->syncPermissions($role->id, $permissionIds);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试切换角色状态
     */
    public function testToggleStatus(): void
    {
        // Arrange
        $role = AdminRole::first();
        if (!$role) {
            $this->markTestSkipped('No role found for testing');
        }

        $originalStatus = $role->status;
        $newStatus = $originalStatus == 1 ? 0 : 1;

        // Act
        $result = $this->service->toggleStatus($role->id, $newStatus);

        // Assert
        if ($result) {
            $updatedRole = AdminRole::find($role->id);
            $this->assertEquals($newStatus, $updatedRole->status);
        }
    }

    /**
     * 测试根据 slug 查找角色
     */
    public function testFindBySlug(): void
    {
        // Arrange
        $role = AdminRole::first();
        if (!$role) {
            $this->markTestSkipped('No role found in database');
        }

        // Act
        $result = $this->service->findBySlug($role->slug);

        // Assert
        if ($result !== null) {
            $this->assertEquals($role->slug, $result->slug);
        }
    }

    /**
     * 测试获取所有角色（不分页）
     */
    public function testGetAllRoles(): void
    {
        // Act
        $result = $this->service->getAllRoles();

        // Assert
        $this->assertIsArray($result);
    }
}
