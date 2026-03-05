<?php

declare(strict_types=1);

namespace App\Service\AiAgent;

/**
 * 会话管理器
 *
 * 用于管理客服场景的多轮对话
 */
class SessionManager
{
    /**
     * 最大上下文 Token 数
     */
    protected int $maxContextTokens = 4000;

    /**
     * 会话过期时间（小时）
     */
    protected int $expireHours = 24;

    public function __construct(
        protected \App\Service\Admin\AiAgentSessionService $sessionService
    ) {}

    /**
     * 创建会话
     */
    public function create(int $agentId, ?int $userId = null, string $userType = 'guest', ?string $userName = null): string
    {
        $sessionId = $this->generateSessionId();

        $this->sessionService->create([
            'agent_id' => $agentId,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'user_type' => $userType,
            'user_name' => $userName,
            'status' => 1,
            'context' => [],
            'message_count' => 0,
            'total_tokens' => 0,
        ]);

        return $sessionId;
    }

    /**
     * 添加用户消息
     */
    public function addUserMessage(string $sessionId, string $content): void
    {
        $this->addMessage($sessionId, 'user', $content);
    }

    /**
     * 添加 AI 回复
     */
    public function addAssistantMessage(string $sessionId, string $content): void
    {
        $this->addMessage($sessionId, 'assistant', $content);
    }

    /**
     * 获取会话上下文
     */
    public function getContext(string $sessionId): array
    {
        $session = $this->sessionService->findBySessionId($sessionId);

        if (!$session) {
            return [];
        }

        return $session->context ?? [];
    }

    /**
     * 构建消息数组（用于 AI 调用）
     */
    public function buildMessages(string $sessionId, string $currentInput): array
    {
        $context = $this->getContext($sessionId);

        $messages = [];

        // 添加历史消息（控制 token 数量）
        $totalTokens = 0;
        foreach (array_reverse($context) as $msg) {
            $msgTokens = $this->estimateTokens($msg['content'] ?? '');
            if ($totalTokens + $msgTokens > $this->maxContextTokens) {
                break;
            }
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
            $totalTokens += $msgTokens;
        }

        $messages = array_reverse($messages);
        $messages[] = ['role' => 'user', 'content' => $currentInput];

        return $messages;
    }

    /**
     * 结束会话
     */
    public function end(string $sessionId): void
    {
        $this->sessionService->updateBySessionId($sessionId, ['status' => 0]);
    }

    /**
     * 获取会话
     */
    public function getSession(string $sessionId): ?\App\Model\Admin\AiAgentSession
    {
        return $this->sessionService->findBySessionId($sessionId);
    }

    /**
     * 清理过期会话
     */
    public function cleanExpired(?int $expireHours = null): int
    {
        $expireHours = $expireHours ?? $this->expireHours;

        $expiredAt = date('Y-m-d H:i:s', time() - $expireHours * 3600);

        return $this->sessionService->cleanExpired($expiredAt);
    }

    /**
     * 添加消息
     */
    protected function addMessage(string $sessionId, string $role, string $content): void
    {
        $session = $this->sessionService->findBySessionId($sessionId);

        if (!$session) {
            return;
        }

        $context = $session->context ?? [];
        $context[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => time(),
        ];

        $tokens = $this->estimateTokens($content);

        $this->sessionService->updateBySessionId($sessionId, [
            'context' => $context,
            'message_count' => ($session->message_count ?? 0) + 1,
            'total_tokens' => ($session->total_tokens ?? 0) + $tokens,
            'last_message_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 生成会话 ID
     */
    protected function generateSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 估算 Token 数量
     */
    protected function estimateTokens(string $content): int
    {
        if (empty($content)) {
            return 0;
        }

        // 简单估算：中文字符约等于 2 个 token，英文约 4 个字符 1 个 token
        $chineseCount = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $content);
        $otherCount = mb_strlen($content) - $chineseCount;

        return (int) ($chineseCount * 0.5 + $otherCount * 0.25);
    }
}
