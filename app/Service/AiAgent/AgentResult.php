<?php

declare(strict_types=1);

namespace App\Service\AiAgent;

use App\Service\AiAgent\Provider\ProviderInterface;

/**
 * AI Agent 结果
 */
class AgentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message = '',
        public readonly mixed $data = null,
        public readonly int $tokens = 0,
        public readonly int $duration = 0,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function success(string $message = '', mixed $data = null, int $tokens = 0, int $duration = 0): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
            tokens: $tokens,
            duration: $duration,
        );
    }

    public static function fail(string $errorMessage, mixed $data = null): self
    {
        return new self(
            success: false,
            message: '',
            data: $data,
            errorMessage: $errorMessage,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'tokens' => $this->tokens,
            'duration' => $this->duration,
            'error_message' => $this->errorMessage,
        ];
    }
}
