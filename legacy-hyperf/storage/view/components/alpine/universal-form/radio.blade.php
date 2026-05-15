{{--
单选框组件

参数：
- $name: 字段名称（必需）
- $label: 标签文本
- $options: 选项数组 [{value, label}]（必需）
- $value: 选中的值
- $required: 是否必填（默认: false）
- $disabled: 是否禁用（默认: false）
- $inline: 是否内联显示（默认: true）
- $help: 帮助文本
- $error: 错误信息

使用方式：
@include('components.alpine.universal-form.radio', [
    'name' => 'gender',
    'label' => '性别',
    'options' => [
        ['value' => '1', 'label' => '男'],
        ['value' => '2', 'label' => '女']
    ],
    'required' => true
])
--}}

@php
    $name = $name ?? '';
    $label = $label ?? '';
    $options = $options ?? [];
    $value = $value ?? ($oldInput[$name] ?? '');
    $required = $required ?? false;
    $disabled = $disabled ?? false;
    $inline = $inline ?? true;
    $help = $help ?? '';
    $error = $error ?? ($errors[$name] ?? []);
    $id = $id ?? $name;
@endphp

<div class="mb-3">
    @if($label)
        <label class="form-label">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif
    
    <div class="{{ $inline ? 'd-flex gap-3' : '' }}">
        @foreach($options as $index => $option)
            @php
                $radioId = $id . '_' . $index;
            @endphp
            <div class="form-check">
                <input
                    type="radio"
                    name="{{ $name }}"
                    id="{{ $radioId }}"
                    class="form-check-input @if(is_array($error) && count($error) > 0) is-invalid @endif"
                    value="{{ $option['value'] }}"
                    @if($value == $option['value']) checked @endif
                    @if($required) required @endif
                    @if($disabled) disabled @endif
                >
                <label class="form-check-label" for="{{ $radioId }}">
                    {{ $option['label'] }}
                </label>
            </div>
        @endforeach
    </div>
    
    @if(is_array($error) && count($error) > 0)
        <div class="invalid-feedback d-block">{{ $error[0] }}</div>
    @endif
    
    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
