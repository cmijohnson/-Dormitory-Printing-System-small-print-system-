<?php
// print_file.php
require_once 'config.php';

// ... (其他代码) ...

    // --- 调用 PowerShell 脚本进行打印 ---
    $ps_script_path = __DIR__ . '\\print_handler.ps1';
    // 使用 -File 参数直接执行脚本，并传递参数
    // 关键修改：`2>&1` 将 PowerShell 的错误流重定向到标准输出，以便 PHP 捕获
    $command = "powershell.exe -ExecutionPolicy Bypass -NoProfile -NonInteractive -File \"{$ps_script_path}\" -Action 'PrintFile' -FilePath '{$file_path}' -PrinterName '{$printer_name}' -LogFile '" . POWERSHELL_LOG_FILE . "' 2>&1";

    php_log("Executing PowerShell command (with stderr redirect): $command");

    $output = [];
    $return_var = 0;
    
    $start_time = microtime(true);
    exec($command, $output, $return_var);
    $end_time = microtime(true);
    $exec_duration = round($end_time - $start_time, 2);

    php_log("PowerShell command finished. Duration: {$exec_duration}s. Return code: $return_var. Raw output: " . implode("\n", $output));

    if (empty($printer_name)) {
        $_SESSION['message'] = "<div class='alert error'>请选择一个打印机。</div>";
        php_log("Error: Printer name not specified.");
        header("location: dashboard.php");
        exit;
    }

    // 获取文件路径和当前状态
    $sql = "SELECT file_path, original_filename, print_status FROM " . TABLE_UPLOADED_FILES . " WHERE id = ? AND user_id = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("ii", $file_id, $user_id);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($file_path, $original_filename, $current_status);
                $stmt->fetch();

                if ($current_status == 'pending' || $current_status == 'failed') {
                    if (!file_exists($file_path)) {
                        $_SESSION['message'] = "<div class='alert error'>文件在服务器上未找到，无法打印。</div>";
                        php_log("Error: File not found on server: $file_path");
                        header("location: dashboard.php");
                        exit;
                    }

                    // --- 调用 PowerShell 脚本进行打印 ---
                    $ps_script_path = __DIR__ . '\\print_handler.ps1';
                    // 使用 -File 参数直接执行脚本，并传递参数
                    $command = "powershell.exe -ExecutionPolicy Bypass -NoProfile -NonInteractive -File \"{$ps_script_path}\" -Action 'PrintFile' -FilePath '{$file_path}' -PrinterName '{$printer_name}' -LogFile '" . POWERSHELL_LOG_FILE . "'";

                    php_log("Executing PowerShell command: $command");

                    $output = [];
                    $return_var = 0;
                    
                    // 记录 exec() 调用开始时间
                    $start_time = microtime(true);
                    exec($command, $output, $return_var);
                    $end_time = microtime(true);
                    $exec_duration = round($end_time - $start_time, 2);

                    php_log("PowerShell command finished. Duration: {$exec_duration}s. Return code: $return_var. Raw output: " . implode("\n", $output));


                    if ($return_var === 0) {
                        $json_output = implode("\n", $output);
                        $result_ps = json_decode($json_output, true);

                        if (json_last_error() === JSON_ERROR_NONE && isset($result_ps['success'])) {
                            // 打印命令发送成功，更新数据库状态
                            $update_sql = "UPDATE " . TABLE_UPLOADED_FILES . " SET print_status = 'printing', printer_name = ?, error_message = NULL WHERE id = ? AND user_id = ?";
                            if ($update_stmt = $mysqli->prepare($update_sql)) {
                                $update_stmt->bind_param("sii", $printer_name, $file_id, $user_id);
                                if ($update_stmt->execute()) {
                                    $_SESSION['message'] = "<div class='alert success'>文件 '" . htmlspecialchars($original_filename) . "' 已发送到打印机 '" . htmlspecialchars($printer_name) . "'。</div>";
                                    php_log("File ID $file_id status updated to 'printing'.");
                                } else {
                                    $_SESSION['message'] = "<div class='alert error'>打印命令发送成功，但更新数据库状态失败。请联系管理员。</div>";
                                    php_log("Error: Database update failed after successful PowerShell print command. MySQL error: " . $mysqli->error);
                                }
                                $update_stmt->close();
                            } else {
                                $_SESSION['message'] = "<div class='alert error'>数据库更新准备失败。</div>";
                                php_log("Error: Database update preparation failed. MySQL error: " . $mysqli->error);
                            }
                        } else {
                            // PowerShell 脚本返回错误或无法解析
                            $error_msg = "PowerShell脚本错误或输出无法解析。原始输出: " . $json_output;
                            if (isset($result_ps['error'])) {
                                $error_msg = $result_ps['error'];
                            }
                            $_SESSION['message'] = "<div class='alert error'>打印请求失败: " . htmlspecialchars($error_msg) . "</div>";
                            php_log("Error: PowerShell script returned error or invalid JSON. Details: $error_msg");
                            // 更新数据库状态为失败
                            $update_sql = "UPDATE " . TABLE_UPLOADED_FILES . " SET print_status = 'failed', printer_name = ?, error_message = ? WHERE id = ? AND user_id = ?";
                            if ($update_stmt = $mysqli->prepare($update_sql)) {
                                $update_stmt->bind_param("ssii", $printer_name, $error_msg, $file_id, $user_id);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                        }
                    } else {
                        // exec() 本身失败
                        $error_msg = "执行PowerShell命令失败。返回码: " . $return_var . "。输出: " . implode("\n", $output);
                        $_SESSION['message'] = "<div class='alert error'>打印请求失败: " . htmlspecialchars($error_msg) . "</div>";
                        php_log("Error: exec() failed. Details: $error_msg");
                        // 更新数据库状态为失败
                        $update_sql = "UPDATE " . TABLE_UPLOADED_FILES . " SET print_status = 'failed', printer_name = ?, error_message = ? WHERE id = ? AND user_id = ?";
                        if ($update_stmt = $mysqli->prepare($update_sql)) {
                            $update_stmt->bind_param("ssii", $printer_name, $error_msg, $file_id, $user_id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                    }
                } else {
                    $_SESSION['message'] = "<div class='alert info'>文件当前状态不允许再次打印。</div>";
                    php_log("Info: File ID $file_id current status '$current_status' does not allow re-printing.");
                }
            } else {
                $_SESSION['message'] = "<div class='alert error'>文件未找到或您无权操作。</div>";
                php_log("Error: File ID $file_id not found or unauthorized for User ID: $user_id.");
            }
        } else {
            $_SESSION['message'] = "<div class='alert error'>获取文件信息失败。</div>";
            php_log("Error: Failed to get file info for ID $file_id. MySQL error: " . $mysqli->error);
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "<div class='alert error'>数据库查询准备失败。</div>";
        php_log("Error: Database query preparation failed. MySQL error: " . $mysqli->error);
    }
} else {
    $_SESSION['message'] = "<div class='alert error'>无效的打印请求。</div>";
    php_log("Error: Invalid print request (not POST or missing parameters).");
}

$mysqli->close();
header("location: dashboard.php");
exit;
?>