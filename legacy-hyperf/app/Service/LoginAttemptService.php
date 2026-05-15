<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Contract\SessionInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Redis\RedisProxy;
use Psr\Container\ContainerInterface;

/**
 * 登录尝试服务
 * 
 * 功能：
 * - 记录登录失败次数（基于 IP）
 * - 免验证码令牌管理（基于 IP，第一个窗口给令牌，后续窗口需要验证码）
 * - 根据失败次数判断是否需要验证码
 * - 清除失败次数
 */
class LoginAttemptService
{
    #[Inject]
    protected SessionInterface $session;

    #[Inject]
    protected RequestInterface $request;

    #[Inject]
    protected ContainerInterface $container;

    /**
     * Session Key（保留用于兼容）
     */
    private const SESSION_KEY = 'login_attempts';

    /**
     * Redis Key 前缀
     */
    private const REDIS_KEY_PREFIX = 'login_attempts:ip:';
    private const REDIS_FREE_TOKEN_PREFIX = 'login_free_token:ip:';

    /**
     * 需要验证码的失败次数阈值
     */
    private const CAPTCHA_THRESHOLD = 1;

    /**
     * 失败次数过期时间（秒）
     */
    private const EXPIRE_TIME = 1800; // 30分钟

    /**
     * 免验证码令牌过期时间（秒）
     */
    private const FREE_TOKEN_EXPIRE_TIME = 300; // 5分钟

    /**
     * 获取 Redis 实例
     */
    private function getRedis(): RedisProxy
    {
        return $this->container->get(RedisFactory::class)->get('default');
    }

    /**
     * 获取客户端 IP
     */
    private function getClientIp(): string
    {
        $serverParams = $this->request->getServerParams();
        
        // 优先获取代理转发的真实 IP
        if (isset($serverParams['http_x_forwarded_for'])) {
            $ips = explode(',', $serverParams['http_x_forwarded_for']);
            return trim($ips[0]);
        }

        if (isset($serverParams['http_x_real_ip'])) {
            return $serverParams['http_x_real_ip'];
        }

        return $serverParams['remote_addr'] ?? '0.0.0.0';
    }

    /**
     * 获取 IP 的 Redis Key
     */
    private function getIpKey(): string
    {
        $ip = $this->getClientIp();
        return self::REDIS_KEY_PREFIX . $ip;
    }

    /**
     * 获取免验证码令牌的 Redis Key
     */
    private function getFreeTokenKey(): string
    {
        $ip = $this->getClientIp();
        return self::REDIS_FREE_TOKEN_PREFIX . $ip;
    }

    /**
     * 增加登录失败次数（基于 IP）
     */
    public function increment(): int
    {
        $redis = $this->getRedis();
        $key = $this->getIpKey();
        
        // 使用原子操作增加失败次数
        $attempts = $redis->incr($key);
        
        // 设置过期时间（只在第一次设置时生效）
        if ($attempts === 1) {
            $redis->expire($key, self::EXPIRE_TIME);
        }

        // 清除免验证码令牌（失败后不再给令牌）
        $this->clearFreeToken();

        return $attempts;
    }

    /**
     * 获取登录失败次数（基于 IP）
     */
    public function getAttempts(): int
    {
        $redis = $this->getRedis();
        $key = $this->getIpKey();
        
        $attempts = $redis->get($key);
        return $attempts ? (int)$attempts : 0;
    }

    /**
     * 清除登录失败次数（基于 IP）
     */
    public function clear(): void
    {
        $redis = $this->getRedis();
        $key = $this->getIpKey();
        $redis->del($key);
        
        // 清除免验证码令牌
        $this->clearFreeToken();
    }

    /**
     * 判断是否需要验证码（优先检查失败次数，如果有失败记录，直接需要验证码）
     */
    public function requiresCaptcha(): bool
    {
        // 如果有失败记录，直接需要验证码
        if ($this->getAttempts() >= self::CAPTCHA_THRESHOLD) {
            return true;
        }

        // 如果没有失败记录，检查是否有免验证码令牌
        // 如果没有令牌，需要验证码
        return !$this->hasFreeToken();
    }

