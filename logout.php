<?php
require_once 'includes/config.php';

// 记录退出日志
if (isset($_SESSION['user_id'])) {
    Logger::getInstance()->logUserAction(
        $_SESSION['user_id'],
        'logout',
        '用户退出登录'
    );
}

// 清除所有会话数据
session_destroy();

// 重定向到登录页
header('Location: login.php');
exit; 