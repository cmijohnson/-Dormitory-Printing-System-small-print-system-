<?php
// get_print_queue.php
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => '未登录。']);
    exit;
}

header('Content-Type: application/json');

$printer_name = $_GET['printer'] ?? '';

if (empty($printer_name)) {
    echo json_encode(['error' => '未指定打印机名称。']);
    exit;
}

$ps_script_path = __DIR__ . '\\print_handler.ps1';
$command = "powershell.exe -ExecutionPolicy Bypass -NoProfile -NonInteractive -Command \"& \\\"{$ps_script_path}\\\" -Action 'GetPrintQueue' -PrinterName '\"{$printer_name}\"'\"";

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

if ($return_var === 0) {
    $json_output = implode("\n", $output);
    $queue_jobs = json_decode($json_output, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($queue_jobs)) {
        echo json_encode($queue_jobs);
    } else {
        echo json_encode(['error' => '无法解析打印队列。PowerShell原始输出: ' . $json_output]);
    }
} else {
    echo json_encode(['error' => '获取打印队列失败。PowerShell错误: ' . implode("\n", $output)]);
}

$mysqli->close();
?>