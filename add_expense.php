<?php
require_once 'includes/config.php';
checkLogin();

$page_title = '记录支出';
$user_id = getCurrentUserId();
$error = '';
$success = '';

try {
    $db = Database::getInstance()->getConnection();
    
    // 获取支出分类
    $categories = $db->query("SELECT * FROM expense_categories ORDER BY sort_order")->fetchAll();
    
    // 获取用户账户
    $accounts = getUserAccounts($user_id);
    
    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('无效的请求');
        }
        
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        if ($amount === false || $amount <= 0) {
            throw new Exception('请输入有效的金额');
        }
        
        // 检查账户余额
        $stmt = $db->prepare("SELECT balance FROM accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['account_id'], $user_id]);
        $account = $stmt->fetch();
        
        if ($account['balance'] < $amount) {
            throw new Exception('账户余额不足');
        }
        
        $db->beginTransaction();
        
        try {
            // 添加支出记录
            $stmt = $db->prepare("
                INSERT INTO expenses (user_id, category_id, account_id, amount, expense_date, description)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $_POST['category_id'],
                $_POST['account_id'],
                $amount,
                $_POST['expense_date'],
                $_POST['description']
            ]);
            
            // 更新账户余额
            $stmt = $db->prepare("
                UPDATE accounts 
                SET balance = balance - ? 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$amount, $_POST['account_id'], $user_id]);
            
            $db->commit();
            $success = '支出记录添加成功';
            
            // 记录操作日志
            Logger::getInstance()->logUserAction($user_id, 'add_expense', "添加支出记录：{$amount}元");
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Add expense error: " . $e->getMessage());
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
    <link rel="stylesheet" href="assets/css/transactions.css">
</head>
<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="transaction-form-card">
                    <div class="card-header">
                        <h1 class="h3 mb-0">记录支出</h1>
                    </div>
                    
                    <div class="card-body">
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
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <!-- 分类选择 -->
                            <div class="form-group">
                                <label>支出分类</label>
                                <div class="category-select">
                                    <?php foreach ($categories as $category): ?>
                                        <div class="category-option">
                                            <input type="radio" name="category_id" 
                                                   id="category_<?php echo $category['id']; ?>" 
                                                   value="<?php echo $category['id']; ?>" required>
                                            <label for="category_<?php echo $category['id']; ?>">
                                                <i class="fas fa-<?php echo $category['icon']; ?> category-icon" 
                                                   style="color: <?php echo $category['color']; ?>"></i>
                                                <span class="category-name">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- 金额输入 -->
                            <div class="form-group">
                                <label>支出金额</label>
                                <div class="amount-input-group">
                                    <span class="currency-symbol">¥</span>
                                    <input type="number" name="amount" class="form-control" 
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <!-- 支出日期 -->
                                <div class="col-md-6 form-group">
                                    <label>支出日期</label>
                                    <input type="date" name="expense_date" class="form-control" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <!-- 支出账户 -->
                                <div class="col-md-6 form-group">
                                    <label>支出账户</label>
                                    <select name="account_id" class="form-control" required>
                                        <?php foreach ($accounts as $account): ?>
                                            <option value="<?php echo $account['id']; ?>">
                                                <?php echo htmlspecialchars($account['name']); ?>
                                                (¥<?php echo number_format($account['balance'], 2); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- 备注 -->
                            <div class="form-group">
                                <label>备注说明</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-danger submit-btn">
                                <i class="fas fa-check"></i> 保存支出
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html> 