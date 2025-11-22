@extends('admin.layouts.admin')

@section('title', 'CRUD生成器 - 配置')

@if (! $isEmbedded)
    @push('admin_sidebar')
        @include('admin.components.sidebar')
    @endpush

    @push('admin_navbar')
        @include('admin.components.navbar')
    @endpush
@endif

@push('admin-styles')
<style>
/* 加载状态 */
.fields-loading {
    text-align: center;
    padding: 3rem;
}

.fields-loading .spinner-border {
    width: 3rem;
    height: 3rem;
}

/* 拖拽排序样式 */
.field-row {
    cursor: move;
}

.field-row.sortable-ghost {
    opacity: 0.4;
    background-color: #f0f0f0;
}

.field-row.sortable-drag {
    opacity: 0.8;
    background-color: #e3f2fd;
}

/* 已禁用：拖动功能BUG太多，暂时禁用，待后续优化 */
.drag-handle {
    /* cursor: grab; */
    /* color: #6c757d; */
    /* font-size: 1.2rem; */
    /* padding: 0.5rem; */
    /* display: flex; */
    display: none; /* 隐藏拖拽手柄 */
    /* align-items: center; */
    /* justify-content: center; */
    /* transition: color 0.2s; */
}

.drag-handle:hover {
    /* color: #495057; */
    /* cursor: grabbing; */
}

.drag-handle:active {
    /* cursor: grabbing; */
}

.field-row:hover .drag-handle {
    color: #0d6efd;
}

/* 字段配置区域 */
#fieldsConfigArea {
    min-height: 400px;
}

/* 字段表格样式 */
.field-row {
    transition: background-color 0.2s;
}

.field-row:hover {
    background-color: #f8f9fa;
}

/* 复选框优化样式 */
.field-row .form-check {
    cursor: pointer;
    padding: 2px 0;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    position: relative;
}

.field-row .form-check:hover {
    background-color: rgba(0, 123, 255, 0.05);
    border-radius: 4px;
    padding-left: 4px;
    padding-right: 4px;
}

.field-row .form-check-label {
    cursor: pointer;
    user-select: none;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 0;
    padding: 0;
    margin-left: 0 !important; /* 确保没有左边距 */
}

.field-row .form-check-label i {
    font-size: 0.875rem;
    opacity: 0.7;
}

.field-row .form-check-input:checked ~ .form-check-label i {
    opacity: 1;
    color: #0d6efd;
}

.field-row .form-check-input {
    cursor: pointer;
    margin: 0 !important; /* 覆盖所有 Bootstrap 默认的 margin */
    margin-top: 0 !important;
    margin-left: 0 !important; /* 覆盖 Bootstrap 默认的 margin-left: -1.5em */
    margin-right: 0 !important;
    margin-bottom: 0 !important;
    flex-shrink: 0;
    position: relative;
    float: none; /* 防止浮动 */
}

/* 复选框列样式 */
.field-row td .d-flex.flex-column {
    min-width: 120px;
}

/* 确保表格单元格内的复选框不会溢出 */
.field-row td {
    position: relative;
    overflow: visible;
    vertical-align: middle;
}

.field-row td .form-check {
    position: relative;
    width: 100%;
}

/* 覆盖 form-check-inline 的样式 */
.field-row .form-check.form-check-inline {
    display: flex;
    align-items: center;
    width: 100%;
    margin-right: 0;
}

/* 类型特定属性布局 */
.type-attrs-group {
    border-top: 1px dashed #dee2e6;
    padding-top: 1.25rem;
    margin-top: 1.5rem;
}

.type-attrs-group:first-of-type {
    border-top: none;
    padding-top: 0;
    margin-top: 0;
}
</style>
@endpush

{{-- 引入 Sortable.js 插件 --}}
{{-- 已禁用：拖动功能BUG太多，暂时禁用，待后续优化 --}}
{{-- @include('components.plugin.sortable-js') --}}

@push('admin-scripts')
@endpush

@section('content')
@include('admin.common.styles')

@php
    $currentConnInfo = $currentConnInfo ?? ($connections[$dbConnection] ?? null);
@endphp

<div class="container-fluid {{ $isEmbedded ? 'py-3 px-2 px-md-4' : 'py-4' }}">
    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h6 class="mb-1 fw-bold">配置 CRUD 生成器 <span class="badge bg-success">V2</span></h6>
                <small class="text-muted d-block">
                    数据表：<code class="text-primary">{{ $tableName }}</code>
                    <span class="ms-2 text-muted" id="tableCommentText" style="{{ $tableComment ? '' : 'display:none;' }}">
                        {{ $tableComment }}
                    </span>
                    @if($currentConnInfo)
                        <span class="ms-2">
                            <span class="badge bg-info" id="connectionBadgeName">
                                <i class="bi bi-database"></i> {{ $dbConnection }}
                            </span>
                            <small class="text-muted ms-2" id="connectionHostInfo" style="{{ $currentConnInfo ? '' : 'display:none;' }}">
                                @if($currentConnInfo)
                                    ({{ $currentConnInfo['database'] }} @ {{ $currentConnInfo['host'] }}:{{ $currentConnInfo['port'] }})
                                @endif
                            </small>
                        </span>
                    @endif
                </small>
            </div>
            @if (! $isEmbedded)
                <div class="mt-2 mt-sm-0">
                    <a href="{{ admin_route('system/crud-generator') }}?connection={{ $dbConnection }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> 返回列表
                    </a>
                </div>
            @endif
        </div>
    </div>

    <form id="configForm">
        <!-- 基础配置 -->
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-gear"></i> 基础配置</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <input type="hidden" name="config_id" id="configIdField" value="">
                    <div class="col-md-6">
                        <label class="form-label">数据表名</label>
                        <input type="text" class="form-control" id="tableNameDisplay" value="" readonly>
                        <input type="hidden" name="table_name" id="tableNameInput" value="">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">数据库连接</label>
                        <input type="hidden" name="db_connection" id="dbConnectionInput" value="">
                        <input type="text" class="form-control" id="dbConnectionDisplay" value="" readonly>
                        <small class="text-muted">
                            选择数据表所在的数据库连接，对应 <code>config/databases.php</code> 中的连接配置
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">模型名称 <span class="text-danger">*</span></label>
                        <input type="text" name="model_name" class="form-control" value="" required>
                        <small class="text-muted">例如：AdminArticle</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">控制器名称 <span class="text-danger">*</span></label>
                        <input type="text" name="controller_name" class="form-control" value="" required>
                        <small class="text-muted">例如：AdminArticleController</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">路由标识（route_slug） <span class="text-danger">*</span></label>
                        <input type="text" name="route_slug" class="form-control" value="" required>
                        <small class="text-muted">
                            用于生成菜单与路由命名，例如：articles 或 system.articles
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">路由前缀（route_prefix） <span class="text-danger">*</span></label>
                        <input type="text" name="route_prefix" class="form-control" value="" required>
                        <small class="text-muted">
                            后台访问路径前缀，例如：system/articles
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">菜单标题（module_name） <span class="text-danger">*</span></label>
                        <input type="text" name="module_name" class="form-control" value="" required>
                        <small class="text-muted">
                            用于创建菜单时显示的标题，例如：文章管理、用户管理
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">图标</label>
                        <div class="input-group">
                            <span class="input-group-text" id="iconPreview">
                                <i class="bi bi-table"></i>
                            </span>
                            <input type="text" name="icon" id="icon" class="form-control"
                                   placeholder="例如：bi bi-file-text">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-bs-toggle="modal"
                                data-bs-target="#iconPickerModal"
                                data-target-input="icon"
                            >
                                <i class="bi bi-emoji-smile"></i> 选择
                            </button>
                        </div>
                        <small class="text-muted">
                            将用于生成的菜单，使用 <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a>
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">每页显示数量</label>
                        <input type="number" name="page_size" class="form-control" value="" min="1" max="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">同步到菜单</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="sync_to_menu" value="1"
                                   id="syncToMenu">
                            <label class="form-check-label" for="syncToMenu">
                                生成后自动添加到菜单（默认开启）
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">启用软删除</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="soft_delete" value="1"
                                   id="useSoftDelete">
                            <label class="form-check-label" for="useSoftDelete">
                                使用软删除功能（检测到 deleted_at 字段时自动开启）
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">状态</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="status" value="1"
                                   id="status">
                            <label class="form-check-label" for="status">
                                启用此配置（默认开启）
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-3 bg-light h-100">
                            <label class="form-label fw-bold mb-2">
                                <i class="bi bi-sliders"></i> 功能开关
                            </label>
                            <p class="text-muted small mb-3">用于控制 CRUD 生成时的全局功能，这些开关与下方的字段配置互不影响。</p>
                            <div class="row g-3">
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="features[search]" value="0">
                                        <input class="form-check-input" type="checkbox" name="features[search]" value="1"
                                               id="featureSearchToggle">
                                        <label class="form-check-label" for="featureSearchToggle">
                                            启用搜索
                                        </label>
                </div>
                                    <small class="text-muted">控制列表页是否展示搜索区域。</small>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="features[add]" value="0">
                                        <input class="form-check-input" type="checkbox" name="features[add]" value="1"
                                               id="featureAddToggle">
                                        <label class="form-check-label" for="featureAddToggle">
                                            启用新增
                                        </label>
                                    </div>
                                    <small class="text-muted">控制是否生成新增按钮与创建表单。</small>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="features[edit]" value="0">
                                        <input class="form-check-input" type="checkbox" name="features[edit]" value="1"
                                               id="featureEditToggle">
                                        <label class="form-check-label" for="featureEditToggle">
                                            启用编辑
                                        </label>
                                    </div>
                                    <small class="text-muted">控制是否生成编辑相关按钮与路由。</small>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="features[delete]" value="0">
                                        <input class="form-check-input" type="checkbox" name="features[delete]" value="1"
                                               id="featureDeleteToggle">
                                        <label class="form-check-label" for="featureDeleteToggle">
                                            启用删除
                                        </label>
                                    </div>
                                    <small class="text-muted">控制是否生成删除操作。</small>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-check form-switch">
                                    <input type="hidden" name="features[export]" value="0">
                                        <input class="form-check-input" type="checkbox" name="features[export]" value="1"
                                               id="featureExportToggle">
                                        <label class="form-check-label" for="featureExportToggle">
                                            启用导出
                                        </label>
                                    </div>
                                    <small class="text-muted">控制是否生成导出相关功能。</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 字段配置区域（分离加载） -->
        <div class="card mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-list-ul"></i> 字段配置</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="reloadFieldsBtn" style="display: none;">
                    <i class="bi bi-arrow-clockwise"></i> 重新加载
                </button>
            </div>
            <div class="card-body">
                <!-- 加载状态 -->
                <div id="fieldsLoading" class="fields-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">加载中...</span>
                    </div>
                    <p class="mt-3 text-muted">正在加载字段配置...</p>
                </div>

                <!-- 错误提示 -->
                <div id="fieldsError" class="alert alert-danger" style="display: none;">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span id="fieldsErrorMsg"></span>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="loadFieldsConfig()">
                        <i class="bi bi-arrow-clockwise"></i> 重试
                    </button>
                </div>

                <!-- 字段配置内容 -->
                <div id="fieldsConfigArea" style="display: none;">
                    <!-- 字段配置将在这里动态加载 -->
                </div>
            </div>
        </div>

    </form>
</div>

<!-- 固定在底部的操作栏 -->
@include('admin.components.fixed-bottom-actions', [
    'infoText' => '配置完成后点击保存按钮提交（当前为模拟提交，仅输出到控制台）',
    'cancelUrl' => $isEmbedded ? 'javascript:void(0);' : admin_route('system/crud-generator') . '?connection=' . ($dbConnection ?? 'default'),
    'cancelText' => $isEmbedded ? '关闭' : '返回列表',
    'cancelBtnId' => 'crudConfigCancelBtn',
    'submitText' => '保存配置',
    'formId' => 'configForm'
])

<script>
window.__CRUD_GENERATOR_PAGE_VARS__ = {
    tableName: @json($tableName),
    dbConnection: @json($dbConnection),
    baseConfig: @json($config ?? []),
    tableComment: @json($tableComment),
    connectionInfo: @json($currentConnInfo),
};
</script>

<script>
// ==================== 常量定义 ====================
const BADGE_COLORS = [
    { value: '', label: '自动（智能匹配）' },
    { value: 'primary', label: '主要（蓝色）' },
    { value: 'success', label: '成功（绿色）' },
    { value: 'danger', label: '危险（红色）' },
    { value: 'warning', label: '警告（黄色）' },
    { value: 'info', label: '信息（青色）' },
    { value: 'secondary', label: '次要（灰色）' },
    { value: 'dark', label: '深色（黑色）' },
    { value: 'light', label: '浅色（浅灰）' }
];

const DEFAULT_ICON_CLASS = 'bi bi-table';
const MAIN_REFRESH_MESSAGE = '菜单配置已更新，正在刷新主框架...';

function refreshMainFrame(payload = {}) {
    const options = Object.assign({
        message: MAIN_REFRESH_MESSAGE,
        showToast: true,
        toastType: 'info',
        delay: 0,
    }, payload);

    const hasIframeClient = window.AdminIframeClient && typeof window.AdminIframeClient.refreshMainFrame === 'function';

    if (hasIframeClient) {
        try {
            window.AdminIframeClient.refreshMainFrame(options);
            return true;
        } catch (error) {
            console.warn('AdminIframeClient.refreshMainFrame 调用失败，启用降级方案:', error);
        }
    }

    // 降级方案：如果处于 iframe 内，尝试强制刷新父窗口；否则刷新当前窗口
    const targetWindow = window.top && window.top !== window ? window.top : window;
    try {
        targetWindow.location.reload();
        return true;
    } catch (fallbackError) {
        console.warn('刷新主窗口失败:', fallbackError);
        return false;
    }
}

// CRUD 功能开关默认配置（无配置时使用）
// 在这里统一配置每个功能默认是打开(true)还是关闭(false)
const DEFAULT_FEATURE_CONFIG = {
    search: false,   // 启用搜索
    add: false,      // 启用新增
    edit: false,     // 启用编辑
    delete: false,   // 启用删除
    export: false,   // 启用导出
};

// 方便直接使用的默认开关对象（仅包含 features）
const DEFAULT_FEATURE_TOGGLES = { ...DEFAULT_FEATURE_CONFIG };

const FORM_TYPES = [
    { value: 'text', label: '文本框' },
    { value: 'textarea', label: '文本域' },
    { value: 'rich_text', label: '富文本' },
    { value: 'number', label: '数字' },
    { value: 'number_range', label: '区间数字' },
    { value: 'email', label: '邮箱' },
    { value: 'password', label: '密码' },
    { value: 'date', label: '日期' },
    { value: 'datetime', label: '日期时间' },
    { value: 'switch', label: '开关' },
    { value: 'radio', label: '单选框' },
    { value: 'checkbox', label: '复选框' },
    { value: 'select', label: '下拉选择' },
    { value: 'relation', label: '关联选择' },
    { value: 'icon', label: '图标选择' },
    { value: 'image', label: '单图上传' },
    { value: 'images', label: '多图上传' },
    { value: 'file', label: '文件上传' }
];

const COLUMN_TYPES = [
    { value: 'text', label: '文本' },
    { value: 'number', label: '数字' },
    { value: 'date', label: '日期' },
    { value: 'icon', label: '图标' },
    { value: 'image', label: '单图' },
    { value: 'images', label: '多图' },
    { value: 'switch', label: '开关' },
    { value: 'badge', label: '徽章' },
    { value: 'code', label: '代码' },
    { value: 'link', label: '链接' },
    { value: 'relation', label: '关联' },
    { value: 'columns', label: '列组' },
    { value: 'custom', label: '自定义' }
];

