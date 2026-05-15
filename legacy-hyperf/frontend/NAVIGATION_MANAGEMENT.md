# 导航菜单管理指南

## 📋 概述

前端导航菜单现已改为**API动态加载**，支持通过后台管理系统灵活配置菜单结构和链接。

---

## 🔌 API接口

### 获取导航菜单

**接口**: `GET /api/v1/navigation`

**响应格式**:
```json
{
  "code": 200,
  "message": "success",
  "data": {
    "items": [
      {
        "id": 1,
        "name": "首页",
        "url": "/",
        "sort_order": 1,
        "children": []
      },
      {
        "id": 2,
        "name": "关于我们",
        "url": "/about",
        "sort_order": 2,
        "children": [
          {
            "id": 21,
            "name": "发起人介绍",
            "url": "/about/founder",
            "sort_order": 1
          }
        ]
      }
    ]
  }
}
```

---

## 📊 数据库表结构

### jianhui_org_navigations 表

```sql
CREATE TABLE `jianhui_org_navigations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT 0 COMMENT '父级菜单ID，0为顶级菜单',
  `name` varchar(100) NOT NULL COMMENT '菜单名称',
  `url` varchar(255) DEFAULT NULL COMMENT '链接地址',
  `icon` varchar(100) DEFAULT NULL COMMENT '图标',
  `target` varchar(20) DEFAULT '_self' COMMENT '打开方式：_self/_blank',
  `sort_order` int(11) DEFAULT 0 COMMENT '排序，数字越小越靠前',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1显示，0隐藏',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `sort_order` (`sort_order`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='导航菜单表';
```

---

## 🎨 前端实现

### 组件结构

**AppHeader.vue**:
```vue
<template>
  <nav class="header-nav" v-if="appStore.navigation.length > 0">
    <ul class="nav-list">
      <li class="nav-item" v-for="item in appStore.navigation" :key="item.id">
        <!-- 无子菜单 -->
        <router-link v-if="!item.children || item.children.length === 0"
                     :to="item.url" class="nav-link">
          {{ item.name }}
        </router-link>

        <!-- 有子菜单 - 下拉菜单 -->
        <el-dropdown v-else trigger="hover">
          <span class="nav-link dropdown-toggle">
            {{ item.name }}
            <span class="dropdown-arrow">▼</span>
          </span>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item v-for="child in item.children" :key="child.id">
                <router-link :to="child.url">{{ child.name }}</router-link>
              </el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </li>
    </ul>
  </nav>

  <!-- Loading state - 骨架屏 -->
  <nav class="header-nav" v-else>
    <ul class="nav-list">
      <li class="nav-item" v-for="i in 6" :key="i">
        <div class="nav-skeleton"></div>
      </li>
    </ul>
  </nav>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAppStore } from '@/stores/app'

const appStore = useAppStore()
const mobileMenuOpen = ref(false)

onMounted(async () => {
  await appStore.loadNavigation()
})
</script>
```

### 数据流

1. **页面加载** → AppHeader 挂载
2. **调用API** → `appStore.loadNavigation()`
3. **获取数据** → `GET /api/v1/navigation`
4. **更新状态** → `appStore.navigation` 存储
5. **渲染菜单** → v-for 循环渲染菜单项
6. **加载失败** → 自动使用 mock 数据

---

## 🛠️ 后台管理功能

### 菜单管理控制器

**Controller/Admin/NavigationController.php**:

```php
<?php

namespace App\Controller\Admin;

use App\Model\JianhuiNavigation;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;

/**
 * 导航菜单管理
 */
#[Controller]
class NavigationController
{
    /**
     * 菜单列表
     */
    #[RequestMapping(path: "/admin/navigation/index", methods: "GET")]
    public function index()
    {
        $navigations = JianhuiNavigation::orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // 构建树形结构
        $tree = $this->buildTree($navigations->toArray());

        return ['code' => 200, 'data' => $tree];
    }

    /**
     * 添加菜单
     */
    #[RequestMapping(path: "/admin/navigation/create", methods: "POST")]
    public function create()
    {
        $data = [
            'parent_id' => (int)request()->input('parent_id', 0),
            'name' => request()->input('name'),
            'url' => request()->input('url', ''),
            'icon' => request()->input('icon', ''),
            'sort_order' => (int)request()->input('sort_order', 0),
            'status' => (int)request()->input('status', 1),
        ];

        $id = JianhuiNavigation::insertGetId($data);

        return ['code' => 200, 'message' => '添加成功', 'data' => ['id' => $id]];
    }

    /**
     * 更新菜单
     */
    #[RequestMapping(path: "/admin/navigation/update", methods: "POST")]
    public function update()
    {
        $id = (int)request()->input('id');
        $data = [
            'parent_id' => (int)request()->input('parent_id', 0),
            'name' => request()->input('name'),
            'url' => request()->input('url', ''),
            'icon' => request()->input('icon', ''),
            'sort_order' => (int)request()->input('sort_order', 0),
            'status' => (int)request()->input('status', 1),
        ];

        JianhuiNavigation::where('id', $id)->update($data);

        return ['code' => 200, 'message' => '更新成功'];
    }

