<?php
// change_password.php
require_once 'config.php';

// 检查用户是否已登录，如果没有，则重定向到登录页面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$old_password = $new_password = $confirm_new_password = "";
$old_password_err = $new_password_err = $confirm_new_password_err = "";
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 验证旧密码
    if (empty(trim($_POST['old_password']))) {
        $old_password_err = "请输入旧密码。";
    } else {
        $old_password = trim($_POST['old_password']);
    }

    // 验证新密码
    if (empty(trim($_POST['new_password']))) {
        $new_password_err = "请输入新密码。";
    } elseif (strlen(trim($_POST['new_password'])) < 6) {
        $new_password_err = "新密码长度不能少于6个字符。";
    } else {
        $new_password = trim($_POST['new_password']);
    }

    // 验证确认新密码
    if (empty(trim($_POST['confirm_new_password']))) {
        $confirm_new_password_err = "请确认新密码。";
    } else {
        $confirm_new_password = trim($_POST['confirm_new_password']);
        if (empty($new_password_err) && ($new_password != $confirm_new_password)) {
            $confirm_new_password_err = "新密码不匹配。";
        }
    }

    // 如果没有错误，则尝试更新密码
    if (empty($old_password_err) && empty($new_password_err) && empty($confirm_new_password_err)) {
        $sql = "SELECT password FROM " . TABLE_USERS . " WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($old_password, $hashed_password)) {
                            // 旧密码正确，更新新密码
                            $update_sql = "UPDATE " . TABLE_USERS . " SET password = ? WHERE id = ?";
                            if ($update_stmt = $mysqli->prepare($update_sql)) {
                                $param_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $update_stmt->bind_param("si", $param_new_password, $user_id);
                                if ($update_stmt->execute()) {
                                    $message = "<div class='alert success'>密码已成功修改。</div>";
                                } else {
                                    $message = "<div class='alert error'>密码更新失败，请稍后再试。</div>";
                                }
                                $update_stmt->close();
                            }
                        } else {
                            $old_password_err = "旧密码不正确。";
                        }
                    }
                } else {
                    $message = "<div class='alert error'>用户账户未找到。</div>";
                }
            } else {
                $message = "<div class='alert error'>Oops! 出现了一些问题，请稍后再试。</div>";
            }
            $stmt->close();
        }
    }
    $mysqli->close();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改密码 - 打印管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>修改密码</h2>
        <?php echo $message; ?>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
            <div class="form-group <?php echo (!empty($old_password_err)) ? 'has-error' : ''; ?>">
                <label>旧密码</label>
                <input type="password" name="old_password">
                <span class="alert error"><?php echo $old_password_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($new_password_err)) ? 'has-error' : ''; ?>">
                <label>新密码</label>
                <input type="password" name="new_password">
                <span class="alert error"><?php echo $new_password_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($confirm_new_password_err)) ? 'has-error' : ''; ?>">
                <label>确认新密码</label>
                <input type="password" name="confirm_new_password">
                <span class="alert error"><?php echo $confirm_new_password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" value="修改密码">
            </div>
            <p class="text-center"><a href="dashboard.php" class="button secondary">返回仪表盘</a></p>
        </form>
    </div>
</body>
</html>