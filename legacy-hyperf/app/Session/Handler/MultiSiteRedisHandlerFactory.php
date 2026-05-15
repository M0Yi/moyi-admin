<?php

declare(strict_types=1);

namespace App\Session\Handler;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;

/**
 * 多站点 Redis Session Handler 工厂类
 *
 * 功能：
 * 1. 创建 MultiSiteRedisHandler 实例
 * 2. 从配置中读取 Redis 连接和生命周期设置
 */
class MultiSiteRedisHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);
        $connection = $config->get('session.options.connection', 'default');
        $gcMaxLifetime = $config->get('session.options.gc_maxlifetime', 1200);
        $redisFactory = $container->get(RedisFactory::class);
        $redis = $redisFactory->get($connection);
        
        return new MultiSiteRedisHandler($redis, $gcMaxLifetime);
    }
}















