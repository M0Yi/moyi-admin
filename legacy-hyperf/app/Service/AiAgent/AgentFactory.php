<?php

declare(strict_types=1);

namespace App\Service\AiAgent;

use App\Model\Admin\AiAgent as AiAgentModel;
use App\Service\AiAgent\Provider\ProviderInterface;
use App\Service\AiAgent\Provider\ZhipuProvider;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;

/**
 * AI Agent 工厂
 */
class AgentFactory
{
    /**
     * 注册的 Agent 类
     */
    protected static array $agents = [];

    /**
     * Provider 实例缓存
     */
    protected static array $providers = [];

    /**
     * 注册 Agent
     */
    public static function register(string $slug, string $class): void
    {
        static::$agents[$slug] = $class;
    }

    /**
     * 获取 Agent 实例
     *
     * @param string|int $identifier Agent slug 或 ID
     */
    public static function get(string|int $identifier): ?AgentInterface
    {
        // 如果是数字，查找 Agent 模型
        if (is_numeric($identifier)) {
            $agentModel = AiAgentModel::find($identifier);
            if (!$agentModel) {
                return null;
            }
            return static::createFromModel($agentModel);
        }

        // 如果是 slug，查找 Agent 模型
        $agentModel = AiAgentModel::where('slug', $identifier)->first();
        if (!$agentModel) {
            return null;
        }

        return static::createFromModel($agentModel);
    }

    /**
     * 从模型创建 Agent 实例
     */
    public static function createFromModel(AiAgentModel $agentModel): AgentInterface
    {
        $class = $agentModel->class;

        // 检查是否已注册
        if (!isset(static::$agents[$agentModel->slug])) {
            // 如果未注册，尝试直接使用类名
            if (!class_exists($class)) {
                throw new \RuntimeException("Agent class {$class} not found");
            }
        } else {
            $class = static::$agents[$agentModel->slug];
        }

        /** @var AgentInterface $agent */
        $agent = make($class);
        $agent->setAgentModel($agentModel);

        // 设置 Provider
        $provider = static::getProvider($agentModel->site_id);
        $agent->setProvider($provider);

        return $agent;
    }

    /**
     * 获取默认 Provider
     */
    public static function getProvider(?int $siteId = null): ProviderInterface
    {
        $siteId = $siteId ?? Context::get('site_id') ?? 1;
        $key = 'site_' . $siteId;

        if (!isset(static::$providers[$key])) {
            $provider = make(ZhipuProvider::class);
            $provider->initFromSite($siteId);
            static::$providers[$key] = $provider;
        }

        return static::$providers[$key];
    }

    /**
     * 设置 Provider
     */
    public static function setProvider(ProviderInterface $provider, ?int $siteId = null): void
    {
        $siteId = $siteId ?? Context::get('site_id') ?? 1;
        $key = 'site_' . $siteId;
        static::$providers[$key] = $provider;
    }

    /**
     * 获取默认 Agent
     */
    public static function getDefault(?int $siteId = null): ?AgentInterface
    {
        $siteId = $siteId ?? Context::get('site_id');

        $query = AiAgentModel::query()
            ->where('is_default', 1)
            ->where('status', 1);

        if ($siteId) {
            $query->where(function ($q) use ($siteId) {
                $q->where('site_id', $siteId)
                    ->orWhereNull('site_id');
            });
        }

        $agentModel = $query->first();

        if (!$agentModel) {
            return null;
        }

        return static::createFromModel($agentModel);
    }

    /**
     * 根据类型获取 Agent
     */
    public static function getByType(string $type, ?int $siteId = null): ?AgentInterface
    {
        $siteId = $siteId ?? Context::get('site_id');

        $query = AiAgentModel::query()
            ->where('type', $type)
            ->where('status', 1);

        if ($siteId) {
            $query->where(function ($q) use ($siteId) {
                $q->where('site_id', $siteId)
                    ->orWhereNull('site_id');
            });
        }

        $agentModel = $query->first();

        if (!$agentModel) {
            return null;
        }

        return static::createFromModel($agentModel);
    }

    /**
     * 获取所有可用的 Agent
     */
    public static function all(?int $siteId = null): array
    {
        $siteId = $siteId ?? Context::get('site_id');

        $query = AiAgentModel::query()
            ->where('status', 1);

        if ($siteId) {
            $query->where(function ($q) use ($siteId) {
                $q->where('site_id', $siteId)
                    ->orWhereNull('site_id');
            });
        }

        return $query->get()
            ->map(fn($model) => static::createFromModel($model))
            ->toArray();
    }
}
