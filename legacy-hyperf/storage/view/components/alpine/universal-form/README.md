# UniversalForm 通用表单渲染引擎

## ⚠️ 重要：这是一个纯 JS 渲染引擎

UniversalForm 不再使用传统的 Blade 表单组件，而是采用 **纯 JS 渲染引擎** 方式。

```
┌─────────────────────────────────────────────────────────────┐
│                    UniversalForm JS 引擎                      │
│                                                             │
│  配置（JSON）：                                              │
│  ─────────────────────────────────────────────────────────  │
│  [                                                          │
│    { name: 'username', type: 'text', label: '用户名' },     │
│    { name: 'email', type: 'email', label: '邮箱' },         │
│    { name: 'status', type: 'select', options: [...] },     │
│    ...                                                       │
│  ]                                                          │
│                                                             │
│  渲染输出：                                                  │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  <form>                                              │   │
│  │    <div class="form-group">...</div>                │   │
│  │    <div class="form-group">...</div>                │   │
│  │  </form>                                             │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

## 优势对比

| 维度 | 传统 Blade 组件 | UniversalForm JS 引擎 |
|------|----------------|---------------------|
| **渲染方式** | 服务器端渲染 | 客户端渲染 |
| **代码量** | 8 个 Blade 文件 | 1 个 JS 文件 |
| **灵活性** | 静态模板 | 动态配置 |
| **首屏速度** | 快 | 略慢（需 JS 执行） |
| **交互体验** | 需额外 JS 增强 | 天生动态 |
| **适用场景** | SEO 要求的页面 | CRUD 弹窗、表单页面 |

## 使用方式

### 1. 引入组件

```blade
{{-- 引入 UniversalForm JS 引擎 --}}
@include('components.alpine.universal-form-js')
```

### 2. 创建表单

```javascript
const form = UniversalForm.create({
    api: '/api/admin/users',
    method: 'POST',
    id: 'user-form',
    layout: 'vertical', // 或 'horizontal'
    
    fields: [
        // 文本输入
        { 
            name: 'username', 
            type: 'text', 
            label: '用户名', 
            required: true,
            placeholder: '请输入用户名',
            min: 3,
            max: 20
        },
        
        // 密码输入
        { 
            name: 'password', 
            type: 'password', 
            label: '密码', 
            required: true,
            min: 6
        },
        
        // 邮箱
        { 
            name: 'email', 
            type: 'email', 
            label: '邮箱',
            required: true
        },
        
        // 下拉选择
        { 
            name: 'status', 
            type: 'select', 
            label: '状态',
            options: [
                { value: '1', label: '启用' },
                { value: '0', label: '禁用' }
            ]
        },
        
        // 多选
        { 
            name: 'roles', 
            type: 'checkbox', 
            label: '角色',
            options: [
                { value: '1', label: '管理员' },
                { value: '2', label: '编辑' },
                { value: '3', label: '访客' }
            ]
        },
        
        // 单选
        { 
            name: 'gender', 
            type: 'radio', 
            label: '性别',
            options: [
                { value: '1', label: '男' },
                { value: '2', label: '女' }
            ]
        },
        
        // 开关
        { 
            name: 'is_active', 
            type: 'switch', 
            label: '是否启用',
            checked: true
        },
        
        // 文本域
        { 
            name: 'remark', 
            type: 'textarea', 
            label: '备注',
            rows: 4,
            placeholder: '请输入备注信息'
        },
        
        // 日期
        { 
            name: 'birthday', 
            type: 'date', 
            label: '生日',
            placeholder: '选择日期'
        },
        
        // 文件上传
        { 
            name: 'avatar', 
            type: 'image', 
            label: '头像',
            accept: 'image/*',
            maxSize: 5,
            showPreview: true
        },
        
        // 分隔线
        { type: 'divider' },
        
        // 分组标题
        { type: 'title', label: '其他信息' },
        
        // 字段分组
        {
            type: 'group',
            label: '高级设置',
            fields: [
                { name: 'vip_level', type: 'select', label: 'VIP等级', options: [...] },
                { name: 'expire_date', type: 'date', label: '过期日期' }
            ]
        }
    ],
    
    // 回调函数
    onSubmit: (data) => {
        // 数据预处理
        return data;
    },
    
    onSuccess: (result) => {
        $toast.success('保存成功！');
        // 关闭弹窗
        $modal.hide('user-form-modal');
        // 刷新表格
        DataTable.reload();
    },
    
    onError: (error) => {
        $toast.error(error.message || '保存失败！');
    },
    
    onReset: () => {
        console.log('表单已重置');
    },
    
    onChange: (name, value, data) => {
        console.log(`字段 ${name} 变为 ${value}`);
    }
});

// 挂载到容器
form.mount('#user-form');
```

### 3. 在弹窗中使用

```javascript
// 获取或创建弹窗
const modal = $modal.getOrCreate('user-form-modal', {
    title: '新增用户',
    size: 'lg'
});

// 挂载表单到弹窗
form.mount('#user-form .modal-body');

