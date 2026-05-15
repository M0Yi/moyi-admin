<?php

declare(strict_types=1);

namespace App\Service\AiAgent;

/**
 * 客服 Agent
 *
 * 支持多轮对话、知识库问答、工具调用、流式输出
 */
class CustomerServiceAgent extends BaseAgent implements AgentInterface
{
    /**
     * Agent 类型
     */
    public const TYPE = 'service';

    protected SessionManager $sessionManager;
    protected KnowledgeBase $knowledgeBase;
    protected ToolRegistry $toolRegistry;

    public function __construct()
    {
        $this->retryTimes = 3;
    }

    /**
     * 获取 Agent 标识
     */
    public function getSlug(): string
    {
        return 'customer-service-agent';
    }

    /**
     * 获取 Agent 名称
     */
    public function getName(): string
    {
        return '智能客服 Agent';
    }

    /**
     * 获取 Agent 类型
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 是否支持流式输出
     */
    public function supportsStream(): bool
    {
        return $this->getConfig('stream_enabled', true);
    }

    /**
     * 是否支持工具调用
     */
    public function supportsTools(): bool
    {
        return $this->getConfig('tools_enabled', true);
    }

    /**
     * 执行客服咨询
     */
    public function execute(mixed $input, array $options = []): AgentResult
    {
        $startTime = microtime(true);

        try {
            // 验证输入
            if (!$this->validateInput($input)) {
                return AgentResult::fail('内容不能为空');
            }

            $sessionId = $options['session_id'] ?? null;
            $userId = $options['user_id'] ?? null;
            $userName = $options['user_name'] ?? null;

            // 初始化组件
            $this->initComponents();

            // 获取或创建会话
            if (!$sessionId) {
                $sessionId = $this->sessionManager->create(
                    $this->agentId,
                    $userId,
                    $options['user_type'] ?? 'guest',
                    $userName
                );
            }

            // 知识库检索（如启用）
            $knowledgeContext = '';
            if ($this->getConfig('knowledge_base_enabled', true)) {
                $relatedDocs = $this->knowledgeBase->search($this->agentId, $input, 5);
                $knowledgeContext = $this->buildKnowledgeContext($relatedDocs);
            }

            // 构建消息
            $messages = $this->buildMessages($sessionId, $input, $knowledgeContext);

            // 调用 AI
            $response = $this->provider->chat([
                'model' => $this->getConfig('model', 'glm-4-flash'),
                'messages' => $messages,
                'temperature' => $this->getConfig('temperature', 0.7),
                'max_tokens' => $this->getConfig('max_tokens', 2000),
            ]);

            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $content = $response['choices'][0]['message']['content'] ?? '';
            $tokens = $response['usage']['total_tokens'] ?? 0;

            // 保存到会话
            $this->sessionManager->addUserMessage($sessionId, $input);
            $this->sessionManager->addAssistantMessage($sessionId, $content);

            // 记录日志
            $this->log([
                'session_id' => $sessionId,
                'prompt' => $input,
                'content' => $input,
                'result' => ['message' => $content],
                'status' => 1,
                'tokens' => $tokens,
                'duration' => $duration,
            ]);

            return AgentResult::success($content, [
                'session_id' => $sessionId,
                'knowledge_used' => !empty($knowledgeContext),
            ], $tokens, $duration);
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $this->log([
                'session_id' => $options['session_id'] ?? null,
                'prompt' => $input,
                'content' => is_string($input) ? $input : json_encode($input),
                'status' => 0,
                'error_message' => $e->getMessage(),
                'duration' => $duration,
            ]);

            return AgentResult::fail($e->getMessage());
        }
    }

