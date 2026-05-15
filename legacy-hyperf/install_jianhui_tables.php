<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\Admin\Addons\AddonsPgsqlService;

// 设置时区
date_default_timezone_set('Asia/Shanghai');

echo "开始创建 JianhuiOrg 插件数据库表...\n\n";

try {
    $pgsqlFile = __DIR__ . '/addons/JianhuiOrg/Manager/pgsql.json';

    if (!file_exists($pgsqlFile)) {
        echo "错误：配置文件不存在: {$pgsqlFile}\n";
        exit(1);
    }

    echo "1. 检查配置文件: {$pgsqlFile}\n";
    $configContent = file_get_contents($pgsqlFile);
    $config = json_decode($configContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "错误：JSON格式错误: " . json_last_error_msg() . "\n";
        exit(1);
    }

    echo "   ✓ 配置文件读取成功\n\n";

    // 创建服务实例
    echo "2. 初始化 PostgreSQL 服务...\n";
    $service = new AddonsPgsqlService();
    echo "   ✓ 服务初始化成功\n\n";

    // 执行数据库管理
    echo "3. 创建数据库表...\n";
    $result = $service->managePgsqlDatabase('JianhuiOrg', $pgsqlFile);

    if ($result) {
        echo "   ✓ 数据库表创建成功\n\n";

        // 显示创建的表
        echo "4. 验证表创建情况:\n";
        echo "   已创建/更新的表:\n";
        if (isset($config['tables']) && is_array($config['tables'])) {
            foreach (array_keys($config['tables']) as $tableName) {
                echo "   - {$tableName}\n";
            }
        }

        echo "\n5. 测试模型...\n";
        // 测试连接和模型
        try {
            $pdo = \Hyperf\DbConnection\Db::connection('pgsql')->getPdo();
            $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE 'jianhui_org%'");
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            echo "   ✓ 数据库连接成功\n";
            echo "   当前 JianhuiOrg 相关表 (" . count($tables) . " 个):\n";
            foreach ($tables as $table) {
                echo "   - {$table}\n";
            }

        } catch (\Exception $e) {
            echo "   ! 验证时出错: " . $e->getMessage() . "\n";
        }

        echo "\n✅ 安装完成！\n";

    } else {
        echo "   ✗ 数据库表创建失败\n";
        echo "   请查看日志获取详细信息\n";
        exit(1);
    }

} catch (\Exception $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
    exit(1);
}
