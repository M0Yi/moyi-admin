<?php
$mysqli = new mysqli("mysql8.orb.local", "moyi", "moyi123", "moyi", 3306);
if ($mysqli->connect_error) {
    die("连接失败: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

echo "=== 所有分类列表 ===\n";
$result = $mysqli->query("SELECT id, name, parent_id, slug FROM jianhui_org_categories ORDER BY parent_id, id");
while ($row = $result->fetch_assoc()) {
    $indent = $row['parent_id'] == 0 ? '' : '  ';
    echo "{$indent}ID: {$row['id']}, 名称: {$row['name']}, 父级: {$row['parent_id']}\n";
}

$mysqli->close();
