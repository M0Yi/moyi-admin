<?php

declare(strict_types=1);

/**
 * PostgreSQL 数据库配置文件校验工具
 *
 * 用于验证 pgsql.json 文件的结构和内容是否符合 SKILLS_PGSQL.md 规范
 */

namespace App\Tools\Database;

use JsonException;
use RuntimeException;

class PgsqlConfigValidator
{
    /**
     * 支持的 PostgreSQL 数据类型
     */
    private const SUPPORTED_COLUMN_TYPES = [
        'BIGSERIAL',
        'SERIAL',
        'BIGINT',
        'INTEGER',
        'INT',
        'SMALLINT',
        'TINYINT',
        'VARCHAR',
        'VARCHAR(255)',
        'CHAR',
        'TEXT',
        'BOOLEAN',
        'BOOL',
        'TIMESTAMP',
        'TIMESTAMPTZ',
        'DATE',
        'TIME',
        'JSON',
        'JSONB',
        'NUMERIC',
        'DECIMAL',
        'FLOAT',
        'DOUBLE',
        'REAL',
        'TSVECTOR',
        'BYTEA',
    ];

    /**
     * 支持的数组类型后缀
     */
    private const ARRAY_TYPE_SUFFIX = '[]';

    /**
     * 支持的索引类型
     */
    private const SUPPORTED_INDEX_TYPES = [
        'btree',
        'gin',
        'gist',
        'hash',
        'spgist',
        'brin',
    ];

    /**
     * 支持的触发器时机
     */
    private const SUPPORTED_TRIGGER_TIMINGS = [
        'BEFORE',
        'AFTER',
        'INSTEAD OF',
    ];

    /**
     * 支持的触发器事件
     */
    private const SUPPORTED_TRIGGER_EVENTS = [
        'INSERT',
        'UPDATE',
        'DELETE',
        'TRUNCATE',
    ];

    /**
     * 支持的约束类型
     */
    private const SUPPORTED_CONSTRAINT_TYPES = [
        'CHECK',
        'FOREIGN KEY',
        'UNIQUE',
        'PRIMARY KEY',
    ];

    /**
     * 支持的函数语言
     */
    private const SUPPORTED_FUNCTION_LANGUAGES = [
        'plpgsql',
        'sql',
        'plperl',
        'plpython3u',
        'c',
        'internal',
    ];

    /**
     * PostgreSQL 保留字
     */
    private const POSTGRESQL_RESERVED_WORDS = [
        'all', 'and', 'any', 'array', 'as', 'asc', 'asymmetric', 'both', 'case', 'cast',
        'check', 'collate', 'column', 'constraint', 'create', 'current_catalog',
        'current_date', 'current_role', 'current_time', 'current_timestamp',
        'current_user', 'default', 'deferrable', 'desc', 'distinct', 'do', 'else',
        'end', 'except', 'false', 'fetch', 'for', 'foreign', 'from', 'grant',
        'group', 'having', 'in', 'initially', 'intersect', 'into', 'lateral',
        'leading', 'limit', 'localtime', 'localtimestamp', 'not', 'null', 'offset',
        'on', 'only', 'or', 'order', 'placing', 'primary', 'references',
        'returning', 'select', 'session_user', 'some', 'symmetric', 'table',
        'then', 'to', 'trailing', 'true', 'union', 'unique', 'user', 'using',
        'variadic', 'when', 'where', 'window', 'with',
    ];

    /**
     * 推荐的 PostgreSQL 扩展
     */
    private const RECOMMENDED_EXTENSIONS = [
        'pg_trgm',
        'btree_gin',
        'btree_gist',
        'pgcrypto',
        'uuid-ossp',
        'hstore',
    ];

    /**
     * 验证错误集合
     */
    private array $errors = [];

    /**
     * 验证警告集合
     */
    private array $warnings = [];