// 关联表及显示字段推断配置
const RELATION_TABLE_NAME_MAP = {
    site: 'admin_sites',
    user: 'users',
};

const RELATION_DEFAULT_LABEL_COLUMN = 'name';
const RELATION_LABEL_COLUMN_MAP = {
    user: 'username',
    users: 'username',
};

const SEARCH_TYPES = [
    { value: 'like', label: '模糊搜索' },
    { value: 'exact', label: '精确匹配' },
    { value: 'number_range', label: '数字区间' },
    { value: 'date_range', label: '日期区间' },
    { value: 'select', label: '下拉选择' },
    { value: 'relation', label: '关联搜索' }
];

// 成功状态关键词（用于智能匹配徽章颜色）
const SUCCESS_KEYWORDS = ['成功', '启用', '是', 'true', '1', 'active', 'enabled', 'yes', 'on', '正常', '已审核', '已通过'];
const DANGER_KEYWORDS = ['失败', '禁用', '否', 'false', '0', 'inactive', 'disabled', 'no', 'off', '异常', '已拒绝', '未通过'];
const WARNING_KEYWORDS = ['警告', '待处理', '审核中', 'pending', 'warning', 'waiting', '待审核'];
const INFO_KEYWORDS = ['信息', '提示', 'info', 'notice', '消息'];

// ==================== 全局变量 ====================
const PAGE_VARS = window.__CRUD_GENERATOR_PAGE_VARS__ || {};
const IS_EMBEDDED_PAGE = document.documentElement?.dataset?.embed === '1';
const { tableName = '', dbConnection = '' } = PAGE_VARS;
let fieldsData = []; // 存储字段数据
let hasRegisteredUserInputTracking = false;

// ==================== 基础配置辅助函数 ====================

function registerUserInputTracking() {
    if (hasRegisteredUserInputTracking) {
        return;
    }
    const form = document.getElementById('configForm');
    if (!form) {
        return;
    }
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach((element) => {
        element.addEventListener('input', () => {
            element.dataset.userModified = 'true';
        });
        element.addEventListener('change', () => {
            element.dataset.userModified = 'true';
        });
    });
    hasRegisteredUserInputTracking = true;
}

function setInputValueIfAllowed(element, value) {
    if (!element || value === undefined || value === null) {
        return;
    }
    if (element.dataset.userModified === 'true') {
        return;
    }
    if (typeof element.value === 'string' && element.value.trim() !== '') {
        return;
    }
    element.value = value;
}

function setCheckboxValueIfAllowed(element, value, fallbackValue = null) {
    if (!element) {
        return;
    }
    const normalizedValue = (value === undefined || value === null) ? fallbackValue : value;
    if (normalizedValue === undefined || normalizedValue === null) {
        return;
    }
    if (element.dataset.userModified === 'true') {
        return;
    }
    if (typeof normalizedValue === 'string') {
        element.checked = normalizedValue === '1' || normalizedValue.toLowerCase() === 'true';
    } else {
        element.checked = !!normalizedValue;
    }
}

/**
 * 从表名推测模型名称（前端推测）
 * @param {string} tableName 表名
 * @returns {string} 模型名称
 */
function guessModelName(tableName) {
    if (!tableName || typeof tableName !== 'string') {
        return 'Model';
    }
    
    const trimmed = tableName.trim();
    if (trimmed === '') {
        return 'Model';
    }
    
    // 直接转换表名为驼峰命名，不移除任何前缀
    // 例如：admin_articles -> AdminArticles, users -> Users, sys_configs -> SysConfigs
    let name = trimmed.replace(/_/g, ' ');
    name = name.split(' ').map(word => {
        if (word.length === 0) return '';
        return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
    }).join('');
    name = name.replace(/\s+/g, '');
    
    // 单数化（简单处理）
    if (name.endsWith('s')) {
        name = name.slice(0, -1);
    }
    
    return name;
}

/**
 * 清洗路由标识，移除斜杠和非法字符（前端推测）
 * @param {string} slug 路由标识
 * @returns {string} 清洗后的路由标识
 */
function normalizeRouteSlug(slug) {
    if (!slug || typeof slug !== 'string') {
        return '';
    }
    
    let normalized = slug.trim();
    if (normalized === '') {
        return '';
    }
    
    // 移除首尾斜杠并替换中间的斜杠
    normalized = normalized.replace(/^[/\\]+|[/\\]+$/g, '');
    normalized = normalized.replace(/[/\\]/g, '-');
    
    // 仅保留字母、数字、连字符、下划线
    normalized = normalized.replace(/[^A-Za-z0-9_-]/g, '-');
    
    // 合并连续的 -
    normalized = normalized.replace(/-+/g, '-');
    
    // 移除首尾的连字符
    return normalized.replace(/^-+|-+$/g, '');
}

/**
 * 从表名推测路由标识（前端推测）
 * @param {string} tableName 表名
 * @returns {string} 路由标识
 */
function guessRouteSlug(tableName) {
    if (!tableName || typeof tableName !== 'string') {
        return 'default';
    }
    
    const trimmed = tableName.trim();
    if (trimmed === '') {
        return 'default';
    }
    
    const normalized = normalizeRouteSlug(trimmed);
    return normalized || trimmed;
}

/**
 * 从表名推测路由前缀（前端推测）
 * @param {string} tableName 表名
 * @param {string|null} routeSlug 路由标识（可选）
 * @returns {string} 路由前缀
 */
function guessRoutePrefix(tableName, routeSlug = null) {
    if (routeSlug) {
        return routeSlug;
    }
    return guessRouteSlug(tableName);
}

function hydrateBasicConfig(baseConfig = {}, meta = {}) {
    const configData = baseConfig || {};
    const tableMeta = meta.table || {};
    const connectionMeta = meta.connection || {};

    const configIdInput = document.getElementById('configIdField');
    setInputValueIfAllowed(configIdInput, configData.id);

    const tableNameValue = configData.table_name || PAGE_VARS.tableName || '';
    const tableNameDisplay = document.getElementById('tableNameDisplay');
    if (tableNameDisplay && tableNameDisplay.dataset.userModified !== 'true' && tableNameDisplay.value !== tableNameValue) {
        tableNameDisplay.value = tableNameValue;
    }
    const tableNameInput = document.getElementById('tableNameInput');
    setInputValueIfAllowed(tableNameInput, tableNameValue);

    const dbConnectionValue = configData.db_connection || PAGE_VARS.dbConnection || '';
    const dbConnectionHidden = document.getElementById('dbConnectionInput');
    setInputValueIfAllowed(dbConnectionHidden, dbConnectionValue);
    const dbConnectionDisplay = document.getElementById('dbConnectionDisplay');
    if (dbConnectionDisplay && dbConnectionDisplay.dataset.userModified !== 'true' && dbConnectionDisplay.value !== dbConnectionValue) {
        dbConnectionDisplay.value = dbConnectionValue;
    }

    // 前端推测：如果后端没有返回 model_name，从前端推测
    const modelInput = document.querySelector('input[name="model_name"]');
    const guessedModelName = configData.model_name || (tableNameValue ? guessModelName(tableNameValue) : '');
    setInputValueIfAllowed(modelInput, guessedModelName);

    // 前端推测：如果后端没有返回 controller_name，从前端推测
    const controllerInput = document.querySelector('input[name="controller_name"]');
    const guessedControllerName = configData.controller_name || (guessedModelName ? guessedModelName + 'Controller' : '');
    setInputValueIfAllowed(controllerInput, guessedControllerName);

    // 前端推测：如果后端没有返回 route_slug，从前端推测
    const routeSlugInput = document.querySelector('input[name="route_slug"]');
    const guessedRouteSlug = configData.route_slug || (tableNameValue ? guessRouteSlug(tableNameValue) : '');
    setInputValueIfAllowed(routeSlugInput, guessedRouteSlug);

    // 前端推测：如果后端没有返回 route_prefix，从前端推测
    const routePrefixInput = document.querySelector('input[name="route_prefix"]');
    const guessedRoutePrefix = configData.route_prefix || guessedRouteSlug;
    setInputValueIfAllowed(routePrefixInput, guessedRoutePrefix);

    // 前端推测：如果后端没有返回 module_name，使用表注释或模型名称
    const moduleNameInput = document.querySelector('input[name="module_name"]');
    const guessedModuleName = configData.module_name || tableMeta.comment || guessedModelName || '';
    setInputValueIfAllowed(moduleNameInput, guessedModuleName);

    const iconInput = document.getElementById('icon');
    if (iconInput && iconInput.dataset.userModified !== 'true') {
        const iconClass = configData.icon || DEFAULT_ICON_CLASS;
        iconInput.value = iconClass;
        const iconPreview = document.getElementById('iconPreview');
        if (iconPreview) {
            iconPreview.innerHTML = `<i class="${iconClass}"></i>`;
        }
    }

    const pageSizeInput = document.querySelector('input[name="page_size"]');
    // 优先从独立字段读取，如果没有则从 options 中读取（向后兼容）
    const pageSizeValue = configData.page_size !== undefined 
        ? configData.page_size 
        : (configData.options && configData.options.page_size ? configData.options.page_size : 15);
    setInputValueIfAllowed(pageSizeInput, pageSizeValue);

    const syncCheckbox = document.getElementById('syncToMenu');
    setCheckboxValueIfAllowed(syncCheckbox, configData.sync_to_menu, true);

    const statusCheckbox = document.getElementById('status');
    setCheckboxValueIfAllowed(statusCheckbox, configData.status, true);

    const softDeleteCheckbox = document.getElementById('useSoftDelete');
    // 优先从独立字段读取，如果没有则从 options 中读取（向后兼容）
    const softDeleteValue = configData.soft_delete !== undefined
        ? configData.soft_delete
        : (configData.options && configData.options.soft_delete !== undefined
            ? configData.options.soft_delete
            : false);
    setCheckboxValueIfAllowed(softDeleteCheckbox, softDeleteValue, false);

    // 功能开关：
    // - 如果已有配置（configData.id 存在），优先使用 configData.features
    // - 如果没有任何配置，则使用 DEFAULT_FEATURE_CONFIG 作为默认值
    const hasConfig = !!configData && !!configData.id;
    const featureSource = hasConfig
        ? (configData.features || {})
        : DEFAULT_FEATURE_CONFIG;

    const featureToggles = {
        ...DEFAULT_FEATURE_TOGGLES,
        ...featureSource,
    };
    setCheckboxValueIfAllowed(document.getElementById('featureSearchToggle'), featureToggles.search, true);
    setCheckboxValueIfAllowed(document.getElementById('featureAddToggle'), featureToggles.add, true);
    setCheckboxValueIfAllowed(document.getElementById('featureEditToggle'), featureToggles.edit, true);
    setCheckboxValueIfAllowed(document.getElementById('featureDeleteToggle'), featureToggles.delete, true);
    setCheckboxValueIfAllowed(document.getElementById('featureExportToggle'), featureToggles.export, true);

    const tableCommentEl = document.getElementById('tableCommentText');
    const commentText = tableMeta.comment || configData.table_comment || '';
    if (tableCommentEl) {
        if (commentText) {
            tableCommentEl.textContent = commentText;
            tableCommentEl.style.display = '';
        } else {
            tableCommentEl.textContent = '';
            tableCommentEl.style.display = 'none';
        }
    }

    const connectionBadgeEl = document.getElementById('connectionBadgeName');
    const connectionName = connectionMeta.name || configData.db_connection;
    if (connectionBadgeEl && connectionName) {
        connectionBadgeEl.innerHTML = `<i class="bi bi-database"></i> ${connectionName}`;
    }

    const connectionHostEl = document.getElementById('connectionHostInfo');
    if (connectionHostEl) {
        const info = connectionMeta.info || PAGE_VARS.connectionInfo || null;
        if (info && info.database && info.host && info.port) {
            connectionHostEl.textContent = `(${info.database} @ ${info.host}:${info.port})`;
            connectionHostEl.style.display = '';
        } else if (!connectionHostEl.textContent.trim()) {
            connectionHostEl.style.display = 'none';
        }
    }
}

// ==================== 页面初始化 ====================
document.addEventListener('DOMContentLoaded', function() {
    registerUserInputTracking();
    hydrateBasicConfig(PAGE_VARS.baseConfig || {}, {
        table: { comment: PAGE_VARS.tableComment || '' },
        connection: {
            name: PAGE_VARS.dbConnection || '',
            info: PAGE_VARS.connectionInfo || null,
        },
    });

    loadFieldsConfig();
    
    // 重新加载按钮
    document.getElementById('reloadFieldsBtn').addEventListener('click', function() {
        loadFieldsConfig();
    });
    
    // 表单提交
    document.getElementById('configForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveConfig();
    });
    
    // 监听图标输入框变化，更新预览
    const iconInput = document.getElementById('icon');
    const iconPreview = document.getElementById('iconPreview');
    const defaultIcon = DEFAULT_ICON_CLASS; // 默认图标
    
    if (iconInput && iconPreview) {
        iconInput.addEventListener('input', function() {
            const iconClass = this.value.trim();
            if (iconClass) {
                iconPreview.innerHTML = `<i class="${iconClass}"></i>`;
            } else {
                // 如果输入框为空，使用默认图标
                iconPreview.innerHTML = `<i class="${defaultIcon}"></i>`;
            }
        });
    }
});

// ==================== 数据加载 ====================

/**
 * 加载字段配置
 */
