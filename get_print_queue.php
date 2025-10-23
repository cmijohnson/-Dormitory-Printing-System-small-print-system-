<?php
// get_print_queue.php (调试模式 - 最终版本)
require_once 'config.php';

// 强制开启错误报告，确保任何PHP错误都会显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 确保输出是 JSON 格式，即使有错误信息
header('Content-Type: application/json');

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => '未登录。']);
    exit;
}

$printer_name = $_GET['printer'] ?? '';

if (empty($printer_name)) {
    echo json_encode(['error' => '未指定打印机名称。']);
    exit;
}

$ps_script_path = __DIR__ . '\\print_handler.ps1';

// 检查 PowerShell 脚本文件是否存在
if (!file_exists($ps_script_path)) {
    echo json_encode([
        'error' => 'PowerShell 脚本文件未找到。',
        'details' => '脚本路径: ' . $ps_script_path
    ]);
    exit;
}

// 构建 PowerShell 命令
// 使用 -File 参数直接执行脚本，并传递参数
// 这种方式对路径和参数的解析通常更健壮
// 注意：这里对 $printer_name 的引用方式已调整，确保在 PowerShell 内部被正确解析
$command = "powershell.exe -ExecutionPolicy Bypass -NoProfile -NonInteractive -File \"{$ps_script_path}\" -Action 'GetPrintQueue' -PrinterName '{$printer_name}'";

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

// 将 PowerShell 的原始输出合并成一个字符串
$raw_powershell_output = implode("\n", $output);

if ($return_var === 0) {
    // 尝试解码 JSON
    $queue_jobs = json_decode($raw_powershell_output, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($queue_jobs)) {
        // 成功解析 JSON，返回队列数据
        echo json_encode($queue_jobs);
    } else {
        // PowerShell 脚本执行成功，但输出不是有效的 JSON
        echo json_encode([
            'error' => '无法解析打印队列。',
            'details' => 'PowerShell脚本输出不是有效的JSON。',
            'json_last_error' => json_last_error_msg(),
            'powershell_raw_output' => $raw_powershell_output,
            'powershell_return_code' => $return_var,
            'command_executed' => $command
        ]);
    }
} else {
    // PowerShell 命令执行失败（返回码非0）
    echo json_encode([
        'error' => '获取打印队列失败。',
        'details' => 'PowerShell命令执行失败。',
        'powershell_raw_output' => $raw_powershell_output,
        'powershell_return_code' => $return_var,
        'command_executed' => $command
    ]);
}

$mysqli->close();
?>