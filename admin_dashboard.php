<?php
// admin_dashboard.php
require_once 'config.php';

// 检查是否是管理员，如果不是，则重定向到登录页面
if (!is_admin()) {
    header("location: login.php");
    exit;
}

$admin_username = htmlspecialchars($_SESSION['username']);
$message = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员仪表盘 - 打印管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>管理员仪表盘</h2>
        <p class="text-center">欢迎, <?php echo $admin_username; ?>! 您拥有系统管理权限。</p>

        <div class="admin-nav">
            <a href="admin_dashboard.php" <?php if(basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php') echo 'class="active"'; ?>>总览</a>
            <a href="admin_users.php" <?php if(basename($_SERVER['PHP_SELF']) == 'admin_users.php') echo 'class="active"'; ?>>用户管理</a>
            <a href="admin_printers.php" <?php if(basename($_SERVER['PHP_SELF']) == 'admin_printers.php') echo 'class="active"'; ?>>打印机管理</a>
            <a href="admin_print_jobs.php" <?php if(basename($_SERVER['PHP_SELF']) == 'admin_print_jobs.php') echo 'class="active"'; ?>>打印记录</a>
            <a href="admin_settings.php" <?php if(basename($_SERVER['PHP_SELF']) == 'admin_settings.php') echo 'class="active"'; ?>>系统设置</a>
            <a href="dashboard.php" class="button secondary">返回用户面板</a>
            <a href="logout.php" class="button logout">退出登录</a>
        </div>

        <?php echo $message; ?>

        <div class="section text-center">
            <h3>系统状态概览</h3>
            <p>这里可以显示一些系统概览信息，例如：</p>
            <p>- 总用户数：(待实现)</p>
            <p>- 总打印任务数：(待实现)</p>
            <p>- 待处理打印任务：(待实现)</p>
            <p class="alert info">请通过导航菜单访问具体管理功能。</p>
        </div>

    </div>
</body>
</html>