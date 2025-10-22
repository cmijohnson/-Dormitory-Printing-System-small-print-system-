<?php
// login.php
require_once 'config.php';

// 如果用户已登录，重定向到 dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (is_admin()) { // 如果是管理员，重定向到管理员仪表盘
        header("location: admin_dashboard.php");
    } else { // 普通用户重定向到用户仪表盘
        header("location: dashboard.php");
    }
    exit;
}

$username = $password = "";
$username_err = $password_err = $login_err = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 验证用户名
    if (empty(trim($_POST['username']))) {
        $username_err = "请输入用户名。";
    } else {
        $username = trim($_POST['username']);
    }

    // 验证密码
    if (empty(trim($_POST['password']))) {
        $password_err = "请输入密码。";
    } else {
        $password = trim($_POST['password']);
    }

    // 如果没有错误，则尝试登录
    if (empty($username_err) && empty($password_err)) {
        // 查询用户时，需要获取 is_admin 和 is_banned 列
        $sql = "SELECT id, username, password, is_admin, is_banned FROM " . TABLE_USERS . " WHERE username = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;

            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $username, $hashed_password, $is_admin, $is_banned);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            if ($is_banned == 1) {
                                $login_err = "您的账户已被禁用，请联系管理员。";
                            } else {
                                // 密码正确，启动会话
                                session_regenerate_id(); // 刷新会话ID，增加安全性
                                $_SESSION['loggedin'] = true;
                                $_SESSION['id'] = $id;
                                $_SESSION['username'] = $username;
                                $_SESSION['is_admin'] = $is_admin; // 存储管理员状态

                                // 根据用户类型重定向
                                if ($is_admin == 1) {
                                    header("location: admin_dashboard.php");
                                } else {
                                    header("location: dashboard.php");
                                }
                                exit;
                            }
                        } else {
                            $login_err = "用户名或密码错误。";
                        }
                    }
                } else {
                    $login_err = "用户名或密码错误。";
                }
            } else {
                echo "<div class='alert error'>Oops! 出现了一些问题，请稍后再试。</div>";
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
    <title>登录 - 打印管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>登录</h2>
        <?php
        if (!empty($login_err)) {
            echo "<div class='alert error text-center'>" . $login_err . "</div>";
        }
        ?>
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
            <div class="form-group">
                <input type="submit" value="登录">
            </div>
            <p class="text-center">还没有账户？ <a href="register.php">立即注册</a>。</p>
        </form>
    </div>
</body>
</html>