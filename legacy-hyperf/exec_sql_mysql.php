<?php

/**
 * 执行 MySQL SQL 脚本
 * 用法: php exec_sql_mysql.php [sql_file]
 */

$sqlFile = $argv[1] ?? 'create_donation_tables_mysql.sql';

echo "开始执行SQL文件: {$sqlFile}\n\n";

if (!file_exists($sqlFile)) {
    echo "错误: SQL文件不存在: {$sqlFile}\n";
    exit(1);
}

try {
    // 读取SQL文件
    $sql = file_get_contents($sqlFile);

    // 数据库配置 (从 .env 读取)
    $config = [
        'host' => getenv('DB_HOST') ?: 'mysql8.orb.local',
        'port' => getenv('DB_PORT') ?: 3306,
        'dbname' => getenv('DB_DATABASE') ?: 'moyi',
        'user' => getenv('DB_USERNAME') ?: 'moyi',
        'password' => getenv('DB_PASSWORD') ?: 'moyi123',
    ];

    echo "数据库配置:\n";
    echo "  主机: {$config['host']}\n";
    echo "  端口: {$config['port']}\n";
    echo "  数据库: {$config['dbname']}\n";
    echo "  用户: {$config['user']}\n\n";

    // 连接数据库
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "✓ 数据库连接成功\n\n";

    // 分割SQL语句
    $statements = [];
    $currentStatement = '';
    $lines = explode("\n", $sql);

    foreach ($lines as $line) {
        // 跳过注释行
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }

        $currentStatement .= $line . "\n";

        // 检查是否有分号
        if (preg_match('/;\s*$/', $line)) {
            $stmt = trim($currentStatement);
            if (!empty($stmt)) {
                $statements[] = $stmt;
            }
            $currentStatement = '';
        }
    }

    echo "共 " . count($statements) . " 条SQL语句\n\n";

    $successCount = 0;
    $errorCount = 0;

    foreach ($statements as $index => $statement) {
        try {
            $pdo->exec($statement);
            $successCount++;
            echo "✓ 语句 " . ($index + 1) . " 执行成功\n";
        } catch (PDOException $e) {
            $errorCount++;
            $errorMsg = $e->getMessage();

            // 某些错误是可以忽略的
            if (strpos($errorMsg, 'already exists') !== false) {
                echo "! 语句 " . ($index + 1) . " 已存在（跳过）\n";
            } else {
                echo "✗ 语句 " . ($index + 1) . " 失败: {$errorMsg}\n";
                if (strlen($statement) < 200) {
                    echo "  SQL: {$statement}\n";
                } else {
                    echo "  SQL: " . substr($statement, 0, 200) . "...\n";
                }
            }
        }
    }

    echo "\n执行完成:\n";
    echo "  成功: {$successCount}\n";
    echo "  失败/跳过: {$errorCount}\n\n";

    // 验证表是否创建成功
    echo "验证数据库表...\n";
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'jianhui_org%' ORDER BY table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "  JianhuiOrg 相关表 (" . count($tables) . " 个):\n";
    foreach ($tables as $table) {
        echo "  ✓ {$table}\n";
    }

    // 检查示例数据
    echo "\n检查示例数据:\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM jianhui_org_donations");
    $donationCount = $stmt->fetchColumn();
    echo "  捐赠记录数: {$donationCount}\n";

    echo "\n✅ 完成！\n";

} catch (PDOException $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    exit(1);
}
