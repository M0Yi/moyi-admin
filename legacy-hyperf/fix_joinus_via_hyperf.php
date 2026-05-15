<?php
/**
 * 通过Hyperf框架连接PostgreSQL数据库检查和修复"加入我们"分类
 */

require __DIR__ . '/vendor/autoload.php';

// 运行Hyperf应用
$app = Hyperf\ApplicationFactory::create()->getApplication();

// 在协程环境中运行
Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

\Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

co::run(function () {
    try {
        echo "=== 数据库连接成功 ===\n\n";

        // 查询所有顶级文章分类
        echo "1. 查询所有顶级文章分类:\n";
        $categories = Hyperf\DbConnection\Db::table('jianhui_org_categories')
            ->where('type', 'article')
            ->where('parent_id', 0)
            ->orderBy('id')
            ->get();

        foreach ($categories as $cat) {
            echo sprintf("  ID: %d | Name: %s | Slug: %s | Active: %s\n",
                $cat->id,
                $cat->name,
                $cat->slug ?? '(null)',
                $cat->is_active ? 'true' : 'false'
            );
        }

        echo "\n2. 查找'加入我们'相关分类:\n";
        $joinUsCategories = Hyperf\DbConnection\Db::table('jianhui_org_categories')
            ->where('type', 'article')
            ->where(function ($query) {
                $query->where('name', 'like', '%加入%')
                      ->orWhere('slug', 'like', '%join%')
                      ->orWhere('slug', 'like', '%us%');
            })
            ->get();

        $foundJoinUs = false;
        $joinUsId = null;

        foreach ($joinUsCategories as $cat) {
            echo sprintf("  ID: %d | Name: %s | Slug: %s | Parent: %d\n",
                $cat->id,
                $cat->name,
                $cat->slug ?? '(null)',
                $cat->parent_id
            );

            // 如果是顶级分类且名称包含"加入我们"
            if ($cat->parent_id == 0 && strpos($cat->name, '加入我们') !== false) {
                $foundJoinUs = true;
                $joinUsId = $cat->id;
                $currentSlug = $cat->slug;
                echo "  -> 这就是加入我们主分类！\n";
            }
        }

        if (!$foundJoinUs) {
            echo "  未找到'加入我们'分类，需要创建\n";

            // 获取当前最大的ID
            $maxId = Hyperf\DbConnection\Db::table('jianhui_org_categories')
                ->max('id') ?? 0;
            $newId = $maxId + 1;

            echo "\n3. 创建'加入我们'分类 (ID: {$newId})...\n";

            Hyperf\DbConnection\Db::table('jianhui_org_categories')->insert([
                'id' => $newId,
                'name' => '加入我们',
                'slug' => 'join_us',
                'type' => 'article',
                'parent_id' => 0,
                'is_active' => true,
                'sort_order' => $newId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            echo "  ✓ 创建成功！ID: {$newId}, Slug: join_us\n";
            $joinUsId = $newId;
        } else {
            echo "\n3. 检查并修复分类信息:\n";

            $category = Hyperf\DbConnection\Db::table('jianhui_org_categories')
                ->where('id', $joinUsId)
                ->first();

            $currentSlug = $category->slug;

            // 如果slug不对，更新它
            if ($currentSlug !== 'join_us') {
                echo "  当前Slug: {$currentSlug} (需要修改为: join_us)\n";

                Hyperf\DbConnection\Db::table('jianhui_org_categories')
                    ->where('id', $joinUsId)
                    ->update([
                        'slug' => 'join_us',
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                echo "  ✓ Slug已更新为: join_us\n";
            } else {
                echo "  Slug正确: join_us\n";
            }

            // 检查是否激活
            if (!$category->is_active) {
                echo "  激活分类...\n";
                Hyperf\DbConnection\Db::table('jianhui_org_categories')
                    ->where('id', $joinUsId)
                    ->update(['is_active' => true]);
                echo "  ✓ 分类已激活\n";
            }
        }

        // 检查是否有子分类
        echo "\n4. 检查子分类:\n";
        $children = Hyperf\DbConnection\Db::table('jianhui_org_categories')
            ->where('parent_id', $joinUsId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($children->isEmpty()) {
            echo "  没有子分类，创建默认子分类...\n";

            // 获取当前最大ID
            $maxChildId = Hyperf\DbConnection\Db::table('jianhui_org_categories')
                ->max('id') ?? 0;

            $subCategories = [
                ['name' => '志愿者招募', 'slug' => 'volunteer_recruitment'],
                ['name' => '全职岗位', 'slug' => 'fulltime_positions']
            ];

            foreach ($subCategories as $index => $sub) {
                $maxChildId++;
                Hyperf\DbConnection\Db::table('jianhui_org_categories')->insert([
                    'id' => $maxChildId,
                    'name' => $sub['name'],
                    'slug' => $sub['slug'],
                    'type' => 'article',
                    'parent_id' => $joinUsId,
                    'is_active' => true,
                    'sort_order' => $index,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                echo "  ✓ 创建子分类: {$sub['name']} (slug: {$sub['slug']}, ID: {$maxChildId})\n";
            }
        } else {
            echo "  现有子分类:\n";
            foreach ($children as $child) {
                echo sprintf("    - %s (slug: %s, ID: %d)\n",
                    $child->name,
                    $child->slug ?? '(null)',
                    $child->id
                );
            }
        }

        echo "\n=== 修复完成 ===\n";
        echo "加入我们分类ID: {$joinUsId}\n";
        echo "前端应使用 slug: join_us\n";
        echo "请修改前端页面 JoinUs/Index.vue 第129行为：\n";
        echo "  const joinUsCategory = result.items.find((cat: any) => cat.slug === 'join_us')\n";

    } catch (\Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
        echo "堆栈: " . $e->getTraceAsString() . "\n";
    }
});

echo "脚本执行完成\n";
