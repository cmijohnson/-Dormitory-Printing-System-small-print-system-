好的，这是一个更全面、更专业的内网文件打印系统方案，包含了你要求的所有功能：用户认证、文件上传、服务器端打印机选择、打印队列查看、支付模拟弹窗、用户密码修改、管理员后端（管理用户、打印机、全局设置、服务说明），以及优化的 UI 设计和手机端适配。

**核心功能概览：**

*   **用户认证：** 注册、登录、退出、修改密码。
*   **文件上传：** 用户上传文件，记录到数据库，预估费用。
*   **打印机选择：** 用户从管理员预设的可用打印机列表中选择。
*   **打印队列：** 动态显示所选打印机的当前打印任务。
*   **支付模拟：** 打印前弹出费用确认框，用户点击“确认已支付”后才触发打印。
*   **管理员后端：**
    *   **用户管理：** 查看所有用户，设置/取消用户封禁状态。
    *   **打印机管理：** 动态获取服务器所有打印机，添加/删除/激活/禁用打印机。
    *   **系统设置：** 控制打印服务开启/关闭，修改每页打印价格，修改用户端服务说明。
*   **UI/UX 优化：** 更现代的设计，平滑的动画，响应式布局。
*   **安全性增强：** 所有输入验证、预处理语句、密码哈希、会话管理。

---

**部署前的准备工作 (Windows Server)：**

1.  **MySQL 数据库：** 确保 MySQL 服务器正在运行。
2.  **PHP 环境：** 确保 PHP 已安装，`mysqli` 扩展已启用，并且 `shell_exec()` 函数已在 `php.ini` 中启用。
3.  **PowerShell 执行策略：** 以**管理员身份**打开 PowerShell，执行 `Set-ExecutionPolicy RemoteSigned -Scope LocalMachine`。
4.  **上传目录：**
    *   在服务器上创建一个用于存储上传文件的目录，例如 `D:\uploaded_prints\`。
    *   **重要：** 确保 IIS 或 Nginx 运行 PHP 的用户账户（通常是 `IIS AppPool\你的网站名称` 或 `NETWORK SERVICE`）对 `D:\uploaded_prints\` 目录拥有**完全控制**权限，以便 PHP 可以写入文件。
5.  **打印机名称：** 确保你的 Windows 服务器上已安装并配置好至少一台打印机。你可以在“控制面板”->“设备和打印机”中找到其**确切名称**。
6.  **PDF/Office 打印支持：**
    *   对于 PDF 文件，Windows 默认可以通过关联程序（如 Adobe Reader 或 Edge）打印。
    *   对于 Word/Excel 等 Office 文件，服务器上需要安装对应的 Office 软件才能通过 `Start-Process -Verb Print` 命令进行打印。如果没有安装，这些文件将无法通过此方式打印。

---

### **文件结构概览：**

```
/ (网站根目录，例如 D:\wwwroot\print.cmiteam.cn\)
├── config.php
├── style.css
├── register.php
├── login.php
├── logout.php
├── dashboard.php
├── upload.php
├── print_file.php
├── change_password.php
├── get_printers.php        <-- 获取管理员已激活的打印机列表 (PHP)
├── get_server_printers.php <-- 新增：获取服务器所有打印机列表 (PHP，供管理员用)
├── get_print_queue.php     <-- 获取打印队列 (PHP)
├── admin_dashboard.php     <-- 新增：管理员主页
├── admin_users.php         <-- 新增：管理员用户管理
├── admin_printers.php      <-- 新增：管理员打印机管理
├── admin_settings.php      <-- 新增：管理员系统设置
└── print_handler.ps1       <-- PowerShell 脚本，处理打印机交互
```

---

### **1. 数据库结构 (MySQL)**

请在你的 MySQL 数据库管理工具中执行以下完整的 SQL 语句来**创建或更新**数据库和所有表。

```sql
-- --------------------------------------------------------
-- 打印管理系统数据库初始化/更新脚本
-- --------------------------------------------------------

-- 步骤 1: (可选) 如果数据库已存在，先删除它，以确保完全干净的重新创建
-- 这一步会删除所有数据，请谨慎执行！
-- DROP DATABASE IF EXISTS `print_app_db`;

-- 步骤 2: 创建新的数据库（如果不存在）
CREATE DATABASE IF NOT EXISTS `print_app_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 步骤 3: 切换到新创建的数据库
USE `print_app_db`;

-- 步骤 4: 创建/更新用户表
-- 存储系统用户的登录信息，新增 is_admin 和 is_banned 字段
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL, -- 存储加密后的密码
    `is_admin` TINYINT(1) DEFAULT 0, -- 0: 普通用户, 1: 管理员
    `is_banned` TINYINT(1) DEFAULT 0, -- 0: 未封禁, 1: 已封禁
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 步骤 5: 创建/更新文件上传记录表
-- 存储用户上传的文件信息和打印状态
CREATE TABLE IF NOT EXISTS `uploaded_files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL, -- 服务器上存储的唯一文件名
    `file_path` VARCHAR(512) NOT NULL, -- 文件在服务器上的绝对路径
    `upload_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `print_status` ENUM('pending', 'printing', 'completed', 'failed') DEFAULT 'pending',
    `printer_name` VARCHAR(255) NULL, -- 实际使用的打印机名称
    `page_count` INT DEFAULT 1, -- 预估或实际页数
    `cost` DECIMAL(10,2) DEFAULT 0.00, -- 打印费用 (每页 0.1 元)
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 步骤 6: 创建打印机管理表
-- 存储管理员添加的可用打印机列表及其激活状态
CREATE TABLE IF NOT EXISTS `printers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `printer_name` VARCHAR(255) NOT NULL UNIQUE, -- 打印机在系统中的名称
    `is_active` TINYINT(1) DEFAULT 1, -- 0: 禁用, 1: 激活
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 步骤 7: 创建系统设置表
-- 存储全局配置，如服务状态、每页价格、服务说明
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE, -- 设置项的键名
    `setting_value` TEXT NULL -- 设置项的值
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 步骤 8: (可选) 插入初始系统设置
-- 如果表为空，则插入默认设置
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('service_status', 'on'), -- 'on' 或 'off'
('cost_per_page', '0.10'),
('service_description', '欢迎使用内网打印服务！请上传文件并选择打印机。');