    /**
     * 验证 JSON 文件
     *
     * @param string $filePath JSON 文件路径
     * @return bool
     */
    public function validate(string $filePath): bool
    {
        $this->errors = [];
        $this->warnings = [];

        // 检查文件是否存在
        if (! file_exists($filePath)) {
            $this->errors[] = "文件不存在: {$filePath}";
            return false;
        }

        // 读取并解析 JSON
        try {
            $content = file_get_contents($filePath);
            $config = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->errors[] = "JSON 解析失败: " . $e->getMessage();
            return false;
        }

        return $this->validateConfig($config, $filePath);
    }

    /**
     * 验证配置数组
     *
     * @param array $config 配置数组
     * @param string $filePath 文件路径（用于错误信息）
     * @return bool
     */
    public function validateConfig(array $config, string $filePath = ''): bool
    {
        $this->errors = [];
        $this->warnings = [];

        // 验证必填字段
        $this->validateRequiredFields($config);

        // 验证版本号
        if (isset($config['version'])) {
            $this->validateVersion($config['version']);
        }

        // 验证扩展
        if (isset($config['extensions'])) {
            $this->validateExtensions($config['extensions']);
        }

        // 验证类型
        if (isset($config['types'])) {
            $this->validateTypes($config['types']);
        }

        // 验证表结构
        if (isset($config['tables'])) {
            $this->validateTables($config['tables']);
        }

        // 验证函数
        if (isset($config['functions'])) {
            $this->validateFunctions($config['functions']);
        }

        // 验证视图
        if (isset($config['views'])) {
            $this->validateViews($config['views']);
        }

        // 验证示例数据
        if (isset($config['sample_data'])) {
            $this->validateSampleData($config['sample_data'], $config['tables'] ?? []);
        }

        return empty($this->errors);
    }

