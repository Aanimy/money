-- 删除已存在的数据库
DROP DATABASE IF EXISTS expense_tracker;

-- 重新创建数据库
CREATE DATABASE expense_tracker DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE expense_tracker;

-- 导入主数据库结构
SOURCE database.sql;

-- 重置管理员密码（如果需要）
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin'; 