function loadFieldsConfig() {
    const loadingEl = document.getElementById('fieldsLoading');
    const errorEl = document.getElementById('fieldsError');
    const areaEl = document.getElementById('fieldsConfigArea');
    const reloadBtn = document.getElementById('reloadFieldsBtn');
    
    // 显示加载状态
    loadingEl.style.display = 'block';
    errorEl.style.display = 'none';
    areaEl.style.display = 'none';
    reloadBtn.style.display = 'none';
    
    // 获取当前数据库连接
    const connectionInput = document.getElementById('dbConnectionInput');
    const connection = connectionInput ? connectionInput.value : PAGE_VARS.dbConnection;
    
    // 统一使用 fields-config 接口，该接口会返回原始表结构（columns）和已保存的配置（config）
    // 前端会比对两者，自动处理缺少和多出的字段
    const url = `${window.adminRoute('system/crud-generator')}/fields-config/${tableName}?connection=${connection}`;
    
    console.log('[字段配置加载] 开始加载字段配置...');
    
    // 发送请求
    fetch(url)
        .then(response => response.json())
        .then(data => {
            // 后端 success() 方法返回 code = 200，error() 返回 code = 400 或其他错误码
            if (data.code === 200 || data.code === 0) {
                const baseConfigFromApi = data.data.config || {};
                const tableMeta = data.data.table || {};
                const connectionMeta = data.data.connection || {};
                hydrateBasicConfig(baseConfigFromApi, { table: tableMeta, connection: connectionMeta });
                PAGE_VARS.baseConfig = baseConfigFromApi;
                PAGE_VARS.tableComment = tableMeta.comment || PAGE_VARS.tableComment;
                if (connectionMeta && connectionMeta.info) {
                    PAGE_VARS.connectionInfo = connectionMeta.info;
                }

                let columns = data.data.columns; // 原始表结构
                const savedConfig = data.data.config; // 已保存的配置（可能为 null）
                const savedFieldsConfig = savedConfig?.fields_config || []; // 已保存的字段配置数组
                
                // 创建已保存配置的映射（以字段名为 key）
                const savedConfigMap = {};
                savedFieldsConfig.forEach(field => {
                    if (field.name) {
                        savedConfigMap[field.name] = field;
                    }
                });
                
                console.log('[字段配置加载] 原始字段数量:', columns.length);
                console.log('[字段配置加载] 已保存配置字段数量:', savedFieldsConfig.length);
                
                // 比对并合并字段配置
                // 1. 处理 columns 中的字段（已有字段以 config 为准，新字段使用自动识别）
                const mergedColumns = columns.map(column => {
                    const columnName = column.name;
                    const savedField = savedConfigMap[columnName];
                    
                    if (savedField) {
                        // 字段在已保存配置中存在：直接使用配置中的信息，不做任何合并或智能识别
                        // 只保留必要的数据库基础信息（用于显示和验证），其他所有配置都以 savedField 为准
                        return {
                            // 保留原始字段的数据库基础信息（只用于显示和验证）
                            name: column.name,
                            type: column.type,
                            data_type: column.data_type,
                            comment: column.comment,
                            nullable: column.nullable,
                            is_primary: column.is_primary,
                            is_auto_increment: column.is_auto_increment,
                            max_length: column.max_length,
                            options: column.options, // 数据库的 ENUM/SET 选项
                            
                            // 完全使用已保存的配置（覆盖所有其他属性）
                            ...savedField,
                        };
                    } else {
                        // 字段在已保存配置中不存在：使用原始字段数据并自动识别
                        console.log(`[字段配置加载] 发现新字段: ${columnName}，将使用自动识别`);
                        
                        const newField = {
                            ...column,
                            form_type: guessFormType(column),
                            model_type: guessModelType(column),
                            column_type: guessRenderType(column),
                            show_in_list: guessShowInList(column, columns),
                            searchable: guessSearchable(column),
                            sortable: guessSortable(column),
                            editable: guessEditable(column),
                            required: guessRequired(column),
                        };
                        
                        return newField;
                    }
                });
                
                // 2. 检查已保存配置中多出来的字段（在配置中存在但不在 columns 中）
                const removedFields = [];
                savedFieldsConfig.forEach(savedField => {
                    const fieldName = savedField.name;
                    const existsInColumns = columns.some(col => col.name === fieldName);
                    if (!existsInColumns) {
                        removedFields.push(fieldName);
                        console.log(`[字段配置加载] 发现已删除的字段: ${fieldName}，将从配置中移除`);
                    }
                });
                
                if (removedFields.length > 0) {
                    console.warn('[字段配置加载] 以下字段已从数据库表中删除，将在配置中移除:', removedFields);
                }
                
                // 3. 如果有已保存的配置，按照已保存配置的数组顺序排列字段
                // 如果没有已保存的配置，保持数据库字段的原始顺序
                if (savedFieldsConfig.length > 0) {
                    // 创建已保存配置的顺序映射（按数组索引）
                    const savedOrderMap = new Map();
                    savedFieldsConfig.forEach((field, index) => {
                        if (field.name) {
                            savedOrderMap.set(field.name, index);
                        }
                    });
                    
                    // 按照已保存配置的顺序排序 mergedColumns
                    mergedColumns.sort((a, b) => {
                        const orderA = savedOrderMap.has(a.name) ? savedOrderMap.get(a.name) : 999999;
                        const orderB = savedOrderMap.has(b.name) ? savedOrderMap.get(b.name) : 999999;
                        
                        // 如果都在已保存配置中，按照保存顺序
                        if (orderA !== 999999 && orderB !== 999999) {
                            return orderA - orderB;
                        }
                        
                        // 如果只有一个在已保存配置中，已保存的在前
                        if (orderA !== 999999) return -1;
                        if (orderB !== 999999) return 1;
                        
                        // 如果都不在已保存配置中，保持数据库原始顺序
                        const indexA = columns.findIndex(col => col.name === a.name);
                        const indexB = columns.findIndex(col => col.name === b.name);
                        return (indexA === -1 ? 999999 : indexA) - (indexB === -1 ? 999999 : indexB);
                    });
                }
                
                // 4. 统计信息
                const newFieldsCount = mergedColumns.filter(col => !savedConfigMap[col.name]).length;
                console.log('[字段配置加载] 字段比对完成:', {
                    '原始字段数': columns.length,
                    '已保存配置字段数': savedFieldsConfig.length,
                    '合并后字段数': mergedColumns.length,
                    '新增字段数': newFieldsCount,
                    '删除字段数': removedFields.length,
                    '已按保存顺序排列': savedFieldsConfig.length > 0
                });
                console.log('[字段配置加载] 字段顺序（按数组顺序）:', mergedColumns.map((col, i) => ({
                    index: i,
                    name: col.name
                })));
                
                fieldsData = mergedColumns;
                renderFieldsConfig(fieldsData);
                
                // 隐藏加载状态，显示内容
                loadingEl.style.display = 'none';
                areaEl.style.display = 'block';
                reloadBtn.style.display = 'block';
            } else {
                throw new Error(data.msg || data.message || '加载失败');
            }
        })
        .catch(error => {
            console.error('加载字段配置失败:', error);
            
            // 显示错误信息
            loadingEl.style.display = 'none';
            errorEl.style.display = 'block';
            document.getElementById('fieldsErrorMsg').textContent = error.message || '加载字段配置失败，请重试';
            reloadBtn.style.display = 'block';
        });
}

// ==================== 渲染相关 ====================

/**
 * 从字段注释中提取字段名
 * @param {string} comment - 字段注释
 *   - 如果包含冒号，例如："用户名:用户的登录名称"，返回冒号前的部分："用户名"
 *   - 如果不包含冒号，例如："用户名"，返回整个注释："用户名"
 * @returns {string} 字段名
 */
function extractFieldNameFromComment(comment) {
    if (!comment || typeof comment !== 'string') {
        return '';
    }
    
    // 查找第一个冒号的位置
    const colonIndex = comment.indexOf(':');
    
    // 如果找到冒号，返回冒号前的部分（去除首尾空格）
    if (colonIndex > 0) {
        return comment.substring(0, colonIndex).trim();
    }
    
    // 如果没有冒号，整个注释都是字段名称（去除首尾空格）
    return comment.trim();
}

/**
 * 根据数据库字段信息推断表单类型（前端自动识别）
 * @param {Object} column - 字段配置对象（原始数据库字段信息）
 * @returns {string} 表单类型
 */
function guessFormType(column) {
    if (!column) return 'text';
    
    const fieldName = (column.name || '').toLowerCase();
    const dataType = (column.data_type || '').toLowerCase();
    const type = (column.type || '').toLowerCase();
    const comment = (column.comment || '').toLowerCase();
    
    // 1. 字段名以 _id 或 _ids 结尾：关联选择
    if (fieldName.endsWith('_id') || fieldName.endsWith('_ids')) {
        return 'relation';
    }
    
    // 2. 字段名包含特定关键词
    if (fieldName.includes('password') || fieldName.includes('pwd')) {
        return 'password';
    }
    if (fieldName.includes('email')) {
        return 'email';
    }
    if (fieldName.includes('phone') || fieldName.includes('mobile') || fieldName.includes('tel')) {
        return 'text'; // 电话号使用文本
    }
    if (fieldName.includes('url') || fieldName.includes('link')) {
        return 'text'; // URL使用文本
    }
    if (fieldName.includes('avatar') || fieldName.includes('logo') || fieldName.includes('image') || fieldName.includes('photo')) {
        // 如果是单个图片字段
        if (!fieldName.endsWith('s') && !fieldName.includes('_list')) {
            return 'image';
        }
        // 如果是多个图片字段
        return 'images';
    }
    if (fieldName.includes('icon')) {
        return 'icon';
    }
    if (fieldName.includes('file') && !fieldName.includes('image')) {
        return 'file';
    }
    if (fieldName.includes('content') || fieldName.includes('description') || fieldName.includes('detail')) {
        // 如果内容较长，使用富文本或文本域
        if (column.max_length && column.max_length > 500) {
            return 'rich_text';
        }
        return 'textarea';
    }
    
    // 3. 根据数据类型推断
    // ENUM/SET 类型：下拉选择
    if (dataType === 'enum' || dataType === 'set') {
        // 如果选项数量 <= 2，使用单选框；否则使用下拉选择
        const options = column.options || [];
        if (options.length > 0 && options.length <= 2) {
            return 'radio';
        }
        return 'select';
    }
    
    // 布尔类型：开关
    if (dataType === 'boolean' || dataType === 'tinyint' && type.includes('(1)')) {
        return 'switch';
    }
    
    // 日期时间类型
    if (dataType === 'date') {
        return 'date';
    }
    if (dataType === 'datetime' || dataType === 'timestamp') {
        return 'datetime';
    }
    if (dataType === 'time') {
        return 'text'; // 时间使用文本输入
    }
    
    // 数字类型
    if (['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double', 'real'].includes(dataType)) {
        // 如果字段名包含 count、num、amount、price、money 等，可能是数字区间
        if (fieldName.includes('count') || fieldName.includes('num') || fieldName.includes('amount') || fieldName.includes('price') || fieldName.includes('money')) {
            // 如果是 _count 结尾，使用数字区间搜索
            if (fieldName.endsWith('_count')) {
                return 'number_range';
            }
        }
        return 'number';
    }
    
    // JSON 类型：根据字段名判断
    if (dataType === 'json' || dataType === 'jsonb') {
        if (fieldName.includes('image') || fieldName.includes('photo') || fieldName.includes('avatar')) {
            return 'images';
        }
        return 'text'; // JSON 类型默认使用文本输入
    }
    
    // TEXT/LONGTEXT 类型：根据字段名和长度判断
    if (dataType === 'text' || dataType === 'longtext') {
        if (fieldName.includes('content') || fieldName.includes('description') || fieldName.includes('detail')) {
            return 'rich_text';
        }
        return 'textarea';
    }
    
    // VARCHAR/CHAR 类型：根据长度判断
    if (dataType === 'varchar' || dataType === 'char') {
        const maxLength = column.max_length || 0;
        if (maxLength > 500) {
            return 'textarea';
        }
        if (maxLength > 200) {
            return 'textarea';
        }
        return 'text';
    }
    
    // 默认返回文本
    return 'text';
}

/**
 * 根据数据库字段信息推断模型类型（前端自动识别）
 * @param {Object} column - 字段配置对象（原始数据库字段信息）
 * @returns {string} 模型类型
 */
function guessModelType(column) {
    if (!column) return 'string';
    
    const fieldName = (column.name || '').toLowerCase();
    const dataType = (column.data_type || '').toLowerCase();
    const type = (column.type || '').toLowerCase();
    
    // *_ids + LONGTEXT/TEXT → array（关联多选字段）
    if (fieldName.endsWith('_ids') && (dataType === 'longtext' || dataType === 'text')) {
        return 'array';
    }
    
    // 整数类型
    if (['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint'].includes(dataType)) {
        return 'integer';
    }
    
    // 浮点数类型
    if (['decimal', 'numeric', 'float', 'real', 'double'].includes(dataType)) {
        return 'float';
    }
    
    // 布尔类型
    if (dataType === 'boolean' || (dataType === 'tinyint' && type.includes('(1)'))) {
        return 'boolean';
    }
    
    // 日期时间类型
    if (dataType === 'datetime' || dataType === 'timestamp') {
        return 'datetime';
    }
    if (dataType === 'date') {
        return 'date';
    }
    
    // JSON 类型
    if (dataType === 'json' || dataType === 'jsonb') {
        return 'array';
    }
    
    // 默认返回字符串
    return 'string';
}

/**
 * 根据数据库字段信息推断列渲染类型（前端自动识别）
 * @param {Object} column - 字段配置对象（原始数据库字段信息）
 * @returns {string} 列渲染类型
 */
function guessRenderType(column) {
    if (!column) return 'text';
    
    const fieldName = (column.name || '').toLowerCase();
    const dataType = (column.data_type || '').toLowerCase();
    const type = (column.type || '').toLowerCase();
    const comment = (column.comment || '').toLowerCase();
    
    // 1. 字段名以 _id 或 _ids 结尾：关联渲染
    if (fieldName.endsWith('_id') || fieldName.endsWith('_ids')) {
        return 'relation';
    }
    
    // 2. 字段名包含特定关键词
    if (fieldName.includes('avatar') || fieldName.includes('logo') || fieldName.includes('image') || fieldName.includes('photo')) {
        // 如果是单个图片字段
        if (!fieldName.endsWith('s') && !fieldName.includes('_list')) {
            return 'image';
        }
        // 如果是多个图片字段
        return 'images';
    }
    if (fieldName.includes('icon')) {
        return 'icon';
    }
    if (fieldName.includes('url') || fieldName.includes('link')) {
        return 'link';
    }
    if (fieldName.includes('code') || fieldName.includes('json') || fieldName.includes('config')) {
        return 'code';
    }
    
    // 3. 根据数据类型推断
    // 布尔类型：开关
    if (dataType === 'boolean' || (dataType === 'tinyint' && type.includes('(1)'))) {
        return 'switch';
    }
    
    // ENUM/SET 类型：徽章（如果有选项）
    if (dataType === 'enum' || dataType === 'set') {
        const options = column.options || [];
        if (options.length > 0) {
            return 'badge';
        }
        return 'text';
    }
    
    // 日期时间类型
    if (dataType === 'date') {
        return 'date';
    }
    if (dataType === 'datetime' || dataType === 'timestamp') {
        return 'date';
    }
    
    // 数字类型
    if (['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double', 'real'].includes(dataType)) {
        return 'number';
    }
    
    // JSON 类型：代码显示
    if (dataType === 'json' || dataType === 'jsonb') {
        return 'code';
    }
    
    // TEXT/LONGTEXT 类型：根据字段名判断
    if (dataType === 'text' || dataType === 'longtext') {
        if (fieldName.includes('code') || fieldName.includes('json') || fieldName.includes('config')) {
            return 'code';
        }
        return 'text';
    }
    
    // 默认返回文本
    return 'text';
}

/**
 * 根据数据库字段信息推断是否在列表中默认显示（前端自动识别）
 * @param {Object} column - 字段配置对象（原始数据库字段信息）
 * @param {Array} allColumns - 所有字段配置数组（用于判断创建时间和更新时间）
 * @returns {boolean} 是否默认显示
 */
