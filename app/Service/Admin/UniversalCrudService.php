<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Model\Admin\AdminCrudConfig;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;

/**
 * 通用 CRUD 服务
 *
 * 负责动态处理模型的增删改查操作
 *
 * @package App\Service\Admin
 */
class UniversalCrudService
{
    #[Inject]
    protected ConfigInterface $config;

    #[Inject]
    protected ContainerInterface $container;

    #[Inject]
    protected CrudService $crudService;

    /**
     * 缓存“连接.表”是否包含 site_id 列，避免重复查询信息架构
     *
     * @var array<string, bool>
     */
    private array $tableSiteIdColumnCache = [];

    /**
     * 从数据库获取 CRUD 配置
     *
     * 查询优先级：
     * 1. 按 model_name 精确匹配（最优先，推荐使用）
     * 2. 按 ID 查询（如果是数字）
     * 3. 按 route_slug 精确匹配（向后兼容）
     * 4. 按 table_name 精确匹配（将路由参数转换为表名后查询，向后兼容）
     *
     * @param string $modelIdentifier 可以是 model_name、id、route_slug 或 table_name
     * @return AdminCrudConfig|null
     */
    protected function getCrudConfigFromDatabase(string $modelIdentifier): ?AdminCrudConfig
    {
        $siteId = site_id();
        $query = AdminCrudConfig::query()
            ->where('route_slug', $modelIdentifier)
            ->where('status', AdminCrudConfig::STATUS_GENERATED);
        if (!is_super_admin()){
            $query->where('site_id', $siteId);
        }
        $config = $query->first();
        if (!empty($config['fields_config']) && is_array($config['fields_config'])) {
            $config['fields_config'] = array_values(array_filter(
                $config['fields_config'],
                fn($fieldConfig) => (bool) ($fieldConfig['show_in_list'] ?? $fieldConfig['list_default'] ?? true)
            ));
        }
        return $config;
    }

    /**
     * 检查模型是否允许访问
     */
    public function isAllowedModel(string $model): bool
    {
        // 首先检查是否在数据库中有配置
        $crudConfig = $this->getCrudConfigFromDatabase($model);
        if ($crudConfig) {
            return true;
        }

        // 如果没有，检查配置文件（向后兼容）
        $allowedModels = $this->config->get('universal_crud.models', []);
        return isset($allowedModels[$model]);
    }

    /**
     * 获取模型配置
     *
     * 优先从数据库的 admin_crud_configs 表读取
     * 如果没有，则从配置文件读取（向后兼容）
     */
    public function getModelConfig(string $model): array
    {
        // 优先从数据库获取配置
        $crudConfig = $this->getCrudConfigFromDatabase($model);

        if ($crudConfig) {
            // 将数据库配置转换为标准格式
            return $this->convertCrudConfigToArray($crudConfig);
        }

        // 如果没有数据库配置，从配置文件读取（向后兼容）
        $config = $this->config->get("universal_crud.models.{$model}", []);

        // 如果没有配置，尝试自动生成基础配置
        if (empty($config)) {
            $config = $this->generateAutoConfig($model);
        }

        return $config;
    }

    /**
     * 将 AdminCrudConfig 模型转换为配置数组
     */
    protected function convertCrudConfigToArray(AdminCrudConfig $crudConfig): array
    {
        $fieldsConfig = $crudConfig->fields_config ?? [];
        $options = $crudConfig->options ?? [];

        // 检查是否有软删除：优先从独立字段读取，如果没有则从字段配置中检测
        $hasSoftDelete = false;
        if (isset($crudConfig->soft_delete)) {
            $hasSoftDelete = (bool)$crudConfig->soft_delete;
        } elseif (!empty($fieldsConfig)) {
            foreach ($fieldsConfig as $field) {
                if (isset($field['name']) && $field['name'] === 'deleted_at') {
                    $hasSoftDelete = true;
                    break;
                }
            }
        }

        // 提取搜索字段配置
        $searchConfig = $this->extractSearchFieldsConfig($fieldsConfig);

        // 功能开关配置：优先使用独立的 feature_* 字段，其次回退到 options['features']
        $featureColumns = [
            'search' => $crudConfig->feature_search,
            'add'    => $crudConfig->feature_add,
            'edit'   => $crudConfig->feature_edit,
            'delete' => $crudConfig->feature_delete,
            'export' => $crudConfig->feature_export,
        ];
        $hasFeatureColumns = array_filter($featureColumns, static fn ($value) => $value !== null);
        $featuresSource = $hasFeatureColumns
            ? array_map(static fn ($value) => (bool) $value, $featureColumns)
            : ($options['features'] ?? null);

        // 规范化功能开关：确保所有键都存在，并转换为布尔值
        $featureDefaults = [
            'search' => true,
            'add'    => true,
            'edit'   => true,
            'delete' => true,
            'export' => true,
            'soft_delete' => false, // 回收站功能默认关闭
        ];
        if (is_array($featuresSource) && !empty($featuresSource)) {
            foreach ($featureDefaults as $key => $defaultValue) {
                if (array_key_exists($key, $featuresSource)) {
                    $featureDefaults[$key] = filter_var($featuresSource[$key], FILTER_VALIDATE_BOOLEAN);
                }
            }
        }
        
        // 处理 soft_delete：优先从 features 配置读取，其次使用检测到的值
        if (isset($options['features']['soft_delete'])) {
            $featureDefaults['soft_delete'] = filter_var($options['features']['soft_delete'], FILTER_VALIDATE_BOOLEAN);
        } elseif (isset($featuresSource['soft_delete'])) {
            $featureDefaults['soft_delete'] = filter_var($featuresSource['soft_delete'], FILTER_VALIDATE_BOOLEAN);
        } else {
            // 如果没有在 features 中配置，使用检测到的值
            $featureDefaults['soft_delete'] = $hasSoftDelete;
        }
        
        $features = $featureDefaults;

        return [
            'table' => $crudConfig->table_name,
            'db_connection' => $crudConfig->db_connection ?? 'default',
            'model_class' => $crudConfig->model_name,
            'title' => $crudConfig->module_name,
            'route_slug' => $crudConfig->route_slug,
            'timestamps' => true, // 默认启用时间戳
            'soft_delete' => $hasSoftDelete,
            'has_site_id' => true, // 通过 CRUD 生成器生成的表都有 site_id
            'search_fields' => $searchConfig['search_fields'],
            'search_fields_config' => $searchConfig['search_fields_config'],
            'default_sort_field' => 'id',
            'default_sort_order' => 'desc',
            'columns' => $this->extractColumns($fieldsConfig),
            'fields' => $this->extractFormFields($fieldsConfig),
            'fillable' => $this->extractFillableFields($fieldsConfig),
            'validation' => $this->extractValidationRules($fieldsConfig),
            'relations' => $this->extractRelations($fieldsConfig),
            'features' => $features,
            // 存储原始配置供扩展使用
            'fields_config' => $fieldsConfig,
            'options' => $options,
        ];
    }

    /**
     * 提取搜索字段（仅返回字段名列表）
     * 
     * 注意：此方法完全依赖前端传递的搜索配置，不再做任何自动判断
     * 前端在 config.blade.php 中已经实现了 guessSearchable() 函数
     * 搜索配置使用 search[enabled] 或 searchable 字段
     */
    protected function extractSearchFields(array $fieldsConfig): array
    {
        $searchFields = [];

        foreach ($fieldsConfig as $field) {
            if (empty($field['name'])) {
                continue;
            }

            // 完全依赖前端配置：检查 search[enabled] 或 searchable（向后兼容）
            $searchConfig = $field['search'] ?? [];
            $searchEnabled = false;
            
            // 优先使用新的 search[enabled] 配置
            if (isset($searchConfig['enabled'])) {
                $searchEnabled = filter_var($searchConfig['enabled'], FILTER_VALIDATE_BOOLEAN);
            }
            // 向后兼容：如果没有 search[enabled]，检查旧的 searchable 字段
            elseif (isset($field['searchable'])) {
                $searchEnabled = filter_var($field['searchable'], FILTER_VALIDATE_BOOLEAN);
            }
            
            // 如果前端没有明确标记为可搜索，则跳过（不再做自动判断）
            if ($searchEnabled) {
                $searchFields[] = $field['name'];
            }
        }

        return $searchFields;
    }

    /**
     * 提取搜索字段配置（返回完整的搜索配置，包括字段类型、选项、占位符等）
     * 
     * 注意：此方法完全依赖前端传递的搜索配置，不再做任何自动判断
     * 前端在 config.blade.php 中已经实现了 guessSearchable() 和 inferDefaultSearchType() 等函数
     * 搜索配置使用 search[enabled] 和 search[type] 等字段
     */
    protected function extractSearchFieldsConfig(array $fieldsConfig): array
    {
        $searchFields = [];
        $searchFieldsConfig = [];

        foreach ($fieldsConfig as $field) {
            if (empty($field['name'])) {
                continue;
            }

            // 完全依赖前端配置：检查 search[enabled] 或 searchable（向后兼容）
            $searchConfig = $field['search'] ?? [];
            $searchEnabled = false;
            
            // 优先使用新的 search[enabled] 配置
            if (isset($searchConfig['enabled'])) {
                $searchEnabled = filter_var($searchConfig['enabled'], FILTER_VALIDATE_BOOLEAN);
            }
            // 向后兼容：如果没有 search[enabled]，检查旧的 searchable 字段
            elseif (isset($field['searchable'])) {
                $searchEnabled = filter_var($field['searchable'], FILTER_VALIDATE_BOOLEAN);
            }
            
            // 如果前端没有明确标记为可搜索，则跳过（不再做自动判断）
            if (!$searchEnabled) {
                continue;
            }

            // 字段已明确标记为可搜索，提取搜索配置
            $fieldName = $field['name'];
            $fieldLabel = $field['search_label'] ?? $field['field_name'] ?? $field['label'] ?? $fieldName;
            
            // 完全依赖前端配置的搜索类型：使用 search[type] 或 search_type
            $searchType = $searchConfig['type'] ?? $field['search_type'] ?? 'like';
            
            // 如果前端没有配置搜索类型，使用默认值 'like'（不再做自动推断）
            if (empty($searchType)) {
                $searchType = 'like';
            }

            // 构建搜索字段配置
            $searchFieldConfig = [
                'name' => $fieldName,
                'label' => $fieldLabel,
                'type' => $searchType,
            ];

            // 添加占位符（从 search[placeholder] 或 search_placeholder 读取）
            $placeholder = $searchConfig['placeholder'] ?? $field['search_placeholder'] ?? null;
            if ($placeholder) {
                $searchFieldConfig['placeholder'] = $placeholder;
            } elseif ($searchType === 'like' || $searchType === 'exact') {
                // 文本类型默认占位符
                $searchFieldConfig['placeholder'] = '搜索' . $fieldLabel;
            }

            // 添加选项（用于 select 类型，从 search[options] 或 options 读取）
            if ($searchType === 'select') {
                $searchOptions = $searchConfig['options'] ?? $field['search_options'] ?? $field['options'] ?? null;
                if ($searchOptions !== null) {
                    // 确保第一个选项是"全部"
                    if (is_array($searchOptions) && !isset($searchOptions[''])) {
                        $searchFieldConfig['options'] = array_merge(['' => '全部'], $searchOptions);
                    } else {
                        $searchFieldConfig['options'] = $searchOptions;
                    }
                }
            }

            // 关联搜索配置（从 search[relation] 或 relation 读取）
            if ($searchType === 'relation') {
                $relationConfig = $searchConfig['relation'] ?? $field['relation'] ?? null;
                if ($relationConfig) {
                    $searchFieldConfig['relation'] = $relationConfig;
                }
            }

            // 标记虚拟字段
            if (isset($field['is_virtual']) && $field['is_virtual']) {
                $searchFieldConfig['is_virtual'] = true;
            }

            $searchFields[] = $fieldName;
            $searchFieldsConfig[] = $searchFieldConfig;
        }

        return [
            'search_fields' => $searchFields,
            'search_fields_config' => $searchFieldsConfig,
        ];
    }

    /**
     * 提取列配置（用于列表显示）
     */
    protected function extractColumns(array $fieldsConfig): array
    {
        $columns = [];

        foreach ($fieldsConfig as $field) {
            if (empty($field['name'])) {
                continue;
            }

            // 使用 field_name，如果没有则从 comment 中提取
            $label = $field['field_name'] ?? $field['label'] ?? '';
            if (empty($label) && !empty($field['comment'])) {
                $comment = $field['comment'];
                if (str_contains($comment, '：')) {
                    $label = explode('：', $comment)[0];
                } elseif (str_contains($comment, ':')) {
                    $label = explode(':', $comment)[0];
                } else {
                    $label = $comment;
                }
            }
            if (empty($label)) {
                $label = $field['name'];
            }

            // 处理列显示逻辑：优先使用新字段，兼容旧字段
            $listable = $field['listable'] ?? $field['show_in_list'] ?? true;
            $listDefault = $field['list_default'] ?? $field['show_in_list'] ?? true;

            // 处理排序配置：优先使用 sortable，默认 false
            $sortable = $field['sortable'] ?? false;
            $sortable = filter_var($sortable, FILTER_VALIDATE_BOOLEAN);

            $columnData = [
                'name' => $field['name'],
                'label' => $label,
                'type' => $field['db_type'] ?? 'string',
                'form_type' => $field['form_type'] ?? null,  // 保存表单类型，用于列类型判断
                'comment' => $field['comment'] ?? '',
                'listable' => $listable,           // 是否可以在列表中显示
                'list_default' => $listDefault,     // 是否默认显示
                'list_show' => $listDefault,        // 向后兼容：list_show 等同于 list_default
                'sortable' => $sortable,            // 是否支持排序
            ];

            // 如果字段有 options 配置，也要包含进去（用于 badgeMap 构建）
            if (isset($field['options']) && !empty($field['options'])) {
                $columnData['options'] = $field['options'];
            }

            // 如果是 relation 类型，保存关联配置信息
            if (($field['form_type'] ?? '') === 'relation' || isset($field['relation_table'])) {
                $columnData['relation_table'] = $field['relation_table'] ?? '';
                $columnData['relation_label_field'] = $field['relation_label_field'] ?? $field['relation_display_field'] ?? 'name';
                $columnData['relation_value_field'] = $field['relation_value_field'] ?? 'id';
                $columnData['relation_multiple'] = str_ends_with($field['name'], '_ids') || 
                                                   ($field['relation_multiple'] ?? false) ||
                                                   ($field['model_type'] ?? '') === 'array';
            }

            $columns[] = $columnData;
        }

        return $columns;
    }