// 打开弹窗时清空表单
modal.on('show', () => {
    form.reset();
});

modal.show();
```

## CRUD 完整示例

```blade
{{-- admin/users/index.blade.php --}}
@extends('layouts.admin')

@section('title', '用户管理')

@push('styles')
@endpush

@section('content')
<div class="page-header">
    <h1>用户管理</h1>
    <div class="actions">
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="bi bi-plus-lg"></i> 新增用户
        </button>
    </div>
</div>

{{-- 搜索表单 --}}
@include('components.alpine.search-form', [
    'id' => 'user-search',
    'fields' => [
        ['name' => 'keyword', 'type' => 'text', 'label' => '关键词', 'placeholder' => '搜索用户名/邮箱'],
        ['name' => 'status', 'type' => 'select', 'label' => '状态', 'options' => [
            ['value' => '', 'label' => '全部'],
            ['value' => '1', 'label' => '启用'],
            ['value' => '0', 'label' => '禁用']
        ]]
    ]
])

{{-- 数据表格 --}}
<div id="user-table"></div>

{{-- 弹窗：新增/编辑表单 --}}
@include('components.alpine.modal', ['id' => 'user-form-modal', 'size' => 'lg'])

{{-- 引入 JS 组件 --}}
@include('components.alpine.universal-form-js')
@include('components.alpine.search-form-js')
@include('components.alpine.datatable-js')
@endpush

@push('scripts')
<script>
let table, searchForm, userForm, userModal;

document.addEventListener('alpine:init', () => {
    // 初始化搜索
    searchForm = SearchForm.create({
        id: 'user-search',
        onSearch: (params) => table.search(params)
    });
    searchForm.mount('#user-search');

    // 初始化表格
    table = DataTable.create({
        api: '/api/admin/users',
        columns: [
            { title: 'ID', data: 'id', width: '60px' },
            { title: '用户名', data: 'username' },
            { title: '邮箱', data: 'email' },
            { 
                title: '状态', 
                data: 'status',
                render: (row) => row.status == 1 
                    ? '<span class="badge bg-success">启用</span>' 
                    : '<span class="badge bg-danger">禁用</span>'
            },
            { 
                title: '操作', 
                render: (row) => `
                    <button class="btn btn-sm btn-info" onclick="editUser(${row.id})">编辑</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser(${row.id})">删除</button>
                `,
                width: '150px'
            }
        ]
    });
    table.mount('#user-table');

    // 初始化弹窗
    userModal = $modal.getOrCreate('user-form-modal', {
        title: '新增用户',
        size: 'lg'
    });

    // 初始化表单
    userForm = UniversalForm.create({
        api: '/api/admin/users',
        method: 'POST',
        fields: [
            { name: 'username', type: 'text', label: '用户名', required: true, placeholder: '请输入用户名' },
            { name: 'email', type: 'email', label: '邮箱', required: true },
            { name: 'password', type: 'password', label: '密码', required: true, min: 6 },
            { name: 'mobile', type: 'tel', label: '手机号' },
            { name: 'role_id', type: 'select', label: '角色', options: @json($roles) },
            { name: 'status', type: 'switch', label: '是否启用', checked: true },
            { name: 'remark', type: 'textarea', label: '备注', rows: 3 }
        ],
        onSuccess: () => {
            $toast.success('保存成功！');
            userModal.hide();
            table.reload();
        },
        onError: (error) => {
            $toast.error(error.message || '保存失败！');
        }
    });
    userForm.mount('#user-form-modal .modal-body');
});

// 打开新增弹窗
function openCreateModal() {
    userForm.reset();
    userForm.setConfig({ api: '/api/admin/users', method: 'POST' });
    userModal.setTitle('新增用户');
    userModal.show();
}

// 打开编辑弹窗
async function editUser(id) {
    $loading.show('加载中...');
    try {
        const user = await $http.get(`/api/admin/users/${id}`);
        userForm.reset();
        userForm.setValues(user);
        userForm.setConfig({ api: `/api/admin/users/${id}`, method: 'PUT' });
        userModal.setTitle('编辑用户');
        userModal.show();
    } catch (error) {
        $toast.error('加载用户信息失败！');
    } finally {
        $loading.hide();
    }
}

