<?php
/**
 * 轮播图管理脚本
 * 保留ID为1的轮播图，删除其他所有轮播图
 */

require_once __DIR__ . '/vendor/autoload.php';

define('BASE_PATH', __DIR__);

// 加载应用容器
$app = require_once __DIR__ . '/config/container.php';

use Addons\JianhuiOrg\Model\JianhuiHeroSlide;

echo "===========================================\n";
echo "轮播图管理工具\n";
echo "===========================================\n\n";

try {
    // 查询所有轮播图
    $allSlides = JianhuiHeroSlide::orderBy('id')->get();
    $totalSlides = $allSlides->count();

    echo "当前轮播图总数: {$totalSlides}\n\n";

    if ($totalSlides === 0) {
        echo "❌ 数据库中没有轮播图数据\n";
        exit(0);
    }

    echo "当前轮播图列表:\n";
    echo str_pad("ID", 8) . str_pad("标题", 40) . str_pad("状态", 10) . str_pad("排序", 8) . "\n";
    echo str_repeat("-", 80) . "\n";

    foreach ($allSlides as $slide) {
        $status = $slide->is_active ? '✅ 启用' : '❌ 禁用';
        echo str_pad($slide->id, 8) .
             str_pad($slide->title, 40) .
             str_pad($status, 10) .
             str_pad($slide->sort_order, 8) . "\n";
    }

    echo "\n";

    // 检查是否有ID为1的轮播图
    $slide1 = JianhuiHeroSlide::find(1);

    if (!$slide1) {
        echo "❌ 数据库中没有ID为1的轮播图，无法执行保留操作\n";
        exit(1);
    }

    echo "✅ 找到ID为1的轮播图: {$slide1->title}\n\n";

    // 计算将要删除的数量
    $slidesToDelete = $totalSlides - 1;
    echo "准备删除的轮播图数量: {$slidesToDelete}\n\n";

    if ($slidesToDelete === 0) {
        echo "✅ 数据库中只有ID为1的轮播图，无需删除\n";
        exit(0);
    }

    // 询问用户确认
    echo "⚠️  警告: 此操作将删除除了ID为1之外的所有轮播图！\n";
    echo "是否继续？(yes/no): ";

    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $confirmation = trim(strtolower($line));
    fclose($handle);

    if (!in_array($confirmation, ['yes', 'y'])) {
        echo "\n❌ 操作已取消\n";
        exit(0);
    }

    echo "\n开始删除...\n\n";

    // 删除除了ID为1之外的所有轮播图
    $deleted = JianhuiHeroSlide::where('id', '!=', 1)->delete();

    echo "✅ 成功删除 {$deleted} 条轮播图记录\n\n";

    // 验证删除结果
    $remainingSlides = JianhuiHeroSlide::count();
    echo "删除后剩余轮播图数量: {$remainingSlides}\n";

    if ($remainingSlides === 1) {
        echo "\n✅ 操作完成！数据库中现在只有ID为1的轮播图\n";
    } else {
        echo "\n⚠️  警告: 预期剩余1条记录，实际剩余{$remainingSlides}条记录\n";
    }

    // 显示保留的轮播图详情
    $keptSlide = JianhuiHeroSlide::find(1);
    if ($keptSlide) {
        echo "\n保留的轮播图详情:\n";
        echo "  ID: {$keptSlide->id}\n";
        echo "  标题: {$keptSlide->title}\n";
        echo "  描述: {$keptSlide->description}\n";
        echo "  背景图: {$keptSlide->bg_image}\n";
        echo "  链接: {$keptSlide->link_url}\n";
        echo "  状态: " . ($keptSlide->is_active ? '启用' : '禁用') . "\n";
        echo "  排序: {$keptSlide->sort_order}\n";
    }

} catch (\Exception $e) {
    echo "\n❌ 错误: " . $e->getMessage() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
    exit(1);
}