-- 步骤 9: (重要) 设置第一个管理员用户
-- 注册一个普通用户后，手动将该用户的 is_admin 字段设置为 1。
-- 例如，如果用户名为 'admin' 的用户 id 是 1，则执行：
-- UPDATE `users` SET `is_admin` = 1 WHERE `id` = 1;

-- --------------------------------------------------------
-- 数据库初始化/更新脚本结束
-- --------------------------------------------------------
```

---

### **2. `config.php`**

**请务必修改数据库凭据和 `UPLOAD_DIR`。`PRINTER_NAME` 现在仅作为默认值，实际打印机将从数据库获取。**

```php
<?php
// config.php

// 数据库配置
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // 替换为你的 MySQL 用户名
define('DB_PASSWORD', 'your_mysql_password'); // 替换为你的 MySQL 密码
define('DB_NAME', 'print_app_db');

// 表名定义
define('TABLE_USERS', 'users');
define('TABLE_UPLOADED_FILES', 'uploaded_files');
define('TABLE_PRINTERS', 'printers');
define('TABLE_SETTINGS', 'settings');

// 文件上传目录 (重要: 建议此目录位于网站根目录之外，以增强安全性)
// 例如：D:\uploaded_prints\
// 确保此目录存在，并且Web服务器用户（如IIS AppPool用户）对此目录有写入权限。
define('UPLOAD_DIR', 'D:\\uploaded_prints\\'); // !!! 请修改为你的实际路径，并确保目录存在且可写 !!!

// 默认打印机名称 (Windows环境下使用，如果管理员未选择打印机，此为备用)
// 你可以在 "控制面板" -> "设备和打印机" 中找到打印机的确切名称。
define('DEFAULT_PRINTER_NAME', 'Your_Default_Printer_Name_Here'); // !!! 请修改为你的实际打印机名称 !!!

// 允许上传的文件类型
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png']);
// 允许上传的最大文件大小 (字节)
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB

// 尝试连接到 MySQL 数据库
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// 检查连接
if($mysqli === false){
    die("ERROR: 无法连接到数据库. " . $mysqli->connect_error);
}

// 启动会话
session_start();

// 获取全局设置
$global_settings = [];
$sql_settings = "SELECT setting_key, setting_value FROM " . TABLE_SETTINGS;
if ($result_settings = $mysqli->query($sql_settings)) {
    while ($row = $result_settings->fetch_assoc()) {
        $global_settings[$row['setting_key']] = $row['setting_value'];
    }
    $result_settings->free();
} else {
    // 如果无法获取设置，使用默认值
    $global_settings['service_status'] = 'off';
    $global_settings['cost_per_page'] = '0.10';
    $global_settings['service_description'] = '服务配置加载失败。';
}

// 定义常量以便在其他地方使用
define('SERVICE_STATUS', $global_settings['service_status']);
define('COST_PER_PAGE', (float)$global_settings['cost_per_page']);
define('SERVICE_DESCRIPTION', $global_settings['service_description']);

