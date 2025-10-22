<?php
// admin_settings.php
require_once 'config.php';

// 检查是否是管理员
if (!is_admin()) {
    header("location: login.php");
    exit;
}

$message = '';

// 处理设置更新
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $service_status = $_POST['service_status'] ?? 'off';
    $cost_per_page = (float)($_POST['cost_per_page'] ?? 0.10);
    $service_description = trim($_POST['service_description'] ?? '');

    // 验证输入
    if (!in_array($service_status, ['on', 'off'])) {
        $message .= "<div class='alert error'>无效的服务状态。</div>";
    }
    if ($cost_per_page <= 0) {
        $message .= "<div class='alert error'>每页价格必须大于0。</div>";
    }

    if (empty($message)) {
        $settings_to_update = [
            'service_status' => $service_status,
            'cost_per_page' => $cost_per_page,
            'service_description' => $service_description
        ];

        foreach ($settings_to_update as $key => $value) {
            $sql = "INSERT INTO " . TABLE_SETTINGS . " (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("sss", $key, $value, $value);
                if (!$stmt->execute()) {
                    $message .= "<div class='alert error'>更新设置 '" . htmlspecialchars($key) . "' 失败: " . $mysqli->error . "</div>";
                }
                $stmt->close();
            } else {
                $message .= "<div class='alert error'>数据库操作准备失败。</div>";
            }
        }

        if (empty($message)) {
            $message = "<div class='alert success'>系统设置已成功更新。</div>";
            // 刷新会话中的全局设置，以便立即生效
            $_SESSION['message'] = $message;
            header("location: admin_settings.php");
            exit;
        }
    }
}

// 重新从数据库获取最新设置以显示
$current_settings = [];
$sql = "SELECT setting_key, setting_value FROM " . TABLE_SETTINGS;
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
    $result->free();
} else {
    $message .= "<div class='alert error'>无法从数据库加载当前设置。</div>";
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - 打印管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>系统设置</h2>
        <p class="text-center">管理打印服务的全局配置。</p>

        <div class="admin-nav">
            <a href="admin_dashboard.php">总览</a>
            <a href="admin_users.php">用户管理</a>
            <a href="admin_printers.php">打印机管理</a>
            <a href="admin_settings.php" class="active">系统设置</a>
            <a href="dashboard.php" class="button secondary">返回用户面板</a>
            <a href="logout.php" class="button logout">退出登录</a>
        </div>

        <?php echo $message; ?>

        <div class="section">
            <h3>全局打印服务设置</h3>
            <form action="admin_settings.php" method="post">
                <div class="form-group">
                    <label>打印服务状态:</label>
                    <input type="radio" name="service_status" value="on" id="service_on" <?php echo (isset($current_settings['service_status']) && $current_settings['service_status'] == 'on') ? 'checked' : ''; ?>>
                    <label for="service_on" style="display:inline; margin-right: 15px;">开启</label>
                    <input type="radio" name="service_status" value="off" id="service_off" <?php echo (isset($current_settings['service_status']) && $current_settings['service_status'] == 'off') ? 'checked' : ''; ?>>
                    <label for="service_off" style="display:inline;">关闭</label>
                </div>
                <div class="form-group">
                    <label for="cost_per_page">每页打印价格 (元):</label>
                    <input type="number" name="cost_per_page" id="cost_per_page" step="0.01" min="0.01" value="<?php echo htmlspecialchars($current_settings['cost_per_page'] ?? '0.10'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="service_description">用户端服务说明:</label>
                    <textarea name="service_description" id="service_description" rows="5"><?php echo htmlspecialchars($current_settings['service_description'] ?? '欢迎使用内网打印服务！'); ?></textarea>
                </div>
                <div class="form-group">
                    <input type="submit" value="保存设置">
                </div>
            </form>
        </div>
    </div>
</body>
</html>