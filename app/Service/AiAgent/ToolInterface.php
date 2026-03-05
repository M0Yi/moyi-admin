<?php

declare(strict_types=1);

namespace App\Service\AiAgent;

/**
 * AI 工具接口
 */
interface ToolInterface
{
    /**
     * 获取工具名称
     */
    public function getName(): string;

    /**
     * 获取工具描述（用于 AI 理解工具用途）
     */
    public function getDescription(): string;

    /**
     * 获取参数 schema（JSON Schema 格式）
     */
    public function getParameters(): array;

    /**
     * 执行工具
     *
     * @param array $params 参数
     * @return mixed
     */
    public function execute(array $params): mixed;
}