// 管理员访问检查函数
function is_admin() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === 1;
}
?>
```

---

### **3. `style.css`**

```css
/* style.css */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #eef2f7; /* 浅蓝色背景 */
    color: #333;
    margin: 0;
    padding: 20px;
    line-height: 1.6;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    min-height: 100vh;
}
.container {
    max-width: 900px;
    width: 100%;
    margin: 20px auto;
    background: #fff;
    padding: 30px;
    border-radius: 12px; /* 更圆润的边角 */
    box-shadow: 0 8px 20px rgba(0,0,0,0.1); /* 更明显的阴影 */
}
h1, h2 {
    color: #2c3e50;
    text-align: center;
    margin-bottom: 25px;
    font-weight: 600;
}
h3 {
    color: #34495e;
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 15px;
    font-size: 1.4em;
    font-weight: 600;
}
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #555;
}
.form-group input[type="text"],
.form-group input[type="password"],
.form-group input[type="file"],
.form-group input[type="number"],
.form-group textarea,
.form-group select {
    width: calc(100% - 24px); /* 调整宽度 */
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 6px; /* 更圆润的输入框 */
    box-sizing: border-box;
    font-size: 16px;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
.form-group input[type="text"]:focus,
.form-group input[type="password"]:focus,
.form-group input[type="file"]:focus,
.form-group input[type="number"]:focus,
.form-group textarea:focus,
.form-group select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
    outline: none;
}
.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-group input[type="submit"], .button {
    background-color: #007bff;
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 17px;
    font-weight: 600;
    transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
    text-decoration: none;
    display: inline-block;
    margin-right: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.form-group input[type="submit"]:hover, .button:hover {
    background-color: #0056b3;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}
.button.logout {
    background-color: #dc3545;
}
.button.logout:hover {
    background-color: #c82333;
}
.button.secondary {
    background-color: #6c757d;
}
.button.secondary:hover {
    background-color: #5a6268;
}
.button.warning {
    background-color: #ffc107;
    color: #333;
}
.button.warning:hover {
    background-color: #e0a800;
}
.button.danger {
    background-color: #dc3545;
}
.button.danger:hover {
    background-color: #c82333;
}
.button.success {
    background-color: #28a745;
}
.button.success:hover {
    background-color: #218838;
}

.alert {
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
    font-weight: bold;
    animation: fadeIn 0.5s ease-out;
}
.alert.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.alert.info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}
.text-center {
    text-align: center;
}
.section {
    margin-bottom: 30px;
    padding: 20px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background-color: #fdfdfd;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

/* Table styles */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
th, td {
    border: 1px solid #eee;
    padding: 10px;
    text-align: left;
    vertical-align: middle;
}
th {
    background-color: #f8f8f8;
    font-weight: bold;
    color: #555;
}
td button, td .button {
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    font-size: 0.9em;
}
td button:hover, td .button:hover {
    transform: translateY(-1px);
}
td .status-pending { color: #ffc107; font-weight: bold; } /* 黄色 */
td .status-printing { color: #17a2b8; font-weight: bold; } /* 蓝色 */
td .status-completed { color: #28a745; font-weight: bold; } /* 绿色 */
td .status-failed { color: #dc3545; font-weight: bold; } /* 红色 */
td .status-active { color: #28a745; font-weight: bold; }
td .status-inactive { color: #6c757d; font-weight: bold; }
td .status-banned { color: #dc3545; font-weight: bold; }
td .status-not-banned { color: #28a745; font-weight: bold; }

/* Admin Navigation */
.admin-nav {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}
.admin-nav a {
    display: inline-block;
    padding: 10px 15px;
    margin: 0 5px;
    background-color: #343a40; /* 深色背景 */
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s ease;
}
.admin-nav a:hover {
    background-color: #495057;
}
.admin-nav a.active {
    background-color: #007bff;
}


/* Modal (弹窗) 样式 */
.modal {
    display: none; /* 默认隐藏 */
    position: fixed; /* 固定定位 */
    z-index: 1000; /* 放置在最上层 */
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto; /* 如果内容过多可滚动 */
    background-color: rgba(0,0,0,0.6); /* 半透明黑色背景 */
    justify-content: center;
    align-items: center;
    animation: fadeIn 0.3s ease-out;
}
.modal-content {
    background-color: #fefefe;
    margin: auto;
    padding: 30px;
    border: 1px solid #888;
    width: 80%;
    max-width: 500px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.4);
    position: relative;
    animation: slideIn 0.3s ease-out;
}
.close-button {
    color: #aaa;
    float: right;
    font-size: 32px; /* 增大关闭按钮 */
    font-weight: bold;
    position: absolute;
    top: 10px;
    right: 20px;
    line-height: 1; /* 调整行高 */
}
.close-button:hover,
.close-button:focus {
    color: #000;
    text-decoration: none;
    cursor: pointer;
}
.modal-footer {
    text-align: right;
    margin-top: 25px;
}
.modal-footer button {
    margin-left: 10px;
}

/* 动画 */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes slideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* 响应式设计 */
@media (max-width: 768px) {
    body {
        padding: 10px;
    }
    .container {
        padding: 15px;
        margin: 10px auto;
    }
    h1, h2 {
        font-size: 1.8em;
    }
    .form-group input[type="text"],
    .form-group input[type="password"],
    .form-group input[type="file"],
    .form-group input[type="number"],
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px;
        font-size: 15px;
    }
    .form-group input[type="submit"], .button {
        width: 100%;
        margin-right: 0;
        margin-bottom: 10px;
        font-size: 16px;
        padding: 10px 15px;
    }
    table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    th, td {
        min-width: 100px; /* 确保小屏幕下可滚动 */
        font-size: 0.9em;
        padding: 8px;
    }
    td button, td .button {
        padding: 6px 10px;
        font-size: 0.85em;
    }
    .modal-content {
        width: 95%;
        padding: 20px;
    }
    .admin-nav {
        flex-direction: column;
        align-items: stretch;
    }
    .admin-nav a {
        margin: 5px 0;
    }
}
```

---

### **4. `register.php`**

```php
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
```

---

### **5. `login.php`**

```php
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
```

---

### **6. `dashboard.php` (用户主页)**

```php
<?php
// dashboard.php
require_once 'config.php';

// 检查用户是否已登录，如果没有，则重定向到登录页面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['id'];
$username = htmlspecialchars($_SESSION['username']);
$message = ''; // 用于显示各种操作消息

// 获取用户已上传的文件
$uploaded_files = [];
$sql = "SELECT id, original_filename, upload_time, print_status, page_count, cost FROM " . TABLE_UPLOADED_FILES . " WHERE user_id = ? ORDER BY upload_time DESC";
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $uploaded_files[] = $row;
        }
    } else {
        $message .= "<div class='alert error'>无法获取文件列表。</div>";
    }
    $stmt->close();
} else {
    $message .= "<div class='alert error'>数据库查询准备失败。</div>";
}

// 处理来自其他页面的消息
if (isset($_SESSION['message'])) {
    $message .= $_SESSION['message'];
    unset($_SESSION['message']); // 显示后清除
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>仪表盘 - 打印管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>欢迎, <?php echo $username; ?>!</h2>
        <p class="text-center">在这里您可以上传文件并管理打印任务。</p>

        <div class="text-center" style="margin-bottom: 30px;">
            <a href="change_password.php" class="button secondary">修改密码</a>
            <a href="https://epay.cmiteam.cn" target="_blank" class="button secondary">支付跳转</a>
            <?php if (is_admin()): ?>
                <a href="admin_dashboard.php" class="button">管理员面板</a>
            <?php endif; ?>
            <a href="logout.php" class="button logout">退出登录</a>
        </div>

        <?php echo $message; // 显示各种操作消息 ?>

        <!-- 服务状态和说明 -->
        <div class="section">
            <h3>服务信息</h3>
            <p>打印服务状态:
                <?php if (SERVICE_STATUS == 'on'): ?>
                    <span class="status-active">运行中</span>
                <?php else: ?>
                    <span class="status-inactive">已暂停</span>
                <?php endif; ?>
            </p>
            <p>每页打印价格: <strong>¥<?php echo number_format(COST_PER_PAGE, 2); ?></strong></p>
            <p>服务说明: <?php echo htmlspecialchars(SERVICE_DESCRIPTION); ?></p>
        </div>

        <?php if (SERVICE_STATUS == 'on'): ?>
            <!-- 文件上传表单 -->
            <div class="section">
                <h3>上传文件进行打印</h3>
                <form action="upload.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="file_to_upload">选择文件:</label>
                        <input type="file" name="file_to_upload" id="file_to_upload" required>
                    </div>
                    <div class="form-group">
                        <label for="printer_name_select">选择打印机:</label>
                        <select name="printer_name" id="printer_name_select" required>
                            <option value="">加载中...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="submit" value="上传文件">
                    </div>
                </form>
            </div>

            <!-- 打印队列信息 -->
            <div class="section">
                <h3>打印队列 (<span id="selected_printer_queue_name">请选择打印机</span>)</h3>
                <div id="print_queue_status">
                    <p class="alert info">正在加载打印队列...</p>
                </div>
            </div>
        <?php else: ?>
            <div class="alert info text-center">打印服务目前已暂停，请稍后再试。</div>
        <?php endif; ?>

        <!-- 已上传文件列表 -->
        <div class="section file-list">
            <h3>我的文件</h3>
            <?php if (!empty($uploaded_files)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>文件名</th>
                            <th>页数</th>
                            <th>费用</th>
                            <th>上传时间</th>
                            <th>打印状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uploaded_files as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['original_filename']); ?></td>
                                <td><?php echo $file['page_count']; ?></td>
                                <td>¥<?php echo number_format($file['cost'], 2); ?></td>
                                <td><?php echo $file['upload_time']; ?></td>
                                <td><span class="status-<?php echo $file['print_status']; ?>"><?php echo ucfirst($file['print_status']); ?></span></td>
                                <td>
                                    <?php if (SERVICE_STATUS == 'on' && ($file['print_status'] == 'pending' || $file['print_status'] == 'failed')): ?>
                                    <button class="print-button" data-file-id="<?php echo $file['id']; ?>" data-page-count="<?php echo $file['page_count']; ?>" data-cost="<?php echo $file['cost']; ?>" data-filename="<?php echo htmlspecialchars($file['original_filename']); ?>">打印</button>
                                    <?php else: ?>
                                    <button disabled>已处理</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>您还没有上传任何文件。</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- 支付模拟模态框 -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>确认打印与支付</h3>
            <p>您即将打印文件: <strong id="modal-filename"></strong></p>
            <p>预估页数: <strong id="modal-page-count"></strong> 页</p>
            <p>费用: <strong id="modal-cost"></strong> 元 (每页 <?php echo number_format(COST_PER_PAGE, 2); ?> 元)</p>
            <p class="alert info">请确认您已支付费用。</p>
            <div class="modal-footer">
                <button class="button secondary" id="cancelPrint">取消</button>
                <button class="button" id="confirmPayment">确认已支付并打印</button>
            </div>
        </div>
    </div>

    <script>
        let selectedFileId = null;
        let selectedPrinterName = '';
        let currentPageCount = 0;

        document.addEventListener('DOMContentLoaded', function() {
            const printerSelect = document.getElementById('printer_name_select');
            const printQueueStatus = document.getElementById('print_queue_status');
            const selectedPrinterQueueName = document.getElementById('selected_printer_queue_name');

            // --- 动态加载打印机列表 (只加载管理员激活的打印机) ---
            async function loadPrinters() {
                if (printerSelect) { // 只有当服务开启时才会有打印机选择框
                    printerSelect.innerHTML = '<option value="">加载中...</option>';
                    try {
                        const response = await fetch('get_printers.php');
                        const printers = await response.json();
                        printerSelect.innerHTML = ''; // 清空加载中选项
                        if (printers.error) {
                            printerSelect.innerHTML = '<option value="">加载失败</option>';
                            console.error('获取打印机失败:', printers.error);
                            return;
                        }
                        if (printers.length === 0) {
                            printerSelect.innerHTML = '<option value="">无可用打印机</option>';
                        } else {
                            printers.forEach(printer => {
                                const option = document.createElement('option');
                                option.value = printer;
                                option.textContent = printer;
                                printerSelect.appendChild(option);
                            });
                            selectedPrinterName = printerSelect.value; // 默认选中第一个
                            selectedPrinterQueueName.textContent = selectedPrinterName;
                            loadPrintQueue(); // 加载默认打印机的队列
                        }
                    } catch (error) {
                        printerSelect.innerHTML = '<option value="">加载失败</option>';
                        console.error('获取打印机列表时发生错误:', error);
                    }
                }
            }

            // --- 动态加载打印队列 ---
            async function loadPrintQueue() {
                if (!selectedPrinterName) {
                    if (printQueueStatus) printQueueStatus.innerHTML = '<p class="alert info">请选择一个打印机。</p>';
                    return;
                }
                if (printQueueStatus) printQueueStatus.innerHTML = '<p class="alert info">正在加载打印队列...</p>';
                try {
                    const response = await fetch(`get_print_queue.php?printer=${encodeURIComponent(selectedPrinterName)}`);
                    const queue = await response.json();

                    if (queue.error) {
                        if (printQueueStatus) printQueueStatus.innerHTML = `<p class="alert error">获取打印队列失败: ${queue.error}</p>`;
                        console.error('获取打印队列失败:', queue.error);
                        return;
                    }

                    if (queue.length === 0) {
                        if (printQueueStatus) printQueueStatus.innerHTML = '<p class="alert success">当前打印队列为空。</p>';
                    } else {
                        let queueHtml = '<table><thead><tr><th>文档名</th><th>状态</th></tr></thead><tbody>';
                        queue.forEach(job => {
                            queueHtml += `<tr><td>${job.DocumentName}</td><td>${job.Status}</td></tr>`;
                        });
                        queueHtml += '</tbody></table>';
                        if (printQueueStatus) printQueueStatus.innerHTML = queueHtml;
                    }
                } catch (error) {
                    if (printQueueStatus) printQueueStatus.innerHTML = '<p class="alert error">获取打印队列时发生错误。</p>';
                    console.error('获取打印队列时发生错误:', error);
                }
            }

            // --- 打印机选择变化事件 ---
            if (printerSelect) {
                printerSelect.addEventListener('change', function() {
                    selectedPrinterName = this.value;
                    selectedPrinterQueueName.textContent = selectedPrinterName;
                    loadPrintQueue();
                });
            }


            // --- 支付模态框逻辑 ---
            const paymentModal = document.getElementById('paymentModal');
            if (paymentModal) {
                const closeButton = paymentModal.querySelector('.close-button');
                const confirmPaymentButton = document.getElementById('confirmPayment');
                const cancelPrintButton = document.getElementById('cancelPrint');
                const modalFilename = document.getElementById('modal-filename');
                const modalPageCount = document.getElementById('modal-page-count');
                const modalCost = document.getElementById('modal-cost');

                document.querySelectorAll('.print-button').forEach(button => {
                    button.addEventListener('click', function() {
                        selectedFileId = this.dataset.fileId;
                        currentPageCount = parseInt(this.dataset.pageCount);
                        const cost = parseFloat(this.dataset.cost);
                        const filename = this.dataset.filename;

                        modalFilename.textContent = filename;
                        modalPageCount.textContent = currentPageCount;
                        modalCost.textContent = cost.toFixed(2); // 格式化为两位小数

                        paymentModal.style.display = 'flex'; // 显示模态框
                    });
                });

                closeButton.addEventListener('click', () => {
                    paymentModal.style.display = 'none';
                });
                cancelPrintButton.addEventListener('click', () => {
                    paymentModal.style.display = 'none';
                });

                confirmPaymentButton.addEventListener('click', async () => {
                    paymentModal.style.display = 'none'; // 隐藏模态框

                    if (!selectedFileId || !selectedPrinterName) {
                        alert('请选择文件和打印机。');
                        return;
                    }

                    // 发送打印请求到 print_file.php
                    const formData = new FormData();
                    formData.append('file_id', selectedFileId);
                    formData.append('printer_name', selectedPrinterName);

                    try {
                        const response = await fetch('print_file.php', {
                            method: 'POST',
                            body: formData
                        });
                        // print_file.php 会设置 $_SESSION['message'] 并重定向
                        // 所以这里只需刷新页面即可看到消息
                        window.location.reload();
                    } catch (error) {
                        alert('打印请求发送失败: ' + error.message);
                        console.error('打印请求失败:', error);
                        window.location.reload(); // 失败也刷新一下，看看后端有没有错误消息
                    }
                });

                // 点击模态框外部关闭
                window.addEventListener('click', function(event) {
                    if (event.target == paymentModal) {
                        paymentModal.style.display = 'none';
                    }
                });
            }

            // --- 页面初始化加载 ---
            if (SERVICE_STATUS === 'on') { // 只有服务开启时才加载打印机和队列
                loadPrinters();
                // 每隔 5 秒刷新一次打印队列
                setInterval(loadPrintQueue, 5000);
            } else {
                if (printerSelect) printerSelect.innerHTML = '<option value="">服务已暂停</option>';
                if (printQueueStatus) printQueueStatus.innerHTML = '<p class="alert info">打印服务目前已暂停。</p>';
            }
        });

        // 从 PHP 获取服务状态
        const SERVICE_STATUS = "<?php echo SERVICE_STATUS; ?>";
    </script>
</body>
</html>
```

---

### **7. `upload.php`**

```php
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
```

---

### **8. `print_file.php`**

```php
<?php
// print_file.php
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// 检查打印服务是否开启
if (SERVICE_STATUS == 'off') {
    $_SESSION['message'] = "<div class='alert error'>打印服务目前已暂停，无法执行打印。</div>";
    header("location: dashboard.php");
    exit;
}

$user_id = $_SESSION['id'];
$username = htmlspecialchars($_SESSION['username']); // 获取用户名用于日志
$message = '';
$file_id = $_POST['file_id'] ?? null;
$printer_name = $_POST['printer_name'] ?? DEFAULT_PRINTER_NAME; // 从前端获取或使用默认

if ($file_id === null) {
    $message = "<div class='alert error'>未指定要打印的文件。</div>";
    $_SESSION['message'] = $message;
    header("location: dashboard.php");
    exit;
}

// 获取文件信息
$file_info = null;
$sql = "SELECT id, user_id, original_filename, stored_filename, file_path, page_count, cost FROM " . TABLE_UPLOADED_FILES . " WHERE id = ? AND user_id = ?";
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("ii", $file_id, $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $file_info = $result->fetch_assoc();
        } else {
            $message = "<div class='alert error'>文件未找到或您无权访问。</div>";
        }
    } else {
        $message = "<div class='alert error'>数据库查询失败。</div>";
    }
    $stmt->close();
} else {
    $message = "<div class='alert error'>数据库查询准备失败。</div>";
}

if ($file_info) {
    $file_path = $file_info['file_path'];
    $original_filename = $file_info['original_filename'];
    $page_count = $file_info['page_count'];
    $cost = $file_info['cost'];

    // 更新文件状态为 "printing"
    $update_sql = "UPDATE " . TABLE_UPLOADED_FILES . " SET print_status = 'printing', printer_name = ? WHERE id = ?";
    if ($stmt = $mysqli->prepare($update_sql)) {
        $stmt->bind_param("si", $printer_name, $file_id);
        $stmt->execute();
        $stmt->close();
    }

    $print_command_executed = false;
    $output = [];
    $return_var = 0;

    // 构建 PowerShell 命令来执行打印
    // 调用 print_handler.ps1 脚本
    $ps_script_path = __DIR__ . '\\print_handler.ps1';
    $command = "powershell.exe -ExecutionPolicy Bypass -NoProfile -NonInteractive -Command \"& \\\"{$ps_script_path}\\\" -Action 'PrintFile' -FilePath '\"{$file_path}\"' -PrinterName '\"{$printer_name}\"'\"";

    // 调试输出命令
    // error_log("Executing print command: " . $command);

    // 执行 PowerShell 脚本
    exec($command, $output, $return_var);
    $output_str = implode("\n", $output);

    if ($return_var === 0) { // PowerShell 脚本成功执行
        $message = "<div class='alert success'>文件 '" . htmlspecialchars($original_filename) . "' 已发送到打印机 '" . htmlspecialchars($printer_name) . "'。</div>";
        $print_command_executed = true;
    } else {
        $message = "<div class='alert error'>文件 '" . htmlspecialchars($original_filename) . "' 打印失败。错误信息: " . htmlspecialchars($output_str) . "</div>";
        // 调试输出PowerShell错误
        // error_log("PowerShell print error for file {$file_id}: " . $output_str);
    }

    // 根据打印结果更新数据库状态
    $final_status = $print_command_executed ? 'completed' : 'failed';
    $update_sql = "UPDATE " . TABLE_UPLOADED_FILES . " SET print_status = ? WHERE id = ?";
    if ($stmt = $mysqli->prepare($update_sql)) {
        $stmt->bind_param("si", $final_status, $file_id);
        $stmt->execute();
        $stmt->close();
    }
}

$mysqli->close();

$_SESSION['message'] = $message;
header("location: dashboard.php");
exit;
?>
```

---

### **9. `change_password.php`**

```php
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
```

---

### **10. `get_printers.php` (用户端：获取管理员已激活的打印机)**

```php
<?php
// get_printers.php (用户端：获取管理员已激活的打印机)
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => '未登录。']);
    exit;
}

header('Content-Type: application/json');

$printers_list = [];
$sql = "SELECT printer_name FROM " . TABLE_PRINTERS . " WHERE is_active = 1 ORDER BY printer_name ASC";
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $printers_list[] = $row['printer_name'];
    }
    $result->free();
} else {
    echo json_encode(['error' => '无法从数据库获取打印机列表。']);
    $mysqli->close();
    exit;
}