function guessShowInList(column, allColumns = []) {
    if (!column) return true;
    
    const fieldName = (column.name || '').toLowerCase();
    const dataType = (column.data_type || '').toLowerCase();
    
    // 特殊字段：id、content、updated_at 默认必须显示
    if (fieldName === 'id' || fieldName === 'content' || fieldName === 'updated_at') {
        return true;
    }
    
    // 主键、自增字段：默认不显示（通常不需要在列表中显示）
    // 但 id 字段已经在上面特殊处理了，所以这里不会影响 id
    if (column.is_primary || column.is_auto_increment) {
        return false;
    }
    
    // 软删除字段：默认不显示
    if (fieldName === 'deleted_at') {
        return false;
    }
    
    // 密码字段：默认不显示（安全考虑）
    if (fieldName.includes('password')) {
        return false;
    }
    
    // 更新时间字段：默认显示（content 已经在上面特殊处理了）
    // 注意：updated_at 已经在上面特殊处理了，所以这里不会影响它
    if (fieldName === 'updated_at') {
        // 检查是否有 created_at 字段
        const hasCreatedAt = allColumns.some(col => {
            const colName = (col.name || '').toLowerCase();
            return colName === 'created_at';
        });
        // 如果有创建时间字段，则更新时间不显示
        if (hasCreatedAt) {
            return false;
        }
    }
    
    // 大文本字段：默认显示（用户可以根据需要取消）
    // 即使是 text/longtext 字段，也默认显示，让用户自己决定是否隐藏
    // 但如果是 content、body、html 等超长内容字段，默认不显示
    // 注意：content 字段已经在上面特殊处理了，所以这里不会影响它
    if (dataType === 'text' || dataType === 'longtext' || dataType === 'mediumtext') {
        // 超长内容字段：默认不显示
        if (fieldName.includes('content') || fieldName.includes('body') || 
            fieldName.includes('html') || fieldName.includes('detail')) {
            return false;
        }
        // 其他大文本字段（如描述、简介等）：默认显示
        return true;
    }
    
    // JSON 字段：默认显示（用户可以根据需要取消）
    // 让用户自己决定是否在列表中显示 JSON 字段
    // if (dataType === 'json' || dataType === 'jsonb') {
    //     return false;
    // }
    
    // 其他字段：默认全部显示（多数字段都需要默认列出）
    return true;
}

/**
 * 根据数据库字段信息推断是否可搜索（前端自动识别）
 * @param {Object} column - 字段配置对象（原始数据库字段信息）
 * @returns {boolean} 是否可搜索
 */
function guessSearchable(column) {
    if (!column) return false;
    
    const fieldName = (column.name || '').toLowerCase();
    const dataType = (column.data_type || '').toLowerCase();
    
    // id 字段：默认可以搜索（精确搜索）
    if (fieldName === 'id') {
        return true;
    }
    
    // 主键、自增字段：不可搜索（通常不需要，但 id 字段除外）
    if (column.is_primary || column.is_auto_increment) {
        return false;
    }
    
    // 软删除字段：不可搜索
    if (fieldName === 'deleted_at') {
        return false;
    }
    
    // 密码字段：不可搜索（安全考虑）
    if (fieldName.includes('password')) {
        return false;
    }
    
    // 大文本字段：不可搜索（性能考虑）
    if (dataType === 'text' || dataType === 'longtext' || dataType === 'mediumtext') {
        return false;
    }
    
    // JSON 字段：不可搜索（需要特殊处理）
    if (dataType === 'json' || dataType === 'jsonb') {
        return false;
    }
    
    // 布尔字段：可搜索（通常用于筛选）
    if (dataType === 'boolean' || (dataType === 'tinyint' && column.type && column.type.includes('(1)'))) {
        return true;
    }
    
    // 外键字段：可搜索（关联搜索）
    if (fieldName.endsWith('_id') || fieldName.endsWith('_ids')) {
        return true;
    }
    
    // 图片、文件字段：不可搜索（存储的是文件路径，不方便搜索）
    const fileImageFields = ['image', 'img', 'photo', 'picture', 'pic', 'avatar', 'logo', 'icon', 'file', 'upload', 'attachment', 'media', 'video', 'audio'];
    if (fileImageFields.some(field => fieldName.includes(field))) {
        return false;
    }
    
    // 常见搜索字段：可搜索
    const searchableFields = ['name', 'title', 'username', 'email', 'mobile', 'phone', 'code', 'sn', 'no', 'number'];
    if (searchableFields.some(field => fieldName.includes(field))) {
        return true;
    }
    
    // 日期时间字段：可搜索（日期区间搜索）
    if (dataType === 'date' || dataType === 'datetime' || dataType === 'timestamp') {
        return true;
    }
    
    // 数字字段：可搜索（数字区间搜索）
    if (['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double'].includes(dataType)) {
        return true;
    }
    
    // 字符串字段：可搜索（模糊搜索）
    if (dataType === 'varchar' || dataType === 'char') {
        return true;
    }
    
    // 默认不可搜索
    return false;
}

/**
 * 根据数据库字段信息推断是否可排序（前端自动识别）
 * @param {Object} column - 字段配置对象（原始数据库字段信息）
 * @returns {boolean} 是否可排序
 */
function guessSortable(column) {
    if (!column) return true;
    
    const fieldName = (column.name || '').toLowerCase();
    const dataType = (column.data_type || '').toLowerCase();
    
    // 主键、自增字段：可排序
    if (column.is_primary || column.is_auto_increment) {
        return true;
    }
    
    // 软删除字段：不可排序
    if (fieldName === 'deleted_at') {
        return false;
    }
    
    // 密码字段：不可排序（安全考虑）
    if (fieldName.includes('password')) {
        return false;
    }
    
    // 大文本字段：不可排序（性能考虑）
    if (dataType === 'text' || dataType === 'longtext' || dataType === 'mediumtext') {
        return false;
    }
    
    // JSON 字段：不可排序（需要特殊处理）
    if (dataType === 'json' || dataType === 'jsonb') {
        return false;
    }
    
    // 日期时间字段：可排序
    if (dataType === 'date' || dataType === 'datetime' || dataType === 'timestamp') {
        return true;
    }
    
    // 数字字段：可排序
    if (['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double'].includes(dataType)) {
        return true;
    }
    
    // 字符串字段：可排序（但性能较差，默认允许）
    if (dataType === 'varchar' || dataType === 'char') {
        return true;
    }
    
    // 布尔字段：可排序
    if (dataType === 'boolean' || (dataType === 'tinyint' && column.type && column.type.includes('(1)'))) {
        return true;
    }
    
    // 默认可排序
    return true;
}

/**
 * 根据数据库字段信息推断是否可编辑（前端自动识别）
 * @param {Object} column - 字段配置对象（原始数据库字段信息）
 * @returns {boolean} 是否可编辑
 */
function guessEditable(column) {
    if (!column) return true;
    
    const fieldName = (column.name || '').toLowerCase();
    
    // 主键、自增字段：不可编辑
    if (column.is_primary || column.is_auto_increment) {
        return false;
    }
    
    // 创建时间、更新时间：不可编辑（通常由系统自动管理）
    if (fieldName === 'created_at' || fieldName === 'updated_at') {
        return false;
    }
    
    // 软删除字段：不可编辑（通常由系统自动管理）
    if (fieldName === 'deleted_at') {
        return false;
    }
    
    // 统计字段（以 _count 结尾）：不可编辑（关联统计字段，由系统自动计算）
    if (fieldName.endsWith('_count')) {
        return false;
    }
    
    // 其他字段：可编辑
    return true;
}

/**
 * 根据数据库字段信息推断是否必填（前端自动识别）
 * @param {Object} column - 字段配置对象（原始数据库字段信息）
 * @returns {boolean} 是否必填
 */
function guessRequired(column) {
    if (!column) return false;
    
    const fieldName = (column.name || '').toLowerCase();
    
    // 主键、自增字段：必填（但通常不需要在表单中显示）
    if (column.is_primary || column.is_auto_increment) {
        return true;
    }
    
    // 数据库层面不可为空：必填
    if (!column.nullable) {
        return true;
    }
    
    // 创建时间、更新时间：不必填（由系统自动管理）
    if (fieldName === 'created_at' || fieldName === 'updated_at' || fieldName === 'deleted_at') {
        return false;
    }
    
    // 常见必填字段
    const requiredFields = ['name', 'title', 'username', 'email', 'mobile', 'phone', 'code', 'sn', 'no'];
    if (requiredFields.some(field => fieldName === field || fieldName.endsWith('_' + field))) {
        // 但如果是可选的，数据库允许为空，则不必填
        if (column.nullable) {
            return false;
        }
        return true;
    }
    
    // 外键字段：根据数据库约束判断
    if (fieldName.endsWith('_id')) {
        // 如果数据库不允许为空，则必填
        return !column.nullable;
    }
    
    // 默认根据数据库约束判断
    return !column.nullable;
}

/**
 * 根据字段名推断关联表名
 * @param {string} fieldName - 字段名，例如：user_id, category_id, role_ids
 * @returns {string} 推断的关联表名，例如：users, categories, roles
 */
function inferRelationTableName(fieldName) {
    if (!fieldName) return '';
    
    const name = fieldName.toLowerCase();
    
    // 去掉 _id 或 _ids 后缀
    let tableName = name;
    if (name.endsWith('_ids')) {
        tableName = name.slice(0, -4); // 去掉 '_ids'
    } else if (name.endsWith('_id')) {
        tableName = name.slice(0, -3); // 去掉 '_id'
    } else {
        return ''; // 不是以 _id 或 _ids 结尾，返回空
    }
    
    if (!tableName) return '';
    
    // 特殊关联表
    const normalized = tableName.toLowerCase();
    if (RELATION_TABLE_NAME_MAP[normalized]) {
        return RELATION_TABLE_NAME_MAP[normalized];
    }
    
    // 默认直接返回去除后缀的字段名，避免误判
    return tableName;
}

/**
 * 根据关联表名推断默认显示字段
 * @param {string} tableName
 * @returns {string}
 */
function inferRelationLabelColumn(tableName) {
    if (!tableName) {
        return RELATION_DEFAULT_LABEL_COLUMN;
    }
    
    const normalized = tableName.toLowerCase();
    if (RELATION_LABEL_COLUMN_MAP[normalized]) {
        return RELATION_LABEL_COLUMN_MAP[normalized];
    }
    
    // 简单处理复数形式
    if (normalized.endsWith('s')) {
        const singular = normalized.slice(0, -1);
        if (RELATION_LABEL_COLUMN_MAP[singular]) {
            return RELATION_LABEL_COLUMN_MAP[singular];
        }
    }
    
    return RELATION_DEFAULT_LABEL_COLUMN;
}

/**
 * 根据字段类型推断默认搜索类型
 * @param {Object} column - 字段配置对象
 * @returns {string} 默认搜索类型
 */
function inferDefaultSearchType(column) {
    const formType = column.form_type || 'text';
    const dataType = (column.data_type || column.type || '').toLowerCase();
    const fieldName = (column.name || '').toLowerCase();
    
    // ID 字段（主键或字段名为 id）：使用精确搜索
    if (fieldName === 'id' || column.is_primary_key || column.is_primary) {
        return 'exact';
    }
    
    // 字段名以 _id 或 _ids 结尾：使用关联搜索
    if (fieldName.endsWith('_id') || fieldName.endsWith('_ids')) {
        return 'relation';
    }
    
    // 选择类型（select、radio、switch、relation）：使用下拉选择搜索
    if (['select', 'radio', 'switch', 'relation'].includes(formType)) {
        return 'select';
    }
    
    // 日期时间类型：使用日期区间搜索
    if (['date', 'datetime', 'datetime-local'].includes(formType) ||
        ['date', 'datetime', 'timestamp', 'time'].includes(dataType)) {
        return 'date_range';
    }
    
    // 数字类型：只有当字段名以 _count 结尾时才使用数字区间搜索
    if (fieldName.endsWith('_count')) {
        return 'number_range';
    }
    
    // 关联类型：使用关联搜索
    if (formType === 'relation' || column.relation) {
        return 'relation';
    }
    
    // 文本类型：使用模糊搜索（默认）
    return 'like';
}

/**
 * 渲染字段配置
 */
