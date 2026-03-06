<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Addons\SimpleBlog\Service;

use Addons\SimpleBlog\Model\SimpleBlogPost;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Paginator\Paginator;

/**
 * 语义搜索向量服务
 *
 * 支持配置语义搜索服务器，统一使用 /v1/embeddings 端点
 */
class VectorService
{
    /**
     * 向量维度配置（默认值，会被实际响应覆盖）
     */
    public const DEFAULT_DIMENSION = 384;

    /**
     * Embedding API 端点
     */
    private const EMBEDDING_ENDPOINT = '/v1/embeddings';

    /**
     * Embedding 请求超时时间（秒）
     */
    private const TIMEOUT = 30;

    /**
     * @var ClientFactory
     */
    private ClientFactory $clientFactory;

    public function __construct(ClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
        logger()->info('[VectorService] 服务初始化完成');
    }

    /**
     * 获取 HTTP 客户端
     */
    private function getClient(): \GuzzleHttp\Client
    {
        return $this->clientFactory->create([
            'timeout' => self::TIMEOUT,
        ]);
    }

    /**
     * 检查语义搜索是否已配置
     */
    public function isEnabled(): bool
    {
        $serverUrl = $this->getServerUrl();

        return !empty($serverUrl);
    }

    /**
     * 获取语义搜索服务器地址
     */
    public function getServerUrl(): ?string
    {
        $url = addons_config('SimpleBlog', 'semantic_search_engine');
        logger()->debug('[VectorService] 获取语义搜索服务器地址', ['url' => $url]);
        return $url;
    }

    /**
     * 获取 Embedding API 的完整 URL
     */
    public function getEmbeddingUrl(): string
    {
        $serverUrl = $this->getServerUrl();

        // 移除末尾的斜杠
        $serverUrl = rtrim($serverUrl, '/');

        return $serverUrl . self::EMBEDDING_ENDPOINT;
    }

