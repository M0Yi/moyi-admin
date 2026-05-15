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

namespace Addons\PgsqlTester\Controller\Admin;

use Addons\PgsqlTester\Service\PgsqlTesterService;
use App\Controller\AbstractController;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Throwable;

/**
 * PostgreSQL 测试管理控制器.
 */
class PgsqlTesterController extends AbstractController
{
    #[Inject]
    protected PgsqlTesterService $testerService;

    /**
     * 首页.
     */
    public function index()
    {
        return $this->render->render('admin.pgsql_tester.index', [
            'title' => 'PostgreSQL 测试',
        ]);
    }

    /**
     * 仪表盘.
     */
    public function dashboard()
    {
        // 获取统计信息
        $stats = $this->testerService->getStats();

        return $this->render->render('admin.pgsql_tester.dashboard', [
            'title' => 'PostgreSQL 测试仪表盘',
            'stats' => $stats['stats'],
        ]);
    }

    /**
     * 连接测试页面.
     */
    public function connectionTest()
    {
        return $this->render->render('admin.pgsql_tester.connection_test', [
            'title' => '连接测试',
        ]);
    }

    /**
     * 执行连接测试.
     */
    public function runConnectionTest(RequestInterface $request)
    {
        $result = $this->testerService->testConnection();

        // 更新统计信息
        $this->testerService->updateStats('connection', isset($result['response_time']) ? (float) str_replace('ms', '', $result['response_time']) : 0);

        return $this->success($result, '连接测试完成');
    }

    /**
     * 查询测试页面.
     */
    public function queryTest()
    {
        // 预设一些常用的测试查询
        $sampleQueries = [
            'SELECT version()' => '获取 PostgreSQL 版本信息',
            'SELECT current_database(), current_user' => '获取当前数据库和用户',
            'SELECT * FROM pg_stat_activity LIMIT 5' => '查看活动连接（前5条）',
            'SELECT schemaname, tablename FROM pg_tables WHERE schemaname = \'public\' LIMIT 10' => '查看 public 模式下的表（前10个）',
            'SELECT name, installed_version FROM pg_available_extensions WHERE installed_version IS NOT NULL' => '查看已安装的扩展',
            'SELECT pg_size_pretty(pg_database_size(current_database()))' => '查看数据库大小',
        ];

        return $this->render->render('admin.pgsql_tester.query_test', [
            'title' => '查询测试',
            'sampleQueries' => $sampleQueries,
        ]);
    }

    /**
     * 执行查询测试.
     */
    public function runQueryTest(RequestInterface $request)
    {
        $query = $request->input('query', '');
        $params = $request->input('params', '');

        if (empty($query)) {
            return $this->error('查询语句不能为空');
        }

        // 解析参数
        $parsedParams = [];
        if (! empty($params)) {
            try {
                $parsedParams = json_decode($params, true);
                if (! is_array($parsedParams)) {
                    $parsedParams = [];
                }
            } catch (Throwable $e) {
                return $this->error('参数格式错误，必须是有效的 JSON 数组');
            }
        }

        $result = $this->testerService->runQueryTest($query, $parsedParams);

        // 更新统计信息
        $this->testerService->updateStats('query', isset($result['execution_time']) ? (float) str_replace('ms', '', $result['execution_time']) : 0);

        return $this->success($result, '查询测试完成');
    }

    /**
     * 性能测试页面.
     */
    public function performanceTest()
    {
        return $this->render->render('admin.pgsql_tester.performance_test', [
            'title' => '性能测试',
        ]);
    }

    /**
     * 执行性能测试.
     */
    public function runPerformanceTest(RequestInterface $request)
    {
        $iterations = (int) $request->input('iterations', 100);
        $query = $request->input('query', 'SELECT 1');

        if ($iterations <= 0 || $iterations > 10000) {
            return $this->error('迭代次数必须在 1-10000 之间');
        }

        $result = $this->testerService->runPerformanceTest($iterations, $query);

        // 更新统计信息
        $this->testerService->updateStats('performance');

        return $this->success($result, '性能测试完成');
    }

    /**
     * 表信息页面.
     */
    public function tableInfo()
    {
        $tables = $this->testerService->getTables();

        return $this->render->render('admin.pgsql_tester.table_info', [
            'title' => '表信息',
            'tables' => $tables['tables'] ?? [],
            'total_count' => $tables['total_count'] ?? 0,
        ]);
    }

    /**
     * 日志页面.
     */
    public function logs()
    {
        return $this->render->render('admin.pgsql_tester.logs', [
            'title' => '测试日志',
        ]);
    }
}

