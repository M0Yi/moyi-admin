@extends('admin.layouts.admin')

@section('title', '添加' . ($config['title'] ?? '数据'))

@section('content')
@include('admin.common.styles')
<div class="container-fluid py-4">
    <!-- 页面标题 -->
    <div class="mb-3">
        <h6 class="mb-1 fw-bold">添加{{ $config['title'] ?? '数据' }}</h6>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ admin_route('dashboard') }}">首页</a></li>
                <li class="breadcrumb-item"><a href="#">系统管理</a></li>
                <li class="breadcrumb-item"><a href="{{ admin_route("universal/{$model}") }}">{{ $config['title'] ?? '数据' }}列表</a></li>
                <li class="breadcrumb-item active">添加</li>
            </ol>
        </nav>
    </div>

    <!-- 表单卡片 -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form id="createForm" onsubmit="submitForm(event)">
                        <div class="row">
                            @foreach($fields as $field)
                            @php
                                // 根据字段类型智能判断宽度
                                // 默认：所有字段在宽屏幕下一行两列（col-md-6）
                                // 只有需要整行的字段才使用 col-12
                                $colClass = 'col-md-6';
                                if (in_array($field['type'], ['textarea'])) {
                                    // 大字段：整行
                                    $colClass = 'col-12';
                                }
                            @endphp
                            <div class="{{ $colClass }}">
                                @include('admin.components.form.field', [
                                    'field' => $field,
                                    'value' => null,
                                    'relations' => $relations ?? [],
                                    'isEdit' => false,
                                    'model' => $model
                                ])
                            </div>
                            @endforeach
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 固定在底部的操作栏 -->
@include('admin.components.fixed-bottom-actions', [
    'infoText' => '填写完成后点击保存按钮提交',
    'cancelUrl' => admin_route("universal/{$model}"),
    'submitText' => '保存',
    'formId' => 'createForm',
    'submitBtnId' => 'submitBtn'
])

@include('admin.common.scripts')

<script>
// 图片预览功能
document.addEventListener('DOMContentLoaded', function() {
    // 单图上传预览
    document.querySelectorAll('.image-upload-input').forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewId = this.id + '_preview';
            const preview = document.getElementById(previewId);
            
            if (file && preview) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="预览" style="max-width: 200px; max-height: 200px;" class="img-thumbnail">';
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // 多图上传预览
    document.querySelectorAll('.images-upload-input').forEach(input => {
        input.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            const previewId = this.id + '_preview';
            const preview = document.getElementById(previewId);
            
            if (files.length > 0 && preview) {
                preview.innerHTML = '';
                files.forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = '预览';
                        img.style.cssText = 'max-width: 100px; max-height: 100px; object-fit: cover; margin-right: 5px;';
                        img.className = 'img-thumbnail';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                });
            }
        });
    });

    // number_range 字段处理：将 min 和 max 合并为 JSON 字符串
    document.querySelectorAll('input[type="number"][id$="_min"], input[type="number"][id$="_max"]').forEach(input => {
        input.addEventListener('change', function() {
            const fieldName = this.id.replace(/_min$|_max$/, '');
            const minInput = document.getElementById(fieldName + '_min');
            const maxInput = document.getElementById(fieldName + '_max');
            const hiddenInput = document.getElementById(fieldName);
            
            if (minInput && maxInput && hiddenInput) {
                const min = minInput.value || null;
                const max = maxInput.value || null;
                const range = {};
                if (min !== null) range.min = parseFloat(min);
                if (max !== null) range.max = parseFloat(max);
                hiddenInput.value = Object.keys(range).length > 0 ? JSON.stringify(range) : '';
            }
        });
    });
});

function submitForm(event) {
    event.preventDefault();

    const form = document.getElementById('createForm');
    const submitBtn = document.getElementById('submitBtn');
    const formData = new FormData(form);

    // 现在图片上传已经改为客户端直传，表单只提交URL，统一使用 JSON 提交
    submitFormAsJson(form, formData, submitBtn);
}

