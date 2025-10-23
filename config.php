<?php
// config.php

// 数据库配置
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'print_app_db'); // 已更新为新的 MySQL 用户名
define('DB_PASSWORD', 'WkyFaWX8hiZZwzCJ'); // 已更新为新的 MySQL 密码
define('DB_NAME', 'print_app_db');

// 表名定义
define('TABLE_USERS', 'users');
define('TABLE_UPLOADED_FILES', 'uploaded_files');
define('TABLE_PRINTERS', 'printers');
define('TABLE_SETTINGS', 'settings');

// 文件上传目录 (重要: 请修改为你的实际路径，并确保目录存在且可写！)
// 你的网站根目录示例: D:\wwwroot\192.168.1.114\
// 你的PHP文件在示例: D:\wwwroot\192.168.1.114\print\
define('UPLOAD_DIR', 'D:\\wwwroot\\192.168.1.114\\print\\print_upload\\'); // !!! 请修改为你的实际路径，并确保目录存在且可写 !!!

// 确保上传目录存在
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true); // 确保 Web 服务器用户对此目录有写入权限
}

// PowerShell 脚本日志文件路径 (新增)
define('POWERSHELL_LOG_FILE', 'D:\\wwwroot\\192.168.1.114\\print\\powershell_log.log'); // !!! 请修改为你的实际路径，并确保目录存在且可写 !!!

// 默认打印机名称 (Windows环境下使用，如果管理员未选择打印机，此为备用)
// 你可以在 "控制面板" -> "设备和打印机" 中找到打印机的确切名称。
define('DEFAULT_PRINTER_NAME', 'Microsoft Print to PDF'); // !!! 请修改为你的实际打印机名称 !!!

// 允许上传的文件类型 (为了简化和Ghostscript的特性，已限制为PDF)
define('ALLOWED_FILE_TYPES', ['pdf']); // 仅允许PDF文件上传
// 允许上传的最大文件大小 (字节)
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB

// 尝试连接到 MySQL 数据库
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// 检查连接
if($mysqli->connect_error){
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