echo json_encode($printers_list);
$mysqli->close();
?>
```

---

### **11. `get_server_printers.php` (管理员端：获取服务器所有打印机)**

```php
<?php
// get_server_printers.php (管理员端：获取服务器所有打印机)
require_once 'config.php';

// 检查是否是管理员
if (!is_admin()) {
    echo json_encode(['error' => '无权限访问。']);
    exit;
}

header('Content-Type: application/json');

$ps_script_path = __DIR__ . '\\print_handler.ps1';
$command = "powershell.exe -ExecutionPolicy Bypass -NoProfile -NonInteractive -Command \"& \\\"{$ps_script_path}\\\" -Action 'GetPrinters'\"";

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

if ($return_var === 0) {
    // PowerShell 脚本的输出是 JSON 字符串，需要解码
    $json_output = implode("\n", $output);
    $printers = json_decode($json_output, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($printers)) {
        echo json_encode($printers);
    } else {
        echo json_encode(['error' => '无法解析打印机列表。PowerShell原始输出: ' . $json_output]);
    }
} else {
    echo json_encode(['error' => '获取打印机列表失败。PowerShell错误: ' . implode("\n", $output)]);
}

$mysqli->close();
?>
```

---

### **12. `get_print_queue.php`**

```php
<?php
// get_print_queue.php
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => '未登录。']);
    exit;
}

