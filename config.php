<?php
// config.php

// 数据库配置
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'print_app_db'); // 替换为你的 MySQL 用户名
define('DB_PASSWORD', 'WkyFaWX8hiZZwzCJ'); // 替换为你的 MySQL 密码
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