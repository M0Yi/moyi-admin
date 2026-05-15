<?php
/**
 * 查询加入我们分类信息
 */

require __DIR__ . '/vendor/autoload.php';

// 运行Hyperf应用
$app = Hyperf\ApplicationFactory::create()->getApplication();

// 在协程环境中运行
Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

\Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

co::run(function () {
    try {
        echo "=== 查找'加入我们'分类 ===\n\n";

        // 查找所有顶级分类
        $categories = Hyperf\DbConnection\Db::table('jianhui_org_categories')
            ->where('type', 'article')
            ->where('parent_id', 0)
            ->where('is_active', true)
            ->get();

        echo "所有顶级文章分类:\n";
        foreach ($categories as $cat) {
            echo sprintf("  ID: %d | Name: %s | Slug: %s\n", $cat->id, $cat->name, $cat->slug);

            // 如果可能是加入我们，显示子分类
            if (strpos($cat->name, '加入') !== false || strpos($cat->slug, 'join') !== false) {
                echo "  -> 可能是加入我们分类！\n";

                $children = Hyperf\DbConnection\Db::table('jianhui_org_categories')
                    ->where('parent_id', $cat->id)
                    ->where('is_active', true)
                    ->get();

                if ($children->count() > 0) {
                    echo "  子分类:\n";
                    foreach ($children as $child) {
                        echo sprintf("     - %s (slug: %s, ID: %d)\n", $child->name, $child->slug, $child->id);
                    }
                }
            }
        }

        echo "\n查找结果:\n";
        $joinUsCategory = Hyperf\DbConnection\Db::table('jianhui_org_categories')
            ->where('type', 'article')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('name', 'like', '%加入%')
                      ->orWhere('slug', 'like', '%join%')
                      ->orWhere('slug', 'like', '%us%');
            })
            ->first();

        if ($joinUsCategory) {
            echo sprintf("找到加入我们分类:\n  ID: %d\n  Name: %s\n  Slug: %s\n",
                $joinUsCategory->id,
                $joinUsCategory->name,
                $joinUsCategory->slug
            );
        } else {
            echo "未找到加入我们分类\n";
        }

    } catch (\Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
        echo "堆栈: " . $e->getTraceAsString() . "\n";
    }
});

echo "脚本执行完成\n";
