<?php

declare(strict_types=1);

namespace App\Service\AiAgent;

use App\Model\Admin\AiAgent;
use App\Service\AiAgent\Provider\ProviderInterface;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;

/**
 * AI Agent 抽象基类
 */
abstract class BaseAgent implements AgentInterface
{
    /**
     * Agent 模型实例
     */
    protected ?AiAgent $agentModel = null;

    /**
     * Agent 配置
     */
    protected array $config = [];

    /**
     * AI Provider
     */
    protected ?ProviderInterface $provider = null;

    /**
     * Agent ID
     */
    protected int $agentId = 0;

    /**
     * 重试次数
     */
    protected int $retryTimes = 3;

    /**
     * 重试间隔（毫秒）
     */
    protected int $retryDelay = 1000;

    /**
     * 设置 Agent 模型
     */
    public function setAgentModel(AiAgent $agentModel): self
    {
        $this->agentModel = $agentModel;
        $this->agentId = $agentModel->id;
        $this->config = $agentModel->config ?? [];
        return $this;
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * 获取配置项
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 设置 Provider
     */
    public function setProvider(ProviderInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * 获取 Provider
     */
    public function getProvider(): ?ProviderInterface
    {
        return $this->provider;
    }

    /**
     * 获取 Agent ID
     */
    public function getAgentId(): int
    {
        return $this->agentId;
    }

    /**
     * 是否支持流式输出
     */
    public function supportsStream(): bool
    {
        return false;
    }

    /**
     * 是否支持工具调用
     */
    public function supportsTools(): bool
    {
        return false;
    }

    /**
     * 执行带重试
     */
    protected function executeWithRetry(callable $callback): mixed
    {
        $lastException = null;

        for ($i = 0; $i < $this->retryTimes; $i++) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $lastException = $e;

                // 非重试异常直接抛出
                if (!$this->isRetryableException($e)) {
                    throw $e;
                }

                // 最后一次不等待
                if ($i < $this->retryTimes - 1) {
                    usleep($this->retryDelay * 1000 * ($i + 1));
                }
            }
        }

        throw $lastException;
    }

    /**
     * 判断是否可重试
     */
    protected function isRetryableException(\Throwable $e): bool
    {
        // 网络错误、超时等可重试
        $message = $e->getMessage();
        return str_contains($message, 'timeout')
            || str_contains($message, 'connection')
            || str_contains($message, 'network')
            || str_contains($message, 'rate limit');
    }

    /**
     * 记录日志
     */
    protected function log(array $data): void
    {
        try {
            $logData = [
                'site_id' => Context::get('site_id'),
                'agent_id' => $this->agentId,
                'agent_name' => $this->getName(),
                'agent_type' => $this->getType(),
                'user_id' => Context::get('admin_user_id'),
                'username' => Context::get('admin_user.username') ?? '',
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $logData = array_merge($logData, $data);

            \App\Model\Admin\AiAgentLog::create($logData);
        } catch (\Throwable $e) {
            logger()->error('[AiAgent] Failed to log: ' . $e->getMessage());
        }
    }

    /**
     * 获取当前站点 ID
     */
    protected function getCurrentSiteId(): ?int
    {
        return Context::get('site_id');
    }

    /**
     * 获取客户端 IP
     */
    protected function getClientIp(): string
    {
        $request = Context::get(\Psr\Http\Message\ServerRequestInterface::class);
        if (!$request) {
            return '';
        }

        $serverParams = $request->getServerParams();
        return $serverParams['http_x_forwarded_for'] ?? $serverParams['remote_addr'] ?? '';
    }

    /**
     * 获取 User Agent
     */
    protected function getUserAgent(): string
    {
        $request = Context::get(\Psr\Http\Message\ServerRequestInterface::class);
        if (!$request) {
            return '';
        }

        return $request->getHeaderLine('User-Agent');
    }

    /**
     * 估算 token 数量（简化版）
     */
    protected function estimateTokens(string $content): int
    {
        // 简单估算：中文字符约等于 2 个 token，英文约 4 个字符 1 个 token
        $chineseCount = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $content);
        $otherCount = mb_strlen($content) - $chineseCount;

        return (int) ($chineseCount * 0.5 + $otherCount * 0.25);
    }
}
