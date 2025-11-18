{{--
下拉选择组件（支持 select 和 relation）
使用 Tom Select 实现搜索和分页功能

参数:
- $field: 字段配置数组
  - name: 字段名
  - type: 字段类型 (select, relation)
  - label: 标签文本
  - required: 是否必填
  - options: 选项数组（可选）
  - relation: 关联配置（可选）
- $value: 当前值（可选，用于编辑页面）
- $relations: 关联数据数组（可选，用于 relation 类型）
- $isEdit: 是否编辑模式（默认 false）
- $model: 模型名称（用于 relation 类型的 API 调用）
--}}
@php
    // 判断是否多选
    $isMultiple = false;
    if ($field['type'] === 'relation' && isset($field['relation'])) {
        // 处理 multiple 可能为空字符串的情况
        $multipleValue = $field['relation']['multiple'] ?? false;
        $isMultiple = $multipleValue === true || $multipleValue === 1 || $multipleValue === '1';
    } elseif (str_ends_with($field['name'], '_ids')) {
        $isMultiple = true;
    }
    
    // 处理当前值（可能是 JSON 字符串）
    $currentValue = $value ?? ($field['default'] ?? '');
    if ($isMultiple && is_string($currentValue)) {
        try {
            $currentValue = json_decode($currentValue, true);
        } catch (\Exception $e) {
            $currentValue = [];
        }
    }
    if (!is_array($currentValue)) {
        $currentValue = $currentValue ? [$currentValue] : [];
    }
    
    // 判断是否为 relation 类型且需要异步加载
    $isRelationType = $field['type'] === 'relation' && !empty($field['relation']);
    $useAsync = $isRelationType; // relation 类型使用异步加载
    
    // 处理静态选项（用于 select 类型或 relation 类型的初始值）
    $optionsArray = [];
    if (!empty($field['options'])) {
        foreach ($field['options'] as $key => $optionValue) {
            if (is_array($optionValue)) {
                $optionsArray[] = $optionValue;
            } else {
                $optionsArray[] = ['value' => $key, 'label' => $optionValue];
            }
        }
    } elseif (!$useAsync && !empty($field['relation']) && !empty($relations[$field['name']])) {
        // 如果不是异步加载，使用静态选项
        foreach ($relations[$field['name']] as $option) {
            $optionsArray[] = [
                'value' => (string)$option->value,
                'label' => $option->label
            ];
        }
    }
    
    // 获取模型名称（用于构建 API URL）
    $modelName = $model ?? '';
@endphp

<select
    class="form-select tom-select-{{ $field['name'] }}"
    id="{{ $field['name'] }}"
    name="{{ $field['name'] }}{{ $isMultiple ? '[]' : '' }}"
    @if($isMultiple) multiple @endif
    @if($field['required'] ?? false) required @endif
    data-field-name="{{ $field['name'] }}"
    @if($isRelationType) data-model="{{ $modelName }}" @endif
    @if($useAsync) data-async="true" @endif
>
    @if(!$isMultiple && !$useAsync)
    <option value="">请选择</option>
    @endif
    
    @if(!$useAsync && !empty($optionsArray))
        @foreach($optionsArray as $option)
        <option value="{{ $option['value'] }}"
                @if($isMultiple ? in_array($option['value'], $currentValue) : ($currentValue == $option['value'])) selected @endif>
            {{ $option['label'] }}
        </option>
        @endforeach
    @elseif($useAsync && !empty($currentValue))
        {{-- 异步加载时，预加载已选中的选项 --}}
        @if($isMultiple)
            @foreach($currentValue as $val)
            <option value="{{ $val }}" selected>{{ $val }}</option>
            @endforeach
        @else
            <option value="{{ $currentValue[0] ?? '' }}" selected>{{ $currentValue[0] ?? '' }}</option>
        @endif
    @endif
</select>

@if($useAsync)
<style>
/* Tom Select 样式优化 */
.tom-select-{{ $field['name'] }}.ts-wrapper {
    position: relative;
}

