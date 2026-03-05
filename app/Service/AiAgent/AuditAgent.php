<?php

declare(strict_types=1);

namespace App\Service\AiAgent;

/**
 * 内容审核 Agent
 *
 * 用于审核文本内容的安全性
 */
class AuditAgent extends BaseAgent implements AgentInterface
{
    /**
     * Agent 类型
     */
    public const TYPE = 'audit';

    /**
     * 审核结果：通过
     */
    public const RESULT_PASS = 'pass';

    /**
     * 审核结果：疑似违规
     */
    public const RESULT_SUSPICIOUS = 'suspicious';

    /**
     * 审核结果：违规
     */
    public const RESULT_REJECT = 'reject';

    /**
     * 审核项目配置
     */
    protected array $checkItems = [
        'politics' => true,   // 政治敏感
        'porn' => true,       // 色情内容
        'violence' => true,   // 暴力内容
        'ad' => true,         // 广告内容
    ];

    /**
     * 敏感度：low, medium, high
     */
    protected string $sensitivity = 'medium';

    public function __construct()
    {
        $this->retryTimes = 3;
    }

    /**
     * 获取 Agent 标识
     */
    public function getSlug(): string
    {
        return 'audit-agent';
    }

    /**
     * 获取 Agent 名称
     */
    public function getName(): string
    {
        return '内容审核 Agent';
    }

    /**
     * 获取 Agent 类型
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 执行审核
     */
    public function execute(mixed $input, array $options = []): AgentResult
    {
        $startTime = microtime(true);

        try {
            // 验证输入
            if (!$this->validateInput($input)) {
                return AgentResult::fail('审核内容不能为空');
            }

            // 加载配置
            $this->loadConfig();

            // 构建审核提示词
            $prompt = $this->buildPrompt($input);

            // 调用 AI
            $response = $this->provider->chat([
                'model' => $this->getConfig('model', 'glm-4-flash'),
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSystemPrompt()],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ]);

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            // 解析结果
            $content = $response['choices'][0]['message']['content'] ?? '';
            $result = $this->parseResult($content);
            $tokens = $response['usage']['total_tokens'] ?? 0;

            // 记录日志
            $this->log([
                'prompt' => $prompt,
                'content' => is_string($input) ? $input : json_encode($input),
                'result' => $result,
                'status' => 1,
                'tokens' => $tokens,
                'duration' => $duration,
                'ip' => $this->getClientIp(),
                'user_agent' => $this->getUserAgent(),
            ]);

            return AgentResult::success('审核完成', $result, $tokens, $duration);
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            // 记录错误日志
            $this->log([
                'prompt' => $input,
                'content' => is_string($input) ? $input : json_encode($input),
                'status' => 0,
                'error_message' => $e->getMessage(),
                'duration' => $duration,
                'ip' => $this->getClientIp(),
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
            return !empty($input['content'] ?? '');
        }

        return false;
    }

    /**
     * 流式执行（审核不支持流式）
     */
    public function executeStream(mixed $input, array $options = [], callable $onChunk): AgentResult
    {
        // 审核不支持流式，直接调用 execute
        return $this->execute($input, $options);
    }

    /**
     * 加载配置
     */
    protected function loadConfig(): void
    {
        $this->sensitivity = $this->getConfig('sensitivity', 'medium');
        $this->checkItems = $this->getConfig('check_items', $this->checkItems);
    }

    /**
     * 获取系统提示词
     */
    protected function getSystemPrompt(): string
    {
        $sensitivityMap = [
            'low' => '宽松',
            'medium' => '中等',
            'high' => '严格',
        ];

        return <<<PROMPT
你是一个内容安全审核助手。请对用户提交的内容进行安全审核。

审核标准：{$sensitivityMap[$this->sensitivity] ?? '中等'}敏感度

审核项目：
1. 政治敏感内容 -政治不正确或有害的政治言论
2. 色情内容 -低俗、色情描写
3. 暴力内容 -暴力、血腥描述
4. 广告内容 -垃圾广告、推广信息

请对内容进行审核，并返回JSON格式结果：
{
    "result": "pass/suspicious/reject",
    "reason": "审核原因",
    "details": {
        "politics": {"result": "pass/reject", "reason": "原因"},
        "porn": {"result": "pass/reject", "reason": "原因"},
        "violence": {"result": "pass/reject", "reason": "原因"},
        "ad": {"result": "pass/reject", "reason": "原因"}
    }
}

只返回JSON，不要其他内容。
PROMPT;
    }

    /**
     * 构建审核提示词
     */
    protected function buildPrompt(mixed $input): string
    {
        $content = is_string($input) ? $input : ($input['content'] ?? '');

        $enabledItems = [];
        foreach ($this->checkItems as $item => $enabled) {
            if ($enabled) {
                $enabledItems[] = $item;
            }
        }

        $prompt = "请审核以下内容：\n\n" . $content . "\n\n";
        $prompt .= "需要审核的项目：" . implode('、', $enabledItems);

        return $prompt;
    }

    /**
     * 解析审核结果
     */
    protected function parseResult(string $content): array
    {
        // 提取 JSON
        $content = trim($content);
        if (str_starts_with($content, '```json')) {
            $content = substr($content, 7);
        }
        if (str_starts_with($content, '```')) {
            $content = substr($content, 3);
        }
        if (str_ends_with($content, '```')) {
            $content = substr($content, 0, -3);
        }
        $content = trim($content);

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // 解析失败，尝试其他方式
            return [
                'result' => self::RESULT_SUSPICIOUS,
                'reason' => '审核结果解析失败',
                'raw' => $content,
            ];
        }

        return $data;
    }
}
