<?php
/**
 * 捐赠汇总表安装脚本
 * 执行此脚本以创建 jianhui_org_donation_summaries 表
 */

require_once __DIR__ . '/vendor/autoload.php';

// 加载 Hyperf 应用
$app = require_once __DIR__ . '/vendor/hyperf/support/bootstrap.php';

use Hyperf\DbConnection\Db;

try {
    echo "开始创建捐赠汇总表...\n";

    // 读取 SQL 文件
    $sql = file_get_contents(__DIR__ . '/create_donation_summary_table.sql');

    if (!$sql) {
        echo "错误：无法读取 SQL 文件\n";
        exit(1);
    }

    // 移除注释和空行
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/^\s*$/m', '', $sql);

    // 分割 SQL 语句
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    // 执行 SQL
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "执行 SQL: " . substr($statement, 0, 50) . "...\n";
            Db::statement($statement);
        }
    }

    echo "\n✓ 捐赠汇总表创建成功！\n";
    echo "\n你可以通过以下路径访问捐赠汇总管理：\n";
    echo "管理后台：/admin/{adminPath}/jianhui_org/donation-summaries\n";
    echo "API 接口：/api/v1/admin/donation-summaries\n";

} catch (\Exception $e) {
    echo "\n✗ 错误：" . $e->getMessage() . "\n";
    echo "堆栈跟踪：\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
