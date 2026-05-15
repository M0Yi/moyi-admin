<?php
/**
 * 直接连接PostgreSQL数据库检查和修复"加入我们"分类
 */

// 从环境变量获取PostgreSQL数据库配置
$dbHost = getenv('PG_HOST') ?: 'postgres.orb.local';
$dbPort = '5432';
$dbName = getenv('PG_DATABASE') ?: 'postgres';
$dbUser = getenv('PG_USERNAME') ?: 'postgres';
$dbPass = getenv('PG_PASSWORD') ?: 'postgres';

try {
    // 连接PostgreSQL
    $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName};options='--client_encoding=UTF8'";
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== 数据库连接成功 ===\n\n";

    // 查询所有顶级文章分类
    echo "1. 查询所有顶级文章分类:\n";
    $sql = "SELECT id, name, slug, parent_id, is_active, type
            FROM jianhui_org_categories
            WHERE type = 'article' AND parent_id = 0
            ORDER BY id";
    $result = $pdo->query($sql);

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("  ID: %d | Name: %s | Slug: %s | Active: %s\n",
            $row['id'],
            $row['name'],
            $row['slug'] ?: '(null)',
            $row['is_active'] ? 'true' : 'false'
        );
    }

    echo "\n2. 查找'加入我们'相关分类:\n";
    $sql = "SELECT id, name, slug, parent_id, is_active
            FROM jianhui_org_categories
            WHERE type = 'article'
            AND (name LIKE '%加入%' OR slug LIKE '%join%' OR slug LIKE '%us%')
            ORDER BY id";
    $result = $pdo->query($sql);

    $joinUsCategories = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("  ID: %d | Name: %s | Slug: %s | Parent: %d\n",
            $row['id'],
            $row['name'],
            $row['slug'] ?: '(null)',
            $row['parent_id']
        );
        $joinUsCategories[] = $row;
    }

    if (empty($joinUsCategories)) {
        echo "  未找到'加入我们'分类，需要创建\n";

        // 获取当前最大的ID
        $maxIdResult = $pdo->query("SELECT COALESCE(MAX(id), 0) as max_id FROM jianhui_org_categories");
        $maxId = $maxIdResult->fetchColumn();
        $newId = $maxId + 1;

        echo "\n3. 创建'加入我们'分类 (ID: {$newId})...\n";

        $sql = "INSERT INTO jianhui_org_categories (id, name, slug, type, parent_id, is_active, created_at, updated_at)
                VALUES (:id, :name, :slug, :type, 0, true, NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $newId,
            ':name' => '加入我们',
            ':slug' => 'join_us',
            ':type' => 'article'
        ]);

        echo "  ✓ 创建成功！ID: {$newId}, Slug: join_us\n";
        $joinUsId = $newId;
    } else {
        $joinUsCategory = $joinUsCategories[0];
        $joinUsId = $joinUsCategory['id'];
        $currentSlug = $joinUsCategory['slug'];

        echo "\n3. 检查分类信息:\n";

        // 如果slug不对，更新它
        if ($currentSlug !== 'join_us') {
            echo "  当前Slug: {$currentSlug} (需要修改为: join_us)\n";

            $updateSql = "UPDATE jianhui_org_categories
                          SET slug = 'join_us', updated_at = NOW()
                          WHERE id = :id";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([':id' => $joinUsId]);

            echo "  ✓ Slug已更新为: join_us\n";
        } else {
            echo "  Slug正确: join_us\n";
        }

        // 检查是否有子分类
        echo "\n4. 检查子分类:\n";
        $childrenSql = "SELECT id, name, slug
                        FROM jianhui_org_categories
                        WHERE parent_id = :parent_id
                        ORDER BY sort_order, id";
        $stmt = $pdo->prepare($childrenSql);
        $stmt->execute([':parent_id' => $joinUsId]);
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($children)) {
            echo "  没有子分类，创建默认子分类...\n";

            // 创建默认子分类
            $maxChildIdResult = $pdo->query("SELECT COALESCE(MAX(id), 0) as max_id FROM jianhui_org_categories");
            $maxChildId = $maxChildIdResult->fetchColumn();

            $subCategories = [
                ['name' => '志愿者招募', 'slug' => 'volunteer_recruitment'],
                ['name' => '全职岗位', 'slug' => 'fulltime_positions']
            ];

            foreach ($subCategories as $index => $sub) {
                $maxChildId++;
                $sql = "INSERT INTO jianhui_org_categories (id, name, slug, type, parent_id, is_active, sort_order, created_at, updated_at)
                        VALUES (:id, :name, :slug, :type, :parent_id, true, :sort_order, NOW(), NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id' => $maxChildId,
                    ':name' => $sub['name'],
                    ':slug' => $sub['slug'],
                    ':type' => 'article',
                    ':parent_id' => $joinUsId,
                    ':sort_order' => $index
                ]);
                echo "  ✓ 创建子分类: {$sub['name']} (slug: {$sub['slug']}, ID: {$maxChildId})\n";
            }
        } else {
            echo "  现有子分类:\n";
            foreach ($children as $child) {
                echo sprintf("    - %s (slug: %s, ID: %d)\n", $child['name'], $child['slug'], $child['id']);
            }
        }
    }

    echo "\n=== 修复完成 ===\n";
    echo "加入我们分类ID: {$joinUsId}\n";
    echo "前端应使用 slug: join_us\n";

} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "\n";
    exit(1);
}