function renderFieldsConfig(columns) {
    const areaEl = document.getElementById('fieldsConfigArea');
    
    if (!columns || columns.length === 0) {
        areaEl.innerHTML = '<p class="text-muted text-center">暂无字段数据</p>';
        return;
    }
    
    // 构建表格 HTML
    let html = `
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        {{-- 已禁用：拖动功能BUG太多，暂时禁用，待后续优化 --}}
                        {{-- <th style="width: 50px;" class="text-center">
                            <i class="bi bi-grip-vertical" title="拖拽排序"></i>
                        </th> --}}
                        <th style="width: 120px;">字段名</th>
                        <th style="width: 120px;">数据库信息</th>
                        <th style="width: 100px;">模型类型</th>
                        <th style="width: 120px;">字段名称</th>
                        <th style="width: 100px;">表单类型</th>
                        <th style="width: 100px;">列渲染</th>
                        <th style="width: 140px;">列表功能</th>
                        <th style="width: 140px;">其他功能</th>
                        <th style="width: 80px;">操作</th>
                    </tr>
                </thead>
                <tbody id="fieldsTableBody">
    `;
    
    columns.forEach((column, index) => {
        // 获取字段显示名称（优先使用已保存的 field_name，否则从注释中提取，最后使用字段名）
        let fieldName = '';
        
        // 优先使用已保存的 field_name（如果存在且不为空）
        // 这样可以确保用户手动设置的字段名称不会被数据库注释覆盖
        if (column.field_name && column.field_name.trim() !== '') {
            fieldName = column.field_name.trim();
        } else if (column.comment) {
            // 如果 field_name 不存在或为空，才从注释中提取字段名称作为默认值
            // - 如果注释包含冒号，提取冒号前的部分
            // - 如果注释不包含冒号，使用整个注释作为字段名称
            const extractedName = extractFieldNameFromComment(column.comment);
            if (extractedName) {
                fieldName = extractedName;
            }
        }
        
        // 如果仍然没有，使用字段名作为默认值
        if (!fieldName) {
            fieldName = column.name;
        }
        
        // 获取字段名（用于判断类型）
        const columnName = (column.name || '').toLowerCase();
        
        // 获取模型类型：如果没有，使用前端自动识别
        const modelType = column.model_type || guessModelType(column) || 'string';
        
        // 获取表单类型：如果没有，使用前端自动识别
        let formType = column.form_type;
        if (!formType) {
            formType = guessFormType(column) || 'text';
        }
        
        // 获取列渲染类型：如果没有，使用前端自动识别
        let columnType = column.column_type || column.render_type;
        if (!columnType) {
            columnType = guessRenderType(column) || 'text';
        }
        
        // 获取搜索配置
        const searchConfig = column.search || {};
        // 如果已有保存的搜索类型，使用保存的；否则根据字段类型推断默认搜索类型
        const searchType = searchConfig.type || column.search_type || inferDefaultSearchType(column);
        // 可搜索：如果已有保存的值，使用保存的；否则使用智能识别
        const searchable = searchConfig.enabled !== undefined ? searchConfig.enabled : 
                          (column.searchable !== undefined ? column.searchable : guessSearchable(column));
        
        // 默认显示：如果已有保存的值，使用保存的；否则使用智能识别（传入所有字段用于判断创建时间和更新时间）
        const showInList = column.show_in_list !== undefined ? column.show_in_list : 
                          (column.listable !== undefined ? column.listable : guessShowInList(column, columns));
        
        // 列表默认显示：如果已有保存的值，使用保存的；否则默认为 true（除非明确设置为 false）
        // 特殊字段：id、content、updated_at 在初始化时默认显示，但用户可以修改
        const isSpecialField = columnName === 'id' || columnName === 'content' || columnName === 'updated_at';
        // 优先使用已保存的值，只有在没有保存值时才使用特殊字段的默认值
        const listDefault = column.list_default !== undefined ? column.list_default : 
                          (isSpecialField ? true : true);
        
        // 可排序：如果已有保存的值，使用保存的；否则使用智能识别
        const sortable = column.sortable !== undefined ? column.sortable : guessSortable(column);
        
        // 可编辑：如果已有保存的值，使用保存的；否则使用智能识别
        const editable = column.editable !== undefined ? column.editable : guessEditable(column);
        
        // 必填：如果已有保存的值，使用保存的；否则使用智能识别
        const required = column.required !== undefined ? column.required : guessRequired(column);
        
        // 获取关联配置
        const relationConfig = column.relation || {};
        
        // 如果字段名以 _id 或 _ids 结尾，且关联表名为空，自动填充推断的表名
        if ((columnName.endsWith('_id') || columnName.endsWith('_ids')) && !relationConfig.table) {
            const inferredTableName = inferRelationTableName(column.name);
            if (inferredTableName) {
                relationConfig.table = inferredTableName;
            }
        }
        
        if (!relationConfig.label_column) {
            relationConfig.label_column = inferRelationLabelColumn(relationConfig.table);
        }
        
        // 处理字段注释显示（作为标签）
        let commentBadge = '';
        if (column.comment && column.comment.trim()) {
            const comment = column.comment.trim();
            commentBadge = `<span class="badge bg-secondary" style="font-size: 0.75rem; max-width: 90px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle;" title="${escapeHtml(comment)}">${escapeHtml(comment)}</span>`;
        }
        
        html += `
            <tr class="field-row" data-index="${index}" data-field-name="${escapeHtml(column.name)}">
                <td style="word-break: break-word;">
                    <div style="margin-bottom: 4px;">
                        <strong>${escapeHtml(column.name)}</strong>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 4px; align-items: center;">
                        ${column.is_primary ? '<span class="badge bg-danger">主键</span>' : ''}
                        ${column.is_auto_increment ? '<span class="badge bg-info">自增</span>' : ''}
                        ${!column.nullable ? '<span class="badge bg-warning">必填</span>' : ''}
                    </div>
                </td>
                <td style="max-width: 200px;">
                    <div class="d-flex flex-column gap-1" style="min-width: 0;">
                        <small class="text-muted">${escapeHtml(column.type || column.data_type)}</small>
                        ${commentBadge}
                    </div>
                </td>
                <td>
                    <select name="fields_config[${index}][model_type]" class="form-select form-select-sm field-model-type" 
                            data-index="${index}">
                        <option value="string" ${modelType === 'string' ? 'selected' : ''}>string</option>
                        <option value="integer" ${modelType === 'integer' ? 'selected' : ''}>integer</option>
                        <option value="float" ${modelType === 'float' ? 'selected' : ''}>float</option>
                        <option value="boolean" ${modelType === 'boolean' ? 'selected' : ''}>boolean</option>
                        <option value="array" ${modelType === 'array' ? 'selected' : ''}>array</option>
                        <option value="datetime" ${modelType === 'datetime' ? 'selected' : ''}>datetime</option>
                        <option value="date" ${modelType === 'date' ? 'selected' : ''}>date</option>
                    </select>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm" 
                           name="fields_config[${index}][field_name]"
                           value="${escapeHtml(fieldName)}"
                           placeholder="字段显示名称">
                </td>
                <td>
                    <select name="fields_config[${index}][form_type]" class="form-select form-select-sm field-form-type" 
                            data-index="${index}">
                        <option value="text" ${column.form_type === 'text' ? 'selected' : ''}>文本框</option>
                        <option value="textarea" ${column.form_type === 'textarea' ? 'selected' : ''}>文本域</option>
                        <option value="rich_text" ${column.form_type === 'rich_text' ? 'selected' : ''}>富文本</option>
                        <option value="number" ${column.form_type === 'number' ? 'selected' : ''}>数字</option>
                        <option value="number_range" ${column.form_type === 'number_range' ? 'selected' : ''}>区间数字</option>
                        <option value="email" ${column.form_type === 'email' ? 'selected' : ''}>邮箱</option>
                        <option value="password" ${column.form_type === 'password' ? 'selected' : ''}>密码</option>
                        <option value="date" ${column.form_type === 'date' ? 'selected' : ''}>日期</option>
                        <option value="datetime" ${column.form_type === 'datetime' ? 'selected' : ''}>日期时间</option>
                        <option value="switch" ${column.form_type === 'switch' ? 'selected' : ''}>开关</option>
                        <option value="radio" ${column.form_type === 'radio' ? 'selected' : ''}>单选框</option>
                        <option value="checkbox" ${column.form_type === 'checkbox' ? 'selected' : ''}>复选框</option>
                        <option value="select" ${column.form_type === 'select' ? 'selected' : ''}>下拉选择</option>
                        <option value="relation" ${column.form_type === 'relation' ? 'selected' : ''}>关联选择</option>
                        <option value="icon" ${column.form_type === 'icon' ? 'selected' : ''}>图标选择</option>
                        <option value="image" ${column.form_type === 'image' ? 'selected' : ''}>单图上传</option>
                        <option value="images" ${column.form_type === 'images' ? 'selected' : ''}>多图上传</option>
                        <option value="file" ${column.form_type === 'file' ? 'selected' : ''}>文件上传</option>
                    </select>
                </td>
                <td>
                    <select name="fields_config[${index}][column_type]" class="form-select form-select-sm column-type-select" 
                            data-index="${index}">
                        <option value="text" ${columnType === 'text' ? 'selected' : ''}>文本</option>
                        <option value="number" ${columnType === 'number' ? 'selected' : ''}>数字</option>
                        <option value="date" ${columnType === 'date' ? 'selected' : ''}>日期</option>
                        <option value="icon" ${columnType === 'icon' ? 'selected' : ''}>图标</option>
                        <option value="image" ${columnType === 'image' ? 'selected' : ''}>单图</option>
                        <option value="images" ${columnType === 'images' ? 'selected' : ''}>多图</option>
                        <option value="switch" ${columnType === 'switch' ? 'selected' : ''}>开关</option>
                        <option value="badge" ${columnType === 'badge' ? 'selected' : ''}>徽章</option>
                        <option value="code" ${columnType === 'code' ? 'selected' : ''}>代码</option>
                        <option value="link" ${columnType === 'link' ? 'selected' : ''}>链接</option>
                        <option value="relation" ${columnType === 'relation' ? 'selected' : ''}>关联</option>
                        <option value="columns" ${columnType === 'columns' ? 'selected' : ''}>列组</option>
                        <option value="custom" ${columnType === 'custom' ? 'selected' : ''}>自定义</option>
                    </select>
                </td>
                <td>
                    <div class="d-flex flex-column gap-1">
                        <div class="form-check form-check-inline m-0">
                            <input type="hidden" name="fields_config[${index}][show_in_list]" value="0">
                            <input type="checkbox" name="fields_config[${index}][show_in_list]" value="1"
                                   ${showInList ? 'checked' : ''} 
                                   class="form-check-input" 
                                   id="show_in_list_${index}">
                            <label class="form-check-label small" for="show_in_list_${index}">
                                <i class="bi bi-list-ul"></i> 列出
                            </label>
                        </div>
                        <div class="form-check form-check-inline m-0">
                            <input type="hidden" name="fields_config[${index}][list_default]" value="0">
                            <input type="checkbox" name="fields_config[${index}][list_default]" value="1"
                                   ${listDefault ? 'checked' : ''} 
                                   class="form-check-input" 
                                   id="list_default_${index}">
                            <label class="form-check-label small" for="list_default_${index}">
                                <i class="bi bi-star"></i> 默认显示
                            </label>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column gap-1">
                        <div class="form-check form-check-inline m-0">
                            <input type="hidden" name="fields_config[${index}][search][enabled]" value="0">
                            <input type="checkbox" name="fields_config[${index}][search][enabled]" value="1"
                                   ${searchable ? 'checked' : ''} 
                                   class="form-check-input" 
                                   id="search_${index}">
                            <label class="form-check-label small" for="search_${index}">
                                <i class="bi bi-search"></i> 搜索
                            </label>
                        </div>
                        <div class="form-check form-check-inline m-0">
                            <input type="hidden" name="fields_config[${index}][sortable]" value="0">
                            <input type="checkbox" name="fields_config[${index}][sortable]" value="1"
                                   ${sortable ? 'checked' : ''} 
                                   class="form-check-input" 
                                   id="sortable_${index}">
                            <label class="form-check-label small" for="sortable_${index}">
                                <i class="bi bi-arrow-down-up"></i> 排序
                            </label>
                        </div>
                        <div class="form-check form-check-inline m-0">
                            <input type="hidden" name="fields_config[${index}][editable]" value="0">
                            <input type="checkbox" name="fields_config[${index}][editable]" value="1"
                                   ${editable ? 'checked' : ''} 
                                   class="form-check-input" 
                                   id="editable_${index}">
                            <label class="form-check-label small" for="editable_${index}">
                                <i class="bi bi-pencil"></i> 编辑
                            </label>
                        </div>
                    </div>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-info" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#fieldDetails-${index}"
                            aria-expanded="false">
                        <i class="bi bi-gear"></i> 详细配置
                    </button>
                </td>
            </tr>
            <!-- 详细配置面板 -->
            <tr>
                <td colspan="10" class="p-0 border-0">
                    <div class="collapse" id="fieldDetails-${index}">
                        <div class="card card-body bg-light m-2">
                            <h6 class="mb-3">
                                <i class="bi bi-sliders text-primary"></i> 
                                字段详细配置：<code class="text-primary">${escapeHtml(column.name)}</code>
                            </h6>
                            
                            <div class="row g-3">
                                <!-- 基础配置 -->
                                <div class="col-md-6">
                                    <label class="form-label">默认值</label>
                                    <input type="text" class="form-control form-control-sm"
                                           name="fields_config[${index}][default_value]"
                                           value="${escapeHtml(column.default_value || '')}"
                                           placeholder="留空表示 NULL">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">占位符</label>
                                    <input type="text" class="form-control form-control-sm"
                                           name="fields_config[${index}][placeholder]"
                                           value="${escapeHtml(column.placeholder || '')}"
                                           placeholder="例如：请输入用户名">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">帮助文本</label>
                                    <input type="text" class="form-control form-control-sm"
                                           name="fields_config[${index}][help]"
                                           value="${escapeHtml(column.help || '')}"
                                           placeholder="例如：请输入3-20个字符">
                                </div>
                                
                                <!-- 搜索配置 -->
                                <div class="col-md-12">
                                    <hr>
                                    <h6 class="mb-3">
                                        <i class="bi bi-search text-success"></i> 搜索配置
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">搜索类型</label>
                                            <select class="form-select form-select-sm search-type-select-detail"
                                                   name="fields_config[${index}][search][type]"
                                                   data-index="${index}">
                                                <option value="like" ${searchType === 'like' ? 'selected' : ''}>模糊搜索</option>
                                                <option value="exact" ${searchType === 'exact' ? 'selected' : ''}>精确匹配</option>
                                                <option value="number_range" ${searchType === 'number_range' ? 'selected' : ''}>数字区间</option>
                                                <option value="date_range" ${searchType === 'date_range' ? 'selected' : ''}>日期区间</option>
                                                <option value="select" ${searchType === 'select' ? 'selected' : ''}>下拉选择</option>
                                                <option value="relation" ${searchType === 'relation' ? 'selected' : ''}>关联搜索</option>
                                            </select>
                                        </div>
                                        <div class="col-md-9">
                                            <label class="form-label">搜索占位符</label>
                                            <input type="text" class="form-control form-control-sm"
                                                   name="fields_config[${index}][search][placeholder]"
                                                   value="${escapeHtml(searchConfig.placeholder || column.search_placeholder || '')}"
                                                   placeholder="例如：请输入关键词搜索">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 关联配置 -->
                                <div class="col-md-12">
                                    <hr>
                                    <h6 class="mb-3">
                                        <i class="bi bi-link-45deg text-info"></i> 关联配置
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">关联表名</label>
                                            <input type="text" class="form-control form-control-sm"
                                                   name="fields_config[${index}][relation][table]"
                                                   value="${escapeHtml(relationConfig.table || '')}"
                                                   placeholder="例如：users">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">显示字段</label>
                                            <input type="text" class="form-control form-control-sm"
                                                   name="fields_config[${index}][relation][label_column]"
                                                   value="${escapeHtml(relationConfig.label_column || inferRelationLabelColumn(relationConfig.table))}"
                                                   placeholder="例如：name">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">值字段</label>
                                            <input type="text" class="form-control form-control-sm"
                                                   name="fields_config[${index}][relation][value_column]"
                                                   value="${escapeHtml(relationConfig.value_column || 'id')}"
                                                   placeholder="例如：id">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">多选</label>
                                            <div class="form-check form-switch mt-2">
                                                <input type="hidden" name="fields_config[${index}][relation][multiple]" value="0">
                                                <input type="checkbox" class="form-check-input"
                                                       name="fields_config[${index}][relation][multiple]"
                                                       value="1"
                                                       ${relationConfig.multiple ? 'checked' : ''}>
                                                <label class="form-check-label">允许多选</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 类型特定属性 -->
                                <div class="col-md-12">
                                    <hr>
                                    <h6 class="mb-3">
                                        <i class="bi bi-code-square text-success"></i> 类型特定属性
                                    </h6>
                                    
                                    <!-- textarea/rich_text 类型属性 -->
                                    <div class="type-attrs-group" data-type="textarea" data-index="${index}">
                                        <div class="alert alert-light border mb-3">
                                            <strong><i class="bi bi-textarea-resize"></i> 文本域/富文本属性</strong>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label">行数 (rows)</label>
                                                <input type="number" class="form-control form-control-sm"
                                                       name="fields_config[${index}][type_attrs][textarea][rows]"
                                                       value="${escapeHtml((column.type_attrs?.textarea?.rows || column.type_attrs?.rich_text?.rows || column.rows || 4))}"
                                                       min="1"
                                                       placeholder="默认：4">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- number 类型属性 -->
                                    <div class="type-attrs-group" data-type="number" data-index="${index}">
                                        <div class="alert alert-light border mb-3">
                                            <strong><i class="bi bi-123"></i> 数字类型属性</strong>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label">最小值 (min_value)</label>
                                                <input type="number" class="form-control form-control-sm"
                                                       name="fields_config[${index}][type_attrs][number][min_value]"
                                                       value="${escapeHtml(column.type_attrs?.number?.min_value || column.min_value || column.min || '')}"
                                                       placeholder="留空表示无限制"
                                                       step="any">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">最大值 (max_value)</label>
                                                <input type="number" class="form-control form-control-sm"
                                                       name="fields_config[${index}][type_attrs][number][max_value]"
                                                       value="${escapeHtml(column.type_attrs?.number?.max_value || column.max_value || column.max || '')}"
                                                       placeholder="留空表示无限制"
                                                       step="any">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">步长 (step)</label>
                                                <input type="number" class="form-control form-control-sm"
                                                       name="fields_config[${index}][type_attrs][number][step]"
                                                       value="${escapeHtml(column.type_attrs?.number?.step || column.step || column.number_step || '1')}"
                                                       placeholder="默认：1"
                                                       step="any">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- string 类型属性 (text, textarea, rich_text, email, password) -->
                                    <div class="type-attrs-group" data-type="string" data-index="${index}">
                                        <div class="alert alert-light border mb-3">
                                            <strong><i class="bi bi-type"></i> 字符串类型属性</strong>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label">最大长度 (max_length)</label>
                                                <input type="number" class="form-control form-control-sm"
                                                       name="fields_config[${index}][type_attrs][string][max_length]"
                                                       value="${escapeHtml(column.type_attrs?.string?.max_length || column.max_length || column.max || '')}"
                                                       placeholder="例如：255"
                                                       min="1">
                                                <small class="text-muted">字符串最大字符数</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 选项配置（键值对） -->
                                <div class="col-md-12" id="attr-options-${index}">
                                    <hr>
                                    <h6 class="mb-3">
                                        <i class="bi bi-list-ul text-primary"></i> 选项配置（键值对）
                                    </h6>
                                    <div class="alert alert-info mb-3">
                                        <i class="bi bi-info-circle"></i> 
                                        <strong>提示：</strong>配置选项的键值对，键为存储值，值为显示文本。适用于下拉选择、单选框、复选框等表单类型，以及徽章、文本等列渲染类型。
                                        <br><small class="text-muted"><i class="bi bi-palette"></i> <strong>颜色配置：</strong>当列渲染类型为"徽章"时，可以为每个选项设置颜色。留空则使用智能匹配（根据文本自动选择颜色）</small>
                                    </div>
                                    <div class="options-list" data-index="${index}">
                                        ${renderOptionsList(index, column)}
                                    </div>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary mt-2" 
                                            onclick="addOption(${index})">
                                        <i class="bi bi-plus-circle"></i> 添加选项
                                    </button>
                                    
                                    <!-- 徽章默认颜色配置 -->
                                    <div class="badge-default-color-config mt-3">
                                        <div class="alert alert-warning border mb-2">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="bi bi-palette-fill text-warning me-2"></i>
                                                <strong>默认颜色配置</strong>
                                            </div>
                                            <small class="text-muted">
                                                当字段值不在上述选项列表中时，将使用此默认颜色显示徽章
                                            </small>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <label class="form-label">
                                                    <i class="bi bi-palette"></i> 未定义值的默认颜色
                                                </label>
                                                <select class="form-select form-select-sm badge-default-color-select" 
                                                        name="fields_config[${index}][badge_default_color]"
                                                        data-index="${index}"
                                                        title="选择未定义值的默认徽章颜色"
                                                        onchange="updateBadgePreview(${index})">
                                                    ${BADGE_COLORS.map(color => 
                                                        `<option value="${color.value}" ${(column.badge_default_color || '') === color.value ? 'selected' : ''}>${color.label}</option>`
                                                    ).join('')}
                                                </select>
                                                <small class="text-muted">当值不在选项列表中时，使用此颜色显示徽章</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 徽章预览区域 -->
                                    <div class="badge-preview-area mt-3" id="badge-preview-${index}">
                                        <div class="alert alert-light border">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="bi bi-eye text-primary me-2"></i>
                                                <strong>徽章预览效果：</strong>
                                            </div>
                                            <div class="badge-preview-list" data-index="${index}">
                                                ${renderBadgePreview(index, column)}
                                            </div>
                                        <div class="badge-default-preview mt-3 text-muted" data-index="${index}">
                                            ${renderBadgeDefaultPreview(column)}
                                        </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 其他选项 -->
                                <div class="col-md-12">
                                    <hr>
                                    <h6 class="mb-3">
                                        <i class="bi bi-toggle-on text-warning"></i> 其他选项
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="fields_config[${index}][required]" value="0">
                                                <input type="checkbox" class="form-check-input"
                                                       name="fields_config[${index}][required]"
                                                       value="1"
                                                       ${required ? 'checked' : ''}>
                                                <label class="form-check-label">必填</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="fields_config[${index}][disabled]" value="0">
                                                <input type="checkbox" class="form-check-input"
                                                       name="fields_config[${index}][disabled]"
                                                       value="1"
                                                       ${column.disabled ? 'checked' : ''}>
                                                <label class="form-check-label">禁用</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="fields_config[${index}][readonly]" value="0">
                                                <input type="checkbox" class="form-check-input"
                                                       name="fields_config[${index}][readonly]"
                                                       value="1"
                                                       ${column.readonly ? 'checked' : ''}>
                                                <label class="form-check-label">只读</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        `;
        
        // 隐藏字段：字段基本信息
        html += `
            <input type="hidden" name="fields_config[${index}][name]" value="${escapeHtml(column.name)}">
            <input type="hidden" name="fields_config[${index}][type]" value="${escapeHtml(column.type || '')}">
            <input type="hidden" name="fields_config[${index}][data_type]" value="${escapeHtml(column.data_type || '')}">
            <input type="hidden" name="fields_config[${index}][comment]" value="${escapeHtml(column.comment || '')}">
            <input type="hidden" name="fields_config[${index}][nullable]" value="${column.nullable ? '1' : '0'}">
            <input type="hidden" name="fields_config[${index}][is_primary]" value="${column.is_primary ? '1' : '0'}">
            <input type="hidden" name="fields_config[${index}][is_auto_increment]" value="${column.is_auto_increment ? '1' : '0'}">
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <p class="text-muted">
                <i class="bi bi-info-circle"></i> 共 <strong>${columns.length}</strong> 个字段
            </p>
        </div>
    `;
    
    areaEl.innerHTML = html;
    
    // 初始化拖拽排序功能
    // 已禁用：拖动功能BUG太多，暂时禁用，待后续优化
    // initFieldSortable();
    
    // 检测是否有 deleted_at 字段，如果有则自动开启软删除开关
    const hasDeletedAt = columns.some(column => column.name === 'deleted_at');
    if (hasDeletedAt) {
        const softDeleteCheckbox = document.getElementById('useSoftDelete');
        if (softDeleteCheckbox) {
            // 检测到 deleted_at 字段时，自动开启软删除开关
            softDeleteCheckbox.checked = true;
        }
    }
    
    // 注意：所有类型属性组默认全部显示，不再根据表单类型动态显示/隐藏
    
    // 为所有列类型选择框添加事件监听
    document.querySelectorAll('.column-type-select').forEach(select => {
        select.addEventListener('change', function() {
            const index = this.closest('tr').getAttribute('data-index');
            updateColumnTypeAttributes(index, this.value);
        });
    });
    
    // 为所有表单类型选择框添加事件监听，自动更新搜索类型
    document.querySelectorAll('.field-form-type').forEach(select => {
        select.addEventListener('change', function() {
            const index = parseInt(this.getAttribute('data-index'));
            const formType = this.value;
            const row = this.closest('tr.field-row');
            if (!row) return;
            
            // 从隐藏字段获取数据类型和字段名
            const nameInput = row.querySelector(`input[name="fields_config[${index}][name]"]`);
            const dataTypeInput = row.querySelector(`input[name="fields_config[${index}][data_type]"]`);
            const typeInput = row.querySelector(`input[name="fields_config[${index}][type]"]`);
            const fieldName = nameInput?.value || '';
            const dataType = dataTypeInput?.value || typeInput?.value || '';
            
            // 检查是否有关联配置
            const relationInput = row.querySelector(`input[name="fields_config[${index}][relation][table]"]`);
            const labelInput = row.querySelector(`input[name="fields_config[${index}][relation][label_column]"]`);
            let hasRelation = relationInput && relationInput.value.trim() !== '';
            
            // 如果表单类型变为 relation，且关联表名为空，且字段名以 _id 或 _ids 结尾，自动填充关联表名
            if (formType === 'relation' && !hasRelation) {
                const fieldNameLower = fieldName.toLowerCase();
                if (fieldNameLower.endsWith('_id') || fieldNameLower.endsWith('_ids')) {
                    const inferredTableName = inferRelationTableName(fieldName);
                    if (inferredTableName && relationInput) {
                        relationInput.value = inferredTableName;
                        // 触发 input 事件，确保值被保存
                        relationInput.dispatchEvent(new Event('input', { bubbles: true }));
                        // 更新 hasRelation 状态
                        hasRelation = true;
                        
                        if (labelInput && !labelInput.value.trim()) {
                            labelInput.value = inferRelationLabelColumn(inferredTableName);
                            labelInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }
                }
            }
            
            // 从 fieldsData 中查找原始字段数据，获取主键信息
            const originalColumn = fieldsData.find(col => col.name === fieldName);
            
            // 构建临时字段对象用于推断搜索类型
            const tempColumn = {
                name: fieldName,
                form_type: formType,
                data_type: dataType,
                type: dataType,
                relation: hasRelation ? {} : null,
                is_primary_key: originalColumn ? (originalColumn.is_primary || originalColumn.is_primary_key) : false
            };
            
            // 推断新的搜索类型
            const newSearchType = inferDefaultSearchType(tempColumn);
            
            // 更新详细配置面板中的搜索类型选择框
            const detailSearchTypeSelect = document.querySelector(`#fieldDetails-${index} .search-type-select-detail`);
            if (detailSearchTypeSelect) {
                detailSearchTypeSelect.value = newSearchType;
            }
        });
    });
}

// ==================== 选项管理 ====================

/**
 * 解析选项数据（统一处理数组和对象格式）
 * @param {Array|Object} options - 选项数据
 * @returns {Array} 标准化的选项数组 [{key, value, color}, ...]
 */
function parseOptionsData(options) {
    const optionsArray = [];
    const rawOptions = options || [];
    
    if (Array.isArray(rawOptions)) {
        // 数组格式：[{key: '1', value: '选项1', color: 'primary'}, ...] 或 ['选项1', '选项2']
        rawOptions.forEach((opt, optIndex) => {
            if (typeof opt === 'object' && opt !== null) {
                optionsArray.push({
                    key: opt.key || optIndex.toString(),
                    value: opt.value || opt.label || '',
                    color: opt.color || ''
                });
            } else {
                // 简单值，使用索引作为 key
                optionsArray.push({
                    key: optIndex.toString(),
                    value: opt.toString(),
                    color: ''
                });
            }
        });
    } else if (typeof rawOptions === 'object' && rawOptions !== null) {
        // 对象格式：{'1': '选项1', '2': '选项2'} 或 {'1': {value: '选项1', color: 'primary'}}
        Object.keys(rawOptions).forEach(key => {
            const value = rawOptions[key];
            if (typeof value === 'object' && value !== null) {
                optionsArray.push({
                    key: key,
                    value: value.value || value.label || '',
                    color: value.color || ''
                });
            } else {
                optionsArray.push({
                    key: key,
                    value: value.toString(),
                    color: ''
                });
            }
        });
    }
    
    return optionsArray;
}

// ==================== 字段属性管理 ====================

/**
 * 根据列类型更新属性显示/隐藏
 * @param {number} index - 字段索引
 * @param {string} columnType - 列渲染类型
 */
function updateColumnTypeAttributes(index, columnType) {
    // 所有配置区域现在都默认显示，不再根据列类型显示/隐藏
    // 如果列类型为徽章，更新徽章预览
    if (columnType === 'badge') {
        updateBadgePreview(index);
    }
}

/**
 * 显示字段详细信息（已通过折叠面板实现，此函数保留用于兼容）
 * @param {number} index - 字段索引
 */
function showFieldDetails(index) {
    // 详细配置已通过 Bootstrap collapse 面板实现
    // 此函数保留用于兼容，实际功能由按钮的 data-bs-toggle="collapse" 实现
    const collapseElement = document.getElementById(`fieldDetails-${index}`);
    if (collapseElement) {
        const bsCollapse = new bootstrap.Collapse(collapseElement, {
            toggle: true
        });
    }
}

// ==================== 数据保存 ====================

/**
 * 保存配置
 */
function saveConfig() {
    const form = document.getElementById('configForm');
    const formData = new FormData(form);
    
    // 转换为 JSON 格式（处理嵌套字段，包括 options）
    // 使用更简单的方法：直接构建对象结构
    const data = {};
    const fieldsConfig = {};
    
    // 辅助函数：解析字段名
    function parseFieldName(key) {
        if (!key.startsWith('fields_config[')) {
            return null;
        }
        
        // 移除前缀 fields_config[
        let rest = key.substring('fields_config['.length);
        
        // 提取字段索引
        const indexMatch = rest.match(/^(\d+)\]/);
        if (!indexMatch) return null;
        
        const fieldIndex = parseInt(indexMatch[1]);
        rest = rest.substring(indexMatch[0].length); // 移除 [0]
        
        // 如果没有更多内容，返回基础结构
        if (!rest || rest === '') {
            return { fieldIndex };
        }
        
        // 解析后续部分
        // 格式可能是：[options][0][key] 或 [relation][table] 或 [field_name]
        const parts = [];
        let current = rest;
        
        while (current && current.startsWith('[')) {
            const match = current.match(/^\[([^\]]+)\]/);
            if (!match) break;
            
            parts.push(match[1]);
            current = current.substring(match[0].length);
        }
        
        return {
            fieldIndex,
            parts: parts
        };
    }
    
    // 处理所有表单数据
    for (const [key, value] of formData.entries()) {
        if (key.startsWith('fields_config[')) {
            const parsed = parseFieldName(key);
            if (parsed && parsed.fieldIndex !== undefined) {
                const fieldIndex = parsed.fieldIndex;
                
                if (!fieldsConfig[fieldIndex]) {
                    fieldsConfig[fieldIndex] = {};
                }
                
                if (parsed.parts && parsed.parts.length > 0) {
                    const [firstPart, secondPart, thirdPart] = parsed.parts;
                    
                    if (firstPart === 'options' && secondPart !== undefined && thirdPart !== undefined) {
                        // fields_config[0][options][0][key] 或 fields_config[0][options][0][value]
                        const optionIndex = parseInt(secondPart);
                        if (!fieldsConfig[fieldIndex].options) {
                            fieldsConfig[fieldIndex].options = [];
                        }
                        if (!fieldsConfig[fieldIndex].options[optionIndex]) {
                            fieldsConfig[fieldIndex].options[optionIndex] = {};
                        }
                        fieldsConfig[fieldIndex].options[optionIndex][thirdPart] = value;
                    } else if (firstPart === 'relation' && secondPart !== undefined) {
                        // fields_config[0][relation][table]
                        if (!fieldsConfig[fieldIndex].relation) {
                            fieldsConfig[fieldIndex].relation = {};
                        }
                        fieldsConfig[fieldIndex].relation[secondPart] = value;
                    } else if (firstPart === 'search' && secondPart !== undefined) {
                        // fields_config[0][search][type] 或 fields_config[0][search][enabled] 等
                        if (!fieldsConfig[fieldIndex].search) {
                            fieldsConfig[fieldIndex].search = {};
                        }
                        // 处理布尔值：对于 enabled，直接检查复选框的 checked 状态
                        if (secondPart === 'enabled') {
                            // 查找对应的复选框元素，直接检查其 checked 状态
                            const checkbox = form.querySelector(`input[name="fields_config[${fieldIndex}][search][enabled]"][type="checkbox"]`);
                            if (checkbox) {
                                fieldsConfig[fieldIndex].search[secondPart] = checkbox.checked;
                            } else {
                                // 如果找不到复选框，使用 FormData 的值（向后兼容）
                                fieldsConfig[fieldIndex].search[secondPart] = value === '1' || value === 'true';
                            }
                        } else {
                            fieldsConfig[fieldIndex].search[secondPart] = value;
                        }
                    } else if (firstPart === 'type_attrs' && secondPart !== undefined && thirdPart !== undefined) {
                        // fields_config[0][type_attrs][textarea][rows] 或 fields_config[0][type_attrs][number][min_value] 等
                        if (!fieldsConfig[fieldIndex].type_attrs) {
                            fieldsConfig[fieldIndex].type_attrs = {};
                        }
                        if (!fieldsConfig[fieldIndex].type_attrs[secondPart]) {
                            fieldsConfig[fieldIndex].type_attrs[secondPart] = {};
                        }
                        // 处理数字值（如果是数字类型）
                        const numValue = parseFloat(value);
                        if (!isNaN(numValue) && value !== '') {
                            fieldsConfig[fieldIndex].type_attrs[secondPart][thirdPart] = numValue;
                        } else if (value !== '') {
                            fieldsConfig[fieldIndex].type_attrs[secondPart][thirdPart] = value;
                        }
                    } else if (firstPart) {
                        // fields_config[0][field_name] 或其他直接字段
                        // 对于布尔类型的复选框字段，直接检查复选框的 checked 状态
                        const booleanFields = ['sortable', 'editable', 'show_in_list', 'list_default', 'required'];
                        if (booleanFields.includes(firstPart)) {
                            const checkbox = form.querySelector(`input[name="fields_config[${fieldIndex}][${firstPart}]"][type="checkbox"]`);
                            if (checkbox) {
                                fieldsConfig[fieldIndex][firstPart] = checkbox.checked;
                            } else {
                                // 如果找不到复选框，使用 FormData 的值（向后兼容）
                                fieldsConfig[fieldIndex][firstPart] = value === '1' || value === 'true';
                            }
                        } else {
                            fieldsConfig[fieldIndex][firstPart] = value;
                        }
                    }
                }
            }
        } else if (key.startsWith('features[')) {
            const featureMatch = key.match(/^features\[(.+)\]$/);
            if (featureMatch && featureMatch[1]) {
                const featureKey = featureMatch[1];
                if (!data.features) {
                    data.features = {};
                }
                const checkbox = form.querySelector(`input[name="features[${featureKey}]"][type="checkbox"]`);
                if (checkbox) {
                    data.features[featureKey] = checkbox.checked;
                } else {
                    data.features[featureKey] = value === '1' || value === 'true';
                }
            }
        } else {
            // 非 fields_config 字段
            data[key] = value;
        }
    }
    
    // 辅助函数：清理字段配置
    function cleanFieldConfig(field) {
        // 处理 options，过滤空选项
        if (field.options && Array.isArray(field.options)) {
            field.options = field.options.filter(opt => opt && opt.key && opt.value);
            if (field.options.length === 0) {
                delete field.options;
            }
        }
        // 处理 search 配置，如果为空或只有默认值则删除
        if (field.search) {
            const search = field.search;
            // 如果只有默认值（type='like' 且 enabled=false 且没有 placeholder），可以删除
            if (search.type === 'like' && !search.enabled && !search.placeholder) {
                delete field.search;
            } else if (Object.keys(search).length === 0) {
                delete field.search;
            }
        }
        // 处理 type_attrs 配置，清理空的类型属性对象
        if (field.type_attrs) {
            // 清理每个类型下的空属性
            Object.keys(field.type_attrs).forEach(typeKey => {
                const typeAttrs = field.type_attrs[typeKey];
                if (!typeAttrs || Object.keys(typeAttrs).length === 0) {
                    delete field.type_attrs[typeKey];
                } else {
                    // 清理空字符串和 null 值
                    Object.keys(typeAttrs).forEach(attrKey => {
                        if (typeAttrs[attrKey] === '' || typeAttrs[attrKey] === null || typeAttrs[attrKey] === undefined) {
                            delete typeAttrs[attrKey];
                        }
                    });
                    // 如果清理后为空，删除整个类型对象
                    if (Object.keys(typeAttrs).length === 0) {
                        delete field.type_attrs[typeKey];
                    }
                }
            });
            // 如果所有类型属性都被清理，删除整个 type_attrs 对象
            if (Object.keys(field.type_attrs).length === 0) {
                delete field.type_attrs;
            }
        }
        return field;
    }
    
    // 将 fieldsConfig 转换为数组，按照 fieldsData 的顺序排序
    // 首先创建一个映射，将字段名映射到配置
    const fieldConfigMap = {};
    Object.keys(fieldsConfig).forEach(index => {
        const field = fieldsConfig[index];
        if (field.name) {
            fieldConfigMap[field.name] = cleanFieldConfig(field);
        }
    });
    
    // 按照 fieldsData 的顺序构建最终的字段配置数组
    // 先过滤掉无效的元素（undefined、null 或没有 name 属性的对象）
    data.fields_config = fieldsData
        .filter(column => column && column.name) // 过滤无效元素
        .map((column, index) => {
            const fieldName = column.name;
            let fieldConfig;
            
            if (fieldConfigMap[fieldName]) {
                fieldConfig = fieldConfigMap[fieldName];
            } else {
                // 如果找不到配置，返回一个基础配置
                fieldConfig = cleanFieldConfig({
                    name: fieldName,
                    type: column.type || '',
                    data_type: column.data_type || '',
                    comment: column.comment || '',
                    nullable: column.nullable ? '1' : '0',
                    is_primary: column.is_primary ? '1' : '0',
                    is_auto_increment: column.is_auto_increment ? '1' : '0',
                });
            }
            
            // 不添加 sort 字段，直接按照 fieldsData 数组的顺序保存
            // 数组顺序即为用户拖拽后的顺序
            
            return fieldConfig;
        });
    
    // 确保字段配置按照 fieldsData 的顺序排列（数组顺序即为拖拽后的顺序）
    console.log('[保存配置] 字段顺序（按数组顺序）:', data.fields_config.map((f, i) => ({
        index: i,
        name: f.name
    })));
    
    // ========== 提交配置到服务器 ==========
    console.log('========== CRUD 生成器配置提交（V2） ==========');
    console.log('提交时间:', new Date().toLocaleString('zh-CN'));
    console.log('提交数据:', data);
    console.log('提交数据（格式化）:', JSON.stringify(data, null, 2));
    console.log('字段配置数量:', data.fields_config ? data.fields_config.length : 0);
    
    // 输出字段比对信息
    if (data.fields_config && data.fields_config.length > 0) {
        const fieldNames = data.fields_config.map(f => f.name).filter(Boolean);
        console.log('保存的字段列表:', fieldNames);
        console.log('注意：只保存当前数据库表中存在的字段，已删除的字段将自动从配置中移除');
    }
    
    // 输出字段配置详情
    if (data.fields_config && data.fields_config.length > 0) {
        console.group('字段配置详情:');
        data.fields_config.forEach((field, index) => {
            console.group(`字段 ${index + 1}:`);
            console.log('字段名:', field.field_name || '未设置');
            console.log('显示名称:', field.display_name || '未设置');
            console.log('字段类型:', field.field_type || '未设置');
            console.log('是否列表显示:', field.is_list ? '是' : '否');
            console.log('是否搜索:', field.is_search ? '是' : '否');
            console.log('是否必填:', field.is_required ? '是' : '否');
            if (field.options && field.options.length > 0) {
                console.log('选项配置:', field.options);
            }
            if (field.relation) {
                console.log('关联配置:', field.relation);
            }
            if (field.search) {
                console.log('搜索配置:', field.search);
            }
            if (field.type_attrs) {
                console.log('类型属性:', field.type_attrs);
            }
            console.groupEnd();
        });
        console.groupEnd();
    }
    
    // 处理 checkbox 字段（未勾选时不提交，需要显式处理）
    // sync_to_menu: 默认开启（1），未勾选时为 0
    if (!data.hasOwnProperty('sync_to_menu')) {
        data.sync_to_menu = 0;
    } else {
        data.sync_to_menu = data.sync_to_menu === '1' || data.sync_to_menu === 1 ? 1 : 0;
    }
    
    // status: 默认开启（1），未勾选时为 0
    if (!data.hasOwnProperty('status')) {
        data.status = 0; // 如果未勾选，提交 0
    } else {
        data.status = data.status === '1' || data.status === 1 ? 1 : 0;
    }
    
    // 输出其他配置
    console.group('其他配置:');
    Object.keys(data).forEach(key => {
        if (key !== 'fields_config') {
            console.log(`${key}:`, data[key]);
        }
    });
    console.groupEnd();
    
    // 发送保存请求
    const csrfTokenEl = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenEl ? csrfTokenEl.content : '';
    
    // 显示加载提示
    const submitBtn = document.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> 保存中...';
    }
    
    fetch(window.adminRoute('system/crud-generator/save-config'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        console.log('保存响应:', result);
        
        // 恢复按钮状态
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
        
        // 后端 success() 方法返回 code = 200，error() 返回 code = 400 或其他错误码
        if (result.code === 200 || result.code === 0) {
            console.log('保存成功！配置ID:', result.data?.config_id);
            
            // 显示成功提示
            if (typeof showToast === 'function') {
                showToast('success', result.message || '配置保存成功');
            } else {
                alert('保存成功！');
            }
            refreshMainFrame({
                message: result.message || MAIN_REFRESH_MESSAGE,
                toastType: 'success',
            });

            const successPayload = {
                action: 'crud-config-saved',
                table: data.table_name || PAGE_VARS.tableName || '',
                configId: result.data?.config_id || data.config_id || null
            };

            if (IS_EMBEDDED_PAGE && window.AdminIframeClient) {
                window.AdminIframeClient.success(successPayload);
                setTimeout(() => {
                    window.AdminIframeClient.close(successPayload);
                }, 300);
            } else {
                // 延迟跳转到列表页，让用户看到成功提示
                setTimeout(() => {
                    window.location.href = window.adminRoute('system/crud-generator');
                }, 1000);
            }
        } else {
            console.error('保存失败:', result);
            const errorMsg = result.msg || result.message || '未知错误';
            
            // 显示错误提示
            if (typeof showToast === 'function') {
                showToast('danger', '保存失败：' + errorMsg);
            } else {
                alert('保存失败：' + errorMsg);
            }
        }
    })
    .catch(error => {
        console.error('保存失败:', error);
        
        // 恢复按钮状态
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
        
        // 显示错误提示
        if (typeof showToast === 'function') {
            showToast('danger', '保存失败，请重试：' + error.message);
        } else {
            alert('保存失败，请重试：' + error.message);
        }
    });
}

