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
// 从数据库获取管理员已激活的打印机列表
$sql = "SELECT printer_name FROM " . TABLE_PRINTERS . " WHERE is_active = 1 ORDER BY printer_name ASC";
if ($stmt = $mysqli->prepare($sql)) { // 使用预处理语句，更安全
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $printers_list[] = $row['printer_name'];
        }
        $result->free();
    } else {
        // 捕获执行错误
        echo json_encode(['error' => '数据库查询执行失败: ' . $stmt->error]);
        $stmt->close();
        $mysqli->close();
        exit;
    }
    $stmt->close();
} else {
    // 捕获准备错误
    echo json_encode(['error' => '数据库查询准备失败: ' . $mysqli->error]);
    $mysqli->close();
    exit;
}

echo json_encode($printers_list);
$mysqli->close();
?>