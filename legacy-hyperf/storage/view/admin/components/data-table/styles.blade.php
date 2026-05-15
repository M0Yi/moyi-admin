{{-- 数据表格组件样式 --}}
<style>
/* 排序样式 */
.sortable-column {
    position: relative;
    user-select: none;
}

.sortable-column:hover {
    background-color: #f8f9fa;
}

.sort-icons {
    display: inline-flex !important;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin-left: 0.5rem;
    gap: 0.1rem;
    opacity: 0.4;
    transition: opacity 0.2s ease;
}

.sort-icons i {
    display: block;
    font-size: 0.7rem;
    line-height: 1;
    color: #6c757d;
    transition: all 0.2s ease;
}

.sort-icons .sort-asc {
    margin-bottom: -0.15rem;
}

.sort-icons .sort-desc {
    margin-top: -0.15rem;
}

/* 悬停状态 */
.sortable-column:hover .sort-icons {
    opacity: 0.7;
}

/* 激活状态 - 更明显的颜色对比 */
.sort-icons .sort-asc.text-primary,
.sort-icons .sort-desc.text-primary {
    opacity: 1 !important;
    color: #667eea !important;
    font-weight: 700;
    font-size: 0.85rem;
    filter: drop-shadow(0 1px 2px rgba(102, 126, 234, 0.4));
    transform: scale(1.1);
}

.sort-icons.active {
    opacity: 1;
}

.sort-icons.active .sort-asc:not(.text-primary),
.sort-icons.active .sort-desc:not(.text-primary) {
    opacity: 0.2;
    color: #adb5bd !important;
}

/* 搜索面板样式 */
#{{ $searchPanelId }} {
    padding: 1.25rem 0;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    transition: opacity 0.3s ease, transform 0.3s ease;
}

#{{ $searchPanelId }} form {
    margin-bottom: 0;
}

/* 搜索按钮激活状态 */
#searchToggleBtn_{{ $tableId }}.active {
    background-color: #667eea;
    border-color: #667eea;
    color: #fff;
}

#searchToggleBtn_{{ $tableId }}.active i {
    color: #fff;
}

#searchToggleBtn_{{ $tableId }}:hover {
    background-color: #667eea;
    border-color: #667eea;
    color: #fff;
}

#searchToggleBtn_{{ $tableId }}:hover i {
    color: #fff;
}

#searchToggleBtn_{{ $tableId }} i {
    transition: transform 0.2s ease;
}

/* 删除按钮禁用状态 */
#batchDeleteBtn_{{ $tableId }}.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    pointer-events: none;
}

/* 复选框样式优化 */
#{{ $tableId }} .form-check-input {
    cursor: pointer;
}

#{{ $tableId }} .form-check-input:indeterminate {
    background-color: #667eea;
    border-color: #667eea;
}
</style>

<style>
/* 状态筛选卡项样式（与 card-body 视觉一体化） */
.status-filter-header {
    background: transparent;
    border-bottom: 0;
    padding-top: 0;
    padding-bottom: 0;
}

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
</style>

