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

@include('components.form-col-visualizer-js')

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
                        @php
                            $isRemote = $connectionTypes[$dbConnection]['is_remote'] ?? false;
                        @endphp
                        <span class="ms-2">
                            <span class="badge bg-info" id="connectionBadgeName">
                                <i class="bi bi-database"></i> {{ $dbConnection }}
                            </span>
                            @if($isRemote)
                                <span class="badge bg-warning ms-1">远程</span>
                            @endif
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
                        <label class="form-label">配置状态</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="status" value="1"
                                   id="status">
                            <label class="form-check-label" for="status">
                                启用此配置（默认开启）
                            </label>
                        </div>
                        <small class="text-muted">
                            控制整个 CRUD 配置是否生效。关闭后，即使功能开关都开启，该配置也不会生效。
                        </small>
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
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="features[soft_delete]" value="0">
                                        <input class="form-check-input" type="checkbox" name="features[soft_delete]" value="1"
                                               id="featureSoftDeleteToggle">
                                        <label class="form-check-label" for="featureSoftDeleteToggle">
                                            启用回收站
                                        </label>
                                    </div>
                                    <small class="text-muted">使用回收站功能（检测到 deleted_at 字段时自动开启）。</small>
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
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="openColVisualizerBtn">
                        <i class="bi bi-layout-three-columns"></i> 表单列宽可视化
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="reloadFieldsBtn" style="display: none;">
                        <i class="bi bi-arrow-clockwise"></i> 重新加载
                    </button>
                </div>
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

<!-- 列宽可视化编辑模态框 -->
<div class="modal fade" id="colVisualizerModal" tabindex="-1" aria-labelledby="colVisualizerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="colVisualizerModalLabel">
                    <i class="bi bi-layout-three-columns me-1"></i> 表单列宽可视化编辑
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    仅展示启用「编辑」的字段，可在一处统一调整表单列宽；保存配置后会应用到生成的创建/编辑表单。
                </p>
                <div id="colVisualizerContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="colVisualizerResetBtn">
                    <i class="bi bi-arrow-counterclockwise"></i> 重置全部
                </button>
                <button type="button" class="btn btn-primary" id="colVisualizerSaveBtn">
                    <i class="bi bi-check-circle"></i> 保存列宽设置
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<!-- 固定在底部的操作栏 -->
@include('admin.components.fixed-bottom-actions', [
    'formId' => 'configForm',
    'cancelUrl' => admin_route('system/crud-generator') . '?connection=' . ($dbConnection ?? 'default'),
    'submitText' => '保存配置',
    'infoText' => '配置完成后点击保存按钮提交'
])

<script>
window.__CRUD_GENERATOR_PAGE_VARS__ = {
    tableName: @json($tableName),
    dbConnection: @json($dbConnection),
    baseConfig: @json($config ?? []),
    tableComment: @json($tableComment),
    connectionInfo: @json($currentConnInfo),
    connectionTypes: @json($connectionTypes ?? []),
};
</script>

@include('components.crud-config-js')

<!-- 引入图标选择器组件 -->
@include('components.icon-picker')
@endsection

