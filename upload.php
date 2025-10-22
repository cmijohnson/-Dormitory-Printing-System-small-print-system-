<?php
// upload.php
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// 检查打印服务是否开启
if (SERVICE_STATUS == 'off') {
    $_SESSION['message'] = "<div class='alert error'>打印服务目前已暂停，无法上传文件。</div>";
    header("location: dashboard.php");
    exit;
}

$user_id = $_SESSION['id'];
$message = ''; // 用于存储消息

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_to_upload'])) {
    $file = $_FILES['file_to_upload'];
    $original_filename = basename($file['name']);
    $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $file_size = $file['size'];
    $tmp_name = $file['tmp_name'];
    $printer_name_selected = $_POST['printer_name'] ?? DEFAULT_PRINTER_NAME; // 获取用户选择的打印机

    // 验证文件类型
    if (!in_array($file_extension, ALLOWED_FILE_TYPES)) {
        $message = "<div class='alert error'>不允许的文件类型。只允许 " . implode(', ', ALLOWED_FILE_TYPES) . "。</div>";
    }
    // 验证文件大小
    elseif ($file_size > MAX_FILE_SIZE) {
        $message = "<div class='alert error'>文件太大。最大允许 " . (MAX_FILE_SIZE / 1024 / 1024) . "MB。</div>";
    }
    // 验证上传错误
    elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "<div class='alert error'>文件上传失败，错误代码: " . $file['error'] . "。</div>";
    }
    else {
        // 生成唯一文件名，防止冲突
        $stored_filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "_", $original_filename); // 允许一些特殊字符，但过滤掉危险的
        $target_file_path = UPLOAD_DIR . $stored_filename;

        // 确保上传目录存在
        if (!is_dir(UPLOAD_DIR)) {
            // 尝试创建目录，如果失败则报错
            if (!mkdir(UPLOAD_DIR, 0755, true)) { // 0755 权限在 Windows 上可能需要调整
                $message = "<div class='alert error'>上传目录不存在且无法创建。请检查配置和权限。</div>";
                $_SESSION['message'] = $message;
                header("location: dashboard.php");
                exit;
            }
        }

        // 移动上传的文件
        if (move_uploaded_file($tmp_name, $target_file_path)) {
            // 文件移动成功，记录到数据库
            // 预估页数和费用 (这里简化为1页，实际需要文件解析库来获取真实页数)
            $page_count = 1; // 默认1页，实际应根据文件类型解析
            $cost = $page_count * COST_PER_PAGE;

            $sql = "INSERT INTO " . TABLE_UPLOADED_FILES . " (user_id, original_filename, stored_filename, file_path, print_status, page_count, cost, printer_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = $mysqli->prepare($sql)) {
                $initial_print_status = 'pending';
                $stmt->bind_param("issssid", $user_id, $original_filename, $stored_filename, $target_file_path, $initial_print_status, $page_count, $cost, $printer_name_selected);

                if ($stmt->execute()) {
                    $message = "<div class='alert success'>文件 " . htmlspecialchars($original_filename) . " 上传成功，等待打印。</div>";
                } else {
                    $message = "<div class='alert error'>文件信息保存到数据库失败。</div>";
                    // 如果数据库保存失败，删除已上传的文件
                    unlink($target_file_path);
                }
                $stmt->close();
            } else {
                $message = "<div class='alert error'>数据库插入准备失败。</div>";
                unlink($target_file_path);
            }
        } else {
            $message = "<div class='alert error'>文件移动失败。请检查上传目录权限。</div>";
        }
    }
} else {
    // 只有当没有文件上传时才显示此错误，否则可能是其他验证错误
    if (!isset($_FILES['file_to_upload']) || $_FILES['file_to_upload']['error'] == UPLOAD_ERR_NO_FILE) {
        $message = "<div class='alert error'>没有文件被上传。</div>";
    } else {
        $message = "<div class='alert error'>文件上传请求无效。</div>";
    }
}

$mysqli->close();

// 将消息存储在会话中，然后重定向回 dashboard
$_SESSION['message'] = $message;
header("location: dashboard.php");
exit;
?>