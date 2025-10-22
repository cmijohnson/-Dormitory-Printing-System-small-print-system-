<?php
// get_printers.php (用户端：获取管理员已激活的打印机)
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => '未登录。']);
    exit;
}

header('Content-Type: application/json');

$printers_list = [];
$sql = "SELECT printer_name FROM " . TABLE_PRINTERS . " WHERE is_active = 1 ORDER BY printer_name ASC";
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $printers_list[] = $row['printer_name'];
    }
    $result->free();
} else {
    echo json_encode(['error' => '无法从数据库获取打印机列表。']);
    $mysqli->close();
    exit;
}

echo json_encode($printers_list);
$mysqli->close();
?>