    /**
     * 提取表单字段配置
     */
    protected function extractFormFields(array $fieldsConfig): array
    {
        $fields = [];

        foreach ($fieldsConfig as $field) {
            if (empty($field['name'])) {
                continue;
            }

            $fieldName = $field['name'];
            $formType = $field['form_type'] ?? $this->guessFormFieldTypeFromDbType($field['db_type'] ?? 'string');
            $isSiteSelector = $fieldName === 'site_id'
                && $formType === 'site_select'
                && is_super_admin();

            // 非超级管理员：完全移除 site_id 字段，防止在前端表单中渲染
            if (! is_super_admin() && $fieldName === 'site_id') {
                continue;
            }

            // 跳过不需要在表单中显示的字段（站点选择类型除外）
            $skipFields = ['id', 'site_id', 'created_at', 'updated_at', 'deleted_at'];
            if (!$isSiteSelector && in_array($fieldName, $skipFields, true)) {
                continue;
            }

            // 使用 field_name，如果没有则从 comment 中提取
            $label = $field['field_name'] ?? $field['label'] ?? '';
            if (empty($label) && !empty($field['comment'])) {
                $comment = $field['comment'];
                if (str_contains($comment, '：')) {
                    $label = explode('：', $comment)[0];
                } elseif (str_contains($comment, ':')) {
                    $label = explode(':', $comment)[0];
                } else {
                    $label = $comment;
                }
            }
            if (empty($label)) {
                $label = $fieldName;
            }
            
            // 规范化 editable 字段值：统一转换为布尔值或 null
            $editable = $field['editable'] ?? null;
            if ($editable !== null && $editable !== '') {
                // 如果 editable 存在且不为空字符串，转换为布尔值以便后续比较
                if (is_string($editable)) {
                    $editable = filter_var($editable, FILTER_VALIDATE_BOOLEAN);
                } elseif (is_numeric($editable)) {
                    $editable = (bool) $editable;
                } else {
                    $editable = (bool) $editable;
                }
            } else {
                // 如果 editable 是 null 或空字符串，统一设置为 null
                $editable = null;
            }
            
            // 处理默认值：从 default_value 映射到 default，并过滤 "NULL" 字符串
            $defaultValue = $field['default_value'] ?? $field['default'] ?? null;
            // 如果默认值是 "NULL" 字符串，则设置为 null（不填充）
            if ($defaultValue === 'NULL' || $defaultValue === 'null') {
                $defaultValue = null;
            }
            if ($isSiteSelector && $defaultValue === null && site_id() !== null) {
                $defaultValue = (string) site_id();
            }
            
            $fieldConfig = [
                'name' => $fieldName,
                'label' => $label,
                'type' => $formType,
                'required' => !($field['nullable'] ?? false),
                'comment' => $field['comment'] ?? '',
                'default' => $defaultValue,
                'options' => $field['options'] ?? null,
                'editable' => $editable, // 规范化后的 editable 值，用于编辑页面过滤
                // 通用属性
                'placeholder' => $field['placeholder'] ?? null,
                'help' => $field['help'] ?? null,
                'disabled' => $field['disabled'] ?? false,
                'readonly' => $field['readonly'] ?? false,
                'col' => $field['col'] ?? null, // 列宽配置，用于前端表单布局
            ];
            
            // 保留 AI 配置（仅当启用时），传递到前端供渲染器使用
            if (isset($field['ai']) && is_array($field['ai'])) {
                $enabled = $field['ai']['enabled'] ?? null;
                $enabledBool = filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($enabledBool) {
                    $fieldConfig['ai'] = $field['ai'];
                }
            }
            
            // 类型特定属性
            // textarea 和 rich_text 类型：rows
            if (in_array($formType, ['textarea', 'rich_text'])) {
                if (isset($field['rows'])) {
                    $fieldConfig['rows'] = (int)$field['rows'];
                }
            }
            
            // number 类型：min, max, step
            if ($formType === 'number') {
                if (isset($field['min'])) {
                    $fieldConfig['min'] = is_numeric($field['min']) ? (float)$field['min'] : null;
                }
                if (isset($field['max'])) {
                    $fieldConfig['max'] = is_numeric($field['max']) ? (float)$field['max'] : null;
                }
                if (isset($field['step'])) {
                    $fieldConfig['step'] = is_numeric($field['step']) ? (float)$field['step'] : null;
                } elseif (isset($field['number_step'])) {
                    // 兼容旧的 number_step 字段
                    $fieldConfig['step'] = is_numeric($field['number_step']) ? (float)$field['number_step'] : null;
                }
            }
            
            // 如果是 relation 类型，添加 relation 配置
            if ($formType === 'relation') {
                $fieldConfig['relation'] = [
                    'table' => $field['relation_table'] ?? '',
                    'label_field' => $field['relation_label_field'] ?? $field['relation_display_field'] ?? 'name',
                    'value_field' => $field['relation_value_field'] ?? 'id',
                    'has_site_id' => $field['relation_has_site_id'] ?? true,
                    'multiple' => str_ends_with($field['name'], '_ids') || 
                                  ($field['relation_multiple'] ?? false) ||
                                  ($field['model_type'] ?? '') === 'array',
                ];
            }
            
            // 如果是 switch 类型，提取 onLabel、offLabel、onValue、offValue
            if ($formType === 'switch') {
                // 优先使用字段配置中直接指定的值
                if (isset($field['onValue']) || isset($field['offValue']) || isset($field['onLabel']) || isset($field['offLabel'])) {
                    $fieldConfig['onValue'] = $field['onValue'] ?? '1';
                    $fieldConfig['offValue'] = $field['offValue'] ?? '0';
                    $fieldConfig['onLabel'] = $field['onLabel'] ?? null;
                    $fieldConfig['offLabel'] = $field['offLabel'] ?? null;
                } elseif (!empty($field['options']) && is_array($field['options'])) {
                    // 如果字段配置中没有直接指定，从 options 中提取
                    // options 格式：['1' => '开启', '0' => '关闭'] 或 ['开启' => '1', '关闭' => '0']
                    $options = $field['options'];
                    $optionKeys = array_keys($options);
                    $optionValues = array_values($options);
                    
                    // 如果 options 只有两个选项，提取第一个和第二个
                    if (count($options) === 2) {
                        // 尝试判断哪个是开启值（通常 '1' 或第一个）
                        $firstKey = $optionKeys[0];
                        $secondKey = $optionKeys[1];
                        $firstValue = $optionValues[0];
                        $secondValue = $optionValues[1];
                        
                        // 如果第一个键是数字字符串且为 1，或者第一个值更"积极"（开启、是、启用等）
                        if (is_numeric($firstKey) && (int)$firstKey === 1) {
                            $fieldConfig['onValue'] = (string)$firstKey;
                            $fieldConfig['offValue'] = (string)$secondKey;
                            $fieldConfig['onLabel'] = $firstValue;
                            $fieldConfig['offLabel'] = $secondValue;
                        } elseif (is_numeric($secondKey) && (int)$secondKey === 1) {
                            $fieldConfig['onValue'] = (string)$secondKey;
                            $fieldConfig['offValue'] = (string)$firstKey;
                            $fieldConfig['onLabel'] = $secondValue;
                            $fieldConfig['offLabel'] = $firstValue;
                        } else {
                            // 默认第一个为开启，第二个为关闭
                            $fieldConfig['onValue'] = (string)$firstKey;
                            $fieldConfig['offValue'] = (string)$secondKey;
                            $fieldConfig['onLabel'] = $firstValue;
                            $fieldConfig['offLabel'] = $secondValue;
                        }
                    }
                }
            }
            
            if ($isSiteSelector) {
                if (empty($fieldConfig['col'])) {
                    $fieldConfig['col'] = 'col-12';
                }
                if (empty($fieldConfig['placeholder'])) {
                    $fieldConfig['placeholder'] = '输入站点名称或域名搜索';
                }
                if (empty($fieldConfig['help'])) {
                    $fieldConfig['help'] = '仅超级管理员可选择数据所属站点';
                }
            }

            $fields[] = $fieldConfig;
        }

        return $fields;
    }

    /**
     * 提取可填充字段
     */
    protected function extractFillableFields(array $fieldsConfig): array
    {
        $fillable = [];

        foreach ($fieldsConfig as $field) {
            if (empty($field['name'])) {
                continue;
            }

            // 排除不可填充的字段
            $skipFields = ['id', 'created_at', 'updated_at', 'deleted_at'];
            if (!in_array($field['name'], $skipFields)) {
                $fillable[] = $field['name'];
            }
        }

        return $fillable;
    }

    /**
     * 提取验证规则
     */
    protected function extractValidationRules(array $fieldsConfig): array
    {
        $rules = [
            'create' => [],
            'update' => [],
        ];

        foreach ($fieldsConfig as $field) {
            if (empty($field['name'])) {
                continue;
            }

            $fieldName = $field['name'];
            $formType = $field['form_type'] ?? '';
            $isSiteSelector = $fieldName === 'site_id'
                && $formType === 'site_select'
                && is_super_admin();

            // 非超级管理员：不输出 site_id 的验证规则，防止前端携带该字段
            if (! is_super_admin() && $fieldName === 'site_id') {
                continue;
            }

            // 跳过不需要验证的字段（站点选择类型除外）
            $skipFields = ['id', 'site_id', 'created_at', 'updated_at', 'deleted_at'];
            if (!$isSiteSelector && in_array($fieldName, $skipFields, true)) {
                continue;
            }

            $fieldRules = [];

            // 必填
            if (!($field['nullable'] ?? false)) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // 类型验证
            $dbType = $field['db_type'] ?? '';
            if (in_array($dbType, ['int', 'integer', 'bigint', 'tinyint', 'smallint'])) {
                $fieldRules[] = 'integer';
            } elseif (in_array($dbType, ['decimal', 'float', 'double'])) {
                $fieldRules[] = 'numeric';
            } elseif (str_contains($field['name'], 'email')) {
                $fieldRules[] = 'email';
            }

            // 唯一性验证
            if ($field['unique'] ?? false) {
                $fieldRules[] = 'unique:' . ($field['table_name'] ?? '');
            }

            // 长度限制
            if (isset($field['length']) && $field['length'] > 0) {
                $fieldRules[] = 'max:' . $field['length'];
            }

            if ($isSiteSelector) {
                $fieldRules[] = 'exists:admin_sites,id';
            }

            if (!empty($fieldRules)) {
                $rules['create'][$fieldName] = implode('|', array_unique($fieldRules));
                $rules['update'][$fieldName] = implode('|', array_unique($fieldRules));
            }
        }

        return $rules;
    }

    /**
     * 提取关联关系配置
     */
    protected function extractRelations(array $fieldsConfig): array
    {
        $relations = [];

        foreach ($fieldsConfig as $field) {
            if (empty($field['name'])) {
                continue;
            }

            // 检查是否是关联类型字段：
            // - 标准关联选择：form_type 为 relation
            // - 站点选择：form_type 为 site_select（与关联选择在列表展示中保持一致）
            $formType = $field['form_type'] ?? '';
            $isRelationType = in_array($formType, ['relation', 'site_select'], true);
            
            // 获取关联表名（支持两种格式：relation['table'] 或 relation_table）
            $relationTable = null;
            if (isset($field['relation']['table']) && !empty($field['relation']['table'])) {
                // 新格式：嵌套的 relation 数组
                $relationTable = $field['relation']['table'];
            } elseif (isset($field['relation_table']) && !empty($field['relation_table'])) {
                // 旧格式：平铺的 relation_table 字段（向后兼容）
                $relationTable = $field['relation_table'];
            }
            
            // 检查是否是 JSON 字段（model_type 为 array 或 json）
            $isJsonField = in_array($field['model_type'] ?? '', ['array', 'json'], true);
            
            // 只有当 form_type 明确为 'relation' 或 'site_select' 时才进行关联查询
            // 如果 form_type 是其他类型（如 number），即使有 relation_table 配置，也不进行关联查询
            if ($isRelationType) {
                $fieldName = $field['name'];

                // 站点选择：固定映射到 admin_sites 表，使用 name 作为展示字段
                if ($formType === 'site_select') {
                    $relationTable = 'admin_sites';
                    $isJsonField = false;
                }
                // 如果没有配置关联表，跳过（避免错误）
                if (empty($relationTable)) {
                    logger()->warning('[UniversalCrudService] 字段配置为 relation 类型但缺少 relation_table', [
                        'field' => $fieldName,
                        'form_type' => $field['form_type'] ?? null,
                    ]);
                    continue;
                }
                
                // 判断是否多选（字段名以 _ids 结尾，或配置中明确指定）
                $isMultiple = str_ends_with($fieldName, '_ids') || 
                              ($field['relation']['multiple'] ?? false) ||
                              ($field['relation_multiple'] ?? false) ||
                              $isJsonField;
                
                // 获取关联配置（优先使用嵌套格式，否则使用平铺格式）
                $relationConfig = $field['relation'] ?? [];

                // 站点选择：站点本身是全局表，不需要 site_id 过滤
                $hasSiteId = $relationConfig['has_site_id'] ??
                             $field['relation_has_site_id'] ??
                             true;
                if ($formType === 'site_select') {
                    $hasSiteId = false;
                }

                $relations[$fieldName] = [
                    'table' => $relationTable ?? '',
                    'label_field' => $relationConfig['label_column'] ?? 
                                     $relationConfig['label_field'] ?? 
                                     $field['relation_label_field'] ?? 
                                     $field['relation_display_field'] ?? 
                                     'name',
                    'value_field' => $relationConfig['value_column'] ?? 
                                    $relationConfig['value_field'] ?? 
                                    $field['relation_value_field'] ?? 
                                    'id',
                    'has_site_id' => $hasSiteId,
                    'multiple' => $isMultiple,
                    'is_json' => $isJsonField, // 标记是否为 JSON 字段
                ];
            }
        }

        return $relations;
    }

    /**
     * 根据数据库类型猜测表单字段类型
     */
    protected function guessFormFieldTypeFromDbType(string $dbType): string
    {
        return match ($dbType) {
            'text', 'mediumtext', 'longtext' => 'textarea',
            'int', 'tinyint', 'smallint', 'bigint', 'integer' => 'number',
            'decimal', 'float', 'double' => 'number',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'enum' => 'select',
            default => 'text',
        };
    }

    /**
     * 获取模型类名
     */
    public function getModelClass(string $model): string
    {
        $config = $this->getModelConfig($model);
        return $config['model_class'] ?? $this->guessModelClass($model);
    }

    /**
     * 获取表名
     */
    public function getTableName(string $model): string
    {
        $config = $this->getModelConfig($model);
        return $config['table'] ?? $model;
    }

