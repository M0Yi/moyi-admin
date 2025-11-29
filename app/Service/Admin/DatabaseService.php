<?php

declare(strict_types=1);

namespace App\Service\Admin;

use Hyperf\DbConnection\Db;
use function Hyperf\Config\config;

/**
 * 数据库服务类
 * 用于读取数据库表结构信息
 */
class DatabaseService
{
    /**
     * 获取所有数据库连接配置
     *
     * @return array 返回连接名称和数据库名称的映射
     */
    public function getAllConnections(): array
    {
        $connections = config('databases', []);
        $result = [];

        foreach ($connections as $name => $config) {
            if (is_array($config) && isset($config['database'])) {
                $result[$name] = [
                    'name' => $name,
                    'driver' => $config['driver'] ?? 'mysql',
                    'database' => $config['database'] ?? '',
                    'host' => $config['host'] ?? 'localhost',
                    'port' => $config['port'] ?? 3306,
                ];
            }
        }

        return $result;
    }


    /**
     * 检查表是否有指定字段
     *
     * @param string $tableName 表名
     * @param string $columnName 字段名
     * @param string|null $connection 数据库连接名称
     * @return bool
     */
    public function hasColumn(string $tableName, string $columnName, ?string $connection = null): bool
    {
        try {
            $connection = $connection ?? 'default';
            $database = config("databases.{$connection}.database");
            $driver = config("databases.{$connection}.driver", 'mysql');
            
            if ($driver === 'pgsql') {
                // PostgreSQL
                $result = Db::connection($connection)->selectOne(
                    "SELECT COUNT(*) as count 
                     FROM information_schema.columns 
                     WHERE table_schema = ? AND table_name = ? AND column_name = ?",
                    [$database, $tableName, $columnName]
                );
            } else {
                // MySQL
                $result = Db::connection($connection)->selectOne(
                    "SELECT COUNT(*) as count 
                     FROM information_schema.COLUMNS 
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                    [$database, $tableName, $columnName]
                );
            }
            
            return (int) $result->count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 获取所有数据表
     *
     * @param string|null $connection 数据库连接名称，默认为 'default'
     * @return array
     */
    public function getAllTables(?string $connection = null): array
    {
        $connection = $connection ?? 'default';
        $database = config("databases.{$connection}.database");
        $driver = config("databases.{$connection}.driver", 'mysql');

        if (!$database) {
            return [];
        }

        // 根据数据库类型使用不同的 SQL 查询
        if ($driver === 'pgsql') {
            // PostgreSQL 查询
            $tables = Db::connection($connection)->select("
                SELECT
                    t.table_name as name,
                    COALESCE(obj_description(c.oid, 'pg_class'), '') as comment,
                    0 as row_count
                FROM information_schema.tables t
                LEFT JOIN pg_class c ON c.relname = t.table_name
                LEFT JOIN pg_namespace n ON n.oid = c.relnamespace AND n.nspname = t.table_schema
                WHERE t.table_schema = ?
                  AND t.table_type = 'BASE TABLE'
                ORDER BY t.table_name ASC
            ", [$database]);
        } else {
            // MySQL 查询（默认）
            $tables = Db::connection($connection)->select("
                SELECT
                    TABLE_NAME as name,
                    TABLE_COMMENT as comment,
                    TABLE_ROWS as row_count
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?
                ORDER BY TABLE_NAME ASC
            ", [$database]);
        }

        $result = [];
        foreach ($tables as $table) {
            $result[] = [
                'name' => $table->name,
                'comment' => $table->comment ?? '',
                'rows' => (int) ($table->row_count ?? 0),
            ];
        }

        return $result;
    }

    /**
     * 获取表注释
     *
     * @param string $tableName
     * @param string|null $connection 数据库连接名称，默认为 'default'
     * @return string
     */
    public function getTableComment(string $tableName, ?string $connection = null): string
    {
        $connection = $connection ?? 'default';
        $database = config("databases.{$connection}.database");
        $driver = config("databases.{$connection}.driver", 'mysql');

        if (!$database) {
            return '';
        }

        // 根据数据库类型使用不同的 SQL 查询
        if ($driver === 'pgsql') {
            // PostgreSQL 查询
            $result = Db::connection($connection)->selectOne(
                "SELECT COALESCE(obj_description(c.oid, 'pg_class'), '') as comment
                 FROM information_schema.tables t
                 LEFT JOIN pg_class c ON c.relname = t.table_name
                 LEFT JOIN pg_namespace n ON n.oid = c.relnamespace AND n.nspname = t.table_schema
                 WHERE t.table_schema = ? AND t.table_name = ?",
                [$database, $tableName]
            );
            return $result->comment ?? '';
        } else {
            // MySQL 查询（默认）
            $result = Db::connection($connection)->selectOne(
                "SELECT TABLE_COMMENT as comment
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
                [$database, $tableName]
            );
            return $result->comment ?? '';
        }
    }

    /**
     * 获取表行数
     *
     * @param string $tableName
     * @param string|null $connection 数据库连接名称，默认为 'default'
     * @return int
     */
    public function getTableRowCount(string $tableName, ?string $connection = null): int
    {
        try {
            $connection = $connection ?? 'default';
            $driver = config("databases.{$connection}.driver", 'mysql');
            
            // PostgreSQL 使用双引号，MySQL 使用反引号
            if ($driver === 'pgsql') {
                $result = Db::connection($connection)->selectOne("SELECT COUNT(*) as count FROM \"{$tableName}\"");
            } else {
                $result = Db::connection($connection)->selectOne("SELECT COUNT(*) as count FROM `{$tableName}`");
            }
            
            return (int) $result->count;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 获取表的所有字段信息
     *
     * @param string $tableName
     * @param string|null $connection 数据库连接名称，默认为 'default'
     * @return array
     */
    public function getTableColumns(string $tableName, ?string $connection = null): array
    {
        $connection = $connection ?? 'default';
        
        // 【日志】开始分析表结构
        logger('crud_generator')->info("========== 开始分析表结构 ==========", [
            'table_name' => $tableName,
            'connection' => $connection,
        ]);

        $database = config("databases.{$connection}.database");
        $driver = config("databases.{$connection}.driver", 'mysql');
        
        if (!$database) {
            logger('crud_generator')->error("数据库连接配置不存在", [
                'connection' => $connection,
            ]);
            return [];
        }
        
        // 根据数据库类型使用不同的 SQL 查询
        if ($driver === 'pgsql') {
            // PostgreSQL 查询 - 使用更简单可靠的方式
            $columns = Db::connection($connection)->select(
                "SELECT
                    a.column_name as name,
                    CASE 
                        WHEN a.data_type = 'character varying' THEN 'varchar(' || a.character_maximum_length || ')'
                        WHEN a.data_type = 'character' THEN 'char(' || a.character_maximum_length || ')'
                        WHEN a.data_type = 'numeric' THEN 'numeric(' || a.numeric_precision || ',' || COALESCE(a.numeric_scale, 0) || ')'
                        WHEN a.data_type = 'integer' THEN 'integer'
                        WHEN a.data_type = 'bigint' THEN 'bigint'
                        WHEN a.data_type = 'smallint' THEN 'smallint'
                        WHEN a.data_type = 'real' THEN 'real'
                        WHEN a.data_type = 'double precision' THEN 'double'
                        WHEN a.data_type = 'boolean' THEN 'boolean'
                        WHEN a.data_type = 'date' THEN 'date'
                        WHEN a.data_type = 'time without time zone' THEN 'time'
                        WHEN a.data_type = 'timestamp without time zone' THEN 'timestamp'
                        WHEN a.data_type = 'timestamp with time zone' THEN 'timestamptz'
                        WHEN a.data_type = 'text' THEN 'text'
                        WHEN a.data_type = 'json' THEN 'json'
                        WHEN a.data_type = 'jsonb' THEN 'jsonb'
                        ELSE a.data_type
                    END as type,
                    a.data_type,
                    a.is_nullable as nullable,
                    a.column_default as default_value,
                    CASE 
                        WHEN pk.column_name IS NOT NULL THEN 'PRI'
                        WHEN uk.column_name IS NOT NULL THEN 'UNI'
                        ELSE ''
                    END as key,
                    CASE 
                        WHEN a.column_default LIKE 'nextval%' THEN 'auto_increment'
                        ELSE ''
                    END as extra,
                    COALESCE(pgd.description, '') as comment,
                    CASE 
                        WHEN a.character_maximum_length IS NOT NULL THEN a.character_maximum_length::integer
                        ELSE NULL
                    END as max_length
                 FROM information_schema.columns a
                 LEFT JOIN pg_class pgc ON pgc.relname = a.table_name
                 LEFT JOIN pg_namespace pgn ON pgn.oid = pgc.relnamespace AND pgn.nspname = a.table_schema
                 LEFT JOIN pg_attribute pga ON pga.attrelid = pgc.oid AND pga.attname = a.column_name
                 LEFT JOIN pg_description pgd ON pgd.objoid = pgc.oid AND pgd.objsubid = pga.attnum
                 LEFT JOIN (
                     SELECT kcu.column_name
                     FROM information_schema.table_constraints tc
                     JOIN information_schema.key_column_usage kcu 
                         ON tc.constraint_name = kcu.constraint_name
                     WHERE tc.table_schema = ? 
                       AND tc.table_name = ?
                       AND tc.constraint_type = 'PRIMARY KEY'
                 ) pk ON pk.column_name = a.column_name
                 LEFT JOIN (
                     SELECT kcu.column_name
                     FROM information_schema.table_constraints tc
                     JOIN information_schema.key_column_usage kcu 
                         ON tc.constraint_name = kcu.constraint_name
                     WHERE tc.table_schema = ? 
                       AND tc.table_name = ?
                       AND tc.constraint_type = 'UNIQUE'
                 ) uk ON uk.column_name = a.column_name
                 WHERE a.table_schema = ? AND a.table_name = ?
                 ORDER BY a.ordinal_position",
                [$database, $tableName, $database, $tableName, $database, $tableName]
            );
        } else {
            // MySQL 查询（默认）
            $columns = Db::connection($connection)->select(
                "SELECT
                    COLUMN_NAME as name,
                    COLUMN_TYPE as type,
                    DATA_TYPE as data_type,
                    IS_NULLABLE as nullable,
                    COLUMN_DEFAULT as default_value,
                    COLUMN_KEY as `key`,
                    EXTRA as extra,
                    COLUMN_COMMENT as comment,
                    CHARACTER_MAXIMUM_LENGTH as max_length
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                 ORDER BY ORDINAL_POSITION",
                [$database, $tableName]
            );
        }

        // 【日志】记录查询到的字段数量
        logger('crud_generator')->debug("从数据库查询到 " . count($columns) . " 个字段");

        $result = [];
        foreach ($columns as $column) {
            // 判断是否是系统保留字段（不可改变编辑状态）
            $isSystemField = $this->isSystemField($column);

            // 判断字段是否可编辑
            $isEditable = $this->isEditable($column);

            // 特殊处理：_count 字段默认禁止编辑（通常是系统自动计算的字段）
            $isCountField = str_ends_with($column->name, '_count') && 
                           in_array($column->data_type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint']);
            if ($isCountField) {
                $isEditable = false; // 计数字段默认不可编辑
            }

            $columnData = [
                'name' => $column->name,
                'type' => $column->type,
                'data_type' => $column->data_type,
                'model_type' => $this->guessModelType($column),
                'nullable' => $column->nullable === 'YES',
                // 不可编辑字段不需要默认值（系统自动维护）
                'default_value' => $isEditable ? $this->cleanDefaultValue($column->default_value) : null,
                'is_primary' => $column->key === 'PRI',
                'is_unique' => $column->key === 'UNI',
                'is_auto_increment' => str_contains($column->extra, 'auto_increment'),
                'comment' => $column->comment ?? '',
                'max_length' => $column->max_length,
                'form_type' => $this->guessFormType($column),
                'number_step' => $this->guessNumberStep($column),
                'show_in_list' => $this->shouldShowInList($column),
                'searchable' => $this->isSearchable($column),
                'sortable' => true,
                'editable' => $isEditable,
                'is_system_field' => $isSystemField, // 标识是否是系统字段
            ];

            // 提取 ENUM/SET 类型的选项
            if (in_array($column->data_type, ['enum', 'set'])) {
                $columnData['options'] = $this->extractEnumOptions($column->type);
            }

            // 从注释中提取选项配置（格式：字段名:key1=值1,key2=值2）
            if ($column->comment && str_contains($column->comment, ':') && str_contains($column->comment, '=')) {
                $commentOptions = $this->extractOptionsFromComment($column->comment);
                if (!empty($commentOptions)) {
                    $columnData['options'] = $commentOptions;
                }
            }

            // 如果是关联类型字段，添加关联配置
            if ($columnData['form_type'] === 'relation') {
                $relationConfig = $this->guessRelationConfig($column);
                $columnData['relation'] = $relationConfig;
            }

            $result[] = $columnData;
        }

        // 【日志】表结构分析完成，统计信息
        $formTypeCounts = [];
        foreach ($result as $col) {
            $formType = $col['form_type'];
            $formTypeCounts[$formType] = ($formTypeCounts[$formType] ?? 0) + 1;
        }

        logger('crud_generator')->info("========== 表结构分析完成 ==========", [
            'table_name' => $tableName,
            'total_columns' => count($result),
            'form_type_distribution' => $formTypeCounts,
        ]);

        return $result;
    }

    /**
     * 获取原始表结构（未经过后端处理，用于前端自动识别）
     * 只返回数据库原始信息，不做任何推断
     *
     * @param string $tableName 表名
     * @param string|null $connection 数据库连接名称
     * @return array
     */
    public function getRawTableColumns(string $tableName, ?string $connection = null): array
    {
        $connection = $connection ?? 'default';
        $database = config("databases.{$connection}.database");
        $driver = config("databases.{$connection}.driver", 'mysql');
        
        if (!$database) {
            return [];
        }
        
        // 根据数据库类型使用不同的 SQL 查询（复用 getTableColumns 的查询逻辑）
        if ($driver === 'pgsql') {
            $columns = Db::connection($connection)->select(
                "SELECT
                    a.column_name as name,
                    CASE 
                        WHEN a.data_type = 'character varying' THEN 'varchar(' || a.character_maximum_length || ')'
                        WHEN a.data_type = 'character' THEN 'char(' || a.character_maximum_length || ')'
                        WHEN a.data_type = 'numeric' THEN 'numeric(' || a.numeric_precision || ',' || COALESCE(a.numeric_scale, 0) || ')'
                        WHEN a.data_type = 'integer' THEN 'integer'
                        WHEN a.data_type = 'bigint' THEN 'bigint'
                        WHEN a.data_type = 'smallint' THEN 'smallint'
                        WHEN a.data_type = 'real' THEN 'real'
                        WHEN a.data_type = 'double precision' THEN 'double'
                        WHEN a.data_type = 'boolean' THEN 'boolean'
                        WHEN a.data_type = 'date' THEN 'date'
                        WHEN a.data_type = 'time without time zone' THEN 'time'
                        WHEN a.data_type = 'timestamp without time zone' THEN 'timestamp'
                        WHEN a.data_type = 'timestamp with time zone' THEN 'timestamptz'
                        WHEN a.data_type = 'text' THEN 'text'
                        WHEN a.data_type = 'json' THEN 'json'
                        WHEN a.data_type = 'jsonb' THEN 'jsonb'
                        ELSE a.data_type
                    END as type,
                    a.data_type,
                    a.is_nullable as nullable,
                    a.column_default as default_value,
                    CASE 
                        WHEN pk.column_name IS NOT NULL THEN 'PRI'
                        WHEN uk.column_name IS NOT NULL THEN 'UNI'
                        ELSE ''
                    END as key,
                    CASE 
                        WHEN a.column_default LIKE 'nextval%' THEN 'auto_increment'
                        ELSE ''
                    END as extra,
                    COALESCE(pgd.description, '') as comment,
                    CASE 
                        WHEN a.character_maximum_length IS NOT NULL THEN a.character_maximum_length::integer
                        ELSE NULL
                    END as max_length
                 FROM information_schema.columns a
                 LEFT JOIN pg_class pgc ON pgc.relname = a.table_name
                 LEFT JOIN pg_namespace pgn ON pgn.oid = pgc.relnamespace AND pgn.nspname = a.table_schema
                 LEFT JOIN pg_attribute pga ON pga.attrelid = pgc.oid AND pga.attname = a.column_name
                 LEFT JOIN pg_description pgd ON pgd.objoid = pgc.oid AND pgd.objsubid = pga.attnum
                 LEFT JOIN (
                     SELECT kcu.column_name
                     FROM information_schema.table_constraints tc
                     JOIN information_schema.key_column_usage kcu 
                         ON tc.constraint_name = kcu.constraint_name
                     WHERE tc.table_schema = ? 
                       AND tc.table_name = ?
                       AND tc.constraint_type = 'PRIMARY KEY'
                 ) pk ON pk.column_name = a.column_name
                 LEFT JOIN (
                     SELECT kcu.column_name
                     FROM information_schema.table_constraints tc
                     JOIN information_schema.key_column_usage kcu 
                         ON tc.constraint_name = kcu.constraint_name
                     WHERE tc.table_schema = ? 
                       AND tc.table_name = ?
                       AND tc.constraint_type = 'UNIQUE'
                 ) uk ON uk.column_name = a.column_name
                 WHERE a.table_schema = ? AND a.table_name = ?
                 ORDER BY a.ordinal_position",
                [$database, $tableName, $database, $tableName, $database, $tableName]
            );
        } else {
            $columns = Db::connection($connection)->select(
                "SELECT
                    COLUMN_NAME as name,
                    COLUMN_TYPE as type,
                    DATA_TYPE as data_type,
                    IS_NULLABLE as nullable,
                    COLUMN_DEFAULT as default_value,
                    COLUMN_KEY as `key`,
                    EXTRA as extra,
                    COLUMN_COMMENT as comment,
                    CHARACTER_MAXIMUM_LENGTH as max_length
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                 ORDER BY ORDINAL_POSITION",
                [$database, $tableName]
            );
        }

        $result = [];
        foreach ($columns as $column) {
            $columnData = [
                'name' => $column->name,
                'type' => $column->type,
                'data_type' => $column->data_type,
                'nullable' => $column->nullable === 'YES',
                'default_value' => $this->cleanDefaultValue($column->default_value),
                'is_primary' => $column->key === 'PRI',
                'is_unique' => $column->key === 'UNI',
                'is_auto_increment' => str_contains($column->extra ?? '', 'auto_increment'),
                'comment' => $column->comment ?? '',
                'max_length' => $column->max_length,
            ];

            // 只提取 ENUM/SET 类型的选项（这是数据本身，不是推断）
            if (in_array($column->data_type, ['enum', 'set'])) {
                $columnData['options'] = $this->extractEnumOptions($column->type);
            }

            // 从注释中提取选项配置（格式：字段名:key1=值1,key2=值2）
            if ($column->comment && str_contains($column->comment, ':') && str_contains($column->comment, '=')) {
                $commentOptions = $this->extractOptionsFromComment($column->comment);
                if (!empty($commentOptions)) {
                    $columnData['options'] = $commentOptions;
                }
            }

            $result[] = $columnData;
        }

        return $result;
    }

    /**
     * 标准化数据类型（兼容 PostgreSQL 和 MySQL）
     *
     * @param string $dataType 原始数据类型
     * @return string 标准化后的数据类型
     */
    protected function normalizeDataType(string $dataType): string
    {
        $type = strtolower($dataType);
        
        // PostgreSQL 类型映射到 MySQL 类型
        $mapping = [
            'character varying' => 'varchar',
            'character' => 'char',
            'integer' => 'int',
            'double precision' => 'double',
            'numeric' => 'decimal',
            'timestamp without time zone' => 'timestamp',
            'timestamp with time zone' => 'timestamp',
            'time without time zone' => 'time',
            'time with time zone' => 'time',
            'jsonb' => 'json',
        ];
        
        return $mapping[$type] ?? $type;
    }

    /**
     * 智能推断模型类型（用于 Model $casts）
     *
     * @param object $column
     * @return string
     */
    protected function guessModelType(object $column): string
    {
        $type = $this->normalizeDataType($column->data_type);
        $name = strtolower($column->name);

        logger('crud_generator')->debug("guessModelType - 开始推断模型类型", [
            'field_name' => $column->name,
            'data_type' => $type,
            'column_type' => $column->type ?? 'N/A',
        ]);

        // ⚠️ 优先级 1：*_ids + LONGTEXT/TEXT → array（关联多选字段）
        // 例如：tag_ids, role_ids, category_ids
        // PostgreSQL 使用 text，MySQL 使用 longtext
        if (preg_match('/_ids$/', $name) && in_array($type, ['longtext', 'text'])) {
            logger('crud_generator')->info("✅ 字段 {$column->name} 模型类型识别为 array（*_ids + LONGTEXT 关联字段）", [
                'field_name' => $column->name,
                'data_type' => $type,
            ]);
            return 'array';
        }

        // 整数类型（兼容 PostgreSQL 的 integer）
        if (in_array($type, ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
            logger('crud_generator')->debug("✅ 模型类型 → integer（整数类型）");
            return 'integer';
        }

        // 浮点数类型（兼容 PostgreSQL 的 real 和 double precision）
        if (in_array($type, ['decimal', 'numeric', 'float', 'real', 'double'])) {
            logger('crud_generator')->debug("✅ 模型类型 → float（浮点数类型）");
            return 'float';
        }

        // 布尔类型（MySQL: tinyint(1), PostgreSQL: boolean）
        $originalType = strtolower($column->data_type ?? '');
        if ($type === 'tinyint' && str_contains($column->type ?? '', 'tinyint(1)')) {
            logger('crud_generator')->debug("✅ 模型类型 → boolean（布尔类型 - MySQL）");
            return 'boolean';
        }
        if ($originalType === 'boolean') {
            logger('crud_generator')->debug("✅ 模型类型 → boolean（布尔类型 - PostgreSQL）");
            return 'boolean';
        }

        // 日期时间类型（兼容 PostgreSQL 的 timestamp）
        if (in_array($type, ['datetime', 'timestamp'])) {
            logger('crud_generator')->debug("✅ 模型类型 → datetime（日期时间类型）");
            return 'datetime';
        }

        if ($type === 'date') {
            logger('crud_generator')->debug("✅ 模型类型 → date（日期类型）");
            return 'date';
        }

        // JSON 类型（兼容 PostgreSQL 的 jsonb）
        if (in_array($type, ['json', 'jsonb'])) {
            logger('crud_generator')->debug("✅ 模型类型 → array（JSON类型）");
            return 'array';
        }

        // ⚠️ 特殊处理：多图字段 + text/longtext → array
        // images, photos, pictures, gallery 等字段存储 JSON 数组
        // PostgreSQL 使用 text，MySQL 使用 longtext/mediumtext
        if (in_array($type, ['text', 'longtext', 'mediumtext'])) {
            $hasImagesKeyword = str_contains($name, 'images');
            $hasPhotosKeyword = str_contains($name, 'photos');
            $hasPicturesKeyword = str_contains($name, 'pictures');
            $hasGalleryKeyword = str_contains($name, 'gallery');
            
            if ($hasImagesKeyword || $hasPhotosKeyword || $hasPicturesKeyword || $hasGalleryKeyword) {
                logger('crud_generator')->info("✅ 字段 {$column->name} 模型类型识别为 array（多图字段）", [
                    'data_type' => $type,
                    'has_images' => $hasImagesKeyword,
                    'has_photos' => $hasPhotosKeyword,
                    'has_pictures' => $hasPicturesKeyword,
                    'has_gallery' => $hasGalleryKeyword,
                ]);
                return 'array';
            } else {
                logger('crud_generator')->debug("✅ 模型类型 → string（TEXT类型但非多图字段）");
            }
        }

        // 枚举类型（字符串）
        if (in_array($type, ['enum', 'set'])) {
            logger('crud_generator')->debug("✅ 模型类型 → string（枚举类型）");
            return 'string';
        }

        // 默认为字符串
        logger('crud_generator')->debug("✅ 模型类型 → string（默认字符串）");
        return 'string';
    }

    /**
     * 智能推断表单类型
     *
     * 优先级规则：
     * 1. 关联字段识别（最优先）
     *    - *_id（数字类型，排除 id 和 site_id） → relation（关联选择）
     *    - *_ids（LONGTEXT 类型） → relation（关联选择，多选，模型类型为 array）
     *
     * 2. 数据库数据类型 + 字段名特殊组合（可靠，优先级高）
     *    - enum/set → select（下拉选择）
     *    - tinyint(1) → switch（开关）
     *    - text/longtext/mediumtext → textarea（文本域）
     *      ⚠️ 特殊1：content/body 字段 + text/longtext/mediumtext 类型 → rich_text（富文本编辑器）⭐ 必须在 textarea 判断之前
     *        支持字段名：content, body, article_content, post_content, description_content 或包含 content 的字段名
     *      ⚠️ 特殊2：images/photos/pictures/gallery 字段 + text/longtext → images（多图上传）
     *    - date → date（日期选择）
     *    - datetime/timestamp → datetime（日期时间选择）
     *    - *_count + int/bigint → number_range（区间数字）⭐ 必须在整数类型判断之前
     *    - int/bigint → number（数字输入）
     *    - decimal/float/double → number（数字输入）
     *    - json → textarea（文本域）
     *
     * 3. 字段名特征（辅助判断）
     *    - status（字符串类型：varchar/char/text） → radio（单选框）
     *    - status（数字类型：tinyint(2+)/smallint/int） → radio（单选框）
     *      ⚠️ 注意：status tinyint(1) 已在优先级2被识别为 switch
     *      ⚠️ 注意：status enum(...) 已在优先级2被识别为 select
     *    - password/pwd → password
     *    - email → email
     *    - url/link → url
     *    - color → color
     *    - icon/*_icon → icon（图标选择）
     *    - images/photos/pictures/gallery → images（多图上传）
     *    - cover/image/avatar/logo/photo/picture/thumbnail → image（单图上传）
     *      ⚠️ 优先：cover 字段 + 字符串类型 → image（单图上传）
     *    - file/attachment → file（文件上传）
     *    - 字段名以 is_ 开头或名为 enabled → switch（布尔字段）
     *
     * 4. 默认值：text（文本框）
     *
     * @param object $column
     * @return string
     */
    protected function guessFormType(object $column): string
    {
        $name = strtolower($column->name);
        $type = $this->normalizeDataType($column->data_type);
        $comment = strtolower($column->comment ?? '');

        // 【调试日志】记录字段信息
        logger('crud_generator')->debug('guessFormType - 字段分析', [
            'field_name' => $column->name,
            'data_type' => $column->data_type,
            'column_type' => $column->type ?? 'N/A',
            'max_length' => $column->max_length ?? 'N/A',
            'max_length_type' => gettype($column->max_length ?? null),
            'comment' => $column->comment ?? '',
        ]);

        // 站点字段优先识别为专用组件
        if ($name === 'site_id') {
            logger('crud_generator')->debug("字段 {$column->name} 识别为 site_select（站点选择组件）");
            return 'site_select';
        }

        // ========== 优先级 1：关联字段识别（最优先） ==========

        // 外键字段：*_id（数字类型） 或 *_ids（LONGTEXT类型）
        // 排除 id 本身和 site_id（系统保留字段）
        if ($name !== 'id' && $name !== 'site_id') {
            // *_ids + LONGTEXT/TEXT → relation（多选关联）
            // 例如：tag_ids, role_ids, category_ids
            // PostgreSQL 使用 text，MySQL 使用 longtext
            if (preg_match('/_ids$/', $name) && in_array($type, ['longtext', 'text'])) {
                logger('crud_generator')->debug("字段 {$column->name} 识别为 relation（多选关联字段 - LONGTEXT类型）");
                return 'relation';
            }
            
            // *_id + 数字类型 → relation（单选关联）
            // 例如：user_id, category_id, parent_id
            // 兼容 PostgreSQL 的 integer
            if (preg_match('/_id$/', $name) && in_array($type, ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
                logger('crud_generator')->debug("字段 {$column->name} 识别为 relation（单选关联字段 - 数字类型）");
                return 'relation';
            }
        }

        // ========== 优先级 2：根据数据库数据类型判断（可靠，优先级高） ==========

        // ENUM 和 SET 类型 → 下拉选择框
        if (in_array($type, ['enum', 'set'])) {
            logger('crud_generator')->debug("字段 {$column->name} 识别为 select（枚举类型）");
            return 'select';
        }

        // tinyint(1) / boolean → 开关（布尔字段）
        // 【修复】使用弱类型比较（==）并且额外检查 column_type
        // PostgreSQL 使用 boolean 类型
        $originalType = strtolower($column->data_type ?? '');
        if ($originalType === 'boolean') {
            logger('crud_generator')->debug("字段 {$column->name} 识别为 switch（布尔字段 - PostgreSQL）");
            return 'switch';
        }
        if ($type === 'tinyint') {
            // 方法1：检查 max_length（可能是数字或字符串）
            $isTinyint1 = ($column->max_length == 1);
            
            // 方法2：检查原始 column_type（如 'tinyint(1)'）
            $columnType = $column->type ?? '';
            $isTinyint1ByType = str_contains($columnType, 'tinyint(1)');
            
            // 【调试日志】详细记录 tinyint 判断过程
            logger('crud_generator')->debug("tinyint 字段判断", [
                'field_name' => $column->name,
                'max_length' => $column->max_length ?? 'NULL',
                'max_length == 1' => $isTinyint1 ? 'true' : 'false',
                'column_type' => $columnType,
                'contains tinyint(1)' => $isTinyint1ByType ? 'true' : 'false',
                'final_result' => ($isTinyint1 || $isTinyint1ByType) ? 'switch' : 'number',
            ]);
            
            if ($isTinyint1 || $isTinyint1ByType) {
                logger('crud_generator')->debug("字段 {$column->name} 识别为 switch（布尔字段）");
            return 'switch';
            }
        }

        // text/longtext/mediumtext → 文本域、多图上传 或 富文本
        // ⚠️ 特殊处理1：content/body 字段 + text/longtext/mediumtext 类型 → 富文本编辑器
        // 扩展：支持 content、body、article_content 等常见富文本字段名
        // PostgreSQL 使用 text，MySQL 使用 longtext/mediumtext
        if (in_array($type, ['text', 'longtext', 'mediumtext'])) {
            // 富文本字段识别（优先级最高）
            $richTextFields = ['content', 'body', 'article_content', 'post_content', 'description_content'];
            if (in_array($name, $richTextFields) || 
                str_contains($name, 'content') || 
                ($name === 'body' || str_ends_with($name, '_body'))) {
                logger('crud_generator')->debug("字段 {$column->name} 识别为 rich_text（富文本编辑器）");
                return 'rich_text';
            }
            
            // ⚠️ 特殊处理2：images 字段 + text/longtext 类型 → 多图上传
            if (str_contains($name, 'images') || str_contains($name, 'photos') ||
                str_contains($name, 'pictures') || str_contains($name, 'gallery')) {
                logger('crud_generator')->debug("字段 {$column->name} 识别为 images（多图上传）");
                return 'images';
            }
            
            // 其他 text/longtext/mediumtext → 文本域
            logger('crud_generator')->debug("字段 {$column->name} 识别为 textarea（文本域）");
            return 'textarea';
        }

        // date → 日期选择
        if ($type === 'date') {
            logger('crud_generator')->debug("字段 {$column->name} 识别为 date（日期选择）");
            return 'date';
        }

        // datetime/timestamp → 日期时间选择
        if (in_array($type, ['datetime', 'timestamp'])) {
            logger('crud_generator')->debug("字段 {$column->name} 识别为 datetime（日期时间选择）");
            return 'datetime';
        }

        // 【特殊处理】计数字段（_count 结尾 + 整数类型） → 区间数字
        // 必须在整数类型判断之前，否则会被识别为普通 number
        // 例如：view_count, click_count, order_count
        // 兼容 PostgreSQL 的 integer
        if (str_ends_with($name, '_count') && in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'])) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配计数字段规则 → number_range（区间数字）", [
                'field_name' => $column->name,
                'data_type' => $type,
            ]);
            return 'number_range';
        }

        // int/bigint/smallint/mediumint/tinyint → 数字输入
        // ⚠️ 注意：tinyint(1) 应该在上面已经被识别为 switch，如果走到这里说明有问题
        // 兼容 PostgreSQL 的 integer
        if (in_array($type, ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
            // 【警告日志】如果是 tinyint 走到这里，可能是判断逻辑有问题
            if ($type === 'tinyint') {
                logger('crud_generator')->warning("⚠️ tinyint 字段 {$column->name} 被识别为 number！", [
                    'max_length' => $column->max_length ?? 'NULL',
                    'column_type' => $column->type ?? 'N/A',
                    'message' => '这可能不是预期行为，tinyint(1) 应该被识别为 switch',
                ]);
            } else {
                logger('crud_generator')->debug("字段 {$column->name} 识别为 number（数字输入）- 类型: {$type}");
            }
            return 'number';
        }

        // decimal/float/double → 数字输入
        // 兼容 PostgreSQL 的 numeric 和 real
        if (in_array($type, ['decimal', 'numeric', 'float', 'real', 'double'])) {
            logger('crud_generator')->debug("字段 {$column->name} 识别为 number（小数输入）- 类型: {$type}");
            return 'number';
        }

        // json → 文本域
        if ($type === 'json') {
            return 'textarea';
        }

        // ========== 优先级 3：根据字段名特征判断（次要依据） ==========
        
        logger('crud_generator')->debug("【优先级3】开始字段名特征检测", [
            'field_name' => $column->name,
            'lowercase_name' => $name,
            'data_type' => $type,
        ]);

        // 密码字段
        if (in_array($name, ['password', 'pwd'])) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配密码字段规则 → password");
            return 'password';
        }

        // 邮箱字段
        if (str_contains($name, 'email')) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配邮箱字段规则 → email");
            return 'email';
        }

        // URL 字段
        if (str_contains($name, 'url') || str_contains($name, 'link')) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配URL字段规则 → url");
            return 'url';
        }

        // 颜色字段
        if (str_contains($name, 'color')) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配颜色字段规则 → color");
            return 'color';
        }

        // 图标字段（字体图标，如 Bootstrap Icons）
        // 匹配：icon, menu_icon, nav_icon 等
        if ($name === 'icon' || str_ends_with($name, '_icon')) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配图标字段规则 → icon");
            return 'icon';
        }

        // 单图字段 - cover 字段优先匹配（字符串类型）
        // ⚠️ 优先处理：cover 字段 + 字符串类型 → 单图上传
        $isCoverField = ($name === 'cover' || str_contains($name, 'cover'));
        $isStringType = in_array($type, ['varchar', 'char', 'text', 'string']);
        
        if ($isCoverField && $isStringType) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配封面字段规则 → image", [
                'is_cover_field' => $isCoverField,
                'is_string_type' => $isStringType,
            ]);
            return 'image';
        } elseif ($isCoverField && !$isStringType) {
            logger('crud_generator')->debug("⚠️ 字段 {$column->name} 包含'cover'但不是字符串类型，不匹配封面规则", [
                'data_type' => $type,
            ]);
        }

        // 多图字段（复数形式，如 images, photos, pictures）
        // 匹配：images, photos, pictures, gallery_images 等
        // 注意：已在数据类型判断中处理了 text/longtext + images 的情况
        $hasImagesKeyword = str_contains($name, 'images');
        $hasPhotosKeyword = str_contains($name, 'photos');
        $hasPicturesKeyword = str_contains($name, 'pictures');
        $hasGalleryKeyword = str_contains($name, 'gallery');
        
        if ($hasImagesKeyword || $hasPhotosKeyword || $hasPicturesKeyword || $hasGalleryKeyword) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配多图字段规则 → images", [
                'has_images' => $hasImagesKeyword,
                'has_photos' => $hasPhotosKeyword,
                'has_pictures' => $hasPicturesKeyword,
                'has_gallery' => $hasGalleryKeyword,
                'data_type' => $type,
            ]);
            return 'images';
        }

        // 单图字段（单数形式）
        // 匹配：image, avatar, logo, photo, picture, thumbnail 等
        if (str_contains($name, 'image') || str_contains($name, 'avatar') ||
            str_contains($name, 'logo') || str_contains($name, 'photo') ||
            str_contains($name, 'picture') || str_contains($name, 'thumbnail')) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配单图字段规则 → image");
            return 'image';
        }

        // 文件字段
        if (str_contains($name, 'file') || str_contains($name, 'attachment')) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配文件字段规则 → file");
            return 'file';
        }

        // 日期字段（字段名包含 date 但不是 datetime）
        if (str_contains($name, 'date') && !str_contains($name, 'datetime')) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配日期字段规则 → date");
            return 'date';
        }

        // 时间字段
        if (str_contains($name, 'time')) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配时间字段规则 → datetime");
            return 'datetime';
        }

        // 关联 ID 字段（外键） → 关联类型
        // 例如：user_id, category_id, parent_id
        // ⚠️ 注意：这个规则实际上是冗余的，因为在优先级1已经处理了
        if (str_contains($name, '_id') && $name !== 'id') {
            logger('crud_generator')->warning("⚠️ 字段 {$column->name} 在优先级3被识别为 relation（这不应该发生，应该在优先级1处理）");
            return 'relation';
        }

        // 多选关联字段（通常是 JSON 存储多个 ID）
        // 例如：user_ids, tag_ids
        // ⚠️ 注意：这个规则实际上是冗余的，因为在优先级1已经处理了
        if (str_contains($name, '_ids')) {
            logger('crud_generator')->warning("⚠️ 字段 {$column->name} 在优先级3被识别为 relation（这不应该发生，应该在优先级1处理）");
            return 'relation';
        }

        // 状态字段（status）
        // 根据数据库类型智能判断：
        // - tinyint(1) → 已在优先级2处理为 switch
        // - enum/set → 已在优先级2处理为 select
        // - varchar/char/string → 单选框（适合少量固定选项，如：启用/禁用、草稿/发布/下架）
        if ($name === 'status') {
            // 字符串类型的 status → 单选框
            if (in_array($type, ['varchar', 'char', 'string', 'text'])) {
                logger('crud_generator')->debug("✅ 字段 {$column->name} (字符串类型) 匹配状态字段规则 → radio");
                return 'radio';
            }
            // 数字类型的 status（但不是 tinyint(1)） → 单选框
            // 如：tinyint(2) 可能用于表示 0=禁用, 1=启用, 2=待审核
            if (in_array($type, ['tinyint', 'smallint', 'int'])) {
                logger('crud_generator')->debug("✅ 字段 {$column->name} (数字类型) 匹配状态字段规则 → radio");
                return 'radio';
            }
        }

        // 布尔类型字段（注意：这个优先级低于数据类型判断）
        // 只有在数据类型不是 enum/set 的情况下才会走到这里
        if (in_array($name, ['is_active', 'is_admin', 'enabled']) || str_starts_with($name, 'is_')) {
            logger('crud_generator')->debug("✅ 字段 {$column->name} 匹配布尔字段规则 → switch（基于is_前缀）");
            return 'switch';
        }

        // ========== 优先级 4：默认值（所有规则都不匹配） ==========
        logger('crud_generator')->info("⚠️ 字段 {$column->name} 没有匹配任何规则，使用默认类型 → text", [
            'field_name' => $column->name,
            'data_type' => $type,
            'max_length' => $column->max_length ?? 'NULL',
        ]);
        return 'text';
    }

    /**
     * 推断数字输入框的 step 属性
     *
     * step 属性控制数字输入框的增量和精度：
     * - step="1"：只能输入整数（INT, BIGINT, TINYINT 等）
     * - step="0.01"：最多 2 位小数（DECIMAL(10,2)、价格字段等）
     * - step="0.0001"：最多 4 位小数（DECIMAL(10,4)）
     * - step="any"：任意小数（FLOAT, DOUBLE）
     *
     * @param object $column
     * @return string|null
     */
    protected function guessNumberStep(object $column): ?string
    {
        $type = $column->data_type;

        // 非数字类型返回 null
        $numericTypes = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double', 'decimal'];
        if (!in_array($type, $numericTypes)) {
            return null;
        }

        // 整数类型：step="1"
        if (in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'])) {
            return '1';
        }

        // DECIMAL 类型：根据精度设置 step
        if ($type === 'decimal') {
            // 从 COLUMN_TYPE 中提取精度信息
            // 例如：decimal(10,2) → 精度为 2 位小数
            if (preg_match('/decimal\(\d+,(\d+)\)/', $column->type, $matches)) {
                $scale = (int) $matches[1];
                if ($scale === 0) {
                    return '1'; // DECIMAL(10,0) 相当于整数
                }
                // 计算 step 值：0.01（2位）、0.0001（4位）等
                return '0.' . str_repeat('0', $scale - 1) . '1';
            }
            // 默认 2 位小数
            return '0.01';
        }

        // FLOAT 和 DOUBLE：允许任意小数
        if (in_array($type, ['float', 'double'])) {
            return 'any';
        }

        return null;
    }

    /**
     * 判断字段是否应该在列表中显示
     *
     * @param object $column
     * @return bool
     */
    protected function shouldShowInList(object $column): bool
    {
        $name = $column->name;
        $type = $column->data_type;

        // 排除不显示的字段
        $excludeFields = ['password', 'remember_token', 'deleted_at'];
        if (in_array($name, $excludeFields)) {
            return false;
        }

        // 排除长文本字段
        if (in_array($type, ['text', 'longtext', 'mediumtext', 'json'])) {
            return false;
        }

        // 排除某些时间字段
        if (in_array($name, ['updated_at'])) {
            return false;
        }

        return true;
    }

    /**
     * 判断字段是否可搜索
     *
     * @param object $column
     * @return bool
     */
    protected function isSearchable(object $column): bool
    {
        $name = strtolower($column->name);
        $type = strtolower($column->data_type);

        // 排除不可搜索的字段
        $excludeFields = ['password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'];
        if (in_array($name, $excludeFields)) {
            return false;
        }

        // 排除某些类型
        if (in_array($type, ['text', 'longtext', 'mediumtext', 'json', 'blob'])) {
            return false;
        }

        // 排除图片上传字段（单图和多图）默认不可搜索
        // 多图字段：images, photos, pictures, gallery（优先检查，避免被单图字段规则匹配）
        $hasImagesKeyword = str_contains($name, 'images');
        $hasPhotosKeyword = str_contains($name, 'photos');
        $hasPicturesKeyword = str_contains($name, 'pictures');
        $hasGalleryKeyword = str_contains($name, 'gallery');
        
        if ($hasImagesKeyword || $hasPhotosKeyword || $hasPicturesKeyword || $hasGalleryKeyword) {
            return false;
        }

        // 单图字段：cover（字符串类型优先）
        $isCoverField = ($name === 'cover' || str_contains($name, 'cover'));
        $isStringType = in_array($type, ['varchar', 'char', 'text', 'string']);
        
        if ($isCoverField && $isStringType) {
            return false;
        }

        // 单图字段（单数形式）：image, avatar, logo, photo, picture, thumbnail
        // 注意：需要排除已匹配的多图字段（images, photos, pictures）
        if ((str_contains($name, 'image') && !str_contains($name, 'images')) ||
            str_contains($name, 'avatar') ||
            str_contains($name, 'logo') ||
            (str_contains($name, 'photo') && !str_contains($name, 'photos')) ||
            (str_contains($name, 'picture') && !str_contains($name, 'pictures')) ||
            str_contains($name, 'thumbnail')) {
            return false;
        }

        // 文件上传字段也不可搜索
        if (str_contains($name, 'file') || str_contains($name, 'attachment')) {
            return false;
        }

        // 主键、唯一键、索引字段通常可搜索
        if ($column->key === 'PRI' || $column->key === 'UNI' || $column->key === 'MUL') {
            return true;
        }

        // 字符串类型通常可搜索
        if (in_array($type, ['varchar', 'char'])) {
            return true;
        }

        // 状态字段可搜索
        if (in_array($name, ['status', 'type', 'category'])) {
            return true;
        }

        return false;
    }

    /**
     * 判断是否是系统保留字段
     *
     * 系统保留字段的编辑状态不允许用户修改（复选框被禁用）
     *
     * @param object $column
     * @return bool
     */
    protected function isSystemField(object $column): bool
    {
        $name = strtolower($column->name);

        // 系统保留字段列表
        $systemFields = [
            'id',           // 主键
            'created_at',   // 创建时间
            'updated_at',   // 更新时间
            'deleted_at',   // 删除时间（软删除）
            'site_id',     // 多站点标识（旧版）
            'site_id',      // 多站点标识（新版）
        ];

        if (in_array($name, $systemFields)) {
            return true;
        }

        // 自增字段也视为系统字段
        if (str_contains($column->extra, 'auto_increment')) {
            return true;
        }

        return false;
    }

    /**
     * 判断字段是否可编辑
     *
     * 系统字段和特殊字段不可编辑：
     * - 主键 ID（自增）
     * - 创建时间、更新时间、删除时间
     * - site_id / site_id（多站点标识）
     * - 自增字段
     *
     * @param object $column
     * @return bool
     */
    protected function isEditable(object $column): bool
    {
        // 系统保留字段不可编辑
        if ($this->isSystemField($column)) {
            return false;
        }

        // 其他字段默认可编辑
        return true;
    }

    /**
     * 从 ENUM/SET 类型中提取选项
     * 例如：enum('normal','hidden','guide') -> ['normal' => 'normal', 'hidden' => 'hidden', 'guide' => 'guide']
     *
     * @param string $type
     * @return array
     */
    protected function extractEnumOptions(string $type): array
    {
        // 匹配 enum('value1','value2') 或 set('value1','value2')
        if (preg_match("/^(?:enum|set)\((.*)\)$/i", $type, $matches)) {
            $values = str_getcsv($matches[1], ',', "'");
            $options = [];
            foreach ($values as $value) {
                $value = trim($value);
                $options[$value] = $value;
            }
            return $options;
        }
        return [];
    }

    /**
     * 从字段注释中提取选项配置
     * 支持格式：
     * - "状态:normal=正常,hidden=隐藏,guide=引流"
     * - "状态: normal=正常, hidden=隐藏"（支持空格）
     * - "是否显示:1=显示,0=隐藏"（支持数字 key，包括 0）
     *
     * @param string $comment
     * @return array
     */
    protected function extractOptionsFromComment(string $comment): array
    {
        // 尝试匹配 "描述:key1=value1,key2=value2" 格式
        if (preg_match('/^[^:]+:(.+)$/', $comment, $matches)) {
            $optionsStr = trim($matches[1]);
            $options = [];

            // 分割每个选项（支持逗号分隔）
            $pairs = array_map('trim', explode(',', $optionsStr));

            foreach ($pairs as $pair) {
                // 分割 key=value
                if (str_contains($pair, '=')) {
                    [$key, $value] = array_map('trim', explode('=', $pair, 2));
                    // 修复：使用 !== '' 判断，避免 key 为 '0' 时被过滤
                    if ($key !== '' && $value !== '') {
                        $options[$key] = $value;
                    }
                }
            }

            return $options;
        }

        return [];
    }

    /**
     * 获取表的索引信息
     *
     * @param string $tableName
     * @return array
     */
    public function getTableIndexes(string $tableName): array
    {
        $indexes = Db::select("SHOW INDEX FROM `{$tableName}`");

        $result = [];
        foreach ($indexes as $index) {
            $key = $index->Key_name;
            if (!isset($result[$key])) {
                $result[$key] = [
                    'name' => $key,
                    'unique' => !$index->Non_unique,
                    'columns' => [],
                ];
            }
            $result[$key]['columns'][] = $index->Column_name;
        }

        return array_values($result);
    }

    /**
     * 清理数据库默认值（移除单引号等）
     *
     * MySQL 的 COLUMN_DEFAULT 会包含单引号，例如：
     * - 'active' -> active
     * - '0' -> 0
     * - 'hello world' -> hello world
     * - NULL -> null
     * - CURRENT_TIMESTAMP -> CURRENT_TIMESTAMP（保持不变）
     *
     * @param mixed $defaultValue
     * @return mixed
     */
    private function cleanDefaultValue($defaultValue)
    {
        // NULL 值直接返回
        if ($defaultValue === null) {
            return null;
        }

        // 转换为字符串
        $value = (string) $defaultValue;

        // 如果是空字符串，直接返回
        if ($value === '') {
            return '';
        }

        // 移除首尾的单引号（MySQL 字符串默认值格式）
        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
        }

        // 处理转义的单引号（MySQL 中 '' 表示一个单引号）
        $value = str_replace("''", "'", $value);

        return $value;
    }

    /**
     * 智能推断关联配置
     *
     * 根据字段名自动推断关联表、显示字段、值字段
     * 例如：user_id → users 表，name 字段，id 值
     *
     * @param object $column
     * @return array
     */
    protected function guessRelationConfig(object $column): array
    {
        $fieldName = $column->name;

        // 默认配置
        $config = [
            'table' => '',
            'label_column' => 'name',  // 显示字段（默认 name）
            'value_column' => 'id',    // 值字段（默认 id）
            'multiple' => false,       // 是否多选
        ];

        // 判断是否是多选（字段名以 _ids 结尾）
        if (str_ends_with($fieldName, '_ids')) {
            $config['multiple'] = true;
            // 移除 _ids 后缀
            $baseFieldName = substr($fieldName, 0, -4);
        } else if (str_ends_with($fieldName, '_id')) {
            // 移除 _id 后缀
            $baseFieldName = substr($fieldName, 0, -3);
        } else {
            $baseFieldName = $fieldName;
        }

        // 推断表名（单数转复数）
        $config['table'] = $this->pluralize($baseFieldName);

        // 特殊字段的显示列推断
        $specialLabelColumns = [
            'title',       // 标题
            'username',    // 用户名
            'nickname',    // 昵称
            'real_name',   // 真实姓名
            'full_name',   // 全名
            'email',       // 邮箱
            'mobile',      // 手机号
            'phone',       // 电话
            'code',        // 代码/编号
            'number',      // 编号
            'slug',        // 别名
        ];

        // 根据字段名推断显示列
        foreach ($specialLabelColumns as $labelColumn) {
            if (str_contains($fieldName, $labelColumn)) {
                $config['label_column'] = $labelColumn;
                break;
            }
        }

        return $config;
    }

    /**
     * 简单的英文单数转复数
     *
     * @param string $singular
     * @return string
     */
    protected function pluralize(string $singular): string
    {
        // 特殊复数形式
        $irregular = [
            'person' => 'people',
            'man' => 'men',
            'woman' => 'women',
            'child' => 'children',
            'tooth' => 'teeth',
            'foot' => 'feet',
            'mouse' => 'mice',
            'goose' => 'geese',
        ];

        if (isset($irregular[$singular])) {
            return $irregular[$singular];
        }

        // 规则转换
        if (str_ends_with($singular, 'y') && !in_array(substr($singular, -2, 1), ['a', 'e', 'i', 'o', 'u'])) {
            // category → categories
            return substr($singular, 0, -1) . 'ies';
        }

        if (str_ends_with($singular, 's') ||
            str_ends_with($singular, 'x') ||
            str_ends_with($singular, 'z') ||
            str_ends_with($singular, 'ch') ||
            str_ends_with($singular, 'sh')) {
            // class → classes, box → boxes
            return $singular . 'es';
        }

        if (str_ends_with($singular, 'f')) {
            // leaf → leaves
            return substr($singular, 0, -1) . 'ves';
        }

        if (str_ends_with($singular, 'fe')) {
            // knife → knives
            return substr($singular, 0, -2) . 'ves';
        }

        // 默认加 s
        return $singular . 's';
    }
}

