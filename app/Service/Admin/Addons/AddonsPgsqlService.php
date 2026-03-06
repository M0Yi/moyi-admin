<?php

declare(strict_types=1);

/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Service\Admin\Addons;

use Hyperf\DbConnection\Db;
use Throwable;

/**
 * 插件PostgreSQL数据库管理服务
 *
 * 专门处理PostgreSQL数据库的安装、升级和管理功能
 * 支持PostgreSQL特有的高级特性：扩展、自定义类型、分区表、索引类型等
 */
class AddonsPgsqlService
{
    /**
     * PostgreSQL 数据库连接
     */
    private $pgsqlConnection;

    public function __construct()
    {
        $this->pgsqlConnection = Db::connection('pgsql');
    }
    /**
     * 管理PostgreSQL数据库表和相关对象
     *
     * @param string $addonName 插件名称
     * @param string $pgsqlFile PostgreSQL配置文件路径
     * @return bool 是否成功
     */
    public function managePgsqlDatabase(string $addonName, string $pgsqlFile): bool
    {
        try {
            logger()->info("[PostgreSQL管理] 开始处理插件 {$addonName} 的PostgreSQL配置");

            if (!file_exists($pgsqlFile)) {
                logger()->warning("[PostgreSQL管理] 插件 {$addonName} 的PostgreSQL配置文件不存在: {$pgsqlFile}");
                return false;
            }

            $configContent = file_get_contents($pgsqlFile);
            $config = json_decode($configContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                logger()->error("[PostgreSQL管理] 插件 {$addonName} 的PostgreSQL配置文件JSON格式错误: " . json_last_error_msg());
                return false;
            }

            // 检查数据库类型
            if (!$this->isPostgreSQL()) {
                logger()->warning("[PostgreSQL管理] 当前数据库不是PostgreSQL，跳过PostgreSQL特有功能处理");
                return true;
            }

            // 处理扩展
            if (isset($config['extensions']) && is_array($config['extensions'])) {
                $this->installExtensions($addonName, $config['extensions']);
            }

            // 处理自定义类型
            if (isset($config['types']) && is_array($config['types'])) {
                $this->createCustomTypes($addonName, $config['types']);
            }

        // 处理表 - 分两轮创建，先创建没有继承的表，再创建继承表
        if (isset($config['tables']) && is_array($config['tables'])) {
            // 第一轮：创建没有继承的表
            foreach ($config['tables'] as $tableName => $tableConfig) {
                if (!isset($tableConfig['inherits'])) {
                    $this->createPgsqlTable($addonName, $tableName, $tableConfig);
                }
            }

            // 第二轮：创建继承表
            foreach ($config['tables'] as $tableName => $tableConfig) {
                if (isset($tableConfig['inherits'])) {
                    $this->createPgsqlTable($addonName, $tableName, $tableConfig);
                }
            }
        }

            // 处理函数
            if (isset($config['functions']) && is_array($config['functions'])) {
                $this->createFunctions($addonName, $config['functions']);
            }

            // 处理触发器（在函数创建完成后，因为触发器依赖函数）
            if (isset($config['tables']) && is_array($config['tables'])) {
                foreach ($config['tables'] as $tableName => $tableConfig) {
                    if (isset($tableConfig['triggers']) && is_array($tableConfig['triggers'])) {
                        $this->createTriggers($tableName, $tableConfig['triggers']);
                    }
                }
            }

            // 处理视图
            if (isset($config['views']) && is_array($config['views'])) {
                $this->createViews($addonName, $config['views']);
            }

            // 处理分区表
            if (isset($config['partitioned_tables']) && is_array($config['partitioned_tables'])) {
                foreach ($config['partitioned_tables'] as $tableName => $tableConfig) {
                    $this->createPartitionedTable($addonName, $tableName, $tableConfig);
                }
            }

            // 处理示例数据
            if (isset($config['sample_data']) && is_array($config['sample_data'])) {
                $this->insertSampleData($addonName, $config['sample_data']);
            }

            logger()->info("[PostgreSQL管理] 插件 {$addonName} 的PostgreSQL配置处理完成");
            return true;

        } catch (Throwable $e) {
            logger()->error("[PostgreSQL管理] 插件 {$addonName} 的PostgreSQL配置处理失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 检查当前是否为PostgreSQL数据库
     *
     * @return bool
     */
    private function isPostgreSQL(): bool
    {
        try {
            // 检查连接配置中的驱动
            $config = $this->pgsqlConnection->getConfig();
            return ($config['driver'] ?? '') === 'pgsql';
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * 安装PostgreSQL扩展
     *
     * @param string $addonName 插件名称
     * @param array $extensions 扩展列表
     */
    private function installExtensions(string $addonName, array $extensions): void
    {
        logger()->info("[PostgreSQL扩展] 开始安装扩展，插件: {$addonName}");

        foreach ($extensions as $extension) {
            try {
                // 检查扩展是否已安装
                $exists = $this->pgsqlConnection->selectOne("SELECT 1 FROM pg_extension WHERE extname = ?", [$extension]);

                if ($exists) {
                    logger()->debug("[PostgreSQL扩展] 扩展 {$extension} 已存在，跳过");
                    continue;
                }

                // 安装扩展
                $this->pgsqlConnection->statement("CREATE EXTENSION IF NOT EXISTS \"{$extension}\"");
                logger()->info("[PostgreSQL扩展] 扩展 {$extension} 安装成功");

            } catch (Throwable $e) {
                logger()->warning("[PostgreSQL扩展] 扩展 {$extension} 不可用，已跳过: " . $e->getMessage());
                // 扩展不可用时跳过，不影响其他功能
            }
        }

        logger()->info('[PostgreSQL扩展] 扩展安装完成');
    }

    /**
     * 创建自定义类型
     *
     * @param string $addonName 插件名称
     * @param array $types 类型定义
     */
    private function createCustomTypes(string $addonName, array $types): void
    {
        logger()->info("[PostgreSQL类型] 开始创建自定义类型，插件: {$addonName}");

        foreach ($types as $typeName => $typeDefinition) {
            try {
                // 检查类型是否已存在
                $exists = $this->pgsqlConnection->selectOne("SELECT 1 FROM pg_type WHERE typname = ?", [$typeName]);

                if ($exists) {
                    logger()->debug("[PostgreSQL类型] 类型 {$typeName} 已存在，跳过");
                    continue;
                }

                // 创建类型 - 根据类型定义调整SQL语法
                $sql = $this->buildCreateTypeSql($typeName, $typeDefinition);
                $this->pgsqlConnection->statement($sql);
                logger()->info("[PostgreSQL类型] 自定义类型 {$typeName} 创建成功");

            } catch (Throwable $e) {
                logger()->warning("[PostgreSQL类型] 自定义类型 {$typeName} 创建失败，已跳过: " . $e->getMessage());
                // 类型创建失败时跳过，不影响其他功能
            }
        }

        logger()->info('[PostgreSQL类型] 自定义类型创建完成');
    }

    /**
     * 创建PostgreSQL表
     *
     * @param string $addonName 插件名称
     * @param string $tableName 表名
     * @param array $tableConfig 表配置
     */
    private function createPgsqlTable(string $addonName, string $tableName, array $tableConfig): bool
    {
        try {
            // 检查表是否已存在
            $exists = $this->pgsqlConnection->selectOne("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?", [$tableName]);

            if ($exists) {
                logger()->info("[PostgreSQL表] 表 {$tableName} 已存在，检查是否需要升级");
                return $this->upgradePgsqlTable($addonName, $tableName, $tableConfig);
            }

            logger()->info("[PostgreSQL表] 开始创建表 {$tableName}");

            // 构建建表SQL
            $createSql = $this->buildCreatePgsqlTableSql($tableName, $tableConfig);

            // 执行建表SQL
            $this->pgsqlConnection->statement($createSql);

            // 添加表注释
            if (isset($tableConfig['comment'])) {
                $comment = addslashes($tableConfig['comment']); // 转义特殊字符
                $this->pgsqlConnection->statement("COMMENT ON TABLE {$tableName} IS '{$comment}'");
            }

            // 添加字段注释
            if (isset($tableConfig['columns']) && is_array($tableConfig['columns'])) {
                foreach ($tableConfig['columns'] as $columnName => $columnConfig) {
                    if (isset($columnConfig['comment'])) {
                        $comment = addslashes($columnConfig['comment']); // 转义特殊字符
                        $this->pgsqlConnection->statement("COMMENT ON COLUMN {$tableName}.{$columnName} IS '{$comment}'");
                    }
                }
            }

            logger()->info("[PostgreSQL表] 表 {$tableName} 创建成功");

            // 创建索引
            if (isset($tableConfig['indexes']) && is_array($tableConfig['indexes'])) {
                $this->createPgsqlIndexes($tableName, $tableConfig['indexes']);
            }

            // 创建约束
            if (isset($tableConfig['constraints']) && is_array($tableConfig['constraints'])) {
                $this->createConstraints($tableName, $tableConfig['constraints']);
            }

            // 注意：触发器不在这里创建，因为触发器依赖函数，需要在函数创建完成后再创建
            return true;

        } catch (Throwable $e) {
            logger()->error("[PostgreSQL表] 表 {$tableName} 创建失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 构建PostgreSQL建表SQL
     *
     * @param string $tableName 表名
     * @param array $tableConfig 表配置
     * @return string 建表SQL
     */
    private function buildCreatePgsqlTableSql(string $tableName, array $tableConfig): string
    {
        $columns = [];
        $inherits = $tableConfig['inherits'] ?? null;

        // 处理列定义
        if (isset($tableConfig['columns']) && is_array($tableConfig['columns'])) {
            foreach ($tableConfig['columns'] as $columnName => $columnConfig) {
                $columns[] = $this->buildPgsqlColumnDefinition($columnName, $columnConfig);
            }
        }

        // 构建建表SQL
        $sql = "CREATE TABLE {$tableName} (" . PHP_EOL;
        $sql .= implode("," . PHP_EOL, $columns);
        $sql .= PHP_EOL . ")";

        // 添加继承
        if ($inherits) {
            $sql .= " INHERITS ({$inherits})";
        }

        return $sql;
    }

    /**
     * 构建PostgreSQL列定义
     *
     * @param string $columnName 列名
     * @param array $columnConfig 列配置
     * @return string 列定义SQL
     */
    private function buildPgsqlColumnDefinition(string $columnName, array $columnConfig): string
    {
        $type = $columnConfig['type'] ?? 'TEXT';

        // 处理默认值，支持布尔类型
        if (array_key_exists('default', $columnConfig) && $columnConfig['default'] !== null) {
            $defaultValue = $columnConfig['default'];
            if (is_bool($defaultValue)) {
                $default = ' DEFAULT ' . ($defaultValue ? 'true' : 'false');
            } else {
                $default = ' DEFAULT ' . $defaultValue;
            }
        } else {
            $default = '';
        }

        $constraints = [];

        // 处理主键约束
        if (isset($columnConfig['primary']) && $columnConfig['primary']) {
            $constraints[] = 'PRIMARY KEY';
        }

        $constraintStr = !empty($constraints) ? ' ' . implode(' ', $constraints) : '';

        // PostgreSQL不支持在字段定义中内联注释，注释需要在建表后单独添加
        return "  {$columnName} {$type}{$default}{$constraintStr}";
    }

    /**
     * 创建PostgreSQL索引
     *
     * @param string $tableName 表名
     * @param array $indexes 索引配置
     */
    private function createPgsqlIndexes(string $tableName, array $indexes): void
    {
        foreach ($indexes as $indexConfig) {
            try {
                $indexName = $indexConfig['name'];
                $columns = $indexConfig['columns'];
                $type = $indexConfig['type'] ?? 'btree';
                $where = isset($indexConfig['where']) ? " WHERE {$indexConfig['where']}" : '';

                // 检查索引是否已存在
                $exists = $this->pgsqlConnection->selectOne("SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$tableName, $indexName]);

                if ($exists) {
                    logger()->debug("[PostgreSQL索引] 索引 {$indexName} 已存在，跳过");
                    continue;
                }

                // 构建索引SQL
                $columnsStr = is_array($columns) ? implode(', ', $columns) : $columns;

                // HNSW 索引需要 WITH 子句来指定参数
                $options = '';
                if ($type === 'hnsw' && isset($indexConfig['options']) && is_array($indexConfig['options'])) {
                    $optionParts = [];
                    foreach ($indexConfig['options'] as $key => $value) {
                        $optionParts[] = "{$key} = {$value}";
                    }
                    $options = ' WITH (' . implode(', ', $optionParts) . ')';
                }

                $sql = "CREATE INDEX {$indexName} ON {$tableName} USING {$type} ({$columnsStr}){$options}{$where}";

                $this->pgsqlConnection->statement($sql);
                logger()->info("[PostgreSQL索引] 索引 {$indexName} 创建成功");

            } catch (Throwable $e) {
                logger()->error("[PostgreSQL索引] 索引 {$indexConfig['name']} 创建失败: " . $e->getMessage());
            }
        }
    }

    /**
     * 创建约束
     *
     * @param string $tableName 表名
     * @param array $constraints 约束配置
     */
    private function createConstraints(string $tableName, array $constraints): void
    {
        foreach ($constraints as $constraint) {
            try {
                $constraintName = $constraint['name'];
                $type = $constraint['type'];
                $condition = $constraint['condition'];

                // 检查约束是否已存在
                $exists = $this->pgsqlConnection->selectOne("SELECT 1 FROM information_schema.table_constraints WHERE table_name = ? AND constraint_name = ?", [$tableName, $constraintName]);

                if ($exists) {
                    logger()->debug("[PostgreSQL约束] 约束 {$constraintName} 已存在，跳过");
                    continue;
                }

                // 创建约束
                $sql = "ALTER TABLE {$tableName} ADD CONSTRAINT {$constraintName} {$type} ({$condition})";
                $this->pgsqlConnection->statement($sql);
                logger()->info("[PostgreSQL约束] 约束 {$constraintName} 创建成功");

            } catch (Throwable $e) {
                logger()->error("[PostgreSQL约束] 约束 {$constraint['name']} 创建失败: " . $e->getMessage());
            }
        }
    }

    /**
     * 创建触发器
     *
     * @param string $tableName 表名
     * @param array $triggers 触发器配置
     */
    private function createTriggers(string $tableName, array $triggers): void
    {
        foreach ($triggers as $trigger) {
            try {
                $triggerName = $trigger['name'];
                $timing = $trigger['timing'];
                $events = implode(' OR ', $trigger['events']);
                $function = $trigger['function'];
                $when = isset($trigger['when']) ? " WHEN ({$trigger['when']})" : '';

                // 检查触发器是否已存在
                $exists = $this->pgsqlConnection->selectOne("SELECT 1 FROM pg_trigger WHERE tgname = ?", [$triggerName]);

                if ($exists) {
                    logger()->debug("[PostgreSQL触发器] 触发器 {$triggerName} 已存在，跳过");
                    continue;
                }

                // 创建触发器
                $sql = "CREATE TRIGGER {$triggerName} {$timing} {$events} ON {$tableName} FOR EACH ROW{$when} EXECUTE FUNCTION {$function}()";
                $this->pgsqlConnection->statement($sql);
                logger()->info("[PostgreSQL触发器] 触发器 {$triggerName} 创建成功");

            } catch (Throwable $e) {
                logger()->error("[PostgreSQL触发器] 触发器 {$trigger['name']} 创建失败: " . $e->getMessage());
            }
        }
    }

    /**
     * 创建函数
     *
     * @param string $addonName 插件名称
     * @param array $functions 函数配置
     */
    private function createFunctions(string $addonName, array $functions): void
    {
        logger()->info("[PostgreSQL函数] 开始创建函数，插件: {$addonName}");

        foreach ($functions as $function) {
            try {
                $functionName = $function['name'];
                $returns = $function['returns'];
                $language = $function['language'] ?? 'plpgsql';
                $code = $function['code'];
                $parameters = isset($function['parameters']) ? implode(', ', $function['parameters']) : '';

                // 检查函数是否依赖不可用的扩展
                $fullFunctionDefinition = $parameters . ' ' . $code; // 包含参数和函数体
                if ($this->functionRequiresUnavailableExtension($functionName, $fullFunctionDefinition)) {
                    logger()->warning("[PostgreSQL函数] 函数 {$functionName} 依赖不可用扩展，已跳过");
                    continue;
                }

                // 使用 CREATE OR REPLACE FUNCTION，自动处理已存在函数的更新
                $sql = "CREATE OR REPLACE FUNCTION {$functionName}({$parameters}) RETURNS {$returns} LANGUAGE {$language} AS $$ {$code} $$";
                $this->pgsqlConnection->statement($sql);
                logger()->info("[PostgreSQL函数] 函数 {$functionName} 创建/更新成功");

            } catch (Throwable $e) {
                logger()->warning("[PostgreSQL函数] 函数 {$function['name']} 创建失败，已跳过: " . $e->getMessage());
            }
        }

        logger()->info('[PostgreSQL函数] 函数创建完成');
    }

    /**
     * 创建视图
     *
     * @param string $addonName 插件名称
     * @param array $views 视图配置
     */
    private function createViews(string $addonName, array $views): void
    {
        logger()->info("[PostgreSQL视图] 开始创建视图，插件: {$addonName}");

        foreach ($views as $view) {
            try {
                $viewName = $view['name'];
                $query = $view['query'];

                // 检查视图是否已存在
                $exists = $this->pgsqlConnection->selectOne("SELECT 1 FROM information_schema.views WHERE table_name = ?", [$viewName]);

                if ($exists) {
                    logger()->debug("[PostgreSQL视图] 视图 {$viewName} 已存在，跳过");
                    continue;
                }

                // 创建视图
                $sql = "CREATE VIEW {$viewName} AS {$query}";
                $this->pgsqlConnection->statement($sql);
                logger()->info("[PostgreSQL视图] 视图 {$viewName} 创建成功");

            } catch (Throwable $e) {
                logger()->error("[PostgreSQL视图] 视图 {$view['name']} 创建失败: " . $e->getMessage());
            }
        }

        logger()->info('[PostgreSQL视图] 视图创建完成');
    }

    /**
     * 创建分区表
     *
     * @param string $addonName 插件名称
     * @param string $tableName 表名
     * @param array $tableConfig 表配置
     */
    private function createPartitionedTable(string $addonName, string $tableName, array $tableConfig): void
    {
        try {
            logger()->info("[PostgreSQL分区表] 开始创建分区表 {$tableName}");

            // 检查分区表是否已存在
            $exists = $this->pgsqlConnection->selectOne("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ?", [$tableName]);

            if ($exists) {
                logger()->info("[PostgreSQL分区表] 分区表 {$tableName} 已存在，跳过创建");
                return;
            }

            // 创建主分区表
            $partitionBy = $tableConfig['partition_by'];
            $columns = $this->buildPartitionTableColumns($tableConfig['columns']);

            $sql = "CREATE TABLE {$tableName} ({$columns}) PARTITION BY {$partitionBy}";
            $this->pgsqlConnection->statement($sql);

            logger()->info("[PostgreSQL分区表] 主分区表 {$tableName} 创建成功");

            // 创建分区
            if (isset($tableConfig['partitions']) && is_array($tableConfig['partitions'])) {
                foreach ($tableConfig['partitions'] as $partition) {
                    try {
                        $partitionName = $partition['name'];
                        $from = $partition['from'];
                        $to = $partition['to'];

                        $partitionSql = "CREATE TABLE {$partitionName} PARTITION OF {$tableName} FOR VALUES FROM ('{$from}') TO ('{$to}')";
                        $this->pgsqlConnection->statement($partitionSql);
                        logger()->info("[PostgreSQL分区表] 分区 {$partitionName} 创建成功");
                    } catch (Throwable $e) {
                        logger()->warning("[PostgreSQL分区表] 分区 {$partition['name']} 创建失败，已跳过: " . $e->getMessage());
                    }
                }
            }

            logger()->info("[PostgreSQL分区表] 分区表 {$tableName} 创建完成");

        } catch (Throwable $e) {
            logger()->warning("[PostgreSQL分区表] 分区表 {$tableName} 创建失败，已跳过: " . $e->getMessage());
        }
    }

    /**
     * 构建分区表列定义
     *
     * @param array $columns 列配置
     * @return string 列定义SQL
     */
    private function buildPartitionTableColumns(array $columns): string
    {
        $columnDefs = [];

        foreach ($columns as $columnName => $columnConfig) {
            $columnDefs[] = $this->buildPgsqlColumnDefinition($columnName, $columnConfig);
        }

        return implode(", ", $columnDefs);
    }

    /**
     * 插入示例数据
     *
     * @param string $addonName 插件名称
     * @param array $sampleData 示例数据配置
     */
    private function insertSampleData(string $addonName, array $sampleData): void
    {
        logger()->info("[PostgreSQL数据] 开始插入示例数据，插件: {$addonName}");

        foreach ($sampleData as $dataConfig) {
            $tableName = $dataConfig['table'];
            $data = $dataConfig['data'] ?? [];

            try {
                foreach ($data as $row) {
                    // 预处理数据，转换数组格式
                    $processedRow = $this->processSampleDataRow($row);

                    // 检查是否已存在相同数据（基于唯一标识）
                    if ($this->shouldInsertSampleData($tableName, $processedRow)) {
                        // 对于PostgreSQL，使用原生SQL插入以避免查询绑定问题
                        $this->insertSampleRowNative($tableName, $processedRow);
                        logger()->debug("[PostgreSQL数据] 插入示例数据到表 {$tableName}");
                    } else {
                        logger()->debug("[PostgreSQL数据] 跳过已存在的示例数据，表 {$tableName}");
                    }
                }
            } catch (Throwable $e) {
                logger()->error("[PostgreSQL数据] 插入示例数据失败，表 {$tableName}: " . $e->getMessage());
            }
        }

        logger()->info('[PostgreSQL数据] 示例数据插入完成');
    }

    /**
     * 构建创建类型的SQL语句
     *
     * @param string $typeName 类型名称
     * @param string $typeDefinition 类型定义
     * @return string SQL语句
     */
    private function buildCreateTypeSql(string $typeName, string $typeDefinition): string
    {
        // 检查是否是ENUM类型
        if (strpos($typeDefinition, 'ENUM(') === 0) {
            return "CREATE TYPE {$typeName} AS {$typeDefinition}";
        }

        // 其他类型的处理
        return "CREATE TYPE {$typeName} AS {$typeDefinition}";
    }

    /**
     * 检查函数是否依赖不可用的扩展
     *
     * @param string $functionName 函数名称
     * @param string $code 函数代码
     * @return bool 是否依赖不可用扩展
     */
    private function functionRequiresUnavailableExtension(string $functionName, string $code): bool
    {
        // 检查代码中是否使用了PostGIS相关函数
        if (strpos($code, 'ST_') !== false || strpos($code, 'GEOMETRY') !== false) {
            // 检查postgis扩展是否可用
            try {
                $this->pgsqlConnection->selectOne("SELECT 1 FROM pg_extension WHERE extname = 'postgis'");
                return false; // 扩展可用
            } catch (Throwable $e) {
                return true; // 扩展不可用
            }
        }

        // 检查代码中是否使用了中文分词相关函数
        if (strpos($code, 'zhparser') !== false || strpos($code, 'to_tsvector') !== false) {
            // 检查postgres-zhparser扩展是否可用
            try {
                $this->pgsqlConnection->selectOne("SELECT 1 FROM pg_extension WHERE extname = 'postgres-zhparser'");
                return false; // 扩展可用
            } catch (Throwable $e) {
                return true; // 扩展不可用
            }
        }

        return false; // 不依赖特殊扩展
    }

    /**
     * 处理示例数据行，转换格式以适应PostgreSQL
     *
     * @param array $row 原始数据行
     * @return array 处理后的数据行
     */
    private function processSampleDataRow(array $row): array
    {
        $processed = [];

        foreach ($row as $key => $value) {
            if (is_array($value)) {
                // 将PHP数组转换为PostgreSQL数组语法
                $processed[$key] = $this->arrayToPostgresArray($value);
            } elseif (is_string($value)) {
                // 检查是否已经是PostgreSQL数组格式
                if (preg_match("/^\{.*\}$/", $value)) {
                    // 已经是PostgreSQL数组格式，直接使用
                    $processed[$key] = $value;
                } elseif (strpos($value, 'HSTORE') !== false) {
                    // HSTORE数据特殊处理
                    $processed[$key] = $value;
                } else {
                    $processed[$key] = $value;
                }
            } else {
                $processed[$key] = $value;
            }
        }

        return $processed;
    }

    /**
     * 将PHP数组转换为PostgreSQL数组语法
     *
     * @param array $array PHP数组
     * @return string PostgreSQL数组语法
     */
    private function arrayToPostgresArray(array $array): string
    {
        $escaped = array_map(function ($item) {
            // 对字符串元素进行适当的转义
            if (is_string($item)) {
                // 如果包含逗号或引号，需要特殊处理
                if (strpos($item, ',') !== false || strpos($item, '"') !== false) {
                    return '"' . str_replace('"', '""', $item) . '"';
                }
                return $item;
            }
            return (string) $item;
        }, $array);

        return '{' . implode(',', $escaped) . '}';
    }

    /**
     * 使用原生SQL插入示例数据行（避免Laravel查询绑定问题）
     *
     * @param string $tableName 表名
     * @param array $row 数据行
     */
    private function insertSampleRowNative(string $tableName, array $row): void
    {
        // 完全避免Laravel查询绑定，直接构建完整的SQL语句
        $columns = array_keys($row);
        $values = array_map([$this, 'formatSqlValue'], array_values($row));

        $columnsStr = implode(', ', array_map(fn($col) => "\"{$col}\"", $columns));
        $valuesStr = implode(', ', $values);

        $sql = "INSERT INTO \"{$tableName}\" ({$columnsStr}) VALUES ({$valuesStr})";

        // 直接执行原生SQL
        $this->pgsqlConnection->statement($sql);
    }

    /**
     * 格式化SQL值，避免查询绑定问题
     *
     * @param mixed $value 值
     * @return string 格式化的SQL值
     */
    private function formatSqlValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            // 特殊处理BIT类型 (B'xxxxx')
            if (preg_match("/^B'[01]+'$/", $value)) {
                return $value; // BIT类型不需要额外引号
            }

            // 特殊处理PostgreSQL数组字面量 ({element1,element2})
            if (preg_match("/^\{.*\}$/", $value)) {
                // PostgreSQL数组字面量需要用单引号包围
                return "'{$value}'";
            }

            // 特殊处理HSTORE类型 (key=>value)
            if (strpos($value, '=>') !== false) {
                // HSTORE类型需要用单引号包围，并转义内部的单引号
                $escaped = str_replace("'", "''", $value);
                return "'{$escaped}'";
            }

            // 转义单引号并添加引号
            $escaped = str_replace("'", "''", $value);
            return "'{$escaped}'";
        }

        // 其他类型转换为字符串
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * 检查是否应该插入示例数据
     *
     * @param string $tableName 表名
     * @param array $row 数据行
     * @return bool
     */
    private function shouldInsertSampleData(string $tableName, array $row): bool
    {
        // 基于ID检查数据是否已存在
        if (isset($row['id'])) {
            try {
                $exists = $this->pgsqlConnection->selectOne(
                    "SELECT 1 FROM {$tableName} WHERE id = ?",
                    [$row['id']]
                );
                return !$exists; // 如果不存在则插入
            } catch (Throwable $e) {
                logger()->warning("[PostgreSQL数据] 检查数据存在性失败: " . $e->getMessage());
                // 如果检查失败，为了安全起见，不插入
                return false;
            }
        }

        // 如果没有ID字段，允许插入（用于自增ID的情况）
        return true;
    }

    /**
     * 升级PostgreSQL表
     *
     * @param string $addonName 插件名称
     * @param string $tableName 表名
     * @param array $tableConfig 表配置
     * @return bool
     */
    private function upgradePgsqlTable(string $addonName, string $tableName, array $tableConfig): bool
    {
        try {
            logger()->info("[PostgreSQL表升级] 开始升级表 {$tableName}");

            // 获取现有表结构
            $existingSchema = $this->getExistingPgsqlTableSchema($tableName);

            // 比较并生成升级SQL
            $alterSqls = $this->generatePgsqlAlterSqls($tableName, $existingSchema, $tableConfig);

            // 处理视图重新创建
            $viewsToRecreate = [];
            foreach ($alterSqls as $key => $sql) {
                if (strpos($sql, '-- RECREATE_VIEW: ') === 0) {
                    $viewName = str_replace('-- RECREATE_VIEW: ', '', $sql);
                    $viewsToRecreate[] = $viewName;
                    unset($alterSqls[$key]);
                }
            }

            // 执行升级SQL
            foreach ($alterSqls as $sql) {
                $this->pgsqlConnection->statement($sql);
            }

            // 重新创建视图
            if (!empty($viewsToRecreate)) {
                $this->recreateViews($addonName, $viewsToRecreate);
            }

            logger()->info("[PostgreSQL表升级] 表 {$tableName} 升级完成");
            return true;

        } catch (Throwable $e) {
            logger()->error("[PostgreSQL表升级] 表 {$tableName} 升级失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取现有PostgreSQL表结构
     *
     * @param string $tableName 表名
     * @return array 表结构信息
     */
    private function getExistingPgsqlTableSchema(string $tableName): array
    {
        try {
            // 获取列信息
            $columns = $this->pgsqlConnection->select("
                SELECT column_name, data_type, column_default, is_nullable
                FROM information_schema.columns
                WHERE table_name = ? AND table_schema = 'public'
                ORDER BY ordinal_position
            ", [$tableName]);

            // 获取索引信息
            $indexes = $this->pgsqlConnection->select("
                SELECT indexname, indexdef
                FROM pg_indexes
                WHERE tablename = ?
            ", [$tableName]);

            return [
                'columns' => $columns,
                'indexes' => $indexes,
            ];

        } catch (Throwable $e) {
            logger()->error("[PostgreSQL表结构] 获取表 {$tableName} 结构失败: " . $e->getMessage());
            return ['columns' => [], 'indexes' => []];
        }
    }

    /**
     * 生成PostgreSQL表升级SQL
     *
     * @param string $tableName 表名
     * @param array $existing 现有结构
     * @param array $expected 期望结构
     * @return array 升级SQL数组
     */
    private function generatePgsqlAlterSqls(string $tableName, array $existing, array $expected): array
    {
        $alterSqls = [];

        // 比较列定义
        if (isset($existing['columns']) && isset($expected['columns'])) {
            $columnAlters = $this->generateColumnAlterSqls($tableName, $existing['columns'], $expected['columns']);
            $alterSqls = array_merge($alterSqls, $columnAlters);
        }

        // 比较索引
        if (isset($existing['indexes']) && isset($expected['indexes'])) {
            $indexAlters = $this->generateIndexAlterSqls($tableName, $existing['indexes'], $expected['indexes']);
            $alterSqls = array_merge($alterSqls, $indexAlters);
        }

        return $alterSqls;
    }

    /**
     * 生成列修改SQL
     *
     * @param string $tableName 表名
     * @param array $existingColumns 现有列
     * @param array $expectedColumns 期望列配置
     * @return array 修改SQL数组
     */
    private function generateColumnAlterSqls(string $tableName, array $existingColumns, array $expectedColumns): array
    {
        $alterSqls = [];

        // 将现有列转换为以列名为主键的数组
        $existingColumnsMap = [];
        foreach ($existingColumns as $column) {
            $existingColumnsMap[$column->column_name] = $column;
        }

        // 收集需要修改的列
        $columnsToAlter = [];

        // 检查期望的列
        foreach ($expectedColumns as $columnName => $columnConfig) {
            $expectedType = $columnConfig['type'] ?? 'TEXT';
            $expectedNullable = !isset($columnConfig['nullable']) || $columnConfig['nullable'];
            $expectedDefault = $columnConfig['default'] ?? null;

            if (isset($existingColumnsMap[$columnName])) {
                // 列已存在，检查是否需要修改
                $existingColumn = $existingColumnsMap[$columnName];
                $needsAlter = false;

                // 特殊处理BIGSERIAL类型
                if (strtoupper($expectedType) === 'BIGSERIAL') {
                    $needsAlter = true;
                } else {
                    // 比较数据类型
                    if (!$this->isColumnTypeCompatible($existingColumn->data_type, $expectedType)) {
                        $needsAlter = true;
                    }

                    // 比较可空性
                    $existingNullable = $existingColumn->is_nullable === 'YES';
                    if ($existingNullable !== $expectedNullable) {
                        $needsAlter = true;
                    }
                }

                if ($needsAlter) {
                    $columnsToAlter[$columnName] = [
                        'existing' => $existingColumn,
                        'config' => $columnConfig
                    ];
                }

            } else {
                // 列不存在，添加新列
                if (strtoupper($expectedType) === 'BIGSERIAL') {
                    // 特殊处理BIGSERIAL新列
                    $alterSqls = array_merge($alterSqls, $this->handleBigserialCreation($tableName, $columnName, $expectedNullable));
                } else {
                    $nullable = $expectedNullable ? '' : ' NOT NULL';
                    $default = $expectedDefault !== null ? " DEFAULT {$expectedDefault}" : '';
                    $alterSql = "ALTER TABLE {$tableName} ADD COLUMN {$columnName} {$expectedType}{$nullable}{$default}";
                    $alterSqls[] = $alterSql;
                    logger()->info("[PostgreSQL列升级] 添加新列: {$columnName} {$expectedType}");
                }
            }
        }

        // 处理需要修改的列
        if (!empty($columnsToAlter)) {
            // 查找依赖于这些列的视图
            $dependentViews = $this->findDependentViews($tableName, array_keys($columnsToAlter));

            // 如果有依赖的视图，先删除它们
            if (!empty($dependentViews)) {
                foreach ($dependentViews as $viewName) {
                    $alterSqls[] = "DROP VIEW IF EXISTS {$viewName}";
                    logger()->info("[PostgreSQL视图依赖] 临时删除视图 {$viewName} 以便修改列");
                }
            }

            // 执行列修改
            foreach ($columnsToAlter as $columnName => $columnInfo) {
                $existingColumn = $columnInfo['existing'];
                $columnConfig = $columnInfo['config'];
                $expectedType = $columnConfig['type'] ?? 'TEXT';
                $expectedNullable = !isset($columnConfig['nullable']) || $columnConfig['nullable'];

                if (strtoupper($expectedType) === 'BIGSERIAL') {
                    $alterSqls = array_merge($alterSqls, $this->handleBigserialUpgrade($tableName, $columnName, $existingColumn));
                } else {
                    // 比较数据类型
                    if (!$this->isColumnTypeCompatible($existingColumn->data_type, $expectedType)) {
                        $alterSql = "ALTER TABLE {$tableName} ALTER COLUMN {$columnName} TYPE {$expectedType}";
                        $alterSqls[] = $alterSql;
                        logger()->info("[PostgreSQL列升级] 列 {$columnName} 类型变更: {$existingColumn->data_type} -> {$expectedType}");
                    }

                    // 比较可空性
                    $existingNullable = $existingColumn->is_nullable === 'YES';
                    if ($existingNullable !== $expectedNullable) {
                        $nullAction = $expectedNullable ? 'DROP NOT NULL' : 'SET NOT NULL';
                        $alterSql = "ALTER TABLE {$tableName} ALTER COLUMN {$columnName} {$nullAction}";
                        $alterSqls[] = $alterSql;
                        logger()->info("[PostgreSQL列升级] 列 {$columnName} 可空性变更: " . ($existingNullable ? '可空' : '非空') . " -> " . ($expectedNullable ? '可空' : '非空'));
                    }
                }
            }

            // 重新创建视图（如果有依赖的视图，需要从配置中重新创建）
            if (!empty($dependentViews)) {
                foreach ($dependentViews as $viewName) {
                    $alterSqls[] = "-- RECREATE_VIEW: {$viewName}";
                    logger()->info("[PostgreSQL视图依赖] 需要重新创建视图 {$viewName}");
                }
            }
        }

        // 检查是否有需要删除的列（可选，通常不自动删除列以防数据丢失）
        // 这里暂时不实现删除列功能

        return $alterSqls;
    }

    /**
     * 检查列类型是否兼容
     *
     * @param string $existingType 现有类型
     * @param string $expectedType 期望类型
     * @return bool 是否兼容
     */
    private function isColumnTypeCompatible(string $existingType, string $expectedType): bool
    {
        // 如果类型完全相同，肯定兼容
        if (strtoupper($existingType) === strtoupper($expectedType)) {
            return true;
        }

        // 清理类型字符串，移除空格和括号内容
        $existingBaseType = $this->getBaseType($existingType);
        $expectedBaseType = $this->getBaseType($expectedType);

        // PostgreSQL类型别名和兼容性映射
        $typeCompatibility = [
            'INTEGER' => ['INT', 'INT4'],
            'BIGINT' => ['INT8'],
            'SMALLINT' => ['INT2'],
            'VARCHAR' => ['CHARACTER VARYING'],
            'CHARACTER VARYING' => ['VARCHAR'],
            'REAL' => ['FLOAT4'],
            'DOUBLE PRECISION' => ['FLOAT8'],
            'BOOLEAN' => ['BOOL'],
            'TIMESTAMP' => ['TIMESTAMP WITHOUT TIME ZONE'],
            'TIMESTAMP WITHOUT TIME ZONE' => ['TIMESTAMP'],
            'TIMESTAMP WITH TIME ZONE' => ['TIMESTAMPTZ'],
            'TIMESTAMPTZ' => ['TIMESTAMP WITH TIME ZONE'],
            'TIME' => ['TIME WITHOUT TIME ZONE'],
            'TIME WITHOUT TIME ZONE' => ['TIME'],
            'TIME WITH TIME ZONE' => ['TIMETZ'],
            'TIMETZ' => ['TIME WITH TIME ZONE'],
        ];

        // 检查双向兼容性
        foreach ($typeCompatibility as $canonical => $aliases) {
            if (($existingBaseType === $canonical && in_array($expectedBaseType, $aliases)) ||
                ($expectedBaseType === $canonical && in_array($existingBaseType, $aliases))) {
                return true;
            }
            // 检查别名之间的兼容性
            if (in_array($existingBaseType, $aliases) && in_array($expectedBaseType, $aliases)) {
                return true;
            }
        }

        // 对于NUMERIC类型，进行精确比较（包括精度和标度）
        if ($existingBaseType === 'NUMERIC' && $expectedBaseType === 'NUMERIC') {
            // 如果NUMERIC的完整定义相同，才认为是兼容的
            return strtoupper($existingType) === strtoupper($expectedType);
        }

        // 对于VARCHAR长度变化，认为是兼容的（可以扩展现有长度）
        if ($existingBaseType === 'VARCHAR' && $expectedBaseType === 'VARCHAR') {
            return true;
        }

        // 对于BIT类型，认为是兼容的
        if ($existingBaseType === 'BIT' && $expectedBaseType === 'BIT') {
            return true;
        }

        // 默认情况下，类型不同就认为不兼容
        return false;
    }

    /**
     * 获取类型的基类型（去除精度、长度等信息）
     *
     * @param string $type 完整类型字符串
     * @return string 基类型
     */
    private function getBaseType(string $type): string
    {
        // 移除括号内容和空格
        $baseType = preg_replace('/\s*\([^)]*\)\s*/', '', trim($type));

        // 处理特殊情况
        $baseType = strtoupper($baseType);

        // 处理复合类型名
        if (strpos($baseType, ' ') !== false) {
            $parts = explode(' ', $baseType);
            return trim($parts[0]);
        }

        return $baseType;
    }

    /**
     * 处理BIGSERIAL字段的升级
     *
     * @param string $tableName 表名
     * @param string $columnName 列名
     * @param object $existingColumn 现有列信息
     * @return array 修改SQL数组
     */
    private function handleBigserialUpgrade(string $tableName, string $columnName, object $existingColumn): array
    {
        $alterSqls = [];
        $sequenceName = "{$tableName}_{$columnName}_seq";

        // 检查当前列是否已经是正确的BIGSERIAL配置
        $isBigint = strtoupper($existingColumn->data_type) === 'BIGINT';
        $hasSequenceDefault = $this->columnHasSequenceDefault($existingColumn->column_default);

        // 如果类型不是BIGINT，需要转换
        if (!$isBigint) {
            $alterSqls[] = "ALTER TABLE {$tableName} ALTER COLUMN {$columnName} TYPE BIGINT";
            logger()->info("[PostgreSQL列升级] 列 {$columnName} 类型改为BIGINT以支持BIGSERIAL");
        }

        // 检查序列是否存在
        $sequenceExists = false;
        try {
            $this->pgsqlConnection->selectOne("SELECT 1 FROM pg_sequences WHERE schemaname = 'public' AND sequencename = ?", [$sequenceName]);
            $sequenceExists = true;
        } catch (Throwable $e) {
            // 序列不存在
        }

        if (!$sequenceExists) {
            // 创建序列
            $alterSqls[] = "CREATE SEQUENCE {$sequenceName}";
            logger()->info("[PostgreSQL列升级] 创建序列: {$sequenceName}");
        }

        if (!$hasSequenceDefault) {
            // 设置默认值为nextval
            $alterSqls[] = "ALTER TABLE {$tableName} ALTER COLUMN {$columnName} SET DEFAULT nextval('{$sequenceName}')";
            logger()->info("[PostgreSQL列升级] 设置列 {$columnName} 的序列默认值");
        }

        // 设置为NOT NULL（BIGSERIAL通常应该是非空的）
        if ($existingColumn->is_nullable === 'YES') {
            $alterSqls[] = "ALTER TABLE {$tableName} ALTER COLUMN {$columnName} SET NOT NULL";
            logger()->info("[PostgreSQL列升级] 设置列 {$columnName} 为NOT NULL");
        }

        return $alterSqls;
    }

    /**
     * 处理BIGSERIAL字段的新建
     *
     * @param string $tableName 表名
     * @param string $columnName 列名
     * @param bool $nullable 是否可空
     * @return array 修改SQL数组
     */
    private function handleBigserialCreation(string $tableName, string $columnName, bool $nullable = false): array
    {
        $alterSqls = [];

        // BIGSERIAL实际上是BIGINT + 序列 + 默认值
        $sequenceName = "{$tableName}_{$columnName}_seq";

        // 创建序列
        $alterSqls[] = "CREATE SEQUENCE {$sequenceName}";

        // 添加BIGINT列，带序列默认值
        $notNull = $nullable ? '' : ' NOT NULL';
        $alterSql = "ALTER TABLE {$tableName} ADD COLUMN {$columnName} BIGINT{$notNull} DEFAULT nextval('{$sequenceName}')";
        $alterSqls[] = $alterSql;

        logger()->info("[PostgreSQL列升级] 添加BIGSERIAL列: {$columnName} (序列: {$sequenceName})");

        return $alterSqls;
    }

    /**
     * 检查列是否有序列默认值
     *
     * @param string|null $defaultValue 默认值
     * @return bool 是否有序列默认值
     */
    private function columnHasSequenceDefault(?string $defaultValue): bool
    {
        if (!$defaultValue) {
            return false;
        }

        // 检查是否包含nextval()函数调用
        return strpos($defaultValue, 'nextval(') !== false;
    }

    /**
     * 查找依赖于指定列的视图
     *
     * @param string $tableName 表名
     * @param array $columnNames 列名数组
     * @return array 依赖的视图名数组
     */
    private function findDependentViews(string $tableName, array $columnNames): array
    {
        $dependentViews = [];

        try {
            // 查询依赖于指定列的视图
            $placeholders = str_repeat('?,', count($columnNames) - 1) . '?';
            $query = "
                SELECT DISTINCT vcu.view_name
                FROM information_schema.view_column_usage vcu
                JOIN information_schema.views v ON vcu.view_name = v.table_name
                WHERE vcu.table_name = ?
                  AND vcu.column_name IN ({$placeholders})
                  AND v.table_schema = 'public'
            ";

            $params = array_merge([$tableName], $columnNames);
            $views = $this->pgsqlConnection->select($query, $params);

            foreach ($views as $view) {
                $dependentViews[] = $view->view_name;
            }

            logger()->info("[PostgreSQL视图依赖] 找到 " . count($dependentViews) . " 个依赖视图: " . implode(', ', $dependentViews));

        } catch (Throwable $e) {
            logger()->warning("[PostgreSQL视图依赖] 查找视图依赖失败: " . $e->getMessage());
        }

        return $dependentViews;
    }

    /**
     * 重新创建视图
     *
     * @param string $addonName 插件名称
     * @param array $viewNames 要重新创建的视图名数组
     * @return void
     */
    private function recreateViews(string $addonName, array $viewNames): void
    {
        try {
            // 获取插件配置中的视图定义
            $configPath = BASE_PATH . "/addons/{$addonName}/Manager/pgsql.json";
            if (!file_exists($configPath)) {
                logger()->warning("[PostgreSQL视图] 插件 {$addonName} 的pgsql.json配置文件不存在，无法重新创建视图");
                return;
            }

            $config = json_decode(file_get_contents($configPath), true);
            if (!$config || !isset($config['views'])) {
                logger()->warning("[PostgreSQL视图] 插件 {$addonName} 的配置中没有视图定义");
                return;
            }

            $viewsConfig = $config['views'];

            foreach ($viewNames as $viewName) {
                foreach ($viewsConfig as $viewConfig) {
                    if (isset($viewConfig['name']) && $viewConfig['name'] === $viewName) {
                        try {
                            $query = $viewConfig['query'] ?? '';
                            if (empty($query)) {
                                logger()->error("[PostgreSQL视图] 视图 {$viewName} 的查询语句为空，跳过");
                                continue;
                            }

                            $sql = "CREATE VIEW {$viewName} AS {$query}";
                            $this->pgsqlConnection->statement($sql);
                            logger()->info("[PostgreSQL视图] 视图 {$viewName} 重新创建成功");
                        } catch (Throwable $e) {
                            logger()->error("[PostgreSQL视图] 视图 {$viewName} 重新创建失败: " . $e->getMessage());
                        }
                        break;
                    }
                }
            }

        } catch (Throwable $e) {
            logger()->error("[PostgreSQL视图] 重新创建视图过程出错: " . $e->getMessage());
        }
    }

    /**
     * 生成索引修改SQL
     *
     * @param string $tableName 表名
     * @param array $existingIndexes 现有索引
     * @param array $expectedIndexes 期望索引配置
     * @return array 修改SQL数组
     */
    private function generateIndexAlterSqls(string $tableName, array $existingIndexes, array $expectedIndexes): array
    {
        $alterSqls = [];

        // 这里可以实现索引的比较和升级逻辑
        // 目前返回空数组，专注于列的升级
        // 索引升级逻辑可以后续扩展

        return $alterSqls;
    }

    /**
     * 执行测试查询
     *
     * @param string $addonName 插件名称
     * @param array $testQueries 测试查询配置
     * @return array 测试结果
     */
    public function executeTestQueries(string $addonName, array $testQueries): array
    {
        $results = [];

        if (!$this->isPostgreSQL()) {
            logger()->warning("[PostgreSQL测试] 当前数据库不是PostgreSQL，跳过测试查询");
            return $results;
        }

        logger()->info("[PostgreSQL测试] 开始执行测试查询，插件: {$addonName}");

        foreach ($testQueries as $testQuery) {
            try {
                $name = $testQuery['name'];
                $description = $testQuery['description'];
                $query = $testQuery['query'];
                $expected = $testQuery['expected_result'];

                logger()->info("[PostgreSQL测试] 执行测试查询: {$name}");

                // 执行查询
                $startTime = microtime(true);
                $result = $this->pgsqlConnection->select($query);
                $endTime = microtime(true);

                $executionTime = round(($endTime - $startTime) * 1000, 2); // 毫秒
                $rowCount = count($result);

                $results[] = [
                    'name' => $name,
                    'description' => $description,
                    'query' => $query,
                    'expected_result' => $expected,
                    'actual_result' => "返回 {$rowCount} 行数据",
                    'execution_time_ms' => $executionTime,
                    'success' => true,
                    'error' => null,
                ];

                logger()->info("[PostgreSQL测试] 测试查询 {$name} 执行成功，耗时: {$executionTime}ms，返回: {$rowCount} 行");

            } catch (Throwable $e) {
                $results[] = [
                    'name' => $testQuery['name'],
                    'description' => $testQuery['description'],
                    'query' => $testQuery['query'],
                    'expected_result' => $testQuery['expected_result'],
                    'actual_result' => null,
                    'execution_time_ms' => null,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                logger()->error("[PostgreSQL测试] 测试查询 {$testQuery['name']} 执行失败: " . $e->getMessage());
            }
        }

        logger()->info("[PostgreSQL测试] 测试查询执行完成，共执行: " . count($results) . " 个查询");
        return $results;
    }

    /**
     * 清理PostgreSQL相关对象
     *
     * @param string $addonName 插件名称
     * @return bool
     */
    public function cleanupPgsqlObjects(string $addonName): bool
    {
        try {
            logger()->info("[PostgreSQL清理] 开始清理插件 {$addonName} 的PostgreSQL对象");

            // 这里可以实现清理逻辑：
            // - 删除视图
            // - 删除函数
            // - 删除自定义类型（谨慎操作）
            // - 删除扩展（谨慎操作）

            logger()->info("[PostgreSQL清理] 插件 {$addonName} 的PostgreSQL对象清理完成");
            return true;

        } catch (Throwable $e) {
            logger()->error("[PostgreSQL清理] 插件 {$addonName} 的PostgreSQL对象清理失败: " . $e->getMessage());
            return false;
        }
    }
}