    /**
     * 获取列表数据
     */
    public function getList(string $model, array $params = []): array
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);
        $connectionName = $this->getConnectionName($config);
        $connection = $this->getConnection($config);
        $relations = $config['relations'] ?? [];
        $currentSiteId = site_id();

        $query = $connection->table($tableName . ' as main');

        // 获取需要查询的字段（只查询可列出的字段）
        $selectFields = $this->getListableFields($config, $params);
        
        // 构建 SELECT 字段列表，同时处理关联字段
        $selectList = [];
        $relationMappings = []; // 存储关联字段的映射关系
        $joinCount = 0; // 统计关联查询数量
        $joinDetails = []; // 记录关联查询详情
        
        // 首先处理所有关联字段
        // 注意：JSON 字段（is_json=true）不应该进行 JOIN，应该在查询后单独处理
        foreach ($relations as $field => $relation) {
            $relationTable = $relation['table'] ?? '';
            $labelField = $relation['label_field'] ?? 'name';
            $valueField = $relation['value_field'] ?? 'id';
            $isJsonField = $relation['is_json'] ?? false;
            
            // JSON 字段（如 user_ids）不进行 JOIN，只选择原始字段，后续单独处理
            if ($isJsonField) {
                // JSON 字段：只有在 selectFields 中时才处理
                if (in_array($field, $selectFields)) {
                    $selectList[] = "main.{$field}";
                    
                    // 存储映射关系，标记为 JSON 字段，后续单独处理
                    $relationMappings[$field] = [
                        'label_alias' => null, // JSON 字段没有 JOIN，所以没有 label_alias
                        'multiple' => true, // JSON 字段默认是多选
                        'is_json' => true,
                        'table' => $relationTable,
                        'label_field' => $labelField,
                        'value_field' => $valueField,
                        'has_site_id' => $relation['has_site_id'] ?? true,
                    ];
                }
                // 如果字段不在 selectFields 中，不处理（不在列表中显示）
                continue; // 跳过 JOIN 处理
            }
            
            // 非 JSON 字段：正常进行 JOIN
            if ($relationTable) {
                // 为关联表创建别名
                $relationAlias = 'rel_' . str_replace(['_', '-'], '', $field);
                
                // LEFT JOIN 关联表
                $query->leftJoin(
                    "{$relationTable} as {$relationAlias}",
                    "main.{$field}",
                    '=',
                    "{$relationAlias}.{$valueField}"
                );
                
                $joinCount++;
                $joinDetails[] = [
                    'field' => $field,
                    'relation_table' => $relationTable,
                    'relation_alias' => $relationAlias,
                    'join_condition' => "main.{$field} = {$relationAlias}.{$valueField}",
                    'label_field' => $labelField,
                    'multiple' => $relation['multiple'] ?? false,
                ];
                
                // 如果字段在 selectFields 中，才添加到 SELECT 列表
                if (in_array($field, $selectFields)) {
                    // 选择主表的原始字段（ID）
                    $selectList[] = "main.{$field}";
                    
                    // 选择关联表的显示字段，并添加别名以便识别
                    $relationLabelAlias = "{$field}_label";
                    $selectList[] = "{$relationAlias}.{$labelField} as {$relationLabelAlias}";
                    
                    // 存储映射关系，供后续处理使用
                    $relationMappings[$field] = [
                        'label_alias' => $relationLabelAlias,
                        'multiple' => $relation['multiple'] ?? false,
                        'is_json' => false,
                    ];
                }
            } else {
                // 如果没有关联表配置，且字段在 selectFields 中，只选择原始字段
                if (in_array($field, $selectFields)) {
                    $selectList[] = "main.{$field}";
                }
            }
        }
        
        // 然后处理非关联字段
        // 构建配置字段名映射，用于二次验证（防御性编程）
        $configFieldNamesForSelect = [];
        if (!empty($config['columns'])) {
            foreach ($config['columns'] as $column) {
                $fieldName = $column['name'] ?? '';
                if (!empty($fieldName)) {
                    $configFieldNamesForSelect[] = $fieldName;
                }
            }
        }
        // 添加关联字段名
        if (!empty($config['relations'])) {
            foreach (array_keys($config['relations']) as $relationField) {
                if (!in_array($relationField, $configFieldNamesForSelect)) {
                    $configFieldNamesForSelect[] = $relationField;
                }
            }
        }
        
        foreach ($selectFields as $field) {
            // 如果已经处理过（是关联字段），跳过
            if (isset($relations[$field])) {
                continue;
            }
            
            // 二次验证：确保字段在配置中（防御性编程，防止意外情况）
            // id 字段始终允许
            if ($field !== 'id' && !in_array($field, $configFieldNamesForSelect)) {
                logger()->warning('[UniversalCrudService] SELECT 字段不在配置中，已跳过', [
                    'field' => $field,
                    'available_fields' => $configFieldNamesForSelect,
                ]);
                continue; // 跳过不存在的字段
            }
            
            // 验证并转义字段名
            $safeField = $this->escapeFieldName($field);
            $safeField = trim($safeField, '`'); // 移除反引号，Eloquent 会自动处理
            
            // 非关联字段，添加表前缀
            $selectList[] = "main.{$safeField}";
        }
        
        // 如果有关联字段，使用构建的 SELECT 列表
        if (!empty($selectList)) {
            $query->select($selectList);
        }

        // 添加站点过滤
