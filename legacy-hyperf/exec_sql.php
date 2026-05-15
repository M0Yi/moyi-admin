<?php

/**
 * 执行 SQL 脚本
 * 用法: php exec_sql.php [sql_file]
 */

$sqlFile = $argv[1] ?? 'create_jianhui_tables.sql';

echo "开始执行SQL文件: {$sqlFile}\n\n";

if (!file_exists($sqlFile)) {
    echo "错误: SQL文件不存在: {$sqlFile}\n";
    exit(1);
}

try {
    // 读取SQL文件
    $sql = file_get_contents($sqlFile);

    // 数据库配置
    $config = [
        'host' => getenv('PG_HOST') ?: 'postgres.orb.local',
        'port' => getenv('PG_PORT') ?: 5432,
        'dbname' => getenv('PG_DATABASE') ?: 'postgres',
        'user' => getenv('PG_USERNAME') ?: 'postgres',
        'password' => getenv('PG_PASSWORD') ?: 'postgres',
    ];

    echo "数据库配置:\n";
    echo "  主机: {$config['host']}\n";
    echo "  端口: {$config['port']}\n";
    echo "  数据库: {$config['dbname']}\n";
    echo "  用户: {$config['user']}\n\n";

    // 连接数据库
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
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

        // 检查是否有分号（不在字符串或注释中）
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
            } elseif (strpos($errorMsg, 'does not exist') !== false && strpos($errorMsg, 'DROP') !== false) {
                echo "! 语句 " . ($index + 1) . " 对象不存在（跳过）\n";
            } else {
                echo "✗ 语句 " . ($index + 1) . " 失败: {$errorMsg}\n";
                // 显示失败的SQL
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
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE 'jianhui_org%' ORDER BY table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "  JianhuiOrg 相关表 (" . count($tables) . " 个):\n";

    $newTables = [
        'jianhui_org_projects',
        'jianhui_org_project_progress',
        'jianhui_org_donations',
        'jianhui_org_donation_disclosures',
        'jianhui_org_annual_reports',
    ];

    foreach ($tables as $table) {
        if (in_array($table, $newTables)) {
            echo "  ✓ {$table} (新表)\n";
        } else {
            echo "  - {$table}\n";
        }
    }

    // 检查示例数据
    echo "\n检查示例数据:\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM jianhui_org_projects");
    $projectCount = $stmt->fetchColumn();
    echo "  项目数: {$projectCount}\n";

    if ($projectCount > 0) {
        $stmt = $pdo->query("SELECT id, title, project_type, status FROM jianhui_org_projects ORDER BY id");
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "  项目列表:\n";
        foreach ($projects as $p) {
            echo "    - [{$p['id']}] {$p['title']} ({$p['project_type']}, {$p['status']})\n";
        }
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM jianhui_org_donations");
    $donationCount = $stmt->fetchColumn();
    echo "  捐赠记录数: {$donationCount}\n";

    echo "\n✅ 完成！\n";

} catch (PDOException $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    exit(1);
}
