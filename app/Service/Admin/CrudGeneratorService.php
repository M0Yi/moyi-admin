<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Model\Admin\AdminCrudConfig;
use Hyperf\DbConnection\Db;

/**
 * CRUD 生成器服务
 * 用于生成 Model、Controller、Views、Routes 等代码
 */
class CrudGeneratorService
{
    public function __construct(
        protected DatabaseService $databaseService
    ) {
    }

    /**
     * 根据配置生成代码
     *
     * @param AdminCrudConfig $config
     * @return array 返回生成的文件列表
     */
    public function generate(AdminCrudConfig $config): array
    {
        $files = [];

        // 生成 Model
        $files['model'] = $this->generateModel($config);

        // 生成 Controller
        $files['controller'] = $this->generateController($config);

        // 生成 Request 验证器
        $files['request_store'] = $this->generateStoreRequest($config);
        $files['request_update'] = $this->generateUpdateRequest($config);

        // 生成 Views
        $files['view_index'] = $this->generateIndexView($config);
        $files['view_create'] = $this->generateCreateView($config);
        $files['view_edit'] = $this->generateEditView($config);

        // 生成 Route
        $files['route'] = $this->generateRoute($config);

        // 生成 Menu SQL
        $files['menu_sql'] = $this->generateMenuSql($config);

        return $files;
    }

    /**
     * 生成 Model 代码
     */
    protected function generateModel(AdminCrudConfig $config): string
    {
        $tableName = $config->table_name;
        $modelName = $config->model_name;
        $dbConnection = $config->db_connection ?? 'default';
        $columns = $this->databaseService->getTableColumns($tableName, $dbConnection);

        // 构建 fillable 字段
        $fillable = [];
        $casts = [];
        $properties = [];

        // 获取用户配置的字段信息（包含 cast_type）
        $fieldsConfig = $config->fields_config ?? [];
        $fieldsConfigByName = [];
        foreach ($fieldsConfig as $fieldConfig) {
            $fieldsConfigByName[$fieldConfig['name']] = $fieldConfig;
        }

        foreach ($columns as $column) {
            if (!$column['is_primary'] && !in_array($column['name'], ['created_at', 'updated_at', 'deleted_at'])) {
                $fillable[] = "'" . $column['name'] . "'";
            }

            // 类型转换 - 优先使用用户配置的 cast_type
            $fieldConfig = $fieldsConfigByName[$column['name']] ?? null;
            $cast = $this->getModelCast($column, $fieldConfig);
            if ($cast) {
                $casts[] = "'{$column['name']}' => '{$cast}'";
            }

            // PHPDoc 属性
            $properties[] = $this->getPropertyDoc($column, $fieldConfig);
        }

        $fillableStr = implode(",\n        ", $fillable);
        $castsStr = implode(",\n        ", $casts);
        $propertiesStr = implode("\n * ", $properties);

        // 检查是否有软删除字段
        // 检查是否有 deleted_at 字段（软删除）
        $hasSoftDeletes = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'deleted_at') {
                $hasSoftDeletes = true;
                break;
            }
        }
        $useSoftDeletes = $hasSoftDeletes ? "use Hyperf\Database\Model\SoftDeletes;\n" : '';
        $traitSoftDeletes = $hasSoftDeletes ? "    use SoftDeletes;\n\n" : '';
        
        // 如果数据库连接不是 'default'，添加 connection 属性
        $connectionProperty = $dbConnection !== 'default' 
            ? "    /**\n     * 数据库连接名称\n     */\n    protected ?string \$connection = '{$dbConnection}';\n\n" 
            : '';

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Model\Admin;

use App\Model\Model;
{$useSoftDeletes}
/**
 * {$config->module_name}模型
 *
 * {$propertiesStr}
 */
class {$modelName} extends Model
{
{$traitSoftDeletes}{$connectionProperty}    /**
     * 表名
     */
    protected ?string \$table = '{$tableName}';

    /**
     * 主键类型
     */
    protected string \$keyType = 'int';

    /**
     * 是否自增
     */
    public bool \$incrementing = true;

    /**
     * 是否自动维护时间戳
     */
    public bool \$timestamps = true;

    /**
     * 可批量赋值的属性
     */
    protected array \$fillable = [
        {$fillableStr},
    ];

    /**
     * 类型转换
     */
    protected array \$casts = [
        {$castsStr},
    ];

    /**
     * 查询作用域：启用状态
     */
    public function scopeActive(\$query)
    {
        return \$query->where('status', 1);
    }
}