header('Content-Type: application/json');

$printer_name = $_GET['printer'] ?? '';

if (empty($printer_name)) {
    echo json_encode(['error' => '未指定打印机名称。']);
    exit;
}

$ps_script_path = __DIR__ . '\\print_handler.ps1';
$command = "powershell.exe -ExecutionPolicy Bypass -NoProfile -NonInteractive -Command \"& \\\"{$ps_script_path}\\\" -Action 'GetPrintQueue' -PrinterName '\"{$printer_name}\"'\"";

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

if ($return_var === 0) {
    $json_output = implode("\n", $output);
    $queue_jobs = json_decode($json_output, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($queue_jobs)) {
        echo json_encode($queue_jobs);
    } else {
        echo json_encode(['error' => '无法解析打印队列。PowerShell原始输出: ' . $json_output]);
    }
} else {
    echo json_encode(['error' => '获取打印队列失败。PowerShell错误: ' . implode("\n", $output)]);
}

$mysqli->close();
?>
```

---

### **13. `admin_dashboard.php`**

```php
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
            <a href="admin_dashboard.php" class="active">总览</a>
            <a href="admin_users.php">用户管理</a>
            <a href="admin_printers.php">打印机管理</a>
            <a href="admin_settings.php">系统设置</a>
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
```

---

### **14. `admin_users.php`**

```php
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
```

---

### **15. `admin_printers.php`**

```php
<?php
// admin_printers.php
require_once 'config.php';

