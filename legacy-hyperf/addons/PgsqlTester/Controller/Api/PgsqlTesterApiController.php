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

namespace Addons\PgsqlTester\Controller\Api;

use Addons\PgsqlTester\Model\BlogPost;
use Addons\PgsqlTester\Model\PgsqlFeaturesDemo;
use Addons\PgsqlTester\Service\PgsqlTesterService;
use App\Constants\ErrorCode;
use App\Controller\AbstractController;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * PostgreSQL 测试 API 控制器.
 */
class PgsqlTesterApiController extends AbstractController
{
    #[Inject]
    protected PgsqlTesterService $testerService;

    /**
     * 测试连接.
     */
    public function testConnection()
    {
        $result = $this->testerService->testConnection();

        // 更新统计信息
        $this->testerService->updateStats('connection', isset($result['response_time']) ? (float) str_replace('ms', '', $result['response_time']) : 0);

        return $this->success($result, '连接测试完成');
    }

    /**
     * 执行连接测试.
     */
    public function runConnectionTest(RequestInterface $request)
    {
        // 获取用户信息
        $userId = $this->getCurrentUserId();
        $clientIp = $this->getClientIp($request);

        // 检查权限
        if (! $this->testerService->isIpAllowed($clientIp)) {
            return $this->error('IP地址不在白名单中', [], ErrorCode::FORBIDDEN);
        }

        // 检查速率限制
        if (! $this->testerService->checkRateLimit($userId, $clientIp)) {
            return $this->error('请求过于频繁，请稍后再试', [], ErrorCode::TOO_MANY_REQUESTS);
        }

        $result = $this->testerService->testConnection();

        // 记录测试日志
        $this->testerService->logTestResult('connection', [
            'host' => $result['host'] ?? null,
            'database' => $result['database'] ?? null,
            'status' => $result['status'],
            'execution_time' => isset($result['response_time']) ? (float) str_replace('ms', '', $result['response_time']) : null,
            'error_message' => $result['status'] === 'error' ? ($result['error'] ?? null) : null,
            'result' => $result,
            'user_id' => $this->getCurrentUserId(),
            'user_ip' => $this->getClientIp($request),
        ]);

        // 更新统计信息
        $success = $result['status'] === 'success';
        $responseTime = isset($result['response_time']) ? (float) str_replace('ms', '', $result['response_time']) : 0;
        $this->testerService->updateStats('connection', $responseTime, $success);

        return $this->success($result, '连接测试完成');
    }

    /**
     * 查询测试.
     */
    public function testQuery()
    {
        return $this->success([
            'message' => '查询测试接口已就绪，请使用 POST 方法执行查询',
            'sample_queries' => [
                'SELECT version()' => '获取版本信息',
                'SELECT current_database()' => '获取当前数据库',
                'SELECT * FROM pg_stat_activity LIMIT 5' => '查看活动连接',
            ],
        ], '查询测试接口');
    }

    /**
     * 执行查询测试.
     */
    public function runQueryTest(RequestInterface $request)
    {
        $query = $request->input('query', '');
        $params = $request->input('params', []);

        if (empty($query)) {
            return $this->error('查询语句不能为空');
        }

        if (! is_array($params)) {
            $params = [];
        }

        // 获取用户信息并检查权限
        $userId = $this->getCurrentUserId();
        $clientIp = $this->getClientIp($request);

        if (! $this->testerService->isIpAllowed($clientIp)) {
            return $this->error('IP地址不在白名单中', [], ErrorCode::FORBIDDEN);
        }

        if (! $this->testerService->checkRateLimit($userId, $clientIp)) {
            return $this->error('请求过于频繁，请稍后再试', [], ErrorCode::TOO_MANY_REQUESTS);
        }

        $result = $this->testerService->runQueryTest($query, $params);

        // 记录测试日志
        $this->testerService->logTestResult('query', [
            'query' => $query,
            'status' => $result['status'],
            'execution_time' => isset($result['execution_time']) ? (float) str_replace('ms', '', $result['execution_time']) : null,
            'error_message' => $result['status'] === 'error' ? ($result['error'] ?? null) : null,
            'result' => $result,
            'user_id' => $this->getCurrentUserId(),
            'user_ip' => $this->getClientIp($request),
        ]);

        // 更新统计信息
        $success = $result['status'] === 'success';
        $responseTime = isset($result['execution_time']) ? (float) str_replace('ms', '', $result['execution_time']) : 0;
        $this->testerService->updateStats('query', $responseTime, $success);

        return $this->success($result, '查询测试完成');
    }

