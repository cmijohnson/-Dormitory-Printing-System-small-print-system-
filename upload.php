<?php
// upload.php
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $_SESSION['message'] = "<div class='alert error'>请先登录。</div>";
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_to_upload'])) {
    // 检查打印服务是否开启
    if (SERVICE_STATUS == 'off') {
        $_SESSION['message'] = "<div class='alert error'>打印服务目前已暂停，无法上传文件。</div>";
        header("location: dashboard.php");
        exit;
    }

    $file = $_FILES['file_to_upload'];

    // 文件上传错误检查
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "<div class='alert error'>文件上传失败，错误码: " . $file['error'] . "</div>";
        $_SESSION['message'] = $message;
        header("location: dashboard.php");
        exit;
    }

    // 文件类型和大小验证
    $original_filename = basename($file['name']);
    $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

    // 根据 config.php 中的 ALLOWED_FILE_TYPES 进行验证
    if (!in_array($file_extension, ALLOWED_FILE_TYPES)) {
        $message = "<div class='alert error'>不支持的文件类型。当前仅支持 " . implode(', ', ALLOWED_FILE_TYPES) . " 文件上传。</div>"; // 明确提示支持的文件类型
        $_SESSION['message'] = $message;
        header("location: dashboard.php");
        exit;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $message = "<div class='alert error'>文件大小超出限制 (" . (MAX_FILE_SIZE / (1024 * 1024)) . "MB)。</div>";
        $_SESSION['message'] = $message;
        header("location: dashboard.php");
        exit;
    }

    // 生成唯一文件名
    $stored_filename = uniqid('print_file_', true) . '.' . $file_extension;
    $file_path = UPLOAD_DIR . $stored_filename;

    // 移动上传的文件到目标目录
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // 插入记录到数据库
        // SQL 语句中有 5 个 ? 占位符: user_id, original_filename, stored_filename, file_path, cost
        $sql = "INSERT INTO " . TABLE_UPLOADED_FILES . " (user_id, original_filename, stored_filename, file_path, print_status, page_count, cost) VALUES (?, ?, ?, ?, 'pending', 1, ?)";
        if ($stmt = $mysqli->prepare($sql)) {
            // 默认页数和费用，用户支付时可调整或确认
            $default_page_count = 1; // 无法自动获取页数，默认为1
            $default_cost = $default_page_count * COST_PER_PAGE;

            // 修复 bind_param 错误：类型定义字符串 'isssd' 对应 5 个变量
            $stmt->bind_param("isssd", $user_id, $original_filename, $stored_filename, $file_path, $default_cost);

            if ($stmt->execute()) {
                $_SESSION['message'] = "<div class='alert success'>文件上传成功，等待打印。</div>";
            } else {
                $message = "<div class='alert error'>数据库插入失败: " . $mysqli->error . "</div>";
                // 如果数据库插入失败，删除已上传的文件
                unlink($file_path);
            }
            $stmt->close();
        } else {
            $message = "<div class='alert error'>数据库操作准备失败: " . $mysqli->error . "</div>";
            unlink($file_path);
        }
    } else {
        $message = "<div class='alert error'>文件移动失败，请检查目录权限。</div>";
    }
} else {
    $message = "<div class='alert error'>无效的文件上传请求。</div>";
}

$_SESSION['message'] = $message; // 存储最终消息
$mysqli->close();
header("location: dashboard.php");
exit;
?>