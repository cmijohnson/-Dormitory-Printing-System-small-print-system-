<?php
// register.php
require_once 'config.php';

// 如果用户已登录，重定向到 dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("location: dashboard.php");
    exit;
}

$username = $password = $confirm_password = "";
$username_err = $password_err = $confirm_password_err = "";
$registration_success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 验证用户名
    if (empty(trim($_POST['username']))) {
        $username_err = "请输入用户名。";
    } else {
        // 检查用户名是否已存在
        $sql = "SELECT id FROM " . TABLE_USERS . " WHERE username = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST['username']);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $username_err = "该用户名已被占用。";
                } else {
                    $username = trim($_POST['username']);
                }
            } else {
                echo "<div class='alert error'>Oops! 出现了一些问题，请稍后再试。</div>";
            }
            $stmt->close();
        }
    }

    // 验证密码
    if (empty(trim($_POST['password']))) {
        $password_err = "请输入密码。";
    } elseif (strlen(trim($_POST['password'])) < 6) {
        $password_err = "密码长度不能少于6个字符。";
    } else {
        $password = trim($_POST['password']);
    }

    // 验证确认密码
    if (empty(trim($_POST['confirm_password']))) {
        $confirm_password_err = "请确认密码。";
    } else {
        $confirm_password = trim($_POST['confirm_password']);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "密码不匹配。";
        }
    }

    // 如果没有错误，则插入用户到数据库
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err)) {
        // 默认注册为普通用户，非管理员，未封禁
        $sql = "INSERT INTO " . TABLE_USERS . " (username, password, is_admin, is_banned) VALUES (?, ?, 0, 0)";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ss", $param_username, $param_password);
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // 加密密码

            if ($stmt->execute()) {
                $registration_success = true;
            } else {
                echo "<div class='alert error'>出现了一些问题，请稍后再试。</div>";
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
    <title>注册 - 打印管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>注册</h2>
        <?php if ($registration_success): ?>
            <div class="alert success text-center">注册成功！您现在可以 <a href="login.php">登录</a>。</div>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="form-group <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
                    <label>用户名</label>
                    <input type="text" name="username" value="<?php echo $username; ?>">
                    <span class="alert error"><?php echo $username_err; ?></span>
                </div>
                <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                    <label>密码</label>
                    <input type="password" name="password">
                    <span class="alert error"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group <?php echo (!empty($confirm_password_err)) ? 'has-error' : ''; ?>">
                    <label>确认密码</label>
                    <input type="password" name="confirm_password">
                    <span class="alert error"><?php echo $confirm_password_err; ?></span>
                </div>
                <div class="form-group">
                    <input type="submit" value="注册">
                </div>
                <p class="text-center">已经有账户了？ <a href="login.php">立即登录</a>。</p>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>