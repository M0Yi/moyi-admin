{{-- 后台管理系统统一样式 --}}
<style>
/* ==================== 通用样式 ==================== */
{{-- 
注意：颜色变量现在从站点配置中获取，定义在 admin/layouts/admin.blade.php 中
如果此文件被单独使用，需要确保颜色变量已定义，或使用默认值
--}}
:root {
    {{-- 这些颜色变量现在从站点配置中获取，定义在 admin/layouts/admin.blade.php --}}
    {{-- 如果此文件被单独使用，请确保在引入此文件前已定义这些变量 --}}
    --border-radius: 8px;
    --border-radius-lg: 12px;
    --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ==================== 按钮样式 ==================== */
.btn {
    border-radius: var(--border-radius);
    padding: 0.625rem 1.25rem;
    font-weight: 500;
    font-size: 0.875rem;
    transition: var(--transition);
    border: none;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--box-shadow);
}

.btn:active {
    transform: translateY(0);
}

/* 主要按钮 */
.btn-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--primary-hover) 0%, var(--primary-color) 100%);
    color: white;
}

/* 成功按钮 */
.btn-success {
    background-color: var(--success-color);
    color: white;
}

.btn-success:hover {
    background-color: #059669;
    color: white;
}

/* 警告按钮 */
.btn-warning {
    background-color: var(--warning-color);
    color: white;
}

.btn-warning:hover {
    background-color: #d97706;
    color: white;
}

/* 危险按钮 */
.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background-color: #dc2626;
    color: white;
}

/* 信息按钮 */
.btn-info {
    background-color: var(--info-color);
    color: white;
}

.btn-info:hover {
    background-color: #2563eb;
    color: white;
}

/* 次要按钮 */
.btn-secondary {
    background-color: var(--secondary-color);
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
    color: white;
}

/* 浅色按钮 */
.btn-light {
    background-color: var(--light-color);
    color: var(--dark-color);
    border: 1px solid var(--border-color);
}

.btn-light:hover {
    background-color: #e9ecef;
    border-color: #dee2e6;
    color: var(--dark-color);
}

/* 轮廓按钮 */
.btn-outline-primary {
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.btn-outline-primary:hover {
    background-color: var(--primary-color);
    color: white;
}

.btn-outline-secondary {
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.btn-outline-secondary:hover {
    background-color: var(--light-color);
    border-color: #adb5bd;
    color: #495057;
}

/* 小按钮 */
.btn-sm, .btn-action {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
}

/* 按钮组 */
.btn-group .btn {
    margin: 0;
}

/* ==================== 卡片样式 ==================== */
.card {
    border: none;
    border-radius: var(--border-radius-lg);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
    /* 确保下拉菜单不被裁剪 */
    overflow: visible;
}

.card-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    color: white;
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    padding: 1rem 1.5rem;
    font-weight: 600;
}

.card-body {
    padding: 1.5rem;
    /* 确保下拉菜单不被裁剪 */
    overflow: visible;
}


/* ==================== 表格样式 ==================== */
.table-responsive {
    border-radius: var(--border-radius);
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table {
    margin-bottom: 0;
}

.table thead th {
    background-color: var(--light-color);
    color: var(--dark-color);
    font-weight: 600;
    font-size: 0.875rem;
    border-bottom: 2px solid var(--border-color);
    padding: 1rem 0.75rem;
    vertical-align: middle;
}

.table tbody td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    color: #4b5563;
}

.table-hover tbody tr {
    transition: background-color 0.2s;
}

.table-hover tbody tr:hover {
    background-color: #f9fafb;
}

/* 固定操作列 */
.sticky-column {
    position: sticky !important;
    right: 0;
    background-color: #ffffff;
    z-index: 10;
    box-shadow: -2px 0 8px rgba(0, 0, 0, 0.05);
}

thead .sticky-column {
    background-color: var(--light-color);
    z-index: 11;
}

.table-hover tbody tr:hover .sticky-column {
    background-color: #f9fafb;
}

/* ==================== 表单样式 ==================== */
.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: var(--dark-color);
}

.form-control, .form-select {
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    padding: 0.625rem 0.875rem;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.form-text {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.form-check-input {
    cursor: pointer;
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

/* ==================== 徽章样式 ==================== */
.badge {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 0.375rem;
}

.badge-menu {
    background: #e3f2fd;
    color: #1976d2;
}

.badge-button {
    background: #fed7aa;
    color: #92400e;
}

.badge-link {
    background: #e8f5e9;
    color: #388e3c;
}

.badge-api {
    background: #f3e5f5;
    color: #7b1fa2;
}

/* ==================== 模态框样式 ==================== */
.modal-backdrop {
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    border-radius: var(--border-radius-lg);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    border: none;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    color: white;
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    border-bottom: none;
    padding: 1.25rem 1.5rem;
}

.modal-title {
    font-weight: 600;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: none;
    padding: 1rem 1.5rem 1.5rem;
}

/* ==================== 固定底部操作栏样式 ==================== */
/* 注意：fixed-bottom-actions 的样式已统一放到 `public/css/admin_style.css` 中，
   这里保留占位注释以兼容单文件引用场景（但优先使用 public/css）。 */

/* ==================== 面包屑样式 ==================== */
.breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 0.5rem;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: "›";
    color: #9ca3af;
}

.breadcrumb-item a {
    color: var(--primary-color);
    text-decoration: none;
}

.breadcrumb-item a:hover {
    color: var(--primary-hover);
    text-decoration: underline;
}

.breadcrumb-item.active {
    color: #6b7280;
}

/* ==================== 分页样式 ==================== */
.pagination {
    margin-bottom: 0;
}

.page-link {
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    margin: 0 0.25rem;
    transition: var(--transition);
}

.page-link:hover {
    background-color: var(--light-color);
    color: var(--primary-hover);
    transform: translateY(-1px);
}

.page-item.active .page-link {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    border-color: var(--primary-color);
}

.page-item.disabled .page-link {
    color: #9ca3af;
    background-color: var(--light-color);
    border-color: var(--border-color);
}

/* ==================== Toast 通知样式 ==================== */
.toast-container {
    z-index: 9999;
}

.toast {
    border-radius: var(--border-radius);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* ==================== 其他通用样式 ==================== */
.text-primary {
    color: var(--primary-color) !important;
}

.bg-primary {
    background-color: var(--primary-color) !important;
    color: white !important;
}

.bg-success {
    background-color: var(--success-color) !important;
    color: white !important;
}

.bg-info {
    background-color: var(--info-color) !important;
    color: white !important;
}

.bg-warning {
    background-color: var(--warning-color) !important;
    color: white !important;
}

.bg-danger {
    background-color: var(--danger-color) !important;
    color: white !important;
}

.bg-secondary {
    background-color: var(--secondary-color) !important;
    color: white !important;
}

.shadow-sm {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

.rounded-pill {
    border-radius: 50rem !important;
}

/* 头像样式 */
.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border-color);
}

/* 菜单/权限层级缩进 */
.menu-level-0, .permission-level-0 { padding-left: 1rem; }
.menu-level-1, .permission-level-1 { padding-left: 2.5rem; }
.menu-level-2, .permission-level-2 { padding-left: 4rem; }
.menu-level-3, .permission-level-3 { padding-left: 5.5rem; }

/* 图标容器 */
.menu-icon, .permission-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: var(--light-color);
    border-radius: 6px;
    font-size: 1.1rem;
}
</style>