// 检查是否是管理员
if (!is_admin()) {
    header("location: login.php");
    exit;
}

$message = '';

// 处理添加打印机
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_printer') {
    $printer_name_to_add = trim($_POST['printer_name']);
    if (!empty($printer_name_to_add)) {
        $sql = "INSERT INTO " . TABLE_PRINTERS . " (printer_name, is_active) VALUES (?, 1)";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $printer_name_to_add);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>打印机 '" . htmlspecialchars($printer_name_to_add) . "' 添加成功。</div>";
            } else {
                if ($mysqli->errno == 1062) { // Duplicate entry error
                    $message = "<div class='alert error'>打印机 '" . htmlspecialchars($printer_name_to_add) . "' 已存在。</div>";
                } else {
                    $message = "<div class='alert error'>添加打印机失败: " . $mysqli->error . "</div>";
                }
            }
            $stmt->close();
        } else {
            $message = "<div class='alert error'>数据库操作准备失败。</div>";
        }
    } else {
        $message = "<div class='alert error'>打印机名称不能为空。</div>";
    }
}

// 处理激活/禁用/删除打印机
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['printer_id']) && isset($_POST['action'])) {
    $printer_id_to_manage = (int)$_POST['printer_id'];
    $action = $_POST['action'];

    if ($action === 'toggle_active') {
        // 获取当前状态
        $current_status = 0;
        $sql_select = "SELECT is_active FROM " . TABLE_PRINTERS . " WHERE id = ?";
        if ($stmt_select = $mysqli->prepare($sql_select)) {
            $stmt_select->bind_param("i", $printer_id_to_manage);
            $stmt_select->execute();
            $stmt_select->bind_result($current_status);
            $stmt_select->fetch();
            $stmt_select->close();
        }

        $new_status = ($current_status == 1) ? 0 : 1;
        $sql = "UPDATE " . TABLE_PRINTERS . " SET is_active = ? WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ii", $new_status, $printer_id_to_manage);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>打印机状态更新成功。</div>";
            } else {
                $message = "<div class='alert error'>更新打印机状态失败。</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='alert error'>数据库操作准备失败。</div>";
        }
    } elseif ($action === 'delete') {
        $sql = "DELETE FROM " . TABLE_PRINTERS . " WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $printer_id_to_manage);
            if ($stmt->execute()) {
                $message = "<div class='alert success'>打印机删除成功。</div>";
            } else {
                $message = "<div class='alert error'>删除打印机失败。</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='alert error'>数据库操作准备失败。</div>";
        }
    }
}

