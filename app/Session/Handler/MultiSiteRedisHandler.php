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
        
        return (bool) $this->redis->setEx($key, $this->gcMaxLifeTime, $data);
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