    /**
     * 流式执行
     */
    public function executeStream(mixed $input, callable $onChunk, array $options = []): AgentResult
    {
        $startTime = microtime(true);

        try {
            if (!$this->validateInput($input)) {
                return AgentResult::fail('内容不能为空');
            }

            $this->initComponents();

            $sessionId = $options['session_id'] ?? null;
            if (!$sessionId) {
                $sessionId = $this->sessionManager->create(
                    $this->agentId,
                    $options['user_id'] ?? null,
                    $options['user_type'] ?? 'guest',
                    $options['user_name'] ?? null
                );
            }

            // 知识库检索
            $knowledgeContext = '';
            if ($this->getConfig('knowledge_base_enabled', true)) {
                $relatedDocs = $this->knowledgeBase->search($this->agentId, $input, 3);
                $knowledgeContext = $this->buildKnowledgeContext($relatedDocs);
            }

            // 构建消息
            $messages = $this->buildMessages($sessionId, $input, $knowledgeContext);

            // 流式调用
            $fullContent = '';
            $this->provider->streamChat([
                'model' => $this->getConfig('model', 'glm-4-flash'),
                'messages' => $messages,
                'temperature' => $this->getConfig('temperature', 0.7),
                'max_tokens' => $this->getConfig('max_tokens', 2000),
            ], function ($chunk) use (&$fullContent, $onChunk) {
                $content = $chunk['choices'][0]['delta']['content'] ?? '';
                $fullContent .= $content;
                $onChunk($content);
            });

            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $tokens = $this->estimateTokens($fullContent);

            // 保存会话
            $this->sessionManager->addUserMessage($sessionId, $input);
            $this->sessionManager->addAssistantMessage($sessionId, $fullContent);

            // 记录日志
            $this->log([
                'session_id' => $sessionId,
                'prompt' => $input,
                'content' => $input,
                'result' => ['message' => $fullContent],
                'status' => 1,
                'tokens' => $tokens,
                'duration' => $duration,
            ]);

            return AgentResult::success($fullContent, [
                'session_id' => $sessionId,
                'knowledge_used' => !empty($knowledgeContext),
            ], $tokens, $duration);
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $this->log([
                'session_id' => $options['session_id'] ?? null,
                'prompt' => $input,
                'content' => is_string($input) ? $input : json_encode($input),
                'status' => 0,
                'error_message' => $e->getMessage(),
                'duration' => $duration,
            ]);

            return AgentResult::fail($e->getMessage());
        }
    }

    /**
     * 验证输入
     */
    public function validateInput(mixed $input): bool
    {
        if (is_string($input)) {
            return !empty(trim($input));
        }

        if (is_array($input)) {
            return !empty($input['message'] ?? '');
        }

        return false;
    }

    /**
     * 初始化组件
     */
    protected function initComponents(): void
    {
        $sessionService = make(\App\Service\Admin\AiAgentSessionService::class);
        $knowledgeService = make(\App\Service\Admin\AiAgentKnowledgeService::class);

        $this->sessionManager = new SessionManager($sessionService);
        $this->knowledgeBase = new KnowledgeBase($knowledgeService);
    }

    /**
     * 构建知识库上下文
     */
    protected function buildKnowledgeContext(array $docs): string
    {
        if (empty($docs)) {
            return '';
        }

        $context = "以下是相关知识库内容：\n\n";
        foreach ($docs as $doc) {
            $context .= "【{$doc['title']}】\n{$doc['content']}\n\n";
        }
        $context .= "请根据以上知识库内容回答用户问题。\n";

        return $context;
    }

    /**
     * 构建消息列表
     */
    protected function buildMessages(string $sessionId, string $currentInput, string $knowledgeContext = ''): array
    {
        $messages = [];

        // System prompt
        $systemPrompt = $this->getConfig('system_prompt', $this->getDefaultSystemPrompt());
        if ($knowledgeContext) {
            $systemPrompt .= "\n\n" . $knowledgeContext;
        }
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        // 历史消息
        $maxMessages = $this->getConfig('max_context_messages', 10);
        $history = $this->sessionManager->getContext($sessionId);

        $historyMessages = array_slice($history, -$maxMessages);
        foreach ($historyMessages as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        // 当前输入
        $messages[] = ['role' => 'user', 'content' => $currentInput];

        return $messages;
    }

    /**
     * 默认系统提示词
     */
    protected function getDefaultSystemPrompt(): string
    {
        return <<<'PROMPT'
你是一个专业、友好的客服助手。请根据知识库内容准确回答用户问题。
如果知识库中没有相关信息，请如实告知用户，并建议用户提供更多细节。
保持回答简洁、专业、有礼貌。
PROMPT;
    }
}