// 获取所有已配置的打印机列表
$configured_printers = [];
$sql = "SELECT id, printer_name, is_active, added_at FROM " . TABLE_PRINTERS . " ORDER BY printer_name ASC";
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $configured_printers[] = $row;
    }
    $result->free();
} else {
    $message .= "<div class='alert error'>无法获取已配置打印机列表。</div>";
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>打印机管理 - 打印管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>打印机管理</h2>
        <p class="text-center">管理用户可用的打印机列表。</p>

        <div class="admin-nav">
            <a href="admin_dashboard.php">总览</a>
            <a href="admin_users.php">用户管理</a>
            <a href="admin_printers.php" class="active">打印机管理</a>
            <a href="admin_settings.php">系统设置</a>
            <a href="dashboard.php" class="button secondary">返回用户面板</a>
            <a href="logout.php" class="button logout">退出登录</a>
        </div>

        <?php echo $message; ?>

        <!-- 添加打印机 -->
        <div class="section">
            <h3>添加新打印机</h3>
            <form action="admin_printers.php" method="post">
                <input type="hidden" name="action" value="add_printer">
                <div class="form-group">
                    <label for="printer_name">打印机名称 (与Windows系统中的名称一致):</label>
                    <input type="text" name="printer_name" id="printer_name" required>
                </div>
                <div class="form-group">
                    <input type="submit" value="添加打印机">
                    <button type="button" class="button secondary" id="discoverPrintersButton">从服务器发现打印机</button>
                </div>
                <div id="discovered_printers_list" style="margin-top:15px;"></div>
            </form>
        </div>

        <!-- 已配置打印机列表 -->
        <div class="section">
            <h3>已配置打印机</h3>
            <?php if (!empty($configured_printers)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>打印机名称</th>
                            <th>状态</th>
                            <th>添加时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configured_printers as $printer): ?>
                            <tr>
                                <td><?php echo $printer['id']; ?></td>
                                <td><?php echo htmlspecialchars($printer['printer_name']); ?></td>
                                <td>
                                    <?php if ($printer['is_active']): ?>
                                        <span class="status-active">激活</span>
                                    <?php else: ?>
                                        <span class="status-inactive">禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $printer['added_at']; ?></td>
                                <td>
                                    <form action="admin_printers.php" method="post" style="display:inline-block;">
                                        <input type="hidden" name="printer_id" value="<?php echo $printer['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <?php if ($printer['is_active']): ?>
                                            <button type="submit" class="button warning">禁用</button>
                                        <?php else: ?>
                                            <button type="submit" class="button success">激活</button>
                                        <?php endif; ?>
                                    </form>
                                    <form action="admin_printers.php" method="post" style="display:inline-block; margin-left: 5px;" onsubmit="return confirm('确定要删除此打印机吗？此操作不可逆。');">
                                        <input type="hidden" name="printer_id" value="<?php echo $printer['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="button danger">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>目前没有配置任何打印机。</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const discoverButton = document.getElementById('discoverPrintersButton');
            const discoveredListDiv = document.getElementById('discovered_printers_list');

            discoverButton.addEventListener('click', async function() {
                discoveredListDiv.innerHTML = '<p class="alert info">正在从服务器发现打印机...</p>';
                try {
                    const response = await fetch('get_server_printers.php');
                    const printers = await response.json();

                    if (printers.error) {
                        discoveredListDiv.innerHTML = `<p class="alert error">发现打印机失败: ${printers.error}</p>`;
                        console.error('发现打印机失败:', printers.error);
                        return;
                    }

                    if (printers.length === 0) {
                        discoveredListDiv.innerHTML = '<p class="alert info">服务器上没有发现任何打印机。</p>';
                    } else {
                        let listHtml = '<h4>发现的服务器打印机:</h4><ul>';
                        printers.forEach(printer => {
                            listHtml += `<li>${printer} <button class="button success button-small add-discovered-printer" data-printer-name="${printer}">添加到配置</button></li>`;
                        });
                        listHtml += '</ul>';
                        discoveredListDiv.innerHTML = listHtml;

                        // 为新添加的按钮绑定事件
                        document.querySelectorAll('.add-discovered-printer').forEach(button => {
                            button.addEventListener('click', async function() {
                                const printerName = this.dataset.printerName;
                                const formData = new FormData();
                                formData.append('action', 'add_printer');
                                formData.append('printer_name', printerName);

                                try {
                                    const addResponse = await fetch('admin_printers.php', {
                                        method: 'POST',
                                        body: formData
                                    });
                                    // 重新加载页面以显示更新后的列表和消息
                                    window.location.reload();
                                } catch (error) {
                                    alert('添加打印机请求失败: ' + error.message);
                                    console.error('添加打印机请求失败:', error);
                                }
                            });
                        });
                    }
                } catch (error) {
                    discoveredListDiv.innerHTML = '<p class="alert error">发现打印机时发生错误。</p>';
                    console.error('发现打印机时发生错误:', error);
                }
            });
        });
    </script>
