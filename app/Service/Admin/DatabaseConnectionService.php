<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminDatabaseConnection;
use Hyperf\DbConnection\Db;
use Hyperf\Database\ConnectionInterface;
use PDO;
use PDOException;

/**
 * 远程数据库连接管理服务
 */
class DatabaseConnectionService
{
    /**
     * 获取列表数据
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getList(array $params): array
    {
        $query = AdminDatabaseConnection::query();

        // 站点过滤
        if (isset($params['site_id']) && $params['site_id'] !== '') {
            $query->where('site_id', $params['site_id']);
        }

        // 状态过滤
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        // 搜索
        if (!empty($params['keyword'])) {
            $keyword = '%' . $params['keyword'] . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', $keyword)
                    ->orWhere('host', 'like', $keyword)
                    ->orWhere('database', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword);
            });
        }

        // 排序
        $query->ordered();

        // 分页
        $page = (int) ($params['page'] ?? 1);
        $pageSize = (int) ($params['page_size'] ?? 15);
        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        return [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 根据ID获取连接配置
     *
     * @param int $id
     * @return AdminDatabaseConnection
     * @throws BusinessException
     */
    public function getById(int $id): AdminDatabaseConnection
    {
        $connection = AdminDatabaseConnection::find($id);
        if (!$connection) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '数据库连接配置不存在');
        }

        return $connection;
    }

    /**
     * 创建数据库连接配置
     *
     * @param array $data
     * @return AdminDatabaseConnection
     * @throws BusinessException
     */
    public function create(array $data): AdminDatabaseConnection
    {
        // 检查连接名称是否已存在（同一站点下）
        $siteId = $data['site_id'] ?? null;
        $exists = AdminDatabaseConnection::query()
            ->where('site_id', $siteId)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '该连接名称已存在');
        }

        // 验证连接名称格式（只允许字母、数字、下划线）
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['name'])) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '连接名称只能包含字母、数字和下划线');
        }

        // 设置默认值
        $data['site_id'] = $siteId ?? site_id();
        $data['driver'] = $data['driver'] ?? AdminDatabaseConnection::DRIVER_MYSQL;
        $data['port'] = $data['port'] ?? 3306;
        $data['charset'] = $data['charset'] ?? 'utf8mb4';
        $data['collation'] = $data['collation'] ?? 'utf8mb4_unicode_ci';
        $data['status'] = $data['status'] ?? AdminDatabaseConnection::STATUS_ENABLED;
        $data['sort'] = $data['sort'] ?? 0;

        return AdminDatabaseConnection::create($data);
    }

    /**
     * 更新数据库连接配置
     *
     * @param int $id
     * @param array $data
     * @return AdminDatabaseConnection
     * @throws BusinessException
     */
    public function update(int $id, array $data): AdminDatabaseConnection
    {
        $connection = $this->getById($id);

        // 如果更新了连接名称，检查是否重复
        if (isset($data['name']) && $data['name'] !== $connection->name) {
            $siteId = $data['site_id'] ?? $connection->site_id;
            $exists = AdminDatabaseConnection::query()
                ->where('site_id', $siteId)
                ->where('name', $data['name'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '该连接名称已存在');
            }

            // 验证连接名称格式
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['name'])) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '连接名称只能包含字母、数字和下划线');
            }
        }

        // 如果密码为空，不更新密码
        if (isset($data['password']) && empty($data['password'])) {
            unset($data['password']);
        }

        $connection->fill($data);
        $connection->save();

        return $connection;
    }

    /**
     * 删除数据库连接配置
     *
     * @param int $id
     * @return bool
     * @throws BusinessException
     */
    public function delete(int $id): bool
    {
        $connection = $this->getById($id);
        return $connection->delete();
    }

    /**
     * 批量删除
     *
     * @param array $ids
     * @return int 删除的记录数
     */
    public function batchDelete(array $ids): int
    {
        return AdminDatabaseConnection::query()
            ->whereIn('id', $ids)
            ->delete();
    }

    /**
     * 测试数据库连接
     *
     * @param int $id 连接配置ID
     * @param string|null $password 明文密码（如果提供，将使用此密码测试；否则使用存储的密码）
     * @return array 测试结果
     * @throws BusinessException
     */
    public function testConnection(int $id, ?string $password = null): array
    {
        $connection = $this->getById($id);

        // 如果提供了密码，使用提供的密码；否则使用存储的密码（明文）
        $testPassword = $password ?? $connection->password;
        if (empty($testPassword)) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '测试连接需要提供密码');
        }

        $config = $connection->toConnectionConfig($testPassword);

        try {
            // 根据驱动类型创建PDO连接
            $dsn = $this->buildDsn($config);
            $pdo = new PDO(
                $dsn,
                $config['username'],
                $testPassword,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5, // 5秒超时
                ]
            );

            // 执行简单查询测试
            $stmt = $pdo->query('SELECT 1');
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // 获取数据库版本信息
                $version = $this->getDatabaseVersion($pdo, $config['driver']);

                return [
                    'success' => true,
                    'message' => '连接成功',
                    'version' => $version,
                    'database' => $config['database'],
                ];
            }

            return [
                'success' => false,
                'message' => '连接失败：无法执行查询',
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '连接失败：' . $e->getMessage(),
                'code' => $e->getCode(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => '连接失败：' . $e->getMessage(),
            ];
        }
    }

    /**
     * 构建DSN连接字符串
     *
     * @param array $config
     * @return string
     */
    private function buildDsn(array $config): string
    {
        $driver = $config['driver'];
        $host = $config['host'];
        $port = $config['port'];
        $database = $config['database'];
        $charset = $config['charset'] ?? 'utf8mb4';

        return match ($driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$database}",
            default => throw new BusinessException(ErrorCode::VALIDATION_ERROR, "不支持的数据库驱动：{$driver}，仅支持 MySQL 和 PostgreSQL"),
        };
    }

    /**
     * 获取数据库版本信息
     *
     * @param PDO $pdo
     * @param string $driver
     * @return string
     */
    private function getDatabaseVersion(PDO $pdo, string $driver): string
    {
        try {
            return match ($driver) {
                'mysql' => $pdo->query('SELECT VERSION()')->fetchColumn() ?: 'Unknown',
                'pgsql' => $pdo->query('SELECT version()')->fetchColumn() ?: 'Unknown',
                default => 'Unknown',
            };
        } catch (\Throwable $e) {
            return 'Unknown';
        }
    }

    /**
     * 获取表单字段配置
     *
     * @param string $scene 场景：create|update
     * @param AdminDatabaseConnection|null $connection 连接对象（编辑时传入）
     * @return array
     */
    public function getFormFields(string $scene = 'create', ?AdminDatabaseConnection $connection = null): array
    {
        $drivers = AdminDatabaseConnection::getDrivers();
        $statuses = AdminDatabaseConnection::getStatuses();

        $fields = [
            [
                'name' => 'name',
                'label' => '连接名称',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：db2、db3',
                'help' => '用于在配置中引用的名称，只能包含字母、数字和下划线',
                'default' => $connection?->name ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '基本信息',
            ],
            [
                'name' => 'driver',
                'label' => '驱动类型',
                'type' => 'select',
                'required' => true,
                'options' => array_map(fn($key, $value) => ['value' => $key, 'label' => $value], array_keys($drivers), $drivers),
                'default' => $connection?->driver ?? AdminDatabaseConnection::DRIVER_MYSQL,
                'col' => 'col-12 col-md-6',
                'group' => '基本信息',
            ],
            [
                'name' => 'host',
                'label' => '主机地址',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：127.0.0.1 或 mysql.example.com',
                'default' => $connection?->host ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '连接信息',
            ],
            [
                'name' => 'port',
                'label' => '端口',
                'type' => 'number',
                'required' => true,
                'placeholder' => '例如：3306',
                'default' => $connection?->port ?? 3306,
                'col' => 'col-12 col-md-6',
                'group' => '连接信息',
            ],
            [
                'name' => 'database',
                'label' => '数据库名',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入数据库名称',
                'default' => $connection?->database ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '连接信息',
            ],
            [
                'name' => 'username',
                'label' => '用户名',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入数据库用户名',
                'default' => $connection?->username ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '连接信息',
            ],
            [
                'name' => 'password',
                'label' => '密码',
                'type' => 'password',
                'required' => $scene === 'create',
                'placeholder' => $scene === 'update' ? '留空则不修改密码' : '请输入数据库密码',
                'help' => $scene === 'update' ? '留空则不修改密码' : '密码以明文形式存储，用于数据库连接',
                'default' => '',
                'col' => 'col-12 col-md-6',
                'group' => '连接信息',
            ],
            [
                'name' => 'charset',
                'label' => '字符集',
                'type' => 'text',
                'required' => false,
                'placeholder' => '例如：utf8mb4',
                'default' => $connection?->charset ?? 'utf8mb4',
                'col' => 'col-12 col-md-6',
                'group' => '高级设置',
            ],
            [
                'name' => 'collation',
                'label' => '排序规则',
                'type' => 'text',
                'required' => false,
                'placeholder' => '例如：utf8mb4_unicode_ci',
                'default' => $connection?->collation ?? 'utf8mb4_unicode_ci',
                'col' => 'col-12 col-md-6',
                'group' => '高级设置',
            ],
            [
                'name' => 'prefix',
                'label' => '表前缀',
                'type' => 'text',
                'required' => false,
                'placeholder' => '例如：pre_',
                'default' => $connection?->prefix ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '高级设置',
            ],
            [
                'name' => 'description',
                'label' => '描述',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => '请输入连接描述（可选）',
                'rows' => 3,
                'default' => $connection?->description ?? '',
                'col' => 'col-12',
                'group' => '其他信息',
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'select',
                'required' => true,
                'options' => array_map(fn($key, $value) => ['value' => (string)$key, 'label' => $value], array_keys($statuses), $statuses),
                'default' => $connection?->status ?? AdminDatabaseConnection::STATUS_ENABLED,
                'col' => 'col-12 col-md-6',
                'group' => '其他信息',
            ],
            [
                'name' => 'sort',
                'label' => '排序',
                'type' => 'number',
                'required' => false,
                'placeholder' => '数字越小越靠前',
                'default' => $connection?->sort ?? 0,
                'col' => 'col-12 col-md-6',
                'group' => '其他信息',
            ],
        ];

        return $fields;
    }
}

