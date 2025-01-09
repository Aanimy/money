<?php
require_once 'includes/config.php';
checkLogin();

$page_title = '账户转账';
$error = '';
$success = false;

// 获取用户的账户列表
$accounts = getUserAccounts(getCurrentUserId());

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $from_account_id = $_POST['from_account_id'];
    $to_account_id = $_POST['to_account_id'];
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    $transfer_date = $_POST['date'];
    
    if ($from_account_id == $to_account_id) {
        $error = '转出和转入账户不能相同';
    } elseif ($amount <= 0) {
        $error = '请输入有效的金额';
    } else {
        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();
            
            // 检查转出账户余额是否足够
            $stmt = $db->prepare("SELECT balance FROM accounts WHERE id = ? AND user_id = ?");
            $stmt->execute([$from_account_id, getCurrentUserId()]);
            $from_account = $stmt->fetch();
            
            if ($from_account && $from_account['balance'] >= $amount) {
                // 扣除转出账户余额
                $stmt = $db->prepare("
                    UPDATE accounts 
                    SET balance = balance - ? 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$amount, $from_account_id, getCurrentUserId()]);
                
                // 增加转入账户余额
                $stmt = $db->prepare("
                    UPDATE accounts 
                    SET balance = balance + ? 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$amount, $to_account_id, getCurrentUserId()]);
                
                // 记录转账记录
                $stmt = $db->prepare("
                    INSERT INTO transfers (
                        user_id, from_account_id, to_account_id, 
                        amount, description, transfer_date
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    getCurrentUserId(),
                    $from_account_id,
                    $to_account_id,
                    $amount,
                    $description,
                    $transfer_date
                ]);
                
                $db->commit();
                $success = true;
            } else {
                $error = '转出账户余额不足';
                $db->rollBack();
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = '转账失败，请重试';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="card">
        <h2>账户转账</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                转账成功！
                <a href="index.php">返回首页</a>
            </div>
        <?php else: ?>
            <form method="POST" action="" onsubmit="return validateTransfer(this);">
                <div class="form-group">
                    <label>转出账户：</label>
                    <select name="from_account_id" class="form-control" required>
                        <option value="">请选择转出账户</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>">
                                <?php echo htmlspecialchars($account['name']); ?> 
                                (余额: <?php echo formatAmount($account['balance']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>转入账户：</label>
                    <select name="to_account_id" class="form-control" required>
                        <option value="">请选择转入账户</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>">
                                <?php echo htmlspecialchars($account['name']); ?> 
                                (余额: <?php echo formatAmount($account['balance']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>转账金额：</label>
                    <input type="number" name="amount" class="form-control" required 
                           step="0.01" min="0.01">
                </div>
                
                <div class="form-group">
                    <label>转账日期：</label>
                    <input type="date" name="date" class="form-control" required 
                           value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>备注：</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">确认转账</button>
                    <a href="index.php" class="btn btn-secondary">取消</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
function validateTransfer(form) {
    if (form.from_account_id.value === form.to_account_id.value) {
        alert('转出和转入账户不能相同');
        return false;
    }
    return validateForm(form);
}
</script>

<?php require_once 'includes/footer.php'; ?> 