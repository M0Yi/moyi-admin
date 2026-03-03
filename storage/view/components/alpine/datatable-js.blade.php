{{--
DataTable 数据表格组件入口

功能：
- 引入 DataTable JS 逻辑
- 提供创建表格实例的方法

使用方式：
{{-- 引入 DataTable 逻辑 --}}
@include('components.alpine.datatable-js')

{{-- 在页面中使用 --}}
<div id="user-table"></div>

<script>
const table = DataTable.create({
    api: '/api/admin/users',
    columns: [
        { title: 'ID', data: 'id' },
        { title: '用户名', data: 'username' },
        { title: '邮箱', data: 'email' },
        { 
            title: '状态', 
            data: 'status',
            render: (row) => row.status == 1 ? '启用' : '禁用'
        },
        {
            title: '操作',
            render: (row) => `
                <button class="btn btn-sm btn-info" onclick="editUser(${row.id})">编辑</button>
                <button class="btn btn-sm btn-danger" onclick="deleteUser(${row.id})">删除</button>
            `
        }
    ],
    onLoad: (data) => {
        console.log('数据加载完成', data);
    },
    onDelete: (ids) => {
        console.log('删除成功', ids);
    }
});

// 挂载到容器
table.mount('#user-table');
</script>
--}}

@php
    $path = $path ?? '/js/components/datatable/index.js';
    $attributes = $attributes ?? [];
    
    // 构建属性字符串
    $attrString = '';
    if (!empty($attributes)) {
        $attrPairs = [];
        foreach ($attributes as $key => $value) {
            $attrPairs[] = htmlspecialchars($key, ENT_QUOTES) . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
        }
        $attrString = ' ' . implode(' ', $attrPairs);
    }
@endphp

{{-- DataTable 逻辑脚本 --}}
<script src="{{ '/' . ltrim($path, '/') }}"{{ $attrString }}></script>
