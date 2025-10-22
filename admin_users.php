<?php
// admin_users.php
require_once 'config.php';

// 检查是否是管理员
if (!is_admin()) {
    header("location: login.php");
    exit;
}

$message = '';

// 处理用户封禁/解封操作
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id']) && isset($_POST['action'])) {
    $user_id_to_manage = (int)$_POST['user_id'];
    $action = $_POST['action'];

    // 避免管理员封禁自己
    if ($user_id_to_manage === $_SESSION['id'] && $action === 'ban') {
        $message = "<div class='alert error'>您不能封禁自己的管理员账户。</div>";
    } else {
        $new_status = ($action === 'ban') ? 1 : 0;
        $sql = "UPDATE " . TABLE_USERS . " SET is_banned = ? WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ii", $new_status, $user_id_to_manage);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>用户状态更新成功。</div>";
            } else {
                $message = "<div class='alert error'>更新用户状态失败。</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='alert error'>数据库操作准备失败。</div>";
        }
    }
}

// 获取所有用户列表
$users = [];
$sql = "SELECT id, username, is_admin, is_banned, created_at FROM " . TABLE_USERS . " ORDER BY created_at DESC";
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
} else {
    $message .= "<div class='alert error'>无法获取用户列表。</div>";
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 打印管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>用户管理</h2>
        <p class="text-center">管理注册用户账户的封禁状态。</p>

        <div class="admin-nav">
            <a href="admin_dashboard.php">总览</a>
            <a href="admin_users.php" class="active">用户管理</a>
            <a href="admin_printers.php">打印机管理</a>
            <a href="admin_settings.php">系统设置</a>
            <a href="dashboard.php" class="button secondary">返回用户面板</a>
            <a href="logout.php" class="button logout">退出登录</a>
        </div>

        <?php echo $message; ?>

        <div class="section">
            <h3>所有用户</h3>
            <?php if (!empty($users)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>管理员</th>
                            <th>状态</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="status-active">是</span>
                                    <?php else: ?>
                                        <span class="status-inactive">否</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['is_banned']): ?>
                                        <span class="status-banned">已封禁</span>
                                    <?php else: ?>
                                        <span class="status-not-banned">正常</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['created_at']; ?></td>
                                <td>
                                    <form action="admin_users.php" method="post" style="display:inline-block;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <?php if ($user['is_banned']): ?>
                                            <button type="submit" name="action" value="unban" class="button success">解封</button>
                                        <?php else: ?>
                                            <button type="submit" name="action" value="ban" class="button danger">封禁</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>目前没有注册用户。</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>