    /**
     * 执行性能测试.
     */
    public function runPerformanceTest(RequestInterface $request)
    {
        $iterations = (int) $request->input('iterations', $this->testerService->getDefaultPerformanceIterations());
        $query = $request->input('query', $this->testerService->getDefaultPerformanceQuery());

        if ($iterations <= 0 || $iterations > 10000) {
            return $this->error('迭代次数必须在 1-10000 之间');
        }

        // 获取用户信息并检查权限
        $userId = $this->getCurrentUserId();
        $clientIp = $this->getClientIp($request);

        if (! $this->testerService->isIpAllowed($clientIp)) {
            return $this->error('IP地址不在白名单中', [], ErrorCode::FORBIDDEN);
        }

        if (! $this->testerService->checkRateLimit($userId, $clientIp)) {
            return $this->error('请求过于频繁，请稍后再试', [], ErrorCode::TOO_MANY_REQUESTS);
        }

        $result = $this->testerService->runPerformanceTest($iterations, $query);

        // 记录测试日志
        $this->testerService->logTestResult('performance', [
            'query' => $query,
            'status' => $result['status'],
            'execution_time' => isset($result['total_time']) ? (float) str_replace('ms', '', $result['total_time']) : null,
            'error_message' => $result['status'] === 'error' ? ($result['error'] ?? null) : null,
            'result' => $result,
            'user_id' => $this->getCurrentUserId(),
            'user_ip' => $this->getClientIp($request),
        ]);

        // 更新统计信息
        $success = $result['status'] === 'success';
        $this->testerService->updateStats('performance', 0, $success);

        return $this->success($result, '性能测试完成');
    }

    /**
     * 获取数据库信息.
     */
    public function getDatabaseInfo(): ResponseInterface
    {
        $blogPost = new BlogPost();
        $blogPost->create([
            'title' => '测试标题',
            'slug' => 'test-slug',
            'content' => '测试内容',
        ]);
//        $blogPost->author_id = 1;
//        $blogPost->title = '测试标题';
//        $blogPost->slug = 'test-slug';

        return $this->error('ojbk');
//        return $this->success($blogPost->save() ? $blogPost->toArray() : null, '数据库信息获取成功');
        //        $result = $this->testerService->testConnection();
        //
        //        // 更新统计信息
        //        $this->testerService->updateStats('connection', isset($result['response_time']) ? (float) str_replace('ms', '', $result['response_time']) : 0);
        //
        //        return $this->success($result, '连接测试完成');
        //        $result = $this->testerService->getDatabaseInfo();
        //        return $this->success($result, '数据库信息获取成功');
    }

    /**
     * 获取表信息.
     */
    public function getTables()
    {
        $result = $this->testerService->getTables();
        return $this->success($result, '表信息获取成功');
    }

    /**
     * 获取扩展信息.
     */
    public function getExtensions()
    {
        $result = $this->testerService->getExtensions();
        return $this->success($result, '扩展信息获取成功');
    }

    /**
     * 获取统计信息.
     */
    public function getStats()
    {
        $result = $this->testerService->getStats();
        return $this->success($result, '统计信息获取成功');
    }