.tom-select-{{ $field['name'] }} .ts-control {
    min-height: 38px;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.tom-select-{{ $field['name'] }} .ts-control:hover {
    border-color: #adb5bd;
}

.tom-select-{{ $field['name'] }} .ts-control.focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
}

/* 搜索框样式优化 */
.tom-select-{{ $field['name'] }} .ts-dropdown {
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border: 1px solid #e5e7eb;
    margin-top: 0.25rem;
}

.tom-select-{{ $field['name'] }} .ts-dropdown .dropdown-input {
    padding: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.tom-select-{{ $field['name'] }} .ts-dropdown .dropdown-input input {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.tom-select-{{ $field['name'] }} .ts-dropdown .dropdown-input input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
}

.tom-select-{{ $field['name'] }} .ts-dropdown .dropdown-input input::placeholder {
    color: #9ca3af;
    font-size: 0.875rem;
}

/* 选项列表样式 */
.tom-select-{{ $field['name'] }} .ts-dropdown-content {
    max-height: 300px;
    overflow-y: auto;
}

.tom-select-{{ $field['name'] }} .ts-dropdown-content .option {
    padding: 0.625rem 0.75rem;
    transition: all 0.15s ease;
    cursor: pointer;
}

.tom-select-{{ $field['name'] }} .ts-dropdown-content .option:hover {
    background-color: #f3f4f6;
}

.tom-select-{{ $field['name'] }} .ts-dropdown-content .option.active {
    background-color: #eef2ff;
    color: #6366f1;
    font-weight: 500;
}

.tom-select-{{ $field['name'] }} .ts-dropdown-content .option.active.selected {
    background-color: #6366f1;
    color: white;
}

/* 加载状态 */
.tom-select-{{ $field['name'] }} .ts-dropdown .loading {
    padding: 1rem;
    text-align: center;
    color: #6b7280;
    font-size: 0.875rem;
}

.tom-select-{{ $field['name'] }} .ts-dropdown .no-results {
    padding: 1rem;
    text-align: center;
    color: #9ca3af;
    font-size: 0.875rem;
}

/* Placeholder 样式 */
.tom-select-{{ $field['name'] }} .ts-control .placeholder {
    color: #9ca3af;
    font-size: 0.875rem;
}

/* 选中项样式 - 统一多选和单选的显示 */
.tom-select-{{ $field['name'] }} .ts-control .item {
    background-color: #eef2ff;
    color: #6366f1;
    border: 1px solid #c7d2fe;
    border-radius: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    margin: 0.125rem;
    display: inline-flex;
    align-items: center;
    line-height: 1.5;
}

/* 单选模式：选中项以标签形式显示（统一UI） */
.tom-select-{{ $field['name'] }}.single .ts-control .item {
    background-color: #eef2ff;
    color: #6366f1;
    border: 1px solid #c7d2fe;
    border-radius: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    margin: 0.125rem;
}

/* 多选模式：多个标签 */
.tom-select-{{ $field['name'] }}.multi .ts-control .item {
    background-color: #eef2ff;
    color: #6366f1;
    border: 1px solid #c7d2fe;
    border-radius: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    margin: 0.125rem;
}

/* 单选模式：输入框样式（当有选中值时） */
.tom-select-{{ $field['name'] }}.single .ts-control.has-items {
    padding: 0.125rem;
}

/* 单选模式：输入框样式（当没有选中值时） */
.tom-select-{{ $field['name'] }}.single .ts-control:not(.has-items) {
    padding: 0.5rem 0.75rem;
}

/* 清除按钮 */
.tom-select-{{ $field['name'] }} .ts-control .clear-button {
    color: #9ca3af;
    cursor: pointer;
    transition: color 0.2s ease;
    padding: 0.25rem;
    margin-left: 0.25rem;
}

.tom-select-{{ $field['name'] }} .ts-control .clear-button:hover {
    color: #6366f1;
}

/* 单选模式：删除按钮样式 */
.tom-select-{{ $field['name'] }}.single .ts-control .item [data-ts-item] .remove {
    border-left: 1px solid #c7d2fe;
    padding-left: 0.5rem;
    margin-left: 0.5rem;
    color: #6366f1;
    cursor: pointer;
}

.tom-select-{{ $field['name'] }}.single .ts-control .item [data-ts-item] .remove:hover {
    color: #4f46e5;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectElement = document.getElementById('{{ $field['name'] }}');
    if (!selectElement) return;
    
    const modelName = selectElement.dataset.model || '';
    const fieldName = selectElement.dataset.fieldName || '';
    const isMultiple = selectElement.hasAttribute('multiple');
    const fieldLabel = '{{ $field['label'] ?? '' }}';
    
    // 生成更友好的 placeholder
    const placeholder = isMultiple 
        ? `点击选择${fieldLabel}（可多选，输入关键词或ID搜索）` 
        : `点击选择${fieldLabel}（输入关键词或ID搜索）`;
    
    // 初始化 Tom Select
    const tomSelect = new TomSelect(selectElement, {
        plugins: ['dropdown_input'],
        valueField: 'value',
        labelField: 'text',
        searchField: 'text',
        maxOptions: null,
        placeholder: placeholder,
        allowEmptyOption: !isMultiple,
        create: false,
        multiple: isMultiple,
        closeAfterSelect: !isMultiple,
        dropdownParent: 'body',
        // 统一多选和单选的渲染方式
        render: {
            option: function(data, escape) {
                return '<div class="option-item">' + escape(data.text) + '</div>';
            },
            item: function(data, escape) {
                // 多选和单选都使用标签样式
                return '<div class="item-label">' + escape(data.text) + '</div>';
            },
            option_create: function(data, escape) {
                return '<div class="create">添加 <strong>' + escape(data.input) + '</strong>&hellip;</div>';
            },
            no_results: function() {
                return '<div class="no-results">未找到匹配项</div>';
            },
            loading: function() {
                return '<div class="loading">搜索中...</div>';
            }
        },
        
        // 异步加载配置
        load: function(query, callback) {
            // 显示加载状态
            const dropdown = this.dropdown;
            if (dropdown && !dropdown.querySelector('.loading')) {
                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'loading';
                loadingDiv.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>搜索中...';
                dropdown.appendChild(loadingDiv);
            }
            
            const url = window.adminRoute('universal/' + modelName + '/search-relation-options');
            const params = new URLSearchParams({
                field: fieldName,
                search: query || '',
                page: this.page || 1,
                per_page: 20
            });
            
            fetch(url + '?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    // 移除加载状态
                    const loadingEl = dropdown?.querySelector('.loading');
                    if (loadingEl) {
                        loadingEl.remove();
                    }
                    
                    if (data.code === 200) {
                        const results = data.data.results || [];
                        
                        // 如果没有结果且不是空查询，显示提示
                        if (results.length === 0 && query) {
                            if (dropdown && !dropdown.querySelector('.no-results')) {
                                const noResultsDiv = document.createElement('div');
                                noResultsDiv.className = 'no-results';
                                noResultsDiv.innerHTML = '<i class="bi bi-search me-2"></i>未找到匹配项，请尝试其他关键词';
                                dropdown.appendChild(noResultsDiv);
                            }
                        } else {
                            // 移除无结果提示
                            const noResultsEl = dropdown?.querySelector('.no-results');
                            if (noResultsEl) {
                                noResultsEl.remove();
                            }
                        }
                        
                        callback(results);
                        
                        // 如果有更多数据，增加页码
                        if (data.data.pagination.more) {
                            this.page = (this.page || 1) + 1;
                        } else {
                            this.page = null;
                        }
                    } else {
                        callback([]);
                    }
                })
                .catch(error => {
                    console.error('加载选项失败:', error);
                    // 移除加载状态
                    const loadingEl = dropdown?.querySelector('.loading');
                    if (loadingEl) {
                        loadingEl.remove();
                    }
                    
                    // 显示错误提示
                    if (dropdown && !dropdown.querySelector('.error-message')) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'no-results';
                        errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>加载失败，请稍后重试';
                        dropdown.appendChild(errorDiv);
                    }
                    
                    callback([]);
                });
        },
        
        // 打开下拉框时
        onDropdownOpen: function() {
            // 自动聚焦搜索框并设置 placeholder
            setTimeout(() => {
                const searchInput = this.dropdown?.querySelector('.dropdown-input input');
                if (searchInput) {
                    searchInput.placeholder = '输入关键词搜索...';
                    searchInput.focus();
                }
            }, 100);
        },
        
        // 搜索时
        onType: function(str) {
            // 重置页码
            this.page = 1;
            // 移除无结果提示
            const noResultsEl = this.dropdown?.querySelector('.no-results');
            if (noResultsEl) {
                noResultsEl.remove();
            }
        },
        
        // 初始化完成后添加CSS类
        onInitialize: function() {
            // 添加单选或多选的CSS类
            const wrapper = this.wrapper;
            if (wrapper) {
                if (isMultiple) {
                    wrapper.classList.add('multi');
                } else {
                    wrapper.classList.add('single');
                }
            }
            
            const selectedValues = Array.from(selectElement.selectedOptions).map(opt => opt.value).filter(v => v);
            if (selectedValues.length > 0) {
                // 预加载已选中的选项：通过值查找
                const url = window.adminRoute('universal/' + modelName + '/search-relation-options');
                const params = new URLSearchParams({
                    field: fieldName,
                    search: '',
                    page: 1,
                    per_page: 100
                });
                
                // 添加 value 参数（多个值）
                selectedValues.forEach(val => {
                    params.append('value[]', val);
                });
                
                fetch(url + '?' + params.toString())
                    .then(response => response.json())
                    .then(data => {
                        if (data.code === 200) {
                            // 找到已选中的选项并添加到选项列表
                            selectedValues.forEach(val => {
                                const option = data.data.results.find(r => r.value === val);
                                if (option && !this.options[val]) {
                                    this.addOption(option);
                                } else if (!option) {
                                    // 如果没找到，添加一个临时选项（使用值作为标签）
                                    this.addOption({ value: val, text: val });
                                }
                            });
                            // 设置选中值
                            this.setValue(selectedValues, true);
                        }
                    })
                    .catch(error => {
                        console.error('预加载选项失败:', error);
                        // 如果加载失败，添加临时选项
                        selectedValues.forEach(val => {
                            if (!this.options[val]) {
                                this.addOption({ value: val, text: val });
                            }
                        });
                        this.setValue(selectedValues, true);
                    });
            }
        }
    });
});
</script>
@else
{{-- 非异步加载的 select 类型，也使用 Tom Select 但配置更简单 --}}
<style>
/* Tom Select 样式优化（静态选项） */
.tom-select-{{ $field['name'] }}.ts-wrapper {
    position: relative;
}

