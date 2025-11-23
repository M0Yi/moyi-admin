{{--
表格列显示控制组件（基于 Bootstrap 5 Dropdown）

使用方法：
@include('admin.components.table-column-toggle', [
    'tableId' => 'myTable',           // 必填：表格的 ID
    'storageKey' => 'myTableColumns', // 必填：localStorage 存储的 key
    'columns' => [                     // 必填：列配置数组
        ['index' => 0, 'label' => 'ID', 'visible' => true],
        ['index' => 1, 'label' => '名称', 'visible' => true],
        ['index' => 2, 'label' => '状态', 'visible' => false],
    ]
])

注意事项：
1. 表格的 <table> 标签必须有 id 属性
2. 表格的 <th> 和 <td> 必须有 data-column 属性，值为列索引
3. 例如：<th data-column="0">ID</th>
4. 例如：<td data-column="0">{{ $item->id }}</td>
--}}

<!-- Bootstrap 5 Dropdown 列显示控制 -->
<div class="btn-group">
    <button
        class="btn btn-outline-secondary px-3 py-2 d-flex align-items-center gap-2"
        style="border-radius: 10px; border-width: 1.5px; transition: all 0.2s ease;"
        type="button"
        id="columnToggleBtn_{{ $tableId }}"
        data-bs-toggle="dropdown"
        data-bs-auto-close="outside"
        aria-expanded="false"
        title="列显示设置"
    >
        <i class="bi bi-eye" id="columnToggleIcon_{{ $tableId }}"></i>
        <i class="bi bi-chevron-down" id="columnToggleArrow_{{ $tableId }}" style="font-size: 0.75rem; opacity: 0.6; transition: transform 0.2s ease;"></i>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow"
         style="min-width: 280px;
                border-radius: 12px;
                margin-top: 8px;
                border: 2px solid #e9ecef;
                padding: 0;
                overflow: hidden;">
        <!-- 标题区域 -->
        <div class="bg-light" style="padding: 12px 16px; border-bottom: 2px solid #e9ecef;">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-columns-gap text-primary"></i>
                <span>列显示设置</span>
            </h6>
        </div>

        <!-- 列选项（可滚动） -->
        <div style="max-height: 350px; overflow-y: auto; padding: 8px;">
            @foreach($columns as $column)
                <div class="form-check py-2 px-2"
                     style="border-radius: 6px; transition: background-color 0.15s ease;"
                     onmouseover="this.style.backgroundColor='#f8f9fa'"
                     onmouseout="this.style.backgroundColor='transparent'">
                    <input
                        class="form-check-input column-toggle-checkbox"
                        type="checkbox"
                        id="col_{{ $tableId }}_{{ $column['index'] }}"
                        data-column="{{ $column['index'] }}"
                        {{ $column['visible'] ? 'checked' : '' }}
                        style="cursor: pointer;"
                    >
                    <label class="form-check-label w-100"
                           for="col_{{ $tableId }}_{{ $column['index'] }}"
                           style="cursor: pointer; user-select: none;">
                        {{ $column['label'] }}
                    </label>
                </div>
            @endforeach
        </div>

        <!-- 底部操作区 -->
        <div class="bg-light" style="padding: 12px 16px; border-top: 2px solid #e9ecef;">
            <button type="button"
                    class="btn btn-sm btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-2"
                    id="resetColumnsBtn_{{ $tableId }}"
                    style="border-radius: 8px; font-weight: 500; padding: 8px;">
                <i class="bi bi-arrow-clockwise"></i>
                <span>重置为默认</span>
            </button>
        </div>
    </div>
</div>

<style>
/* 按钮 hover 效果 */
#columnToggleBtn_{{ $tableId }}:hover {
    border-color: #6c757d !important;
    background-color: #f8f9fa !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

#columnToggleBtn_{{ $tableId }}:active {
    transform: translateY(0);
}

/* 覆盖 Bootstrap 的 .show 状态样式，保持按钮颜色不变 */
#columnToggleBtn_{{ $tableId }}.show,
#columnToggleBtn_{{ $tableId }}.show:hover,
#columnToggleBtn_{{ $tableId }}.show:focus,
#columnToggleBtn_{{ $tableId }}.show:active {
    color: #6c757d !important;
    background-color: transparent !important;
    border-color: #6c757d !important;
}

/* 下拉菜单动画 */
#columnToggleBtn_{{ $tableId }} ~ .dropdown-menu {
    animation: dropdownSlideIn 0.2s ease-out;
}

@keyframes dropdownSlideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* 复选框容器样式修复 */
#columnToggleBtn_{{ $tableId }} ~ .dropdown-menu .form-check {
    padding-left: 0;
    min-height: auto;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* 复选框样式增强 */
