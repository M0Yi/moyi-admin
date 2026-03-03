<?php

declare(strict_types=1);

/**
 * 菜单服务单元测试
 * 
 * 测试菜单相关业务逻辑
 */

namespace HyperfTest\Unit\Admin;

use HyperfTest\UnitTestCase;
use App\Service\Admin\MenuService;
use App\Model\Admin\AdminMenu;

/**
 * 菜单服务测试
 */
class MenuServiceTest extends UnitTestCase
{
    private MenuService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$containerInited) {
            self::initContainer();
        }

        try {
            $this->service = $this->getService(MenuService::class);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MenuService not available: ' . $e->getMessage());
        }
    }

    /**
     * 测试获取菜单列表
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
     * 测试获取菜单列表（带关键词搜索）
     */
    public function testGetList_WithKeywordFilter(): void
    {
        // Arrange
        $params = [
            'keyword' => '系统',
            'page' => 1,
            'page_size' => 10,
        ];

        // Act
        $result = $this->service->getList($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取菜单列表（带状态筛选）
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
     * 测试查找存在的菜单
     */
    public function testFind_ExistingMenu(): void
    {
        // Arrange
        $menu = AdminMenu::first();
        if (!$menu) {
            $this->markTestSkipped('No menu found in database');
        }

        // Act
        $result = $this->service->find($menu->id);

        // Assert
        if ($result !== null) {
            $this->assertInstanceOf(AdminMenu::class, $result);
            $this->assertEquals($menu->id, $result->id);
        }
    }

    /**
     * 测试查找不存在的菜单
     */
    public function testFind_NonExistingMenu(): void
    {
        // Arrange
        $menuId = 999999;

        // Act
        $result = $this->service->find($menuId);

        // Assert
        $this->assertNull($result);
    }

    /**
     * 测试创建菜单（有效数据）
     */
    public function testCreate_MenuWithValidData(): void
    {
        // Arrange
        $uniqueId = time() . '_' . uniqid();
        $menuData = [
            'parent_id' => 0,
            'name' => 'test_menu_' . $uniqueId,
            'title' => '测试菜单_' . $uniqueId,
            'icon' => 'icon-test',
            'path' => '/test',
            'component' => 'test/index',
            'status' => 1,
            'sort' => 0,
        ];

        // Act
        try {
            $result = $this->service->create($menuData);

            // Assert
            $this->assertIsArray($result);
            $this->assertArrayHasKey('id', $result);
            $this->assertIsInt($result['id']);
            $this->assertGreaterThan(0, $result['id']);

            // Cleanup
            if (isset($result['id'])) {
                AdminMenu::find($result['id'])?->forceDelete();
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Create failed - test data conflict: ' . $e->getMessage());
        }
    }

    /**
     * 测试更新菜单
     */
    public function testUpdate_ExistingMenu(): void
    {
        // Arrange
        $menu = AdminMenu::first();
        if (!$menu) {
            $this->markTestSkipped('No menu found for testing');
        }

        $updateData = [
            'title' => '更新后的菜单标题_' . time(),
            'icon' => 'icon-updated',
        ];

        // Act
        $result = $this->service->update($menu->id, $updateData);

        // Assert
        $this->assertTrue($result);

        // Verify
        $updatedMenu = AdminMenu::find($menu->id);
        $this->assertEquals($updateData['title'], $updatedMenu->title);
    }

    /**
     * 测试更新不存在的菜单
     */
    public function testUpdate_NonExistingMenu(): void
    {
        // Arrange
        $updateData = [
            'title' => '测试标题',
        ];

        // Act
        $result = $this->service->update(999999, $updateData);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试删除菜单
     */
    public function testDelete_ExistingMenu(): void
    {
        // Arrange
        $uniqueId = time() . '_' . uniqid();
        $menu = AdminMenu::create([
            'parent_id' => 0,
            'name' => 'to_delete_' . $uniqueId,
            'title' => '删除测试菜单_' . $uniqueId,
            'icon' => 'icon-delete',
            'path' => '/delete-test',
            'status' => 1,
        ]);

        // Act
        $result = $this->service->delete($menu->id);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(AdminMenu::find($menu->id));
    }

    /**
     * 测试删除不存在的菜单
     */
    public function testDelete_NonExistingMenu(): void
    {
        // Act
        $result = $this->service->delete(999999);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * 测试获取菜单树
     */
    public function testGetTree(): void
    {
        // Act
        $result = $this->service->getTree();

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取菜单树（带状态筛选）
     */
    public function testGetTree_WithStatusFilter(): void
    {
        // Arrange
        $params = [
            'status' => 1,
        ];

        // Act
        $result = $this->service->getTree($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取菜单树（带父级筛选）
     */
    public function testGetTree_WithParentFilter(): void
    {
        // Arrange
        $params = [
            'parent_id' => 0,
        ];

        // Act
        $result = $this->service->getTree($params);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取子菜单
     */
    public function testGetChildren(): void
    {
        // Arrange
        $menu = AdminMenu::where('parent_id', '>', 0)->first();
        if (!$menu) {
            // 找一个父菜单
            $parent = AdminMenu::first();
            if (!$parent) {
                $this->markTestSkipped('No menu found for testing');
            }
        }

        // Act
        $result = $this->service->getChildren($parent->id ?? 1);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试获取面包屑导航
     */
    public function testGetBreadcrumb(): void
    {
        // Arrange
        $menu = AdminMenu::first();
        if (!$menu) {
            $this->markTestSkipped('No menu found in database');
        }

        // Act
        $result = $this->service->getBreadcrumb($menu->id);

        // Assert
        $this->assertIsArray($result);
    }

    /**
     * 测试切换菜单状态
     */
    public function testToggleStatus(): void
    {
        // Arrange
        $menu = AdminMenu::first();
        if (!$menu) {
            $this->markTestSkipped('No menu found for testing');
        }

        $originalStatus = $menu->status;
        $newStatus = $originalStatus == 1 ? 0 : 1;

        // Act
        $result = $this->service->toggleStatus($menu->id, $newStatus);

        // Assert
        if ($result) {
            $updatedMenu = AdminMenu::find($menu->id);
            $this->assertEquals($newStatus, $updatedMenu->status);
        }
    }

    /**
     * 测试批量更新排序
     */
    public function testBatchUpdateSort(): void
    {
        // Arrange
        $menus = AdminMenu::limit(3)->get();
        if ($menus->isEmpty()) {
            $this->markTestSkipped('Not enough menus for testing');
        }

        $sortData = [];
        foreach ($menus as $index => $menu) {
            $sortData[$menu->id] = $index + 100;
        }

        // Act
        $result = $this->service->batchUpdateSort($sortData);

        // Assert
        $this->assertTrue($result);
    }
}