// ==================== 拖拽排序功能 ====================

/**
 * 初始化字段拖拽排序功能
 */
/**
 * 字段拖拽排序功能
 * 
 * 已禁用：拖动功能BUG太多，暂时禁用，待后续优化
 * 
 * 已知问题：
 * 1. 拖拽后字段顺序保存和加载不一致
 * 2. 拖拽时字段数据可能丢失
 * 3. 拖拽后索引更新不及时
 * 4. 与字段配置合并逻辑冲突
 * 
 * 待优化方向：
 * 1. 重新设计字段顺序保存机制
 * 2. 优化拖拽事件处理逻辑
 * 3. 确保拖拽后数据完整性
 * 4. 改进字段配置合并算法
 */
function initFieldSortable() {
    // 已禁用：拖动功能BUG太多，暂时禁用，待后续优化
    console.warn('[字段排序] 拖动功能已禁用，待后续优化');
    return;
    
    /* 原始代码已注释，待后续优化
    const tbody = document.getElementById('fieldsTableBody');
    if (!tbody) {
        console.warn('[字段排序] 未找到表格 tbody 元素');
        return;
    }

    // 检查是否已加载 Sortable.js（通过插件加载）
    if (typeof Sortable === 'undefined') {
        console.error('[字段排序] Sortable.js 未加载，请确保已引入 components.plugin.sortable-js');
        return;
    }

    // 如果已存在实例，先销毁
    if (window.fieldsSortable) {
        window.fieldsSortable.destroy();
        window.fieldsSortable = null;
    }

    // 创建 Sortable 实例
    const sortable = new Sortable(tbody, {
        handle: '.drag-handle', // 指定拖拽手柄
        animation: 150, // 动画时长
        ghostClass: 'sortable-ghost', // 拖拽时的占位符样式
        dragClass: 'sortable-drag', // 拖拽元素的样式
        chosenClass: 'sortable-chosen', // 选中元素的样式
        forceFallback: false, // 使用 HTML5 拖拽 API
        fallbackOnBody: true, // 如果拖拽到 body 外，回退到 body
        swapThreshold: 0.65, // 交换阈值
        group: 'fields', // 分组名称
        onEnd: function(evt) {
            // 拖拽结束后的回调
            console.log('[字段排序] onEnd 事件触发', evt);
            const oldIndex = evt.oldIndex;
            const newIndex = evt.newIndex;
            
            console.log('[字段排序] Sortable 索引变化:', { oldIndex, newIndex, changed: oldIndex !== newIndex });
            
            if (oldIndex !== newIndex) {
                // 方法：根据 DOM 中字段行的实际顺序重新排列 fieldsData
                // 这样更可靠，不依赖于 Sortable.js 的索引
                const tbody = document.getElementById('fieldsTableBody');
                if (!tbody) {
                    console.error('[字段排序] 未找到 tbody 元素');
                    return;
                }
                
                // 获取所有字段行（按 DOM 顺序）
                const fieldRows = tbody.querySelectorAll('tr.field-row');
                console.log('[字段排序] 找到字段行数量:', fieldRows.length, 'fieldsData 长度:', fieldsData.length);
                
                if (fieldRows.length !== fieldsData.length) {
                    console.warn('[字段排序] 字段行数量与数据数组长度不匹配，可能存在问题');
                }
                
                // 根据 DOM 顺序创建新的 fieldsData 数组
                const newFieldsData = [];
                const fieldNameMap = new Map();
                
                // 先建立字段名到字段数据的映射
                fieldsData.forEach(field => {
                    if (field && field.name) {
                        fieldNameMap.set(field.name, field);
                    }
                });
                
                // 按照 DOM 中的顺序重新排列
                fieldRows.forEach((row, domIndex) => {
                    const fieldName = row.getAttribute('data-field-name');
                    if (fieldName && fieldNameMap.has(fieldName)) {
                        const field = fieldNameMap.get(fieldName);
                        // 不设置 sort 字段，直接按照数组顺序保存
                        newFieldsData.push(field);
                        console.log(`[字段排序] 字段 "${fieldName}" 移动到位置 ${domIndex}`);
                    } else {
                        console.warn(`[字段排序] 未找到字段 "${fieldName}" 的数据`);
                    }
                });
                
                // 检查是否有字段丢失
                if (newFieldsData.length !== fieldsData.length) {
                    console.error('[字段排序] 字段数量不匹配，可能存在数据丢失', {
                        oldLength: fieldsData.length,
                        newLength: newFieldsData.length
                    });
                    // 添加丢失的字段
                    fieldsData.forEach(field => {
                        if (field && field.name && !newFieldsData.find(f => f.name === field.name)) {
                            console.warn(`[字段排序] 添加丢失的字段: ${field.name}`);
                            newFieldsData.push(field);
                        }
                    });
                }
                
                // 更新 fieldsData 数组
                fieldsData.length = 0;
                fieldsData.push(...newFieldsData);
                
                console.log('[字段排序] 已更新字段顺序:', fieldsData.map((f, i) => ({
                    index: i,
                    name: f.name
                })));
                
                // 重新渲染表格以更新索引
                reindexFieldRows();
                
                // 显示提示信息
                console.log('[字段排序] 准备显示提示信息');
                showSortHint('字段顺序已更新，保存后生效');
            } else {
                console.log('[字段排序] 索引未变化，不显示提示');
            }
        }
    });

    // 保存到全局变量，以便后续使用
    window.fieldsSortable = sortable;
    
    console.log('[字段排序] 拖拽排序功能已初始化');
    */
}

