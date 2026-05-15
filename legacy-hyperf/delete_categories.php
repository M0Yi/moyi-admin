<?php
$pdo = new PDO('mysql:host=mysql8.orb.local;port=3306;dbname=moyi;charset=utf8mb4', 'moyi', 'moyi123');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "\n=== 当前所有分类 ===\n";
$stmt = $pdo->query("SELECT id, name, parent_id FROM jianhui_org_categories ORDER BY parent_id, id");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($categories as $cat) {
    $indent = $cat['parent_id'] == 0 ? '' : '  ';
    echo "{$indent}[ID:{$cat['id']}] {$cat['name']} (父级:{$cat['parent_id']})\n";
}

// 直接通过ID删除（根据你的需求）
$idsToDelete = [2, 6, 7]; // 假设这些是"项目介绍"、"执行机构"、"资助对象"的ID
echo "\n=== 尝试删除分类ID: " . implode(', ', $idsToDelete) . " ===\n";

foreach ($idsToDelete as $id) {
    $stmt = $pdo->prepare("SELECT name FROM jianhui_org_categories WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo "ID {$id}: {$row['name']}\n";
        
        // 检查关联数据
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM jianhui_org_articles WHERE category_id = ?");
        $stmt->execute([$id]);
        $articleCount = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM jianhui_org_categories WHERE parent_id = ?");
        $stmt->execute([$id]);
        $childCount = $stmt->fetchColumn();
        
        echo "  - {$articleCount} 篇文章, {$childCount} 个子分类\n";
        
        if ($articleCount > 0 || $childCount > 0) {
            echo "  - 跳过（有关联数据）\n";
        } else {
            // 删除
            $delStmt = $pdo->prepare("DELETE FROM jianhui_org_categories WHERE id = ?");
            $delStmt->execute([$id]);
            echo "  - ✓ 已删除\n";
        }
    } else {
        echo "ID {$id}: 不存在\n";
    }
}

echo "\n✓ 操作完成！\n";
