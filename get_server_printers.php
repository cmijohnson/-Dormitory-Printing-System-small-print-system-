<?php
// get_server_printers.php (管理员端：获取服务器所有打印机)
require_once 'config.php';

// 检查是否是管理员
if (!is_admin()) {
    echo json_encode(['error' => '无权限访问。']);
    exit;
}

header('Content-Type: application/json');

$ps_script_path = __DIR__ . '\\print_handler.ps1';
$command = "powershell.exe -ExecutionPolicy Bypass -NoProfile -NonInteractive -Command \"& \\\"{$ps_script_path}\\\" -Action 'GetPrinters'\"";

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

if ($return_var === 0) {
    // PowerShell 脚本的输出是 JSON 字符串，需要解码
    $json_output = implode("\n", $output);
    $printers = json_decode($json_output, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($printers)) {
        echo json_encode($printers);
    } else {
        echo json_encode(['error' => '无法解析打印机列表。PowerShell原始输出: ' . $json_output]);
    }
} else {
    echo json_encode(['error' => '获取打印机列表失败。PowerShell错误: ' . implode("\n", $output)]);
}

$mysqli->close();
?>