PHP;
    }

    /**
     * 生成 Controller 代码
     */
    protected function generateController(AdminCrudConfig $config): string
    {
        $modelName = $config->model_name;
        $controllerName = $config->controller_name;
        $moduleName = $config->module_name;
        $routeSlug = $config->route_slug;
        $routePrefix = $config->route_prefix ?: $routeSlug;
        $tableName = $config->table_name;
        $dbConnection = $config->db_connection ?? 'default';

        $columns = $this->databaseService->getTableColumns($tableName, $dbConnection);
        $listFields = array_filter($columns, fn($col) => $col['show_in_list']);
        $searchFields = array_filter($columns, fn($col) => $col['searchable']);

        // 获取 page_size（优先从独立字段读取，如果没有则从 options 中读取，最后使用默认值 15）
        $pageSize = $config->page_size ?? ($config->options['page_size'] ?? 15);

        // 构建搜索条件
        $searchConditions = '';
        foreach ($searchFields as $field) {
            $fieldName = $field['name'];
            $formType = $field['form_type'] ?? 'text';
            
            // 区间数字类型：生成范围查询条件
            if ($formType === 'number_range') {
                $searchConditions .= <<<PHP

            // 区间数字搜索
            if (!empty(\$params['{$fieldName}_min'])) {
                \$query->where('{$fieldName}', '>=', \$params['{$fieldName}_min']);
            }
            if (!empty(\$params['{$fieldName}_max'])) {
                \$query->where('{$fieldName}', '<=', \$params['{$fieldName}_max']);
            }
PHP;
            } else {
                // 其他类型：模糊查询
            $searchConditions .= <<<PHP

            if (!empty(\$params['{$fieldName}'])) {
                \$query->where('{$fieldName}', 'like', '%' . \$params['{$fieldName}'] . '%');
            }
PHP;
            }
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\Admin\AbstractAdminController;
use App\Model\Admin\\{$modelName};
use App\Request\Admin\\{$modelName}StoreRequest;
use App\Request\Admin\\{$modelName}UpdateRequest;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Psr\Http\Message\ResponseInterface;

        #[Controller(prefix: '/admin/{{adminPath}}/{$routePrefix}')]
class {$controllerName} extends AbstractAdminController
{
    /**
     * 列表页面
     */
    #[GetMapping('')]
    public function index(): ResponseInterface
    {
        \$params = \$this->request->all();
        \$page = (int) (\$params['page'] ?? 1);
        \$pageSize = (int) (\$params['page_size'] ?? {$pageSize});

        \$query = {$modelName}::query()->where('site_id', site_id());
{$searchConditions}

        \$total = \$query->count();
        \$items = \$query->orderBy('id', 'desc')
            ->offset((\$page - 1) * \$pageSize)
            ->limit(\$pageSize)
            ->get();

        return \$this->render('admin.system.{$routeSlug}.index', [
            'items' => \$items,
            'total' => \$total,
            'page' => \$page,
            'pageSize' => \$pageSize,
            'params' => \$params,
        ]);
    }

    /**
     * 创建页面
     */
    #[GetMapping('create')]
    public function create(): ResponseInterface
    {
        return \$this->render('admin.system.{$routeSlug}.create');
    }

    /**
     * 保存数据
     */
    #[PostMapping('')]
    public function store({$modelName}StoreRequest \$request): ResponseInterface
    {
        \$data = \$request->validated();
        \$data['site_id'] = site_id();

        {$modelName}::create(\$data);

        return \$this->success([], '创建成功');
    }

    /**
     * 编辑页面
     */
    #[GetMapping('{id:\\\d+}/edit')]
    public function edit(int \$id): ResponseInterface
    {
        \$item = {$modelName}::query()
            ->where('site_id', site_id())
            ->findOrFail(\$id);

        return \$this->render('admin.system.{$routeSlug}.edit', [
            'item' => \$item,
        ]);
    }

    /**
     * 更新数据
     */
    #[PostMapping('{id:\\\d+}')]
    public function update(int \$id, {$modelName}UpdateRequest \$request): ResponseInterface
    {
        \$item = {$modelName}::query()
            ->where('site_id', site_id())
            ->findOrFail(\$id);

        \$data = \$request->validated();
        \$item->update(\$data);

        return \$this->success([], '更新成功');
    }

    /**
     * 删除数据
     */
    #[DeleteMapping('{id:\\\d+}')]
    public function destroy(int \$id): ResponseInterface
    {
        \$item = {$modelName}::query()
            ->where('site_id', site_id())
            ->findOrFail(\$id);

        \$item->delete();

        return \$this->success([], '删除成功');
    }
}

PHP;
    }

    /**
     * 生成 Store Request 验证器
     */
    protected function generateStoreRequest(AdminCrudConfig $config): string
    {
        $modelName = $config->model_name;
        $tableName = $config->table_name;
        $dbConnection = $config->db_connection ?? 'default';
        $columns = $this->databaseService->getTableColumns($tableName, $dbConnection);

        $rules = [];
        $messages = [];

        foreach ($columns as $column) {
            if ($column['is_primary'] || $column['is_auto_increment']) {
                continue;
            }

            if (in_array($column['name'], ['created_at', 'updated_at', 'deleted_at', 'site_id'])) {
                continue;
            }

            $rule = $this->getValidationRule($column, $tableName);
            if ($rule) {
                $rules[] = "'{$column['name']}' => '{$rule}'";

                $label = $column['comment'] ?: $column['name'];
                $messages[] = "'{$column['name']}.required' => '{$label}不能为空'";
            }
        }

        $rulesStr = implode(",\n            ", $rules);
        $messagesStr = implode(",\n            ", $messages);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Request\Admin;

use Hyperf\Validation\Request\FormRequest;

class {$modelName}StoreRequest extends FormRequest
{
    /**
     * 验证规则
     */
    public function rules(): array
    {
        return [
            {$rulesStr},
        ];
    }

    /**
     * 验证消息
     */
    public function messages(): array
    {
        return [
            {$messagesStr},
        ];
    }
}

PHP;
    }

    /**
     * 生成 Update Request 验证器
     */
    protected function generateUpdateRequest(AdminCrudConfig $config): string
    {
        $modelName = $config->model_name;
        $tableName = $config->table_name;
        $dbConnection = $config->db_connection ?? 'default';
        $columns = $this->databaseService->getTableColumns($tableName, $dbConnection);

        $rules = [];
        $messages = [];

        foreach ($columns as $column) {
            if ($column['is_primary'] || $column['is_auto_increment']) {
                continue;
            }

            if (in_array($column['name'], ['created_at', 'updated_at', 'deleted_at', 'site_id'])) {
                continue;
            }

            $rule = $this->getValidationRule($column, $tableName, true);
            if ($rule) {
                $rules[] = "'{$column['name']}' => '{$rule}'";

                $label = $column['comment'] ?: $column['name'];
                $messages[] = "'{$column['name']}.required' => '{$label}不能为空'";
            }
        }

        $rulesStr = implode(",\n            ", $rules);
        $messagesStr = implode(",\n            ", $messages);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Request\Admin;

use Hyperf\Validation\Request\FormRequest;

class {$modelName}UpdateRequest extends FormRequest
{
    /**
     * 验证规则
     */
    public function rules(): array
    {
        \$id = \$this->route('id');

        return [
            {$rulesStr},
        ];
    }

    /**
     * 验证消息
     */
    public function messages(): array
    {
        return [
            {$messagesStr},
        ];
    }
}

PHP;
    }

    /**
     * 生成列表视图
     */
    protected function generateIndexView(AdminCrudConfig $config): string
    {
        $moduleName = $config->module_name;
        $routeSlug = $config->route_slug;
        $routePrefix = $config->route_prefix ?: $routeSlug;
        $tableName = $config->table_name;
        $dbConnection = $config->db_connection ?? 'default';
        $columns = $this->databaseService->getTableColumns($tableName, $dbConnection);

        // 分离可列出的字段和默认显示的字段
        $listableFields = array_filter($columns, fn($col) => $col['listable'] ?? $col['show_in_list'] ?? true);
        $defaultFields = array_filter($columns, fn($col) => $col['list_default'] ?? $col['show_in_list'] ?? true);
        $searchFields = array_filter($columns, fn($col) => $col['searchable']);

        // 构建列选择器选项
        $columnOptions = '';
        foreach ($listableFields as $field) {
            $fieldName = $field['name'];
            $label = $field['field_name'] ?? $field['comment'] ?: $fieldName;
            $isDefault = in_array($fieldName, array_column($defaultFields, 'name'));
            $checked = $isDefault ? 'checked' : '';
            $columnOptions .= <<<HTML
                        <div class="form-check">
                            <input class="form-check-input column-toggle" type="checkbox"
                                   value="{$fieldName}" id="col-{$fieldName}" {$checked}>
                            <label class="form-check-label" for="col-{$fieldName}">
                                {$label}
                            </label>
                        </div>

HTML;
        }

        // 构建表头（所有可列出的列）
        $tableHeaders = '';
        foreach ($listableFields as $field) {
            $fieldName = $field['name'];
            $label = $field['field_name'] ?? $field['comment'] ?: $fieldName;
            $isDefault = in_array($fieldName, array_column($defaultFields, 'name'));
            $display = $isDefault ? '' : ' style="display:none;"';
            $tableHeaders .= "                <th class=\"column-{$fieldName}\"{$display}>{$label}</th>\n";
        }

        // 构建表格行（所有可列出的列）
        $tableRows = '';
        foreach ($listableFields as $field) {
            $fieldName = $field['name'];
            $isDefault = in_array($fieldName, array_column($defaultFields, 'name'));
            $display = $isDefault ? '' : ' style="display:none;"';

            if ($field['form_type'] === 'datetime' || $field['form_type'] === 'date') {
                $tableRows .= "                    <td class=\"column-{$fieldName}\"{$display}>{{ \$item->{$fieldName} ? \$item->{$fieldName}->format('Y-m-d H:i:s') : '-' }}</td>\n";
            } elseif ($fieldName === 'status') {
                $tableRows .= <<<HTML
                    <td class="column-{$fieldName}"{$display}>
                        @if(\$item->status == 1)
                            <span class="badge bg-success">启用</span>
                        @else
                            <span class="badge bg-danger">禁用</span>
                        @endif
                    </td>

HTML;
            } else {
                $tableRows .= "                    <td class=\"column-{$fieldName}\"{$display}>{{ \$item->{$fieldName} }}</td>\n";
            }
        }

        // 构建搜索表单
        $searchForm = '';
        foreach ($searchFields as $field) {
            $fieldName = $field['name'];
            $label = $field['comment'] ?: $field['name'];
            $formType = $field['form_type'] ?? 'text';
            
            // 区间数字类型：生成最小值-最大值输入框
            if ($formType === 'number_range') {
                $searchForm .= <<<HTML
                <div class="col-md-3">
                    <label class="form-label small">{$label}</label>
                    <div class="input-group">
                        <input type="number" name="{$fieldName}_min" class="form-control" placeholder="最小" value="{{ \$params['{$fieldName}_min'] ?? '' }}">
                        <span class="input-group-text">-</span>
                        <input type="number" name="{$fieldName}_max" class="form-control" placeholder="最大" value="{{ \$params['{$fieldName}_max'] ?? '' }}">
                    </div>
                </div>

HTML;
            } else {
                // 其他类型：普通文本框
            $searchForm .= <<<HTML
                <div class="col-md-3">
                    <input type="text" name="{$fieldName}" class="form-control" placeholder="{$label}" value="{{ \$params['{$fieldName}'] ?? '' }}">
                </div>

HTML;
            }
        }

        // 构建默认列的 JavaScript 数组
        $defaultColumnNames = array_map(fn($f) => $f['name'], $defaultFields);
        $defaultColumnsJs = "'" . implode("', '", $defaultColumnNames) . "'";

        // 查找用于显示删除确认的字段名称（优先使用 name、title、title_name 等字段，否则使用第一个文本字段）
        $displayFieldName = 'id';
        $displayFieldLabel = 'ID';
        foreach ($listableFields as $field) {
            $fieldName = $field['name'];
            if (in_array($fieldName, ['name', 'title', 'title_name', 'username', 'real_name'])) {
                $displayFieldName = $fieldName;
                $displayFieldLabel = $field['field_name'] ?? $field['comment'] ?: $fieldName;
                break;
            } elseif ($displayFieldName === 'id' && in_array($field['form_type'] ?? '', ['text', 'textarea'])) {
                $displayFieldName = $fieldName;
                $displayFieldLabel = $field['field_name'] ?? $field['comment'] ?: $fieldName;
            }
        }

        return <<<HTML
@extends('admin.layouts.app')

@section('title', '{$moduleName}管理')

@section('content')
<div class="container-fluid">
    <!-- 页面头部 -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">{$moduleName}列表</h3>
        <div>
            <!-- 列选择器下拉按钮 -->
            <div class="btn-group me-2">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-columns-gap"></i> 列显示
                </button>
                <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 200px;">
                    <div class="mb-2"><strong>选择显示的列：</strong></div>
{$columnOptions}                    <div class="mt-2 pt-2 border-top">
                        <button type="button" class="btn btn-sm btn-primary w-100" onclick="resetColumns()">
                            <i class="bi bi-arrow-clockwise"></i> 恢复默认
                        </button>
                    </div>
                </div>
            </div>
            <a href="{{ admin_route('{$routePrefix}/create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> 新增
            </a>
        </div>
    </div>

    <!-- 搜索表单 -->
    @if(count([{$searchForm}]) > 0)
    <div class="card mb-3">
        <div class="card-body">
            <form action="{{ admin_route('{$routePrefix}') }}" method="GET">
                <div class="row g-3">
{$searchForm}                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">搜索</button>
                        <a href="{{ admin_route('{$routePrefix}') }}" class="btn btn-secondary">重置</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- 数据表格 -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
{$tableHeaders}                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(\$items as \$item)
                        <tr>
{$tableRows}                            <td>
                                <a href="{{ admin_route('{$routePrefix}/' . \$item->id . '/edit') }}" class="btn btn-sm btn-info">编辑</a>
                                <button class="btn btn-sm btn-danger" onclick="deleteItem({{ \$item->id }}, '{{ \$item->{$displayFieldName} ?? \$item->id }}')">删除</button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="100" class="text-center">暂无数据</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- 分页 -->
            @if(\$total > \$pageSize)
            <nav>
                <ul class="pagination">
                    @for(\$i = 1; \$i <= ceil(\$total / \$pageSize); \$i++)
                    <li class="page-item {{ \$page == \$i ? 'active' : '' }}">
                        <a class="page-link" href="{{ admin_route('{$routePrefix}') }}?page={{ \$i }}">{{ \$i }}</a>
                    </li>
                    @endfor
                </ul>
            </nav>
            @endif
        </div>
    </div>

    <!-- 删除确认模态框 -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        确认删除
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <strong>警告：</strong>删除后将无法恢复！
                    </div>
                    <p class="mb-0">确定要删除 <strong id="deleteItemName"></strong> 吗？</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>
                        取消
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bi bi-trash me-1"></i>
                        确认删除
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@include('admin.common.scripts')

@push('scripts')
<script>
// 列显示控制
const STORAGE_KEY = '{$routeSlug}_visible_columns';
const DEFAULT_COLUMNS = [{$defaultColumnsJs}];

// 初始化列显示状态
document.addEventListener('DOMContentLoaded', function() {
    loadColumnPreferences();

    // 监听复选框变化
    document.querySelectorAll('.column-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            toggleColumn(this.value, this.checked);
            saveColumnPreferences();
        });
    });

    // 绑定确认删除按钮事件
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            executeDelete((id) => `{{ admin_route("{$routePrefix}") }}/\${id}`);
        });
    }
});

