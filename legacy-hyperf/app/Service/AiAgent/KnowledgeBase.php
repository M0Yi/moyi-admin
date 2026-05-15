<?php

declare(strict_types=1);

namespace App\Service\AiAgent;

use App\Model\Admin\AiAgentKnowledge;

/**
 * 知识库系统（简化版）
 *
 * 使用关键词匹配 + 全文索引，不使用向量搜索
 */
class KnowledgeBase
{
    public function __construct(
        protected \App\Service\Admin\AiAgentKnowledgeService $knowledgeService
    ) {}

    /**
     * 搜索相关文档
     *
     * 使用 MySQL 全文索引 + LIKE 匹配
     */
    public function search(int $agentId, string $query, int $limit = 5): array
    {
        return AiAgentKnowledge::query()
            ->where('agent_id', $agentId)
            ->where('status', 1)
            ->where(function ($q) use ($query) {
                // 全文索引匹配
                try {
                    $q->whereRaw("MATCH(title, content) AGAINST(? IN BOOLEAN MODE)", [$query]);
                } catch (\Throwable $e) {
                    // 如果全文索引不可用，使用 LIKE
                }
                // LIKE 兜底匹配
                $q->orWhere('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            })
            ->orderBy('sort', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 构建上下文（Prompt 注入）
     *
     * 将检索到的文档拼接成上下文，注入到 AI Prompt 中
     */
    public function buildContext(int $agentId, string $query): string
    {
        $docs = $this->search($agentId, $query, 5);

        if (empty($docs)) {
            return '';
        }

        $context = "以下是相关知识库内容，请根据这些信息回答用户问题：\n\n";

        foreach ($docs as $index => $doc) {
            $context .= "【文档 " . ($index + 1) . "：{$doc['title']}】\n";
            $context .= $doc['content'] . "\n\n";
        }

        $context .= "如果知识库中没有相关信息，请如实告知用户。\n";

        return $context;
    }

    /**
     * 添加文档
     */
    public function addDocument(int $agentId, array $data): AiAgentKnowledge
    {
        // 自动提取关键词
        $keywords = $this->extractKeywords($data['title'] . ' ' . ($data['content'] ?? ''));

        return $this->knowledgeService->create([
            'agent_id' => $agentId,
            'title' => $data['title'],
            'content' => $data['content'],
            'keywords' => json_encode($keywords),
            'status' => $data['status'] ?? 1,
            'category_id' => $data['category_id'] ?? null,
            'sort' => $data['sort'] ?? 0,
        ]);
    }

    /**
     * 更新文档
     */
    public function updateDocument(int $id, array $data): bool
    {
        $keywords = $this->extractKeywords(($data['title'] ?? '') . ' ' . ($data['content'] ?? ''));
        $data['keywords'] = json_encode($keywords);

        return $this->knowledgeService->update($id, $data);
    }

    /**
     * 批量导入文档
     */
    public function batchImport(int $agentId, array $documents): array
    {
        $successCount = 0;
        $errors = [];

        foreach ($documents as $index => $doc) {
            try {
                if (empty($doc['title']) || empty($doc['content'])) {
                    $errors[] = "文档 #{$index}: 标题和内容不能为空";
                    continue;
                }

                $this->addDocument($agentId, $doc);
                $successCount++;
            } catch (\Throwable $e) {
                $errors[] = "文档 #{$index}: " . $e->getMessage();
            }
        }

        return [
            'success' => $successCount,
            'total' => count($documents),
            'errors' => $errors,
        ];
    }

    /**
     * 简单关键词提取
     */
    protected function extractKeywords(string $content): array
    {
        if (empty($content)) {
            return [];
        }

        // 简单实现：提取 2-4 个字的词汇
        preg_match_all('/[\x{4e00}-\x{9fa5}]{2,4}/u', $content, $matches);

        if (empty($matches[0])) {
            return [];
        }

        // 去重并返回前 10 个
        $keywords = array_unique($matches[0]);

        return array_slice(array_values($keywords), 0, 10);
    }
}
