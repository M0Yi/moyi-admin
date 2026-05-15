<?php

declare(strict_types=1);

namespace App\Service\AiAgent;

use App\Model\Admin\AiAgentTool;

/**
 * 工具注册器
 */
class ToolRegistry
{
    /**
     * 注册的工具实例
     */
    protected static array $tools = [];

    /**
     * 注册工具
     */
    public static function register(string $slug, ToolInterface $tool): void
    {
        static::$tools[$slug] = $tool;
    }

    /**
     * 获取工具
     */
    public static function get(string $slug): ?ToolInterface
    {
        return static::$tools[$slug] ?? null;
    }

    /**
     * 获取可用工具列表（从数据库加载）
     */
    public static function getTools(int $agentId): array
    {
        $tools = AiAgentTool::query()
            ->where('agent_id', $agentId)
            ->where('is_enabled', 1)
            ->orderBy('sort', 'asc')
            ->get();

        $result = [];
        foreach ($tools as $toolModel) {
            $slug = $toolModel->slug;

            // 优先从缓存获取
            if (!isset(static::$tools[$slug])) {
                // 尝试实例化工具类
                $class = $toolModel->class;
                if (class_exists($class)) {
                    try {
                        $tool = make($class);
                        if ($tool instanceof ToolInterface) {
                            static::$tools[$slug] = $tool;
                        }
                    } catch (\Throwable $e) {
                        logger()->error('[ToolRegistry] Failed to create tool: ' . $e->getMessage(), [
                            'class' => $class,
                            'slug' => $slug,
                        ]);
                    }
                }
            }

            if (isset(static::$tools[$slug])) {
                $result[] = [
                    'slug' => $slug,
                    'name' => $toolModel->name,
                    'description' => $toolModel->description,
                    'tool' => static::$tools[$slug],
                ];
            }
        }

        return $result;
    }

    /**
     * 获取工具的 JSON Schema（用于 AI 函数调用）
     */
    public static function getToolsSchema(int $agentId): array
    {
        $tools = static::getTools($agentId);

        $schemas = [];
        foreach ($tools as $tool) {
            /** @var ToolInterface $toolInstance */
            $toolInstance = $tool['tool'];

            $schemas[] = [
                'type' => 'function',
                'function' => [
                    'name' => $toolInstance->getName(),
                    'description' => $toolInstance->getDescription(),
                    'parameters' => $toolInstance->getParameters(),
                ],
            ];
        }

        return $schemas;
    }

    /**
     * 执行工具
     */
    public static function execute(string $slug, array $params): mixed
    {
        $tool = static::get($slug);

        if (!$tool) {
            throw new \RuntimeException("Tool {$slug} not found");
        }

        return $tool->execute($params);
    }

    /**
     * 执行工具（从数据库加载）
     */
    public static function executeFromDb(int $agentId, string $slug, array $params): mixed
    {
        $toolModel = AiAgentTool::query()
            ->where('agent_id', $agentId)
            ->where('slug', $slug)
            ->where('is_enabled', 1)
            ->first();

        if (!$toolModel) {
            throw new \RuntimeException("Tool {$slug} not found or disabled");
        }

        $class = $toolModel->class;
        if (!class_exists($class)) {
            throw new \RuntimeException("Tool class {$class} not found");
        }

        $tool = make($class);
        if (!($tool instanceof ToolInterface)) {
            throw new \RuntimeException("Tool class must implement ToolInterface");
        }

        return $tool->execute($params);
    }
}