// 加载用户的列显示偏好
function loadColumnPreferences() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        try {
            const visibleColumns = JSON.parse(saved);
            applyColumnVisibility(visibleColumns);
        } catch (e) {
            console.error('解析列显示偏好失败:', e);
        }
    }
}

// 保存用户的列显示偏好
function saveColumnPreferences() {
    const visibleColumns = [];
    document.querySelectorAll('.column-toggle:checked').forEach(checkbox => {
        visibleColumns.push(checkbox.value);
    });
    localStorage.setItem(STORAGE_KEY, JSON.stringify(visibleColumns));
}

// 应用列显示状态
function applyColumnVisibility(visibleColumns) {
    document.querySelectorAll('.column-toggle').forEach(checkbox => {
        const isVisible = visibleColumns.includes(checkbox.value);
        checkbox.checked = isVisible;
        toggleColumn(checkbox.value, isVisible);
    });
}

// 切换列显示/隐藏
function toggleColumn(columnName, show) {
    const elements = document.querySelectorAll(`.column-\${columnName}`);
    elements.forEach(el => {
        el.style.display = show ? '' : 'none';
    });
}

// 恢复默认列显示
function resetColumns() {
    applyColumnVisibility(DEFAULT_COLUMNS);
    saveColumnPreferences();
}