/**
 * 重新索引字段行（拖拽后调用）
 */
function reindexFieldRows() {
    const tbody = document.getElementById('fieldsTableBody');
    if (!tbody) return;

    const rows = tbody.querySelectorAll('tr.field-row');
    rows.forEach((row, newIndex) => {
        const oldIndex = parseInt(row.getAttribute('data-index'));
        
        // 更新 data-index 属性
        row.setAttribute('data-index', newIndex);
        
        // 更新所有表单字段的 name 属性中的索引
        const inputs = row.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.name && input.name.includes('fields_config[')) {
                // 替换索引
                input.name = input.name.replace(
                    /fields_config\[\d+\]/,
                    `fields_config[${newIndex}]`
                );
            }
        });
        
        // 更新详细配置面板的 ID
        const collapseId = `fieldDetails-${oldIndex}`;
        const newCollapseId = `fieldDetails-${newIndex}`;
        const collapseEl = document.getElementById(collapseId);
        if (collapseEl) {
            collapseEl.id = newCollapseId;
            // 更新按钮的 data-bs-target
            const toggleBtn = row.querySelector(`button[data-bs-target="#${collapseId}"]`);
            if (toggleBtn) {
                toggleBtn.setAttribute('data-bs-target', `#${newCollapseId}`);
                toggleBtn.setAttribute('aria-controls', newCollapseId);
            }
        }
        
        // 更新所有 ID 中包含索引的元素
        const idElements = row.querySelectorAll('[id*="' + oldIndex + '"]');
        idElements.forEach(el => {
            if (el.id) {
                el.id = el.id.replace(new RegExp('_' + oldIndex + '|' + oldIndex + '_'), '_' + newIndex);
            }
        });
        
        // 更新选项列表的 data-index
        const optionsList = row.querySelector(`.options-list[data-index="${oldIndex}"]`);
        if (optionsList) {
            optionsList.setAttribute('data-index', newIndex);
        }
        
        // 更新徽章预览区域的 ID
        const badgePreview = document.getElementById(`badge-preview-${oldIndex}`);
        if (badgePreview) {
            badgePreview.id = `badge-preview-${newIndex}`;
        }
    });
    
    console.log('[字段排序] 字段行索引已更新');
}

