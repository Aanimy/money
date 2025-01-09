<?php
require_once 'includes/config.php';
checkLogin();

$page_title = '控制面板';
$user_id = getCurrentUserId();

try {
    $db = Database::getInstance()->getConnection();
    // 获取账户信息
    $stmt = $db->prepare("SELECT * FROM accounts WHERE user_id = ? ORDER BY name");
    $stmt->execute([$user_id]);
    $accounts = $stmt->fetchAll();

    // 计算总资产
    $total_assets = array_sum(array_column($accounts, 'balance'));

    // 获取本月收支统计
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');

    $stmt = $db->prepare("
        SELECT SUM(amount) as total 
        FROM incomes 
        WHERE user_id = ? AND income_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $month_start, $month_end]);
    $month_income = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT SUM(amount) as total 
        FROM expenses 
        WHERE user_id = ? AND expense_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $month_start, $month_end]);
    $month_expense = $stmt->fetch()['total'] ?? 0;

    // 获取最近交易记录
    $stmt = $db->prepare("
        (SELECT 'income' as type, i.amount, i.income_date as date, 
                ic.name as category, ic.icon, ic.color, a.name as account_name
         FROM incomes i
         JOIN income_categories ic ON i.category_id = ic.id
         JOIN accounts a ON i.account_id = a.id
         WHERE i.user_id = ?)
        UNION ALL
        (SELECT 'expense' as type, e.amount, e.expense_date as date,
                ec.name as category, ec.icon, ec.color, a.name as account_name
         FROM expenses e
         JOIN expense_categories ec ON e.category_id = ec.id
         JOIN accounts a ON e.account_id = a.id
         WHERE e.user_id = ?)
        ORDER BY date DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id, $user_id]);
    $recent_transactions = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    handleError('加载数据失败，请稍后重试', 'index.php');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>控制面板 - <?php echo SITE_NAME; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <?php require_once 'includes/header.php'; ?>

    <div class="container py-4">
        <!-- 欢迎信息 -->
        <div class="welcome-banner">
            <h1>欢迎回来，<?php echo htmlspecialchars($_SESSION['username']); ?></h1>
            <p><?php echo date('Y年m月d日'); ?></p>
        </div>

        <!-- 资产概览 -->
        <div class="row">
            <div class="col-md-4">
                <div class="overview-card total-assets">
                    <div class="card-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="card-content">
                        <h3>总资产</h3>
                        <div class="amount">¥<?php echo number_format($total_assets, 2); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="overview-card month-income">
                    <div class="card-icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="card-content">
                        <h3>本月收入</h3>
                        <div class="amount">¥<?php echo number_format($month_income, 2); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="overview-card month-expense">
                    <div class="card-icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="card-content">
                        <h3>本月支出</h3>
                        <div class="amount">¥<?php echo number_format($month_expense, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- 账户列表 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2>我的账户</h2>
                        <a href="accounts.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> 添加账户
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="account-list">
                            <?php foreach ($accounts as $account): ?>
                                <div class="account-item">
                                    <div class="account-info">
                                        <h4><?php echo htmlspecialchars($account['name']); ?></h4>
                                        <small class="text-muted"><?php echo htmlspecialchars($account['description']); ?></small>
                                    </div>
                                    <div class="account-balance">
                                        ¥<?php echo number_format($account['balance'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 最近交易 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2>最近交易</h2>
                        <div>
                            <a href="add_income.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus"></i> 收入
                            </a>
                            <a href="add_expense.php" class="btn btn-sm btn-danger">
                                <i class="fas fa-minus"></i> 支出
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="transaction-list">
                            <?php foreach ($recent_transactions as $trans): ?>
                                <div class="transaction-item">
                                    <div class="transaction-icon" style="background-color: <?php echo $trans['color']; ?>">
                                        <i class="fas fa-<?php echo $trans['icon']; ?>"></i>
                                    </div>
                                    <div class="transaction-info">
                                        <h4><?php echo htmlspecialchars($trans['category']); ?></h4>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($trans['account_name']); ?> · 
                                            <?php echo date('m-d H:i', strtotime($trans['date'])); ?>
                                        </small>
                                    </div>
                                    <div class="transaction-amount <?php echo $trans['type']; ?>">
                                        <?php echo $trans['type'] == 'income' ? '+' : '-'; ?>
                                        ¥<?php echo number_format($trans['amount'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html> 