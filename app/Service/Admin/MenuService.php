<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminMenu;
use Hyperf\DbConnection\Db;

/**
 * 菜单管理服务
 */
class MenuService
{
    /**
     * 获取菜单列表（树形结构）
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getTree(array $params = []): array
    {
        $siteId = $params['site_id'] ?? site_id() ?? 0;
        $parentId = $params['parent_id'] ?? 0;
        $status = $params['status'] ?? null;

        $query = AdminMenu::query()
            ->where('site_id', $siteId)
            ->where('parent_id', $parentId)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc');

        // 状态筛选
        if ($status !== null) {
            $query->where('status', $status);
        }

        $menus = $query->get();

        $tree = [];
        foreach ($menus as $menu) {
            $item = $menu->toArray();
            
            // 递归获取子菜单
            $children = $this->getTree([
                'site_id' => $siteId,
                'parent_id' => $menu->id,
                'status' => $status,
            ]);

            if (!empty($children)) {
                $item['children'] = $children;
            }

            $tree[] = $item;
        }

        return $tree;
    }

    /**
     * 获取扁平化菜单列表（带层级缩进）
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getList(array $params = []): array
    {
        $siteId = $params['site_id'] ?? site_id() ?? 0;

        $query = AdminMenu::query()
            ->where('site_id', $siteId)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc');

        $menus = $query->get();

        // 构建扁平化列表（带层级）
        return $this->buildFlatList($menus->toArray(), 0, 0);
    }

    /**
     * 构建扁平化列表（递归）
     *
     * @param array $menus 菜单数组
     * @param int $parentId 父级ID
     * @param int $level 层级
     * @return array
     */
    protected function buildFlatList(array $menus, int $parentId, int $level): array
    {
        $list = [];

        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $menu['level'] = $level;
                $menu['title_indent'] = str_repeat('　', $level) . $menu['title'];
                $list[] = $menu;

                // 递归获取子菜单
                $children = $this->buildFlatList($menus, $menu['id'], $level + 1);
                $list = array_merge($list, $children);
            }
        }

        return $list;
    }

    /**
     * 获取菜单详情
     *
     * @param int $id 菜单ID
     * @return AdminMenu
     * @throws BusinessException
     */
    public function getById(int $id): AdminMenu
    {
        $siteId = site_id() ?? 0;
        
        $menu = AdminMenu::query()
            ->where('id', $id)
            ->where('site_id', $siteId)
            ->first();

        if (!$menu) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '菜单不存在');
        }

        return $menu;
    }

    /**
     * 获取表单字段配置（用于 UniversalFormRenderer）
     *
     * @param string $scene 场景：create 或 edit
     * @param AdminMenu|null $menu 菜单对象（编辑时传入）
     * @return array
     */
    public function getFormFields(string $scene = 'create', ?AdminMenu $menu = null): array
    {
        $parentOptions = $this->getParentOptions($menu?->id);
        
        // 构建父级菜单选项（添加顶级选项）
        $parentSelectOptions = [
            ['value' => '0', 'label' => '顶级菜单']
        ];
        foreach ($parentOptions as $option) {
            $parentSelectOptions[] = [
                'value' => (string)$option['value'],
                'label' => $option['label']
            ];
        }

        $defaultType = $menu?->type ?? 'menu';
        $defaultPath = $menu?->path ?? '';
        $isLinkType = $defaultType === 'link';

        $fields = [
            [
                'name' => 'name',
                'label' => '菜单名称',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：system.menus',
                'help' => '用于标识菜单',
                'default' => $menu?->name ?? '',
                'col' => 'col-12 col-md-6', // 半宽
            ],
            [
                'name' => 'title',
                'label' => '菜单标题',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：菜单管理',
                'default' => $menu?->title ?? '',
                'col' => 'col-12 col-md-6', // 半宽
            ],
            [
                'name' => 'parent_id',
                'label' => '父级菜单',
                'type' => 'select',
                'required' => false,
                'options' => $parentSelectOptions,
                'default' => $menu?->parent_id ?? '0',
                'col' => 'col-12 col-md-6', // 半宽
            ],
            [
                'name' => 'type',
                'label' => '菜单类型',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'menu', 'label' => '菜单'],
                    ['value' => 'link', 'label' => '外链'],
                    ['value' => 'group', 'label' => '分组'],
                    ['value' => 'divider', 'label' => '分割线'],
                ],
                'default' => $defaultType,
                'help' => '选择菜单类型',
                'col' => 'col-12 col-md-6', // 半宽
            ],
            [
                'name' => 'icon',
                'label' => '图标',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'bi bi-icon',
                'default' => $menu?->icon ?? '',
                'data-menu-type' => 'menu,link,group',
                'col' => 'col-12 col-md-6', // 半宽
            ],
            [
                'name' => 'target',
                'label' => '打开方式',
                'type' => 'select',
                'required' => false,
                'options' => [
                    ['value' => '_self', 'label' => '当前窗口'],
                    ['value' => '_blank', 'label' => '新窗口'],
                ],
                'default' => $menu?->target ?? '_self',
                'data-menu-type' => 'menu,link',
                'col' => 'col-12 col-md-6', // 半宽
            ],
            [
                'name' => 'path',
                'label' => '路由路径',
                'type' => 'text',
                'required' => false,
                'placeholder' => '例如：/system/menus',
                'help' => '菜单对应的路由路径',
                'default' => $isLinkType ? '' : $defaultPath,
                'data-menu-type' => 'menu',
                'col' => 'col-12 col-md-6', // 半宽
            ],
            [
                'name' => 'linkPath',
                'label' => '外链地址',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'https://...',
                'help' => '完整的外链地址',
                'default' => $isLinkType ? $defaultPath : '',
                'data-menu-type' => 'link',
                'col' => 'col-12', // 全宽（外链地址可能较长）
            ],
            [
                'name' => 'component',
                'label' => '组件路径',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'admin/system/menus/index',
                'help' => '前端组件路径（可选）',
                'default' => $menu?->component ?? '',
                'data-menu-type' => 'menu',
                'col' => 'col-12 col-md-6', // 半宽
            ],
            [
                'name' => 'permission',
                'label' => '权限标识',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'system.menus.view',
                'help' => '用于权限控制，格式：模块.功能.操作',
                'default' => $menu?->permission ?? '',
                'data-menu-type' => 'menu',
                'col' => 'col-12 col-md-6', // 半宽
            ],
            [
                'name' => 'redirect',
                'label' => '重定向路径',
                'type' => 'text',
                'required' => false,
                'placeholder' => '/system/menus/list',
                'help' => '访问该菜单时自动跳转的路径（可选）',
                'default' => $menu?->redirect ?? '',
                'data-menu-type' => 'menu',
                'col' => 'col-12 col-md-6', // 半宽
            ],
            [
                'name' => 'sort',
                'label' => '排序',
                'type' => 'number',
                'required' => false,
                'min' => 0,
                'default' => $menu?->sort ?? 0,
                'help' => '越小越前',
                'col' => 'col-12 col-md-3', // 1/4宽（switch 类型默认也是这个宽度）
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $menu?->status ?? '1',
                'col' => 'col-12 col-md-3', // 1/4宽（switch 类型默认也是这个宽度）
            ],
            [
                'name' => 'visible',
                'label' => '是否可见',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $menu?->visible ?? '1',
                'col' => 'col-12 col-md-3', // 1/4宽（switch 类型默认也是这个宽度）
            ],
            [
                'name' => 'cache',
                'label' => '是否缓存',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $menu?->cache ?? '1',
                'col' => 'col-12 col-md-3', // 1/4宽（switch 类型默认也是这个宽度）
            ],
            [
                'type' => 'card',
                'title' => '徽章设置',
                'collapsible' => true,
                'collapsed' => false,
                'fields' => [
                    [
                        'name' => 'badge',
                        'label' => '徽章文本',
                        'type' => 'text',
                        'required' => false,
                        'placeholder' => '例如：NEW',
                        'default' => $menu?->badge ?? '',
                        'col' => 'col-12 col-md-6', // 半宽
                    ],
                    [
                        'name' => 'badge_type',
                        'label' => '徽章类型',
                        'type' => 'select',
                        'required' => false,
                        'options' => [
                            ['value' => '', 'label' => '无'],
                            ['value' => 'primary', 'label' => '主要'],
                            ['value' => 'success', 'label' => '成功'],
                            ['value' => 'warning', 'label' => '警告'],
                            ['value' => 'danger', 'label' => '危险'],
                            ['value' => 'info', 'label' => '信息'],
                        ],
                        'default' => $menu?->badge_type ?? '',
                        'col' => 'col-12 col-md-6', // 半宽
                    ],
                ],
            ],
            [
                'name' => 'remark',
                'label' => '备注',
                'type' => 'textarea',
                'required' => false,
                'rows' => 3,
                'placeholder' => '菜单说明信息',
                'default' => $menu?->remark ?? '',
                'col' => 'col-12', // 全宽（textarea 类型默认也是全宽）
            ],
        ];

        return $fields;
    }

    /**
     * 获取父级菜单选项（用于下拉选择）
     *
     * @param int|null $excludeId 排除的菜单ID（编辑时排除自己和子菜单）
     * @return array
     */
    public function getParentOptions(?int $excludeId = null): array
    {
        $siteId = site_id() ?? 0;

        $query = AdminMenu::query()
            ->where('site_id', $siteId)
            ->where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc');

        // 排除指定菜单及其子菜单
        if ($excludeId) {
            $excludeIds = $this->getDescendantIds($excludeId);
            $excludeIds[] = $excludeId;
            $query->whereNotIn('id', $excludeIds);
        }

        $menus = $query->get();

        // 构建树形选项
        return $this->buildOptions($menus->toArray(), 0, 0);
    }

    /**
     * 构建选项数组（递归）
     *
     * @param array $menus 菜单数组
     * @param int $parentId 父级ID
     * @param int $level 层级
     * @return array
     */
    protected function buildOptions(array $menus, int $parentId, int $level): array
    {
        $options = [];

        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $prefix = str_repeat('　', $level);
                $options[] = [
                    'value' => $menu['id'],
                    'label' => $prefix . $menu['title'],
                ];

                // 递归获取子菜单选项
                $children = $this->buildOptions($menus, $menu['id'], $level + 1);
                $options = array_merge($options, $children);
            }
        }

        return $options;
    }

    /**
     * 获取所有后代菜单ID（递归）
     *
     * @param int $menuId 菜单ID
     * @return array
     */
    protected function getDescendantIds(int $menuId): array
    {
        $ids = [];
        $children = AdminMenu::query()
            ->where('parent_id', $menuId)
            ->get();

        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child->id));
        }

        return $ids;
    }

    /**
     * 创建菜单
     *
     * @param array $data 菜单数据
     * @return AdminMenu
     * @throws BusinessException
     */
    public function create(array $data): AdminMenu
    {
        // 验证父级菜单
        if (!empty($data['parent_id'])) {
            $parent = AdminMenu::query()
                ->where('id', $data['parent_id'])
                ->where('site_id', $data['site_id'] ?? site_id() ?? 0)
                ->first();

            if (!$parent) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '父级菜单不存在');
            }
        }

        // 验证路径唯一性
        if (!empty($data['path'])) {
            $exists = AdminMenu::query()
                ->where('site_id', $data['site_id'] ?? site_id() ?? 0)
                ->where('path', $data['path'])
                ->exists();

            if ($exists) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '路径已存在');
            }
        }

        // 设置默认值
        $data['site_id'] = $data['site_id'] ?? site_id() ?? 0;
        $data['parent_id'] = $data['parent_id'] ?? 0;
        $data['type'] = $data['type'] ?? AdminMenu::TYPE_MENU;
        $data['target'] = $data['target'] ?? AdminMenu::TARGET_SELF;
        $data['status'] = $data['status'] ?? 1;
        $data['visible'] = $data['visible'] ?? 1;
        $data['sort'] = $data['sort'] ?? 0;
        $data['cache'] = $data['cache'] ?? 1;

        return AdminMenu::create($data);
    }

    /**
     * 更新菜单
     *
     * @param int $id 菜单ID
     * @param array $data 菜单数据
     * @return AdminMenu
     * @throws BusinessException
     */
    public function update(int $id, array $data): AdminMenu
    {
        $menu = $this->getById($id);

        // 验证父级菜单（不能将自己设为父级）
        if (!empty($data['parent_id']) && $data['parent_id'] == $id) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '不能将自己设为父级菜单');
        }

        // 验证父级菜单不能是自己的子菜单
        if (!empty($data['parent_id'])) {
            $descendantIds = $this->getDescendantIds($id);
            if (in_array($data['parent_id'], $descendantIds)) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '不能将子菜单设为父级');
            }

            $parent = AdminMenu::query()
                ->where('id', $data['parent_id'])
                ->where('site_id', $menu->site_id)
                ->first();

            if (!$parent) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '父级菜单不存在');
            }
        }

        // 验证路径唯一性（排除自己）
        if (!empty($data['path']) && $data['path'] != $menu->path) {
            $exists = AdminMenu::query()
                ->where('site_id', $menu->site_id)
                ->where('path', $data['path'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '路径已存在');
            }
        }

        $menu->update($data);

        return $menu->fresh();
    }

    /**
     * 删除菜单
     *
     * @param int $id 菜单ID
     * @return bool
     * @throws BusinessException
     */
    public function delete(int $id): bool
    {
        $menu = $this->getById($id);

        // 检查是否有子菜单
        $hasChildren = AdminMenu::query()
            ->where('parent_id', $id)
            ->exists();

        if ($hasChildren) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '存在子菜单，无法删除');
        }

        return $menu->delete();
    }

    /**
     * 批量删除菜单
     *
     * @param array $ids 菜单ID数组
     * @return int 删除数量
     * @throws BusinessException
     */
    public function batchDelete(array $ids): int
    {
        $siteId = site_id() ?? 0;
        $count = 0;

        Db::beginTransaction();
        try {
            foreach ($ids as $id) {
                $menu = AdminMenu::query()
                    ->where('id', $id)
                    ->where('site_id', $siteId)
                    ->first();

                if (!$menu) {
                    continue;
                }

                // 检查是否有子菜单
                $hasChildren = AdminMenu::query()
                    ->where('parent_id', $id)
                    ->exists();

                if ($hasChildren) {
                    throw new BusinessException(ErrorCode::VALIDATION_ERROR, "菜单 ID {$id} 存在子菜单，无法删除");
                }

                $menu->delete();
                $count++;
            }

            Db::commit();
            return $count;
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 切换菜单状态
     *
     * @param int $id 菜单ID
     * @return AdminMenu
     * @throws BusinessException
     */
    /**
     * 切换字段值（通用方法）
     * 
     * @param int $id 菜单ID
     * @param string $field 字段名（如 status、visible）
     * @return AdminMenu
     * @throws BusinessException
     */
    public function toggleField(int $id, string $field): AdminMenu
    {
        $menu = $this->getById($id);
        
        // 验证字段名
        $allowedFields = ['status', 'visible'];
        if (!in_array($field, $allowedFields)) {
            throw new BusinessException(ErrorCode::BAD_REQUEST, "不允许切换字段: {$field}");
        }
        
        // 业务逻辑验证
        if ($field === 'visible') {
            // 如果菜单状态为禁用，不允许切换可见性
            if ($menu->status == 0) {
                throw new BusinessException(ErrorCode::BAD_REQUEST, '禁用的菜单无法设置可见性');
            }
        }
        
        // 切换字段值
        $currentValue = $menu->{$field} ?? 0;
        $menu->{$field} = $currentValue == 1 ? 0 : 1;
        $menu->save();

        return $menu->fresh();
    }

    /**
     * 切换菜单状态（保留向后兼容）
     *
     * @param int $id 菜单ID
     * @return AdminMenu
     * @throws BusinessException
     */
    public function toggleStatus(int $id): AdminMenu
    {
        return $this->toggleField($id, 'status');
    }

    /**
     * 切换菜单可见性（保留向后兼容）
     *
     * @param int $id 菜单ID
     * @return AdminMenu
     * @throws BusinessException
     */
    public function toggleVisible(int $id): AdminMenu
    {
        return $this->toggleField($id, 'visible');
    }

    /**
     * 更新菜单排序
     *
     * @param array $sorts 排序数据 [['id' => 1, 'sort' => 10], ...]
     * @return bool
     */
    public function updateSort(array $sorts): bool
    {
        $siteId = site_id() ?? 0;

        Db::beginTransaction();
        try {
            foreach ($sorts as $item) {
                AdminMenu::query()
                    ->where('id', $item['id'])
                    ->where('site_id', $siteId)
                    ->update(['sort' => $item['sort']]);
            }

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }
}

