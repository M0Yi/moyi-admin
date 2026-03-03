{{--
下拉选择框组件

参数：
- $name: 字段名称（必需）
- $label: 标签文本
- $options: 选项数组 [{value, label}]（必需）
- $value: 选中的值
- $placeholder: 占位符
- $required: 是否必填（默认: false）
- $disabled: 是否禁用（默认: false）
- $help: 帮助文本
- $error: 错误信息
- $multiple: 是否多选（默认: false）

使用方式：
@include('components.alpine.universal-form.select', [
    'name' => 'status',
    'label' => '状态',
    'options' => [
        ['value' => '1', 'label' => '启用'],
        ['value' => '0', 'label' => '禁用']
    ],
    'required' => true
])
--}}

@php
    $name = $name ?? '';
    $label = $label ?? '';
    $options = $options ?? [];
    $value = $value ?? ($oldInput[$name] ?? '');
    $placeholder = $placeholder ?? '请选择';
    $required = $required ?? false;
    $disabled = $disabled ?? false;
    $help = $help ?? '';
    $error = $error ?? ($errors[$name] ?? []);
    $id = $id ?? $name;
    $multiple = $multiple ?? false;
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
    
    <select
        name="{{ $name }}{{ $multiple ? '[]' : '' }}"
        id="{{ $id }}"
        class="form-select @if(is_array($error) && count($error) > 0) is-invalid @endif {{ $class }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($multiple) multiple size="4" @endif
    >
        @if(!$multiple)
            <option value="">{{ $placeholder }}</option>
        @endif
        
        @foreach($options as $option)
            @php
                $selected = false;
                if ($multiple && is_array($value)) {
                    $selected = in_array($option['value'], $value);
                } else {
                    $selected = $value == $option['value'];
                }
            @endphp
            <option value="{{ $option['value'] }}" @if($selected) selected @endif>
                {{ $option['label'] }}
            </option>
        @endforeach
    </select>
    
    @if(is_array($error) && count($error) > 0)
        <div class="invalid-feedback d-block">{{ $error[0] }}</div>
    @endif
    
    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
