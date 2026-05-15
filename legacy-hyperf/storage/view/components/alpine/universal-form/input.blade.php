{{--
输入框组件

参数：
- $name: 字段名称（必需）
- $label: 标签文本
- $type: 输入类型（默认: text）
- $value: 字段值
- $placeholder: 占位符
- $required: 是否必填（默认: false）
- $disabled: 是否禁用（默认: false）
- $readonly: 是否只读（默认: false）
- $help: 帮助文本
- $error: 错误信息

使用方式：
@include('components.alpine.universal-form.input', [
    'name' => 'username',
    'label' => '用户名',
    'required' => true,
    'placeholder' => '请输入用户名'
])
--}}

@php
    $name = $name ?? '';
    $label = $label ?? '';
    $type = $type ?? 'text';
    $value = $value ?? ($oldInput[$name] ?? '');
    $placeholder = $placeholder ?? '';
    $required = $required ?? false;
    $disabled = $disabled ?? false;
    $readonly = $readonly ?? false;
    $help = $help ?? '';
    $error = $error ?? ($errors[$name] ?? []);
    $id = $id ?? $name;
    $class = $class ?? '';
@endphp

<div class="mb-3">
    @if($label)
        <label class="form-label" for="{{ $id }}">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif
    
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $id }}"
        class="form-control @if(is_array($error) && count($error) > 0) is-invalid @endif {{ $class }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        @if($type === 'number') step="any" @endif
    >
    
    @if(is_array($error) && count($error) > 0)
        <div class="invalid-feedback">{{ $error[0] }}</div>
    @endif
    
    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
