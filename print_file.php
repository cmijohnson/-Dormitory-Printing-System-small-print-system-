<?php
// print_file.php
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// 检查打印服务是否开启
if (SERVICE_STATUS == 'off') {
    $_SESSION['message'] = "<div class='alert error'>打印服务目前已暂停，无法执行打印。</div>";
    header("location: dashboard.php");
    exit;
}

$user_id = $_SESSION['id'];
$username = htmlspecialchars($_SESSION['username']); // 获取用户名用于日志
$message = '';
$file_id = $_POST['file_id'] ?? null;
$printer_name = $_POST['printer_name'] ?? DEFAULT_PRINTER_NAME; // 从前端获取或使用默认

if ($file_id === null) {
    $message = "<div class='alert error'>未指定要打印的文件。</div>";
    $_SESSION['message'] = $message;
    header("location: dashboard.php");
    exit;
}

// 获取文件信息
$file_info = null;
$sql = "SELECT id, user_id, original_filename, stored_filename, file_path, page_count, cost FROM " . TABLE_UPLOADED_FILES . " WHERE id = ? AND user_id = ?";
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("ii", $file_id, $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $file_info = $result->fetch_assoc();
        } else {
            $message = "<div class='alert error'>文件未找到或您无权访问。</div>";
        }
    } else {
        $message = "<div class='alert error'>数据库查询失败。</div>";
    }
    $stmt->close();
} else {
    $message = "<div class='alert error'>数据库查询准备失败。</div>";
}

if ($file_info) {
    $file_path = $file_info['file_path'];
    $original_filename = $file_info['original_filename'];
    $page_count = $file_info['page_count'];
    $cost = $file_info['cost'];

    // 更新文件状态为 "printing"
    $update_sql = "UPDATE " . TABLE_UPLOADED_FILES . " SET print_status = 'printing', printer_name = ? WHERE id = ?";
    if ($stmt = $mysqli->prepare($update_sql)) {
        $stmt->bind_param("si", $printer_name, $file_id);
        $stmt->execute();
        $stmt->close();
    }

    $print_command_executed = false;
    $output = [];
    $return_var = 0;

    // 构建 PowerShell 命令来执行打印
    // 调用 print_handler.ps1 脚本
    $ps_script_path = __DIR__ . '\\print_handler.ps1';
    $command = "powershell.exe -ExecutionPolicy Bypass -NoProfile -NonInteractive -Command \"& \\\"{$ps_script_path}\\\" -Action 'PrintFile' -FilePath '\"{$file_path}\"' -PrinterName '\"{$printer_name}\"'\"";

    // 调试输出命令
    // error_log("Executing print command: " . $command);

    // 执行 PowerShell 脚本
    exec($command, $output, $return_var);
    $output_str = implode("\n", $output);

    if ($return_var === 0) { // PowerShell 脚本成功执行
        $message = "<div class='alert success'>文件 '" . htmlspecialchars($original_filename) . "' 已发送到打印机 '" . htmlspecialchars($printer_name) . "'。</div>";
        $print_command_executed = true;
    } else {
        $message = "<div class='alert error'>文件 '" . htmlspecialchars($original_filename) . "' 打印失败。错误信息: " . htmlspecialchars($output_str) . "</div>";
        // 调试输出PowerShell错误
        // error_log("PowerShell print error for file {$file_id}: " . $output_str);
    }

    // 根据打印结果更新数据库状态
    $final_status = $print_command_executed ? 'completed' : 'failed';
    $update_sql = "UPDATE " . TABLE_UPLOADED_FILES . " SET print_status = ? WHERE id = ?";
    if ($stmt = $mysqli->prepare($update_sql)) {
        $stmt->bind_param("si", $final_status, $file_id);
        $stmt->execute();
        $stmt->close();
    }
}

$mysqli->close();

$_SESSION['message'] = $message;
header("location: dashboard.php");
exit;
?>