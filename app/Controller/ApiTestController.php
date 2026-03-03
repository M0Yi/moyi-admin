<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use PDO;
use PDOException;
use Throwable;

/**
 * API测试控制器
 * 用于测试各种API功能和数据库连接
 */
class ApiTestController extends AbstractController
{
    /**
     * 测试PostgreSQL数据库连接
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function testPgConnection()
    {
        try {
            // 使用指定连接进行测试，避免修改默认连接
            $pgsql = Db::connection('pgsql');

            // 测试基本查询来验证连接
            $result = $pgsql->select('SELECT version() as version, current_database() as database, inet_server_addr() as host, inet_server_port() as port');
            $info = $result[0] ?? null;

            if (!$info) {
                throw new BusinessException(ErrorCode::DB_QUERY_ERROR, '无法获取数据库信息');
            }

            // 测试连接池状态
            $poolStats = [
                'total_connections' => 0, // Hyperf 不直接提供此信息
                'active_connections' => 0,
                'idle_connections' => 0,
            ];

            // 返回成功响应
            return $this->success([
                'status' => 'connected',
                'database' => $info->database ?? 'unknown',
                'host' => $info->host ?? 'unknown',
                'port' => $info->port ?? 'unknown',
                'version' => $info->version ?? 'unknown',
                'connection_time' => date('Y-m-d H:i:s'),
                'pool_stats' => $poolStats,
                'driver' => 'pgsql',
            ], 'PostgreSQL数据库连接成功');

        } catch (BusinessException $e) {
            // 业务异常
            return $this->error($e->getMessage(), [
                'error_code' => $e->getCode(),
            ], $e->getCode());

        } catch (Throwable $e) {
            // 其他异常（连接失败等）
            return $this->error('PostgreSQL数据库连接失败: ' . $e->getMessage(), [
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
                'connection' => 'pgsql',
            ], ErrorCode::DB_CONNECTION_ERROR);
        }
    }

    /**
     * 测试PostgreSQL数据库基本查询
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function testPgQuery()
    {
        try {
            // 使用指定连接进行测试
            $pgsql = Db::connection('pgsql');

            // 测试基本查询
            $result = $pgsql->select('SELECT version() as version');

            if (empty($result)) {
                throw new BusinessException(ErrorCode::DB_QUERY_ERROR, '查询无结果');
            }

            $version = $result[0]->version ?? 'Unknown';

            // 测试数据库状态查询
            $dbStats = $pgsql->select('
                SELECT
                    current_database() as database,
                    current_schema() as schema,
                    current_user as user,
                    version() as version
            ');

            // 测试表查询（查询pg_tables系统表）
            $tables = [];
            try {
                $tableResult = $pgsql->select("
                    SELECT tablename, tableowner, schemaname
                    FROM pg_tables
                    WHERE schemaname = 'public'
                    ORDER BY tablename
                    LIMIT 10
                ");
                $tables = array_map(function($row) {
                    return [
                        'name' => $row->tablename,
                        'owner' => $row->tableowner,
                        'schema' => $row->schemaname,
                    ];
                }, $tableResult);
            } catch (Throwable $e) {
                // 忽略表查询错误，继续执行
            }

            // 测试 PostgreSQL 特有的功能
            $extensions = [];
            try {
                $extResult = $pgsql->select("
                    SELECT name, default_version, installed_version
                    FROM pg_available_extensions
                    WHERE installed_version IS NOT NULL
                    ORDER BY name
                    LIMIT 5
                ");
                $extensions = array_map(function($row) {
                    return [
                        'name' => $row->name,
                        'default_version' => $row->default_version,
                        'installed_version' => $row->installed_version,
                    ];
                }, $extResult);
            } catch (Throwable $e) {
                // 忽略扩展查询错误
            }

            // 返回成功响应
            return $this->success([
                'version' => $version,
                'database_info' => $dbStats[0] ?? null,
                'tables_count' => count($tables),
                'tables' => $tables,
                'extensions' => $extensions,
                'query_time' => date('Y-m-d H:i:s'),
            ], 'PostgreSQL数据库查询测试成功');

        } catch (BusinessException $e) {
            return $this->error($e->getMessage(), [
                'error_code' => $e->getCode(),
            ], $e->getCode());

        } catch (Throwable $e) {
            return $this->error('数据库查询测试异常: ' . $e->getMessage(), [
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
            ], ErrorCode::DB_QUERY_ERROR);
        }
    }

    /**
     * 测试多个数据库连接
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function testAllConnections()
    {
        $results = [];

        // 测试MySQL连接
        try {
            $mysql = Db::connection('default');
            $mysqlResult = $mysql->select('SELECT VERSION() as version');
            $results['mysql'] = [
                'status' => 'success',
                'version' => $mysqlResult[0]->version ?? 'Unknown',
                'connection' => 'default',
                'driver' => 'mysql',
            ];
        } catch (Throwable $e) {
            $results['mysql'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
                'connection' => 'default',
                'driver' => 'mysql',
            ];
        }

        // 测试PostgreSQL连接
        try {
            $pgsql = Db::connection('pgsql');
            $pgsqlResult = $pgsql->select('SELECT version() as version');
            $results['postgresql'] = [
                'status' => 'success',
                'version' => $pgsqlResult[0]->version ?? 'Unknown',
                'connection' => 'pgsql',
                'driver' => 'pgsql',
            ];
        } catch (Throwable $e) {
            $results['postgresql'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
                'connection' => 'pgsql',
                'driver' => 'pgsql',
            ];
        }

        return $this->success($results, '数据库连接测试完成');
    }

    /**
     * 获取数据库状态信息
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getDbStatus()
    {
        $status = [];

        // MySQL状态
        try {
            $mysql = Db::connection('default');
            $mysqlInfo = $mysql->select('SELECT DATABASE() as database, @@version as version');
            $status['mysql'] = [
                'connection' => 'default',
                'database' => $mysqlInfo[0]->database ?? 'unknown',
                'version' => $mysqlInfo[0]->version ?? 'unknown',
                'status' => 'connected',
                'driver' => 'mysql',
            ];
        } catch (Throwable $e) {
            $status['mysql'] = [
                'connection' => 'default',
                'status' => 'error',
                'error' => $e->getMessage(),
                'driver' => 'mysql',
            ];
        }

        // PostgreSQL状态
        try {
            $pgsql = Db::connection('pgsql');
            $pgsqlInfo = $pgsql->select('SELECT current_database() as database, current_user as user, version() as version');
            $status['postgresql'] = [
                'connection' => 'pgsql',
                'database' => $pgsqlInfo[0]->database ?? 'unknown',
                'user' => $pgsqlInfo[0]->user ?? 'unknown',
                'version' => $pgsqlInfo[0]->version ?? 'unknown',
                'status' => 'connected',
                'driver' => 'pgsql',
            ];
        } catch (Throwable $e) {
            $status['postgresql'] = [
                'connection' => 'pgsql',
                'status' => 'error',
                'error' => $e->getMessage(),
                'driver' => 'pgsql',
            ];
        }

        return $this->success($status, '数据库状态信息');
    }
}
