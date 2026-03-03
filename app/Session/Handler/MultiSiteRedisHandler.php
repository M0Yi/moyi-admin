<?php

declare(strict_types=1);

namespace App\Session\Handler;

use Hyperf\Context\Context;
use Hyperf\Session\Handler\RedisHandler as BaseRedisHandler;

/**
 * 多站点 Redis Session Handler
 *
 * 功能：
 * 1. Session key 包含站点ID，确保不同站点的 session 数据完全隔离
 * 2. 格式：session:{site_id}:{session_id}
 * 3. 继承 Hyperf\Session\Handler\RedisHandler，重写 read、write、destroy 方法
 */
class MultiSiteRedisHandler extends BaseRedisHandler
{
    /**
     * 获取带站点ID的 Session Key
     */
    protected function getSessionKey(string $sessionId): string
    {
        // 获取当前站点ID
        $siteId = Context::get('site_id', 0);
        
        // 如果站点ID为0或未设置，使用默认格式（兼容未识别站点的情况）
        if ($siteId <= 0) {
            return 'session:0:' . $sessionId;
        }

        // 返回包含站点ID的 key：session:{site_id}:{session_id}
        return sprintf('session:%d:%s', $siteId, $sessionId);
    }

    /**
     * 读取 Session 数据
     */
    public function read(string $id): false|string
    {
        $key = $this->getSessionKey($id);
        $data = $this->redis->get($key);
        
        return $data === false ? '' : $data;
    }

    /**
     * 写入 Session 数据
     */
    public function write(string $id, string $data): bool
    {
        $key = $this->getSessionKey($id);

        // 检查是否为"保持登录"模式
        $ttl = $this->getSessionTtl($data);

        return (bool) $this->redis->setEx($key, $ttl, $data);
    }

    /**
     * 获取 Session 过期时间
     */
    protected function getSessionTtl(string $data): int
    {
        // 解析 Session 数据，检查是否包含 remember_me 标志
        $decoded = $this->decodeSessionData($data);

        // 如果包含 admin_remember_me 标志且为 true，设置为 24 小时
        if (isset($decoded['admin_remember_me']) && $decoded['admin_remember_me']) {
            return 24 * 60 * 60; // 24小时
        }

        // 否则使用默认过期时间
        return $this->gcMaxLifeTime;
    }

    /**
     * 解析 Session 数据
     */
    protected function decodeSessionData(string $data): array
    {
        try {
            // Session 数据通常是序列化的，这里尝试反序列化
            if (empty($data)) {
                return [];
            }

            $unserialized = unserialize($data);
            return is_array($unserialized) ? $unserialized : [];
        } catch (\Throwable $e) {
            // 如果反序列化失败，返回空数组
            return [];
        }
    }

    /**
     * 删除 Session 数据
     */
    public function destroy(string $id): bool
    {
        $key = $this->getSessionKey($id);
        $this->redis->del($key);
        
        return true;
    }
}

