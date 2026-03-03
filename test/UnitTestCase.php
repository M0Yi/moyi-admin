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

namespace HyperfTest;

use PHPUnit\Framework\TestCase;
use Hyperf\Di\Container;
use Hyperf\Di\ClassLoader;

/**
 * 检查 Swoole 是否可用
 */
function hasSwoole(): bool
{
    return extension_loaded('swoole');
}

/**
 * 单元测试基础类
 * 
 * 提供：
 * - 容器初始化（可选）
 * - 服务获取方法
 * - 数据库事务回滚（测试数据隔离）
 */
abstract class UnitTestCase extends TestCase
{
    /**
     * 测试容器
     */
    protected static ?Container $container = null;

    /**
     * 容器是否已初始化
     */
    protected static bool $containerInited = false;

    /**
     * Swoole 是否可用
     */
    protected static ?bool $swooleAvailable = null;

    /**
     * 标记是否为数据测试（需要回滚）
     */
    private bool $isDataTest = false;

    /**
     * 已创建的测试数据记录
     */
    private array $createdRecords = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // 检查 Swoole 是否可用
        self::$swooleAvailable = hasSwoole();

        if (!self::$containerInited && !defined('BASE_PATH') && self::$swooleAvailable) {
            self::initContainer();
        }
    }

    /**
     * 初始化测试容器
     */
    protected static function initContainer(): void
    {
        if (defined('BASE_PATH') || !self::$swooleAvailable) {
            return;
        }

        defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__, 2));
        require BASE_PATH . '/vendor/autoload.php';

        Hyperf\Di\ClassLoader::init();

        self::$container = require BASE_PATH . '/config/container.php';
        self::$containerInited = true;

        // 初始化应用
        self::$container->get(Hyperf\Contract\ApplicationInterface::class);
    }

    /**
     * 获取容器实例
     */
    protected function getContainer(): Container
    {
        if (self::$container === null) {
            if (!self::$swooleAvailable) {
                $this->markTestSkipped('Swoole 扩展未安装，无法使用容器');
            }
            self::initContainer();
        }

        return self::$container;
    }

    /**
     * 获取服务实例
     */
    protected function getService(string $class)
    {
        if (!self::$swooleAvailable) {
            $this->markTestSkipped('Swoole 扩展未安装，无法获取服务');
        }
        return $this->getContainer()->get($class);
    }

    /**
     * 获取模型实例
     */
    protected function getModel(string $class)
    {
        return new $class();
    }

    /**
     * 标记为数据测试（自动清理数据）
     */
    protected function markAsDataTest(): void
    {
        $this->isDataTest = true;
    }

    /**
     * 记录创建的测试数据（用于清理）
     */
    protected function recordCreated(string $model, int $id): void
    {
        $this->createdRecords[] = [
            'model' => $model,
            'id' => $id,
        ];
    }

    /**
     * 清理测试数据
     */
    protected function cleanupTestData(): void
    {
        foreach ($this->createdRecords as $record) {
            try {
                $model = $record['model'];
                $model::find($record['id'])?->delete();
            } catch (\Throwable $e) {
                // 忽略清理错误
            }
        }
        $this->createdRecords = [];
    }

    /**
     * 创建测试数据并记录
     */
    protected function createRecord(string $model, array $data): array
    {
        /** @var \Hyperf\Database\Model\Model $instance */
        $instance = $model::create($data);
        $this->recordCreated($model, $instance->id);
        return $instance->toArray();
    }

    /**
     * 设置测试数据
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * 清理测试数据
     */
    protected function tearDown(): void
    {
        if ($this->isDataTest) {
            $this->cleanupTestData();
        }
        parent::tearDown();
    }

    /**
     * 跳过测试（带原因）
     */
    protected function skipTest(string $reason): void
    {
        $this->markTestSkipped($reason);
    }

    /**
     * 断言数组包含指定键
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array should contain key: {$key}");
        }
    }

    /**
     * 断言分页数据结构
     */
    protected function assertPaginatorStructure(array $result): void
    {
        $this->assertArrayHasKeys(['list', 'total', 'page', 'page_size', 'last_page'], $result);
        $this->assertIsArray($result['list']);
        $this->assertIsInt($result['total']);
        $this->assertIsInt($result['page']);
        $this->assertIsInt($result['page_size']);
        $this->assertIsInt($result['last_page']);
    }

    /**
     * 断言响应数据结构
     */
    protected function assertResponseStructure(array $response, array $requiredDataKeys = []): void
    {
        $this->assertArrayHasKey('code', $response);
        $this->assertArrayHasKey('msg', $response);
        $this->assertArrayHasKey('data', $response);

        if (!empty($requiredDataKeys)) {
            $this->assertArrayHasKeys($requiredDataKeys, $response['data'] ?? []);
        }
    }
}
