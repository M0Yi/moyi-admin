<?php

declare(strict_types=1);

namespace App\Service\AiAgent\Provider;

/**
 * AI Provider 接口
 */
interface ProviderInterface
{
    /**
     * 获取 Provider 标识
     */
    public function getSlug(): string;

    /**
     * 获取 Provider 名称
     */
    public function getName(): string;

    /**
     * 发送聊天请求
     *
     * @param array $params 请求参数
     * @return array 响应结果
     */
    public function chat(array $params): array;

    /**
     * 流式发送聊天请求
     *
     * @param array $params 请求参数
     * @param callable $onChunk 回调函数
     */
    public function streamChat(array $params, callable $onChunk): void;

    /**
     * 获取可用模型列表
     *
     * @return array
     */
    public function getModels(): array;

    /**
     * 设置 API Key
     *
     * @param string $apiKey
     * @return self
     */
    public function setApiKey(string $apiKey): self;

    /**
     * 设置基础 URL
     *
     * @param string $baseUrl
     * @return self
     */
    public function setBaseUrl(string $baseUrl): self;
}