    /**
     * 删除菜单
     */
    #[RequestMapping(path: "/admin/navigation/delete", methods: "POST")]
    public function delete()
    {
        $id = (int)request()->input('id');

        // 检查是否有子菜单
        $hasChildren = JianhuiNavigation::where('parent_id', $id)->exists();
        if ($hasChildren) {
            return ['code' => 400, 'message' => '请先删除子菜单'];
        }

        JianhuiNavigation::where('id', $id)->delete();

        return ['code' => 200, 'message' => '删除成功'];
    }

    /**
     * 构建树形结构
     */
    private function buildTree(array $items, int $parentId = 0): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] === $parentId) {
                $children = $this->buildTree($items, $item['id']);
                $item['children'] = $children;
                $tree[] = $item;
            }
        }
        return $tree;
    }
}
```

### 前端API接口

**Controller/Web/JianhuiOrgWebController.php**:

```php
<?php

/**
 * 获取导航菜单（前端API）
 */
#[RequestMapping(path: "/api/v1/navigation", methods: "GET")]
public function navigation()
{
    $navigations = JianhuiNavigation::where('status', 1)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get()
        ->toArray();

    $tree = $this->buildTree($navigations);

    return [
        'code' => 200,
        'message' => 'success',
        'data' => ['items' => $tree]
    ];
}
```

---

## 📦 初始数据

### SQL导入脚本

```sql
-- 清空表
TRUNCATE TABLE jianhui_org_navigations;

-- 插入导航菜单数据
INSERT INTO jianhui_org_navigations (parent_id, name, url, sort_order, status) VALUES
(0, '首页', '/', 1, 1),
(0, '关于我们', '/about', 2, 1),
(2, '发起人介绍', '/about/founder', 1, 1),
(2, '基金会介绍', '/about', 2, 1),
(2, '理事会及监事会', '/about/council', 3, 1),
(2, '基金会章程', '/about/constitution', 4, 1),
(2, '管理制度', '/about/management', 5, 1),
(2, '资质证书', '/about/certificates', 6, 1),
(0, '公益项目', '/projects', 3, 1),
(9, '非定向', '/projects?type=undesignated', 1, 1),
(9, '应急响应与救援', '/projects?type=emergency', 2, 1),
(9, '医疗援助与发展', '/projects?type=medical', 3, 1),
(9, '健康社会关怀', '/projects?type=health', 4, 1),
(0, '爱心捐赠', '/donate', 4, 1),
(14, '捐赠方式', '/donate', 1, 1),
(14, '捐赠披露', '/donation-disclosure', 2, 1),
(14, '票据开具', '/donate/invoice', 3, 1),
(14, '证书申领', '/donate/certificate', 4, 1),
(14, '抵扣说明', '/donate/deduction', 5, 1),
(14, '爱心传递', '/donate/share', 6, 1),
(0, '新闻中心', '/articles', 5, 1),
(21, '网站公告', '/articles?category=notice', 1, 1),
(21, '项目动态', '/articles?category=project', 2, 1),
(21, '视频动态', '/articles?category=video', 3, 1),
(21, '志愿者动态', '/articles?category=volunteer', 4, 1),
(21, '行业动态', '/articles?category=industry', 5, 1),
(21, '社会评价', '/articles?category=social', 6, 1),
(0, '信息公开', '/disclosure', 6, 1),
(28, '年度报告', '/disclosure/annual', 1, 1),
(28, '工作报告', '/disclosure/work', 2, 1),
(28, '审计报告', '/disclosure/audit', 3, 1),
(28, '季度报告', '/disclosure/quarterly', 4, 1),
(28, '投资活动', '/disclosure/investment', 5, 1),
(0, '党建专栏', '/disclosure/party', 7, 1),
(0, '加入我们', '/join', 8, 1),
(34, '人员招聘', '/join/recruitment', 1, 1),
(34, '志愿者招募', '/join/volunteer', 2, 1),
(34, '联系我们', '/about/contact', 3, 1);
```

---

## ✅ 功能特性

### 1. 动态加载
- API实时获取菜单数据
- 支持热更新，无需重新部署

### 2. 骨架屏
- 加载时显示占位动画
- 提升用户体验

### 3. Mock数据
- API失败时自动使用mock数据
- 确保页面始终可访问

### 4. 树形结构
- 支持无限层级嵌套
- 自动构建父子关系

### 5. 排序支持
- sort_order字段控制显示顺序
- 数字越小越靠前

### 6. 状态管理
- status字段控制显示/隐藏
- 1显示，0隐藏

---

## 🎯 使用场景

### 开发阶段
- 使用mock数据进行开发
- 快速迭代，无需等待后端

### 测试阶段
- 后端提供API接口
- 前端切换到真实数据

### 生产环境
- 后台管理菜单
- 实时更新，立即生效

---

## 📝 注意事项

1. **URL格式**: 前端路由使用 `/path` 格式，外部链接使用完整URL
2. **嵌套层级**: 建议不超过3级，避免用户操作复杂
3. **排序规则**: 同级菜单按sort_order升序排列
4. **缓存策略**: 建议前端缓存菜单数据5-10分钟
5. **权限控制**: 可根据用户角色显示不同菜单

---

## 🚀 下一步

- [ ] 创建导航菜单管理界面
- [ ] 实现拖拽排序功能
- [ ] 添加菜单图标上传
- [ ] 支持多语言菜单
- [ ] 添加菜单访问统计

---

**文档创建时间**: 2026-03-23
**版本**: v1.0
**维护者**: 建辉慈善基金会技术团队
