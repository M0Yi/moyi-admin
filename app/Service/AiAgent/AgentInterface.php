<?php

declare(strict_types=1);

namespace App\Service\AiAgent;

use App\Service\AiAgent\Provider\ProviderInterface;

/**
 * AI Agent 接口
 */
interface AgentInterface
{
    /**
     * 获取 Agent 标识
     */
    public function getSlug(): string;

    /**
     * 获取 Agent 名称
     */
    public function getName(): string;

    /**
     * 获取 Agent 类型
     */
    public function getType(): string;

    /**
     * 执行 Agent（单次调用）
     *
     * @param mixed $input 输入
     * @param array $options 选项
     * @return AgentResult
     */
    public function execute(mixed $input, array $options = []): AgentResult;

    /**
     * 流式执行（客服场景）
     *
     * @param mixed $input 输入
     * @param array $options 选项
     * @param callable $onChunk 回调函数
     * @return AgentResult
     */
    public function executeStream(mixed $input, array $options = [], callable $onChunk): AgentResult;

    /**
     * 验证输入
     *
     * @param mixed $input 输入
     * @return bool
     */
    public function validateInput(mixed $input): bool;

    /**
     * 获取配置项
     *
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function getConfig(string $key, mixed $default = null): mixed;

    /**
     * 设置 Provider
     *
     * @param ProviderInterface $provider
     * @return self
     */
    public function setProvider(ProviderInterface $provider): self;

    /**
     * 是否支持流式输出
     *
     * @return bool
     */
    public function supportsStream(): bool;

    /**
     * 是否支持工具调用
     *
     * @return bool
     */
    public function supportsTools(): bool;
}
