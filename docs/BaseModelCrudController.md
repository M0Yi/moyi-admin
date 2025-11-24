# BaseModelCrudController 使用指南

## 简介

`BaseModelCrudController` 是一个适用于 Hyperf Model 的 CRUD 基类控制器，提供了完整的增删改查功能。它基于 Eloquent ORM，适用于使用具体 Model 类的场景。

## 与 UniversalCrudController 的区别

| 特性 | BaseModelCrudController | UniversalCrudController |
|------|------------------------|------------------------|
| 适用场景 | 具体的 Model 类 | 动态表名/配置 |
| 数据操作 | Eloquent ORM | 直接操作表 |
| 配置方式 | 代码中定义 | 数据库/配置文件 |
| 灵活性 | 高（可重写方法） | 中（配置驱动） |
| 性能 | 高（Eloquent 优化） | 中（动态查询） |

## 功能特性

- ✅ 列表查询（支持搜索、过滤、排序、分页）
- ✅ 创建记录
- ✅ 编辑记录
- ✅ 删除记录（支持软删除和硬删除）
- ✅ 批量删除
- ✅ 切换字段值（如状态切换）
- ✅ 数据验证
- ✅ 站点过滤（自动处理 `site_id`）
- ✅ 回收站管理（如果启用软删除）
- ✅ 批量恢复/永久删除

## 快速开始

### 1. 创建控制器

```php
<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\BaseModelCrudController;
use App\Model\Admin\AdminUser;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class UserController extends BaseModelCrudController
{
    /**
     * 指定 Model 类
     */
    protected function getModelClass(): string
    {
        return AdminUser::class;
    }

    /**
     * 自定义验证规则
     */
    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        return [
            'create' => [
                'username' => 'required|string|max:50|unique:admin_users',
                'email' => 'required|email|unique:admin_users',
            ],
            'update' => [
                'username' => 'required|string|max:50|unique:admin_users,username,' . $id,
                'email' => 'required|email|unique:admin_users,email,' . $id,
            ],
        ][$scene] ?? [];
    }
}
```

### 2. 配置路由

```php
// config/routes.php
use Hyperf\HttpServer\Router\Router;

Router::addGroup('/admin/users', function () {
    Router::get('', 'App\Controller\Admin\UserController@index');
    Router::get('/create', 'App\Controller\Admin\UserController@create');
    Router::post('', 'App\Controller\Admin\UserController@store');
    Router::get('/{id:\d+}/edit', 'App\Controller\Admin\UserController@edit');
    Router::put('/{id:\d+}', 'App\Controller\Admin\UserController@update');
    Router::delete('/{id:\d+}', 'App\Controller\Admin\UserController@destroy');
    Router::post('/{id:\d+}/toggle-status', 'App\Controller\Admin\UserController@toggleStatus');
    Router::get('/trash', 'App\Controller\Admin\UserController@trash');
    Router::post('/{id:\d+}/restore', 'App\Controller\Admin\UserController@restore');
    Router::delete('/{id:\d+}/force', 'App\Controller\Admin\UserController@forceDelete');
});
```

## 核心方法

### 必须实现的方法

#### `getModelClass(): string`

返回 Model 类名，例如：`AdminUser::class`

### 可重写的方法

#### `getValidationRules(string $scene, ?int $id = null): array`

返回验证规则数组。`$scene` 可以是 `'create'` 或 `'update'`。

```php
protected function getValidationRules(string $scene, ?int $id = null): array
{
    return [
        'create' => [
            'username' => 'required|string|max:50|unique:admin_users',
        ],
        'update' => [
            'username' => 'required|string|max:50|unique:admin_users,username,' . $id,
        ],
    ][$scene] ?? [];
}
```

#### `getSearchableFields(): array`

返回可搜索的字段列表。

```php
protected function getSearchableFields(): array
{
    return ['id', 'username', 'email', 'real_name'];
}
```

#### `getSortableFields(): array`

返回可排序的字段列表。

```php
protected function getSortableFields(): array
{
    return ['id', 'username', 'email', 'created_at'];
}
```

#### `getListQuery()`

返回列表查询构建器，可以添加关联查询、作用域等。

```php
protected function getListQuery()
{
    $query = parent::getListQuery();
    
    // 添加关联查询
    $query->with(['roles', 'site']);
    
    // 添加查询作用域
    $query->active();
    
    return $query;
}
```

#### `applyFilters($query, array $filters): void`

自定义过滤逻辑。

```php
protected function applyFilters($query, array $filters): void
{
    parent::applyFilters($query, $filters);
    
    // 自定义过滤逻辑
    if (isset($filters['role_id'])) {
        $query->whereHas('roles', function ($q) use ($filters) {
            $q->where('role_id', $filters['role_id']);
        });
    }
}
```

#### `getFieldLabels(): array`

返回字段标签映射（用于错误消息）。

```php
protected function getFieldLabels(): array
{
    return array_merge(parent::getFieldLabels(), [
        'username' => '用户名',
        'email' => '邮箱',
        'status' => '状态',
    ]);
}
```

#### `renderListPage(RequestInterface $request): ResponseInterface`

渲染列表页面。

```php
protected function renderListPage(RequestInterface $request): ResponseInterface
{
    return $this->renderAdmin('admin.users.index', []);
}
```

#### `renderCreatePage(RequestInterface $request): ResponseInterface`

渲染创建页面。

```php
protected function renderCreatePage(RequestInterface $request): ResponseInterface
{
    return $this->renderAdmin('admin.users.create', []);
}
```