    /**
     * 生成文本的向量表示
     *
     * @param string $text 输入文本
     * @return array{vector: array, dimension: int}|null
     */
    public function generateEmbedding(string $text): ?array
    {
        if (! $this->isEnabled()) {
            logger()->info('[VectorService] 语义搜索未配置，跳过向量生成');
            return null;
        }

        $textLength = mb_strlen($text);
        logger()->info('[VectorService] 开始生成向量', ['text_length' => $textLength]);

        try {
            $url = $this->getEmbeddingUrl();
            logger()->debug('[VectorService] 发送 Embedding 请求', ['url' => $url]);

            $response = $this->getClient()->post($url, [
                'input' => $text,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('Failed to parse embedding response', [
                    'url' => $url,
                    'response' => $response->getBody()->getContents(),
                ]);

                return null;
            }

            // 检查响应格式
            if (! isset($data['data'][0]['embedding'])) {
                $this->logError('Invalid embedding response format', $data);

                return null;
            }

            $dimension = count($data['data'][0]['embedding']);
            logger()->info('[VectorService] 向量生成成功', [
                'dimension' => $dimension,
                'text_length' => $textLength,
            ]);

            return [
                'vector' => $data['data'][0]['embedding'],
                'dimension' => $dimension,
            ];
        } catch (\Throwable $e) {
            $this->logError('Failed to generate embedding', [
                'error' => $e->getMessage(),
                'url' => $url ?? null,
            ]);

            return null;
        }

        return null;
    }

    /**
     * 批量生成文本的向量表示
     *
     * @param array $texts 输入文本数组
     * @return array Array of vectors, null if failed
     */
    public function generateEmbeddings(array $texts): ?array
    {
        if (! $this->isEnabled()) {
            logger()->info('[VectorService] 语义搜索未配置，跳过批量向量生成');
            return null;
        }

        if (empty($texts)) {
            logger()->info('[VectorService] 批量向量生成：输入为空');
            return [];
        }

        $count = count($texts);
        logger()->info('[VectorService] 开始批量生成向量', ['count' => $count]);

        try {
            $url = $this->getEmbeddingUrl();

            $response = $this->getClient()->post($url, [
                'input' => $texts,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('Failed to parse batch embedding response', [
                    'url' => $url,
                    'response' => $response->getBody()->getContents(),
                ]);

                return null;
            }

            $embeddings = [];
            foreach ($data['data'] ?? [] as $item) {
                if (isset($item['embedding'])) {
                    $embeddings[$item['index'] ?? count($embeddings)] = $item['embedding'];
                }
            }

            $successCount = count($embeddings);
            logger()->info('[VectorService] 批量向量生成完成', [
                'total' => $count,
                'success' => $successCount,
            ]);

            return $embeddings;
        } catch (\Throwable $e) {
            $this->logError('Failed to generate batch embeddings', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return null;
    }

    /**
     * 语义搜索文章
     *
     * @param string $query 搜索查询
     * @param array $params 搜索参数
     * @return Paginator 搜索结果
     */
    public function semanticSearch(string $query, array $params = []): Paginator
    {
        if (! $this->isEnabled()) {
            logger()->info('[VectorService] 语义搜索未配置');
            return new Paginator([], 0, $params['per_page'] ?? 10);
        }

        logger()->info('[VectorService] 开始语义搜索', [
            'query' => mb_strlen($query) > 100 ? mb_substr($query, 0, 100) . '...' : $query,
            'params' => $params,
        ]);

        try {
            // 生成查询向量
            $queryVector = $this->generateEmbedding($query);

            if ($queryVector === null) {
                logger()->warning('[VectorService] 查询向量生成失败');
                return new Paginator([], 0, $params['per_page'] ?? 10);
            }

            logger()->debug('[VectorService] 查询向量生成成功', [
                'dimension' => $queryVector['dimension'],
            ]);

            // 获取所有已发布文章的向量
            $posts = SimpleBlogPost::published()
                ->whereNotNull('title_embedding')
                ->orWhereNotNull('content_embedding')
                ->get();

            if ($posts->isEmpty()) {
                logger()->info('[VectorService] 没有找到带有向量的文章');
                return new Paginator([], 0, $params['per_page'] ?? 10);
            }

            logger()->debug('[VectorService] 获取到带向量的文章数', ['count' => $posts->count()]);

            // 计算余弦相似度
            $results = [];
            foreach ($posts as $post) {
                // 优先使用内容向量
                $postEmbedding = $post->content_embedding ?? $post->title_embedding;
                if (! is_array($postEmbedding)) {
                    continue;
                }

                $similarity = $this->cosineSimilarity(
                    $queryVector['vector'],
                    $postEmbedding
                );

                $results[] = [
                    'post' => $post,
                    'similarity' => $similarity,
                ];
            }

            // 按相似度排序
            usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

            // 获取前 N 个结果
            $limit = $params['limit'] ?? 10;
            $results = array_slice($results, 0, $limit);

            // 提取文章
            $resultPosts = array_map(fn($r) => $r['post'], $results);

            logger()->info('[VectorService] 语义搜索完成', [
                'query_length' => mb_strlen($query),
                'total_posts' => $posts->count(),
                'result_count' => count($resultPosts),
                'top_similarity' => $results[0]['similarity'] ?? 0,
            ]);

            // 构建分页器
            $page = $params['page'] ?? 1;
            $perPage = $params['per_page'] ?? 10;

            return new Paginator($resultPosts, $perPage, $page);
        } catch (\Throwable $e) {
            $this->logError('Semantic search failed', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            return new Paginator([], 0, $params['per_page'] ?? 10);
        }
    }

    /**
     * 为文章生成并保存向量
     */
    public function generateAndSavePostEmbedding(int $postId): bool
    {
        $post = SimpleBlogPost::find($postId);

        if (! $post) {
            logger()->warning('[VectorService] 文章不存在', ['post_id' => $postId]);
            return false;
        }

        logger()->info('[VectorService] 开始为文章生成向量', [
            'post_id' => $postId,
            'title_length' => mb_strlen($post->title),
            'content_length' => mb_strlen(strip_tags($post->content)),
        ]);

        // 生成标题向量
        $titleEmbedding = null;
        if (! empty($post->title)) {
            $titleEmbedding = $this->generateEmbedding($post->title);
        }

        // 生成内容向量
        $contentEmbedding = null;
        $contentText = trim(strip_tags($post->content));
        if (! empty($contentText)) {
            $contentEmbedding = $this->generateEmbedding($contentText);
        }

        $updateData = [];
        if ($titleEmbedding !== null) {
            $updateData['title_embedding'] = $titleEmbedding['vector'];
        }
        if ($contentEmbedding !== null) {
            $updateData['content_embedding'] = $contentEmbedding['vector'];
        }

        if (empty($updateData)) {
            logger()->warning('[VectorService] 文章向量生成失败，无有效内容', ['post_id' => $postId]);
            return false;
        }

        // 使用 withoutEvents 避免触发循环
        $result = $post->withoutEvents(function () use ($post, $updateData) {
            return $post->update($updateData);
        });

        logger()->info('[VectorService] 文章向量生成完成', [
            'post_id' => $postId,
            'has_title_embedding' => isset($updateData['title_embedding']),
            'has_content_embedding' => isset($updateData['content_embedding']),
            'success' => $result,
        ]);

        return $result;
    }

    /**
     * 为所有文章批量生成向量
     */
    public function generateAllPostEmbeddings(): array
    {
        if (! $this->isEnabled()) {
            logger()->info('[VectorService] 语义搜索未配置，跳过批量生成');
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }

        $posts = SimpleBlogPost::all();
        $total = $posts->count();

        logger()->info('[VectorService] 开始为所有文章生成向量', ['total' => $total]);

        $success = 0;
        $failed = 0;

        foreach ($posts as $post) {
            if ($this->generateAndSavePostEmbedding($post->id)) {
                $success++;
            } else {
                $failed++;
            }
        }

        logger()->info('[VectorService] 所有文章向量生成完成', [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
        ]);

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
        ];
    }

    /**
     * 计算两个向量的余弦相似度
     */
    public function cosineSimilarity(array $vector1, array $vector2): float
    {
        if (count($vector1) !== count($vector2)) {
            return 0.0;
        }

        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $norm1 += $vector1[$i] * $vector1[$i];
            $norm2 += $vector2[$i] * $vector2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 === 0 || $norm2 === 0) {
            return 0.0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }

    /**
     * 测试语义搜索服务器连接
     *
     * @return array{success: bool, message: string, details?: array}
     */
    public function testConnection(): array
    {
        $serverUrl = $this->getServerUrl();

        if (empty($serverUrl)) {
            return [
                'success' => false,
                'message' => '语义搜索服务器未配置',
            ];
        }

        try {
            $url = $this->getEmbeddingUrl();

            // 发送测试请求
            $response = $this->getClient()->post($url, [
                'input' => 'Hello world',
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'message' => '无法解析服务器响应',
                    'details' => [
                        'status_code' => $response->getStatusCode(),
                    ],
                ];
            }

            if (! isset($data['data'][0]['embedding'])) {
                return [
                    'success' => false,
                    'message' => '服务器返回格式不正确',
                    'details' => $data,
                ];
            }

            $dimension = count($data['data'][0]['embedding']);

            return [
                'success' => true,
                'message' => '连接成功',
                'details' => [
                    'dimension' => $dimension,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => '连接失败: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 记录错误日志
     */
    private function logError(string $message, array $context = []): void
    {
        logger()->warning('[VectorService] ' . $message, $context);
    }
}
