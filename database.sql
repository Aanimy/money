-- 创建数据库
CREATE DATABASE IF NOT EXISTS expense_tracker DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE expense_tracker;

-- 创建用户表
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建收入分类表
CREATE TABLE IF NOT EXISTS `income_categories` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `icon` VARCHAR(50) DEFAULT 'circle',
    `color` VARCHAR(20) DEFAULT '#2B5AAD',
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建支出分类表
CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `icon` VARCHAR(50) DEFAULT 'circle',
    `color` VARCHAR(20) DEFAULT '#2B5AAD',
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 在创建账户表之前添加账户类型表
CREATE TABLE IF NOT EXISTS `account_types` (
    `type_id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `icon` VARCHAR(50) DEFAULT 'wallet',
    `color` VARCHAR(20) DEFAULT '#2B5AAD',
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 修改账户表，添加账户类型关联
CREATE TABLE IF NOT EXISTS `accounts` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `type_id` INT NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`type_id`) REFERENCES `account_types`(`type_id`),
    INDEX `idx_user_balance` (`user_id`, `balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建收入记录表
CREATE TABLE IF NOT EXISTS `incomes` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `income_date` DATE NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`category_id`) REFERENCES `income_categories`(`id`),
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`),
    INDEX `idx_user_date` (`user_id`, `income_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建支出记录表
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `expense_date` DATE NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`),
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`),
    INDEX `idx_user_date` (`user_id`, `expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建系统日志表
CREATE TABLE IF NOT EXISTS `system_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `level` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建用户操作日志表
CREATE TABLE IF NOT EXISTS `user_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `description` TEXT NOT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认管理员账号
INSERT INTO `users` (`username`, `password`, `is_admin`) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);
-- 默认密码：password

-- 插入默认收入分类
INSERT INTO `income_categories` (`name`, `icon`, `color`) VALUES 
('工资', 'money-bill', '#52C41A'),
('奖金', 'gift', '#722ED1'),
('投资收益', 'chart-line', '#1890FF'),
('其他收入', 'plus-circle', '#F5222D');

-- 插入默认支出分类
INSERT INTO `expense_categories` (`name`, `icon`, `color`) VALUES 
('餐饮', 'utensils', '#F5222D'),
('交通', 'car', '#FA8C16'),
('购物', 'shopping-cart', '#1890FF'),
('娱乐', 'film', '#722ED1'),
('居住', 'home', '#52C41A'),
('其他', 'plus-circle', '#595959');

-- 插入默认账户类型
INSERT INTO `account_types` (`name`, `icon`, `color`, `sort_order`) VALUES 
('现金账户', 'money-bill', '#52C41A', 1),
('银行卡', 'credit-card', '#1890FF', 2),
('支付宝', 'alipay', '#1677FF', 3),
('微信钱包', 'weixin', '#07C160', 4),
('储蓄卡', 'piggy-bank', '#722ED1', 5),
('信用卡', 'credit-card', '#F5222D', 6),
('投资账户', 'chart-line', '#FA8C16', 7),
('其他账户', 'wallet', '#595959', 8); 