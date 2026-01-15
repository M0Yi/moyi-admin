{{--
 * 状态筛选卡项组件
 *
 * @param string $tableId 表格ID
 * @param array $statusFilterConfig 状态筛选配置
 --}}
@php
    $filterField = $statusFilterConfig['filter_field'];
    $options = $statusFilterConfig['options'] ?? [];
    $showAll = $statusFilterConfig['show_all'] ?? true;
    $allLabel = $statusFilterConfig['all_label'] ?? '全部';
    $allVariant = $statusFilterConfig['all_variant'] ?? 'outline-secondary';
    $multiple = $statusFilterConfig['multiple'] ?? false;
    $defaultValue = $statusFilterConfig['default_value'] ?? null;

    // 如果支持多选，默认值应该是数组
    if ($multiple && !is_array($defaultValue)) {
        $defaultValue = $defaultValue ? [$defaultValue] : [];
    }
@endphp

{{-- 状态筛选卡项区域 --}}
<div class="status-filter-header border-bottom-0">
    <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div id="statusFilterCards_{{ $tableId }}" class="status-filter-cards d-flex gap-2 flex-wrap">
                
            </div>
        </div>
    </div>
</div>

{{-- 状态筛选样式 --}}
<style>
/* Nav-tabs 一体化样式（浅色一体化）*/
.status-filter-header {
    padding-bottom: 0;
}
/* 轻微灰色背景，用于 card-header 使其与背景分离但不显眼 */
.bg-faint {
    background: #9393931c !important;
}
/* 在 .border-0 上强制去掉圆角，保证直角外观 */
.border-0 {
    border-radius: 0 !important;
}
.status-filter-header .nav-tabs {
    margin-bottom: 0;
}
.status-filter-header .nav-tabs .nav-link {
    border: none;
    background: transparent;
    color: #6c757d !important;
    padding: 0.5rem 0.9rem;
}
.status-filter-header .nav-tabs .nav-link:hover {
    background: transparent;
}
.status-filter-header .nav-tabs .nav-link.active {
    color: #212529 !important;
    background-color: #fff;
    border: 1px solid #e9ecef;
    border-bottom-color: transparent;
    margin-bottom: -1px;
    border-top-left-radius: 0.375rem;
    border-top-right-radius: 0.375rem;
    box-shadow: none;
}
/* 与 card-body 无缝对接：card-body 不再有顶部边框 */
.status-filter-header ~ .card-body {
    border-top: none;
    padding-top: 1rem;
}

/* 状态筛选卡项样式（与 card-body 视觉一体化） */
.status-filter-header .status-filter-cards {
    margin-left: 0;
}

.status-filter-card {
    border-radius: 0.375rem;
    background-image: none !important;
    box-shadow: none !important;
    transition: none !important;
    /* 使用 outline 按钮风格作为默认外观（视觉扁平、无渐变） */
    padding: 0.45rem 0.9rem;
    font-size: 0.95rem;
}

.status-filter-card.btn-outline-primary,
.status-filter-card.btn-outline-success,
.status-filter-card.btn-outline-warning,
.status-filter-card.btn-outline-danger,
.status-filter-card.btn-outline-info,
.status-filter-card.btn-outline-secondary {
    /* 确保 outline 样式平面化（无渐变） */
    background-image: none !important;
    box-shadow: none !important;
}

.status-filter-card.active {
    /* 激活态：使用实心背景但无渐变和阴影，使其与 card-body 融为一体 */
    color: #212529 !important;
    background-image: none !important;
    box-shadow: none !important;
    border-color: #e9ecef !important;
}

/* 多选状态下的样式调整 */
.status-filter-cards[data-multiple="true"] .status-filter-card.active {
    background-color: #e9ecef !important;
    border-color: #adb5bd !important;
    color: #495057 !important;
}
</style>

