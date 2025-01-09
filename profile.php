<?php
require_once 'includes/config.php';
checkLogin();

$page_title = '个人设置';
$error = '';
$success = '';

$db = Database::getInstance()->getConnection();

// 获取用户信息
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([getCurrentUserId()]);
$user = $stmt->fetch();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_profile') {
        $email = trim($_POST['email']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // 验证邮箱格式
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '请输入有效的邮箱地址';
        } else {
            try {
                $db->beginTransaction();
                
                // 检查邮箱是否已被其他用户使用
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, getCurrentUserId()]);
                if ($stmt->fetch()) {
                    $error = '该邮箱已被使用';
                } else {
                    // 更新邮箱
                    if ($email != $user['email']) {
                        $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
                        $stmt->execute([$email, getCurrentUserId()]);
                    }
                    
                    // 更新密码
                    if ($current_password && $new_password) {
                        if (!password_verify($current_password, $user['password'])) {
                            $error = '当前密码错误';
                        } elseif (strlen($new_password) < 6) {
                            $error = '新密码至少需要6个字符';
                        } elseif ($new_password !== $confirm_password) {
                            $error = '两次输入的新密码不一致';
                        } else {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $stmt->execute([$hashed_password, getCurrentUserId()]);
                        }
                    }
                    
                    if (!$error) {
                        $db->commit();
                        $success = '个人信息已更新';
                        // 重新获取用户信息
                        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([getCurrentUserId()]);
                        $user = $stmt->fetch();
                    } else {
                        $db->rollBack();
                    }
                }
            } catch (Exception $e) {
                $db->rollBack();
                $error = '更新失败，请重试';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h2>个人设置</h2>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="" onsubmit="return validateForm(this);">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label>用户名：</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" 
                           readonly disabled>
                    <small class="form-text text-muted">用户名不可修改</small>
                </div>
                
                <div class="form-group">
                    <label>邮箱：</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?php echo htmlspecialchars($user['email']); ?>">
                </div>
                
                <hr>
                <h4>修改密码</h4>
                <p class="text-muted">如果不需要修改密码，请留空以下字段</p>
                
                <div class="form-group">
                    <label>当前密码：</label>
                    <input type="password" name="current_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>新密码：</label>
                    <input type="password" name="new_password" class="form-control" minlength="6">
                    <small class="form-text text-muted">密码至少需要6个字符</small>
                </div>
                
                <div class="form-group">
                    <label>确认新密码：</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="6">
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">保存更改</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function validateForm(form) {
    const newPassword = form.new_password.value;
    const confirmPassword = form.confirm_password.value;
    
    if (newPassword || confirmPassword) {
        if (!form.current_password.value) {
            alert('请输入当前密码');
            return false;
        }
        if (newPassword !== confirmPassword) {
            alert('两次输入的新密码不一致');
            return false;
        }
    }
    
    return true;
}
</script>

<?php require_once 'includes/footer.php'; ?> 