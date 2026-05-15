<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\AbstractController;
use App\Model\Admin\AdminCrudConfig;
use App\Model\Admin\AdminPermission;
use App\Service\Admin\CrudGeneratorService;
use App\Service\Admin\DatabaseService;
use App\Service\Admin\PermissionService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;

class CrudGeneratorController extends AbstractController
{

    #[Inject]
    protected DatabaseService $databaseService;
    #[Inject]
    protected CrudGeneratorService $crudGeneratorService;
    #[Inject]
    protected PermissionService $permissionService;


    /**
     * CRUD生成器首页 - 显示已生成的CRUD配置记录列表
     */
    public function index(): ResponseInterface
    {
        // 获取所有CRUD配置记录（超级管理员跳过站点筛选）
        $query = AdminCrudConfig::query();
        $configs = $query->orderBy('created_at', 'desc')->get();
        return $this->renderAdmin('admin.system.crud-generator.index', [
            'configs' => $configs,
        ]);
    }

    /**
     * 新建CRUD配置 - 显示数据表选择页面
     */
    public function create(): ResponseInterface
    {
        // 获取所有数据库连接
        $connections = $this->databaseService->getAllConnections();
        // 从请求参数获取数据库连接名称，默认为 'default'
        $connection = $this->request->query('connection', key($connections));
        
        // 确保远程连接已注册
        $this->databaseService->ensureConnectionRegistered($connection);
        
        $tables = $this->databaseService->getAllTables($connection);
        
        // 构建连接类型映射
        $connectionTypes = [];
        foreach ($connections as $name => $conn) {
            $connectionTypes[$name] = [
                'type' => $conn['type'] ?? 'config',
                'is_remote' => $conn['is_remote'] ?? false,
            ];
        }
        
        // 只获取当前连接下的配置
        $query = AdminCrudConfig::query()->where('db_connection', $connection);
        $configs = $query->get()->keyBy('table_name');
        
        return $this->renderAdmin('admin.system.crud-generator.create', [
            'tables' => $tables,
            'configs' => $configs,
            'connections' => $connections,
            'connectionTypes' => $connectionTypes, // 新增：连接类型信息
            'currentConnection' => $connection,
        ]);
    }

    /**
     * 配置页面 - 选择字段和配置属性
     */
    public function config(string $tableName): ResponseInterface
    {

        // 获取所有数据库连接
        $connections = $this->databaseService->getAllConnections();

        // 从请求参数获取数据库连接名称
        $requestConnection = $this->request->query('connection');
        $dbConnection = $requestConnection ?? key($connections);

        // 确保远程连接已注册
        $this->databaseService->ensureConnectionRegistered($dbConnection);

        // 尝试获取已有配置
        $query = AdminCrudConfig::query()
            ->where('table_name', $tableName)
            ->where('db_connection', $dbConnection);
        $config = $query->first();
        // 如果请求参数指定了连接，但配置不存在，使用请求参数
        if ($requestConnection && !$config) {
            $dbConnection = $requestConnection;
        } elseif ($config && !$requestConnection) {
            // 如果配置存在但没有请求参数，使用配置中的连接
            $dbConnection = $config->db_connection ?? key($connections);
        }

        // 构建连接类型映射
        $connectionTypes = [];
        foreach ($connections as $name => $conn) {
            $connectionTypes[$name] = [
                'type' => $conn['type'] ?? 'config',
                'is_remote' => $conn['is_remote'] ?? false,
            ];
        }

        $baseConfig = $this->buildBaseConfig($tableName, $dbConnection, $config);
        $tableComment = $baseConfig['table_comment'] ?? null;

        return $this->renderAdmin('admin.system.crud-generator.config', [
            'tableName' => $tableName,
            'tableComment' => $tableComment,
            'config' => $baseConfig,
            'connections' => $connections,
            'connectionTypes' => $connectionTypes, // 新增：连接类型信息
            'dbConnection' => $dbConnection,
            'currentConnInfo' => $connections[$dbConnection] ?? null,
        ]);
    }
    /**
     * 获取字段配置（分离加载 API）
     */
    public function getFieldsConfig(string $tableName): ResponseInterface
    {
        // 获取所有数据库连接
        $connections = $this->databaseService->getAllConnections();

        // 从请求参数获取数据库连接名称
        $requestConnection = $this->request->query('connection');
        $dbConnection = $requestConnection ?? key($connections);

        // 确保远程连接已注册
        $this->databaseService->ensureConnectionRegistered($dbConnection);

        // 尝试获取已有配置（超级管理员跳过站点筛选）
        $query = AdminCrudConfig::query()
            ->where('table_name', $tableName)
            ->where('db_connection', $dbConnection);
        if (!is_super_admin()) {
            $query->where('site_id', site_id());
        }
        $config = $query->first();

        // 如果请求参数指定了连接，但配置不存在，使用请求参数
        if ($requestConnection && !$config) {
            $dbConnection = $requestConnection;
        } elseif ($config && !$requestConnection) {
            // 如果配置存在但没有请求参数，使用配置中的连接
            $dbConnection = $config->db_connection ?? key($connections);
        }

        // 获取原始表结构（只返回数据库原始信息，不做任何推断和合并）
        $columns = $this->databaseService->getRawTableColumns($tableName, $dbConnection);

        $configData = $config ? $this->buildBaseConfig($tableName, $dbConnection, $config) : null;
        $connectionInfo = $connections[$dbConnection] ?? null;
        $tableMeta = [
            'name' => $tableName,
            'comment' => $configData['table_comment'] ?? $this->databaseService->getTableComment($tableName, $dbConnection),
        ];

        return $this->success([
            'columns' => $columns,
            'total' => count($columns),
            'db_connection' => $dbConnection,
            'config' => $configData,
            'table' => $tableMeta,
            'connection' => [
                'name' => $dbConnection,
                'info' => $connectionInfo,
            ],
        ], '字段配置加载成功');
    }