// 删除用户
async function deleteUser(id) {
    const confirmed = await $confirm.danger('确定要删除该用户吗？');
    if (!confirmed) return;

    $loading.show('删除中...');
    try {
        await $http.delete(`/api/admin/users/${id}`);
        $toast.success('删除成功！');
        table.reload();
    } catch (error) {
        $toast.error(error.message || '删除失败！');
    } finally {
        $loading.hide();
    }
}
</script>
@endpush
```

## 字段类型支持

| 类型 | 说明 | 额外参数 |
|------|------|---------|
| `text` | 文本输入 | `min`, `max`, `placeholder` |
| `password` | 密码输入 | `min`, `max` |
| `email` | 邮箱输入 | 自动验证格式 |
| `number` | 数字输入 | `min`, `max`, `step` |
| `tel` | 电话输入 | `pattern` |
| `url` | URL 输入 | 自动验证格式 |
| `textarea` | 文本域 | `rows`, `placeholder` |
| `select` | 下拉单选 | `options`, `placeholder` |
| `multiselect` | 下拉多选 | `options` |
| `checkbox` | 复选框组 | `options`, `inline` |
| `radio` | 单选框组 | `options`, `inline` |
| `switch` | 开关 | `trueValue`, `falseValue`, `size` |
| `date` | 日期选择 | `format` |
| `datetime` | 日期时间 | `format` |
| `file` | 文件上传 | `accept`, `maxSize` |
| `image` | 图片上传 | `accept`, `maxSize`, `showPreview` |
| `color` | 颜色选择 | - |
| `hidden` | 隐藏字段 | - |
| `divider` | 分隔线 | - |
| `title` | 分组标题 | - |
| `group` | 字段分组 | `fields` |

## 字段参数说明

```javascript
{
    // 基础参数
    name: 'field_name',        // 字段名（必需）
    type: 'text',              // 类型（必需）
    label: '字段标签',          // 显示标签
    value: '',                  // 默认值
    default: '',                // 默认值（别名）
    
    // 验证参数
    required: false,           // 是否必填
    requiredMessage: 'xxx不能为空',  // 自定义错误消息
    min: 3,                    // 最小值/最小长度
    max: 50,                   // 最大值/最大长度
    pattern: /^[a-z]+$/,       // 正则验证
    validator: (value, data) => {  // 自定义验证函数
        return value.length > 0 || '不能为空';
    },
    
    // UI 参数
    placeholder: '请输入',      // 占位符
    disabled: false,           // 是否禁用
    readonly: false,          // 是否只读
    help: '帮助文字',          // 帮助提示
    class: 'custom-class',    // 自定义 CSS 类
    
    // Select/Checkbox/Radio 参数
    options: [                 // 选项
        { value: '1', label: '启用' },
        { value: '0', label: '禁用' }
    ],
    inline: true,              // 是否内联显示（checkbox/radio）
    
    // Switch 参数
    trueValue: '1',            // 开启时的值
    falseValue: '0',           // 关闭时的值
    size: 'lg',                // 尺寸（sm/lg）
    
    // File/Image 参数
    accept: 'image/*',         // 接受的文件类型
    maxSize: 5,                // 最大文件大小（MB）
    maxWidth: 1920,            // 最大宽度（像素）
    maxHeight: 1080,           // 最大高度（像素）
    showPreview: true,          // 是否显示预览
    
    // 事件回调
    onChange: (name, value, data) => {}  // 值变更回调
}
```

## API 方法

| 方法 | 说明 |
|------|------|
| `UniversalForm.create(options)` | 创建表单实例 |
| `form.mount(selector)` | 挂载到 DOM |
| `form.getData()` | 获取表单数据 |
| `form.setValue(name, value)` | 设置单个字段值 |
| `form.setValues(data)` | 批量设置字段值 |
| `form.reset()` | 重置表单 |
| `form.clear()` | 清空表单 |
| `form.validate()` | 验证表单 |
| `form.submit()` | 手动提交表单 |
| `form.destroy()` | 销毁表单实例 |
| `form.show()` | 显示表单 |
| `form.hide()` | 隐藏表单 |

## 文件结构

```
public/js/components/universal-form/
└── index.js                  # UniversalForm JS 引擎（唯一文件）

storage/view/components/alpine/
└── universal-form-js.blade.php  # Blade 入口组件
```

## 依赖关系

```
UniversalForm
    │
    ├── $http       ← request-js.blade.php
    ├── $toast      ← toast-js.blade.php
    ├── $loading    ← loading-js.blade.php
    ├── $modal      ← modal-js.blade.php
    └── Bootstrap 5 ← plugin/bootstrap-js.blade.php
```

## 对比传统 Blade 组件

### 传统方式（已废弃）
```blade
{{-- 需要 8 个 Blade 文件 --}}
@include('components.alpine.universal-form.input', [...])
@include('components.alpine.universal-form.select', [...])
@include('components.alpine.universal-form.checkbox', [...])
{{-- ... 更多组件 --}}
```

### 新方式（推荐）
```blade
{{-- 只需引入一个 JS 引擎 --}}
@include('components.alpine.universal-form-js')

<script>
// 通过配置自动渲染所有字段
const form = UniversalForm.create({
    fields: [
        { name: 'username', type: 'text', label: '用户名' },
        { name: 'status', type: 'select', options: [...] },
        // ...
    ]
});
form.mount('#form-container');
</script>
```

## 总结

- ✅ **纯 JS 渲染**：无需 Blade 组件
- ✅ **配置驱动**：通过 JSON 配置生成表单
- ✅ **10+ 字段类型**：支持所有常见表单场景
- ✅ **内置验证**：必填、格式、长度、自定义验证
- ✅ **无缝集成**：与 $http, $toast, $modal 完美配合
- ✅ **动态交互**：实时验证、动态禁用/显示
- ✅ **适合 CRUD**：特别适合管理后台的弹窗表单场景
