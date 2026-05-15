<?php
/**
 * 轮播图清理脚本 - 使用Hyperf ORM
 * 通过Hyperf的数据库连接执行清理操作
 */

// 定义基础路径
defined('BASE_PATH') or define('BASE_PATH', __DIR__);

// 加载Hyperf应用
require_once __DIR__ . '/vendor/autoload.php';

// 设置运行时目录
defined('RUNTIME_ROOT') or define('RUNTIME_ROOT', __DIR__ . '/runtime');

use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Context\ApplicationContext;
use Addons\JianhuiOrg\Model\JianhuiHeroSlide;

try {
    echo "===========================================\n";
    echo "轮播图清理脚本（使用Hyperf ORM）\n";
    echo "===========================================\n\n";

    // 初始化Hyperf容器
    $container = new Container((new DefinitionSourceFactory())());
    ApplicationContext::setContainer($container);

    echo "✓ Hyperf容器初始化成功\n\n";

    // 查询所有轮播图
    echo "当前轮播图列表:\n";
    $allSlides = JianhuiHeroSlide::orderBy('id')->get();
    $totalSlides = $allSlides->count();

    if ($totalSlides === 0) {
        echo "❌ 数据库中没有轮播图数据\n";
        exit(0);
    }

    foreach ($allSlides as $slide) {
        $status = $slide->is_active ? '启用' : '禁用';
        echo sprintf(
            "  ID: %d | 标题: %s | 状态: %s | 排序: %d\n",
            $slide->id,
            mb_substr($slide->title, 0, 40),
            $status,
            $slide->sort_order
        );
    }

    echo "\n总数量: {$totalSlides}\n\n";

    // 检查是否有ID为1的轮播图
    $slide1 = JianhuiHeroSlide::find(1);
    if (!$slide1) {
        echo "❌ 数据库中没有ID为1的轮播图，操作取消\n";
        exit(1);
    }

    // 计算将要删除的数量
    $slidesToDelete = $totalSlides - 1;
    echo "准备删除的轮播图数量: {$slidesToDelete}\n\n";

    if ($slidesToDelete === 0) {
        echo "✅ 数据库中只有ID为1的轮播图，无需删除\n";
        exit(0);
    }

    // 确认删除
    echo "即将删除ID为2及以上的所有轮播图...\n";
    $deleted = JianhuiHeroSlide::where('id', '!=', 1)->delete();

    echo "✅ 成功删除 {$deleted} 条轮播图记录\n\n";

    // 验证删除结果
    $remainingSlides = JianhuiHeroSlide::count();
    echo "删除后剩余轮播图数量: {$remainingSlides}\n\n";

    if ($remainingSlides === 1) {
        echo "✅ 操作完成！数据库中现在只有ID为1的轮播图\n\n";

        // 显示保留的轮播图详情
        $keptSlide = JianhuiHeroSlide::find(1);
        if ($keptSlide) {
            echo "保留的轮播图详情:\n";
            echo "  ID: {$keptSlide->id}\n";
            echo "  标题: {$keptSlide->title}\n";
            echo "  描述: " . mb_substr($keptSlide->description, 0, 60) . "\n";
            echo "  链接: {$keptSlide->link_url}\n";
            echo "  状态: " . ($keptSlide->is_active ? '启用' : '禁用') . "\n";
            echo "  排序: {$keptSlide->sort_order}\n";
        }
    } else {
        echo "⚠️  警告: 预期剩余1条记录，实际剩余{$remainingSlides}条记录\n";
    }

} catch (\Exception $e) {
    echo "\n❌ 错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
