<?php

declare(strict_types=1);

namespace App\Service\Admin;

use Hyperf\Database\Model\Model;
use Hyperf\DbConnection\Db;

/**
 * 通用 CRUD 服务
 *
 * 提供基础的增删改查操作和工具方法，供后台控制器使用
 * 
 * 支持两种方式：
 * 1. 使用表名字符串（如 'admin_users'）
 * 2. 使用 Model 类名或实例（如 AdminUser::class 或 new AdminUser()）
 *
 * @package App\Service\Admin
 */
class CrudService
{
    /**
     * 解析 Model 类或表名
     *
     * @param string|object $modelOrTable Model 类名、实例或表名
     * @return array{model: string|null, table: string, isModel: bool}
     * @throws \InvalidArgumentException 如果参数不合法
     */
    protected function resolveModelOrTable(string|object $modelOrTable): array
    {
        // 如果是对象（Model 实例）
        if (is_object($modelOrTable)) {
            if ($modelOrTable instanceof Model) {
                return [
                    'model' => get_class($modelOrTable),
                    'table' => $modelOrTable->getTable(),
                    'isModel' => true,
                ];
            }
            throw new \InvalidArgumentException('对象必须是 Model 实例');
        }

        // 如果是字符串，判断是类名还是表名
        if (is_string($modelOrTable)) {
            // 检查是否是完整的类名（包含命名空间）
            if (class_exists($modelOrTable) && is_subclass_of($modelOrTable, Model::class)) {
                $modelInstance = new $modelOrTable();
                return [
                    'model' => $modelOrTable,
                    'table' => $modelInstance->getTable(),
                    'isModel' => true,
                ];
            }

            // 尝试猜测 Model 类名（App\Model\Admin\{ClassName}）
            $guessedModelClass = $this->guessModelClass($modelOrTable);
            if (class_exists($guessedModelClass) && is_subclass_of($guessedModelClass, Model::class)) {
                $modelInstance = new $guessedModelClass();
                return [
                    'model' => $guessedModelClass,
                    'table' => $modelInstance->getTable(),
                    'isModel' => true,
                ];
            }

            // 否则当作表名处理，需要验证表名格式
            $this->validateTableName($modelOrTable);
            
            return [
                'model' => null,
                'table' => $modelOrTable,
                'isModel' => false,
            ];
        }

        throw new \InvalidArgumentException('参数必须是字符串（表名或类名）或 Model 实例');
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
            logger()->warning('[CrudService] 非法的表名格式', [
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
     * 验证并转义表名（用于原生 SQL）
     *
     * @param string $tableName 表名
     * @return string 转义后的表名
     * @throws \InvalidArgumentException 如果表名不合法
     */
    protected function escapeTableName(string $tableName): string
    {
        $this->validateTableName($tableName);
        return "`{$tableName}`";
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
     * 验证字段名格式，防止 SQL 注入
     *
     * @param string $fieldName 字段名
     * @param string $paramName 参数名称（用于错误信息）
     * @throws \InvalidArgumentException 如果字段名不合法
     */
    protected function validateFieldName(string $fieldName, string $paramName = 'field'): void
    {
        // 字段名只能包含字母、数字、下划线和连字符
        // 长度限制：1-64 个字符（MySQL 限制）
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $fieldName)) {
            logger()->warning('[CrudService] 非法的字段名格式', [
                'field_name' => $fieldName,
                'param_name' => $paramName,
            ]);
            throw new \InvalidArgumentException("非法的字段名格式: {$fieldName}");
        }
    }

    /**
     * 验证批量操作的 ID 数组
     *
     * @param array $ids ID 数组
     * @param int $maxCount 最大允许数量（默认：100）
     * @throws \InvalidArgumentException 如果 ID 数组不合法
     */
    protected function validateIds(array $ids, int $maxCount = 100): void
    {
        if (empty($ids)) {
            throw new \InvalidArgumentException('ID 数组不能为空');
        }

        // 限制批量操作的最大数量，防止 DOS 攻击
        if (count($ids) > $maxCount) {
            throw new \InvalidArgumentException("批量操作数量不能超过 {$maxCount} 条，当前: " . count($ids));
        }

        // 验证每个 ID
        foreach ($ids as $id) {
            if (!is_int($id)) {
                throw new \InvalidArgumentException("ID 必须是整数，当前值: " . gettype($id));
            }
            $this->validateId($id, 'id');
        }

        // 去重，防止重复操作
        $uniqueIds = array_unique($ids);
        if (count($uniqueIds) !== count($ids)) {
            logger()->warning('[CrudService] 批量操作 ID 数组包含重复值，已自动去重', [
                'original_count' => count($ids),
                'unique_count' => count($uniqueIds),
            ]);
        }
    }

    /**
     * 从表名猜测 Model 类名
     *
     * @param string $tableName 表名
     * @return string Model 类名
     */
    protected function guessModelClass(string $tableName): string
    {
        // 将表名转换为类名
        // admin_users -> AdminUser
        $parts = explode('_', $tableName);
        $className = implode('', array_map('ucfirst', $parts));

        return "App\\Model\\Admin\\{$className}";
    }
    /**
     * 查找单条记录
     *
     * @param string|object $modelOrTable Model 类名、实例或表名
     * @param int $id 记录ID
     * @param array $options 选项配置
     *   - has_site_id: bool 是否启用站点过滤（默认：false）
     *   - site_id: int|null 站点ID（默认：自动获取）
     * @return array|null
     */
    public function find(string|object $modelOrTable, int $id, array $options = []): ?array
    {
        // 验证 ID
        $this->validateId($id);

        $resolved = $this->resolveModelOrTable($modelOrTable);
        $hasSiteId = $options['has_site_id'] ?? false;
        $siteId = $options['site_id'] ?? site_id();

        if ($resolved['isModel']) {
            // 使用 Model 查询
            $modelClass = $resolved['model'];
            $query = $modelClass::query()->where('id', $id);

            // 添加站点过滤（超级管理员跳过）
            if ($hasSiteId && $siteId && !is_super_admin()) {
                $query->where('site_id', $siteId);
            }

            $result = $query->first();
            return $result ? $result->toArray() : null;
        }

        // 使用 DB 查询
        $query = Db::table($resolved['table'])->where('id', $id);

        // 添加站点过滤（超级管理员跳过）
        if ($hasSiteId && $siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        $result = $query->first();
        return $result ? (array) $result : null;
    }

    /**
     * 创建记录
     *
     * @param string|object $modelOrTable Model 类名、实例或表名
     * @param array $data 数据
     * @param array $options 选项配置
     *   - fillable: array 允许填充的字段列表（默认：允许所有字段）
     *   - has_site_id: bool 是否自动添加站点ID（默认：false）
     *   - site_id: int|null 站点ID（默认：自动获取）
     *   - timestamps: bool 是否自动添加时间戳（默认：false）
     * @return int 新插入记录的ID
     */
    public function create(string|object $modelOrTable, array $data, array $options = []): int
    {
        // 验证数据不为空
        if (empty($data)) {
            throw new \InvalidArgumentException('创建数据不能为空');
        }

        $resolved = $this->resolveModelOrTable($modelOrTable);

        // 过滤字段
        $fillable = $options['fillable'] ?? null;
        
        // 如果使用 Model 且没有指定 fillable，尝试从 Model 获取
        if ($resolved['isModel'] && $fillable === null) {
            $modelClass = $resolved['model'];
            $modelInstance = new $modelClass();
            if (!empty($modelInstance->getFillable())) {
                $fillable = $modelInstance->getFillable();
            }
        }
        
        if ($fillable !== null) {
            $data = $this->filterFields($data, $fillable);
        } else {
            // 如果没有定义 fillable，移除 id 字段
            unset($data['id']);
        }

        // 验证过滤后的数据不为空
        if (empty($data)) {
            throw new \InvalidArgumentException('过滤后的创建数据为空，请检查 fillable 配置');
        }

        // 将空字符串转换为 null
        $data = $this->convertEmptyStringsToNull($data);

        // 自动添加站点 ID（超级管理员跳过）
        $hasSiteId = $options['has_site_id'] ?? false;
        $siteId = $options['site_id'] ?? site_id();
        if ($hasSiteId && $siteId && !is_super_admin()) {
            $data['site_id'] = $siteId;
        }

        // 自动添加时间戳
        if (!empty($options['timestamps'])) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        if ($resolved['isModel']) {
            // 使用 Model 创建（会自动使用 fillable 属性）
            $modelClass = $resolved['model'];
            $model = new $modelClass($data);
            $model->save();
            return $model->id;
        }

        // 使用 DB 插入
        return (int) Db::table($resolved['table'])->insertGetId($data);
    }

    /**
     * 更新记录
     *
     * @param string|object $modelOrTable Model 类名、实例或表名
     * @param int $id 记录ID
     * @param array $data 数据
     * @param array $options 选项配置
     *   - fillable: array 允许填充的字段列表（默认：允许所有字段）
     *   - has_site_id: bool 是否启用站点过滤（默认：false）
     *   - site_id: int|null 站点ID（默认：自动获取）
     *   - timestamps: bool 是否自动更新时间戳（默认：false）
     * @return bool 是否更新成功
     */
    public function update(string|object $modelOrTable, int $id, array $data, array $options = []): bool
    {
        // 验证 ID
        $this->validateId($id);

        // 验证数据不为空
        if (empty($data)) {
            throw new \InvalidArgumentException('更新数据不能为空');
        }

        $resolved = $this->resolveModelOrTable($modelOrTable);

        // 过滤字段
        $fillable = $options['fillable'] ?? null;
        
        // 如果使用 Model 且没有指定 fillable，尝试从 Model 获取
        if ($resolved['isModel'] && $fillable === null) {
            $modelClass = $resolved['model'];
            $modelInstance = new $modelClass();
            if (!empty($modelInstance->getFillable())) {
                $fillable = $modelInstance->getFillable();
            }
        }
        
        if ($fillable !== null) {
            $data = $this->filterFields($data, $fillable);
        } else {
            // 如果没有定义 fillable，移除 id 字段
            unset($data['id']);
        }

        // 验证过滤后的数据不为空
        if (empty($data)) {
            throw new \InvalidArgumentException('过滤后的更新数据为空，请检查 fillable 配置');
        }

        // 将空字符串转换为 null
        $data = $this->convertEmptyStringsToNull($data);

        // 自动更新时间戳
        if (!empty($options['timestamps'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        if ($resolved['isModel']) {
            // 使用 Model 更新
            $modelClass = $resolved['model'];
            $query = $modelClass::query()->where('id', $id);

            // 添加站点过滤（超级管理员跳过）
            $hasSiteId = $options['has_site_id'] ?? false;
            $siteId = $options['site_id'] ?? site_id();
            if ($hasSiteId && $siteId && !is_super_admin()) {
                $query->where('site_id', $siteId);
            }

            $model = $query->first();
            if (!$model) {
                return false;
            }

            // Model 的 fill() 方法会自动使用 fillable 属性
            $model->fill($data);
            return $model->save();
        }

        // 使用 DB 更新
        $query = Db::table($resolved['table'])->where('id', $id);

        // 添加站点过滤（超级管理员跳过）
        $hasSiteId = $options['has_site_id'] ?? false;
        $siteId = $options['site_id'] ?? site_id();
        if ($hasSiteId && $siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        return $query->update($data) > 0;
    }

    /**
     * 删除记录
     *
     * @param string|object $modelOrTable Model 类名、实例或表名
     * @param int $id 记录ID
     * @param array $options 选项配置
     *   - has_site_id: bool 是否启用站点过滤（默认：false）
     *   - site_id: int|null 站点ID（默认：自动获取）
     *   - soft_delete: bool 是否使用软删除（默认：false，Model 会自动检测是否使用软删除）
     * @return bool 是否删除成功
     */
    public function delete(string|object $modelOrTable, int $id, array $options = []): bool
    {
        // 验证 ID
        $this->validateId($id);

        $resolved = $this->resolveModelOrTable($modelOrTable);

        if ($resolved['isModel']) {
            // 使用 Model 删除
            $modelClass = $resolved['model'];
            $query = $modelClass::query()->where('id', $id);

            // 添加站点过滤（超级管理员跳过）
            $hasSiteId = $options['has_site_id'] ?? false;
            $siteId = $options['site_id'] ?? site_id();
            if ($hasSiteId && $siteId && !is_super_admin()) {
                $query->where('site_id', $siteId);
            }

            $model = $query->first();
            if (!$model) {
                return false;
            }

            // Model 会自动检测是否使用软删除
            // 检查是否使用了 SoftDeletes trait
            $usesSoftDeletes = in_array(
                \Hyperf\Database\Model\SoftDeletes::class,
                class_uses_recursive($model)
            );
            
            if (!empty($options['soft_delete']) || $usesSoftDeletes) {
                return $model->delete(); // Model 的 delete() 会自动处理软删除
            }

            return $model->forceDelete() ?? false;
        }

        // 使用 DB 删除
        $query = Db::table($resolved['table'])->where('id', $id);

        // 添加站点过滤（超级管理员跳过）
        $hasSiteId = $options['has_site_id'] ?? false;
        $siteId = $options['site_id'] ?? site_id();
        if ($hasSiteId && $siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        // 软删除
        if (!empty($options['soft_delete'])) {
            return $query->update(['deleted_at' => date('Y-m-d H:i:s')]) > 0;
        }

        return $query->delete() > 0;
    }

    /**
     * 批量删除
     *
     * @param string|object $modelOrTable Model 类名、实例或表名
     * @param array $ids ID数组
     * @param array $options 选项配置
     *   - has_site_id: bool 是否启用站点过滤（默认：false）
     *   - site_id: int|null 站点ID（默认：自动获取）
     *   - soft_delete: bool 是否使用软删除（默认：false，Model 会自动检测是否使用软删除）
     * @return int 删除的记录数
     */
    public function batchDelete(string|object $modelOrTable, array $ids, array $options = []): int
    {
        if (empty($ids)) {
            return 0;
        }

        // 验证 ID 数组
        $maxCount = $options['max_count'] ?? 100;
        $this->validateIds($ids, $maxCount);
        
        // 去重
        $ids = array_values(array_unique($ids));

        $resolved = $this->resolveModelOrTable($modelOrTable);

        if ($resolved['isModel']) {
            // 使用 Model 批量删除
            $modelClass = $resolved['model'];
            $query = $modelClass::query()->whereIn('id', $ids);

            // 添加站点过滤（超级管理员跳过）
            $hasSiteId = $options['has_site_id'] ?? false;
            $siteId = $options['site_id'] ?? site_id();
            if ($hasSiteId && $siteId && !is_super_admin()) {
                $query->where('site_id', $siteId);
            }

            $models = $query->get();
            if ($models->isEmpty()) {
                return 0;
            }

            $count = 0;
            foreach ($models as $model) {
                // Model 会自动检测是否使用软删除
                // 检查是否使用了 SoftDeletes trait
                $usesSoftDeletes = in_array(
                    \Hyperf\Database\Model\SoftDeletes::class,
                    class_uses_recursive($model)
                );
                
                if (!empty($options['soft_delete']) || $usesSoftDeletes) {
                    if ($model->delete()) {
                        $count++;
                    }
                } else {
                    if ($model->forceDelete()) {
                        $count++;
                    }
                }
            }

            return $count;
        }

        // 使用 DB 批量删除
        $query = Db::table($resolved['table'])->whereIn('id', $ids);

        // 添加站点过滤（超级管理员跳过）
        $hasSiteId = $options['has_site_id'] ?? false;
        $siteId = $options['site_id'] ?? site_id();
        if ($hasSiteId && $siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        // 软删除
        if (!empty($options['soft_delete'])) {
            return $query->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        return $query->delete();
    }

    /**
     * 切换字段值（如状态）
     *
     * @param string|object $modelOrTable Model 类名、实例或表名
     * @param int $id 记录ID
     * @param string $field 字段名
     * @param array $options 选项配置
     * @return bool 是否更新成功
     * @throws \RuntimeException 记录不存在时抛出异常
     */
    public function toggleField(string|object $modelOrTable, int $id, string $field, array $options = []): bool
    {
        // 验证 ID
        $this->validateId($id);

        // 验证字段名
        $this->validateFieldName($field, 'field');

        $record = $this->find($modelOrTable, $id, $options);
        if (!$record) {
            throw new \RuntimeException('记录不存在');
        }

        // 检查字段是否存在
        if (!array_key_exists($field, $record)) {
            throw new \InvalidArgumentException("字段不存在: {$field}");
        }

        $currentValue = $record[$field] ?? 0;
        $newValue = $currentValue ? 0 : 1;

        return $this->update($modelOrTable, $id, [$field => $newValue], $options);
    }

    /**
     * 过滤字段（只保留允许的字段）
     *
     * @param array $data 原始数据
     * @param array $fillable 允许填充的字段列表
     * @param array $protected 受保护的字段（始终不允许修改）
     * @return array 过滤后的数据
     */
    public function filterFields(array $data, array $fillable, array $protected = []): array
    {
        // 移除受保护的字段
        foreach ($protected as $field) {
            unset($data[$field]);
        }

        // 如果没有定义 fillable，允许所有字段（除了受保护的）
        if (empty($fillable)) {
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
     * @param array $data 数据数组
     * @return array 转换后的数据数组
     */
    public function convertEmptyStringsToNull(array $data): array
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
     *
     * @param string $tableName 表名
     * @return array 列信息数组，每个元素包含：name, type, label, comment, nullable, default
     */
    public function getTableColumnsFromDatabase(string $tableName): array
    {
        // 验证并转义表名，防止 SQL 注入
        $this->validateTableName($tableName);
        $safeTableName = $this->escapeTableName($tableName);

        // 使用参数化查询（虽然表名不能参数化，但我们已经验证了格式）
        // 注意：SHOW 语句不支持参数绑定，所以必须验证表名格式
        $columns = Db::select("SHOW FULL COLUMNS FROM {$safeTableName}");

        $result = [];
        foreach ($columns as $column) {
            $result[] = [
                'name' => $column->Field,
                'type' => $this->parseColumnType($column->Type),
                'label' => $this->guessFieldLabel($column->Field, $column->Comment),
                'comment' => $column->Comment,
                'nullable' => $column->Null === 'YES',
                'default' => $column->Default,
            ];
        }

        return $result;
    }

    /**
     * 解析数据库列类型
     *
     * @param string $type 数据库类型字符串，如 "int(11)"、"varchar(255)"
     * @return string 基础类型，如 "int"、"varchar"
     */
    public function parseColumnType(string $type): string
    {
        if (preg_match('/^(\w+)/', $type, $matches)) {
            return $matches[1];
        }
        return $type;
    }

    /**
     * 猜测字段标签
     *
     * @param string $fieldName 字段名
     * @param string $comment 数据库注释
     * @return string 字段标签
     */
    public function guessFieldLabel(string $fieldName, string $comment): string
    {
        if ($comment) {
            // 移除注释中的冒号后内容（如："状态：0=禁用 1=启用" -> "状态"）
            $comment = preg_replace('/[：:].+$/', '', $comment);
            return trim($comment);
        }

        // 根据字段名猜测
        $labels = [
            'name' => '名称',
            'title' => '标题',
            'description' => '描述',
            'content' => '内容',
            'status' => '状态',
            'sort' => '排序',
            'type' => '类型',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];

        return $labels[$fieldName] ?? $fieldName;
    }

    /**
     * 猜测表单字段类型
     *
     * @param array $column 列信息数组，包含 name, type 等
     * @return string 表单字段类型，如 "text"、"number"、"textarea"
     */
    public function guessFormFieldType(array $column): string
    {
        $name = $column['name'];
        $type = $column['type'];

        // 根据字段名判断
        if (str_contains($name, 'password')) {
            return 'password';
        }
        if (str_contains($name, 'email')) {
            return 'email';
        }
        if (str_contains($name, 'content') || str_contains($name, 'description')) {
            return 'textarea';
        }

        // 根据数据库类型判断
        if (in_array($type, ['text', 'mediumtext', 'longtext'])) {
            return 'textarea';
        }
        if (in_array($type, ['int', 'tinyint', 'smallint', 'bigint'])) {
            return 'number';
        }
        if (in_array($type, ['date'])) {
            return 'date';
        }
        if (in_array($type, ['datetime', 'timestamp'])) {
            return 'datetime';
        }

        return 'text';
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
    public function convertRouteParamToTableName(string $routeParam): string
    {
        // 将连字符转换为下划线
        $tableName = str_replace('-', '_', $routeParam);

        // 如果是驼峰命名，转换为蛇形命名
        // FundBrand -> fund_brand
        if (preg_match('/[A-Z]/', $tableName)) {
            $tableName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $tableName));
        }

        return strtolower($tableName);
    }

    /**
     * 将路由参数转换为模型名
     *
     * 转换规则：
     * - 连字符/下划线转换为驼峰：admin_users -> AdminUsers
     * - 自动添加 Admin 前缀：users -> AdminUsers
     *
     * @param string $routeParam 路由参数
     * @return string 模型名
     *
     * @example
     * convertRouteParamToModelName('admin_users') => 'AdminUsers'
     * convertRouteParamToModelName('fund-brand') => 'AdminFundBrand'
     * convertRouteParamToModelName('users') => 'AdminUsers'
     */
    public function convertRouteParamToModelName(string $routeParam): string
    {
        // 将连字符和下划线转换为空格
        $modelName = str_replace(['-', '_'], ' ', $routeParam);

        // 首字母大写（每个单词）
        // fund brand -> Fund Brand
        $modelName = ucwords($modelName);

        // 移除空格
        // Fund Brand -> FundBrand
        $modelName = str_replace(' ', '', $modelName);

        // 添加 Admin 前缀（如果还没有）
        if (!str_starts_with($modelName, 'Admin')) {
            $modelName = 'Admin' . $modelName;
        }

        return $modelName;
    }

    /**
     * 判断是否为字符串类型
     *
     * @param string $dbType 数据库类型
     * @return bool
     */
    public function isStringType(string $dbType): bool
    {
        $stringTypes = ['varchar', 'char', 'text', 'mediumtext', 'longtext', 'tinytext'];
        return in_array(strtolower($dbType), $stringTypes);
    }

    /**
     * 判断是否为数字类型
     *
     * @param string $dbType 数据库类型
     * @return bool
     */
    public function isNumericType(string $dbType): bool
    {
        $numericTypes = ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'];
        return in_array(strtolower($dbType), $numericTypes);
    }

    /**
     * 判断是否为日期类型
     *
     * @param string $dbType 数据库类型
     * @param string|null $formType 表单类型（可选）
     * @return bool
     */
    public function isDateType(string $dbType, ?string $formType = null): bool
    {
        // 如果表单类型明确指定为日期类型
        if (in_array($formType, ['date', 'datetime', 'datetime-local', 'time'])) {
            return true;
        }

        // 根据数据库类型判断
        $dateTypes = ['date', 'datetime', 'timestamp', 'time', 'year'];
        return in_array(strtolower($dbType), $dateTypes);
    }
}

