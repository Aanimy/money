<?php
require_once 'includes/config.php';

if (!ALLOW_REGISTRATION) {
    die('注册功能已关闭');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = '请填写所有必填项';
    } elseif ($password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // 检查用户名是否已存在
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $error = '用户名已被使用';
            } else {
                // 创建新用户
                $stmt = $db->prepare("
                    INSERT INTO users (username, password, created_at)
                    VALUES (?, ?, NOW())
                ");
                
                if ($stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)])) {
                    $user_id = $db->lastInsertId();
                    
                    // 为新用户创建默认账户
                    $stmt = $db->prepare("
                        INSERT INTO accounts (user_id, name, description)
                        VALUES (?, '现金账户', '默认现金账户')
                    ");
                    $stmt->execute([$user_id]);
                    
                    $success = '注册成功！请登录';
                } else {
                    $error = '注册失败，请稍后重试';
                    error_log("Registration failed: " . print_r($stmt->errorInfo(), true));
                }
            }
        } catch (Exception $e) {
            $error = '注册失败，请稍后重试';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>注册 - <?php echo SITE_NAME; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1><i class="fas fa-chart-line"></i> <?php echo SITE_NAME; ?></h1>
                <p>创建您的账号</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <meta http-equiv="refresh" content="2;url=login.php">
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> 用户名</label>
                    <input type="text" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> 密码</label>
                    <div class="password-input">
                        <input type="password" name="password" class="form-control" required>
                        <span class="toggle-password" onclick="togglePassword(this)">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> 确认密码</label>
                    <div class="password-input">
                        <input type="password" name="confirm_password" class="form-control" required>
                        <span class="toggle-password" onclick="togglePassword(this)">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> 注册
                </button>
            </form>
            
            <div class="auth-footer">
                <p>已有账号？<a href="login.php">立即登录</a></p>
            </div>
        </div>
    </div>

    <script>
    function togglePassword(button) {
        const input = button.parentNode.querySelector('input');
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    </script>
</body>
</html> 