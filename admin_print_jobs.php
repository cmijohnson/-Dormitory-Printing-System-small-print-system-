<?php
// admin_print_jobs.php
require_once 'config.php';

// 检查是否是管理员，如果不是，则重定向到登录页面
if (!is_admin()) {
    header("location: login.php");
    exit;
}

$message = '';

// 获取所有用户的打印记录
$all_print_jobs = [];
// 查询 uploaded_files 表，并连接 users 表以获取用户名
$sql = "SELECT uf.id, u.username, uf.original_filename, uf.printer_name, uf.page_count, uf.cost, uf.upload_time, uf.print_status, uf.error_message
        FROM " . TABLE_UPLOADED_FILES . " uf
        JOIN " . TABLE_USERS . " u ON uf.user_id = u.id
        ORDER BY uf.upload_time DESC";

if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $all_print_jobs[] = $row;
    }
    $result->free();
} else {
    $message .= "<div class='alert error'>无法获取打印记录列表: " . $mysqli->error . "</div>";
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>打印记录 - 打印管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>所有打印记录</h2>
        <p class="text-center">查看所有用户提交的文件及其打印状态。</p>

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

        <div class="section">
            <h3>所有用户打印任务</h3>
            <?php if (!empty($all_print_jobs)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>原始文件名</th>
                            <th>打印机</th>
                            <th>页数</th>
                            <th>费用</th>
                            <th>上传时间</th>
                            <th>打印状态</th>
                            <th>错误信息</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_print_jobs as $job): ?>
                            <tr>
                                <td><?php echo $job['id']; ?></td>
                                <td><?php echo htmlspecialchars($job['username']); ?></td>
                                <td><?php echo htmlspecialchars($job['original_filename']); ?></td>
                                <td><?php echo htmlspecialchars($job['printer_name'] ?? '未指定'); ?></td>
                                <td><?php echo $job['page_count']; ?></td>
                                <td>¥<?php echo number_format($job['cost'], 2); ?></td>
                                <td><?php echo $job['upload_time']; ?></td>
                                <td>
                                    <span class="status-<?php echo $job['print_status']; ?>">
                                        <?php echo ucfirst($job['print_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($job['error_message'])): ?>
                                        <span title="<?php echo htmlspecialchars($job['error_message']); ?>">查看</span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>目前没有打印记录。</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>