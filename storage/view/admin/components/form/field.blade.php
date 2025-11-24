{{--
统一表单字段组件

参数:
- $field: 字段配置数组
  - name: 字段名
  - type: 字段类型
  - label: 标签文本
  - required: 是否必填
  - placeholder: 占位符
  - help: 帮助文本
  - [其他字段特定属性]
- $value: 当前值（可选，用于编辑页面）
- $relations: 关联数据数组（可选，用于 relation 类型）
- $isEdit: 是否编辑模式（默认 false）
--}}
<div class="mb-3">
    {{-- switch 类型字段不显示外层 label，因为 switch 组件内部已经包含 label --}}
    @if($field['type'] !== 'switch')
    <label for="{{ $field['name'] }}" class="form-label">
        {{ $field['label'] }}
        @if($field['required'] ?? false)
        <span class="text-danger">*</span>
        @endif
    </label>
    @endif

    @if(in_array($field['type'], ['text', 'email', 'password']))
        @include('admin.components.form.text', ['field' => $field, 'value' => $value ?? null])

    @elseif($field['type'] === 'number')
        @include('admin.components.form.number', ['field' => $field, 'value' => $value ?? null])

    @elseif($field['type'] === 'textarea')
        @include('admin.components.form.textarea', ['field' => $field, 'value' => $value ?? null])

    @elseif(in_array($field['type'], ['select', 'relation']))
        @include('admin.components.form.select', [
            'field' => $field,
            'value' => $value ?? null,
            'relations' => $relations ?? [],
            'isEdit' => $isEdit ?? false,
            'model' => $model ?? ''
        ])

    @elseif($field['type'] === 'radio')
        @include('admin.components.form.radio', ['field' => $field, 'value' => $value ?? null])

    @elseif($field['type'] === 'checkbox')
        @include('admin.components.form.checkbox', ['field' => $field, 'value' => $value ?? null])

    @elseif($field['type'] === 'date')
        @include('admin.components.form.date', ['field' => $field, 'value' => $value ?? null])

    @elseif($field['type'] === 'datetime')
        @include('admin.components.form.datetime', ['field' => $field, 'value' => $value ?? null])

    @elseif($field['type'] === 'switch')
        @include('admin.components.form.switch', ['field' => $field, 'value' => $value ?? null])

    @elseif($field['type'] === 'color')
        @include('admin.components.form.color', ['field' => $field, 'value' => $value ?? null])

    @elseif($field['type'] === 'image')
        @include('admin.components.form.image', ['field' => $field, 'value' => $value ?? null])

    @elseif($field['type'] === 'images')
        @include('admin.components.form.images', ['field' => $field, 'value' => $value ?? null])

    @elseif($field['type'] === 'rich_text')
        @include('admin.components.form.rich_text', ['field' => $field, 'value' => $value ?? null])

    @elseif($field['type'] === 'number_range')
        @include('admin.components.form.number_range', ['field' => $field, 'value' => $value ?? null])

    @else
        <div class="alert alert-warning">未知字段类型: {{ $field['type'] }}</div>
    @endif

    @if(!empty($field['help']))
    <div class="form-text" @if($field['type'] === 'switch') style="margin-left: 120px; margin-top: 0.25rem;" @endif>{{ $field['help'] }}</div>
    @endif
</div>

