<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

define('BASE_PATH', __DIR__);
// 运行应用
$app = require_once __DIR__ . '/config/container.php';

use Addons\JianhuiOrg\Model\JianhuiNavigation;

echo "检查导航数据...\n\n";

// 检查表是否存在
try {
    $count = JianhuiNavigation::count();
    echo "总导航数量: {$count}\n\n";

    // 显示所有顶级导航
    $rootNavs = JianhuiNavigation::where('parent_id', 0)->get();
    echo "顶级导航:\n";
    foreach ($rootNavs as $nav) {
        $childCount = JianhuiNavigation::where('parent_id', $nav->id)->count();
        echo "  - {$nav->id}: {$nav->name} (子菜单: {$childCount})\n";
    }

    echo "\n初始化导航数据...\n";
    $controller = new \Addons\JianhuiOrg\Controller\Api\CommonApiController(
        $app->get(\Hyperf\HttpServer\Contract\ResponseInterface::class),
        $app->get(\Psr\Http\Message\ServerRequestInterface::class)
    );

    // 通过反射调用私有方法
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('initNavigationData');
    $method->setAccessible(true);
    $method->invoke($controller);

    echo "\n重新检查:\n";
    $newCount = JianhuiNavigation::count();
    echo "总导航数量: {$newCount}\n";

    $rootNavs = JianhuiNavigation::where('parent_id', 0)->get();
    echo "顶级导航:\n";
    foreach ($rootNavs as $nav) {
        $childCount = JianhuiNavigation::where('parent_id', $nav->id)->count();
        echo "  - {$nav->id}: {$nav->name} (子菜单: {$childCount})\n";
    }

} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}
