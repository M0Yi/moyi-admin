<?php

declare(strict_types=1);

namespace App\Service\AiAgent\Provider;

use Hyperf\Context\Context;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Contract\ConfigInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 智谱 AI Provider 实现
 */
class ZhipuProvider implements ProviderInterface
{
    /**
     * 默认基础 URL
     */
    protected const DEFAULT_BASE_URL = 'https://open.bigmodel.cn/api/paas/v4';

    /**
     * 默认模型
     */
    protected const DEFAULT_MODEL = 'glm-4-flash';

    /**
     * 超时时间（秒）
     */
    protected const TIMEOUT = 30;

    protected string $apiKey = '';

    protected string $baseUrl = '';

    protected string $model = self::DEFAULT_MODEL;

    protected int $timeout = self::TIMEOUT;

    protected ?int $siteId = null;

    public function __construct(
        protected ConfigInterface $config
    ) {
        $this->baseUrl = $this->config->get('site.ai.base_url', self::DEFAULT_BASE_URL);
    }

    /**
     * 获取 Provider 标识
     */
    public function getSlug(): string
    {
        return 'zhipu';
    }

    /**
     * 获取 Provider 名称
     */
    public function getName(): string
    {
        return '智谱 AI';
    }

    /**
     * 发送聊天请求
     */
    public function chat(array $params): array
    {
        $url = $this->baseUrl . '/chat/completions';

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        $body = [
            'model' => $params['model'] ?? $this->model,
            'messages' => $params['messages'] ?? [],
            'temperature' => $params['temperature'] ?? 0.7,
            'max_tokens' => $params['max_tokens'] ?? 2000,
            'stream' => false,
        ];

        // 如果有 tools 参数，添加
        if (isset($params['tools'])) {
            $body['tools'] = $params['tools'];
        }

        $client = $this->getClient();
        $response = $client->post($url, [
            'headers' => $headers,
            'json' => $body,
        ]);

        $content = $response->getBody()->getContents();
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse AI response: ' . json_last_error_msg());
        }

        // 检查错误
        if (isset($data['error'])) {
            throw new \RuntimeException($data['error']['message'] ?? 'AI request failed');
        }

        return $data;
    }

    /**
     * 流式发送聊天请求
     */
    public function streamChat(array $params, callable $onChunk): void
    {
        $url = $this->baseUrl . '/chat/completions';

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        $body = [
            'model' => $params['model'] ?? $this->model,
            'messages' => $params['messages'] ?? [],
            'temperature' => $params['temperature'] ?? 0.7,
            'max_tokens' => $params['max_tokens'] ?? 2000,
            'stream' => true,
        ];

        $client = $this->getClient();

        $response = $client->post($url, [
            'headers' => $headers,
            'json' => $body,
        ]);

        $body = $response->getBody();

        while (!$body->eof()) {
            $line = $body->read(1024);
            if ($line) {
                $line = trim($line);
                if (str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6);
                    if ($data === '[DONE]') {
                        break;
                    }
                    $chunk = json_decode($data, true);
                    if ($chunk) {
                        $onChunk($chunk);
                    }
                }
            }
        }
    }

    /**
     * 获取可用模型列表
     */
    public function getModels(): array
    {
        return [
            ['id' => 'glm-4-flash', 'name' => 'GLM-4-Flash', 'description' => '免费快速'],
            ['id' => 'glm-4', 'name' => 'GLM-4', 'description' => '高性能'],
            ['id' => 'glm-3-turbo', 'name' => 'GLM-3-Turbo', 'description' => '轻量快速'],
            ['id' => 'glm-z1-flash', 'name' => 'GLM-Z1-Flash', 'description' => '最新高性能'],
        ];
    }

    /**
     * 设置 API Key
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * 设置基础 URL
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * 设置模型
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * 设置站点 ID（用于获取站点配置）
     */
    public function setSiteId(?int $siteId): self
    {
        $this->siteId = $siteId;
        return $this;
    }

    /**
     * 从站点配置初始化
     */
    public function initFromSite(?int $siteId = null): self
    {
        $siteId = $siteId ?? $this->getCurrentSiteId();

        if ($siteId) {
            // 从站点配置获取 AI 配置
            $site = \App\Model\Admin\AdminSite::find($siteId);
            if ($site) {
                $aiConfig = $site->getAiConfig();
                if (!empty($aiConfig['token'])) {
                    $this->apiKey = $aiConfig['token'];
                }
                if (!empty($aiConfig['base_url'])) {
                    $this->baseUrl = $aiConfig['base_url'];
                }
                if (!empty($aiConfig['text_model'])) {
                    $this->model = $aiConfig['text_model'];
                }
            }
        }

        return $this;
    }

    /**
     * 获取 Guzzle 客户端
     */
    protected function getClient(): \GuzzleHttp\Client
    {
        $clientFactory = Context::get(\Hyperf\Guzzle\ClientFactory::class);
        return $clientFactory->create([
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * 获取当前站点 ID
     */
    protected function getCurrentSiteId(): ?int
    {
        return Context::get('site_id');
    }
}
