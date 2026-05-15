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

namespace Addons\PgsqlTester\Service;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;
use Throwable;

use function Hyperf\Support\now;

/**
 * PostgreSQL 测试服务类.
 */
class PgsqlTesterService
{
    #[Inject]
    protected Redis $redis;

    #[Inject]
    protected ConfigInterface $config;

    /**
     * 插件配置.
     */
    protected array $pluginConfig;

    public function __construct()
    {
        // 获取插件配置
        $this->pluginConfig = [];
    }

    /**
     * 测试 PostgreSQL 数据库连接.
     */
    public function testConnection(): array
    {
        try {
            $startTime = microtime(true);

            // 使用指定连接进行测试
            $pgsql = Db::connection('pgsql');

            // 执行基本查询来测试连接
            $result = $pgsql->select('SELECT version() as version, current_database() as database, current_user as user, inet_server_addr() as host, inet_server_port() as port');

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2); // 毫秒

            $info = $result[0] ?? null;

            if (! $info) {
                throw new BusinessException(ErrorCode::DB_QUERY_ERROR, '无法获取数据库信息');
            }

            return [
                'status' => 'success',
                'database' => $info->database ?? 'unknown',
                'user' => $info->user ?? 'unknown',
                'host' => $info->host ?? 'unknown',
                'port' => $info->port ?? 'unknown',
                'version' => $info->version ?? 'unknown',
                'connection_time' => date('Y-m-d H:i:s'),
                'response_time' => $duration . 'ms',
                'driver' => 'pgsql',
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
                'connection' => 'pgsql',
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 执行 DDL 语句 (CREATE, ALTER, DROP 等).
     */
    public function executeDdl(string $sql): array
    {
        try {
            $startTime = microtime(true);

            $pgsql = Db::connection('pgsql');

            // 执行 DDL 语句
            $result = $pgsql->statement($sql);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            return [
                'status' => 'success',
                'result' => $result,
                'execution_time' => $duration . 'ms',
                'sql' => $sql,
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
                'sql' => $sql,
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 检查 zhparser 扩展是否已安装.
     */
    public function checkZhparserExtension(): array
    {
        try {
            $pgsql = Db::connection('pgsql');

            // 检查 zhparser 扩展是否已安装
            $extensionExists = $pgsql->select("
                SELECT 1
                FROM pg_extension
                WHERE extname = 'zhparser'
            ");

            return [
                'status' => 'success',
                'extension_installed' => !empty($extensionExists),
                'extension_name' => 'zhparser',
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'extension_installed' => false,
                'extension_name' => 'zhparser',
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 安装 zhparser 扩展.
     */
    public function installZhparserExtension(): array
    {
        try {
            // 安装 zhparser 扩展
            $installResult = $this->executeDdl('CREATE EXTENSION IF NOT EXISTS zhparser');

            if ($installResult['status'] === 'error') {
                return [
                    'status' => 'error',
                    'message' => '安装 zhparser 扩展失败',
                    'details' => $installResult,
                    'timestamp' => time(),
                ];
            }

            return [
                'status' => 'success',
                'message' => 'zhparser 扩展安装成功',
                'details' => $installResult,
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 全面检查 zhparser 中文搜索设置是否正确配置.
     */
    public function checkZhparserSetupComplete(): array
    {
        $results = [
            'extension' => $this->checkZhparserExtension(),
            'configuration' => $this->checkZhparserConfiguration(),
            'data_vectors' => $this->checkVectorDataIntegrity(),
            'timestamp' => time(),
        ];

        // 确定整体状态
        $allSuccessful = true;
        foreach ($results as $key => $result) {
            if ($key !== 'timestamp' && ($result['status'] === 'error' ||
                (isset($result['extension_installed']) && !$result['extension_installed']) ||
                (isset($result['fully_configured']) && !$result['fully_configured']) ||
                (isset($result['vectors_populated']) && !$result['vectors_populated']))) {
                $allSuccessful = false;
                break;
            }
        }

        $results['overall_status'] = $allSuccessful ? 'complete' : 'incomplete';
        $results['setup_complete'] = $allSuccessful;

        return $results;
    }

    /**
     * 检查向量数据完整性.
     */
    public function checkVectorDataIntegrity(): array
    {
        try {
            $pgsql = Db::connection('pgsql');

            // 检查表是否存在
            $tableExists = $pgsql->select("
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'public'
                AND table_name = 'pgsql_features_demo'
            ");

            if (empty($tableExists)) {
                return [
                    'status' => 'error',
                    'error' => 'pgsql_features_demo 表不存在',
                    'vectors_populated' => false,
                    'total_records' => 0,
                    'vector_records' => 0,
                    'timestamp' => time(),
                ];
            }

            // 检查总记录数和向量记录数
            $stats = $pgsql->select("
                SELECT
                    COUNT(*) as total_records,
                    COUNT(content_zh_vector) as vector_records,
                    COUNT(CASE WHEN content_zh_vector IS NOT NULL AND content IS NOT NULL THEN 1 END) as valid_vectors
                FROM pgsql_features_demo
            ");

            $stat = $stats[0] ?? null;
            if (!$stat) {
                return [
                    'status' => 'error',
                    'error' => '无法获取统计信息',
                    'vectors_populated' => false,
                    'timestamp' => time(),
                ];
            }

            $totalRecords = (int) $stat->total_records;
            $vectorRecords = (int) $stat->vector_records;
            $validVectors = (int) $stat->valid_vectors;

            // 检查向量数据是否完整（至少80%的记录有向量）
            $vectorRatio = $totalRecords > 0 ? ($vectorRecords / $totalRecords) : 0;
            $vectorsPopulated = $vectorRatio >= 0.8; // 80% 阈值

            return [
                'status' => 'success',
                'vectors_populated' => $vectorsPopulated,
                'total_records' => $totalRecords,
                'vector_records' => $vectorRecords,
                'valid_vectors' => $validVectors,
                'vector_ratio' => round($vectorRatio * 100, 2) . '%',
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'vectors_populated' => false,
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 检查主键约束是否存在.
     */
    public function checkPrimaryKeyConstraint(): array
    {
        try {
            $pgsql = Db::connection('pgsql');

            // 检查 pgsql_features_demo 表的主键约束
            $primaryKeyExists = $pgsql->select("
                SELECT 1
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                  ON tc.constraint_name = kcu.constraint_name
                 AND tc.table_schema = kcu.table_schema
                WHERE tc.table_name = 'pgsql_features_demo'
                  AND tc.table_schema = 'public'
                  AND tc.constraint_type = 'PRIMARY KEY'
                  AND kcu.column_name = 'id'
            ");

            return [
                'status' => 'success',
                'has_primary_key' => !empty($primaryKeyExists),
                'table_name' => 'pgsql_features_demo',
                'constraint_column' => 'id',
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'has_primary_key' => false,
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 创建主键约束.
     */
    public function createPrimaryKeyConstraint(): array
    {
        try {
            // 创建主键约束
            $alterSql = "
                ALTER TABLE pgsql_features_demo
                ADD CONSTRAINT pgsql_features_demo_pkey PRIMARY KEY (id)
            ";

            $result = $this->executeDdl($alterSql);

            return [
                'status' => 'success',
                'message' => '主键约束创建成功',
                'table_name' => 'pgsql_features_demo',
                'constraint_name' => 'pgsql_features_demo_pkey',
                'constraint_column' => 'id',
                'details' => $result,
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 执行向量数据更新.
     */
    public function updateVectorData(): array
    {
        try {
            $startTime = microtime(true);

            // 使用 zhparser 配置更新向量数据
            $updateSql = "
                UPDATE pgsql_features_demo
                SET content_zh_vector = to_tsvector('zhparser', COALESCE(content, ''))
                WHERE content IS NOT NULL
            ";

            $result = $this->executeDdl($updateSql);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            if ($result['status'] === 'success') {
                // 重新检查向量完整性
                $integrityCheck = $this->checkVectorDataIntegrity();

                return [
                    'status' => 'success',
                    'message' => '向量数据更新完成',
                    'execution_time' => $duration . 'ms',
                    'integrity_check' => $integrityCheck,
                    'timestamp' => time(),
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => '向量数据更新失败',
                    'error_details' => $result,
                    'timestamp' => time(),
                ];
            }
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 检查 zhparser 文本搜索配置是否存在.
     */
    public function checkZhparserConfiguration(): array
    {
        try {
            $pgsql = Db::connection('pgsql');

            // 检查 zhparser 配置是否存在
            $zhparserConfig = $pgsql->select("
                SELECT 1
                FROM pg_ts_config
                WHERE cfgname = 'zhparser'
            ");

            // 检查 zh_cfg 配置是否存在（兼容性检查）
            $zhCfgConfig = $pgsql->select("
                SELECT 1
                FROM pg_ts_config
                WHERE cfgname = 'zh_cfg'
            ");

            $configExists = !empty($zhparserConfig) || !empty($zhCfgConfig);
            $configName = !empty($zhparserConfig) ? 'zhparser' : (!empty($zhCfgConfig) ? 'zh_cfg' : null);

            if (!$configExists) {
                return [
                    'status' => 'success',
                    'config_exists' => false,
                    'mapping_exists' => false,
                    'fully_configured' => false,
                    'config_name' => null,
                    'timestamp' => time(),
                ];
            }

            // 检查映射是否存在
            $mappingExists = $pgsql->select("
                SELECT 1
                FROM pg_ts_config_map
                WHERE mapcfg = (SELECT oid FROM pg_ts_config WHERE cfgname = ?)
                LIMIT 1
            ", [$configName]);

            return [
                'status' => 'success',
                'config_exists' => true,
                'mapping_exists' => !empty($mappingExists),
                'fully_configured' => !empty($mappingExists),
                'config_name' => $configName,
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'config_exists' => false,
                'mapping_exists' => false,
                'fully_configured' => false,
                'config_name' => null,
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 创建 zhparser 文本搜索配置.
     */
    public function createZhparserConfiguration(): array
    {
        $results = [];

        try {
            // 1. 创建 zhparser 配置
            $createZhparserSql = "CREATE TEXT SEARCH CONFIGURATION zhparser (PARSER = zhparser)";
            $zhparserResult = $this->executeDdl($createZhparserSql);
            $results['create_zhparser_config'] = $zhparserResult;

            // 2. 创建 zh_cfg 配置（兼容性）
            $createZhCfgSql = "CREATE TEXT SEARCH CONFIGURATION zh_cfg (PARSER = zhparser)";
            $zhCfgResult = $this->executeDdl($createZhCfgSql);
            $results['create_zh_cfg_config'] = $zhCfgResult;

            // 检查至少一个配置创建成功
            if ($zhparserResult['status'] === 'error' && $zhCfgResult['status'] === 'error') {
                return [
                    'status' => 'error',
                    'message' => '创建文本搜索配置失败',
                    'details' => $results,
                    'timestamp' => time(),
                ];
            }

            // 3. 为成功创建的配置添加映射策略
            $mappingResults = [];

            if ($zhparserResult['status'] === 'success') {
                $addZhparserMappingSql = "
                    ALTER TEXT SEARCH CONFIGURATION zhparser
                    ADD MAPPING FOR n,v,a,i,e,l WITH simple
                ";
                $zhparserMappingResult = $this->executeDdl($addZhparserMappingSql);
                $mappingResults['zhparser_mapping'] = $zhparserMappingResult;
            }

            if ($zhCfgResult['status'] === 'success') {
                $addZhCfgMappingSql = "
                    ALTER TEXT SEARCH CONFIGURATION zh_cfg
                    ADD MAPPING FOR n,v,a,i,e,l WITH simple
                ";
                $zhCfgMappingResult = $this->executeDdl($addZhCfgMappingSql);
                $mappingResults['zh_cfg_mapping'] = $zhCfgMappingResult;
            }

            $results['mappings'] = $mappingResults;

            // 检查映射是否都成功
            $allMappingsSuccess = true;
            foreach ($mappingResults as $mappingResult) {
                if ($mappingResult['status'] === 'error') {
                    $allMappingsSuccess = false;
                    break;
                }
            }

            if (!$allMappingsSuccess) {
                return [
                    'status' => 'warning',
                    'message' => '部分配置创建成功，但映射添加失败',
                    'details' => $results,
                    'timestamp' => time(),
                ];
            }

            return [
                'status' => 'success',
                'message' => 'zhparser 和 zh_cfg 配置创建成功',
                'details' => $results,
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'details' => $results,
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 执行查询测试.
     */
    public function runQueryTest(string $query, array $params = []): array
    {
        try {
            $startTime = microtime(true);

            $pgsql = Db::connection('pgsql');

            // 执行查询
            if (stripos($query, 'select') === 0) {
                $result = $pgsql->select($query, $params);
            } elseif (stripos($query, 'insert') === 0) {
                $result = $pgsql->insert($query, $params);
            } elseif (stripos($query, 'update') === 0) {
                $result = $pgsql->update($query, $params);
            } elseif (stripos($query, 'delete') === 0) {
                $result = $pgsql->delete($query, $params);
            } else {
                $result = $pgsql->statement($query, $params);
            }

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            return [
                'status' => 'success',
                'query' => $query,
                'params' => $params,
                'result' => $result,
                'execution_time' => $duration . 'ms',
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'query' => $query,
                'params' => $params,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 执行原生查询（专门用于内部API调用）.
     */
    public function runRawQuery(string $query, array $params = []): array
    {
        try {
            $startTime = microtime(true);

            $pgsql = Db::connection('pgsql');

            // 直接执行 SELECT 查询
            $result = $pgsql->select($query, $params);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            return [
                'status' => 'success',
                'data' => $result,
                'execution_time' => $duration . 'ms',
                'query' => $query,
                'params' => $params,
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
                'query' => $query,
                'params' => $params,
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 执行性能测试.
     */
    public function runPerformanceTest(int $iterations = 100, string $query = 'SELECT 1'): array
    {
        $results = [];
        $totalTime = 0;
        $minTime = PHP_FLOAT_MAX;
        $maxTime = 0;

        try {
            $pgsql = Db::connection('pgsql');

            for ($i = 0; $i < $iterations; ++$i) {
                $startTime = microtime(true);

                $pgsql->select($query);

                $endTime = microtime(true);
                $duration = ($endTime - $startTime) * 1000; // 毫秒

                $results[] = $duration;
                $totalTime += $duration;
                $minTime = min($minTime, $duration);
                $maxTime = max($maxTime, $duration);
            }

            $avgTime = $totalTime / $iterations;

            return [
                'status' => 'success',
                'iterations' => $iterations,
                'query' => $query,
                'total_time' => round($totalTime, 2) . 'ms',
                'average_time' => round($avgTime, 2) . 'ms',
                'min_time' => round($minTime, 2) . 'ms',
                'max_time' => round($maxTime, 2) . 'ms',
                'qps' => round($iterations / ($totalTime / 1000), 2), // 查询每秒
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'iterations' => $iterations,
                'query' => $query,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 获取数据库信息.
     */
    public function getDatabaseInfo(): array
    {
        try {
            $pgsql = Db::connection('pgsql');

            // 数据库基本信息
            $basicInfo = $pgsql->select('
                SELECT
                    current_database() as database,
                    current_user as user,
                    version() as version,
                    current_setting(\'server_version\') as server_version,
                    current_setting(\'server_encoding\') as server_encoding,
                    current_setting(\'client_encoding\') as client_encoding
            ');

            // 数据库大小
            $sizeInfo = $pgsql->select("
                SELECT
                    pg_size_pretty(pg_database_size(current_database())) as database_size,
                    pg_size_pretty(sum(pg_total_relation_size(quote_ident(schemaname)||'.'||quote_ident(tablename)))) as tables_size
                FROM pg_tables
                WHERE schemaname = 'public'
            ");

            // 连接信息
            $connectionInfo = $pgsql->select('
                SELECT
                    inet_server_addr() as server_addr,
                    inet_server_port() as server_port,
                    inet_client_addr() as client_addr,
                    inet_client_port() as client_port
            ');

            return [
                'status' => 'success',
                'basic_info' => $basicInfo[0] ?? null,
                'size_info' => $sizeInfo[0] ?? null,
                'connection_info' => $connectionInfo[0] ?? null,
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 获取表信息.
     */
    public function getTables(): array
    {
        try {
            $pgsql = Db::connection('pgsql');

            $tables = $pgsql->select("
                SELECT
                    schemaname,
                    tablename,
                    tableowner,
                    tablespace,
                    hasindexes,
                    hasrules,
                    hastriggers,
                    rowsecurity
                FROM pg_tables
                WHERE schemaname = 'public'
                ORDER BY tablename
            ");

            // 为每个表获取更多详细信息
            $detailedTables = [];
            foreach ($tables as $table) {
                $tableName = $table->tablename;

                // 获取表大小和行数
                $stats = $pgsql->select("
                    SELECT
                        schemaname,
                        tablename,
                        n_tup_ins as inserts,
                        n_tup_upd as updates,
                        n_tup_del as deletes,
                        n_live_tup as live_rows,
                        n_dead_tup as dead_rows,
                        pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as total_size,
                        pg_size_pretty(pg_relation_size(schemaname||'.'||tablename)) as table_size,
                        pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename) - pg_relation_size(schemaname||'.'||tablename)) as index_size
                    FROM pg_stat_user_tables
                    WHERE schemaname = 'public' AND tablename = ?
                ", [$tableName]);

                $detailedTables[] = array_merge((array) $table, (array) ($stats[0] ?? []));
            }

            return [
                'status' => 'success',
                'tables' => $detailedTables,
                'total_count' => count($detailedTables),
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 获取扩展信息.
     */
    public function getExtensions(): array
    {
        try {
            $pgsql = Db::connection('pgsql');

            $extensions = $pgsql->select('
                SELECT
                    name,
                    default_version,
                    installed_version,
                    comment
                FROM pg_available_extensions
                WHERE installed_version IS NOT NULL
                ORDER BY name
            ');

            return [
                'status' => 'success',
                'extensions' => $extensions,
                'total_count' => count($extensions),
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 获取统计信息.
     */
    public function getStats(): array
    {
        try {
            $pgsql = Db::connection('pgsql');

            // 获取今天的统计信息
            $todayStats = $pgsql->table('pgsql_tester_statistics')
                ->where('stat_date', date('Y-m-d'))
                ->first();

            // 获取最近7天的统计信息
            $weekStats = $pgsql->table('pgsql_tester_statistics')
                ->where('stat_date', '>=', date('Y-m-d', strtotime('-7 days')))
                ->orderBy('stat_date', 'desc')
                ->get();

            // 获取最近的测试日志
            $recentLogs = Db::table('pgsql_tester_test_logs')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $stats = [
                'connection_tests' => (int) ($todayStats->connection_tests ?? 0),
                'query_tests' => (int) ($todayStats->query_tests ?? 0),
                'performance_tests' => (int) ($todayStats->performance_tests ?? 0),
                'total_tests' => (int) ($todayStats->total_tests ?? 0),
                'success_tests' => (int) ($todayStats->success_tests ?? 0),
                'failed_tests' => (int) ($todayStats->failed_tests ?? 0),
                'avg_response_time' => (float) ($todayStats->avg_response_time ?? 0),
                'max_response_time' => (float) ($todayStats->max_response_time ?? 0),
                'min_response_time' => (float) ($todayStats->min_response_time ?? 0),
                'last_test_time' => $todayStats ? $todayStats->updated_at : null,
                'week_stats' => $weekStats,
                'recent_logs' => $recentLogs,
            ];

            return [
                'status' => 'success',
                'stats' => $stats,
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            logger()->warning('[PgsqlTester] 获取统计信息失败: ' . $e->getMessage());

            // 降级到Redis缓存
            return $this->fallbackGetStats();
        }
    }

    /**
     * 记录测试日志.
     */
    public function logTestResult(string $testType, array $testData): bool
    {
        return true;
        // 检查是否启用了详细日志记录
        if (! ($this->pluginConfig['enable_detailed_logging'] ?? true)) {
            return true; // 如果未启用，直接返回成功
        }

        try {
            $pgsql = Db::connection();

            $logData = [
                'test_type' => $testType,
                'database_host' => $testData['host'] ?? null,
                'database_name' => $testData['database'] ?? null,
                'query_sql' => $testData['query'] ?? null,
                'execution_time' => $testData['execution_time'] ?? null,
                'status' => $testData['status'] ?? 'success',
                'error_message' => $testData['error_message'] ?? null,
                'result_data' => isset($testData['result']) ? json_encode($testData['result']) : null,
                'user_id' => $testData['user_id'] ?? null,
                'user_ip' => $testData['user_ip'] ?? '127.0.0.1',
            ];

            $pgsql->table('pgsql_tester_test_logs')->insert($logData);

            return true;
        } catch (Throwable $e) {
            logger()->warning('[PgsqlTester] 记录测试日志失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新统计信息.
     */
    public function updateStats(string $type, float $responseTime = 0, bool $success = true): void
    {
        return;
        // 检查是否启用了性能监控
        if (! ($this->pluginConfig['enable_performance_monitoring'] ?? true)) {
            return; // 如果未启用，直接返回
        }

        try {
            $pgsql = Db::connection();
            $today = date('Y-m-d');

            // 查找今天的统计记录
            $stat = $pgsql->table('pgsql_tester_statistics')
                ->where('stat_date', $today)
                ->first();

            if ($stat) {
                // 更新现有记录
                $updateData = [
                    'total_tests' => $stat->total_tests + 1,
                    'updated_at' => now(),
                ];

                // 更新具体类型的计数
                $typeColumn = $type . '_tests';
                if (isset($stat->{$typeColumn})) {
                    $updateData[$typeColumn] = $stat->{$typeColumn} + 1;
                }

                // 更新成功/失败计数
                if ($success) {
                    $updateData['success_tests'] = $stat->success_tests + 1;
                } else {
                    $updateData['failed_tests'] = $stat->failed_tests + 1;
                }

                // 更新响应时间统计
                if ($responseTime > 0) {
                    $currentCount = $stat->success_tests + ($success ? 1 : 0);
                    if ($currentCount > 0) {
                        $currentAvg = $stat->avg_response_time;
                        $newAvg = (($currentAvg * ($currentCount - 1)) + $responseTime) / $currentCount;
                        $updateData['avg_response_time'] = $newAvg;
                    }

                    $updateData['max_response_time'] = max($stat->max_response_time, $responseTime);
                    if ($stat->min_response_time == 0 || $responseTime < $stat->min_response_time) {
                        $updateData['min_response_time'] = $responseTime;
                    }
                }

                $pgsql->table('pgsql_tester_statistics')
                    ->where('id', $stat->id)
                    ->update($updateData);
            } else {
                // 创建新记录
                $insertData = [
                    'stat_date' => $today,
                    'total_tests' => 1,
                    'success_tests' => $success ? 1 : 0,
                    'failed_tests' => $success ? 0 : 1,
                    'avg_response_time' => $responseTime,
                    'max_response_time' => $responseTime,
                    'min_response_time' => $responseTime,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // 设置对应类型的计数
                $insertData[$type . '_tests'] = 1;

                $pgsql->table('pgsql_tester_statistics')->insert($insertData);
            }
        } catch (Throwable $e) {
            logger()->warning('[PgsqlTester] 更新统计信息失败: ' . $e->getMessage());
            // 降级到Redis缓存
            $this->fallbackToRedisStats($type, $responseTime, $success);
        }
    }

    /**
     * 获取配置值
     *
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->pluginConfig[$key] ?? $default;
    }

    /**
     * 获取默认性能测试迭代次数.
     */
    public function getDefaultPerformanceIterations(): int
    {
        return (int) $this->getConfig('default_performance_iterations', 100);
    }

    /**
     * 获取默认性能测试查询.
     */
    public function getDefaultPerformanceQuery(): string
    {
        return (string) $this->getConfig('default_performance_query', 'SELECT 1');
    }

    /**
     * 获取连接超时时间.
     */
    public function getConnectionTimeout(): int
    {
        return (int) $this->getConfig('connection_timeout', 10);
    }

    /**
     * 获取查询超时时间.
     */
    public function getQueryTimeout(): int
    {
        return (int) $this->getConfig('query_timeout', 30);
    }

    /**
     * 检查是否启用速率限制.
     */
    public function isRateLimitingEnabled(): bool
    {
        return (bool) $this->getConfig('enable_rate_limiting', true);
    }

    /**
     * 获取每分钟最大测试次数.
     */
    public function getMaxTestsPerMinute(): int
    {
        return (int) $this->getConfig('max_tests_per_minute', 60);
    }

    /**
     * 检查用户是否有权限执行测试.
     */
    public function canUserExecuteTest(?int $userId, ?array $userRoles = null): bool
    {
        // 如果未启用IP白名单或其他限制，则允许所有用户
        $allowedRoles = $this->getConfig('allowed_roles', []);
        if (empty($allowedRoles)) {
            return true;
        }

        // 如果有角色限制，检查用户角色
        if ($userRoles && ! empty(array_intersect($userRoles, $allowedRoles))) {
            return true;
        }

        return false;
    }

    /**
     * 检查IP是否在白名单中.
     */
    public function isIpAllowed(string $ip): bool
    {
        // 如果未启用IP白名单，则允许所有IP
        if (! ($this->pluginConfig['enable_ip_whitelist'] ?? false)) {
            return true;
        }

        $whitelist = $this->getConfig('ip_whitelist', []);
        return in_array($ip, $whitelist);
    }

    /**
     * 检查是否超过速率限制.
     *
     * @return bool true表示允许执行，false表示超过限制
     */
    public function checkRateLimit(?int $userId, string $ip): bool
    {
        if (! $this->isRateLimitingEnabled()) {
            return true;
        }

        $maxTests = $this->getMaxTestsPerMinute();
        $identifier = $userId ? "user:{$userId}" : "ip:{$ip}";
        $key = "pgsql_tester:rate_limit:{$identifier}";

        try {
            $currentCount = (int) $this->redis->get($key) ?: 0;

            if ($currentCount >= $maxTests) {
                return false; // 超过限制
            }

            // 增加计数，如果是第一次则设置过期时间（1分钟）
            $newCount = $this->redis->incr($key);
            if ($newCount === 1) {
                $this->redis->expire($key, 60); // 1分钟过期
            }

            return true;
        } catch (Throwable $e) {
            // Redis不可用时允许执行
            return true;
        }
    }

    /**
     * 执行中文全文搜索测试.
     *
     * @param string $keyword 搜索关键词
     * @param int $limit 结果限制数量
     */
    public function runChineseSearchTest(string $keyword = '', int $limit = 10): array
    {
        print_r(['执行中文全文搜索测试']);
        try {
            $startTime = microtime(true);

            $pgsql = Db::connection('pgsql');

            // 如果没有提供关键词，使用默认的测试关键词
            if (empty($keyword)) {
                $keyword = 'PostgreSQL';
            }

            // 使用 PostgreSQL 内置的全文搜索函数
            $sql = "
                SELECT
                    id,
                    title,
                    content,
                    ts_rank(content_zh_vector, plainto_tsquery('zhparser', ?)) as rank_score,
                    similarity(title, ?) as title_similarity,
                    similarity(content, ?) as content_similarity
                FROM pgsql_features_demo
                WHERE
                    content_zh_vector @@ plainto_tsquery('zhparser', ?)
                    OR similarity(title, ?) > 0.1
                    OR similarity(content, ?) > 0.1
                ORDER BY rank_score DESC, title_similarity DESC, content_similarity DESC
                LIMIT ?
            ";

            $params = [$keyword, $keyword, $keyword, $keyword, $keyword, $keyword, $limit];

            // 打印原始查询语句和参数，用于调试
            print_r(['原始SQL' => $sql]);
            print_r(['参数' => $params]);

            // 生成完整的查询语句（替换占位符）
            $fullSql = $this->buildFullSql($sql, $params);
            print_r(['完整SQL' => $fullSql]);

            $results = $pgsql->select($sql, $params);
            print_r(['$results' => $results]);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            // 格式化结果
            $formattedResults = array_map(function ($row) {
                return [
                    'id' => $row->id,
                    'title' => $row->title,
                    'content' => $row->content ? mb_substr($row->content, 0, 200) . '...' : '',
                    'rank_score' => round((float) $row->rank_score, 4),
                    'title_similarity' => round((float) $row->title_similarity, 4),
                    'content_similarity' => round((float) $row->content_similarity, 4),
                ];
            }, $results);

            return [
                'status' => 'success',
                'keyword' => $keyword,
                'total_results' => count($formattedResults),
                'limit' => $limit,
                'results' => $formattedResults,
                'execution_time' => $duration . 'ms',
                'sql_query' => $sql,
                'search_type' => 'chinese_fulltext_search',
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'keyword' => $keyword,
                'limit' => $limit,
                'timestamp' => time(),
            ];
        }
    }

    /**
     * 执行地理空间/坐标数据测试.
     *
     * @param float $lat 纬度
     * @param float $lng 经度
     * @param float $radiusKm 搜索半径（公里）
     * @param int $limit 结果限制数量
     */
    public function runGeospatialTest(float $lat = 39.9042, float $lng = 116.4074, float $radiusKm = 10.0, int $limit = 20): array
    {
        try {
            $startTime = microtime(true);

            $pgsql = Db::connection('pgsql');

            // 转换为 PostGIS 几何类型 (SRID: 4326 - WGS84)
            $pointSql = "ST_GeomFromText('POINT({$lng} {$lat})', 4326)";

            // 查询附近的记录
            $sql = "
                SELECT
                    id,
                    title,
                    contact_name,
                    contact_email,
                    address_type,
                    location_lat,
                    location_lng,
                    ST_Distance(
                        ST_GeomFromText('POINT(? ?)', 4326)::geography,
                        ST_GeomFromText(CONCAT('POINT(', location_lng, ' ', location_lat, ')'), 4326)::geography
                    ) / 1000 as distance_km,
                    CASE
                        WHEN ST_DWithin(
                            ST_GeomFromText('POINT(? ?)', 4326)::geography,
                            ST_GeomFromText(CONCAT('POINT(', location_lng, ' ', location_lat, ')'), 4326)::geography,
                            ? * 1000
                        ) THEN true
                        ELSE false
                    END as within_radius
                FROM pgsql_features_demo
                WHERE
                    location_lat IS NOT NULL
                    AND location_lng IS NOT NULL
                    AND location_lat BETWEEN ? AND ?
                    AND location_lng BETWEEN ? AND ?
                ORDER BY distance_km ASC
                LIMIT ?
            ";

            // 计算边界框（粗略的地理边界）
            $latOffset = $radiusKm / 111.0; // 纬度偏移（约111km每度）
            $lngOffset = $radiusKm / (111.0 * cos(deg2rad($lat))); // 经度偏移

            $params = [
                $lng, $lat, // ST_GeomFromText POINT参数
                $lng, $lat, $radiusKm, // ST_DWithin参数
                $lat - $latOffset, $lat + $latOffset, // lat BETWEEN
                $lng - $lngOffset, $lng + $lngOffset, // lng BETWEEN
                $limit,
            ];

            $results = $pgsql->select($sql, $params);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            // 格式化结果
            $formattedResults = array_map(function ($row) {
                return [
                    'id' => $row->id,
                    'title' => $row->title,
                    'contact_name' => $row->contact_name,
                    'contact_email' => $row->contact_email,
                    'address_type' => $row->address_type,
                    'location_lat' => (float) $row->location_lat,
                    'location_lng' => (float) $row->location_lng,
                    'distance_km' => round((float) $row->distance_km, 3),
                    'within_radius' => (bool) $row->within_radius,
                ];
            }, $results);

            // 统计信息
            $withinRadiusCount = count(array_filter($formattedResults, function ($item) {
                return $item['within_radius'];
            }));

            return [
                'status' => 'success',
                'search_center' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'description' => $this->getLocationDescription($lat, $lng),
                ],
                'search_radius_km' => $radiusKm,
                'total_results' => count($formattedResults),
                'within_radius_count' => $withinRadiusCount,
                'limit' => $limit,
                'results' => $formattedResults,
                'execution_time' => $duration . 'ms',
                'sql_query' => $sql,
                'search_type' => 'geospatial_search',
                'timestamp' => time(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'search_center' => ['latitude' => $lat, 'longitude' => $lng],
                'search_radius_km' => $radiusKm,
                'limit' => $limit,
                'timestamp' => time(),
            ];
        }
    }

    /**
     * Redis缓存降级方案.
     */
    private function fallbackGetStats(): array
    {
        $stats = [
            'connection_tests' => 0,
            'query_tests' => 0,
            'performance_tests' => 0,
            'last_test_time' => null,
            'avg_response_time' => 0,
        ];

        try {
            // 从 Redis 获取统计信息
            $stats['connection_tests'] = (int) $this->redis->get('pgsql_tester:stats:connection_tests') ?: 0;
            $stats['query_tests'] = (int) $this->redis->get('pgsql_tester:stats:query_tests') ?: 0;
            $stats['performance_tests'] = (int) $this->redis->get('pgsql_tester:stats:performance_tests') ?: 0;
            $stats['last_test_time'] = $this->redis->get('pgsql_tester:stats:last_test_time');
            $stats['avg_response_time'] = (float) $this->redis->get('pgsql_tester:stats:avg_response_time') ?: 0;
        } catch (Throwable $e) {
            // Redis 也不可用时返回默认统计信息
        }

        return [
            'status' => 'success',
            'stats' => $stats,
            'timestamp' => time(),
        ];
    }

    /**
     * Redis缓存降级方案.
     */
    private function fallbackToRedisStats(string $type, float $responseTime = 0, bool $success = true): void
    {
        try {
            // 从 Redis 获取统计信息
            $stats = [
                'connection_tests' => (int) $this->redis->get('pgsql_tester:stats:connection_tests') ?: 0,
                'query_tests' => (int) $this->redis->get('pgsql_tester:stats:query_tests') ?: 0,
                'performance_tests' => (int) $this->redis->get('pgsql_tester:stats:performance_tests') ?: 0,
                'last_test_time' => $this->redis->get('pgsql_tester:stats:last_test_time'),
                'avg_response_time' => (float) $this->redis->get('pgsql_tester:stats:avg_response_time') ?: 0,
            ];

            // 更新对应类型的计数
            $stats[$type . '_tests'] = $stats[$type . '_tests'] + 1;

            // 更新平均响应时间
            if ($responseTime > 0) {
                $totalTests = $stats['connection_tests'] + $stats['query_tests'] + $stats['performance_tests'] + 1;
                $stats['avg_response_time'] = (($stats['avg_response_time'] * ($totalTests - 1)) + $responseTime) / $totalTests;
            }

            $stats['last_test_time'] = date('Y-m-d H:i:s');

            // 保存到 Redis
            foreach ($stats as $key => $value) {
                $this->redis->set('pgsql_tester:stats:' . $key, $value);
            }
        } catch (Throwable $e) {
            // Redis 也不可用时完全忽略
        }
    }

    /**
     * 获取位置描述.
     */
    private function getLocationDescription(float $lat, float $lng): string
    {
        // 简单的坐标到地名的映射（示例）
        $locations = [
            '39.9042-116.4074' => '北京天安门',
            '31.2304-121.4737' => '上海外滩',
            '22.3193-114.1694' => '香港维多利亚港',
            '23.1291-113.2644' => '广州天河',
            '30.5728-104.0668' => '成都春熙路',
        ];

        $closest = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($locations as $coordKey => $name) {
            [$locLat, $locLng] = explode('-', $coordKey);
            $locLat = (float) $locLat;
            $locLng = (float) $locLng;

            $distance = sqrt(pow($lat - $locLat, 2) + pow($lng - $locLng, 2));
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closest = $name;
            }
        }

        return $closest ?: '未知位置';
    }

    /**
     * 构建完整的SQL语句（用于调试，将占位符替换为实际参数值）
     *
     * @param string $sql 原始SQL语句
     * @param array $params 参数数组
     * @return string 完整的SQL语句
     */
    private function buildFullSql(string $sql, array $params): string
    {
        $fullSql = $sql;
        foreach ($params as $param) {
            // 对字符串参数加引号，对数字参数直接替换
            if (is_string($param)) {
                $escapedParam = "'" . addslashes($param) . "'";
            } elseif (is_bool($param)) {
                $escapedParam = $param ? 'true' : 'false';
            } elseif (is_null($param)) {
                $escapedParam = 'NULL';
            } else {
                $escapedParam = (string) $param;
            }

            // 替换第一个问号
            $fullSql = preg_replace('/\?/', $escapedParam, $fullSql, 1);
        }

        return $fullSql;
    }
}
