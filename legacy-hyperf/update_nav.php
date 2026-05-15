<?php
/**
 * 更新导航菜单：将"新闻动态"改为"项目动态"
 */

require __DIR__ . '/vendor/autoload.php';

use Hyperf\DbConnection\Db;

// 加载Hyperf应用
$app = require __DIR__ . '/vendor/autoload.php';

// 获取容器
$container = Hyperf\Context\ApplicationContext::getContainer();

try {
    echo "=== 更新导航菜单 ===\n\n";

    // 1. 查看当前导航菜单
    echo "1. 当前导航菜单:\n";
    $navItems = Db::table('jianhui_org_navigation')
        ->where('position', 'header')
        ->orderBy('sort_order')
        ->get();

    foreach ($navItems as $item) {
        echo sprintf("  %d: %s (%s) - %s\n", $item->id, $item->name, $item->slug, $item->url);
    }

    echo "\n";

    // 2. 查找"新闻动态"或"news"相关的导航项
    $newsNav = Db::table('jianhui_org_navigation')
        ->where('position', 'header')
        ->where(function ($query) {
            $query->where('name', 'like', '%新闻%')
                  ->orWhere('slug', 'like', '%news%');
        })
        ->first();

    if ($newsNav) {
        echo "2. 找到导航项: {$newsNav->name} (ID: {$newsNav->id})\n";

        // 3. 更新导航项
        Db::table('jianhui_org_navigation')
            ->where('id', $newsNav->id)
            ->update([
                'name' => '项目动态',
                'slug' => 'project_progress',
                'type' => 'link',
                'url' => '/projects'
            ]);

        echo "3. ✓ 已更新为: 项目动态 (/projects)\n";
    } else {
        echo "2. ✗ 未找到'新闻动态'导航项\n";
        echo "3. 尝试创建新的导航项...\n";

        // 获取当前最大的sort_order
        $maxSort = Db::table('jianhui_org_navigation')
            ->where('position', 'header')
            ->max('sort_order') ?: 0;

        // 插入新的导航项
        $newId = Db::table('jianhui_org_navigation')->insertGetId([
            'name' => '项目动态',
            'slug' => 'project_progress',
            'type' => 'link',
            'url' => '/projects',
            'position' => 'header',
            'sort_order' => $maxSort + 1,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        echo "3. ✓ 已创建新导航项，ID: {$newId}\n";
    }

    echo "\n4. 更新后的导航菜单:\n";
    $navItems = Db::table('jianhui_org_navigation')
        ->where('position', 'header')
        ->orderBy('sort_order')
        ->get();

    foreach ($navItems as $item) {
        echo sprintf("  %d: %s (%s) - %s\n", $item->id, $item->name, $item->slug, $item->url);
    }

    echo "\n✓ 导航菜单更新完成！\n";

} catch (\Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}