function submitFormAsJson(form, formData, submitBtn) {
    // 转换为 JSON
    const data = {};
    formData.forEach((value, key) => {
        // 跳过隐藏字段（number_range 的隐藏字段）
        if (key.endsWith('_current')) {
            return;
        }
        
        // 跳过文件输入框（图片上传已经改为客户端直传，只提交URL）
        const input = form.querySelector(`input[name="${key}"]`);
        if (input && input.type === 'file') {
            return;
        }
        
        // 处理数组字段（如 checkbox、多选 relation）
        if (key.endsWith('[]')) {
            const actualKey = key.slice(0, -2);
            if (!data[actualKey]) {
                data[actualKey] = [];
            }
            data[actualKey].push(value);
        } else {
            data[key] = value;
        }
    });

    // 处理 number_range 字段：将 min 和 max 合并为 JSON 字符串
    const processedFields = new Set();
    form.querySelectorAll('input[type="number"][id$="_min"], input[type="number"][id$="_max"]').forEach(input => {
        const fieldName = input.id.replace(/_min$|_max$/, '');
        if (processedFields.has(fieldName)) return;
        processedFields.add(fieldName);
        
        const minInput = form.querySelector(`#${fieldName}_min`);
        const maxInput = form.querySelector(`#${fieldName}_max`);
        
        if (minInput || maxInput) {
            const min = minInput ? minInput.value : null;
            const max = maxInput ? maxInput.value : null;
            const range = {};
            if (min !== null && min !== '') range.min = parseFloat(min);
            if (max !== null && max !== '') range.max = parseFloat(max);
            if (Object.keys(range).length > 0) {
                data[fieldName] = JSON.stringify(range);
            }
        }
    });

    // 将多选 relation 字段（_ids 结尾的数组字段）转换为 JSON 字符串
    Object.keys(data).forEach(key => {
        if (key.endsWith('_ids') && Array.isArray(data[key])) {
            data[key] = JSON.stringify(data[key]);
        }
    });
    
    // 处理多图上传字段：如果字段值是 JSON 字符串，保持原样；如果是数组，转换为 JSON
    form.querySelectorAll('input[type="hidden"][id$="_urls"]').forEach(input => {
        const fieldName = input.name;
        if (input.value) {
            try {
                // 尝试解析 JSON，如果已经是 JSON 字符串则保持原样
                const parsed = JSON.parse(input.value);
                if (Array.isArray(parsed)) {
                    data[fieldName] = input.value; // 保持 JSON 字符串格式
                }
            } catch (e) {
                // 不是 JSON，直接使用原值
                data[fieldName] = input.value;
            }
        }
    });

    // 禁用按钮
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 提交中...';

    // 提交数据
    fetch('{{ admin_route("universal/{$model}") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 200) {
            showToast('success', result.msg || '创建成功');
            setTimeout(() => {
                window.location.href = '{{ admin_route("universal/{$model}") }}';
            }, 1000);
        } else {
            showToast('danger', result.msg || '创建失败');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> 保存';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('danger', '提交失败，请稍后重试');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> 保存';
    });
}

function submitFormWithFiles(form, formData, submitBtn) {
    // 处理 number_range 字段：查找所有 _min 和 _max 字段
    const processedFields = new Set();
    form.querySelectorAll('input[type="number"][id$="_min"], input[type="number"][id$="_max"]').forEach(input => {
        const fieldName = input.id.replace(/_min$|_max$/, '');
        if (processedFields.has(fieldName)) return;
        processedFields.add(fieldName);
        
        const minInput = form.querySelector(`#${fieldName}_min`);
        const maxInput = form.querySelector(`#${fieldName}_max`);
        const hiddenInput = form.querySelector(`#${fieldName}`);
        
        if (minInput || maxInput) {
            const min = minInput ? minInput.value : null;
            const max = maxInput ? maxInput.value : null;
            const range = {};
            if (min !== null && min !== '') range.min = parseFloat(min);
            if (max !== null && max !== '') range.max = parseFloat(max);
            if (Object.keys(range).length > 0) {
                formData.set(fieldName, JSON.stringify(range));
            }
        }
    });

    // 移除 _current 字段（图片上传时保留旧图片的字段，不需要提交）
    const currentFields = form.querySelectorAll('input[name$="_current"]');
    currentFields.forEach(input => {
        formData.delete(input.name);
    });

    // 禁用按钮
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 提交中...';

    // 提交 FormData
    fetch('{{ admin_route("universal/{$model}") }}', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.code === 200) {
            showToast('success', result.msg || '创建成功');
            setTimeout(() => {
                window.location.href = '{{ admin_route("universal/{$model}") }}';
            }, 1000);
        } else {
            showToast('danger', result.msg || '创建失败');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> 保存';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('danger', '提交失败，请稍后重试');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> 保存';
    });
}
</script>
@endsection



