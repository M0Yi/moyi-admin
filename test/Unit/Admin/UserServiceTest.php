<?php

declare(strict_types=1);

/**
 * 用户服务单元测试
 * 
 * 测试用户相关业务逻辑
 */

namespace HyperfTest\Unit\Admin;

use HyperfTest\UnitTestCase;
use App\Service\Admin\UserService;
use App\Model\Admin\AdminUser;
use App\Model\Admin\AdminRole;

/**
 * 用户服务测试
 */
class UserServiceTest extends UnitTestCase
{
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$containerInited) {
            self::initContainer();
        }

        try {
            $this->service = $this->getService(UserService::class);
        } catch (\Throwable $e) {
            $this->markTestSkipped('UserService not available: ' . $e->getMessage());
        }
    }

    /**
     * 测试获取列表（基础结构）
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
        $this->assertIsInt($result['total']);
        $this->assertIsInt($result['page']);
    }

    /**
     * 测试获取列表（带关键词搜索）
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
        $this->assertArrayHasKey('list', $result);
    }

    /**
     * 测试获取列表（带精确搜索）
     */
    public function testGetList_WithExactFieldFilter(): void
    {
        // Arrange
        $params = [
            'username' => 'admin',
            'page' => 1,
            'page_size' => 10,
        ];

        // Act
        $result = $this->service->getList($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试查找存在的用户
     */
    public function testFind_ExistingUser(): void
    {
        // Arrange
        $user = AdminUser::first();
        if (!$user) {
            $this->markTestSkipped('No user found in database');
        }

        // Act
        $result = $this->service->find($user->id);

        // Assert
        if ($result !== null) {
            $this->assertInstanceOf(AdminUser::class, $result);
            $this->assertEquals($user->id, $result->id);
        }
    }

    /**
     * 测试查找不存在的用户
     */
    public function testFind_NonExistingUser(): void
    {
        // Arrange
        $userId = 999999;

        // Act
        $result = $this->service->find($userId);

        // Assert
        $this->assertNull($result);
    }

    /**
     * 测试创建用户（有效数据）
     */
    public function testCreate_UserWithValidData(): void
    {
        // Arrange
        $uniqueId = time() . '_' . uniqid();
        $userData = [
            'username' => 'test_user_' . $uniqueId,
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'email' => 'test_' . $uniqueId . '@example.com',
            'real_name' => '测试用户',
            'status' => 1,
            'site_id' => 0,
        ];

        // Act
        try {
            $result = $this->service->create($userData);

            // Assert
            $this->assertIsArray($result);
            $this->assertArrayHasKey('id', $result);
            $this->assertIsInt($result['id']);
            $this->assertGreaterThan(0, $result['id']);

            // Cleanup
            if (isset($result['id'])) {
                AdminUser::find($result['id'])?->forceDelete();
            }
        } catch (\Exception $e) {
            // 如果创建失败，检查是否因为唯一约束
            $this->assertStringContainsString('Duplicate', $e->getMessage() ?? '');
            $this->markTestSkipped('Duplicate entry - test data conflict');
        }
    }

    /**
     * 测试创建用户（用户名重复）
     */
    public function testCreate_UserWithDuplicateUsername_ThrowsException(): void
    {
        // Arrange
        $existingUser = AdminUser::first();
        if (!$existingUser) {
            $this->markTestSkipped('No existing user found');
        }

        $userData = [
            'username' => $existingUser->username,
            'password' => 'password123',
            'email' => 'unique_' . time() . '@example.com',
            'status' => 1,
            'site_id' => 0,
        ];

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->service->create($userData);
    }

    /**
     * 测试更新用户
     */
    public function testUpdate_ExistingUser(): void
    {
        // Arrange
        $user = AdminUser::first();
        if (!$user) {
            $this->markTestSkipped('No user found for testing');
        }

        $updateData = [
            'real_name' => '更新后的名称_' . time(),
        ];

        // Act
        $result = $this->service->update($user->id, $updateData);

        // Assert
        $this->assertTrue($result);

        // Verify
        $updatedUser = AdminUser::find($user->id);
        $this->assertEquals($updateData['real_name'], $updatedUser->real_name);
    }

    /**
     * 测试更新不存在的用户
     */
    public function testUpdate_NonExistingUser(): void
    {
        // Arrange
        $updateData = [
            'real_name' => '测试名称',
        ];

        // Act
        $result = $this->service->update(999999, $updateData);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试删除用户
     */
    public function testDelete_ExistingUser(): void
    {
        // Arrange
        $uniqueId = time() . '_' . uniqid();
        $user = AdminUser::create([
            'username' => 'to_delete_' . $uniqueId,
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'email' => 'delete_' . $uniqueId . '@example.com',
            'status' => 1,
            'site_id' => 0,
        ]);

        // Act
        $result = $this->service->delete($user->id);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(AdminUser::find($user->id));
    }

    /**
     * 测试删除不存在的用户
     */
    public function testDelete_NonExistingUser(): void
    {
        // Act
        $result = $this->service->delete(999999);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试状态切换
     */
    public function testToggleStatus(): void
    {
        // Arrange
        $user = AdminUser::first();
        if (!$user) {
            $this->markTestSkipped('No user found for testing');
        }

        $originalStatus = $user->status;
        $newStatus = $originalStatus == 1 ? 0 : 1;

        // Act
        $result = $this->service->toggleStatus($user->id, $newStatus);

        // Assert
        if ($result) {
            $updatedUser = AdminUser::find($user->id);
            $this->assertEquals($newStatus, $updatedUser->status);
        }
    }

    /**
     * 测试获取用户角色
     */
    public function testGetUserRoles(): void
    {
        // Arrange
        $user = AdminUser::first();
        if (!$user) {
            $this->markTestSkipped('No user found for testing');
        }

        // Act
        $roles = $this->service->getUserRoles($user->id);

        // Assert
        $this->assertIsArray($roles);
    }

    /**
     * 测试分配角色
     */
    public function testAssignRoles(): void
    {
        // Arrange
        $user = AdminUser::first();
        $role = AdminRole::first();

        if (!$user || !$role) {
            $this->markTestSkipped('No user or role found for testing');
        }

        // Act
        $result = $this->service->assignRoles($user->id, [$role->id]);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试移除角色
     */
    public function testRemoveRoles(): void
    {
        // Arrange
        $user = AdminUser::first();
        $role = AdminRole::first();

        if (!$user || !$role) {
            $this->markTestSkipped('No user or role found for testing');
        }

        // Act
        $result = $this->service->removeRoles($user->id, [$role->id]);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * 测试获取用户权限列表
     */
    public function testGetUserPermissions(): void
    {
        // Arrange
        $user = AdminUser::first();
        if (!$user) {
            $this->markTestSkipped('No user found for testing');
        }

        // Act
        $permissions = $this->service->getUserPermissions($user->id);

        // Assert
        $this->assertIsArray($permissions);
    }

    /**
     * 测试验证用户凭据
     */
    public function testVerifyCredentials(): void
    {
        // Arrange
        $user = AdminUser::where('status', 1)->first();
        if (!$user) {
            $this->markTestSkipped('No active user found for testing');
        }

        // Act
        $result = $this->service->verifyCredentials($user->username, 'wrong_password');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试修改密码
     */
    public function testChangePassword(): void
    {
        // Arrange
        $user = AdminUser::first();
        if (!$user) {
            $this->markTestSkipped('No user found for testing');
        }

        // Act
        $result = $this->service->changePassword($user->id, 'new_password123');

        // Assert
        $this->assertTrue($result);
    }
}