    /**
     * 尝试获取免验证码令牌（第一个窗口可以获取，后续窗口获取不到）
     * 
     * 使用 Redis 的 SETNX 原子操作确保并发安全
     * 
     * 重要：每个 IP 只能获取一次免验证码令牌，即使令牌被使用或过期，也不会再次获取
     * 这样可以防止用户通过刷新页面重复获取令牌
     * 
     * 并发安全机制：
     * 1. 使用 $usedKey 进行 SETNX 原子操作，确保只有一个窗口能成功
     * 2. 只有 SETNX 成功的窗口才能生成和返回令牌
     * 3. 其他窗口 SETNX 失败，直接返回 null
     * 
     * @return string|null 返回令牌，如果获取不到返回 null
     */
    public function tryGetFreeToken(): ?string
    {
        // 如果有失败记录，不给令牌
        if ($this->getAttempts() >= self::CAPTCHA_THRESHOLD) {
            return null;
        }

        $redis = $this->getRedis();
        $key = $this->getFreeTokenKey();
        $tokenKey = $key . ':token';
        $usedKey = $key . ':used'; // 标记是否已获取过令牌

        // 使用 SETNX 原子操作在 $usedKey 上，确保只有一个窗口能成功
        // SETNX key value：如果 key 不存在，设置 key 的值为 value，返回 1；如果 key 已存在，返回 0
        // 这个操作是原子的，多个窗口同时调用时，只有一个会成功
        $success = $redis->setNx($usedKey, '1');
        
        if (!$success) {
            // SETNX 失败，说明已经有窗口获取过令牌了，直接返回 null
            return null;
        }

        // SETNX 成功，说明这是第一个窗口，可以获取令牌
        // 设置过期时间（和令牌过期时间一致）
        $redis->expire($usedKey, self::FREE_TOKEN_EXPIRE_TIME);
        
        // 生成令牌
        $token = bin2hex(random_bytes(16)); // 生成32位随机令牌
        
        // 存储令牌
        $redis->setex($tokenKey, self::FREE_TOKEN_EXPIRE_TIME, $token);
        // 标记令牌已存在（用于 hasFreeToken 检查）
        $redis->setex($key, self::FREE_TOKEN_EXPIRE_TIME, '1');
        
        return $token;
    }

    /**
     * 检查是否有免验证码令牌
     */
    public function hasFreeToken(): bool
    {
        $redis = $this->getRedis();
        $key = $this->getFreeTokenKey();
        return $redis->exists($key) > 0;
    }

    /**
     * 验证免验证码令牌
     * 
     * 注意：令牌使用后只删除令牌本身，保留"已获取令牌"标记，防止刷新页面后重复获取
     * 
     * @param string|null $token 令牌
     * @return bool 是否有效
     */
    public function verifyFreeToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        // 如果有失败记录，令牌无效
        if ($this->getAttempts() >= self::CAPTCHA_THRESHOLD) {
            return false;
        }

        $redis = $this->getRedis();
        $key = $this->getFreeTokenKey();
        $tokenKey = $key . ':token';
        
        // 验证令牌
        $storedToken = $redis->get($tokenKey);
        if ($storedToken === $token) {
            // 令牌有效，使用后删除令牌（但保留"已获取令牌"标记，防止刷新页面后重复获取）
            $redis->del($tokenKey);
            $redis->del($key);
            // 注意：不删除 $usedKey，这样即使刷新页面也无法再次获取令牌
            return true;
        }

        return false;
    }

    /**
     * 清除免验证码令牌
     * 
     * 清除所有相关标记，包括"已获取令牌"标记，允许重新获取令牌
     */
    public function clearFreeToken(): void
    {
        $redis = $this->getRedis();
        $key = $this->getFreeTokenKey();
        $tokenKey = $key . ':token';
        $usedKey = $key . ':used';
        $redis->del($key, $tokenKey, $usedKey);
    }
}