</body>
</html>
```

---

### **16. `admin_settings.php`**

```php
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
```

---

### **17. `print_handler.ps1`**

```powershell
# print_handler.ps1
# 这是一个处理打印相关操作的PowerShell脚本
# 接收 -Action (GetPrinters, GetPrintQueue, PrintFile)
# 接收 -FilePath, -PrinterName 等参数

param (
    [Parameter(Mandatory=$true)]
    [string]$Action,

    [string]$FilePath,
    [string]$PrinterName
)

# 强制PowerShell的输出编码为UTF8，以便PHP正确解析JSON
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

function Get-PrintersList {
    try {
        Get-Printer | Select-Object -ExpandProperty Name | ConvertTo-Json -Compress
    } catch {
        @{ "error" = "获取打印机列表失败: $($_.Exception.Message)" } | ConvertTo-Json -Compress
    }
}

function Get-PrintQueueStatus {
    param (
        [Parameter(Mandatory=$true)]
        [string]$PrinterName
    )
    try {
        # 检查打印机是否存在
        if (-not (Get-Printer -Name $PrinterName -ErrorAction SilentlyContinue)) {
            throw "打印机 '$PrinterName' 不存在。"
        }
        Get-PrintJob -PrinterName $PrinterName | Select-Object DocumentName, Status | ConvertTo-Json -Compress
    } catch {
        @{ "error" = "获取打印队列失败: $($_.Exception.Message)" } | ConvertTo-Json -Compress
    }
}

function Print-File {
    param (
        [Parameter(Mandatory=$true)]
        [string]$FilePath,
        [Parameter(Mandatory=$true)]
        [string]$PrinterName
    )
    try {
        if (-not (Test-Path $FilePath)) {
            throw "文件不存在: $FilePath"
        }
        # 检查打印机是否存在
        if (-not (Get-Printer -Name $PrinterName -ErrorAction SilentlyContinue)) {
            throw "打印机 '$PrinterName' 不存在。"
        }

        # 使用Start-Process -Verb Print 来打印文件
        # 这会调用文件关联的默认程序来打印
        # 注意：服务器上需要安装对应的应用程序（如Adobe Reader for PDF, MS Office for DOCX/XLSX）
        # 并且运行IIS/Nginx的用户需要有权限启动这些应用程序并访问打印机
        Start-Process -FilePath $FilePath -Verb Print -Printer $PrinterName -PassThru | Wait-Process -Timeout 60 # 等待打印进程完成，最多60秒

        @{ "success" = "文件 '$FilePath' 已发送到打印机 '$PrinterName'" } | ConvertTo-Json -Compress
    } catch {
        @{ "error" = "打印文件失败: $($_.Exception.Message)" } | ConvertTo-Json -Compress
    }
}

switch ($Action) {
    "GetPrinters" {
        Get-PrintersList
    }
    "GetPrintQueue" {
        Get-PrintQueueStatus -PrinterName $PrinterName
    }
    "PrintFile" {
        Print-File -FilePath $FilePath -PrinterName $PrinterName
    }
    default {
        @{ "error" = "无效的Action参数: $Action" } | ConvertTo-Json -Compress
    }
}
```

---

### **18. `logout.php`**

```php
<?php
// logout.php
require_once 'config.php';

// 销毁所有会话变量
$_SESSION = array();

// 如果需要彻底销毁会话，请删除会话 cookie。
// 注意：这将销毁会话，而不仅仅是会话数据！
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

// 最后，销毁会话
session_destroy();

// 重定向到登录页面
header("location: login.php");
exit;
?>
```

---

### **部署和测试步骤 (Windows Server)：**

1.  **创建目录：** 在你的网站根目录（例如 `D:\wwwroot\print.cmiteam.cn\`）下创建一个名为 `print` 的子目录。
2.  **上传所有文件：** 将上述所有 `.php`、`.css` 和 `.ps1` 文件上传到 `print` 目录。
3.  **配置 `config.php`：**
    *   **修改 `DB_USERNAME` 和 `DB_PASSWORD` 为你的 MySQL 数据库凭据。**
    *   **修改 `UPLOAD_DIR` 为你服务器上一个实际存在的、Web 服务器用户可写的目录。** 例如 `D:\uploaded_prints\`。
    *   **修改 `DEFAULT_PRINTER_NAME` 为你服务器上真实打印机的确切名称。**
    *   **重要：** 确保 `UPLOAD_DIR` 目录已创建并设置了正确的权限。
4.  **执行 SQL 脚本：**
    *   登录你的 MySQL 数据库管理工具。
    *   **执行上述“1. 数据库结构 (MySQL)”中的所有 SQL 语句。** 这将创建或更新数据库和所有表。
5.  **PowerShell 脚本权限：** 确保 IIS 或 Nginx 运行 PHP 的用户账户对 `print_handler.ps1` 脚本有**读取和执行**权限。
6.  **注册管理员账户：**
    *   访问 `http://你的域名/print/register.php` 注册一个普通用户（例如 `admin`）。
    *   **登录到你的 MySQL 数据库管理工具。**
    *   找到 `users` 表，找到你刚刚注册的 `admin` 用户。
    *   将其 `is_admin` 列的值从 `0` 修改为 `1`。
7.  **测试管理员功能：**
    *   使用 `admin` 账户登录 `http://你的域名/print/login.php`。
    *   你应该会被重定向到 `admin_dashboard.php`。
    *   尝试在“打印机管理”中发现并添加打印机，然后激活它们。
    *   在“系统设置”中修改服务状态、价格和说明。
    *   在“用户管理”中测试封禁/解封用户。
8.  **测试用户功能：**
    *   使用普通用户账户登录 `http://你的域名/print/login.php`。
    *   你应该会被重定向到 `dashboard.php`。
    *   检查服务说明、价格和打印服务状态是否正确显示。
    *   上传文件，选择打印机，点击打印，并确认支付。
    *   检查你的物理打印机是否开始打印文件。同时，`dashboard.php` 上的文件状态应该会更新为“completed”或“failed”。
    *   测试“修改密码”功能。

这个系统现在应该功能非常强大且易于管理。请务必仔细配置权限和路径，这是此类系统成功的关键。