    /**
     * 获取验证错误
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取验证警告
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * 获取错误报告
     */
    public function getReport(): array
    {
        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings),
        ];
    }

    /**
     * 打印验证报告
     */
    public function printReport(string $filePath = ''): void
    {
        $report = $this->getReport();

        echo "========================================\n";
        echo "PostgreSQL 配置文件验证报告\n";
        echo "========================================\n";

        if ($filePath) {
            echo "文件: {$filePath}\n";
        }

        echo "----------------------------------------\n";
        echo "验证结果: " . ($report['valid'] ? "✓ 通过" : "✗ 失败") . "\n";
        echo "错误数量: {$report['error_count']}\n";
        echo "警告数量: {$report['warning_count']}\n";
        echo "----------------------------------------\n";

        if (! empty($report['errors'])) {
            echo "\n错误:\n";
            foreach ($report['errors'] as $index => $error) {
                echo "  [" . ($index + 1) . "] {$error}\n";
            }
        }

        if (! empty($report['warnings'])) {
            echo "\n警告:\n";
            foreach ($report['warnings'] as $index => $warning) {
                echo "  [" . ($index + 1) . "] {$warning}\n";
            }
        }

        echo "========================================\n";
    }

    /**
     * 验证必填字段
     */
    private function validateRequiredFields(array $config): void
    {
        $requiredFields = ['version', 'tables', 'functions', 'views'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (! isset($config[$field])) {
                $missingFields[] = $field;
            }
        }

        if (! empty($missingFields)) {
            $this->errors[] = "缺少必填字段: " . implode(', ', $missingFields);
        }
    }

    /**
     * 验证版本号格式
     */
    private function validateVersion(string $version): void
    {
        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            $this->errors[] = "版本号格式无效，应为 MAJOR.MINOR.PATCH 格式，如: 1.0.0";
        }
    }

    /**
     * 验证扩展配置
     */
    private function validateExtensions(array $extensions): void
    {
        if (! is_array($extensions)) {
            $this->errors[] = "extensions 必须是数组";
            return;
        }

        $seen = [];
        foreach ($extensions as $extension) {
            if (! is_string($extension)) {
                $this->errors[] = "扩展名必须是字符串";
                continue;
            }

            $ext = strtolower($extension);
            if (in_array($ext, $seen, true)) {
                $this->errors[] = "扩展名重复: {$extension}";
            }
            $seen[] = $ext;

            // 检查是否为推荐的扩展
            if (! in_array($ext, self::RECOMMENDED_EXTENSIONS, true)) {
                $this->warnings[] = "非常用扩展: {$extension}";
            }
        }
    }

    /**
     * 验证类型定义
     */
    private function validateTypes(array $types): void
    {
        foreach ($types as $name => $definition) {
            // 验证类型名称
            if (! $this->isValidIdentifier($name)) {
                $this->errors[] = "类型名称无效: {$name}";
            }

            if (! is_string($definition)) {
                $this->errors[] = "类型定义必须是字符串: {$name}";
                continue;
            }

            // 验证 ENUM 类型
            if (str_starts_with($definition, 'ENUM')) {
                $this->validateEnumDefinition($name, $definition);
            }
        }
    }

    /**
     * 验证 ENUM 定义
     */
    private function validateEnumDefinition(string $name, string $definition): void
    {
        // 检查 ENUM 格式: ENUM('value1', 'value2', ...)
        if (! preg_match("/^ENUM\(\s*'[^']+'(?:\s*,\s*'[^']+')*\s*\)$/", $definition)) {
            $this->errors[] = "ENUM 格式无效: {$name} = {$definition}";
            return;
        }

        // 提取值并检查重复
        preg_match_all("/'([^']+)'/", $definition, $matches);
        $values = $matches[1] ?? [];

        if (count($values) !== count(array_unique($values))) {
            $this->errors[] = "ENUM 值重复: {$name}";
        }

        // 检查值命名规范
        foreach ($values as $value) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', $value)) {
                $this->warnings[] = "ENUM 值命名不规范: '{$value}' (建议使用小写字母和下划线)";
            }
        }
    }

    /**
     * 验证表结构
     */
    private function validateTables(array $tables): void
    {
        foreach ($tables as $tableName => $tableConfig) {
            // 验证表名
            if (! $this->isValidTableName($tableName)) {
                $this->errors[] = "表名无效: {$tableName}";
            }

            if (! is_array($tableConfig)) {
                $this->errors[] = "表配置必须是数组: {$tableName}";
                continue;
            }

            // 验证表注释
            if (! isset($tableConfig['comment']) || ! is_string($tableConfig['comment'])) {
                $this->errors[] = "表缺少 comment 字段或格式无效: {$tableName}";
            }

            // 验证列定义
            if (isset($tableConfig['columns'])) {
                $this->validateColumns($tableName, $tableConfig['columns']);
            } else {
                $this->errors[] = "表缺少 columns 定义: {$tableName}";
            }

            // 验证约束
            if (isset($tableConfig['constraints'])) {
                $this->validateConstraints($tableName, $tableConfig['constraints']);
            }

            // 验证索引
            if (isset($tableConfig['indexes'])) {
                $this->validateIndexes($tableName, $tableConfig['indexes']);
            }

            // 验证触发器
            if (isset($tableConfig['triggers'])) {
                $this->validateTriggers($tableName, $tableConfig['triggers']);
            }
        }
    }

    /**
     * 验证列定义
     */
    private function validateColumns(string $tableName, array $columns): void
    {
        $primaryKeyCount = 0;
        $columnNames = [];

        foreach ($columns as $columnName => $columnConfig) {
            // 验证列名
            if (! $this->isValidIdentifier($columnName)) {
                $this->errors[] = "列名无效: {$tableName}.{$columnName}";
            }

            if (in_array($columnName, $columnNames, true)) {
                $this->errors[] = "列名重复: {$tableName}.{$columnName}";
            }
            $columnNames[] = $columnName;

            if (! is_array($columnConfig)) {
                $this->errors[] = "列配置必须是数组: {$tableName}.{$columnName}";
                continue;
            }

            // 验证数据类型
            if (! isset($columnConfig['type'])) {
                $this->errors[] = "列缺少 type 字段: {$tableName}.{$columnName}";
            } else {
                $this->validateColumnType($tableName, $columnName, $columnConfig['type']);
            }

            // 验证主键
            if (isset($columnConfig['primary']) && $columnConfig['primary'] === true) {
                $primaryKeyCount++;
                if ($columnName !== 'id') {
                    $this->warnings[] = "主键列建议命名为 'id': {$tableName}.{$columnName}";
                }
            }

            // 验证注释
            if (! isset($columnConfig['comment']) || ! is_string($columnConfig['comment'])) {
                $this->warnings[] = "列缺少 comment 字段: {$tableName}.{$columnName}";
            }
        }

        // 检查主键
        if ($primaryKeyCount === 0) {
            $this->warnings[] = "表没有定义主键: {$tableName}";
        } elseif ($primaryKeyCount > 1) {
            $this->warnings[] = "表有多个主键列（复合主键）: {$tableName}";
        }
    }

    /**
     * 验证列类型
     */
    private function validateColumnType(string $tableName, string $columnName, string $type): void
    {
        // 检查是否支持该类型
        $baseType = str_replace(self::ARRAY_TYPE_SUFFIX, '', $type);

        if (! in_array($baseType, self::SUPPORTED_COLUMN_TYPES, true)) {
            $this->errors[] = "不支持的列类型: {$tableName}.{$columnName} = {$type}";
        }

        // 验证 VARCHAR 长度
        if (str_starts_with($type, 'VARCHAR') && preg_match('/VARCHAR\((\d+)\)/', $type, $matches)) {
            $length = (int) $matches[1];
            if ($length > 255) {
                $this->warnings[] = "VARCHAR 长度超过 255，建议使用 TEXT: {$tableName}.{$columnName}";
            }
        }
    }

    /**
     * 验证约束定义
     */
    private function validateConstraints(string $tableName, array $constraints): void
    {
        $constraintNames = [];

        foreach ($constraints as $constraint) {
            if (! is_array($constraint)) {
                $this->errors[] = "约束配置必须是数组: {$tableName}";
                continue;
            }

            // 验证约束名
            if (! isset($constraint['name'])) {
                $this->errors[] = "约束缺少 name 字段: {$tableName}";
                continue;
            }

            $constraintName = $constraint['name'];
            if (in_array($constraintName, $constraintNames, true)) {
                $this->errors[] = "约束名重复: {$tableName}.{$constraintName}";
            }
            $constraintNames[] = $constraintName;

            // 验证约束类型
            if (! isset($constraint['type'])) {
                $this->errors[] = "约束缺少 type 字段: {$tableName}.{$constraintName}";
                continue;
            }

            $constraintType = $constraint['type'];
            if (! in_array($constraintType, self::SUPPORTED_CONSTRAINT_TYPES, true)) {
                $this->errors[] = "不支持的约束类型: {$tableName}.{$constraintName} = {$constraintType}";
            }

            // 根据类型验证特定字段
            switch ($constraintType) {
                case 'CHECK':
                    if (! isset($constraint['condition']) || ! is_string($constraint['condition'])) {
                        $this->errors[] = "CHECK 约束缺少 condition 字段: {$tableName}.{$constraintName}";
                    }
                    break;

                case 'FOREIGN KEY':
                    if (! isset($constraint['references']) || ! is_array($constraint['references'])) {
                        $this->errors[] = "FOREIGN KEY 约束缺少 references 字段: {$tableName}.{$constraintName}";
                    } else {
                        if (! isset($constraint['references']['table']) || ! is_string($constraint['references']['table'])) {
                            $this->errors[] = "FOREIGN KEY 缺少 references.table: {$tableName}.{$constraintName}";
                        }
                    }
                    break;

                case 'UNIQUE':
                case 'PRIMARY KEY':
                    if (! isset($constraint['columns']) || ! is_array($constraint['columns'])) {
                        $this->errors[] = "约束缺少 columns 字段: {$tableName}.{$constraintName}";
                    }
                    break;
            }
        }
    }

    /**
     * 验证索引定义
     */
    private function validateIndexes(string $tableName, array $indexes): void
    {
        $indexNames = [];

        foreach ($indexes as $index) {
            if (! is_array($index)) {
                $this->errors[] = "索引配置必须是数组: {$tableName}";
                continue;
            }

            // 验证索引名
            if (! isset($index['name'])) {
                $this->errors[] = "索引缺少 name 字段: {$tableName}";
                continue;
            }

            $indexName = $index['name'];
            if (in_array($indexName, $indexNames, true)) {
                $this->errors[] = "索引名重复: {$tableName}.{$indexName}";
            }
            $indexNames[] = $indexName;

            // 验证索引列
            if (! isset($index['columns']) || ! is_array($index['columns']) || empty($index['columns'])) {
                $this->errors[] = "索引缺少 columns 字段或为空: {$tableName}.{$indexName}";
            }

            // 验证索引类型
            if (isset($index['type'])) {
                if (! in_array($index['type'], self::SUPPORTED_INDEX_TYPES, true)) {
                    $this->errors[] = "不支持的索引类型: {$tableName}.{$indexName} = {$index['type']}";
                }
            }

            // 验证部分索引条件
            if (isset($index['where'])) {
                if (! is_string($index['where'])) {
                    $this->errors[] = "索引 where 条件必须是字符串: {$tableName}.{$indexName}";
                }
            }
        }
    }

    /**
     * 验证触发器定义
     */
    private function validateTriggers(string $tableName, array $triggers): void
    {
        $triggerNames = [];

        foreach ($triggers as $trigger) {
            if (! is_array($trigger)) {
                $this->errors[] = "触发器配置必须是数组: {$tableName}";
                continue;
            }

            // 验证触发器名
            if (! isset($trigger['name'])) {
                $this->errors[] = "触发器缺少 name 字段: {$tableName}";
                continue;
            }

            $triggerName = $trigger['name'];
            if (in_array($triggerName, $triggerNames, true)) {
                $this->errors[] = "触发器名重复: {$tableName}.{$triggerName}";
            }
            $triggerNames[] = $triggerName;

            // 验证触发器时机
            if (! isset($trigger['timing'])) {
                $this->errors[] = "触发器缺少 timing 字段: {$tableName}.{$triggerName}";
            } elseif (! in_array($trigger['timing'], self::SUPPORTED_TRIGGER_TIMINGS, true)) {
                $this->errors[] = "不支持的触发器时机: {$tableName}.{$triggerName} = {$trigger['timing']}";
            }

            // 验证触发器事件
            if (! isset($trigger['events']) || ! is_array($trigger['events']) || empty($trigger['events'])) {
                $this->errors[] = "触发器缺少 events 字段或为空: {$tableName}.{$triggerName}";
            } else {
                foreach ($trigger['events'] as $event) {
                    if (! in_array($event, self::SUPPORTED_TRIGGER_EVENTS, true)) {
                        $this->errors[] = "不支持的触发器事件: {$tableName}.{$triggerName} = {$event}";
                    }
                }
            }

            // 验证触发器函数
            if (! isset($trigger['function']) || ! is_string($trigger['function'])) {
                $this->errors[] = "触发器缺少 function 字段: {$tableName}.{$triggerName}";
            }
        }
    }

    /**
     * 验证函数定义
     */
    private function validateFunctions(array $functions): void
    {
        $functionNames = [];

        foreach ($functions as $function) {
            if (! is_array($function)) {
                $this->errors[] = "函数配置必须是数组";
                continue;
            }

            // 验证函数名
            if (! isset($function['name'])) {
                $this->errors[] = "函数缺少 name 字段";
                continue;
            }

            $functionName = $function['name'];
            if (in_array($functionName, $functionNames, true)) {
                $this->errors[] = "函数名重复: {$functionName}";
            }
            $functionNames[] = $functionName;

            // 验证返回类型
            if (! isset($function['returns'])) {
                $this->errors[] = "函数缺少 returns 字段: {$functionName}";
            }

            // 验证语言
            if (! isset($function['language'])) {
                $this->errors[] = "函数缺少 language 字段: {$functionName}";
            } elseif (! in_array($function['language'], self::SUPPORTED_FUNCTION_LANGUAGES, true)) {
                $this->errors[] = "不支持的函数语言: {$functionName} = {$function['language']}";
            }

            // 验证函数体
            if (! isset($function['code']) || ! is_string($function['code'])) {
                $this->errors[] = "函数缺少 code 字段: {$functionName}";
            }

            // 验证参数格式
            if (isset($function['parameters'])) {
                if (! is_array($function['parameters'])) {
                    $this->errors[] = "函数 parameters 必须是数组: {$functionName}";
                }
            }
        }
    }

    /**
     * 验证视图定义
     */
    private function validateViews(array $views): void
    {
        $viewNames = [];

        foreach ($views as $view) {
            if (! is_array($view)) {
                $this->errors[] = "视图配置必须是数组";
                continue;
            }

            // 验证视图名
            if (! isset($view['name'])) {
                $this->errors[] = "视图缺少 name 字段";
                continue;
            }

            $viewName = $view['name'];
            if (in_array($viewName, $viewNames, true)) {
                $this->errors[] = "视图名重复: {$viewName}";
            }
            $viewNames[] = $viewName;

            // 验证视图查询
            if (! isset($view['query']) || ! is_string($view['query'])) {
                $this->errors[] = "视图缺少 query 字段: {$viewName}";
            }

            // 验证视图描述
            if (! isset($view['description']) || ! is_string($view['description'])) {
                $this->warnings[] = "视图缺少 description 字段: {$viewName}";
            }

            // 验证命名规范（应该以 _view 结尾）
            if (! str_ends_with($viewName, '_view')) {
                $this->warnings[] = "视图名建议以 '_view' 结尾: {$viewName}";
            }
        }
    }

    /**
     * 验证示例数据
     */
    private function validateSampleData(array $sampleData, array $tables): void
    {
        $definedTables = array_keys($tables);
        $seenTables = [];

        foreach ($sampleData as $data) {
            if (! is_array($data)) {
                $this->errors[] = "sample_data 元素必须是数组";
                continue;
            }

            // 验证表名
            if (! isset($data['table'])) {
                $this->errors[] = "sample_data 缺少 table 字段";
                continue;
            }

            $tableName = $data['table'];
            if (! in_array($tableName, $definedTables, true)) {
                $this->errors[] = "sample_data 引用的表不存在: {$tableName}";
                continue;
            }

            if (in_array($tableName, $seenTables, true)) {
                $this->errors[] = "sample_data 中表重复: {$tableName}";
            }
            $seenTables[] = $tableName;

            // 验证数据
            if (! isset($data['data']) || ! is_array($data['data'])) {
                $this->errors[] = "sample_data 缺少 data 字段: {$tableName}";
                continue;
            }

            foreach ($data['data'] as $index => $row) {
                if (! is_array($row)) {
                    $this->errors[] = "sample_data data 元素必须是对象: {$tableName}[{$index}]";
                    continue;
                }

                // 验证列名是否存在
                if (isset($tables[$tableName]['columns'])) {
                    $definedColumns = array_keys($tables[$tableName]['columns']);
                    foreach (array_keys($row) as $columnName) {
                        if (! in_array($columnName, $definedColumns, true)) {
                            $this->warnings[] = "sample_data 中引用了不存在的列: {$tableName}.{$columnName}";
                        }
                    }
                }
            }
        }
    }

    /**
     * 验证标识符名称
     */
    private function isValidIdentifier(string $name): bool
    {
        // 标识符必须以字母开头，只能包含字母、数字、下划线
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name) === 1;
    }

    /**
     * 验证表名
     */
    private function isValidTableName(string $name): bool
    {
        // 表名必须以小写字母开头，只能包含小写字母、数字、下划线
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            return false;
        }

        // 检查是否包含保留字
        if (in_array($name, self::POSTGRESQL_RESERVED_WORDS, true)) {
            return false;
        }

        return true;
    }
}

