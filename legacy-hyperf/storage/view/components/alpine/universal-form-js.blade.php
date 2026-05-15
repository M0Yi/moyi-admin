{{--
UniversalForm JS 渲染引擎入口

这是一个纯 JS 表单渲染引擎，通过配置自动生成表单。

使用方式：
1. 在页面中引入此组件
2. 在 script 中创建表单实例

使用示例：
@include('components.alpine.universal-form-js')

<script>
// 创建表单
const form = UniversalForm.create({
    api: '/api/admin/users',
    method: 'POST',
    fields: [
        { name: 'username', type: 'text', label: '用户名', required: true },
        { name: 'email', type: 'email', label: '邮箱', required: true },
        { name: 'status', type: 'select', label: '状态', options: [
            { value: '1', label: '启用' },
            { value: '0', label: '禁用' }
        ]}
    ],
    onSuccess: () => {
        $toast.success('保存成功！');
    }
});

form.mount('#form-container');
</script>
--}}

@php
    $path = $path ?? '/js/components/universal-form/index.js';
    $attributes = $attributes ?? [];
    $attrString = '';
    
    foreach ($attributes as $key => $value) {
        $attrString .= ' ' . $key . '="' . $value . '"';
    }
@endphp

<script src="{{ '/' . ltrim($path, '/') }}"{{ $attrString }}></script>