{{-- 状态筛选JavaScript --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 初始化状态筛选功能
    initializeStatusFilter_{{ $tableId }}();

    // 状态筛选处理函数
    window.handleStatusFilter_{{ $tableId }} = function(button, field, value, mode) {
        const tableId = '{{ $tableId }}';
        const container = document.getElementById('statusFilterCards_' + tableId);

        if (mode === 'multiple') {
            // 多选模式
            handleMultipleStatusFilter(button, field, value, tableId);
        } else {
            // 单选模式
            handleSingleStatusFilter(button, field, value, tableId);
        }

        // 触发表格重新加载
        const table = window['_dataTable_' + tableId];
        if (table && typeof table.reload === 'function') {
            table.reload();
        }
    };

    // 单选模式处理
    function handleSingleStatusFilter(button, field, value, tableId) {
        const container = document.getElementById('statusFilterCards_' + tableId);
        const buttons = container.querySelectorAll('.status-filter-card');

        // 移除所有激活状态
        buttons.forEach(btn => btn.classList.remove('active'));

        // 添加当前按钮的激活状态
        button.classList.add('active');

        // 更新URL参数
        updateUrlParams(field, value);
    }

    // 多选模式处理
    function handleMultipleStatusFilter(button, field, value, tableId) {
        const container = document.getElementById('statusFilterCards_' + tableId);
        const isAllButton = button.dataset.filterType === 'all';

        if (isAllButton) {
            // 点击"全部"按钮，清除所有筛选
            clearAllStatusFilters(tableId);
        } else {
            // 点击选项按钮
            const isActive = button.classList.contains('active');
            const allButton = container.querySelector('[data-filter-type="all"]');

            if (isActive) {
                // 取消选择
                button.classList.remove('active');
            } else {
                // 添加选择
                button.classList.add('active');
                // 如果有"全部"按钮且当前处于激活状态，取消其激活
                if (allButton) {
                    allButton.classList.remove('active');
                }
            }

            // 检查是否没有选中任何选项，如果是则激活"全部"
            const activeOptions = container.querySelectorAll('.status-filter-card[data-filter-type="option"].active');
            if (activeOptions.length === 0 && allButton) {
                allButton.classList.add('active');
            }
        }

        // 收集选中的值并更新URL
        const selectedValues = getSelectedStatusFilterValues(tableId);
        updateUrlParams(field, selectedValues.length > 0 ? selectedValues : null);
    }

    // 清除所有状态筛选
    window.clearStatusFilter_{{ $tableId }} = function() {
        const tableId = '{{ $tableId }}';
        clearAllStatusFilters(tableId);

        const field = '{{ $filterField }}';
        updateUrlParams(field, null);

        // 触发表格重新加载
        const table = window['_dataTable_' + tableId];
        if (table && typeof table.reload === 'function') {
            table.reload();
        }
    };

    // 获取选中的筛选值
    function getSelectedStatusFilterValues(tableId) {
        const container = document.getElementById('statusFilterCards_' + tableId);
        const activeButtons = container.querySelectorAll('.status-filter-card[data-filter-type="option"].active');
        return Array.from(activeButtons).map(btn => btn.dataset.filterValue);
    }

    // 清除所有筛选状态
    function clearAllStatusFilters(tableId) {
        const container = document.getElementById('statusFilterCards_' + tableId);
        const buttons = container.querySelectorAll('.status-filter-card');
        buttons.forEach(btn => btn.classList.remove('active'));

        // 激活"全部"按钮
        const allButton = container.querySelector('[data-filter-type="all"]');
        if (allButton) {
            allButton.classList.add('active');
        }
    }

    // 更新URL参数
    function updateUrlParams(field, value) {
        const url = new URL(window.location);
        if (value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
            url.searchParams.delete(field);
        } else {
            if (Array.isArray(value)) {
                url.searchParams.set(field, value.join(','));
            } else {
                url.searchParams.set(field, value);
            }
        }

        // 更新URL但不重新加载页面
        window.history.replaceState({}, '', url);
    }

    // 初始化状态筛选功能 - 移除重复渲染，只保留交互功能
    function initializeStatusFilter_{{ $tableId }}() {
        const tableId = '{{ $tableId }}';
        const container = document.getElementById('statusFilterCards_' + tableId);
        if (!container) return;

        // 不再从URL参数重新初始化，直接使用后端传递的初始状态
        // 避免页面加载时的闪烁现象
    }
});
</script>