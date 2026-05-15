<?php
/**
 * 测试合作伙伴模型
 */

require __DIR__ . '/vendor/autoload.php';

// 运行Hyperf应用
$app = Hyperf\ApplicationFactory::create()->getApplication();

// 在协程环境中运行
Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
\Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

co::run(function () {
    try {
        echo "=== 测试合作伙伴模型 ===\n\n";

        // 测试获取所有分类
        echo "1. 测试获取所有分类:\n";
        $categories = \Addons\JianhuiOrg\Model\JianhuiPartnerCategory::with(['activePartners'])
            ->orderBy('sort_order')
            ->get();

        foreach ($categories as $category) {
            echo sprintf("  分类: %s (slug: %s)\n", $category->name, $category->slug);
            echo sprintf("    合作伙伴数量: %d\n", $category->activePartners->count());

            foreach ($category->activePartners as $partner) {
                echo sprintf("      - %s (%s)\n", $partner->name, $partner->website_url);
            }
        }

        echo "\n✓ 模型测试成功！\n";

    } catch (\Exception $e) {
        echo "\n❌ 错误: " . $e->getMessage() . "\n";
        echo "堆栈: " . $e->getTraceAsString() . "\n";
    }
});

echo "\n脚本执行完成\n";
