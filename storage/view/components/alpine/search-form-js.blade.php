{{--
SearchForm 搜索表单组件入口

功能：
- 引入 SearchForm JS 逻辑
- 提供创建表单实例的方法

使用方式：
{{-- 引入 SearchForm 逻辑 --}}
@include('components.alpine.search-form-js')

{{-- 在页面中使用 --}}
<div id="search-form"></div>

<script>
const form = SearchForm.create({
    fields: [
        { name: 'keyword', type: 'text', label: '关键词', placeholder: '搜索用户名/邮箱' },
        { 
            name: 'status', 
            type: 'select', 
            label: '状态',
            options: [
                { value: '', label: '全部' },
                { value: '1', label: '启用' },
                { value: '0', label: '禁用' }
            ]
        },
        { name: 'created_at', type: 'daterange', label: '创建日期' }
    ],
    layout: 'inline',
    onSearch: (data) => {
        console.log('搜索参数:', data);
        // 与 DataTable 联动
        table.search(data.keyword);
    },
    onReset: () => {
        console.log('表单已重置');
    }
});

form.mount('#search-form');
</script>
--}}

@php
    $path = $path ?? '/js/components/search-form/index.js';
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

{{-- SearchForm 逻辑脚本 --}}
<script src="{{ '/' . ltrim($path, '/') }}"{{ $attrString }}></script>
