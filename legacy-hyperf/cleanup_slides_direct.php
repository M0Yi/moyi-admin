<?php
/**
 * 轮播图清理脚本 - 直接执行
 */

// 加载环境
require_once __DIR__ . '/vendor/autoload.php';

// 使用SQL直接操作
$sqlCleanup = <<<'SQL'
-- 删除除了ID为1之外的所有轮播图
DELETE FROM jianhui_org_hero_slides WHERE id != 1;
SQL;

$sqlSelect = <<<'SQL'
-- 查询当前所有轮播图
SELECT id, title, is_active, sort_order FROM jianhui_org_hero_slides ORDER BY id;
SQL;

echo "===========================================\n";
echo "轮播图清理脚本\n";
echo "===========================================\n\n";

try {
    // 从配置读取数据库信息
    $config = [
        'host' => getenv('PG_HOST') ?: 'postgres.orb.local',
        'port' => getenv('PG_PORT') ?: '5432',
        'database' => getenv('PG_DATABASE') ?: 'postgres',
        'username' => getenv('PG_USERNAME') ?: 'postgres',
        'password' => getenv('PG_PASSWORD') ?: 'postgres'
    ];

    // 连接数据库
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✓ 数据库连接成功\n\n";

    // 查询当前所有轮播图
    echo "当前轮播图列表:\n";
    $stmt = $pdo->query($sqlSelect);
    $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo str_pad("ID", 8) . str_pad("标题", 50) . str_pad("状态", 10) . str_pad("排序", 8) . "\n";
    echo str_repeat("-", 80) . "\n";

    foreach ($slides as $slide) {
        $active = $slide['is_active'] == 't' || $slide['is_active'] === true || $slide['is_active'] == 1 ? '启用' : '禁用';
        echo str_pad($slide['id'], 8) .
             str_pad(substr($slide['title'], 0, 48), 50) .
             str_pad($active, 10) .
             str_pad($slide['sort_order'], 8) . "\n";
    }

    echo "\n总数量: " . count($slides) . "\n\n";

    // 检查是否有ID为1的轮播图
    $hasSlide1 = false;
    foreach ($slides as $slide) {
        if ($slide['id'] == 1) {
            $hasSlide1 = true;
            break;
        }
    }

    if (!$hasSlide1) {
        echo "❌ 数据库中没有ID为1的轮播图，操作取消\n";
        exit(1);
    }

    // 计算将要删除的数量
    $slidesToDelete = count($slides) - 1;
    echo "准备删除的轮播图数量: {$slidesToDelete}\n\n";

    if ($slidesToDelete === 0) {
        echo "✅ 数据库中只有ID为1的轮播图，无需删除\n";
        exit(0);
    }

    // 执行删除
    echo "开始删除...\n";
    $affectedRows = $pdo->exec($sqlCleanup);

    echo "✅ 成功删除 {$affectedRows} 条轮播图记录\n\n";

    // 验证删除结果
    $stmt = $pdo->query($sqlSelect);
    $remainingSlides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $remainingCount = count($remainingSlides);

    echo "删除后剩余轮播图数量: {$remainingCount}\n\n";

    if ($remainingCount === 1 && $remainingSlides[0]['id'] == 1) {
        echo "✅ 操作完成！数据库中现在只有ID为1的轮播图\n\n";

        // 显示保留的轮播图详情
        $keptSlide = $remainingSlides[0];
        echo "保留的轮播图详情:\n";
        echo "  ID: {$keptSlide['id']}\n";
        echo "  标题: {$keptSlide['title']}\n";
        echo "  状态: " . ($keptSlide['is_active'] == 't' ? '启用' : '禁用') . "\n";
        echo "  排序: {$keptSlide['sort_order']}\n";
    } else {
        echo "⚠️  警告: 预期剩余1条记录，实际剩余{$remainingCount}条记录\n";
    }

} catch (PDOException $e) {
    echo "\n❌ 数据库错误: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n❌ 错误: " . $e->getMessage() . "\n";
    exit(1);
}