    /**
     * 构建基础配置，供视图和 API 共用
     */
    private function buildBaseConfig(string $tableName, string $dbConnection, ?AdminCrudConfig $config = null): array
    {
        $tableComment = $this->databaseService->getTableComment($tableName, $dbConnection);

        if ($config instanceof AdminCrudConfig) {
            $options = $config->options ?? [];
            if (!is_array($options)) {
                $options = [];
            }
            $featureColumns = [
                'search' => $config->feature_search,
                'add' => $config->feature_add,
                'edit' => $config->feature_edit,
                'delete' => $config->feature_delete,
                'export' => $config->feature_export,
            ];
            $hasFeatureColumns = array_filter($featureColumns, static fn($value) => $value !== null);
            $featuresSource = $hasFeatureColumns
                ? array_map(static fn($value) => (bool) $value, $featureColumns)
                : ($options['features'] ?? null);
            $features = $this->normalizeFeatureToggles($featuresSource);
            
            // 优先从独立字段读取，如果没有则从 options 中读取（向后兼容）
            $pageSize = $config->page_size ?? $options['page_size'] ?? 15;
            $softDelete = $config->soft_delete ?? ($options['soft_delete'] ?? $this->databaseService->hasColumn($tableName, 'deleted_at', $dbConnection));
            
            // 确保 soft_delete 被包含在 features 中
            if (isset($options['features']['soft_delete'])) {
                $features['soft_delete'] = filter_var($options['features']['soft_delete'], FILTER_VALIDATE_BOOLEAN);
            } else {
                $features['soft_delete'] = (bool)$softDelete;
            }

            return [
                'id' => $config->id,
                'table_name' => $config->table_name,
                'table_comment' => $tableComment,
                'db_connection' => $config->db_connection,
                'model_name' => $config->model_name,
                'controller_name' => $config->controller_name,
                'module_name' => $config->module_name,
                'route_prefix' => $config->route_prefix,
                'route_slug' => $config->route_slug,
                'icon' => $config->icon ?: 'bi bi-table',
                'page_size' => $pageSize,
                'soft_delete' => $softDelete,
                'options' => $options,
                'features' => $features,
                'sync_to_menu' => $config->sync_to_menu,
                'status' => $config->status,
                'fields_config' => $config->fields_config ?? [],
            ];
        }

        // 如果没有配置，只返回基本信息，让前端自己推测 model_name、route_slug 等
        $hasSoftDelete = $this->databaseService->hasColumn($tableName, 'deleted_at', $dbConnection);

        return [
            'table_name' => $tableName,
            'table_comment' => $tableComment,
            'db_connection' => $dbConnection,
            'model_name' => null, // 由前端推测
            'controller_name' => null, // 由前端推测
            'module_name' => null, // 由前端推测
            'route_prefix' => null, // 由前端推测
            'route_slug' => null, // 由前端推测
            'icon' => 'bi bi-table',
            'page_size' => 15,
            'soft_delete' => $hasSoftDelete,
            'options' => [],
            'features' => $this->getDefaultFeatureToggles(),
            'sync_to_menu' => true,
            'status' => AdminCrudConfig::STATUS_CONFIGURING,
            'fields_config' => [],
        ];
    }

