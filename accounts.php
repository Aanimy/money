<?php
require_once 'includes/config.php';
checkLogin();

$page_title = '账户管理';
$user_id = getCurrentUserId();
$error = '';
$success = '';

try {
    $db = Database::getInstance()->getConnection();
    
    // 获取账户类型列表
    $account_types = $db->query("SELECT * FROM account_types ORDER BY sort_order")->fetchAll();
    
    // 处理添加账户
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('无效的请求');
        }
        
        switch ($_POST['action']) {
            case 'add':
                $stmt = $db->prepare("
                    INSERT INTO accounts (user_id, type_id, name, balance, description)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $_POST['type_id'],
                    $_POST['name'],
                    $_POST['balance'],
                    $_POST['description']
                ]);
                $success = '账户添加成功';
                break;
        }
    }
    
    // 获取用户的账户列表
    $accounts = getUserAccounts($user_id);
    
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Accounts error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/accounts.css">
</head>
<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>账户管理</h1>
            <button type="button" class="btn btn-primary add-account-btn" data-toggle="modal" data-target="#addAccountModal">
                <i class="fas fa-plus"></i> 添加账户
            </button>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <?php foreach ($accounts as $account): ?>
                <div class="col-md-4 mb-4">
                    <div class="account-card">
                        <div class="account-card-header">
                            <i class="fas fa-<?php echo $account['icon']; ?>" style="color: <?php echo $account['color']; ?>"></i>
                            <span class="account-type"><?php echo htmlspecialchars($account['type_name']); ?></span>
                        </div>
                        <h3 class="account-name"><?php echo htmlspecialchars($account['name']); ?></h3>
                        <div class="account-balance">¥<?php echo number_format($account['balance'], 2); ?></div>
                        <div class="account-description text-muted">
                            <?php echo htmlspecialchars($account['description']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 添加账户模态框 -->
    <div class="modal fade" id="addAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">添加账户</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>账户类型</label>
                            <select name="type_id" class="form-control" required>
                                <?php foreach ($account_types as $type): ?>
                                    <option value="<?php echo $type['type_id']; ?>">
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>账户名称</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>初始余额</label>
                            <input type="number" name="balance" class="form-control" step="0.01" value="0.00" required>
                        </div>
                        <div class="form-group">
                            <label>账户说明</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html> 