/**
 * 显示排序提示
 */
function showSortHint(message) {
    console.log('[showSortHint] 函数被调用，消息:', message);
    
    // 先移除之前的提示（如果有）
    const existingHint = document.querySelector('.sort-hint-alert');
    if (existingHint) {
        existingHint.remove();
    }
    
    // 创建临时提示元素
    const hint = document.createElement('div');
    hint.className = 'sort-hint-alert alert alert-info alert-dismissible fade show position-fixed';
    hint.style.cssText = 'top: 20px; right: 20px; z-index: 99999; min-width: 250px; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
    
    // 使用更简单的 HTML，不依赖 Bootstrap Icons
    hint.innerHTML = `
        <strong><i class="bi bi-info-circle me-2"></i>提示</strong>
        <div class="mt-1">${message}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // 如果 Bootstrap 不可用，使用原生关闭按钮
    const closeBtn = hint.querySelector('.btn-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            hint.remove();
        });
    }
    
    document.body.appendChild(hint);
    console.log('[showSortHint] 提示元素已添加到页面:', hint);
    
    // 确保元素可见
    setTimeout(() => {
        if (hint.parentNode) {
            hint.style.display = 'block';
            hint.style.opacity = '1';
            console.log('[showSortHint] 提示元素应该已显示');
        }
    }, 10);
    
    // 3秒后自动移除
    setTimeout(() => {
        if (hint.parentNode) {
            hint.style.opacity = '0';
            hint.style.transition = 'opacity 0.3s';
            setTimeout(() => {
                if (hint.parentNode) {
                    hint.remove();
                    console.log('[showSortHint] 提示元素已自动移除');
                }
            }, 300);
        }
    }, 3000);
}

// ==================== 工具函数 ====================

/**
 * HTML 转义（防止 XSS）
 * @param {string} text - 需要转义的文本
 * @returns {string} 转义后的文本
 */
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * 渲染选项列表
 */
function renderOptionsList(index, column) {
    // 使用统一的选项解析函数
    const optionsArray = parseOptionsData(column.options);
    let html = '';
    
    optionsArray.forEach((option, optIndex) => {
        html += `
            <div class="input-group input-group-sm mb-2 option-item">
                <span class="input-group-text">
                    <i class="bi bi-key"></i>
                </span>
                <input type="text" 
                       class="form-control option-key-input" 
                       name="fields_config[${index}][options][${optIndex}][key]" 
                       value="${escapeHtml(option.key)}" 
                       placeholder="键（存储值）"
                       data-index="${index}"
                       data-option-index="${optIndex}"
                       onchange="updateBadgePreview(${index}, ${optIndex})"
                       oninput="updateBadgePreview(${index}, ${optIndex})">
                <span class="input-group-text">
                    <i class="bi bi-text-left"></i>
                </span>
                <input type="text" 
                       class="form-control option-value-input" 
                       name="fields_config[${index}][options][${optIndex}][value]" 
                       value="${escapeHtml(option.value)}" 
                       placeholder="值（显示文本）"
                       data-index="${index}"
                       data-option-index="${optIndex}"
                       onchange="updateBadgePreview(${index}, ${optIndex})"
                       oninput="updateBadgePreview(${index}, ${optIndex})">
                <span class="input-group-text option-color-wrapper">
                    <i class="bi bi-palette"></i>
                </span>
                <select class="form-select form-select-sm option-color-select" 
                        name="fields_config[${index}][options][${optIndex}][color]"
                        data-index="${index}"
                        data-option-index="${optIndex}"
                        title="选择徽章颜色"
                        onchange="updateBadgePreview(${index}, ${optIndex})">
                    ${BADGE_COLORS.map(color => 
                        `<option value="${color.value}" ${option.color === color.value ? 'selected' : ''}>${color.label}</option>`
                    ).join('')}
                </select>
                <button type="button" 
                        class="btn btn-sm btn-outline-danger remove-option" 
                        onclick="removeOption(this)"
                        title="删除选项">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
    });
    
    return html || '<div class="text-muted text-center py-2">暂无选项，点击"添加选项"按钮添加</div>';
}

/**
 * 渲染徽章预览
 */
function renderBadgePreview(index, column) {
    // 使用统一的选项解析函数
    const optionsArray = parseOptionsData(column.options);
    let html = '';
    
    optionsArray.forEach((option, optIndex) => {
        const color = getBadgeColor(option.value, option.color, column.badge_default_color);
        html += `
            <span class="badge bg-${color} badge-preview-item me-2 mb-2" 
                  data-key="${escapeHtml(option.key)}"
                  data-color="${escapeHtml(option.color || '')}">
                ${escapeHtml(option.value)}
            </span>
        `;
    });
    
    return html || '<span class="text-muted">暂无选项</span>';
}

/**
 * 渲染未定义值的默认徽章预览
 */
function renderBadgeDefaultPreview(column) {
    const defaultColor = column.badge_default_color || '';
    return buildDefaultBadgePreviewHtml(defaultColor);
}

/**
 * 根据颜色值获取展示标签
 * @param {string} colorValue
 * @returns {string}
 */
function getBadgeColorLabelByValue(colorValue) {
    const colorItem = BADGE_COLORS.find(item => item.value === colorValue);
    return colorItem ? colorItem.label : (colorValue || '自动（智能匹配）');
}

/**
 * 生成默认徽章预览的 HTML
 * @param {string} defaultColor
 * @returns {string}
 */
function buildDefaultBadgePreviewHtml(defaultColor) {
    const previewColor = getBadgeColor('未定义值', '', defaultColor);
    const hasCustomDefault = !!defaultColor;
    const helperText = hasCustomDefault
        ? `未定义值将统一使用 ${getBadgeColorLabelByValue(defaultColor)}`
        : '未设置默认颜色，将根据实际值智能匹配（此处展示智能匹配示例）';
    
    return `
        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-2">
            <span class="badge bg-${previewColor}">未定义值示例</span>
            <small class="text-muted">${helperText}</small>
        </div>
    `;
}

/**
 * 获取徽章颜色（智能匹配）
 * @param {string} value - 选项值
 * @param {string} configuredColor - 配置的颜色
 * @param {string} defaultColor - 默认颜色
 * @returns {string} Bootstrap 徽章颜色类名
 */
function getBadgeColor(value, configuredColor, defaultColor) {
    // 优先使用配置的颜色
    if (configuredColor) {
        return configuredColor;
    }
    
    // 其次使用默认颜色
    if (defaultColor) {
        return defaultColor;
    }
    
    // 智能匹配颜色
    const valueStr = String(value).toLowerCase();
    
    // 检查成功关键词
    if (SUCCESS_KEYWORDS.some(keyword => valueStr === keyword || valueStr.includes(keyword))) {
        return 'success';
    }
    
    // 检查危险关键词
    if (DANGER_KEYWORDS.some(keyword => valueStr === keyword || valueStr.includes(keyword))) {
        return 'danger';
    }
    
    // 检查警告关键词
    if (WARNING_KEYWORDS.some(keyword => valueStr === keyword || valueStr.includes(keyword))) {
        return 'warning';
    }
    
    // 检查信息关键词
    if (INFO_KEYWORDS.some(keyword => valueStr === keyword || valueStr.includes(keyword))) {
        return 'info';
    }
    
    // 默认返回主要颜色
    return 'primary';
}

/**
 * 添加选项
 * @param {number} index - 字段索引
 */
function addOption(index) {
    const optionsList = document.querySelector(`.options-list[data-index="${index}"]`);
    if (!optionsList) return;
    
    // 获取当前选项数量
    const existingOptions = optionsList.querySelectorAll('.option-item');
    const optIndex = existingOptions.length;
    
    // 创建新选项 HTML
    const optionHtml = `
        <div class="input-group input-group-sm mb-2 option-item">
            <span class="input-group-text">
                <i class="bi bi-key"></i>
            </span>
            <input type="text" 
                   class="form-control option-key-input" 
                   name="fields_config[${index}][options][${optIndex}][key]" 
                   value="" 
                   placeholder="键（存储值）"
                   data-index="${index}"
                   data-option-index="${optIndex}"
                   onchange="updateBadgePreview(${index}, ${optIndex})"
                   oninput="updateBadgePreview(${index}, ${optIndex})">
            <span class="input-group-text">
                <i class="bi bi-text-left"></i>
            </span>
            <input type="text" 
                   class="form-control option-value-input" 
                   name="fields_config[${index}][options][${optIndex}][value]" 
                   value="" 
                   placeholder="值（显示文本）"
                   data-index="${index}"
                   data-option-index="${optIndex}"
                   onchange="updateBadgePreview(${index}, ${optIndex})"
                   oninput="updateBadgePreview(${index}, ${optIndex})">
            <span class="input-group-text option-color-wrapper">
                <i class="bi bi-palette"></i>
            </span>
            <select class="form-select form-select-sm option-color-select" 
                    name="fields_config[${index}][options][${optIndex}][color]"
                    data-index="${index}"
                    data-option-index="${optIndex}"
                    title="选择徽章颜色"
                    onchange="updateBadgePreview(${index}, ${optIndex})">
                ${BADGE_COLORS.map(color => 
                    `<option value="${color.value}">${color.label}</option>`
                ).join('')}
            </select>
            <button type="button" 
                    class="btn btn-sm btn-outline-danger remove-option" 
                    onclick="removeOption(this)"
                    title="删除选项">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    
    // 移除"暂无选项"提示
    const emptyMsg = optionsList.querySelector('.text-muted');
    if (emptyMsg) {
        emptyMsg.remove();
    }
    
    // 添加新选项
    optionsList.insertAdjacentHTML('beforeend', optionHtml);
    
    // 更新徽章预览
    updateBadgePreview(index);
}

/**
 * 删除选项
 * @param {HTMLElement} button - 删除按钮元素
 */
function removeOption(button) {
    const optionItem = button.closest('.option-item');
    if (optionItem) {
        const index = optionItem.querySelector('.option-key-input').getAttribute('data-index');
        optionItem.remove();
        
        // 重新编号选项索引
        reindexOptions(index);
        
        // 更新徽章预览
        updateBadgePreview(index);
    }
}

/**
 * 重新编号选项索引（删除选项后调用）
 * @param {number} index - 字段索引
 */
function reindexOptions(index) {
    const optionsList = document.querySelector(`.options-list[data-index="${index}"]`);
    if (!optionsList) return;
    
    const optionItems = optionsList.querySelectorAll('.option-item');
    optionItems.forEach((item, newIndex) => {
        // 更新所有输入框的 name 属性
        const keyInput = item.querySelector('.option-key-input');
        const valueInput = item.querySelector('.option-value-input');
        const colorSelect = item.querySelector('.option-color-select');
        
        if (keyInput) {
            keyInput.name = `fields_config[${index}][options][${newIndex}][key]`;
            keyInput.setAttribute('data-option-index', newIndex);
        }
        if (valueInput) {
            valueInput.name = `fields_config[${index}][options][${newIndex}][value]`;
            valueInput.setAttribute('data-option-index', newIndex);
        }
        if (colorSelect) {
            colorSelect.name = `fields_config[${index}][options][${newIndex}][color]`;
            colorSelect.setAttribute('data-option-index', newIndex);
        }
        
        // 更新事件处理函数中的索引
        const onChangeAttr = `updateBadgePreview(${index}, ${newIndex})`;
        if (keyInput) {
            keyInput.setAttribute('onchange', onChangeAttr);
            keyInput.setAttribute('oninput', onChangeAttr);
        }
        if (valueInput) {
            valueInput.setAttribute('onchange', onChangeAttr);
            valueInput.setAttribute('oninput', onChangeAttr);
        }
        if (colorSelect) {
            colorSelect.setAttribute('onchange', onChangeAttr);
        }
    });
    
    // 如果没有选项了，显示提示
    if (optionItems.length === 0) {
        optionsList.innerHTML = '<div class="text-muted text-center py-2">暂无选项，点击"添加选项"按钮添加</div>';
    }
}

/**
 * 更新徽章预览
 * @param {number} index - 字段索引
 * @param {number|null} optIndex - 选项索引（可选，用于单个选项更新）
 */
function updateBadgePreview(index, optIndex = null) {
    const previewArea = document.getElementById(`badge-preview-${index}`);
    if (!previewArea) return;
    
    const previewList = previewArea.querySelector('.badge-preview-list');
    if (!previewList) return;
    
    // 获取默认颜色
    const defaultColorSelect = document.querySelector(`select[name="fields_config[${index}][badge_default_color]"]`);
    const defaultColor = defaultColorSelect ? defaultColorSelect.value : '';
    
    // 收集所有选项
    const optionsList = document.querySelector(`.options-list[data-index="${index}"]`);
    if (!optionsList) return;
    
    const optionItems = optionsList.querySelectorAll('.option-item');
    const optionsArray = [];
    
    optionItems.forEach((item, idx) => {
        const keyInput = item.querySelector('.option-key-input');
        const valueInput = item.querySelector('.option-value-input');
        const colorSelect = item.querySelector('.option-color-select');
        
        if (keyInput && valueInput) {
            const key = keyInput.value.trim();
            const value = valueInput.value.trim();
            const color = colorSelect ? colorSelect.value : '';
            
            if (key && value) {
                optionsArray.push({
                    key: key,
                    value: value,
                    color: color
                });
            }
        }
    });
    
    // 渲染预览
    let html = '';
    optionsArray.forEach(option => {
        const color = getBadgeColor(option.value, option.color, defaultColor);
        html += `
            <span class="badge bg-${color} badge-preview-item me-2 mb-2" 
                  data-key="${escapeHtml(option.key)}"
                  data-color="${escapeHtml(option.color || '')}">
                ${escapeHtml(option.value)}
            </span>
        `;
    });
    
    previewList.innerHTML = html || '<span class="text-muted">暂无选项</span>';
    
    const defaultPreviewContainer = previewArea.querySelector('.badge-default-preview');
    if (defaultPreviewContainer) {
        defaultPreviewContainer.innerHTML = buildDefaultBadgePreviewHtml(defaultColor);
    }
}
</script>

<!-- 引入图标选择器组件 -->
@include('components.icon-picker')
@endsection

