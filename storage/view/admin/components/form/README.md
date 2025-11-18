# 表单组件使用说明

## 组件目录结构

```
storage/view/admin/components/form/
├── field.blade.php          # 统一字段组件（根据类型自动调用对应组件）
├── text.blade.php           # 文本输入（text, email, password）
├── number.blade.php         # 数字输入
├── textarea.blade.php       # 文本域
├── date.blade.php           # 日期选择
├── datetime.blade.php        # 日期时间选择
├── select.blade.php         # 下拉选择（select, relation）
├── radio.blade.php          # 单选框
├── checkbox.blade.php       # 复选框
├── switch.blade.php         # 开关（使用 radio 实现）
├── image.blade.php          # 单图上传
├── images.blade.php         # 多图上传
├── rich_text.blade.php      # 富文本编辑器
└── number_range.blade.php   # 数字区间
```

## 使用方法

### 统一字段组件（推荐）

在表单中直接使用 `field` 组件，它会根据字段类型自动调用对应的组件：

```blade
@include('admin.components.form.field', [
    'field' => $field,
    'value' => $value ?? null,           // 可选，用于编辑页面
    'relations' => $relations ?? [],      // 可选，用于 relation 类型
    'isEdit' => false                     // 可选，是否编辑模式
])
```

### 在创建页面使用

```blade
@foreach($fields as $field)
@include('admin.components.form.field', [
    'field' => $field,
    'value' => null,
    'relations' => $relations ?? [],
    'isEdit' => false
])
@endforeach
```

### 在编辑页面使用

```blade
@foreach($fields as $field)
@php
    $value = $record[$field['name']] ?? ($field['default'] ?? '');
@endphp
@include('admin.components.form.field', [
    'field' => $field,
    'value' => $value,
    'relations' => $relations ?? [],
    'isEdit' => true
])
@endforeach
```

### 直接使用单个组件

如果需要单独使用某个组件：

```blade
{{-- 文本输入 --}}
@include('admin.components.form.text', [
    'field' => [
        'name' => 'username',
        'type' => 'text',
        'label' => '用户名',
        'required' => true,
        'placeholder' => '请输入用户名',
        'default' => ''
    ],
    'value' => null
])

{{-- 下拉选择 --}}
@include('admin.components.form.select', [
    'field' => [
        'name' => 'status',
        'type' => 'select',
        'label' => '状态',
        'required' => true,
        'options' => [
            ['value' => '1', 'label' => '启用'],
            ['value' => '0', 'label' => '禁用']
        ]
    ],
    'value' => '1',
    'relations' => [],
    'isEdit' => false
])
```

## 字段配置格式

### 基础字段配置

```php
$field = [
    'name' => 'field_name',           // 字段名（必需）
    'type' => 'text',                 // 字段类型（必需）
    'label' => '字段标签',            // 标签文本（必需）
    'required' => true,               // 是否必填（可选）
    'placeholder' => '占位符',        // 占位符（可选）
    'help' => '帮助文本',             // 帮助文本（可选）
    'default' => '默认值',            // 默认值（可选）
];
```

### 不同类型字段的特定配置

#### text, email, password
```php
$field = [
    'name' => 'email',
    'type' => 'email',
    'label' => '邮箱',
    'placeholder' => '请输入邮箱地址',
];
```

#### textarea
```php
$field = [
    'name' => 'content',
    'type' => 'textarea',
    'label' => '内容',
    'rows' => 10,                     // 行数（可选，默认 4）
];
```

#### select, relation
```php
// 使用 options
$field = [
    'name' => 'status',
    'type' => 'select',
    'label' => '状态',
    'options' => [
        ['value' => '1', 'label' => '启用'],
        ['value' => '0', 'label' => '禁用']
    ],
    // 或者使用关联数组格式
    'options' => [
        '1' => '启用',
        '0' => '禁用'
    ],
];

// 使用 relation
$field = [
    'name' => 'user_id',
    'type' => 'relation',
    'label' => '用户',
    'relation' => [
        'model' => 'User',
        'multiple' => false,           // 是否多选
    ],
];
```

#### radio, checkbox
```php
$field = [
    'name' => 'gender',
    'type' => 'radio',
    'label' => '性别',
    'options' => [
        ['value' => 'male', 'label' => '男'],
        ['value' => 'female', 'label' => '女']
    ],
    // 或者使用关联数组格式
    'options' => [
        'male' => '男',
        'female' => '女'
    ],
];
```

#### switch
```php
$field = [
    'name' => 'is_show',
    'type' => 'switch',
    'label' => '是否显示',
    'options' => [                    // 可选，默认是/否
        '0' => '隐藏',
        '1' => '显示'
    ],
    'default' => '1',
];
```

#### image
```php
$field = [
    'name' => 'avatar',
    'type' => 'image',
    'label' => '头像',
    'default' => '/uploads/avatar.jpg',  // 当前图片URL（编辑时）
];
```

#### images
```php
$field = [
    'name' => 'gallery',
    'type' => 'images',
    'label' => '图片画廊',
    'default' => [                       // 当前图片数组（编辑时）
        '/uploads/img1.jpg',
        '/uploads/img2.jpg'
    ],
    // 或者 JSON 字符串格式
    'default' => '["/uploads/img1.jpg","/uploads/img2.jpg"]',
];
```

#### rich_text
```php
$field = [
    'name' => 'content',
    'type' => 'rich_text',
    'label' => '内容',
    'rows' => 10,                         // 行数（可选，默认 10）
];
```

#### number_range
```php
$field = [
    'name' => 'view_count',
    'type' => 'number_range',
    'label' => '浏览量范围',
    'default' => '{"min": 10, "max": 100}',  // JSON 字符串格式
];
```

## 组件特性

### 1. 自动处理关联数组格式的 options

组件会自动处理两种格式的 options：

```php
// 格式1：标准格式
'options' => [
    ['value' => '1', 'label' => '启用'],
    ['value' => '0', 'label' => '禁用']
]

// 格式2：关联数组格式（自动转换）
'options' => [
    '1' => '启用',
    '0' => '禁用'
]
```

### 2. 支持多选 relation

```php
$field = [
    'name' => 'user_ids',           // 以 _ids 结尾自动识别为多选
    'type' => 'relation',
    'relation' => [
        'model' => 'User',
        'multiple' => true,         // 或 '1' 或 1
    ],
];
```

### 3. 图片预览功能

image 和 images 组件自动支持图片预览，无需额外配置。

### 4. 数字区间自动合并

number_range 组件会自动将 min 和 max 合并为 JSON 字符串格式。

## JavaScript 支持

在页面中需要包含以下 JavaScript 代码以支持：
- 图片预览功能
- number_range 字段处理
- 文件上传提交

这些代码已经在 `create.blade.php` 和 `edit.blade.php` 中包含。

## 注意事项

1. **组件路径**：所有组件使用 `admin.components.form.*` 路径引用
2. **value 参数**：编辑页面需要传递当前值，创建页面传递 `null`
3. **relations 参数**：relation 类型字段需要传递关联数据数组
4. **isEdit 参数**：用于区分创建和编辑模式（某些组件可能需要此参数）
5. **文件上传**：包含文件上传字段的表单会自动使用 FormData 提交
6. **number_range**：会自动将 min 和 max 合并为 JSON 字符串格式存储

## 示例

完整的使用示例请参考：
- `storage/view/admin/system/universal/create.blade.php`
- `storage/view/admin/system/universal/edit.blade.php`