    /**
     * 获取 PostgreSQL 特性演示数据列表.
     */
    public function getPgsqlFeaturesDemoList(RequestInterface $request)
    {
        // 获取查询参数
        $page = (int) $request->input('page', 1);
        $pageSize = (int) $request->input('page_size', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status', '');
        $priority = $request->input('priority', '');
        $isActive = $request->input('is_active', '');
        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        // 验证参数
        if ($page < 1) {
            $page = 1;
        }
        if ($pageSize < 1 || $pageSize > 100) {
            $pageSize = 15;
        }

        // 允许的排序字段
        $allowedSortFields = [
            'id', 'title', 'status', 'priority', 'created_at', 'updated_at',
            'score', 'view_count', 'price', 'location_lat', 'location_lng',
        ];
        if (! in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }
        if (! in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        // 获取用户信息并检查权限
        $userId = $this->getCurrentUserId();
        $clientIp = $this->getClientIp($request);

        if (! $this->testerService->isIpAllowed($clientIp)) {
            return $this->error('IP地址不在白名单中', [], ErrorCode::FORBIDDEN);
        }

        if (! $this->testerService->checkRateLimit($userId, $clientIp)) {
            return $this->error('请求过于频繁，请稍后再试', [], ErrorCode::TOO_MANY_REQUESTS);
        }

        try {
            // 构建查询
            $query = PgsqlFeaturesDemo::query();

            // 关键词搜索
            if (! empty($keyword)) {
                $query->search($keyword);
            }

            // 状态筛选
            if (! empty($status)) {
                $query->byStatus($status);
            }

            // 优先级筛选
            if (! empty($priority)) {
                $query->byPriority($priority);
            }

            // 激活状态筛选
            if ($isActive !== '') {
                $isActive = filter_var($isActive, FILTER_VALIDATE_BOOLEAN);
                $query->where('is_active', $isActive);
            }

            // 未过期筛选
            $query->notExpired();

            // 排序
            $query->orderBy($sortField, $sortOrder);

            // 分页查询
            $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

            // 格式化数据
            $data = $paginator->items();
            $formattedData = array_map(function ($item) {
                return [
                    'id' => $item->id,
                    'uuid_field' => $item->uuid_field,
                    'status' => $item->status,
                    'status_label' => $item->status_label,
                    'priority' => $item->priority,
                    'priority_label' => $item->priority_label,
                    'tags' => $item->tags ?? [],
                    'tags_string' => $item->tags_string,
                    'title' => $item->title,
                    'content' => $item->content ? mb_substr($item->content, 0, 100) . '...' : '',
                    'contact_name' => $item->contact_name,
                    'contact_email' => $item->contact_email,
                    'contact_phone' => $item->contact_phone,
                    'address_type' => $item->address_type,
                    'location_lat' => $item->location_lat,
                    'location_lng' => $item->location_lng,
                    'price' => $item->price,
                    'formatted_price' => $item->formatted_price,
                    'score' => $item->score,
                    'view_count' => $item->view_count,
                    'data_size' => $item->data_size,
                    'is_active' => $item->is_active,
                    'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
                    'expires_at' => $item->expires_at?->format('Y-m-d H:i:s'),
                ];
            }, $data);

            $result = [
                'data' => $formattedData,
                'pagination' => [
                    'total' => $paginator->total(),
                    'page' => $paginator->currentPage(),
                    'page_size' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'filters' => [
                    'keyword' => $keyword,
                    'status' => $status,
                    'priority' => $priority,
                    'is_active' => $isActive,
                    'sort_field' => $sortField,
                    'sort_order' => $sortOrder,
                ],
            ];

            // 记录访问日志
            $this->testerService->logTestResult('pgsql_features_demo_list', [
                'status' => 'success',
                'filters' => [
                    'page' => $page,
                    'page_size' => $pageSize,
                    'keyword' => $keyword,
                    'status' => $status,
                    'priority' => $priority,
                    'is_active' => $isActive,
                    'sort_field' => $sortField,
                    'sort_order' => $sortOrder,
                ],
                'result_count' => count($formattedData),
                'total_count' => $paginator->total(),
                'user_id' => $userId,
                'user_ip' => $clientIp,
            ]);

            return $this->success($result, 'PostgreSQL 特性演示数据列表获取成功');
        } catch (Throwable $e) {
            // 记录错误日志
            $this->testerService->logTestResult('pgsql_features_demo_list', [
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'user_id' => $userId,
                'user_ip' => $clientIp,
            ]);

            return $this->error('获取数据失败：' . $e->getMessage());
        }
    }

    /**
     * 中文全文搜索测试.
     */
    public function chineseSearchTest(RequestInterface $request)
    {
        $query = $request->input('query', '');
        $limit = (int) $request->input('limit', 10);

        if (empty($query)) {
            return $this->error('搜索关键词不能为空');
        }

        if ($limit < 1 || $limit > 100) {
            $limit = 10;
        }

        // 获取用户信息并检查权限
        $userId = $this->getCurrentUserId();
        $clientIp = $this->getClientIp($request);

        if (! $this->testerService->isIpAllowed($clientIp)) {
            return $this->error('IP地址不在白名单中', [], ErrorCode::FORBIDDEN);
        }

        if (! $this->testerService->checkRateLimit($userId, $clientIp)) {
            return $this->error('请求过于频繁，请稍后再试', [], ErrorCode::TOO_MANY_REQUESTS);
        }

        try {
            // 使用 PostgreSQL 内置的中文搜索函数
            $sql = "
                SELECT
                    id, title, content,
                    ts_rank(content_zh_vector, plainto_tsquery('zhparser', $1)) as rank
                FROM pgsql_features_demo
                WHERE content_zh_vector @@ plainto_tsquery('zhparser', $1)
                ORDER BY rank DESC
                LIMIT $2
            ";

            $results = $this->testerService->runRawQuery($sql, [$query, $limit]);

            // 格式化结果
            $formattedResults = array_map(function ($row) {
                return [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'content' => mb_substr($row['content'], 0, 200) . '...',
                    'rank' => round((float) $row['rank'], 4),
                ];
            }, $results['data'] ?? []);

            $result = [
                'query' => $query,
                'total' => count($formattedResults),
                'results' => $formattedResults,
                'execution_time' => $results['execution_time'] ?? null,
            ];

            // 记录测试日志
            $this->testerService->logTestResult('chinese_search', [
                'query' => $query,
                'limit' => $limit,
                'result_count' => count($formattedResults),
                'status' => 'success',
                'execution_time' => $results['execution_time'] ?? null,
                'user_id' => $userId,
                'user_ip' => $clientIp,
            ]);

            return $this->success($result, '中文搜索测试完成');
        } catch (Throwable $e) {
            // 记录错误日志
            $this->testerService->logTestResult('chinese_search', [
                'query' => $query,
                'limit' => $limit,
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'user_id' => $userId,
                'user_ip' => $clientIp,
            ]);

            return $this->error('中文搜索测试失败：' . $e->getMessage());
        }
    }

    /**
     * 地理位置搜索测试.
     */
    public function locationSearchTest(RequestInterface $request)
    {
        $lat = $request->input('lat', '');
        $lng = $request->input('lng', '');
        $radius = (int) $request->input('radius', 1000); // 默认1公里

        if (empty($lat) || empty($lng)) {
            return $this->error('经纬度坐标不能为空');
        }

        if ($radius < 1 || $radius > 50000) {
            $radius = 1000;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        // 验证坐标范围
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return $this->error('无效的经纬度坐标');
        }

        // 获取用户信息并检查权限
        $userId = $this->getCurrentUserId();
        $clientIp = $this->getClientIp($request);

        if (! $this->testerService->isIpAllowed($clientIp)) {
            return $this->error('IP地址不在白名单中', [], ErrorCode::FORBIDDEN);
        }

        if (! $this->testerService->checkRateLimit($userId, $clientIp)) {
            return $this->error('请求过于频繁，请稍后再试', [], ErrorCode::TOO_MANY_REQUESTS);
        }

        try {
            // 使用 PostgreSQL 地理位置搜索
            $sql = '
                SELECT
                    id, title, location_lat, location_lng,
                    ST_Distance(
                        ST_Point(location_lng, location_lat)::geography,
                        ST_Point($2, $1)::geography
                    ) as distance_meters
                FROM pgsql_features_demo
                WHERE location_lat IS NOT NULL AND location_lng IS NOT NULL
                AND ST_DWithin(
                    ST_Point(location_lng, location_lat)::geography,
                    ST_Point($2, $1)::geography,
                    $3
                )
                ORDER BY distance_meters
            ';

            $results = $this->testerService->runRawQuery($sql, [$lat, $lng, $radius]);

            // 格式化结果
            $formattedResults = array_map(function ($row) {
                return [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'location_lat' => (float) $row['location_lat'],
                    'location_lng' => (float) $row['location_lng'],
                    'distance_meters' => round((float) $row['distance_meters'], 2),
                    'distance_km' => round((float) $row['distance_meters'] / 1000, 2),
                ];
            }, $results['data'] ?? []);

            $result = [
                'center_point' => [
                    'lat' => $lat,
                    'lng' => $lng,
                ],
                'search_radius_meters' => $radius,
                'total' => count($formattedResults),
                'results' => $formattedResults,
                'execution_time' => $results['execution_time'] ?? null,
            ];

            // 记录测试日志
            $this->testerService->logTestResult('location_search', [
                'lat' => $lat,
                'lng' => $lng,
                'radius' => $radius,
                'result_count' => count($formattedResults),
                'status' => 'success',
                'execution_time' => $results['execution_time'] ?? null,
                'user_id' => $userId,
                'user_ip' => $clientIp,
            ]);

            return $this->success($result, '地理位置搜索测试完成');
        } catch (Throwable $e) {
            // 记录错误日志
            $this->testerService->logTestResult('location_search', [
                'lat' => $lat,
                'lng' => $lng,
                'radius' => $radius,
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'user_id' => $userId,
                'user_ip' => $clientIp,
            ]);

            return $this->error('地理位置搜索测试失败：' . $e->getMessage());
        }
    }

    /**
     * JSONB 查询测试.
     */
    public function jsonbQueryTest(RequestInterface $request)
    {
        $searchable = $request->input('searchable', '');
        $category = $request->input('category', '');

        // 获取用户信息并检查权限
        $userId = $this->getCurrentUserId();
        $clientIp = $this->getClientIp($request);

        if (! $this->testerService->isIpAllowed($clientIp)) {
            return $this->error('IP地址不在白名单中', [], ErrorCode::FORBIDDEN);
        }

        if (! $this->testerService->checkRateLimit($userId, $clientIp)) {
            return $this->error('请求过于频繁，请稍后再试', [], ErrorCode::TOO_MANY_REQUESTS);
        }

        try {
            $query = PgsqlFeaturesDemo::query();
            $conditions = [];

            // JSONB 字段查询
            if ($searchable !== '') {
                $searchableBool = filter_var($searchable, FILTER_VALIDATE_BOOLEAN);
                $query->whereRaw("settings->>'searchable' = ?", [$searchableBool ? 'true' : 'false']);
                $conditions['searchable'] = $searchableBool;
            }

            if (! empty($category)) {
                $query->whereRaw("settings->>'category' = ?", [$category]);
                $conditions['category'] = $category;
            }

            $results = $query->select(['id', 'title', 'settings'])->get();

            // 格式化结果
            $formattedResults = $results->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'settings' => $item->settings,
                    'searchable' => $item->settings['searchable'] ?? null,
                    'category' => $item->settings['category'] ?? null,
                ];
            })->toArray();

            $result = [
                'conditions' => $conditions,
                'total' => count($formattedResults),
                'results' => $formattedResults,
            ];

            // 记录测试日志
            $this->testerService->logTestResult('jsonb_query', [
                'conditions' => $conditions,
                'result_count' => count($formattedResults),
                'status' => 'success',
                'user_id' => $userId,
                'user_ip' => $clientIp,
            ]);

            return $this->success($result, 'JSONB查询测试完成');
        } catch (Throwable $e) {
            // 记录错误日志
            $this->testerService->logTestResult('jsonb_query', [
                'conditions' => $conditions ?? [],
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'user_id' => $userId,
                'user_ip' => $clientIp,
            ]);

            return $this->error('JSONB查询测试失败：' . $e->getMessage());
        }
    }