//        if (!empty($config['has_site_id']) && site_id()) {
//            $query->where('main.site_id', site_id());
//        }

        // 回收站过滤：根据配置决定是否启用 deleted_at 过滤
        $onlyTrashed = $params['only_trashed'] ?? false;
        $softDeleteEnabled = !empty($config['soft_delete'])
            || (!empty($config['features']['soft_delete']) && filter_var($config['features']['soft_delete'], FILTER_VALIDATE_BOOLEAN));
        $hasDeletedAtColumn = $softDeleteEnabled;

        if (!$hasDeletedAtColumn) {
            $columns = $config['columns'] ?? [];
            foreach ($columns as $column) {
                if (($column['name'] ?? '') === 'deleted_at') {
                    $hasDeletedAtColumn = true;
                    break;
                }
            }
        }

        if ($hasDeletedAtColumn) {
            if ($onlyTrashed) {
                $query->whereNotNull('main.deleted_at');
            } else {
                // 正常列表：只查询未删除的记录
                $query->whereNull('main.deleted_at');
            }
        } elseif ($onlyTrashed) {
            logger()->warning('[UniversalCrudService] 请求了回收站数据但未启用软删除', [
                'table' => $config['table'] ?? null,
            ]);
        }

        // 搜索条件
        $keyword = $params['keyword'] ?? '';
        // 确保 keyword 是字符串
        if (is_array($keyword)) {
            $keyword = '';
        } else {
            $keyword = trim((string) $keyword);
        }
        
        if (!empty($keyword)) {
            $searchFields = $config['search_fields'] ?? ['id'];
            $query->where(function ($q) use ($searchFields, $keyword, $relationMappings, $relations) {
                foreach ($searchFields as $field) {
                    // 如果是关联字段，在关联表中搜索
                    if (isset($relationMappings[$field])) {
                        $relationAlias = 'rel_' . str_replace(['_', '-'], '', $field);
                        $relation = $relations[$field];
                        $labelField = $relation['label_field'] ?? 'name';
                        $q->orWhere("{$relationAlias}.{$labelField}", 'like', '%' . $keyword . '%');
                    } else {
                        $q->orWhere("main.{$field}", 'like', '%' . $keyword . '%');
                    }
                }
            });
        }

        // 额外过滤条件
        if (!empty($params['filters']) && is_array($params['filters'])) {
            $this->applyFilters($query, $params['filters'], $config, $relationMappings, $relations);
        }

        // 排序
        $sortField = $params['sort_field'] ?? ($config['default_sort_field'] ?? 'id');
        $sortOrder = $params['sort_order'] ?? ($config['default_sort_order'] ?? 'desc');
        
        // 验证排序字段是否允许排序（检查列配置中的 sortable）
        $allowedSortFields = ['id']; // id 字段始终允许排序
        $columns = $config['columns'] ?? [];
        foreach ($columns as $column) {
            if (($column['sortable'] ?? false) && isset($column['name'])) {
                $allowedSortFields[] = $column['name'];
            }
        }
        
        // 如果排序字段不在允许列表中，使用默认排序字段
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = $config['default_sort_field'] ?? 'id';
            $sortOrder = $config['default_sort_order'] ?? 'desc';
        }
        
        $query->orderBy("main.{$sortField}", $sortOrder);

        // 分页
        $page = $params['page'] ?? 1;
        $pageSize = (int)($params['page_size'] ?? 15);

        // 克隆查询用于统计总数，避免影响原始查询
        $countQuery = clone $query;
        
        $total = $countQuery->count();

        // 克隆查询用于数据查询，避免影响原始查询
        $dataQuery = clone $query;
        
        // 如果 page_size 为 0，返回所有数据（不分页）
        if ($pageSize > 0) {
            $dataQuery->forPage($page, $pageSize);
        }
        
        // 执行数据查询
        $data = $dataQuery->get()->toArray();
        
        // 处理关联字段的数据，将关联名称映射到原始字段
        $multipleRelationQueries = 0; // 统计多选关联的额外查询次数
        foreach ($data as &$row) {
            // 确保 $row 是数组格式（防止是 stdClass 对象）
            if (is_object($row)) {
                $row = (array) $row;
            }
            
            // 处理所有关联字段（包括不在 selectFields 中的 JSON 字段）
            // 先处理 relationMappings 中的字段
            foreach ($relationMappings as $field => $mapping) {
                $labelAlias = $mapping['label_alias'] ?? null;
                $isMultiple = $mapping['multiple'] ?? false;
                $isJsonField = $mapping['is_json'] ?? false;
                
                // JSON 字段（如 user_ids）：需要单独查询关联数据
                // 注意：如果字段不在 selectFields 中，$row 中可能没有这个字段
                if ($isJsonField) {
                    // 检查字段是否存在（可能不在 selectFields 中）
                    if (!isset($row[$field]) || $row[$field] === null) {
                        // 如果字段不存在，设置为空数组并跳过
                        $row["{$field}_label"] = [];
                        continue;
                    }
                    
                    // 处理 JSON 字段：解析 JSON 数组
                    $ids = $row[$field];
                    if (is_string($ids)) {
                        try {
                            $ids = json_decode($ids, true);
                        } catch (\Exception $e) {
                            $ids = [];
                        }
                    }
                    
                    // 如果不是数组或为空，设置为空数组
                    if (!is_array($ids)) {
                        $ids = [];
                    }
                    
                    // 过滤空值
                    $ids = array_filter($ids, function($id) {
                        return $id !== '' && $id !== null;
                    });
                    
                    // 查询关联数据
                    $relationTable = $mapping['table'] ?? '';
                    $labelField = $mapping['label_field'] ?? 'name';
                    $valueField = $mapping['value_field'] ?? 'id';
                    $hasSiteId = $mapping['has_site_id'] ?? true;
                    
                    if ($relationTable && !empty($ids)) {
                        $multipleRelationQueries++;
                        $multipleQuery = $connection->table($relationTable)
                            ->whereIn($valueField, $ids);
                        
                        $this->applyRelationSiteFilter(
                            $multipleQuery,
                            $hasSiteId,
                            $currentSiteId,
                            $connectionName,
                            $relationTable
                        );
                        
                        $labels = $multipleQuery->pluck($labelField, $valueField)->toArray();
                        
                        // 将标签数组添加到结果中
                        $row["{$field}_label"] = array_values($labels); // 只保留标签值数组
                    } else {
                        // 如果没有关联表或 ID 为空，设置为空数组
                        $row["{$field}_label"] = [];
                    }
                    
                    continue; // JSON 字段处理完成，继续下一个字段
                }
                
                // 非 JSON 字段：处理 JOIN 查询的结果
                if ($isMultiple && isset($row[$field])) {
                    // 处理多选情况（如果是 JSON 字符串，需要解析）
                    $ids = $row[$field];
                    if (is_string($ids)) {
                        try {
                            $ids = json_decode($ids, true);
                        } catch (\Exception $e) {
                            $ids = [];
                        }
                    }
                    
                    if (is_array($ids) && !empty($ids)) {
                        // 查询多个关联名称
                        $relation = $relations[$field];
                        $relationTable = $relation['table'] ?? '';
                        $labelField = $relation['label_field'] ?? 'name';
                        $valueField = $relation['value_field'] ?? 'id';
                        
                        if ($relationTable) {
                            $multipleRelationQueries++;
                            $multipleQuery = $connection->table($relationTable)
                                ->whereIn($valueField, $ids);
                            
                            $labels = $multipleQuery->pluck($labelField, $valueField)->toArray();
                            
                            // 保持 ID 顺序，获取对应的名称
                            $labelArray = [];
                            foreach ($ids as $id) {
                                if (isset($labels[$id])) {
                                    $labelArray[] = $labels[$id];
                                }
                            }
                            
                            $row[$labelAlias] = implode(', ', $labelArray);
                        }
                    } else {
                        $row[$labelAlias] = '';
                    }
                } else {
                    // 单选情况，关联名称已经在查询中获取
                    // 确保字段存在
                    if (!isset($row[$labelAlias])) {
                        $row[$labelAlias] = '';
                    }
                }
            }
        }

        // 计算总页数：如果 page_size 为 0，则总页数为 1（所有数据在一页）
        $lastPage = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize > 0 ? $pageSize : $total, // 如果 page_size 为 0，返回实际数据总数
            'last_page' => $lastPage,
        ];
    }

    /**
     * 导出数据（获取所有数据，不分页）
     * 
     * @param string $model 模型标识
     * @param array $params 查询参数（keyword, filters, sort_field, sort_order）
     * @return array 返回数据数组和列配置
     */
    public function export(string $model, array $params = []): array
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);
        $relations = $config['relations'] ?? [];
        $connection = $this->getConnection($config);

        $query = $connection->table($tableName . ' as main');

        // 获取需要查询的字段（只查询可列出的字段）
        $selectFields = $this->getListableFields($config, $params);
        
        // 构建 SELECT 字段列表，同时处理关联字段（复用 getList 的逻辑）
        $selectList = [];
        $relationMappings = [];
        $joinCount = 0;
        $joinDetails = [];
        
        // 处理关联字段
        foreach ($relations as $field => $relation) {
            $relationTable = $relation['table'] ?? '';
            $labelField = $relation['label_field'] ?? 'name';
            $valueField = $relation['value_field'] ?? 'id';
            $isJsonField = $relation['is_json'] ?? false;
            
            if ($isJsonField) {
                if (in_array($field, $selectFields)) {
                    $selectList[] = "main.{$field}";
                    $relationMappings[$field] = [
                        'label_alias' => null,
                        'multiple' => true,
                        'is_json' => true,
                        'table' => $relationTable,
                        'label_field' => $labelField,
                        'value_field' => $valueField,
                        'has_site_id' => $relation['has_site_id'] ?? true,
                    ];
                }
                continue;
            }
            
            if ($relationTable) {
                $relationAlias = 'rel_' . str_replace(['_', '-'], '', $field);
                $query->leftJoin(
                    "{$relationTable} as {$relationAlias}",
                    "main.{$field}",
                    '=',
                    "{$relationAlias}.{$valueField}"
                );
                
                if (in_array($field, $selectFields)) {
                    $selectList[] = "main.{$field}";
                    $relationLabelAlias = "{$field}_label";
                    $selectList[] = "{$relationAlias}.{$labelField} as {$relationLabelAlias}";
                    $relationMappings[$field] = [
                        'label_alias' => $relationLabelAlias,
                        'multiple' => $relation['multiple'] ?? false,
                        'is_json' => false,
                    ];
                }
            } else {
                if (in_array($field, $selectFields)) {
                    $selectList[] = "main.{$field}";
                }
            }
        }
        
        // 处理非关联字段
        $configFieldNamesForSelect = [];
        if (!empty($config['columns'])) {
            foreach ($config['columns'] as $column) {
                $fieldName = $column['name'] ?? '';
                if (!empty($fieldName)) {
                    $configFieldNamesForSelect[] = $fieldName;
                }
            }
        }
        if (!empty($config['relations'])) {
            foreach (array_keys($config['relations']) as $relationField) {
                if (!in_array($relationField, $configFieldNamesForSelect)) {
                    $configFieldNamesForSelect[] = $relationField;
                }
            }
        }
        
        foreach ($selectFields as $field) {
            if (isset($relations[$field])) {
                continue;
            }
            
            if ($field !== 'id' && !in_array($field, $configFieldNamesForSelect)) {
                continue;
            }
            
            $safeField = $this->escapeFieldName($field);
            $safeField = trim($safeField, '`');
            $selectList[] = "main.{$safeField}";
        }
        
        if (!empty($selectList)) {
            $query->select($selectList);
        }

        // 搜索条件
        $keyword = $params['keyword'] ?? '';
        if (is_array($keyword)) {
            $keyword = '';
        } else {
            $keyword = trim((string) $keyword);
        }
        
        if (!empty($keyword)) {
            $searchFields = $config['search_fields'] ?? ['id'];
            $query->where(function ($q) use ($searchFields, $keyword, $relationMappings, $relations) {
                foreach ($searchFields as $field) {
                    if (isset($relationMappings[$field])) {
                        $relationAlias = 'rel_' . str_replace(['_', '-'], '', $field);
                        $relation = $relations[$field];
                        $labelField = $relation['label_field'] ?? 'name';
                        $q->orWhere("{$relationAlias}.{$labelField}", 'like', '%' . $keyword . '%');
                    } else {
                        $q->orWhere("main.{$field}", 'like', '%' . $keyword . '%');
                    }
                }
            });
        }

        // 额外过滤条件
        if (!empty($params['filters']) && is_array($params['filters'])) {
            $this->applyFilters($query, $params['filters'], $config, $relationMappings, $relations);
        }

        // 排序
        $sortField = $params['sort_field'] ?? ($config['default_sort_field'] ?? 'id');
        $sortOrder = $params['sort_order'] ?? ($config['default_sort_order'] ?? 'desc');
        
        $allowedSortFields = ['id'];
        $columns = $config['columns'] ?? [];
        foreach ($columns as $column) {
            if (($column['sortable'] ?? false) && isset($column['name'])) {
                $allowedSortFields[] = $column['name'];
            }
        }
        
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = $config['default_sort_field'] ?? 'id';
            $sortOrder = $config['default_sort_order'] ?? 'desc';
        }
        
        $query->orderBy("main.{$sortField}", $sortOrder);

        // 获取所有数据（不分页）
        $data = $query->get()->toArray();
        
        // 处理关联字段的数据（复用 getList 的处理逻辑）
        $siteId = site_id();
        foreach ($data as &$row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            
            foreach ($relationMappings as $field => $mapping) {
                $labelAlias = $mapping['label_alias'] ?? null;
                $isMultiple = $mapping['multiple'] ?? false;
                $isJsonField = $mapping['is_json'] ?? false;
                
                if ($isJsonField) {
                    if (!isset($row[$field]) || $row[$field] === null) {
                        $row["{$field}_label"] = [];
                        continue;
                    }
                    
                    $ids = $row[$field];
                    if (is_string($ids)) {
                        try {
                            $ids = json_decode($ids, true);
                        } catch (\Exception $e) {
                            $ids = [];
                        }
                    }
                    
                    if (!is_array($ids)) {
                        $ids = [];
                    }
                    
                    $ids = array_filter($ids, function($id) {
                        return $id !== '' && $id !== null;
                    });
                    
                    $relationTable = $mapping['table'] ?? '';
                    $labelField = $mapping['label_field'] ?? 'name';
                    $valueField = $mapping['value_field'] ?? 'id';
                    $hasSiteId = $mapping['has_site_id'] ?? true;
                    
                    if ($relationTable && !empty($ids)) {
                        $multipleQuery = $connection->table($relationTable)
                            ->whereIn($valueField, $ids);
                        
                        $this->applyRelationSiteFilter(
                            $multipleQuery,
                            $hasSiteId,
                            $siteId,
                            $connectionName,
                            $relationTable
                        );
                        
                        $labels = $multipleQuery->pluck($labelField, $valueField)->toArray();
                        $row["{$field}_label"] = array_values($labels);
                    } else {
                        $row["{$field}_label"] = [];
                    }
                    
                    continue;
                }
                
                if ($isMultiple && isset($row[$field])) {
                    $ids = $row[$field];
                    if (is_string($ids)) {
                        try {
                            $ids = json_decode($ids, true);
                        } catch (\Exception $e) {
                            $ids = [];
                        }
                    }
                    
                    if (is_array($ids) && !empty($ids)) {
                        $relation = $relations[$field];
                        $relationTable = $relation['table'] ?? '';
                        $labelField = $relation['label_field'] ?? 'name';
                        $valueField = $relation['value_field'] ?? 'id';
                        $hasSiteId = $relation['has_site_id'] ?? true;
                        
                        if ($relationTable) {
                            $multipleQuery = $connection->table($relationTable)
                                ->whereIn($valueField, $ids);
                            
                            $this->applyRelationSiteFilter(
                                $multipleQuery,
                                $hasSiteId,
                                $siteId,
                                $connectionName,
                                $relationTable
                            );
                            
                            $labels = $multipleQuery->pluck($labelField, $valueField)->toArray();
                            $labelArray = [];
                            foreach ($ids as $id) {
                                if (isset($labels[$id])) {
                                    $labelArray[] = $labels[$id];
                                }
                            }
                            
                            $row[$labelAlias] = implode(', ', $labelArray);
                        }
                    } else {
                        $row[$labelAlias] = '';
                    }
                } else {
                    if (!isset($row[$labelAlias])) {
                        $row[$labelAlias] = '';
                    }
                }
            }
        }

        // 获取列配置（用于 CSV 表头）
        $columns = $this->getTableColumns($model);
        $exportColumns = [];
        foreach ($columns as $column) {
            $name = $column['name'];
            $label = $column['label'] ?? $name;
            $listable = $column['listable'] ?? $column['show_in_list'] ?? true;
            
            if ($listable) {
                $exportColumns[] = [
                    'name' => $name,
                    'label' => $label,
                    'type' => $column['type'] ?? 'string',
                    'form_type' => $column['form_type'] ?? null,
                ];
            }
        }

        return [
            'data' => $data,
            'columns' => $exportColumns,
        ];
    }

    /**
     * 获取导出列配置
     *
     * @param string $model 模型名
     * @param array $params 查询参数
     * @return array 列配置数组
     */
    public function getExportColumns(string $model, array $params = []): array
    {
        // 获取列配置（用于 CSV 表头）
        $columns = $this->getTableColumns($model);
        $exportColumns = [];
        foreach ($columns as $column) {
            $name = $column['name'];
            $label = $column['label'] ?? $name;
            $listable = $column['listable'] ?? $column['show_in_list'] ?? true;
            
            if ($listable) {
                $exportColumns[] = [
                    'name' => $name,
                    'label' => $label,
                    'type' => $column['type'] ?? 'string',
                    'form_type' => $column['form_type'] ?? null,
                ];
            }
        }

        return $exportColumns;
    }

    /**
     * 分批导出数据（支持大数据量）
     *
     * @param string $model 模型名
     * @param array $params 查询参数
     * @param int $offset 偏移量
     * @param int $limit 每批数量
     * @return array 数据数组
     */
    public function exportBatch(string $model, array $params, int $offset = 0, int $limit = 2000): array
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);
        $relations = $config['relations'] ?? [];
        $connection = $this->getConnection($config);

        $query = $connection->table($tableName . ' as main');

        // 获取需要查询的字段（只查询可列出的字段）
        $selectFields = $this->getListableFields($config, $params);
        
        // 构建 SELECT 字段列表，同时处理关联字段（复用 getList 的逻辑）
        $selectList = [];
        $relationMappings = [];
        
        // 处理关联字段
        foreach ($relations as $field => $relation) {
            $relationTable = $relation['table'] ?? '';
            $labelField = $relation['label_field'] ?? 'name';
            $valueField = $relation['value_field'] ?? 'id';
            $isJsonField = $relation['is_json'] ?? false;
            
            if ($isJsonField) {
                if (in_array($field, $selectFields)) {
                    $selectList[] = "main.{$field}";
                    $relationMappings[$field] = [
                        'label_alias' => null,
                        'multiple' => true,
                        'is_json' => true,
                        'table' => $relationTable,
                        'label_field' => $labelField,
                        'value_field' => $valueField,
                        'has_site_id' => $relation['has_site_id'] ?? true,
                    ];
                }
                continue;
            }
            
            if ($relationTable) {
                $relationAlias = 'rel_' . str_replace(['_', '-'], '', $field);
                $query->leftJoin(
                    "{$relationTable} as {$relationAlias}",
                    "main.{$field}",
                    '=',
                    "{$relationAlias}.{$valueField}"
                );
                
                if (in_array($field, $selectFields)) {
                    $selectList[] = "main.{$field}";
                    $relationLabelAlias = "{$field}_label";
                    $selectList[] = "{$relationAlias}.{$labelField} as {$relationLabelAlias}";
                    $relationMappings[$field] = [
                        'label_alias' => $relationLabelAlias,
                        'multiple' => $relation['multiple'] ?? false,
                        'is_json' => false,
                    ];
                }
            } else {
                if (in_array($field, $selectFields)) {
                    $selectList[] = "main.{$field}";
                }
            }
        }
        
        // 处理非关联字段
        $configFieldNamesForSelect = [];
        if (!empty($config['columns'])) {
            foreach ($config['columns'] as $column) {
                $fieldName = $column['name'] ?? '';
                if (!empty($fieldName)) {
                    $configFieldNamesForSelect[] = $fieldName;
                }
            }
        }
        if (!empty($config['relations'])) {
            foreach (array_keys($config['relations']) as $relationField) {
                if (!in_array($relationField, $configFieldNamesForSelect)) {
                    $configFieldNamesForSelect[] = $relationField;
                }
            }
        }
        
        foreach ($selectFields as $field) {
            if (isset($relations[$field])) {
                continue;
            }
            
            if ($field !== 'id' && !in_array($field, $configFieldNamesForSelect)) {
                continue;
            }
            
            $safeField = $this->escapeFieldName($field);
            $safeField = trim($safeField, '`');
            $selectList[] = "main.{$safeField}";
        }
        
        if (!empty($selectList)) {
            $query->select($selectList);
        }

        // 搜索条件
        $keyword = $params['keyword'] ?? '';
        if (is_array($keyword)) {
            $keyword = '';
        } else {
            $keyword = trim((string) $keyword);
        }
        
        if (!empty($keyword)) {
            $searchFields = $config['search_fields'] ?? ['id'];
            $query->where(function ($q) use ($searchFields, $keyword, $relationMappings, $relations) {
                foreach ($searchFields as $field) {
                    if (isset($relationMappings[$field])) {
                        $relationAlias = 'rel_' . str_replace(['_', '-'], '', $field);
                        $relation = $relations[$field];
                        $labelField = $relation['label_field'] ?? 'name';
                        $q->orWhere("{$relationAlias}.{$labelField}", 'like', '%' . $keyword . '%');
                    } else {
                        $q->orWhere("main.{$field}", 'like', '%' . $keyword . '%');
                    }
                }
            });
        }

        // 额外过滤条件
        if (!empty($params['filters']) && is_array($params['filters'])) {
            $this->applyFilters($query, $params['filters'], $config, $relationMappings, $relations);
        }

        // 排序
        $sortField = $params['sort_field'] ?? ($config['default_sort_field'] ?? 'id');
        $sortOrder = $params['sort_order'] ?? ($config['default_sort_order'] ?? 'desc');
        
        $allowedSortFields = ['id'];
        $columns = $config['columns'] ?? [];
        foreach ($columns as $column) {
            if (($column['sortable'] ?? false) && isset($column['name'])) {
                $allowedSortFields[] = $column['name'];
            }
        }
        
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = $config['default_sort_field'] ?? 'id';
            $sortOrder = $config['default_sort_order'] ?? 'desc';
        }
        
        $query->orderBy("main.{$sortField}", $sortOrder);

        // 分批查询（使用 limit 和 offset）
        $data = $query->offset($offset)->limit($limit)->get()->toArray();
        
        // 处理关联字段的数据（复用 getList 的处理逻辑）
        $siteId = site_id();
        foreach ($data as &$row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            
            foreach ($relationMappings as $field => $mapping) {
                $labelAlias = $mapping['label_alias'] ?? null;
                $isMultiple = $mapping['multiple'] ?? false;
                $isJsonField = $mapping['is_json'] ?? false;
                
                if ($isJsonField) {
                    if (!isset($row[$field]) || $row[$field] === null) {
                        $row["{$field}_label"] = [];
                        continue;
                    }
                    
                    $ids = $row[$field];
                    if (is_string($ids)) {
                        try {
                            $ids = json_decode($ids, true);
                        } catch (\Exception $e) {
                            $ids = [];
                        }
                    }
                    
                    if (!is_array($ids)) {
                        $ids = [];
                    }
                    
                    $ids = array_filter($ids, function($id) {
                        return $id !== '' && $id !== null;
                    });
                    
                    $relationTable = $mapping['table'] ?? '';
                    $labelField = $mapping['label_field'] ?? 'name';
                    $valueField = $mapping['value_field'] ?? 'id';
                    $hasSiteId = $mapping['has_site_id'] ?? true;
                    
                    if ($relationTable && !empty($ids)) {
                        $multipleQuery = $connection->table($relationTable)
                            ->whereIn($valueField, $ids);
                        
                        $this->applyRelationSiteFilter(
                            $multipleQuery,
                            $hasSiteId,
                            $siteId,
                            $connectionName,
                            $relationTable
                        );
                        
                        $labels = $multipleQuery->pluck($labelField, $valueField)->toArray();
                        $row["{$field}_label"] = array_values($labels);
                    } else {
                        $row["{$field}_label"] = [];
                    }
                    
                    continue;
                }
                
                if ($isMultiple && isset($row[$field])) {
                    $ids = $row[$field];
                    if (is_string($ids)) {
                        try {
                            $ids = json_decode($ids, true);
                        } catch (\Exception $e) {
                            $ids = [];
                        }
                    }
                    
                    if (is_array($ids) && !empty($ids)) {
                        $relation = $relations[$field];
                        $relationTable = $relation['table'] ?? '';
                        $labelField = $relation['label_field'] ?? 'name';
                        $valueField = $relation['value_field'] ?? 'id';
                        $hasSiteId = $relation['has_site_id'] ?? true;
                        
                        if ($relationTable) {
                            $multipleQuery = $connection->table($relationTable)
                                ->whereIn($valueField, $ids);
                            
                            $this->applyRelationSiteFilter(
                                $multipleQuery,
                                $hasSiteId,
                                $siteId,
                                $connectionName,
                                $relationTable
                            );
                            
                            $labels = $multipleQuery->pluck($labelField, $valueField)->toArray();
                            $labelArray = [];
                            foreach ($ids as $id) {
                                if (isset($labels[$id])) {
                                    $labelArray[] = $labels[$id];
                                }
                            }
                            
                            $row[$labelAlias] = implode(', ', $labelArray);
                        }
                    } else {
                        $row[$labelAlias] = '';
                    }
                } else {
                    if (!isset($row[$labelAlias])) {
                        $row[$labelAlias] = '';
                    }
                }
            }
        }

        return $data;
    }

    /**
     * 验证表名格式，防止 SQL 注入
     *
     * @param string $tableName 表名
     * @throws \InvalidArgumentException 如果表名格式不合法
     */
    protected function validateTableName(string $tableName): void
    {
        // 表名只能包含字母、数字、下划线和连字符
        // 长度限制：1-64 个字符（MySQL 限制）
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $tableName)) {
            logger()->warning('[UniversalCrudService] 非法的表名格式', [
                'table_name' => $tableName,
            ]);
            throw new \InvalidArgumentException("非法的表名格式: {$tableName}");
        }

        // 禁止以数字开头（MySQL 限制）
        if (preg_match('/^\d/', $tableName)) {
            throw new \InvalidArgumentException("表名不能以数字开头: {$tableName}");
        }
    }

    /**
     * 验证 ID 参数，防止越界和非法值
     *
     * @param int $id ID 值
     * @param string $paramName 参数名称（用于错误信息）
     * @throws \InvalidArgumentException 如果 ID 不合法
     */
    protected function validateId(int $id, string $paramName = 'id'): void
    {
        // ID 必须是正整数
        if ($id <= 0) {
            throw new \InvalidArgumentException("{$paramName} 必须是正整数，当前值: {$id}");
        }

        // ID 不能超过 PHP_INT_MAX（防止整数溢出）
        if ($id > PHP_INT_MAX) {
            throw new \InvalidArgumentException("{$paramName} 超出最大允许值");
        }
    }

    /**
     * 验证并转义数据库字段名
     * 
     * 防止 SQL 注入：确保字段名只包含字母、数字、下划线和连字符
     * 
     * @param string $fieldName 字段名
     * @return string 转义后的字段名（使用反引号包裹）
     * @throws \InvalidArgumentException 如果字段名不合法
     */
    protected function escapeFieldName(string $fieldName): string
    {
        // 验证字段名格式：只允许字母、数字、下划线和连字符
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $fieldName)) {
            logger()->warning('[UniversalCrudService] 非法的字段名格式', [
                'field_name' => $fieldName,
            ]);
            throw new \InvalidArgumentException("非法的字段名格式: {$fieldName}");
        }
        
        // 使用反引号包裹字段名（MySQL 语法）
        return "`{$fieldName}`";
    }
    
    /**
     * 验证并转义关联字段的标签字段名
     * 
     * @param string $labelField 标签字段名
     * @return string 转义后的字段名
     */
    protected function escapeRelationLabelField(string $labelField): string
    {
        return $this->escapeFieldName($labelField);
    }

    /**
     * 应用过滤条件
     *
     * @param \Hyperf\Database\Query\Builder $query 查询构建器
     * @param array $filters 过滤条件数组
     * @param array $config 模型配置
     * @param array $relationMappings 关联字段映射
     * @param array $relations 关联配置
     */
    protected function applyFilters($query, array $filters, array $config, array $relationMappings, array $relations): void
    {
        $fieldsConfig = $config['fields'] ?? [];
        $columns = $config['columns'] ?? [];
        $searchableFields = $config['search_fields'] ?? [];
        
        // 记录接收到的 filters 参数
        logger()->info('[UniversalCrudService] applyFilters 开始', [
            'filters' => $filters,
            'filters_count' => count($filters),
            'fields_config_count' => count($fieldsConfig),
            'columns_count' => count($columns),
            'relations' => array_keys($relations),
            'searchable_fields' => $searchableFields,
        ]);
        
        // 过滤掉不在可搜索字段白名单中的字段
        $filteredFilters = [];
        foreach ($filters as $field => $value) {
            // 跳过空值
            if ($value === '' || $value === null) {
                continue;
            }
            
            // 检查字段是否可搜索
            if (!empty($searchableFields)) {
                // 检查是否是区间字段（以 _min 或 _max 结尾）
                if (str_ends_with($field, '_min') || str_ends_with($field, '_max')) {
                    $baseField = str_ends_with($field, '_min') 
                        ? substr($field, 0, -4)  // 移除 '_min' 后缀
                        : substr($field, 0, -4);  // 移除 '_max' 后缀
                    
                    // 检查基础字段是否在可搜索字段列表中
                    if (!in_array($baseField, $searchableFields, true)) {
                        logger()->debug('[UniversalCrudService] 跳过不可搜索的区间字段', [
                            'field' => $field,
                            'base_field' => $baseField,
                            'searchable_fields' => $searchableFields,
                        ]);
                        continue;
                    }
                } else {
                    // 普通字段：检查是否在可搜索字段列表中
                    if (!in_array($field, $searchableFields, true)) {
                        logger()->debug('[UniversalCrudService] 跳过不可搜索的字段', [
                            'field' => $field,
                            'searchable_fields' => $searchableFields,
                        ]);
                        continue;
                    }
                }
            }
            
            // 字段通过验证，添加到过滤后的数组中
            $filteredFilters[$field] = $value;
        }
        
        // 记录过滤后的 filters
        if (count($filters) !== count($filteredFilters)) {
            logger()->info('[UniversalCrudService] 字段白名单过滤完成', [
                'original_count' => count($filters),
                'filtered_count' => count($filteredFilters),
                'filtered_filters' => $filteredFilters,
            ]);
        }
        
        // 使用过滤后的 filters
        $filters = $filteredFilters;
        
        // 处理区间数字字段（_min 和 _max 后缀）
        $rangeFields = [];
        $processedFields = [];
        
        foreach ($filters as $field => $value) {
            // 跳过空值（虽然前面已经过滤过，但为了安全再次检查）
            if ($value === '' || $value === null) {
                continue;
            }
            
            // 检查是否是区间字段（以 _min 或 _max 结尾）
            if (str_ends_with($field, '_min')) {
                $baseField = substr($field, 0, -4); // 移除 '_min' 后缀
                if (!isset($rangeFields[$baseField])) {
                    $rangeFields[$baseField] = ['min' => null, 'max' => null];
                }
                $rangeFields[$baseField]['min'] = $value;
                $processedFields[] = $field;
            } elseif (str_ends_with($field, '_max')) {
                $baseField = substr($field, 0, -4); // 移除 '_max' 后缀
                if (!isset($rangeFields[$baseField])) {
                    $rangeFields[$baseField] = ['min' => null, 'max' => null];
                }
                $rangeFields[$baseField]['max'] = $value;
                $processedFields[] = $field;
            }
        }
        
        // 处理区间字段查询
        foreach ($rangeFields as $baseField => $range) {
            // 获取基础字段配置（不包含 _min 或 _max 后缀）
            $fieldConfig = $this->getFieldConfig($baseField, $fieldsConfig, $columns);
            
            // 检查是否是关联字段
            $isRelationField = isset($relations[$baseField]);
            $isJsonRelation = false;
            $relationAlias = null;
            
            if ($isRelationField) {
                $relation = $relations[$baseField];
                $isJsonRelation = $relation['is_json'] ?? false;
                if (!$isJsonRelation) {
                    $relationAlias = 'rel_' . str_replace(['_', '-'], '', $baseField);
                }
            }
            
            // 构建查询条件
            $minValue = $range['min'];
            $maxValue = $range['max'];
            
            // 获取字段类型，判断是否是时间字段
            $dbType = $fieldConfig['db_type'] ?? $fieldConfig['type'] ?? 'string';
            $formType = $fieldConfig['form_type'] ?? $fieldConfig['type'] ?? null;
            $isDateField = $this->isDateType($dbType, $formType);
            
            // 时间字段特殊处理
            if ($isDateField) {
                // 判断是 date 还是 datetime 类型
                $isDateOnly = (strtolower($dbType) === 'date') || ($formType === 'date');
                
                // 处理开始时间
                if ($minValue !== null && $minValue !== '') {
                    if ($isDateOnly) {
                        // date 类型：补全到当天的开始时间 00:00:00
                        $minValue = date('Y-m-d 00:00:00', strtotime($minValue));
                    } else {
                        // datetime 类型：确保格式正确
                        // 如果输入的是 datetime-local 格式（YYYY-MM-DDTHH:mm），先转换为标准格式
                        if (strpos($minValue, 'T') !== false) {
                            // 格式：YYYY-MM-DDTHH:mm -> YYYY-MM-DD HH:mm:00
                            $minValue = str_replace('T', ' ', $minValue);
                            // 如果已经有秒数，保持不变；如果没有，补全为 :00
                            if (substr_count($minValue, ':') === 1) {
                                $minValue .= ':00';
                            }
                        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $minValue)) {
                            // 如果输入的是日期格式（YYYY-MM-DD），补全为 YYYY-MM-DD 00:00:00
                            $minValue = $minValue . ' 00:00:00';
                        }
                        // 确保格式完整：YYYY-MM-DD HH:mm:ss
                        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{1,2}:\d{2}$/', $minValue)) {
                            $minValue .= ':00';
                        }
                    }
                }
                
                // 处理结束时间
                if ($maxValue !== null && $maxValue !== '') {
                    if ($isDateOnly) {
                        // date 类型：补全到当天的结束时间 23:59:59
                        $maxValue = date('Y-m-d 23:59:59', strtotime($maxValue));
                    } else {
                        // datetime 类型：确保格式正确
                        // 如果输入的是 datetime-local 格式（YYYY-MM-DDTHH:mm），先转换为标准格式
                        if (strpos($maxValue, 'T') !== false) {
                            // 格式：YYYY-MM-DDTHH:mm -> YYYY-MM-DD HH:mm:59（结束时间补全到该分钟的最后一秒）
                            $maxValue = str_replace('T', ' ', $maxValue);
                            // 如果已经有秒数，保持不变；如果没有，补全为 :59
                            if (substr_count($maxValue, ':') === 1) {
                                $maxValue .= ':59';
                            } elseif (preg_match('/:\d{2}$/', $maxValue)) {
                                // 如果已经有秒数，但需要确保是这一分钟的最后一秒
                                $parts = explode(' ', $maxValue);
                                if (count($parts) === 2) {
                                    $timeParts = explode(':', $parts[1]);
                                    if (count($timeParts) === 3) {
                                        $timeParts[2] = '59';
                                        $maxValue = $parts[0] . ' ' . implode(':', $timeParts);
                                    }
                                }
                            }
                        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $maxValue)) {
                            // 如果输入的是日期格式（YYYY-MM-DD），补全为 YYYY-MM-DD 23:59:59
                            $maxValue = $maxValue . ' 23:59:59';
                        }
                        // 确保格式完整：YYYY-MM-DD HH:mm:ss
                        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{1,2}:\d{2}$/', $maxValue)) {
                            $maxValue .= ':59';
                        }
                    }
                }
            } else {
                // 非时间字段：数字类型转换
                if ($minValue !== null && $minValue !== '') {
                    $minValue = is_numeric($minValue) ? (float)$minValue : $minValue;
                }
                if ($maxValue !== null && $maxValue !== '') {
                    $maxValue = is_numeric($maxValue) ? (float)$maxValue : $maxValue;
                }
            }
            
            // 验证并转义基础字段名
            $safeBaseField = $this->escapeFieldName($baseField);
            $safeBaseField = trim($safeBaseField, '`'); // 移除反引号，Eloquent 会自动处理
            
            // 如果两个值都存在，使用 whereBetween
            if ($minValue !== null && $minValue !== '' && $maxValue !== null && $maxValue !== '') {
                if ($isRelationField && !$isJsonRelation) {
                    // 关联字段区间查询（通常不支持，这里使用主表字段）
                    $query->whereBetween("main.{$safeBaseField}", [$minValue, $maxValue]);
                } else {
                    // 普通字段区间查询
                    logger()->info("[UniversalCrudService] 应用区间查询条件: {$baseField}", [
                        'min' => $minValue,
                        'max' => $maxValue,
                        'is_relation' => $isRelationField,
                        'is_date_field' => $isDateField,
                    ]);
                    $query->whereBetween("main.{$safeBaseField}", [$minValue, $maxValue]);
                }
            } elseif ($minValue !== null && $minValue !== '') {
                // 只有最小值：大于等于
                if ($isRelationField && !$isJsonRelation) {
                    $query->where("main.{$safeBaseField}", '>=', $minValue);
                } else {
                    logger()->info("[UniversalCrudService] 应用最小值查询条件: {$baseField}", [
                        'min' => $minValue,
                        'is_relation' => $isRelationField,
                        'is_date_field' => $isDateField,
                    ]);
                    $query->where("main.{$safeBaseField}", '>=', $minValue);
                }
            } elseif ($maxValue !== null && $maxValue !== '') {
                // 只有最大值：小于等于
                if ($isRelationField && !$isJsonRelation) {
                    $query->where("main.{$safeBaseField}", '<=', $maxValue);
                } else {
                    logger()->info("[UniversalCrudService] 应用最大值查询条件: {$baseField}", [
                        'max' => $maxValue,
                        'is_relation' => $isRelationField,
                        'is_date_field' => $isDateField,
                    ]);
                    $query->where("main.{$safeBaseField}", '<=', $maxValue);
                }
            }
        }
        
        // 过滤掉已处理的区间字段，继续处理其他字段
        $filters = array_filter($filters, function($key) use ($processedFields) {
            return !in_array($key, $processedFields);
        }, ARRAY_FILTER_USE_KEY);
        
        foreach ($filters as $field => $value) {
            // 跳过空值
            if ($value === '' || $value === null) {
                logger()->debug("[UniversalCrudService] 跳过空值字段: {$field}", [
                    'value' => $value,
                ]);
                continue;
            }
            
            logger()->info("[UniversalCrudService] 处理过滤字段: {$field}", [
                'field' => $field,
                'value' => $value,
                'value_type' => gettype($value),
                'is_array' => is_array($value),
            ]);
            
            // 获取字段配置
            $fieldConfig = $this->getFieldConfig($field, $fieldsConfig, $columns);
            
            logger()->debug("[UniversalCrudService] 字段配置: {$field}", [
                'field_config' => $fieldConfig,
                'found_in_fields' => !empty($fieldConfig),
            ]);
            
            // 判断是否是关联字段
            $isRelationField = isset($relations[$field]);
            $isJsonRelation = false;
            $relationAlias = null;
            $relationLabelField = null;
            
            if ($isRelationField) {
                $relation = $relations[$field];
                $isJsonRelation = $relation['is_json'] ?? false;
                
                logger()->debug("[UniversalCrudService] 关联字段检测: {$field}", [
                    'is_relation' => true,
                    'is_json_relation' => $isJsonRelation,
                    'relation_config' => $relation,
                ]);
                
                if (!$isJsonRelation) {
                    // 非 JSON 关联字段，使用 JOIN 的别名
                    // 验证并清理字段名，只保留字母数字
                    $cleanField = preg_replace('/[^a-zA-Z0-9]/', '', $field);
                    $relationAlias = 'rel_' . $cleanField;
                    
                    // 验证并转义关联字段的标签字段名
                    $rawLabelField = $relation['label_field'] ?? 'name';
                    try {
                        $relationLabelField = $this->escapeRelationLabelField($rawLabelField);
                        // 移除反引号用于后续使用（Eloquent 会自动处理）
                        $relationLabelField = trim($relationLabelField, '`');
                    } catch (\InvalidArgumentException $e) {
                        logger()->warning('[UniversalCrudService] 关联字段标签字段名不合法，使用默认值', [
                            'field' => $field,
                            'invalid_label_field' => $rawLabelField,
                        ]);
                        $relationLabelField = 'name';
                    }
                    
                    logger()->debug("[UniversalCrudService] 关联字段别名: {$field}", [
                        'relation_alias' => $relationAlias,
                        'relation_label_field' => $relationLabelField,
                    ]);
                }
            } else {
                logger()->debug("[UniversalCrudService] 普通字段: {$field}", [
                    'is_relation' => false,
                ]);
            }
            
            // 获取字段类型
            $dbType = $fieldConfig['db_type'] ?? $fieldConfig['type'] ?? 'string';
            $formType = $fieldConfig['form_type'] ?? $fieldConfig['type'] ?? null;
            
            // ID 字段特殊处理：如果没有 form_type，默认使用 number（精确匹配）
            if ($field === 'id' && empty($formType)) {
                $formType = 'number';
                logger()->debug("[UniversalCrudService] ID 字段特殊处理: 设置 form_type = number", [
                    'field' => $field,
                    'original_form_type' => null,
                    'set_form_type' => $formType,
                ]);
            }
            
            logger()->debug("[UniversalCrudService] 字段类型: {$field}", [
                'db_type' => $dbType,
                'form_type' => $formType,
                'is_string_type' => $this->isStringType($dbType),
                'is_numeric_type' => $this->isNumericType($dbType),
                'is_date_type' => $this->isDateType($dbType, $formType),
            ]);
            
            // 处理数组值（多选）
            if (is_array($value)) {
                // 过滤空值
                $value = array_filter($value, function($v) {
                    return $v !== '' && $v !== null;
                });
                
                if (empty($value)) {
                    logger()->debug("[UniversalCrudService] 数组值过滤后为空: {$field}");
                    continue;
                }
                
                // 关联字段：在关联表中搜索
                if ($isRelationField && !$isJsonRelation) {
                    // 验证字段名
                    $safeField = $this->escapeFieldName($field);
                    $safeField = trim($safeField, '`'); // 移除反引号，Eloquent 会自动处理
                    $safeRelationAlias = $this->escapeFieldName($relationAlias);
                    $safeRelationAlias = trim($safeRelationAlias, '`');
                    $safeLabelField = $this->escapeFieldName($relationLabelField);
                    $safeLabelField = trim($safeLabelField, '`');
                    
                    $whereClause = "{$safeRelationAlias}.{$safeLabelField} IN (" . implode(',', array_map('intval', $value)) . ")";
                    logger()->info("[UniversalCrudService] 应用查询条件（数组-关联字段）: {$field}", [
                        'where_clause' => $whereClause,
                        'values' => $value,
                    ]);
                    $query->whereIn("{$safeRelationAlias}.{$safeLabelField}", $value);
                } elseif ($isRelationField && $isJsonRelation) {
                    // JSON 关联字段：使用 JSON_CONTAINS 或 JSON_SEARCH
                    // 验证并转义字段名
                    $safeField = $this->escapeFieldName($field);
                    
                    logger()->info("[UniversalCrudService] 应用查询条件（数组-JSON关联）: {$field}", [
                        'where_type' => 'JSON_CONTAINS',
                        'values' => $value,
                        'safe_field' => $safeField,
                    ]);
                    $query->where(function ($q) use ($safeField, $value) {
                        foreach ($value as $val) {
                            // 使用转义后的字段名
                            $q->orWhereRaw("JSON_CONTAINS(CAST(main.{$safeField} AS JSON), ?)", [json_encode($val)]);
                        }
                    });
                } else {
                    // 普通字段：使用 whereIn
                    // 验证并转义字段名
                    $safeField = $this->escapeFieldName($field);
                    $safeField = trim($safeField, '`'); // 移除反引号，Eloquent 会自动处理
                    
                    $whereClause = "main.{$safeField} IN (" . implode(',', $value) . ")";
                    logger()->info("[UniversalCrudService] 应用查询条件（数组-普通字段）: {$field}", [
                        'where_clause' => $whereClause,
                        'values' => $value,
                    ]);
                    $query->whereIn("main.{$safeField}", $value);
                }
                continue;
            }
            
            // 处理单个值
            // 关联字段：在关联表中搜索
            if ($isRelationField && !$isJsonRelation) {
                // 验证并转义字段名
                $safeField = $this->escapeFieldName($field);
                $safeField = trim($safeField, '`'); // 移除反引号，Eloquent 会自动处理
                $safeRelationAlias = $this->escapeFieldName($relationAlias);
                $safeRelationAlias = trim($safeRelationAlias, '`');
                $safeLabelField = $this->escapeFieldName($relationLabelField);
                $safeLabelField = trim($safeLabelField, '`');
                
                // 关联字段：根据值类型决定搜索方式
                // 如果是数字（包括字符串形式的数字），可能是关联表的 ID，直接匹配主表字段
                // 如果是字符串，可能是关联表的 label，在关联表中搜索
                if (is_numeric($value)) {
                    // 数字值：直接匹配主表的关联字段（存储的是关联表的 ID）
                    // 转换为整数以匹配数据库中的类型
                    $intValue = (int) $value;
                    logger()->info("[UniversalCrudService] 应用查询条件（单个-关联字段-数字）: {$field}", [
                        'where_clause' => "main.{$safeField} = {$intValue}",
                        'original_value' => $value,
                        'converted_value' => $intValue,
                        'is_numeric' => true,
                    ]);
                    $query->where("main.{$safeField}", $intValue);
                } else {
                    // 字符串值：在关联表中搜索 label_field（模糊搜索）
                    // 注意：$value 已经通过 Eloquent 的参数绑定处理，不会导致 SQL 注入
                    $whereClause = "{$safeRelationAlias}.{$safeLabelField} LIKE ?";
                    logger()->info("[UniversalCrudService] 应用查询条件（单个-关联字段-字符串）: {$field}", [
                        'where_clause' => $whereClause,
                        'value' => $value,
                        'is_numeric' => false,
                    ]);
                    $query->where("{$safeRelationAlias}.{$safeLabelField}", 'like', '%' . $value . '%');
                }
            } elseif ($isRelationField && $isJsonRelation) {
                // JSON 关联字段：使用 JSON_CONTAINS
                // 验证并转义字段名
                $safeField = $this->escapeFieldName($field);
                
                logger()->info("[UniversalCrudService] 应用查询条件（单个-JSON关联）: {$field}", [
                    'where_type' => 'JSON_CONTAINS',
                    'value' => $value,
                    'json_value' => json_encode($value),
                    'safe_field' => $safeField,
                ]);
                $query->whereRaw("JSON_CONTAINS(CAST(main.{$safeField} AS JSON), ?)", [json_encode($value)]);
            } else {
                // 普通字段：优先使用 search_type 配置，如果没有则根据 form_type 决定搜索方式，最后根据 db_type 判断
                
                // 验证并转义字段名
                $safeField = $this->escapeFieldName($field);
                $safeField = trim($safeField, '`'); // 移除反引号，Eloquent 会自动处理
                
                // 优先检查 search_type 配置
                $searchType = $fieldConfig['search_type'] ?? null;
                
                if (!empty($searchType)) {
                    // 使用配置的搜索类型
                    if ($searchType === 'like') {
                        // 模糊搜索
                        $whereClause = "main.{$safeField} LIKE ?";
                        logger()->info("[UniversalCrudService] 应用查询条件（单个-普通字段-search_type=like）: {$field}", [
                            'where_clause' => $whereClause,
                            'value' => $value,
                            'search_type' => $searchType,
                        ]);
                        $query->where("main.{$safeField}", 'like', '%' . $value . '%');
                    } elseif ($searchType === 'exact') {
                        // 精确匹配
                        $whereClause = "main.{$safeField} = ?";
                        logger()->info("[UniversalCrudService] 应用查询条件（单个-普通字段-search_type=exact）: {$field}", [
                            'where_clause' => $whereClause,
                            'value' => $value,
                            'search_type' => $searchType,
                        ]);
                        $query->where("main.{$safeField}", $value);
                    } elseif ($searchType === 'number_range') {
                        // 数字区间搜索（单个值转换为精确匹配）
                        $numericValue = is_numeric($value) ? (int) $value : $value;
                        $whereClause = "main.{$safeField} = {$numericValue}";
                        logger()->info("[UniversalCrudService] 应用查询条件（单个-普通字段-search_type=number_range）: {$field}", [
                            'where_clause' => $whereClause,
                            'value' => $value,
                            'converted_value' => $numericValue,
                            'search_type' => $searchType,
                        ]);
                        $query->where("main.{$safeField}", $numericValue);
                    } elseif ($searchType === 'date_range') {
                        // 日期区间搜索（单个值转换为区间）
                        $isDateOnly = (strtolower($dbType) === 'date') || ($formType === 'date');
                        if ($isDateOnly) {
                            $startTime = date('Y-m-d 00:00:00', strtotime($value));
                            $endTime = date('Y-m-d 23:59:59', strtotime($value));
                            logger()->info("[UniversalCrudService] 应用日期查询条件（单个值转换为区间-search_type=date_range）: {$field}", [
                                'original_value' => $value,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'search_type' => $searchType,
                            ]);
                            $query->whereBetween("main.{$safeField}", [$startTime, $endTime]);
                        } else {
                            // datetime 类型处理
                            if (strpos($value, 'T') !== false) {
                                $value = str_replace('T', ' ', $value);
                                if (substr_count($value, ':') === 1) {
                                    $value .= ':00';
                                }
                            }
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                                $value = $value . ' 00:00:00';
                            }
                            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{1,2}:\d{2}$/', $value)) {
                                $value .= ':00';
                            }
                            logger()->info("[UniversalCrudService] 应用日期时间查询条件（单个值-search_type=date_range）: {$field}", [
                                'original_value' => $value,
                                'converted_value' => $value,
                                'search_type' => $searchType,
                            ]);
                            $query->where("main.{$safeField}", '>=', $value);
                        }
                    } elseif ($searchType === 'select') {
                        // 下拉选择（精确匹配）
                        $whereClause = "main.{$safeField} = ?";
                        logger()->info("[UniversalCrudService] 应用查询条件（单个-普通字段-search_type=select）: {$field}", [
                            'where_clause' => $whereClause,
                            'value' => $value,
                            'search_type' => $searchType,
                        ]);
                        $query->where("main.{$safeField}", $value);
                    } else {
                        // 未知的搜索类型，回退到默认逻辑
                        logger()->warning("[UniversalCrudService] 未知的搜索类型，回退到默认逻辑: {$field}", [
                            'search_type' => $searchType,
                            'field' => $field,
                        ]);
                        // 继续执行下面的默认逻辑
                        $searchType = null;
                    }
                }
                
                // 如果没有配置 search_type，使用原有的逻辑
                if (empty($searchType)) {
                    // 先检查 form_type
                    if ($formType) {
                        // 数字类型（form_type 为 number）：使用精确匹配
                        if ($formType === 'number') {
                            $numericValue = is_numeric($value) ? (int) $value : $value;
                            $whereClause = "main.{$safeField} = {$numericValue}";
                            logger()->info("[UniversalCrudService] 应用查询条件（单个-普通字段-form_type=number）: {$field}", [
                                'where_clause' => $whereClause,
                                'value' => $value,
                                'converted_value' => $numericValue,
                                'form_type' => $formType,
                                'db_type' => $dbType,
                            ]);
                            $query->where("main.{$safeField}", $numericValue);
                        }
                        // 文本类型（form_type 为 text、textarea 等）：使用 LIKE 模糊搜索
                        elseif (in_array($formType, ['text', 'textarea', 'rich_text', 'email', 'url', 'password'])) {
                            $whereClause = "main.{$safeField} LIKE ?";
                            logger()->info("[UniversalCrudService] 应用查询条件（单个-普通字段-form_type=文本）: {$field}", [
                                'where_clause' => $whereClause,
                                'value' => $value,
                                'form_type' => $formType,
                                'db_type' => $dbType,
                            ]);
                            $query->where("main.{$safeField}", 'like', '%' . $value . '%');
                        }
                        // 日期类型（form_type 为 date、datetime 等）：转换为区间查询
                        elseif ($this->isDateType($dbType, $formType)) {
                            // 时间字段应该使用区间查询，单个值也需要转换为区间
                            $isDateOnly = (strtolower($dbType) === 'date') || ($formType === 'date');
                            
                            if ($isDateOnly) {
                                // date 类型：转换为当天范围
                                $startTime = date('Y-m-d 00:00:00', strtotime($value));
                                $endTime = date('Y-m-d 23:59:59', strtotime($value));
                                logger()->info("[UniversalCrudService] 应用日期查询条件（单个值转换为区间）: {$field}", [
                                    'original_value' => $value,
                                    'start_time' => $startTime,
                                    'end_time' => $endTime,
                                ]);
                                $query->whereBetween("main.{$safeField}", [$startTime, $endTime]);
                            } else {
                                // datetime 类型：转换为 >= 查询
                                // 处理 datetime-local 格式
                                if (strpos($value, 'T') !== false) {
                                    // 格式：YYYY-MM-DDTHH:mm -> YYYY-MM-DD HH:mm:00
                                    $value = str_replace('T', ' ', $value);
                                    // 如果已经有秒数，保持不变；如果没有，补全为 :00
                                    if (substr_count($value, ':') === 1) {
                                        $value .= ':00';
                                    }
                                }
                                // 如果只是日期格式，补全为当天的开始时间
                                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                                    $value = $value . ' 00:00:00';
                                }
                                // 确保格式完整：YYYY-MM-DD HH:mm:ss
                                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{1,2}:\d{2}$/', $value)) {
                                    $value .= ':00';
                                }
                                logger()->info("[UniversalCrudService] 应用日期时间查询条件（单个值）: {$field}", [
                                    'original_value' => $value,
                                    'converted_value' => $value,
                                ]);
                                $query->where("main.{$safeField}", '>=', $value);
                            }
                        }
                        // 其他 form_type：默认使用精确匹配
                        else {
                            $whereClause = "main.{$safeField} = ?";
                            logger()->info("[UniversalCrudService] 应用查询条件（单个-普通字段-form_type=其他）: {$field}", [
                                'where_clause' => $whereClause,
                                'value' => $value,
                                'form_type' => $formType,
                                'db_type' => $dbType,
                            ]);
                            $query->where("main.{$safeField}", $value);
                        }
                    }
                    // 如果没有 form_type，回退到使用 db_type 判断
                    elseif ($this->isStringType($dbType)) {
                        // 字符串类型：使用 LIKE 模糊搜索
                        $whereClause = "main.{$safeField} LIKE ?";
                        logger()->info("[UniversalCrudService] 应用查询条件（单个-普通字段-db_type=字符串）: {$field}", [
                            'where_clause' => $whereClause,
                            'value' => $value,
                            'db_type' => $dbType,
                            'form_type' => null,
                        ]);
                        $query->where("main.{$safeField}", 'like', '%' . $value . '%');
                    } elseif ($this->isNumericType($dbType)) {
                        // 数字类型：精确匹配
                        $numericValue = is_numeric($value) ? (int) $value : $value;
                        $whereClause = "main.{$safeField} = {$numericValue}";
                        logger()->info("[UniversalCrudService] 应用查询条件（单个-普通字段-db_type=数字）: {$field}", [
                            'where_clause' => $whereClause,
                            'value' => $value,
                            'converted_value' => $numericValue,
                            'db_type' => $dbType,
                            'form_type' => null,
                        ]);
                        $query->where("main.{$safeField}", $numericValue);
                    } elseif ($this->isDateType($dbType, $formType)) {
                        // 日期类型：转换为区间查询（兼容单个值的情况）
                        $isDateOnly = (strtolower($dbType) === 'date') || ($formType === 'date');
                        
                        if ($isDateOnly) {
                            // date 类型：转换为当天范围
                            $startTime = date('Y-m-d 00:00:00', strtotime($value));
                            $endTime = date('Y-m-d 23:59:59', strtotime($value));
                            logger()->info("[UniversalCrudService] 应用日期查询条件（单个值转换为区间-db_type）: {$field}", [
                                'original_value' => $value,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'db_type' => $dbType,
                            ]);
                            $query->whereBetween("main.{$safeField}", [$startTime, $endTime]);
                        } else {
                            // datetime 类型：转换为 >= 查询
                            // 处理 datetime-local 格式
                            if (strpos($value, 'T') !== false) {
                                // 格式：YYYY-MM-DDTHH:mm -> YYYY-MM-DD HH:mm:00
                                $value = str_replace('T', ' ', $value);
                                // 如果已经有秒数，保持不变；如果没有，补全为 :00
                                if (substr_count($value, ':') === 1) {
                                    $value .= ':00';
                                }
                            }
                            // 如果只是日期格式，补全为当天的开始时间
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                                $value = $value . ' 00:00:00';
                            }
                            // 确保格式完整：YYYY-MM-DD HH:mm:ss
                            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{1,2}:\d{2}$/', $value)) {
                                $value .= ':00';
                            }
                            logger()->info("[UniversalCrudService] 应用日期时间查询条件（单个值-db_type）: {$field}", [
                                'original_value' => $value,
                                'converted_value' => $value,
                                'db_type' => $dbType,
                            ]);
                            $query->where("main.{$safeField}", '>=', $value);
                        }
                    } else {
                        // 其他类型：精确匹配
                        $whereClause = "main.{$safeField} = ?";
                        logger()->info("[UniversalCrudService] 应用查询条件（单个-普通字段-db_type=其他）: {$field}", [
                            'where_clause' => $whereClause,
                            'value' => $value,
                            'db_type' => $dbType,
                            'form_type' => $formType,
                        ]);
                        $query->where("main.{$safeField}", $value);
                    }
                }
            }
        }
        
        // 记录处理完成
        logger()->info('[UniversalCrudService] applyFilters 完成', [
            'processed_fields' => array_keys($filters),
        ]);
    }
    
    /**
     * 获取字段配置
     *
     * @param string $fieldName 字段名
     * @param array $fieldsConfig 字段配置数组
     * @param array $columns 列配置数组
     * @return array|null 字段配置
     */
    protected function getFieldConfig(string $fieldName, array $fieldsConfig, array $columns): ?array
    {
        // 优先从 fields 配置中查找
        foreach ($fieldsConfig as $field) {
            if (($field['name'] ?? '') === $fieldName) {
                return $field;
            }
        }
        
        // 从 columns 配置中查找
        foreach ($columns as $column) {
            $colFieldName = $column['name'] ?? $column['field'] ?? '';
            if ($colFieldName === $fieldName) {
                return $column;
            }
        }
        
        return null;
    }
    
    /**
     * 判断是否是字符串类型
     *
     * @param string $dbType 数据库类型
     * @return bool
     */
    protected function isStringType(string $dbType): bool
    {
        return $this->crudService->isStringType($dbType);
    }
    
    /**
     * 判断是否是数字类型
     *
     * @param string $dbType 数据库类型
     * @return bool
     */
    protected function isNumericType(string $dbType): bool
    {
        return $this->crudService->isNumericType($dbType);
    }
    
    /**
     * 判断是否是日期类型
     *
     * @param string $dbType 数据库类型
     * @param string|null $formType 表单类型
     * @return bool
     */
    protected function isDateType(string $dbType, ?string $formType): bool
    {
        return $this->crudService->isDateType($dbType, $formType);
    }

    /**
     * 获取可列出的字段列表
     *
     * 只返回配置为可列出（listable=true）的字段名
     * 必须包含 id 字段（用于操作）
     * 同时包含搜索字段和排序字段（即使它们不在可列出字段中）
     *
     * @param array $config 模型配置
     * @param array $params 查询参数（用于提取搜索和排序字段）
     * @return array 字段名数组
     */
    protected function getListableFields(array $config, array $params = []): array
    {
        $fields = ['id']; // 必须包含 id

        // 从列配置中提取可列出的字段
        if (!empty($config['columns'])) {
            foreach ($config['columns'] as $column) {
                $fieldName = $column['name'] ?? '';
                if (empty($fieldName)) {
                    continue;
                }

                // 检查是否可列出
                $listable = $column['listable'] ?? $column['show_in_list'] ?? true;

                if ($listable && $fieldName !== 'id') {
                    $fields[] = $fieldName;
                }
            }
        }

        // 构建配置字段名映射，用于验证字段是否存在
        $configFieldNames = [];
        if (!empty($config['columns'])) {
            foreach ($config['columns'] as $column) {
                $fieldName = $column['name'] ?? '';
                if (!empty($fieldName)) {
                    $configFieldNames[] = $fieldName;
                }
            }
        }
        
        // 添加关联字段名（关联字段可能不在 columns 中，但在 relations 中）
        if (!empty($config['relations'])) {
            foreach (array_keys($config['relations']) as $relationField) {
                if (!in_array($relationField, $configFieldNames)) {
                    $configFieldNames[] = $relationField;
                }
            }
        }

        // 添加搜索字段（如果使用了搜索功能）
        if (!empty($params['keyword']) && !empty($config['search_fields'])) {
            foreach ($config['search_fields'] as $searchField) {
                // 验证搜索字段是否在配置中定义
                if (!in_array($searchField, $configFieldNames)) {
                    logger()->warning('[UniversalCrudService] 搜索字段不在配置中，已忽略', [
                        'field' => $searchField,
                        'available_fields' => $configFieldNames,
                    ]);
                    continue; // 跳过不存在的字段
                }
                if (!in_array($searchField, $fields)) {
                    $fields[] = $searchField;
                }
            }
        }

        // 添加排序字段（如果不在列表中）
        $sortField = $params['sort_field'] ?? ($config['default_sort_field'] ?? 'id');
        // 验证排序字段是否在配置中定义（id 字段始终允许）
        if ($sortField !== 'id' && !in_array($sortField, $configFieldNames)) {
            logger()->warning('[UniversalCrudService] 排序字段不在配置中，使用默认字段 id', [
                'requested_field' => $sortField,
                'available_fields' => $configFieldNames,
            ]);
            $sortField = 'id'; // 回退到默认字段
        }
        if (!in_array($sortField, $fields)) {
            $fields[] = $sortField;
        }

        // 添加过滤字段（如果使用了过滤功能）
        // 注意：$configFieldNames 已在上面构建
        if (!empty($params['filters']) && is_array($params['filters'])) {
            foreach (array_keys($params['filters']) as $filterField) {
                // 处理区间字段：将 view_count_min/view_count_max 转换为 view_count
                $baseField = $filterField;
                
                // 如果字段以 _min 或 _max 结尾，提取基础字段名
                if (str_ends_with($filterField, '_min')) {
                    $baseField = substr($filterField, 0, -4); // 移除 '_min' 后缀
                } elseif (str_ends_with($filterField, '_max')) {
                    $baseField = substr($filterField, 0, -4); // 移除 '_max' 后缀
                }
                
                // 验证字段是否在配置中定义（防止 SQL 注入和字段不存在错误）
                if (!in_array($baseField, $configFieldNames)) {
                    logger()->warning('[UniversalCrudService] 过滤字段不在配置中，已忽略', [
                        'field' => $baseField,
                        'filter_field' => $filterField,
                        'available_fields' => $configFieldNames,
                    ]);
                    continue; // 跳过不存在的字段
                }
                
                // 只添加基础字段名（不添加 _min 和 _max 字段）
                if (!in_array($baseField, $fields)) {
                    $fields[] = $baseField;
                }
            }
        }

        return array_unique($fields);
    }

    /**
     * 查找单条记录
     */
    public function find(string $model, int $id): ?array
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);

        return $this->crudService->find($tableName, $id, [
            'has_site_id' => !empty($config['has_site_id']),
            'connection' => $this->getConnectionName($config),
        ]);
    }

    /**
     * 创建记录
     */
    public function create(string $model, array $data): int
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);

        // 过滤字段
        $data = $this->filterFields($model, $data);

        return $this->crudService->create($tableName, $data, [
            'fillable' => $config['fillable'] ?? null,
            'has_site_id' => !empty($config['has_site_id']),
            'timestamps' => !empty($config['timestamps']),
            'connection' => $this->getConnectionName($config),
        ]);
    }

    /**
     * 更新记录
     */
    public function update(string $model, int $id, array $data): bool
    {
        // 验证 ID
        $this->validateId($id);

        // 验证数据不为空
        if (empty($data)) {
            throw new \InvalidArgumentException('更新数据不能为空');
        }

        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);

        // 验证表名
        $this->validateTableName($tableName);

        // 过滤字段
        $data = $this->filterFields($model, $data);

        // 验证过滤后的数据不为空
        if (empty($data)) {
            throw new \InvalidArgumentException('过滤后的更新数据为空，请检查 fillable 配置');
        }

        // 将空字符串转换为 null
        $data = $this->convertEmptyStringsToNull($data);

        // 自动更新时间戳
        if (!empty($config['timestamps'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        return $this->crudService->update($tableName, $id, $data, [
            'fillable' => $config['fillable'] ?? null,
            'has_site_id' => !empty($config['has_site_id']),
            'timestamps' => !empty($config['timestamps']),
            'connection' => $this->getConnectionName($config),
        ]);
    }

    /**
     * 删除记录
     * 
     * 支持软删除和硬删除：
     * - 优先尝试使用模型类（如果存在），可以自动检测 SoftDeletes trait
     * - 如果模型类不存在，使用表名进行删除
     * - 根据配置和模型 trait 自动判断使用软删除还是硬删除
     */
    public function delete(string $model, int $id): bool
    {
        $config = $this->getModelConfig($model);
        
        // 优先尝试使用模型类（如果存在）
        // 这样可以自动检测 SoftDeletes trait，无需依赖配置
        $modelClass = null;
        try {
            $modelClass = $this->getModelClass($model);
            if (!empty($modelClass) && class_exists($modelClass)) {
                // 使用模型类进行删除，CrudService 会自动检测 SoftDeletes trait
                return $this->crudService->delete($modelClass, $id, [
                    'has_site_id' => !empty($config['has_site_id']),
                    'soft_delete' => !empty($config['soft_delete']),
                ]);
            }
        } catch (\Throwable $e) {
            // 如果获取模型类失败，记录日志但继续使用表名方式
            logger()->debug('[UniversalCrudService] 无法使用模型类删除，回退到表名方式', [
                'model' => $model,
                'model_class' => $modelClass ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }

        // 回退到使用表名方式
        $tableName = $this->getTableName($model);
        return $this->crudService->delete($tableName, $id, [
            'has_site_id' => !empty($config['has_site_id']),
            'soft_delete' => !empty($config['soft_delete']),
            'connection' => $this->getConnectionName($config),
        ]);
    }

    /**
     * 批量删除
     */
    public function batchDelete(string $model, array $ids): int
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);

        return $this->crudService->batchDelete($tableName, $ids, [
            'has_site_id' => !empty($config['has_site_id']),
            'soft_delete' => !empty($config['soft_delete']),
            'connection' => $this->getConnectionName($config),
        ]);
    }

    /**
     * 切换字段值（如状态）
     */
    public function toggleField(string $model, int $id, string $field): bool
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);

        return $this->crudService->toggleField($tableName, $id, $field, [
            'has_site_id' => !empty($config['has_site_id']),
            'connection' => $this->getConnectionName($config),
        ]);
    }

    /**
     * 获取表列信息
     *
     * 优先使用配置中的列定义，否则从数据库读取
     */
    public function getTableColumns(string $model): array
    {
        $config = $this->getModelConfig($model);

        // 如果配置中定义了列，直接返回
        if (!empty($config['columns'])) {
            return $config['columns'];
        }

        // 否则从数据库读取
        $tableName = $this->getTableName($model);
        return $this->getTableColumnsFromDatabase($tableName, $config);
    }

    /**
     * 获取表单字段
     */
    public function getFormFields(string $model, string $scene = 'create'): array
    {
        $config = $this->getModelConfig($model);

        // 如果配置中定义了表单字段，直接返回
        if (!empty($config['fields'])) {
            return $this->filterFieldsByScene($config['fields'], $scene);
        }

        // 否则从数据库读取并自动生成
        $tableName = $this->getTableName($model);
        $columns = $this->getTableColumnsFromDatabase($tableName, $config);

        return $this->generateFormFieldsFromColumns($columns, $scene);
    }

    /**
     * 获取关联选项数据
     */
    public function getRelationOptions(string $model): array
    {
        $config = $this->getModelConfig($model);
        $relations = $config['relations'] ?? [];
        $connectionName = $this->getConnectionName($config);
        $connection = $this->getConnection($config);
        $currentSiteId = site_id();

        $options = [];
        foreach ($relations as $field => $relation) {
            $relationTable = $relation['table'] ?? '';
            $labelField = $relation['label_field'] ?? 'name';
            $valueField = $relation['value_field'] ?? 'id';

            if ($relationTable) {
                $query = $connection->table($relationTable);

                // 如果关联表也有站点过滤（超级管理员跳过）
                if (!is_super_admin()) {
                    $this->applyRelationSiteFilter(
                        $query,
                        !empty($relation['has_site_id']),
                        $currentSiteId,
                        $connectionName,
                        $relationTable
                    );
                }

                $options[$field] = $query->select($valueField . ' as value', $labelField . ' as label')->get();
            }
        }

        return $options;
    }

    /**
     * 获取验证规则
     */
    public function getValidationRules(string $model, string $scene = 'create', ?int $id = null): array
    {
        $config = $this->getModelConfig($model);
        $rules = $config['validation'][$scene] ?? [];

        // 替换唯一规则中的 ID（编辑时排除自己）
        if ($scene === 'update' && $id) {
            foreach ($rules as $field => &$rule) {
                if (is_string($rule) && str_contains($rule, 'unique:')) {
                    $rule = str_replace('unique:', "unique:,{$field},{$id}|", $rule);
                }
            }
        }

        return $rules;
    }

    /**
     * 过滤字段（只保留允许的字段）
     */
    protected function filterFields(string $model, array $data): array
    {
        $config = $this->getModelConfig($model);
        $fillable = $config['fillable'] ?? [];

        // 特殊保护：Site 模型的 domain 和 admin_entry_path 字段禁止修改
        $protected = [];
        if (in_array(strtolower($model), ['site', 'admin_site', 'admin_sites', 'sites'])) {
            $protected = ['domain', 'admin_entry_path'];
        }

        // 移除受保护的字段
        foreach ($protected as $field) {
            unset($data[$field]);
        }

        // 如果没有定义 fillable，允许所有字段（除了 id）
        if (empty($fillable)) {
            unset($data['id']);
            return $data;
        }

        // 只保留 fillable 中定义的字段
        $filtered = array_filter($data, function ($key) use ($fillable) {
            return in_array($key, $fillable);
        }, ARRAY_FILTER_USE_KEY);

        return $filtered;
    }

    /**
     * 将空字符串转换为 null
     *
     * @param array $data
     * @return array
     */
    protected function convertEmptyStringsToNull(array $data): array
    {
        foreach ($data as $key => $value) {
            // 如果值是空字符串，转换为 null
            if ($value === '') {
                $data[$key] = null;
            }
            // 如果是数组，递归处理（但数组本身不为空时不转换）
            elseif (is_array($value) && !empty($value)) {
                $data[$key] = $this->convertEmptyStringsToNull($value);
            }
        }

        return $data;
    }

    /**
     * 从数据库获取表列信息
     */
    protected function getTableColumnsFromDatabase(string $tableName, array $config): array
    {
        return $this->crudService->getTableColumnsFromDatabase(
            $tableName,
            $this->getConnectionName($config)
        );
    }

    /**
     * 从列信息生成表单字段
     */
    protected function generateFormFieldsFromColumns(array $columns, string $scene): array
    {
        $fields = [];
        $skipFields = ['id', 'created_at', 'updated_at', 'deleted_at'];

        foreach ($columns as $column) {
            $name = $column['name'];

            // 跳过不需要在表单中显示的字段
            if (in_array($name, $skipFields)) {
                continue;
            }

            // 编辑场景下跳过某些字段
            if ($scene === 'edit' && in_array($name, ['password'])) {
                continue;
            }

            $fields[] = [
                'name' => $name,
                'label' => $column['label'],
                'type' => $this->guessFormFieldType($column),
                'required' => !$column['nullable'],
                'comment' => $column['comment'],
            ];
        }

        return $fields;
    }

    /**
     * 解析数据库列类型
     */
    protected function parseColumnType(string $type): string
    {
        return $this->crudService->parseColumnType($type);
    }

    /**
     * 猜测字段标签
     */
    protected function guessFieldLabel(string $fieldName, string $comment): string
    {
        return $this->crudService->guessFieldLabel($fieldName, $comment);
    }

    /**
     * 猜测表单字段类型
     */
    protected function guessFormFieldType(array $column): string
    {
        return $this->crudService->guessFormFieldType($column);
    }

    /**
     * 根据场景过滤字段
     * 
     * @param array $fields 字段配置数组
     * @param string $scene 场景：create 或 edit
     * @return array 过滤后的字段数组
     */
    protected function filterFieldsByScene(array $fields, string $scene): array
    {
        $filtered = array_filter($fields, function ($field) use ($scene) {
            // 1. 场景过滤：如果字段定义了 scenes，只显示在指定场景中
            if (isset($field['scenes'])) {
                if (!in_array($scene, $field['scenes'])) {
                    return false;
                }
            }
            
            // 2. editable 字段过滤：创建和编辑场景都只显示可编辑的字段
            // editable 字段已经在 extractFormFields 中被规范化为布尔值或 null
            // 只有 editable === true 或 editable 未设置的字段才显示
            // 如果 editable === false，则隐藏该字段
            
            // 如果 editable === false，无论是创建还是编辑场景都不显示
            if (isset($field['editable']) && $field['editable'] === false) {
                return false;
            }
            
            // 编辑场景：只显示 editable === true 的字段
            if ($scene === 'edit') {
                // 调试：记录 user_ids 字段的过滤过程
                if (isset($field['name']) && $field['name'] === 'user_ids') {
                    logger()->info('过滤 user_ids 字段', [
                        'scene' => $scene,
                        'editable_exists' => isset($field['editable']),
                        'editable_value' => $field['editable'] ?? 'NOT_SET',
                        'editable_type' => gettype($field['editable'] ?? null),
                        'editable_strict_true' => ($field['editable'] ?? null) === true,
                        'will_show' => isset($field['editable']) && $field['editable'] === true,
                    ]);
                }
                
                // 编辑场景：只显示 editable === true 的字段
                if (!isset($field['editable']) || $field['editable'] !== true) {
                    return false;
                }
                return true;
            }
            
            // 创建场景：显示 editable !== false 的字段（包括 editable === true 和 editable 未设置）
            // 已经在上面过滤了 editable === false 的情况，这里直接返回 true
            return true;
        });
        
        // 重新索引数组，确保索引从 0 开始连续（保持原始顺序）
        return array_values($filtered);
    }

    /**
     * 猜测模型类名
     */
    protected function guessModelClass(string $model): string
    {
        // 将表名转换为类名
        // admin_users -> AdminUser
        $parts = explode('_', $model);
        $className = implode('', array_map('ucfirst', $parts));

        return "App\\Model\\Admin\\{$className}";
    }

    /**
     * 生成自动配置
     *
     * 当找不到数据库配置和配置文件时，尝试自动生成基础配置
     *
     * 转换示例：
     * - 'fund-brand' -> 表名 'fund_brand'
     * - 'admin-users' -> 表名 'admin_users'
     * - 'system-config' -> 表名 'system_config'
     *
     * 注意：
     * - 路由参数不会自动添加 admin_ 前缀
     * - 如果需要 admin_ 前缀，请在路由参数中显式包含
     */
    protected function generateAutoConfig(string $model): array
    {
        // 将路由参数转换为表名
        $tableName = $this->convertRouteParamToTableName($model);
        $modelClass = $this->guessModelClass($model);

        return [
            'table' => $tableName,
            'db_connection' => 'default',
            'model_class' => $modelClass,
            'title' => $model,
            'timestamps' => true,
            'soft_delete' => false,
            'has_site_id' => false,
            'search_fields' => ['id'],
            'default_sort_field' => 'id',
            'default_sort_order' => 'desc',
        ];
    }

    /**
     * 获取配置所使用的数据库连接名称
     */
    protected function getConnectionName(array $config): string
    {
        $connection = $config['db_connection'] ?? $config['connection'] ?? null;
        if (is_string($connection) && $connection !== '') {
            return $connection;
        }

        return 'default';
    }

    /**
     * 根据配置获取数据库连接实例
     */
    protected function getConnection(array $config)
    {
        $connectionName = $this->getConnectionName($config);
        return Db::connection($connectionName);
    }

    /**
     * 针对关联表应用站点过滤（仅当表真实存在 site_id 字段时）
     *
     * @param \Hyperf\Database\Query\Builder|\Hyperf\DbConnection\Query\Builder $query
     */
    protected function applyRelationSiteFilter($query, bool $hasSiteId, ?int $siteId, string $connectionName, ?string $table = null): void
    {
        if (! $this->shouldApplyRelationSiteFilter($hasSiteId, $siteId, $connectionName, $table)) {
            return;
        }

        $query->where('site_id', $siteId);
    }

    protected function shouldApplyRelationSiteFilter(bool $hasSiteId, ?int $siteId, string $connectionName, ?string $table = null): bool
    {
        if (! $hasSiteId || $siteId === null || $siteId <= 0) {
            return false;
        }

        if (empty($table)) {
            return true;
        }

        return $this->tableHasSiteIdColumn($table, $connectionName);
    }

    protected function tableHasSiteIdColumn(string $table, string $connectionName): bool
    {
        $normalizedTable = $this->normalizeTableName($table);
        if ($normalizedTable === '') {
            return false;
        }

        $cacheKey = "{$connectionName}.{$normalizedTable}";
        if (! array_key_exists($cacheKey, $this->tableSiteIdColumnCache)) {
            try {
                $this->tableSiteIdColumnCache[$cacheKey] = Schema::connection($connectionName)
                    ->hasColumn($normalizedTable, 'site_id');
            } catch (\Throwable $throwable) {
                logger()->warning('[UniversalCrudService] 检查表的 site_id 字段失败', [
                    'table' => $normalizedTable,
                    'connection' => $connectionName,
                    'error' => $throwable->getMessage(),
                ]);
                $this->tableSiteIdColumnCache[$cacheKey] = false;
            }
        }

        return $this->tableSiteIdColumnCache[$cacheKey];
    }

    protected function normalizeTableName(string $table): string
    {
        $table = trim($table);
        if ($table === '') {
            return '';
        }

        // 去掉 AS/别名部分
        if (str_contains(strtolower($table), ' as ')) {
            $table = preg_split('/\s+as\s+/i', $table)[0] ?? $table;
        }

        // 去掉多余空格和反引号
        $table = trim($table);
        $parts = preg_split('/\s+/', $table);
        $table = $parts[0] ?? $table;

        return trim($table, '`');
    }

    /**
     * 将路由参数转换为数据库表名
     *
     * 转换规则：
     * - 连字符转换为下划线：'fund-brand' -> 'fund_brand'
     * - 驼峰命名转换为蛇形命名：'FundBrand' -> 'fund_brand'
     * - 保持原有前缀：'admin-fund-brand' -> 'admin_fund_brand'
     *
     * @param string $routeParam 路由参数
     * @return string 数据库表名
     *
     * @example
     * convertRouteParamToTableName('fund-brand') => 'fund_brand'
     * convertRouteParamToTableName('admin-fund-brand') => 'admin_fund_brand'
     * convertRouteParamToTableName('FundBrand') => 'fund_brand'
     * convertRouteParamToTableName('users') => 'users'
     */
    protected function convertRouteParamToTableName(string $routeParam): string
    {
        return $this->crudService->convertRouteParamToTableName($routeParam);
    }

    /**
     * 将路由参数转换为模型名（向后兼容）
     *
     * 注意：推荐直接在路由中使用完整的模型名（如 AdminUser）
     * 此方法主要用于向后兼容，将旧格式的路由参数转换为模型名
     *
     * 转换规则：
     * - 连字符/下划线转换为驼峰：admin_users -> AdminUsers
     * - 自动添加 Admin 前缀：users -> AdminUsers
     *
     * 示例：
     * - admin_users -> AdminUsers（注意：不会自动单数化）
     * - fund-brand -> AdminFundBrand
     * - users -> AdminUsers
     *
     * @param string $routeParam 路由参数
     * @return string 模型名
     */
    protected function convertRouteParamToModelName(string $routeParam): string
    {
        return $this->crudService->convertRouteParamToModelName($routeParam);
    }

    /**
     * 恢复记录（将 deleted_at 设置为 null）
     */
    public function restore(string $model, int $id): bool
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);
        $connection = $this->getConnection($config);

        // 检查是否启用软删除
        if (empty($config['soft_delete'])) {
            throw new \RuntimeException('该模型未启用软删除功能');
        }

        // 使用 DB 恢复
        $query = $connection->table($tableName)->where('id', $id)->whereNotNull('deleted_at');

        // 添加站点过滤（超级管理员跳过）
        $hasSiteId = !empty($config['has_site_id']);
        $siteId = site_id();
        if ($hasSiteId && $siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->update(['deleted_at' => null]) > 0;
    }

    /**
     * 永久删除记录
     */
    public function forceDelete(string $model, int $id): bool
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);
        $connection = $this->getConnection($config);

        // 使用 DB 永久删除
        $query = $connection->table($tableName)->where('id', $id)->whereNotNull('deleted_at');

        // 添加站点过滤（超级管理员跳过）
        $hasSiteId = !empty($config['has_site_id']);
        $siteId = site_id();
        if ($hasSiteId && $siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->delete() > 0;
    }

    /**
     * 批量恢复
     */
    public function batchRestore(string $model, array $ids): int
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);
        $connection = $this->getConnection($config);

        // 检查是否启用软删除
        if (empty($config['soft_delete'])) {
            throw new \RuntimeException('该模型未启用软删除功能');
        }

        if (empty($ids)) {
            return 0;
        }

        // 验证 ID 数组
        $maxCount = 100;
        $this->validateIds($ids, $maxCount);
        
        // 去重
        $ids = array_values(array_unique($ids));

        // 使用 DB 批量恢复
        $query = $connection->table($tableName)->whereIn('id', $ids)->whereNotNull('deleted_at');

        // 添加站点过滤（超级管理员跳过）
        $hasSiteId = !empty($config['has_site_id']);
        $siteId = site_id();
        if ($hasSiteId && $siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->update(['deleted_at' => null]);
    }

    /**
     * 批量永久删除
     */
    public function batchForceDelete(string $model, array $ids): int
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);
        $connection = $this->getConnection($config);

        if (empty($ids)) {
            return 0;
        }

        // 验证 ID 数组
        $maxCount = 100;
        $this->validateIds($ids, $maxCount);
        
        // 去重
        $ids = array_values(array_unique($ids));

        // 使用 DB 批量永久删除
        $query = $connection->table($tableName)->whereIn('id', $ids)->whereNotNull('deleted_at');

        // 添加站点过滤（超级管理员跳过）
        $hasSiteId = !empty($config['has_site_id']);
        $siteId = site_id();
        if ($hasSiteId && $siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->delete();
    }

    /**
     * 清空回收站（永久删除所有已删除的记录）
     */
    public function clearTrash(string $model): int
    {
        $tableName = $this->getTableName($model);
        $config = $this->getModelConfig($model);
        $connection = $this->getConnection($config);

        // 检查是否启用软删除
        if (empty($config['soft_delete'])) {
            throw new \RuntimeException('该模型未启用软删除功能');
        }

        // 使用 DB 永久删除所有已删除的记录
        $query = $connection->table($tableName)->whereNotNull('deleted_at');

        // 添加站点过滤（超级管理员跳过）
        $hasSiteId = !empty($config['has_site_id']);
        $siteId = site_id();
        if ($hasSiteId && $siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->delete();
    }

    /**
     * 验证 ID 数组
     */
    protected function validateIds(array $ids, int $maxCount = 100): void
    {
        if (empty($ids)) {
            throw new \InvalidArgumentException('ID 数组不能为空');
        }

        if (count($ids) > $maxCount) {
            throw new \InvalidArgumentException("批量操作最多支持 {$maxCount} 条记录");
        }

        foreach ($ids as $id) {
            if (!is_numeric($id) || (int)$id <= 0) {
                throw new \InvalidArgumentException('ID 必须为正整数');
            }
        }
    }
}

