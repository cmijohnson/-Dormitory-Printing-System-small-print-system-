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