.tom-select-{{ $field['name'] }} .ts-control {
    min-height: 38px;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.tom-select-{{ $field['name'] }} .ts-control:hover {
    border-color: #adb5bd;
}

.tom-select-{{ $field['name'] }} .ts-control.focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
}

.tom-select-{{ $field['name'] }} .ts-dropdown {
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border: 1px solid #e5e7eb;
    margin-top: 0.25rem;
}

.tom-select-{{ $field['name'] }} .ts-dropdown .dropdown-input {
    padding: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.tom-select-{{ $field['name'] }} .ts-dropdown .dropdown-input input {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.tom-select-{{ $field['name'] }} .ts-dropdown .dropdown-input input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
}

.tom-select-{{ $field['name'] }} .ts-dropdown .dropdown-input input::placeholder {
    color: #9ca3af;
    font-size: 0.875rem;
}

.tom-select-{{ $field['name'] }} .ts-dropdown-content {
    max-height: 300px;
    overflow-y: auto;
}

.tom-select-{{ $field['name'] }} .ts-dropdown-content .option {
    padding: 0.625rem 0.75rem;
    transition: all 0.15s ease;
    cursor: pointer;
}

.tom-select-{{ $field['name'] }} .ts-dropdown-content .option:hover {
    background-color: #f3f4f6;
}

.tom-select-{{ $field['name'] }} .ts-dropdown-content .option.active {
    background-color: #eef2ff;
    color: #6366f1;
    font-weight: 500;
}

.tom-select-{{ $field['name'] }} .ts-dropdown-content .option.active.selected {
    background-color: #6366f1;
    color: white;
}

.tom-select-{{ $field['name'] }} .ts-control .placeholder {
    color: #9ca3af;
    font-size: 0.875rem;
}

/* 统一选中项标签样式 */
.tom-select-{{ $field['name'] }} .ts-control .item {
    background-color: #eef2ff;
    color: #6366f1;
    border: 1px solid #c7d2fe;
    border-radius: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    margin: 0.125rem;
    display: inline-flex;
    align-items: center;
    line-height: 1.5;
}

.tom-select-{{ $field['name'] }} .ts-control .item .item-label {
    display: inline-block;
    line-height: 1.5;
}

/* 单选模式：确保选中项显示为标签 */
.tom-select-{{ $field['name'] }}.single .ts-control {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    min-height: 38px;
    padding: 0.25rem;
}

.tom-select-{{ $field['name'] }}.single .ts-control.has-items {
    padding: 0.125rem;
}

.tom-select-{{ $field['name'] }}.single .ts-control:not(.has-items) {
    padding: 0.5rem 0.75rem;
}

/* 多选模式：选中项标签 */
.tom-select-{{ $field['name'] }}.multi .ts-control {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    min-height: 38px;
    padding: 0.25rem;
}

/* 清除按钮 */
.tom-select-{{ $field['name'] }} .ts-control .clear-button {
    color: #9ca3af;
    cursor: pointer;
    transition: color 0.2s ease;
    padding: 0.25rem;
    margin-left: 0.25rem;
}

.tom-select-{{ $field['name'] }} .ts-control .clear-button:hover {
    color: #6366f1;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectElement = document.getElementById('{{ $field['name'] }}');
    if (!selectElement) return;
    
    const isMultiple = selectElement.hasAttribute('multiple');
    const fieldLabel = '{{ $field['label'] ?? '' }}';
    
    // 生成更友好的 placeholder
    const placeholder = isMultiple 
        ? `点击选择${fieldLabel}（可多选，输入关键词或ID搜索）` 
        : `点击选择${fieldLabel}（输入关键词或ID搜索）`;
    
    // 初始化 Tom Select（静态选项）
    const tomSelect = new TomSelect(selectElement, {
        plugins: ['dropdown_input'],
        placeholder: placeholder,
        allowEmptyOption: !isMultiple,
        create: false,
        multiple: isMultiple,
        closeAfterSelect: !isMultiple,
        searchField: ['text'],
        // 统一多选和单选的渲染方式
        render: {
            option: function(data, escape) {
                return '<div class="option-item">' + escape(data.text) + '</div>';
            },
            item: function(data, escape) {
                // 多选和单选都使用标签样式
                return '<div class="item-label">' + escape(data.text) + '</div>';
            },
            option_create: function(data, escape) {
                return '<div class="create">添加 <strong>' + escape(data.input) + '</strong>&hellip;</div>';
            },
            no_results: function() {
                return '<div class="no-results">未找到匹配项</div>';
            },
            loading: function() {
                return '<div class="loading">搜索中...</div>';
            }
        },
        
        // 初始化完成后添加CSS类
        onInitialize: function() {
            // 添加单选或多选的CSS类
            const wrapper = this.wrapper;
            if (wrapper) {
                if (isMultiple) {
                    wrapper.classList.add('multi');
                } else {
                    wrapper.classList.add('single');
                }
            }
        },
        
        // 打开下拉框时自动聚焦搜索框并设置 placeholder
        onDropdownOpen: function() {
            setTimeout(() => {
                const searchInput = this.dropdown?.querySelector('.dropdown-input input');
                if (searchInput) {
                    searchInput.placeholder = '输入关键词或ID搜索...';
                    searchInput.focus();
                }
            }, 100);
        }
    });
});
</script>
@endif