    /**
     * 合并字段配置（提取的公共方法）
     */
    private function mergeFieldConfig(array $column, array $savedConfig): array
    {
        // 只覆盖用户可配置的字段，保留表结构的基础信息
        $column['field_name'] = $savedConfig['field_name'] ?? '';
        $column['show_in_list'] = $savedConfig['show_in_list'] ?? $column['show_in_list'];
        $column['listable'] = $savedConfig['listable'] ?? $column['listable'] ?? $column['show_in_list'];
        $column['list_default'] = $savedConfig['list_default'] ?? $column['list_default'] ?? $column['show_in_list'];
        $column['searchable'] = $savedConfig['searchable'] ?? $column['searchable'];
        $column['sortable'] = $savedConfig['sortable'] ?? $column['sortable'];
        $column['editable'] = $savedConfig['editable'] ?? $column['editable'];
        $column['form_type'] = $savedConfig['form_type'] ?? $column['form_type'];
        $column['model_type'] = $savedConfig['model_type'] ?? $column['model_type'];
        $column['required'] = $savedConfig['required'] ?? false;

        // 列渲染类型（column_type 或 render_type，优先使用 column_type）
        if (isset($savedConfig['column_type'])) {
            $column['column_type'] = $savedConfig['column_type'];
        } elseif (isset($savedConfig['render_type'])) {
            // 兼容旧字段名
            $column['column_type'] = $savedConfig['render_type'];
        }

        // 处理 default_value：确保 null 不被转换为字符串
        if (array_key_exists('default_value', $savedConfig)) {
            $column['default_value'] = $savedConfig['default_value'];
        }

        // 选项配置（针对 select/radio/switch 等类型）
        if (isset($savedConfig['options']) && is_array($savedConfig['options'])) {
            $column['options'] = $savedConfig['options'];
        }

        // 关联配置（针对 relation 类型）
        if (isset($savedConfig['relation']) && is_array($savedConfig['relation'])) {
            $defaultRelation = [
                'table' => '',
                'label_column' => 'name',
                'value_column' => 'id',
                'multiple' => false,
            ];
            $column['relation'] = array_merge($defaultRelation, $savedConfig['relation']);
        }

        // 数字步长（针对 number 类型）
        if (isset($savedConfig['number_step'])) {
            $column['number_step'] = $savedConfig['number_step'];
        }

        // 表单属性（placeholder、help、disabled、readonly、rows 等）
        if (isset($savedConfig['placeholder'])) {
            $column['placeholder'] = $savedConfig['placeholder'];
        }
        if (isset($savedConfig['help'])) {
            $column['help'] = $savedConfig['help'];
        }
        if (isset($savedConfig['disabled'])) {
            $column['disabled'] = filter_var($savedConfig['disabled'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($savedConfig['readonly'])) {
            $column['readonly'] = filter_var($savedConfig['readonly'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($savedConfig['rows'])) {
            $column['rows'] = (int) $savedConfig['rows'];
        }

        // 数字类型属性（min、max、step）
        // 注意：max 也用于字符串类型，表示最大长度
        if (isset($savedConfig['min'])) {
            $column['min'] = $savedConfig['min'];
        }
        if (isset($savedConfig['max'])) {
            $column['max'] = $savedConfig['max'];
        }
        if (isset($savedConfig['step'])) {
            $column['step'] = $savedConfig['step'];
        }

        // 开关类型属性（onValue、offValue、onLabel、offLabel）
        if (isset($savedConfig['onValue'])) {
            $column['onValue'] = $savedConfig['onValue'];
        }
        if (isset($savedConfig['offValue'])) {
            $column['offValue'] = $savedConfig['offValue'];
        }
        if (isset($savedConfig['onLabel'])) {
            $column['onLabel'] = $savedConfig['onLabel'];
        }
        if (isset($savedConfig['offLabel'])) {
            $column['offLabel'] = $savedConfig['offLabel'];
        }

        // 搜索配置（search 对象）
        if (isset($savedConfig['search']) && is_array($savedConfig['search'])) {
            $column['search'] = $savedConfig['search'];
        }

        // 类型特定属性（type_attrs）
        if (isset($savedConfig['type_attrs']) && is_array($savedConfig['type_attrs'])) {
            $column['type_attrs'] = $savedConfig['type_attrs'];
        }

        // 徽章默认颜色（badge_default_color）
        if (isset($savedConfig['badge_default_color'])) {
            $column['badge_default_color'] = $savedConfig['badge_default_color'];
        }

        return $column;
    }
//
    /**
     * 保存配置 V2 版本
     */
    public function saveConfig(): ResponseInterface
    {
        $data = $this->request->all();

        // 验证必填字段
        if (empty($data['table_name'])) {
            return $this->error('表名不能为空');
        }

        // 确保 table_name 是字符串类型
        $tableName = is_string($data['table_name']) ? $data['table_name'] : (string)$data['table_name'];
        if (trim($tableName) === '') {
            return $this->error('表名不能为空');
        }

        // 查询配置（超级管理员跳过站点筛选）
        $query = AdminCrudConfig::query()->where('table_name', $tableName);
        if (!is_super_admin()) {
            $query->where('site_id', site_id());
        }
        $config = $query->first();

        // 处理字段配置中的默认值
        $fieldsConfig = $this->processDefaultValues($data['fields_config'] ?? []);

        // 记录用于创建菜单的字段的旧值（用于判断是否需要更新菜单）
        $oldMenuValues = [
            'model_name' => $config?->model_name,
            'module_name' => $config?->module_name,
            'route_slug' => $config?->route_slug,
            'icon' => $config?->icon,
        ];

        // 记录用于创建权限的字段的旧值（用于判断是否需要更新权限）
        $oldPermissionValues = [
            'route_slug' => $config?->route_slug,
            'route_prefix' => $config?->route_prefix,
            'module_name' => $config?->module_name,
            'icon' => $config?->icon,
        ];

        // 获取菜单同步配置（默认为1，即默认勾选）
        $syncToMenu = isset($data['sync_to_menu']) ? filter_var($data['sync_to_menu'], FILTER_VALIDATE_BOOLEAN) : true;

        // 获取状态配置（默认为1，即默认开启）
        // 表单中 status 是 checkbox，勾选时值为 "1" 或 1，未勾选时值为 "0" 或 0
        // 前端已处理：未勾选时也会提交 0，所以这里直接处理即可
        $status = isset($data['status']) ? (int)filter_var($data['status'], FILTER_VALIDATE_BOOLEAN) : 1;

        // 获取 page_size 和 soft_delete（作为独立字段）
        $pageSize = isset($data['page_size']) ? (int)$data['page_size'] : ($config?->page_size ?? 15);
        if ($pageSize < 1) {
            $pageSize = 15;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        // 获取 options（不再包含 page_size 和 soft_delete）
        $options = $data['options'] ?? [];
        // 从 options 中移除 page_size 和 soft_delete（如果存在），因为它们是独立字段
        unset($options['page_size'], $options['soft_delete']);
        if (!is_array($options)) {
            $options = [];
        }

        // 获取功能配置：优先从 features[soft_delete] 读取，其次从独立的 soft_delete 字段读取
        $featureInput = isset($data['features']) && is_array($data['features']) ? $data['features'] : [];
        $featureToggles = $this->normalizeFeatureToggles($featureInput);
        
        // 处理 soft_delete：优先从 features[soft_delete] 读取，其次从独立的 soft_delete 字段读取
        if (isset($featureInput['soft_delete'])) {
            $softDelete = filter_var($featureInput['soft_delete'], FILTER_VALIDATE_BOOLEAN);
            $featureToggles['soft_delete'] = $softDelete;
        } elseif (isset($data['soft_delete'])) {
            $softDelete = filter_var($data['soft_delete'], FILTER_VALIDATE_BOOLEAN);
            $featureToggles['soft_delete'] = $softDelete;
        } else {
            // 如果没有提供，使用配置中的值或检测表是否有 deleted_at 字段
            $softDelete = $config?->soft_delete ?? false;
            if ($config === null) {
                $softDelete = $this->databaseService->hasColumn($tableName, 'deleted_at', $data['db_connection'] ?? 'default');
            }
            $featureToggles['soft_delete'] = $softDelete;
        }
        
        $options['features'] = $featureToggles;

        // 获取数据库连接名称（默认为 'default'）
        $dbConnection = $data['db_connection'] ?? 'default';
        
        // 确保远程连接已注册
        $this->databaseService->ensureConnectionRegistered($dbConnection);
        
        // 判断连接类型
        $connections = $this->databaseService->getAllConnections();
        $isRemoteConnection = $connections[$dbConnection]['is_remote'] ?? false;
        
        $routeSlugInput = $this->normalizeRouteSlug($data['route_slug'] ?? null);

        if ($routeSlugInput === '') {

            $routeSlugInput = $this->guessRouteSlug($tableName);
        }

        $routePrefixInput = $this->normalizeRoutePrefix($data['route_prefix'] ?? null);
        if ($routePrefixInput === '') {
            $routePrefixInput = $this->guessRoutePrefix($tableName, $routeSlugInput);
        }

        if ($config) {
            $config->update([
                'is_remote_connection' => $isRemoteConnection ? 1 : 0,
                'db_connection' => $dbConnection,
                'model_name' => $data['model_name'] ?? $config->model_name,
                'controller_name' => $data['controller_name'] ?? $config->controller_name,
                'module_name' => $data['module_name'] ?? $config->module_name,
                'route_prefix' => $routePrefixInput ?: $config->route_prefix,
                'route_slug' => $routeSlugInput ?: $config->route_slug,
                'icon' => $data['icon'] ?? $config->icon,
                'page_size' => $pageSize,
                'soft_delete' => $softDelete ? 1 : 0,
                'feature_search' => $featureToggles['search'] ? 1 : 0,
                'feature_add' => $featureToggles['add'] ? 1 : 0,
                'feature_edit' => $featureToggles['edit'] ? 1 : 0,
                'feature_delete' => $featureToggles['delete'] ? 1 : 0,
                'feature_export' => $featureToggles['export'] ? 1 : 0,
                'fields_config' => $fieldsConfig,
                'options' => $options,
                'sync_to_menu' => $syncToMenu,
                'status' => $status,
            ]);
        } else {
            // 如果没有提供必要字段，使用默认值
            $modelName = $data['model_name'] ?? $this->guessModelName($tableName);
            $controllerName = $data['controller_name'] ?? $modelName . 'Controller';
            $moduleName = $data['module_name'] ?? '';
            $routeSlug = $routeSlugInput ?: $this->guessRouteSlug($tableName);
            $routePrefix = $routePrefixInput ?: $this->guessRoutePrefix($tableName, $routeSlug);

            $config = AdminCrudConfig::create([
                'site_id' => site_id(),
                'table_name' => $tableName,
                'is_remote_connection' => $isRemoteConnection ? 1 : 0,
                'db_connection' => $dbConnection,
                'model_name' => $modelName,
                'controller_name' => $controllerName,
                'module_name' => $moduleName,
                'route_prefix' => $routePrefix,
                'route_slug' => $routeSlug,
                'icon' => $data['icon'] ?? null,
                'page_size' => $pageSize,
                'soft_delete' => $softDelete ? 1 : 0,
                'feature_search' => $featureToggles['search'] ? 1 : 0,
                'feature_add' => $featureToggles['add'] ? 1 : 0,
                'feature_edit' => $featureToggles['edit'] ? 1 : 0,
                'feature_delete' => $featureToggles['delete'] ? 1 : 0,
                'feature_export' => $featureToggles['export'] ? 1 : 0,
                'fields_config' => $fieldsConfig,
                'options' => $options,
                'sync_to_menu' => $syncToMenu,
                'status' => $status,
            ]);
        }

        // 自动管理菜单规则
        // 如果启用了菜单同步，检查用于创建菜单的字段是否有变化
        if ($syncToMenu) {

            // 获取新值（需要重新加载配置以获取更新后的值）
            $config->refresh();
            $newMenuValues = [
                'model_name' => $config->model_name,
                'module_name' => $config->module_name,
                'route_slug' => $config->route_slug,
                'icon' => $config->icon,
            ];

            // 检查是否有任何菜单相关字段发生变化
            $menuValuesChanged = false;
            $changedFields = [];
            foreach ($newMenuValues as $key => $newValue) {
                $oldValue = $oldMenuValues[$key] ?? null;
                // 标准化比较（处理 null 和空字符串）
                $oldValueNormalized = $oldValue === null || $oldValue === '' ? null : (string)$oldValue;
                $newValueNormalized = $newValue === null || $newValue === '' ? null : (string)$newValue;
                
                if ($oldValueNormalized !== $newValueNormalized) {
                    $menuValuesChanged = true;
                    $changedFields[$key] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
            logger()->info('[CRUD配置保存V2] 菜单相关字段未变化，跳过菜单同步哈哈哈', [
                'config_id' => $config->id,
                'menu_values' => $newMenuValues,
            ]);
            if ($menuValuesChanged) {
                logger()->info('[CRUD配置保存V2] 检测到菜单相关字段变化，准备同步菜单', [
                    'config_id' => $config->id,
                    'sync_to_menu' => $syncToMenu,
                    'changed_fields' => $changedFields,
                    'old_values' => $oldMenuValues,
                    'new_values' => $newMenuValues,
                ]);

                try {
                    $this->syncMenuForConfig(
                        $config,
                        $oldMenuValues['model_name'] ?? null,
                        $oldMenuValues['route_slug'] ?? null
                    );
                } catch (\Exception $e) {
                    logger()->error('[CRUD配置保存V2] 同步菜单失败', [
                        'config_id' => $config->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                logger()->info('[CRUD配置保存V2] 菜单相关字段未变化，跳过菜单同步', [
                    'config_id' => $config->id,
                    'menu_values' => $newMenuValues,
                ]);
            }
        } else {
            logger()->info('[CRUD配置保存V2] 未启用菜单同步，跳过菜单操作', [
                'config_id' => $config->id,
                'sync_to_menu' => $syncToMenu,
            ]);
        }

        // 自动管理权限
        try {
            // 重新加载配置以获取更新后的值
            $config->refresh();
            
            // 同步权限（如果 route_slug 变化，会先删除旧权限再创建新权限）
            $this->syncPermissionsForConfig($config, $oldPermissionValues);
        } catch (\Exception $e) {
            logger()->error('[CRUD配置保存V2] 同步权限失败', [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);
            // 权限同步失败不影响配置保存，只记录日志
        }

        return $this->success([
            'config_id' => $config->id,
        ], '配置保存成功');
    }

    /**
     * 默认功能开关
     */
    private function getDefaultFeatureToggles(): array
    {
        return [
            'search' => true,
            'add' => true,
            'edit' => true,
            'delete' => true,
            'export' => true,
            'soft_delete' => false, // 回收站功能默认关闭
        ];
    }

    /**
     * 规范化功能开关配置
     */
    private function normalizeFeatureToggles(null|array $input): array
    {
        $defaults = $this->getDefaultFeatureToggles();
        if (empty($input)) {
            return $defaults;
        }

        foreach ($defaults as $key => $defaultValue) {
            if (array_key_exists($key, $input)) {
                $defaults[$key] = filter_var($input[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $defaults;
    }

    /**
     * 验证和清理字段配置数据 V2 版本
     * V2 版本的数据格式可能包含 type_attrs 等新字段
     */
    protected function processDefaultValues(array $fieldsConfig): array
    {
        foreach ($fieldsConfig as &$field) {
            // 1. 处理默认值：如果前端没有设置，从数据库信息推断
            if (!isset($field['default_value'])) {
                $field['default_value'] = $field['default_value_db'] ?? null;
            }
            // 统一空值处理
            if ($field['default_value'] === '' || $field['default_value'] === 'NULL' || $field['default_value'] === 'null') {
                $field['default_value'] = null;
            }

            // 2. 验证布尔字段类型（防御性编程，前端应该已经处理好）
            $booleanFields = [
                'listable', 'list_default', 'searchable', 'sortable',
                'required', 'editable', 'nullable', 'is_primary',
                'is_auto_increment', 'is_list', 'is_search', 'is_required',
                'disabled', 'readonly'
            ];
            foreach ($booleanFields as $boolField) {
                if (isset($field[$boolField]) && !is_bool($field[$boolField])) {
                    $field[$boolField] = filter_var($field[$boolField], FILTER_VALIDATE_BOOLEAN);
                }
            }
            // 兼容旧字段
            if (isset($field['show_in_list']) && !is_bool($field['show_in_list'])) {
                $field['show_in_list'] = filter_var($field['show_in_list'], FILTER_VALIDATE_BOOLEAN);
            }

            // 3. 验证关联配置中的布尔字段类型
            if (isset($field['relation']) && is_array($field['relation'])) {
                if (isset($field['relation']['multiple']) && !is_bool($field['relation']['multiple'])) {
                    $field['relation']['multiple'] = filter_var($field['relation']['multiple'], FILTER_VALIDATE_BOOLEAN);
                }
            }

            // 4. 验证 options 字段中的数据类型（保留所有配置，不做清理）
            if (isset($field['options']) && is_array($field['options'])) {
                foreach ($field['options'] as &$option) {
                    if (is_array($option)) {
                        // 确保数据类型正确，但不删除任何数据
                        if (isset($option['key']) && !is_string($option['key']) && !is_numeric($option['key'])) {
                            $option['key'] = (string)$option['key'];
                        }
                    }
                }
                unset($option);
            }

            // 5. 验证 search 配置中的布尔字段类型（保留所有配置，不做清理）
            if (isset($field['search']) && is_array($field['search'])) {
                if (isset($field['search']['enabled']) && !is_bool($field['search']['enabled'])) {
                    $field['search']['enabled'] = filter_var($field['search']['enabled'], FILTER_VALIDATE_BOOLEAN);
                }
            }

            // 6. 验证 type_attrs 配置（保留所有配置，不做清理）
            // 只做基本的数据类型验证，不删除任何配置

            // 7. 清理临时字段（必须）
            unset($field['default_value_db']);
        }

        return $fieldsConfig;
    }
//
    /**
     * 删除配置
     */
    public function delete(int $id): ResponseInterface
    {
        try {
            $query = AdminCrudConfig::query();
            $config = $query->findOrFail($id);
            // 如果启用了菜单同步，删除对应的菜单
            if ($config->sync_to_menu) {
                logger()->info('[CRUD配置删除] 准备删除关联菜单', [
                    'config_id' => $config->id,
                    'table_name' => $config->table_name,
                    'route_slug' => $config->route_slug,
                ]);
                $this->deleteMenuForConfig($config);
            }
            // 删除关联的权限
            try {
                $this->deletePermissionsForConfig($config);
            } catch (\Exception $e) {
                logger()->error('[CRUD配置删除] 删除权限失败', [
                    'config_id' => $config->id,
                    'error' => $e->getMessage(),
                ]);
                // 权限删除失败不影响配置删除，只记录日志
            }
            $config->delete();
            return $this->success([], '删除成功');
        } catch (\Exception $e) {
            logger()->error('[CRUD配置删除] 处理异常', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('处理异常');
        }
    }

    /**
     * 推测模型名称
     */
    protected function guessModelName(string $tableName): string
    {
        // 确保输入是字符串类型
        if (!is_string($tableName)) {
            $tableName = (string)$tableName;
        }
        
        // 如果为空，返回默认值
        if (trim($tableName) === '') {
            return 'Model';
        }
        
        // 直接转换表名为驼峰命名，不移除任何前缀
        // 例如：admin_articles -> AdminArticles, users -> Users, sys_configs -> SysConfigs
        $name = str_replace('_', ' ', $tableName);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);

        // 单数化（简单处理）
        if (str_ends_with($name, 's')) {
            $name = substr($name, 0, -1);
        }

        return $name;
    }
    /**
     * 推测路由标识
     */
    protected function guessRouteSlug(string $tableName): string
    {
        // 确保输入是字符串类型
        if (!is_string($tableName)) {
            $tableName = (string)$tableName;
        }
        
        // 如果为空，返回默认值
        if (trim($tableName) === '') {
            return 'default';
        }
        
        return $this->normalizeRouteSlug($tableName) ?: $tableName;
    }

    /**
     * 推测路由前缀
     */
    protected function guessRoutePrefix(string $tableName, ?string $routeSlug = null): string
    {
        // 确保输入是字符串类型
        if (!is_string($tableName)) {
            $tableName = (string)$tableName;
        }
        
        $routeSlug = $routeSlug ?: $this->guessRouteSlug($tableName);
        return $routeSlug;
    }

    /**
     * 清洗路由标识，移除斜杠和非法字符
     */
    protected function normalizeRouteSlug(?string $slug): string
    {
        if ($slug === null) {
            return '';
        }

        // 确保是字符串类型
        if (!is_string($slug)) {
            $slug = (string)$slug;
        }

        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }

        // 移除首尾斜杠并替换中间的斜杠
        $slug = trim($slug, "/\\");
        $slug = str_replace(['/', '\\'], '-', $slug);

        // 仅保留字母、数字、连字符、下划线
        $slug = preg_replace('/[^A-Za-z0-9_-]/', '-', $slug);

        // 合并连续的 -
        $slug = preg_replace('/-+/', '-', $slug);

        return trim($slug, '-');
    }

    /**
     * 清洗路由前缀，保留多级路径
     */
    protected function normalizeRoutePrefix(?string $prefix): string
    {
        if ($prefix === null) {
            return '';
        }

        // 确保是字符串类型
        if (!is_string($prefix)) {
            $prefix = (string)$prefix;
        }

        $prefix = trim($prefix);
        if ($prefix === '') {
            return '';
        }

        $prefix = str_replace('\\', '/', $prefix);
        $segments = array_map(function ($segment) {
            return $this->normalizeRouteSlug($segment);
        }, explode('/', $prefix));

        $segments = array_filter($segments, static fn($segment) => $segment !== '');

        return implode('/', $segments);
    }

//
//
//
    /**
     * 为 CRUD 配置同步菜单
     *
     * @param AdminCrudConfig $config 当前配置
     * @param string|null $oldModelName 旧的模型名称（仅用于日志）
     * @param string|null $oldRouteSlug 旧的路由标识（用于清理旧菜单）
     * @throws \Exception
     */
    protected function syncMenuForConfig(AdminCrudConfig $config, ?string $oldModelName = null, ?string $oldRouteSlug = null): void
    {
        $siteId = $config->site_id;
        $moduleName = $config->module_name;
        $routeSlug = $config->route_slug;
        $permissionSlug = $this->buildCrudPermissionSlug($config);

        // 直接从 icon 字段获取，如果没有则使用默认图标
        $icon = $config->icon ?? 'bi bi-table';

        // 日志：开始同步菜单
        logger()->info('[CRUD菜单同步] 开始同步菜单', [
            'config_id' => $config->id,
            'site_id' => $siteId,
            'table_name' => $config->table_name,
            'model_name' => $config->model_name,
            'old_model_name' => $oldModelName,
            'old_route_slug' => $oldRouteSlug,
            'module_name' => $moduleName,
            'route_slug' => $routeSlug,
            'icon' => $icon,
        ]);

        // 查找系统管理父菜单
        $systemMenu = \App\Model\Admin\AdminMenu::query()
            ->where('site_id', $siteId)
            ->where('name', 'system')
            ->first();

        // 如果没有系统管理菜单，创建一个
        if (!$systemMenu) {
            logger()->info('[CRUD菜单同步] 系统管理菜单不存在，开始创建', [
                'site_id' => $siteId,
            ]);

            $systemMenu = \App\Model\Admin\AdminMenu::create([
                'site_id' => $siteId,
                'parent_id' => 0,
                'name' => 'system',
                'title' => '系统管理',
                'icon' => 'bi bi-gear',
                'path' => '/system',
                'type' => 'menu',
                'visible' => 1,
                'status' => 1,
                'sort' => 999,
            ]);

            logger()->info('[CRUD菜单同步] 系统管理菜单创建成功', [
                'menu_id' => $systemMenu->id,
                'menu_name' => $systemMenu->name,
                'menu_title' => $systemMenu->title,
            ]);
        } else {
            logger()->info('[CRUD菜单同步] 找到系统管理菜单', [
                'menu_id' => $systemMenu->id,
                'menu_name' => $systemMenu->name,
                'menu_title' => $systemMenu->title,
            ]);
        }

        // 如果旧的 route_slug 存在且与当前不同，删除旧菜单（无需兼容旧路径）
        if ($oldRouteSlug && $oldRouteSlug !== $routeSlug) {
            $previousMenuPath = "/u/{$oldRouteSlug}";
            logger()->info('[CRUD菜单同步] 检测到路由标识变化，准备删除旧菜单', [
                'old_route_slug' => $oldRouteSlug,
                'new_route_slug' => $routeSlug,
                'old_menu_path' => $previousMenuPath,
            ]);

            $oldMenu = \App\Model\Admin\AdminMenu::query()
                ->where('site_id', $siteId)
                ->where('parent_id', $systemMenu->id)
                ->where('path', $previousMenuPath)
                ->first();

            if ($oldMenu) {
                logger()->info('[CRUD菜单同步] 找到旧菜单，开始删除', [
                    'old_menu_id' => $oldMenu->id,
                    'old_menu_name' => $oldMenu->name,
                    'old_menu_title' => $oldMenu->title,
                    'old_menu_path' => $oldMenu->path,
                ]);

                $oldMenu->delete();

                logger()->info('[CRUD菜单同步] 旧菜单删除成功', [
                    'deleted_menu_id' => $oldMenu->id,
                ]);
            } else {
                logger()->info('[CRUD菜单同步] 未找到旧菜单，跳过删除', [
                    'search_path' => $previousMenuPath,
                ]);
            }
        }

        // 查找或创建当前模块的菜单，路径固定为 /u/{route_slug}
        $menuPath = "/u/{$routeSlug}";

        logger()->info('[CRUD菜单同步] 查找当前模块的菜单', [
            'site_id' => $siteId,
            'parent_id' => $systemMenu->id,
            'model_name' => $config->model_name,
            'search_path' => $menuPath,
        ]);

        $parentMenu = \App\Model\Admin\AdminMenu::query()
            ->where('site_id', $siteId)
            ->where('parent_id', $systemMenu->id)
            ->where('path', $menuPath)
            ->first();

        if (!$parentMenu) {
            logger()->info('[CRUD菜单同步] 菜单不存在，开始创建', [
                'module_name' => $moduleName,
                'model_name' => $config->model_name,
                'menu_path' => $menuPath,
                'icon' => $icon,
            ]);

            // 创建菜单
            $parentMenu = \App\Model\Admin\AdminMenu::create([
                'site_id' => $siteId,
                'parent_id' => $systemMenu->id,
                'name' => $routeSlug,
                'title' => $moduleName,
                'icon' => $icon,
                'path' => $menuPath,
                'type' => 'menu',
                'visible' => 1,
                'status' => 1,
                'sort' => 100,
                'permission' => $permissionSlug,
            ]);

            logger()->info('[CRUD菜单同步] 菜单创建成功', [
                'menu_id' => $parentMenu->id,
                'menu_name' => $parentMenu->name,
                'menu_title' => $parentMenu->title,
                'menu_path' => $parentMenu->path,
            ]);
        } else {
            // 构建需要更新的字段
            $updateData = [];
            
            // 如果 module_name 变化，更新 title
            if ($parentMenu->title !== $moduleName) {
                $updateData['title'] = $moduleName;
            }
            
            // 如果 icon 变化，更新 icon
            if ($parentMenu->icon !== $icon) {
                $updateData['icon'] = $icon;
            }
            
            // 如果 route_slug 变化，更新 name
            if ($parentMenu->name !== $routeSlug) {
                $updateData['name'] = $routeSlug;
            }
            
            // 如果 route_slug 变化，更新 path（正常情况下菜单路径与 route_slug 一致）
            $newMenuPath = "/u/{$routeSlug}";
            if ($parentMenu->path !== $newMenuPath) {
                $updateData['path'] = $newMenuPath;
            }

            if ($parentMenu->permission !== $permissionSlug) {
                $updateData['permission'] = $permissionSlug;
            }

            // 如果有需要更新的字段，执行更新
            if (!empty($updateData)) {
                logger()->info('[CRUD菜单同步] 找到菜单，开始更新', [
                    'menu_id' => $parentMenu->id,
                    'old_title' => $parentMenu->title,
                    'new_title' => $moduleName,
                    'old_icon' => $parentMenu->icon,
                    'new_icon' => $icon,
                    'old_name' => $parentMenu->name,
                    'new_name' => $routeSlug,
                    'old_path' => $parentMenu->path,
                    'new_path' => $newMenuPath,
                    'update_fields' => array_keys($updateData),
                ]);

                // 更新菜单
                $parentMenu->update($updateData);

                logger()->info('[CRUD菜单同步] 菜单更新成功', [
                    'menu_id' => $parentMenu->id,
                    'updated_fields' => array_keys($updateData),
                ]);
            } else {
                logger()->info('[CRUD菜单同步] 菜单字段无变化，跳过更新', [
                    'menu_id' => $parentMenu->id,
                ]);
            }
        }

        logger()->info('[CRUD菜单同步] 菜单同步完成', [
            'config_id' => $config->id,
            'model_name' => $config->model_name,
            'menu_id' => $parentMenu->id,
            'menu_title' => $parentMenu->title,
            'menu_path' => $parentMenu->path,
        ]);
    }

    /**
     * 删除 CRUD 配置对应的菜单
     *
     * @param AdminCrudConfig $config CRUD 配置
     * @throws \Exception
     */
    protected function deleteMenuForConfig(AdminCrudConfig $config): void
    {
        $siteId = $config->site_id;
        $routeSlug = $config->route_slug;
        $menuPath = "/u/{$routeSlug}";

        logger()->info('[CRUD菜单删除] 开始删除菜单', [
            'config_id' => $config->id,
            'site_id' => $siteId,
            'table_name' => $config->table_name,
            'model_name' => $config->model_name,
            'route_slug' => $routeSlug,
            'menu_path' => $menuPath,
        ]);

        // 查找系统管理父菜单
        $systemMenu = \App\Model\Admin\AdminMenu::query()
            ->where('site_id', $siteId)
            ->where('name', 'system')
            ->first();

        if (!$systemMenu) {
            logger()->info('[CRUD菜单删除] 系统管理菜单不存在，无需删除', [
                'site_id' => $siteId,
            ]);
            return;
        }

        // 查找要删除的菜单（仅使用最新的 /u/{route_slug} 路径）
        $menu = \App\Model\Admin\AdminMenu::query()
            ->where('site_id', $siteId)
            ->where('parent_id', $systemMenu->id)
            ->where('path', $menuPath)
            ->first();

        if (!$menu) {
            logger()->info('[CRUD菜单删除] 未找到对应菜单，无需删除', [
                'route_slug' => $routeSlug,
                'search_path' => $menuPath,
            ]);
            return;
        }

        logger()->info('[CRUD菜单删除] 找到菜单，开始删除', [
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'menu_title' => $menu->title,
            'menu_path' => $menu->path,
        ]);

        // 删除菜单
        $menu->delete();

        logger()->info('[CRUD菜单删除] 菜单删除成功', [
            'deleted_menu_id' => $menu->id,
            'deleted_menu_title' => $menu->title,
        ]);
    }
//
    /**
     * 从模型名称推测表名
     *
     * @param string $modelName 模型名称（如 AdminUser）
     * @return string 表名（如 admin_users）
     */
    protected function guessTableNameFromModel(string $modelName): string
    {
        // 将驼峰命名转换为下划线命名
        $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName));

        // 简单的复数化处理（添加 s）
        if (!str_ends_with($tableName, 's')) {
            $tableName .= 's';
        }

        return $tableName;
    }

    /**
     * 为 CRUD 配置同步权限
     *
     * @param AdminCrudConfig $config CRUD 配置
     * @param array|null $oldValues 旧的权限相关字段值（用于检测变化）
     * @throws \Exception
     */
    protected function syncPermissionsForConfig(AdminCrudConfig $config, ?array $oldValues = null): void
    {
        $siteId = $config->site_id;
        $moduleName = $config->module_name;
        $routePrefix = $config->route_prefix ?: $config->route_slug;
        $routePrefix = ltrim($routePrefix, '/');
        $routeSlug = $config->route_slug;

        // 权限路径使用前台业务路径（需要带 /u 前缀，但不包含 /admin/{adminPath}）
        // 权限中间件会自动去掉 /admin/{adminPath} 前缀进行匹配
        $basePath = "/u/{$routePrefix}";

        // 如果 route_slug 发生变化，需要先删除旧权限
        $oldRouteSlug = $oldValues['route_slug'] ?? null;
        if ($oldRouteSlug && $oldRouteSlug !== $routeSlug) {
            logger()->info('[CRUD权限同步] 检测到 route_slug 变化，删除旧权限', [
                'config_id' => $config->id,
                'old_route_slug' => $oldRouteSlug,
                'new_route_slug' => $routeSlug,
            ]);

            // 查找旧的父级权限
            $oldParentSlug = $this->buildCrudPermissionSlugFromRoute($oldRouteSlug);
            $oldParentPermission = AdminPermission::where('slug', $oldParentSlug)->first();

            if ($oldParentPermission) {
                // 删除所有子权限
                $oldChildPermissions = AdminPermission::where('parent_id', $oldParentPermission->id)->get();
                foreach ($oldChildPermissions as $child) {
                    try {
                        // 先解除角色关联
                        if ($child->roles()->exists()) {
                            $child->roles()->detach();
                        }
                        $this->permissionService->delete($child->id);
                        logger()->info('[CRUD权限同步] 删除旧子权限', [
                            'permission_id' => $child->id,
                            'slug' => $child->slug,
                        ]);
                    } catch (\Exception $e) {
                        logger()->warning('[CRUD权限同步] 删除旧子权限失败', [
                            'permission_id' => $child->id,
                            'slug' => $child->slug,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // 删除旧的父级权限
                try {
                    // 先解除角色关联
                    if ($oldParentPermission->roles()->exists()) {
                        $oldParentPermission->roles()->detach();
                    }
                    $this->permissionService->delete($oldParentPermission->id);
                    logger()->info('[CRUD权限同步] 删除旧父级权限', [
                        'permission_id' => $oldParentPermission->id,
                        'slug' => $oldParentSlug,
                    ]);
                } catch (\Exception $e) {
                    logger()->warning('[CRUD权限同步] 删除旧父级权限失败', [
                        'permission_id' => $oldParentPermission->id,
                        'slug' => $oldParentSlug,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // 获取功能开关
        $features = $config->options['features'] ?? [];
        $featureList = $features['search'] ?? $config->feature_search ?? true;
        $featureAdd = $features['add'] ?? $config->feature_add ?? true;
        $featureEdit = $features['edit'] ?? $config->feature_edit ?? true;
        $featureDelete = $features['delete'] ?? $config->feature_delete ?? true;
        $featureExport = $features['export'] ?? $config->feature_export ?? true;
        $featureSoftDelete = $features['soft_delete'] ?? $config->soft_delete ?? false;

        logger()->info('[CRUD权限同步] 开始同步权限', [
            'config_id' => $config->id,
            'site_id' => $siteId,
            'module_name' => $moduleName,
            'route_prefix' => $routePrefix,
            'route_slug' => $routeSlug,
            'base_path' => $basePath,
            'features' => [
                'list' => $featureList,
                'add' => $featureAdd,
                'edit' => $featureEdit,
                    'delete' => $featureDelete,
                    'export' => $featureExport,
                    'soft_delete' => $featureSoftDelete,
            ],
        ]);

        // 查找或创建父级权限（菜单类型）
        $parentSlug = $this->buildCrudPermissionSlug($config);
        $parentPermission = AdminPermission::where('slug', $parentSlug)->first();

        if (!$parentPermission) {
            $parentPermission = $this->permissionService->create([
                'site_id' => null, // 权限全局共享，不绑定站点
                'parent_id' => 0,
                'name' => $moduleName,
                'slug' => $parentSlug,
                'type' => 'menu',
                'icon' => $config->icon ?? 'bi bi-table',
                'path' => $basePath,
                'method' => '*',
                'component' => null,
                'description' => "{$moduleName}管理权限组",
                'status' => 1,
                'sort' => 100,
            ]);

            logger()->info('[CRUD权限同步] 创建父级权限', [
                'permission_id' => $parentPermission->id,
                'slug' => $parentSlug,
            ]);
        } else {
            // 更新父级权限信息
            $updateData = [];
            if ($parentPermission->name !== $moduleName) {
                $updateData['name'] = $moduleName;
            }
            if ($parentPermission->path !== $basePath) {
                $updateData['path'] = $basePath;
            }
            if ($config->icon && $parentPermission->icon !== $config->icon) {
                $updateData['icon'] = $config->icon;
            }

            if (!empty($updateData)) {
                $this->permissionService->update($parentPermission->id, $updateData);
                logger()->info('[CRUD权限同步] 更新父级权限', [
                    'permission_id' => $parentPermission->id,
                    'updated_fields' => array_keys($updateData),
                ]);
            }
        }

        $parentId = $parentPermission->id;
        $sort = 0;

        // 定义需要创建的权限列表
        $permissions = [];

        // 列表权限
        if ($featureList) {
            $permissions[] = [
                'slug' => "{$parentSlug}.list",
                'name' => "{$moduleName}列表",
                'path' => $basePath,
                'method' => 'GET',
                'description' => "查看{$moduleName}列表",
                'sort' => $sort++,
            ];
        }

        // 创建权限
        if ($featureAdd) {
            $permissions[] = [
                'slug' => "{$parentSlug}.create",
                'name' => "{$moduleName}创建",
                'path' => "{$basePath}/create",
                'method' => 'GET',
                'description' => "访问{$moduleName}创建页面",
                'sort' => $sort++,
            ];
            $permissions[] = [
                'slug' => "{$parentSlug}.store",
                'name' => "{$moduleName}保存",
                'path' => $basePath,
                'method' => 'POST',
                'description' => "保存{$moduleName}数据",
                'sort' => $sort++,
            ];
        }

        // 编辑权限
        if ($featureEdit) {
            $permissions[] = [
                'slug' => "{$parentSlug}.edit",
                'name' => "{$moduleName}编辑",
                'path' => "{$basePath}/*/edit",
                'method' => 'GET',
                'description' => "访问{$moduleName}编辑页面",
                'sort' => $sort++,
            ];
            $permissions[] = [
                'slug' => "{$parentSlug}.update",
                'name' => "{$moduleName}更新",
                'path' => "{$basePath}/*",
                'method' => 'PUT',
                'description' => "更新{$moduleName}数据",
                'sort' => $sort++,
            ];
        }

        // 删除权限
        if ($featureDelete) {
            $permissions[] = [
                'slug' => "{$parentSlug}.delete",
                'name' => "{$moduleName}删除",
                'path' => "{$basePath}/*",
                'method' => 'DELETE',
                'description' => "删除{$moduleName}数据",
                'sort' => $sort++,
            ];
        }

        // 导出权限
        if ($featureExport) {
            $permissions[] = [
                'slug' => "{$parentSlug}.export",
                'name' => "{$moduleName}导出",
                'path' => "{$basePath}/export",
                'method' => 'GET',
                'description' => "导出{$moduleName}数据",
                'sort' => $sort++,
            ];
        }

        // 回收站相关权限（需要启用软删除）
        if ($featureSoftDelete) {
            $permissions[] = [
                'slug' => "{$parentSlug}.trash",
                'name' => "{$moduleName}回收站",
                'path' => "{$basePath}/trash",
                'method' => 'GET',
                'description' => "查看{$moduleName}回收站",
                'sort' => $sort++,
            ];
            $permissions[] = [
                'slug' => "{$parentSlug}.restore",
                'name' => "{$moduleName}恢复",
                'path' => "{$basePath}/*/restore",
                'method' => 'POST',
                'description' => "恢复{$moduleName}记录",
                'sort' => $sort++,
            ];
            $permissions[] = [
                'slug' => "{$parentSlug}.force_delete",
                'name' => "{$moduleName}永久删除",
                'path' => "{$basePath}/*/force-delete",
                'method' => 'DELETE',
                'description' => "永久删除{$moduleName}记录",
                'sort' => $sort++,
            ];
            $permissions[] = [
                'slug' => "{$parentSlug}.batch_restore",
                'name' => "{$moduleName}批量恢复",
                'path' => "{$basePath}/batch-restore",
                'method' => 'POST',
                'description' => "批量恢复{$moduleName}记录",
                'sort' => $sort++,
            ];
            $permissions[] = [
                'slug' => "{$parentSlug}.batch_force_delete",
                'name' => "{$moduleName}批量永久删除",
                'path' => "{$basePath}/batch-force-delete",
                'method' => 'POST',
                'description' => "批量永久删除{$moduleName}记录",
                'sort' => $sort++,
            ];
            $permissions[] = [
                'slug' => "{$parentSlug}.clear_trash",
                'name' => "{$moduleName}清空回收站",
                'path' => "{$basePath}/clear-trash",
                'method' => 'POST',
                'description' => "清空{$moduleName}回收站",
                'sort' => $sort++,
            ];
        }

        // 创建或更新每个权限
        foreach ($permissions as $permData) {
            $existing = AdminPermission::where('slug', $permData['slug'])->first();

            if ($existing) {
                // 更新现有权限
                $updateData = [
                    'name' => $permData['name'],
                    'path' => $permData['path'],
                    'method' => $permData['method'],
                    'description' => $permData['description'],
                    'sort' => $permData['sort'],
                ];

                // 如果父级ID变化，更新
                if ($existing->parent_id !== $parentId) {
                    $updateData['parent_id'] = $parentId;
                }

                $this->permissionService->update($existing->id, $updateData);

                logger()->info('[CRUD权限同步] 更新权限', [
                    'permission_id' => $existing->id,
                    'slug' => $permData['slug'],
                ]);
            } else {
                // 创建新权限
                $this->permissionService->create([
                    'site_id' => null, // 权限全局共享
                    'parent_id' => $parentId,
                    'name' => $permData['name'],
                    'slug' => $permData['slug'],
                    'type' => 'button',
                    'icon' => null,
                    'path' => $permData['path'],
                    'method' => $permData['method'],
                    'component' => null,
                    'description' => $permData['description'],
                    'status' => 1,
                    'sort' => $permData['sort'],
                ]);

                logger()->info('[CRUD权限同步] 创建权限', [
                    'slug' => $permData['slug'],
                ]);
            }
        }

        // 删除不再需要的权限（如果功能被关闭）
        $allChildPermissions = AdminPermission::where('parent_id', $parentId)->get();
        $requiredSlugs = array_column($permissions, 'slug');

        foreach ($allChildPermissions as $child) {
            if (!in_array($child->slug, $requiredSlugs)) {
                try {
                    $this->permissionService->delete($child->id);
                    logger()->info('[CRUD权限同步] 删除不需要的权限', [
                        'permission_id' => $child->id,
                        'slug' => $child->slug,
                    ]);
                } catch (\Exception $e) {
                    logger()->warning('[CRUD权限同步] 删除权限失败', [
                        'permission_id' => $child->id,
                        'slug' => $child->slug,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        logger()->info('[CRUD权限同步] 权限同步完成', [
            'config_id' => $config->id,
            'parent_permission_id' => $parentId,
            'permissions_count' => count($permissions),
        ]);
    }

    /**
     * 生成 CRUD 对应的权限标识（父级）
     */
    protected function buildCrudPermissionSlug(AdminCrudConfig $config): string
    {
        return $this->buildCrudPermissionSlugFromRoute($config->route_slug);
    }

    /**
     * 根据给定的 route slug 生成权限标识
     */
    protected function buildCrudPermissionSlugFromRoute(?string $routeSlug): string
    {
        $slug = trim((string) $routeSlug);
        if ($slug === '') {
            return 'crud';
        }

        return "crud.{$slug}";
    }

    /**
     * 删除 CRUD 配置对应的权限
     *
     * @param AdminCrudConfig $config CRUD 配置
     * @throws \Exception
     */
    protected function deletePermissionsForConfig(AdminCrudConfig $config): void
    {
        $routeSlug = $config->route_slug;
        $parentSlug = "crud.{$routeSlug}";

        logger()->info('[CRUD权限删除] 开始删除权限', [
            'config_id' => $config->id,
            'route_slug' => $routeSlug,
            'parent_slug' => $parentSlug,
        ]);

        // 查找父级权限
        $parentPermission = AdminPermission::where('slug', $parentSlug)->first();

        if (!$parentPermission) {
            logger()->info('[CRUD权限删除] 未找到父级权限，无需删除', [
                'parent_slug' => $parentSlug,
            ]);
            return;
        }

        // 查找所有子权限
        $childPermissions = AdminPermission::where('parent_id', $parentPermission->id)->get();

        // 删除所有子权限（先删除子权限，再删除父权限）
        $deletedCount = 0;
        foreach ($childPermissions as $child) {
            try {
                // 检查是否被角色使用
                if ($child->roles()->exists()) {
                    // 先解除角色关联
                    $child->roles()->detach();
                    logger()->info('[CRUD权限删除] 解除子权限的角色关联', [
                        'permission_id' => $child->id,
                        'slug' => $child->slug,
                    ]);
                }

                $this->permissionService->delete($child->id);
                $deletedCount++;
                logger()->info('[CRUD权限删除] 删除子权限', [
                    'permission_id' => $child->id,
                    'slug' => $child->slug,
                ]);
            } catch (\Exception $e) {
                logger()->warning('[CRUD权限删除] 删除子权限失败', [
                    'permission_id' => $child->id,
                    'slug' => $child->slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 删除父级权限
        try {
            // 检查是否被角色使用
            if ($parentPermission->roles()->exists()) {
                // 先解除角色关联
                $parentPermission->roles()->detach();
                logger()->info('[CRUD权限删除] 解除父级权限的角色关联', [
                    'permission_id' => $parentPermission->id,
                    'slug' => $parentSlug,
                ]);
            }

            $this->permissionService->delete($parentPermission->id);
            $deletedCount++;
            logger()->info('[CRUD权限删除] 删除父级权限', [
                'permission_id' => $parentPermission->id,
                'slug' => $parentSlug,
            ]);
        } catch (\Exception $e) {
            logger()->warning('[CRUD权限删除] 删除父级权限失败', [
                'permission_id' => $parentPermission->id,
                'slug' => $parentSlug,
                'error' => $e->getMessage(),
            ]);
        }

        logger()->info('[CRUD权限删除] 权限删除完成', [
            'config_id' => $config->id,
            'total_permissions' => $childPermissions->count() + 1,
            'deleted_count' => $deletedCount,
        ]);
    }
}