    /**
     * 数组操作测试.
     */
    public function arrayQueryTest(RequestInterface $request)
    {
        $tag = $request->input('tag', '');
        $operator = $request->input('operator', 'contains'); // contains, overlap, any

        if (empty($tag)) {
            return $this->error('标签关键词不能为空');
        }

        // 获取用户信息并检查权限
        $userId = $this->getCurrentUserId();
        $clientIp = $this->getClientIp($request);

        if (! $this->testerService->isIpAllowed($clientIp)) {
            return $this->error('IP地址不在白名单中', [], ErrorCode::FORBIDDEN);
        }

        if (! $this->testerService->checkRateLimit($userId, $clientIp)) {
            return $this->error('请求过于频繁，请稍后再试', [], ErrorCode::TOO_MANY_REQUESTS);
        }

        try {
            $query = PgsqlFeaturesDemo::query();

            // 数组操作查询
            switch ($operator) {
                case 'contains':
                    // 数组包含指定元素
                    $query->whereRaw('$1 = ANY(tags)', [$tag]);
                    break;
                case 'overlap':
                    // 数组重叠（有交集）
                    $query->whereRaw('tags && ARRAY[$1]', [$tag]);
                    break;
                case 'any':
                    // 数组中任意元素匹配
                    $query->whereRaw('$1 = ANY(tags)', [$tag]);
                    break;
                default:
                    $query->whereRaw('$1 = ANY(tags)', [$tag]);
            }

            $results = $query->select(['id', 'title', 'tags'])->get();

            // 格式化结果
            $formattedResults = $results->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'tags' => $item->tags ?? [],
                    'tags_string' => $item->tags_string,
                ];
            })->toArray();

            $result = [
                'tag' => $tag,
                'operator' => $operator,
                'total' => count($formattedResults),
                'results' => $formattedResults,
            ];

            // 记录测试日志
            $this->testerService->logTestResult('array_query', [
                'tag' => $tag,
                'operator' => $operator,
                'result_count' => count($formattedResults),
                'status' => 'success',
                'user_id' => $userId,
                'user_ip' => $clientIp,
            ]);

            return $this->success($result, '数组操作测试完成');
        } catch (Throwable $e) {
            // 记录错误日志
            $this->testerService->logTestResult('array_query', [
                'tag' => $tag,
                'operator' => $operator,
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'user_id' => $userId,
                'user_ip' => $clientIp,
            ]);

            return $this->error('数组操作测试失败：' . $e->getMessage());
        }
    }

    /**
     * 执行中文全文搜索测试.
     */
    public function runChineseSearchTest(RequestInterface $request): ResponseInterface
    {
        $keyword = $request->input('keyword', '');
        $limit = (int) $request->input('limit', 10);

        // 验证参数
        if (empty($keyword)) {
            return $this->error('搜索关键词不能为空');
        }

        if ($limit < 1 || $limit > 50) {
            $limit = 10;
        }

        // 获取用户信息并检查权限
        $userId = $this->getCurrentUserId();
        $clientIp = $this->getClientIp($request);

        if (! $this->testerService->isIpAllowed($clientIp)) {
            return $this->error('IP地址不在白名单中', [], ErrorCode::FORBIDDEN);
        }

        if (! $this->testerService->checkRateLimit($userId, $clientIp)) {
            return $this->error('请求过于频繁，请稍后再试', [], ErrorCode::TOO_MANY_REQUESTS);
        }

        $result = $this->testerService->runChineseSearchTest($keyword, $limit);

        // 记录测试日志
        $this->testerService->logTestResult('chinese_search', [
            'keyword' => $keyword,
            'limit' => $limit,
            'status' => $result['status'],
            'execution_time' => isset($result['execution_time']) ? (float) str_replace('ms', '', $result['execution_time']) : null,
            'error_message' => $result['status'] === 'error' ? ($result['error'] ?? null) : null,
            'result_count' => $result['status'] === 'success' ? ($result['total_results'] ?? 0) : 0,
            'result' => $result,
            'user_id' => $this->getCurrentUserId(),
            'user_ip' => $this->getClientIp($request),
        ]);

        // 更新统计信息
        $success = $result['status'] === 'success';
        $responseTime = isset($result['execution_time']) ? (float) str_replace('ms', '', $result['execution_time']) : 0;
        $this->testerService->updateStats('chinese_search', $responseTime, $success);

        return $this->success($result, '中文全文搜索测试完成');
    }

    /**
     * 执行地理空间/坐标数据测试.
     */
    public function runGeospatialTest(RequestInterface $request)
    {
        $lat = (float) $request->input('lat', 39.9042); // 北京天安门的纬度
        $lng = (float) $request->input('lng', 116.4074); // 北京天安门的经度
        $radiusKm = (float) $request->input('radius_km', 10.0);
        $limit = (int) $request->input('limit', 20);

        // 验证参数
        if ($lat < -90 || $lat > 90) {
            return $this->error('纬度必须在 -90 到 90 之间');
        }

        if ($lng < -180 || $lng > 180) {
            return $this->error('经度必须在 -180 到 180 之间');
        }

        if ($radiusKm <= 0 || $radiusKm > 1000) {
            return $this->error('搜索半径必须在 0-1000km 之间');
        }

        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }

        // 获取用户信息并检查权限
        $userId = $this->getCurrentUserId();
        $clientIp = $this->getClientIp($request);

        if (! $this->testerService->isIpAllowed($clientIp)) {
            return $this->error('IP地址不在白名单中', [], ErrorCode::FORBIDDEN);
        }

        if (! $this->testerService->checkRateLimit($userId, $clientIp)) {
            return $this->error('请求过于频繁，请稍后再试', [], ErrorCode::TOO_MANY_REQUESTS);
        }

        $result = $this->testerService->runGeospatialTest($lat, $lng, $radiusKm, $limit);

        // 记录测试日志
        $this->testerService->logTestResult('geospatial', [
            'latitude' => $lat,
            'longitude' => $lng,
            'radius_km' => $radiusKm,
            'limit' => $limit,
            'status' => $result['status'],
            'execution_time' => isset($result['execution_time']) ? (float) str_replace('ms', '', $result['execution_time']) : null,
            'error_message' => $result['status'] === 'error' ? ($result['error'] ?? null) : null,
            'result_count' => $result['status'] === 'success' ? ($result['total_results'] ?? 0) : 0,
            'within_radius_count' => $result['status'] === 'success' ? ($result['within_radius_count'] ?? 0) : 0,
            'result' => $result,
            'user_id' => $this->getCurrentUserId(),
            'user_ip' => $this->getClientIp($request),
        ]);

        // 更新统计信息
        $success = $result['status'] === 'success';
        $responseTime = isset($result['execution_time']) ? (float) str_replace('ms', '', $result['execution_time']) : 0;
        $this->testerService->updateStats('geospatial', $responseTime, $success);

        return $this->success($result, '地理空间数据测试完成');
    }

    /**
     * 创建博客文章.
     */
    public function createBlogPost(RequestInterface $request)
    {
        try {
            $data = $request->post();

            // 验证必填字段
            if (empty($data['title'])) {
                return $this->error('文章标题不能为空', 400);
            }

            if (empty($data['description'])) {
                return $this->error('文章描述不能为空', 400);
            }

            // 处理数据
            $blogData = [
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'status' => $data['status'] ?? BlogPost::STATUS_DRAFT,
                'author_id' => 1, // 默认作者ID，可以根据实际情况修改
                'category' => $data['category'] ?? null,
                'tags' => $data['tags'] ?? [],
                'view_count' => 0,
            ];

            // 创建博客文章
            $blogPost = BlogPost::create($blogData);

            return $this->success([
                'id' => $blogPost->id,
                'slug' => $blogPost->slug,
                'status' => $blogPost->status,
                'created_at' => $blogPost->created_at->toISOString(),
            ], '博客文章创建成功');
        } catch (Throwable $e) {
            logger()->error('[博客创建失败] ' . $e->getMessage(), [
                'data' => $data ?? [],
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('创建博客文章失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取当前用户ID.
     */
    private function getCurrentUserId(): ?int
    {
        try {
            $adminUser = Context::get('admin_user');
            return $adminUser ? $adminUser->id : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * 获取客户端IP地址
     */
    private function getClientIp(RequestInterface $request): string
    {
        try {
            $serverParams = $request->getServerParams();

            // 检查代理头部
            if (isset($serverParams['http_x_forwarded_for'])) {
                $ips = explode(',', $serverParams['http_x_forwarded_for']);
                return trim($ips[0]);
            }

            // 检查其他代理头部
            if (isset($serverParams['http_x_real_ip'])) {
                return $serverParams['http_x_real_ip'];
            }

            // 直接连接IP
            return $serverParams['remote_addr'] ?? '127.0.0.1';
        } catch (Throwable $e) {
            return '127.0.0.1';
        }
    }
}

