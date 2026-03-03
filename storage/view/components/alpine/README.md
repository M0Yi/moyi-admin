# Alpine.js 相关组件

本目录包含与 Alpine.js 相关的内部 JavaScript 工具组件。

## 目录结构

```
components/alpine/
├── helper-js.blade.php           # 通用工具函数（无依赖）
├── request-js.blade.php          # HTTP 请求封装（依赖 helper-js）
├── toast-js.blade.php            # Toast 通知组件（依赖 request-js）
├── loading-js.blade.php          # Loading 组件（依赖 toast-js）
├── toast/                         # Toast 子组件
│   └── container.blade.php       # Toast 容器
└── loading/                       # Loading 子组件
    └── (自动注入 DOM)
```

## 使用方式

### 引入顺序

```blade
{{-- 1. 先引入 Alpine.js --}}
@include('components.plugin.alpinejs')

{{-- 2. 再引入内部工具组件 --}}
@include('components.alpine.helper-js')
@include('components.alpine.request-js')
@include('components.alpine.toast-js')
@include('components.alpine.loading-js')
```

## 组件说明

### helper-js

通用工具函数，提供 `$helper` 全局对象。

**功能特性：**
- 验证函数：手机号、邮箱、URL、身份证等
- 字符串处理：截取、替换、格式化
- 日期处理：格式化、相对时间
- 数字处理：千分位、文件大小、补零
- 浏览器检测：浏览器类型、移动设备、微信
- 本地存储：设置、获取、删除
- Cookie 操作：设置、获取、删除
- DOM 操作：位置、视口、滚动、防抖、节流
- 杂项：随机字符串、UUID、深拷贝、剪贴板

**使用示例：**
```javascript
// 验证
$helper.isMobile('13800138000');  // true
$helper.isEmail('test@test.com'); // true

// 日期
$helper.formatDate(new Date(), 'Y-m-d H:i:s'); // '2024-01-01 12:00:00'
$helper.relativeTime('2024-01-01 12:00:00');  // 'X 天前'

// 数字
$helper.formatNumber(1234567);   // '1,234,567'
$helper.formatFileSize(1024);     // '1 KB'

// 存储
$helper.setStorage('token', 'xxx', 3600);  // 1小时后过期
$helper.getStorage('token');

// DOM
$helper.debounce(fn, 300);  // 防抖
$helper.throttle(fn, 300);  // 节流
$helper.copyToClipboard('text');  // 复制
```

### request-js

HTTP 请求封装，提供 `$http` 全局对象。

**功能特性：**
- 自动携带 CSRF Token
- 自动处理 JWT Token
- 统一错误处理
- 自动显示 Toast 通知
- 统一 Loading 状态
- 文件上传/下载
- 链式调用（自定义配置）

**使用示例：**
```javascript
// GET 请求
const data = await $http.get('/api/users');

// POST 请求
await $http.post('/api/users', { name: '张三' });

// PUT 请求
await $http.put('/api/users/1', { name: '李四' });

// DELETE 请求
await $http.delete('/api/users/1');

// 文件上传
const result = await $http.upload('/api/upload', fileElement.files[0]);

// 文件下载
$http.download('/api/export', { ids: [1, 2, 3] });
```

### toast-js

Toast 通知组件，提供 `$toast` 全局对象。

**功能特性：**
- 支持多种类型：success、error、warning、info
- 自动消失（默认 3 秒）
- 手动关闭
- 多个 Toast 同时显示
- 位置可配置

**使用示例：**
```javascript
// 显示成功提示
$toast.success('操作成功');

// 显示错误提示
$toast.error('操作失败');

// 显示警告提示
$toast.warning('警告信息');

// 显示信息提示
$toast.info('提示信息');

// 自定义配置
$toast.config({
    duration: 5000,     // 显示时间（毫秒）
    position: 'top-end', // 位置
    maxToasts: 3,       // 最大同时显示数量
});

// 清除所有
$toast.clear();
```

### loading-js

Loading 组件，提供 `$loading` 全局对象。

**功能特性：**
- 全屏 Loading
- 自动控制（与 $http 集成）
- 引用计数（支持嵌套调用）
- 自定义文本

**使用示例：**
```javascript
// 显示 Loading
$loading.show('保存中...');

// 隐藏 Loading
$loading.hide();

// 强制隐藏（重置计数）
$loading.forceHide();

// 设置 Loading 文本
$loading.setText('正在提交...');

// 检查是否正在显示
if ($loading.isShowing()) {
    console.log('Loading 正在显示');
}
```

**自动控制：** `$http` 请求会自动显示/隐藏 Loading，无需手动调用。

## 与 Alpine.js 结合使用

```blade
{{-- 完整引入示例 --}}
@include('components.plugin.alpinejs')
@include('components.alpine.helper-js')
@include('components.alpine.request-js')

{{-- 在 Alpine.js 组件中使用 --}}
<div x-data="userPage()" x-init="loadUsers()">
    <table class="table">
        <template x-for="user in users" :key="user.id">
            <tr>
                <td x-text="user.id"></td>
                <td x-text="user.name"></td>
                <td>
                    <button @click="deleteUser(user.id)">删除</button>
                </td>
            </tr>
        </template>
    </table>
</div>

<script>
function userPage() {
    return {
        users: [],
        
        async loadUsers() {
            this.users = await $http.get('/api/admin/users');
        },
        
        async deleteUser(id) {
            if (!confirm('确定删除？')) return;
            await $http.delete('/api/admin/users/' + id);
            $toast.success('删除成功');
            this.loadUsers();
        }
    };
}
</script>
```

## 依赖关系

```
components.plugin.alpinejs (外部插件)
         │
         ▼
components.alpine.helper-js (通用工具)
         │
         ▼
components.alpine.request-js (HTTP 请求)
         │
         ▼
components.alpine.toast-js (Toast 通知)
         │
         ▼
components.alpine.loading-js (Loading 组件)
```

**注意：** 请按照依赖顺序引入组件。