#columnToggleBtn_{{ $tableId }} ~ .dropdown-menu .form-check-input {
    border-width: 1.5px;
    border-color: #ced4da;
    margin: 0;
    position: static;
    flex-shrink: 0;
}

#columnToggleBtn_{{ $tableId }} ~ .dropdown-menu .form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

#columnToggleBtn_{{ $tableId }} ~ .dropdown-menu .form-check-input:focus {
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}

/* label 样式修复 */
#columnToggleBtn_{{ $tableId }} ~ .dropdown-menu .form-check-label {
    margin: 0;
    flex: 1;
}

/* 重置按钮 hover 效果 */
#resetColumnsBtn_{{ $tableId }}:hover {
    background-color: #0d6efd;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2);
}

#resetColumnsBtn_{{ $tableId }}:active {
    transform: translateY(0);
}

/* 滚动条美化 */
#columnToggleBtn_{{ $tableId }} ~ .dropdown-menu > div:nth-child(2)::-webkit-scrollbar {
    width: 6px;
}

#columnToggleBtn_{{ $tableId }} ~ .dropdown-menu > div:nth-child(2)::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

#columnToggleBtn_{{ $tableId }} ~ .dropdown-menu > div:nth-child(2)::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
}

#columnToggleBtn_{{ $tableId }} ~ .dropdown-menu > div:nth-child(2)::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}
</style>

<script>
(function() {
    const tableId = '{{ $tableId }}';
    const storageKey = '{{ $storageKey }}';
    // 只获取默认显示的列索引（visible === true）
    const defaultVisible = @json(array_column(array_filter($columns, fn($col) => $col['visible'] ?? false), 'index'));

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        const btn = document.getElementById('columnToggleBtn_' + tableId);
        if (!btn) return;

        // 加载用户偏好
        loadPreferences();

        // 监听 Bootstrap Dropdown 事件切换图标
        btn.addEventListener('show.bs.dropdown', () => {
            const icon = document.getElementById('columnToggleIcon_' + tableId);
            if (icon) icon.className = 'bi bi-eye-fill';
            const arrow = document.getElementById('columnToggleArrow_' + tableId);
            if (arrow) {
                arrow.className = 'bi bi-chevron-up';
                arrow.style.transform = 'rotate(0deg)';
            }
        });

        btn.addEventListener('hide.bs.dropdown', () => {
            const icon = document.getElementById('columnToggleIcon_' + tableId);
            if (icon) icon.className = 'bi bi-eye';
            const arrow = document.getElementById('columnToggleArrow_' + tableId);
            if (arrow) {
                arrow.className = 'bi bi-chevron-down';
                arrow.style.transform = 'rotate(0deg)';
            }
        });

        // 复选框切换
        document.querySelectorAll(`#columnToggleBtn_${tableId} ~ .dropdown-menu .column-toggle-checkbox`).forEach(cb => {
            cb.onchange = () => {
                toggleColumn(+cb.dataset.column, cb.checked);
                savePreferences();
            };
        });

        // 重置按钮
        const resetBtn = document.getElementById('resetColumnsBtn_' + tableId);
        if (resetBtn) {
            resetBtn.onclick = () => {
                document.querySelectorAll(`#columnToggleBtn_${tableId} ~ .dropdown-menu .column-toggle-checkbox`).forEach(cb => {
                    const isDefault = defaultVisible.includes(+cb.dataset.column);
                    cb.checked = isDefault;
                    toggleColumn(+cb.dataset.column, isDefault);
                });
                savePreferences();
                // 使用全局的 showToast 函数
                if (typeof window.showToast === 'function') {
                    window.showToast('success', '已重置为默认显示');
                } else if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                    window.Admin.utils.showToast('success', '已重置为默认显示');
                } else {
                    console.log('已重置为默认显示');
                }
            };
        }
    }

    function toggleColumn(index, visible) {
        const table = document.getElementById(tableId);
        const display = visible ? '' : 'none';

        table.querySelector(`thead th[data-column="${index}"]`).style.display = display;
        table.querySelectorAll(`tbody td[data-column="${index}"]`).forEach(td => {
            td.style.display = display;
        });
    }

    function savePreferences() {
        const visible = Array.from(document.querySelectorAll(`#columnToggleBtn_${tableId} ~ .dropdown-menu .column-toggle-checkbox:checked`))
            .map(cb => +cb.dataset.column);
        localStorage.setItem(storageKey, JSON.stringify(visible));
    }

    function loadPreferences() {
        const saved = localStorage.getItem(storageKey);
        const visible = saved ? JSON.parse(saved) : defaultVisible;

        document.querySelectorAll(`#columnToggleBtn_${tableId} ~ .dropdown-menu .column-toggle-checkbox`).forEach(cb => {
            const index = +cb.dataset.column;
            const isVisible = visible.includes(index);
            cb.checked = isVisible;
            toggleColumn(index, isVisible);
        });
    }
})();
</script>