#### `renderEditPage(RequestInterface $request, Model $model): ResponseInterface`

渲染编辑页面。

```php
protected function renderEditPage(RequestInterface $request, Model $model): ResponseInterface
{
    return $this->renderAdmin('admin.users.edit', ['user' => $model]);
}
```

## 公共方法（可直接使用）

### `index(RequestInterface $request): ResponseInterface`

列表页面。如果请求包含 `_ajax=1` 参数，返回 JSON 数据。

### `listData(RequestInterface $request): ResponseInterface`

获取列表数据（API）。

**请求参数：**
- `keyword`: 搜索关键词
- `filters`: 过滤条件（JSON 字符串或数组）
- `page`: 页码（默认：1）
- `page_size`: 每页数量（默认：15）
- `sort_field`: 排序字段（默认：id）
- `sort_order`: 排序方向（默认：desc）

**响应格式：**
```json
{
    "code": 200,
    "msg": "",
    "data": {
        "data": [...],
        "total": 100,
        "page": 1,
        "page_size": 15,
        "last_page": 7
    }
}
```

### `create(RequestInterface $request): ResponseInterface`

创建页面。

### `store(RequestInterface $request): ResponseInterface`

保存数据。

**请求体：** 表单数据或 JSON

**响应格式：**
```json
{
    "code": 200,
    "msg": "创建成功",
    "data": {
        "id": 1
    }
}
```

### `edit(RequestInterface $request, int $id): ResponseInterface`

编辑页面。

### `update(RequestInterface $request, int $id): ResponseInterface`

更新数据。

### `destroy(RequestInterface $request, int $id): ResponseInterface`

删除数据（支持软删除和硬删除）。

### `batchDestroy(RequestInterface $request): ResponseInterface`

批量删除。

**请求参数：**
- `ids`: ID 数组

### `toggleStatus(RequestInterface $request, int $id): ResponseInterface`

切换字段值（默认切换 `status` 字段）。

**请求参数：**
- `field`: 要切换的字段名（默认：status）

### `trash(RequestInterface $request): ResponseInterface`

回收站页面。

### `trashData(RequestInterface $request): ResponseInterface`

获取回收站数据（API）。

### `restore(RequestInterface $request, int $id): ResponseInterface`

恢复记录。

### `forceDelete(RequestInterface $request, int $id): ResponseInterface`

永久删除记录。

### `batchRestore(RequestInterface $request): ResponseInterface`

批量恢复。

### `batchForceDelete(RequestInterface $request): ResponseInterface`

批量永久删除。

### `clearTrash(RequestInterface $request): ResponseInterface`

清空回收站。

## 自动功能

### 站点过滤

如果模型有 `site_id` 字段，控制器会自动添加站点过滤（超级管理员除外）。

### 软删除

如果模型使用了 `SoftDeletes` trait，控制器会自动处理软删除：
- `destroy()` 执行软删除
- `trash()` 显示回收站
- `restore()` 恢复记录
- `forceDelete()` 永久删除

### 数据验证

控制器会自动使用 `getValidationRules()` 返回的规则进行数据验证，并返回友好的中文错误消息。

### 字段过滤

控制器会自动过滤 `fillable` 之外的字段，只保存允许的字段。

## 自定义示例

### 示例 1：添加关联查询

```php
protected function getListQuery()
{
    $query = parent::getListQuery();
    $query->with(['roles', 'site']);
    return $query;
}
```

### 示例 2：自定义保存逻辑

```php
public function store(RequestInterface $request): ResponseInterface
{
    $data = $request->all();
    
    // 自定义处理：密码加密
    if (isset($data['password'])) {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    // 调用父类方法
    return parent::store($request);
}
```

### 示例 3：自定义删除逻辑

```php
public function destroy(RequestInterface $request, int $id): ResponseInterface
{
    $model = $this->findModel($id);
    
    // 自定义检查：是否可以删除
    if ($model->is_admin) {
        return $this->error('不能删除超级管理员');
    }
    
    // 调用父类方法
    return parent::destroy($request, $id);
}
```

## 注意事项

1. **Model 类必须存在**：确保 `getModelClass()` 返回的类存在且可访问。

2. **Fillable 配置**：确保 Model 的 `$fillable` 属性正确配置，控制器会自动过滤字段。

3. **软删除**：如果使用软删除，确保 Model 使用了 `SoftDeletes` trait。

4. **站点过滤**：如果模型有 `site_id` 字段，控制器会自动添加站点过滤（超级管理员除外）。

5. **验证规则**：建议为每个场景（create/update）定义验证规则，确保数据安全。

6. **视图实现**：需要实现 `renderListPage()`、`renderCreatePage()`、`renderEditPage()` 方法或创建对应的视图。

## 最佳实践

1. **使用验证规则**：始终定义验证规则，确保数据完整性。

2. **自定义查询**：使用 `getListQuery()` 添加关联查询，避免 N+1 问题。

3. **字段标签**：定义字段标签，提供友好的错误消息。

4. **重写方法**：在需要时重写方法，添加业务逻辑。

5. **错误处理**：控制器已包含错误处理，但可以在重写的方法中添加额外的错误处理。

## 与 UniversalCrudController 的选择

- **使用 BaseModelCrudController**：当你需要为具体的 Model 创建控制器，且需要自定义业务逻辑时。
- **使用 UniversalCrudController**：当你需要快速为多个表创建 CRUD 功能，且配置驱动即可满足需求时。

两者可以共存，根据实际需求选择使用。