// 删除项目（显示确认模态框）
function deleteItem(id, name) {
    showDeleteModal(id, name || id);
}
</script>
@endpush
HTML;
    }

    /**
     * 生成创建视图
     */
    protected function generateCreateView(AdminCrudConfig $config): string
    {
        $moduleName = $config->module_name;
        $routeSlug = $config->route_slug;
        $routePrefix = $config->route_prefix ?: $routeSlug;
        $tableName = $config->table_name;
        $dbConnection = $config->db_connection ?? 'default';
        $columns = $this->databaseService->getTableColumns($tableName, $dbConnection);

        $formFields = '';
        foreach ($columns as $column) {
            if ($column['is_primary'] || $column['is_auto_increment']) {
                continue;
            }

            if (in_array($column['name'], ['created_at', 'updated_at', 'deleted_at', 'site_id'])) {
                continue;
            }

            $formFields .= $this->generateFormField($column);
        }

        return <<<HTML
@extends('admin.layouts.app')

@section('title', '新增{$moduleName}')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <h3>新增{$moduleName}</h3>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="createForm">
{$formFields}
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">提交</button>
                    <a href="{{ admin_route('{$routePrefix}') }}" class="btn btn-secondary">返回</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Icon 字段实时预览
document.querySelectorAll('input[type="text"][id*="icon"]').forEach(input => {
    const preview = document.getElementById(input.id + '_preview');
    if (preview) {
        input.addEventListener('input', function() {
            const iconElement = preview.querySelector('i');
            if (iconElement) {
                iconElement.className = this.value || 'bi bi-star';
            }
        });
    }
});

// 表单提交
document.getElementById('createForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);

    try {
        const response = await fetch('{{ admin_route('{$routePrefix}') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.code === 0) {
            alert('创建成功');
            window.location.href = '{{ admin_route('{$routePrefix}') }}';
        } else {
            alert(result.message || '创建失败');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('创建失败');
    }
});
</script>
@endpush
HTML;
    }

    /**
     * 生成编辑视图
     */
    protected function generateEditView(AdminCrudConfig $config): string
    {
        $moduleName = $config->module_name;
        $routeSlug = $config->route_slug;
        $routePrefix = $config->route_prefix ?: $routeSlug;
        $tableName = $config->table_name;
        $dbConnection = $config->db_connection ?? 'default';
        $columns = $this->databaseService->getTableColumns($tableName, $dbConnection);

        $formFields = '';
        foreach ($columns as $column) {
            if ($column['is_primary'] || $column['is_auto_increment']) {
                continue;
            }

            if (in_array($column['name'], ['created_at', 'updated_at', 'deleted_at', 'site_id'])) {
                continue;
            }

            $formFields .= $this->generateFormField($column, true);
        }

        return <<<HTML
@extends('admin.layouts.app')

@section('title', '编辑{$moduleName}')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <h3>编辑{$moduleName}</h3>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="editForm">
{$formFields}
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">更新</button>
                    <a href="{{ admin_route('{$routePrefix}') }}" class="btn btn-secondary">返回</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Icon 字段实时预览
document.querySelectorAll('input[type="text"][id*="icon"]').forEach(input => {
    const preview = document.getElementById(input.id + '_preview');
    if (preview) {
        input.addEventListener('input', function() {
            const iconElement = preview.querySelector('i');
            if (iconElement) {
                iconElement.className = this.value || 'bi bi-star';
            }
        });
    }
});

// 表单提交
document.getElementById('editForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = Object.fromEntries(formData);

    try {
        const response = await fetch('{{ admin_route('{$routePrefix}/' . \$item->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.code === 0) {
            alert('更新成功');
            window.location.href = '{{ admin_route('{$routePrefix}') }}';
        } else {
            alert(result.message || '更新失败');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('更新失败');
    }
});
</script>
@endpush
HTML;
    }

    /**
     * 生成路由配置
     */
    protected function generateRoute(AdminCrudConfig $config): string
    {
        $controllerName = $config->controller_name;
        $routeSlug = $config->route_slug;
        $routePrefix = $config->route_prefix ?: $routeSlug;

        return <<<PHP
// {$config->module_name}管理路由
Router::addGroup('/admin/{{adminPath}}/{$routePrefix}', function () {
    Router::get('', 'App\Controller\Admin\System\\{$controllerName}@index');
    Router::get('/create', 'App\Controller\Admin\System\\{$controllerName}@create');
    Router::post('', 'App\Controller\Admin\System\\{$controllerName}@store');
    Router::get('/{{id:\\\d+}}/edit', 'App\Controller\Admin\System\\{$controllerName}@edit');
    Router::post('/{{id:\\\d+}}', 'App\Controller\Admin\System\\{$controllerName}@update');
    Router::delete('/{{id:\\\d+}}', 'App\Controller\Admin\System\\{$controllerName}@destroy');
}, ['middleware' => [App\Middleware\AdminAuthMiddleware::class]]);

PHP;
    }

    /**
     * 生成菜单 SQL
     */
    protected function generateMenuSql(AdminCrudConfig $config): string
    {
        $moduleName = $config->module_name;
        $routeSlug = $config->route_slug;
        $siteId = site_id();

        return <<<SQL
-- {$moduleName}管理菜单
INSERT INTO `admin_menus` (`site_id`, `parent_id`, `title`, `icon`, `route`, `component`, `is_link`, `is_hidden`, `is_keepalive`, `is_affix`, `is_iframe`, `sort`, `created_at`, `updated_at`) VALUES
({$siteId}, 0, '{$moduleName}', 'grid', '{$routeSlug}', 'admin/system/{$routeSlug}/index', 0, 0, 1, 0, 0, 100, NOW(), NOW());

SQL;
    }

    /**
     * 获取模型的类型转换
     */
    protected function getModelCast(array $column, ?array $fieldConfig = null): ?string
    {
        // 优先使用用户配置的 cast_type
        if ($fieldConfig && !empty($fieldConfig['cast_type'])) {
            return $fieldConfig['cast_type'];
        }

        // 自动推断类型
        $type = $column['data_type'];
        $name = $column['name'];

        if (in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
            return 'integer';
        }

        if (in_array($type, ['decimal', 'float', 'double'])) {
            return 'float';
        }

        if ($type === 'json') {
            return 'array';
        }

        if (in_array($type, ['date', 'datetime', 'timestamp'])) {
            return 'datetime';
        }

        if ($type === 'tinyint' && $column['max_length'] == 1) {
            return 'boolean';
        }

        return null;
    }

    /**
     * 获取 PHPDoc 属性声明
     */
    protected function getPropertyDoc(array $column, ?array $fieldConfig = null): string
    {
        $type = $this->getPhpType($column, $fieldConfig);
        $name = $column['name'];
        $comment = $column['comment'];

        $typeStr = $column['nullable'] ? "{$type}|null" : $type;
        $commentStr = $comment ? " {$comment}" : '';

        return "@property {$typeStr} \${$name}{$commentStr}";
    }

    /**
     * 获取 PHP 类型
     */
    protected function getPhpType(array $column, ?array $fieldConfig = null): string
    {
        // 优先根据用户配置的 cast_type 返回 PHP 类型
        if ($fieldConfig && !empty($fieldConfig['cast_type'])) {
            $castType = $fieldConfig['cast_type'];

            return match ($castType) {
                'integer' => 'int',
                'float' => 'float',
                'boolean' => 'bool',
                'array', 'json' => 'array',
                'datetime', 'date', 'timestamp' => '\Carbon\Carbon',
                default => 'string',
            };
        }

        // 自动推断类型
        $type = $column['data_type'];

        if (in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
            return 'int';
        }

        if (in_array($type, ['decimal', 'float', 'double'])) {
            return 'float';
        }

        if ($type === 'json') {
            return 'array';
        }

        if (in_array($type, ['date', 'datetime', 'timestamp'])) {
            return '\Carbon\Carbon';
        }

        if ($type === 'tinyint' && $column['max_length'] == 1) {
            return 'bool';
        }

        return 'string';
    }

    /**
     * 获取验证规则
     */
    protected function getValidationRule(array $column, string $tableName, bool $isUpdate = false): ?string
    {
        // 不可编辑字段不需要验证规则（系统自动维护，前端不会提交）
        $editable = $column['editable'] ?? true;
        if (!$editable) {
            return null;
        }

        $rules = [];

        // 必填规则
        if (!$column['nullable']) {
            $rules[] = 'required';
        }

        // 类型规则
        $type = $column['data_type'];
        if (in_array($type, ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
            $rules[] = 'integer';
        } elseif (in_array($type, ['decimal', 'float', 'double'])) {
            $rules[] = 'numeric';
        } elseif ($type === 'date') {
            $rules[] = 'date';
        } elseif (in_array($type, ['datetime', 'timestamp'])) {
            $rules[] = 'date';
        } elseif (in_array($type, ['varchar', 'char', 'text'])) {
            $rules[] = 'string';
            if ($column['max_length']) {
                $rules[] = "max:{$column['max_length']}";
            }
        }

        // 唯一规则
        if ($column['is_unique']) {
            $uniqueRule = "unique:{$tableName},{$column['name']}";
            if ($isUpdate) {
                $uniqueRule .= ',{$id}';
            }
            $rules[] = $uniqueRule;
        }

        // Email 规则
        if (str_contains(strtolower($column['name']), 'email')) {
            $rules[] = 'email';
        }

        return implode('|', $rules);
    }

    /**
     * 生成表单字段
     */
    protected function generateFormField(array $column, bool $isEdit = false): string
    {
        $fieldName = $column['name'];
        $label = $column['comment'] ?: $column['name'];
        $formType = $column['form_type'];
        
        // 处理默认值：编辑模式使用数据值，创建模式使用配置的默认值
        if ($isEdit) {
            $value = "\$item->{$fieldName}";
        } else {
            // 创建模式：使用配置的默认值，如果默认值是 "NULL" 字符串或 null，则保持空
            $defaultValue = $column['default_value'] ?? null;
            if ($defaultValue === 'NULL' || $defaultValue === 'null' || $defaultValue === null || $defaultValue === '') {
                $value = "''";
            } else {
                // 转义单引号，避免语法错误
                $escapedValue = str_replace("'", "\\'", (string)$defaultValue);
                $value = "'{$escapedValue}'";
            }
        }

        // 判断字段是否可编辑
        $editable = $column['editable'] ?? true;
        
        // 获取用户配置的 disabled 和 readonly 状态
        $userDisabled = isset($column['disabled']) && $column['disabled'];
        $userReadonly = isset($column['readonly']) && $column['readonly'];

        // 优先级：系统级 editable > 用户级 disabled/readonly
        // 如果系统级不可编辑，则强制禁用/只读
        // 如果系统级可编辑，则使用用户配置的 disabled/readonly
        
        // 不可编辑字段：不需要 required（系统自动维护），添加 readonly/disabled
        // 可编辑字段：根据 nullable 判断是否必填
        $required = ($editable && !$column['nullable']) ? 'required' : '';
        
        // 确定最终的 readonly 和 disabled 状态
        // 如果系统级不可编辑，则强制禁用
        if (!$editable) {
            $readonly = 'readonly';
            $disabled = 'disabled';
        } else {
            // 系统级可编辑，根据用户配置和表单类型决定使用哪个属性
            // 定义哪些表单类型使用 readonly，哪些使用 disabled
            $readonlyFormTypes = ['text', 'textarea', 'number', 'number_range', 'date', 'datetime', 'email', 'password', 'icon', 'rich_text'];
            $disabledFormTypes = ['select', 'radio', 'checkbox', 'switch', 'file', 'image', 'images', 'relation'];
            
            // 如果用户明确配置了 disabled 或 readonly，优先使用用户配置
            if ($userDisabled) {
                // 用户配置了 disabled，根据表单类型决定使用哪个属性
                // 对于输入框类型，如果用户配置了 disabled，我们仍然使用 disabled（虽然值不会提交，但这是用户的选择）
                $disabled = 'disabled';
                $readonly = ''; // disabled 和 readonly 互斥
            } elseif ($userReadonly) {
                // 用户配置了 readonly，根据表单类型决定使用哪个属性
                // 对于选择框类型，如果用户配置了 readonly，我们使用 disabled（因为选择框不支持 readonly）
                if (in_array($formType, $readonlyFormTypes)) {
                    $readonly = 'readonly';
                    $disabled = '';
                } else {
                    // 选择框类型不支持 readonly，使用 disabled
                    $disabled = 'disabled';
                    $readonly = '';
                }
            } else {
                // 用户未配置，字段可编辑
                $readonly = '';
                $disabled = '';
            }
        }

        $field = <<<HTML
                <div class="mb-3">
                    <label for="{$fieldName}" class="form-label">{$label}</label>

HTML;

        switch ($formType) {
            case 'textarea':
                $field .= <<<HTML
                    <textarea name="{$fieldName}" id="{$fieldName}" class="form-control" rows="5" {$required} {$readonly}>{{ {$value} }}</textarea>

HTML;
                break;

            case 'radio':
                // 使用配置的 options 或默认启用/禁用
                if (isset($column['options']) && !empty($column['options'])) {
                    $field .= "                    <div>\n";
                    foreach ($column['options'] as $optKey => $optLabel) {
                        $field .= <<<HTML
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="{$fieldName}" id="{$fieldName}_{$optKey}" value="{$optKey}" {{ {$value} == '{$optKey}' ? 'checked' : '' }} {$required} {$disabled}>
                            <label class="form-check-label" for="{$fieldName}_{$optKey}">{$optLabel}</label>
                        </div>

HTML;
                    }
                    $field .= "                    </div>\n\n";
                } else {
                    $field .= <<<HTML
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="{$fieldName}" id="{$fieldName}_1" value="1" {{ {$value} == 1 ? 'checked' : '' }} {$required} {$disabled}>
                            <label class="form-check-label" for="{$fieldName}_1">启用</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="{$fieldName}" id="{$fieldName}_0" value="0" {{ {$value} == 0 ? 'checked' : '' }} {$disabled}>
                            <label class="form-check-label" for="{$fieldName}_0">禁用</label>
                        </div>
                    </div>

HTML;
                }
                break;

            case 'select':
                $field .= <<<HTML
                    <select name="{$fieldName}" id="{$fieldName}" class="form-select" {$required} {$disabled}>
                        <option value="">请选择</option>

HTML;
                // 使用配置的 options
                if (isset($column['options']) && !empty($column['options'])) {
                    foreach ($column['options'] as $optKey => $optLabel) {
                        $field .= "                        <option value=\"{$optKey}\" {{ {$value} == '{$optKey}' ? 'selected' : '' }}>{$optLabel}</option>\n";
                    }
                }
                $field .= <<<HTML
                    </select>

HTML;
                break;

            case 'number':
                $stepAttr = isset($column['number_step']) && $column['number_step'] ? " step=\"{$column['number_step']}\"" : '';
                $field .= <<<HTML
                    <input type="number" name="{$fieldName}" id="{$fieldName}" class="form-control" value="{{ {$value} }}"{$stepAttr} {$required} {$readonly}>

HTML;
                break;

            case 'number_range':
                // 区间数字：最小值和最大值输入框
                $stepAttr = isset($column['number_step']) && $column['number_step'] ? " step=\"{$column['number_step']}\"" : '';
                $minFieldName = $fieldName . '_min';
                $maxFieldName = $fieldName . '_max';
                $field .= <<<HTML
                    <div class="row">
                        <div class="col-md-6">
                            <label for="{$minFieldName}" class="form-label">最小值</label>
                            <input type="number" name="{$minFieldName}" id="{$minFieldName}" class="form-control" placeholder="最小值"{$stepAttr} {$readonly}>
                        </div>
                        <div class="col-md-6">
                            <label for="{$maxFieldName}" class="form-label">最大值</label>
                            <input type="number" name="{$maxFieldName}" id="{$maxFieldName}" class="form-control" placeholder="最大值"{$stepAttr} {$readonly}>
                        </div>
                    </div>

HTML;
                break;

            case 'date':
                $field .= <<<HTML
                    <input type="date" name="{$fieldName}" id="{$fieldName}" class="form-control" value="{{ {$value} }}" {$required} {$readonly}>

HTML;
                break;

            case 'datetime':
                $field .= <<<HTML
                    <input type="datetime-local" name="{$fieldName}" id="{$fieldName}" class="form-control" value="{{ {$value} }}" {$required} {$readonly}>

HTML;
                break;

            case 'email':
                $field .= <<<HTML
                    <input type="email" name="{$fieldName}" id="{$fieldName}" class="form-control" value="{{ {$value} }}" {$required} {$readonly}>

HTML;
                break;

            case 'password':
                $field .= <<<HTML
                    <input type="password" name="{$fieldName}" id="{$fieldName}" class="form-control" {$required} {$readonly}>

HTML;
                break;

            case 'switch':
                $field .= <<<HTML
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="{$fieldName}" id="{$fieldName}" value="1" {{ {$value} ? 'checked' : '' }} {$disabled}>
                    </div>

HTML;
                break;

            case 'icon':
                $field .= <<<HTML
                    <div class="input-group">
                        <span class="input-group-text" id="{$fieldName}_preview">
                            <i class="{{ {$value} ?: 'bi bi-star' }}"></i>
                        </span>
                        <input type="text" name="{$fieldName}" id="{$fieldName}" class="form-control" 
                               value="{{ {$value} }}" placeholder="例如：bi bi-house" {$required} {$readonly}>
                    </div>
                    <small class="form-text text-muted">
                        输入 Bootstrap Icons 类名，如：bi bi-house、bi bi-person 等
                        <a href="https://icons.getbootstrap.com/" target="_blank" class="ms-2">查看图标库</a>
                    </small>

HTML;
                break;

            case 'image':
            case 'file':
                $field .= <<<HTML
                    <input type="file" name="{$fieldName}" id="{$fieldName}" class="form-control" {$disabled}>

HTML;
                break;

            default: // text
                $field .= <<<HTML
                    <input type="text" name="{$fieldName}" id="{$fieldName}" class="form-control" value="{{ {$value} }}" {$required} {$readonly}>

HTML;
                break;
        }

        $field .= "                </div>\n\n";

        return $field;
    }
}

