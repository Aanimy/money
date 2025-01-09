<?php
require_once 'includes/config.php';
checkLogin();

$page_title = '交易记录';
$error = '';
$success = '';

// 获取筛选参数
$type = $_GET['type'] ?? 'all';
$account_id = $_GET['account'] ?? '';
$category_id = $_GET['category'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 获取用户的账户列表
$accounts = getUserAccounts(getCurrentUserId());
// 获取分类列表
$income_categories = getIncomeCategories();
$expense_categories = getExpenseCategories();

// 构建查询条件
$conditions = ['user_id = ?'];
$params = [getCurrentUserId()];

if ($account_id) {
    $conditions[] = 'account_id = ?';
    $params[] = $account_id;
}

if ($category_id) {
    $conditions[] = 'category_id = ?';
    $params[] = $category_id;
}

$date_condition = '';
if ($start_date && $end_date) {
    $date_condition = 'BETWEEN ? AND ?';
    $params[] = $start_date;
    $params[] = $end_date;
} elseif ($start_date) {
    $date_condition = '>= ?';
    $params[] = $start_date;
} elseif ($end_date) {
    $date_condition = '<= ?';
    $params[] = $end_date;
}

// 获取交易记录
$db = Database::getInstance()->getConnection();
$transactions = [];

if ($type != 'expense') {
    // 获取收入记录
    $income_sql = "
        SELECT 
            'income' as type,
            i.id,
            i.amount,
            i.description,
            i.income_date as date,
            a.name as account_name,
            ic.name as category_name,
            ic.icon,
            ic.color
        FROM incomes i
        JOIN accounts a ON i.account_id = a.id
        JOIN income_categories ic ON i.category_id = ic.id
        WHERE " . implode(' AND ', $conditions) . 
        ($date_condition ? " AND i.income_date {$date_condition}" : "") . "
        ORDER BY i.income_date DESC, i.id DESC
    ";
    $stmt = $db->prepare($income_sql);
    $stmt->execute($params);
    $transactions = array_merge($transactions, $stmt->fetchAll());
}

if ($type != 'income') {
    // 获取支出记录
    $expense_sql = "
        SELECT 
            'expense' as type,
            e.id,
            e.amount,
            e.description,
            e.expense_date as date,
            a.name as account_name,
            ec.name as category_name,
            ec.icon,
            ec.color
        FROM expenses e
        JOIN accounts a ON e.account_id = a.id
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE " . implode(' AND ', $conditions) . 
        ($date_condition ? " AND e.expense_date {$date_condition}" : "") . "
        ORDER BY e.expense_date DESC, e.id DESC
    ";
    $stmt = $db->prepare($expense_sql);
    $stmt->execute($params);
    $transactions = array_merge($transactions, $stmt->fetchAll());
}

// 按日期排序
usort($transactions, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});

require_once 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h2>交易记录</h2>
    </div>

    <!-- 筛选表单 -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>类型：</label>
                        <select name="type" class="form-control">
                            <option value="all" <?php echo $type == 'all' ? 'selected' : ''; ?>>全部</option>
                            <option value="income" <?php echo $type == 'income' ? 'selected' : ''; ?>>收入</option>
                            <option value="expense" <?php echo $type == 'expense' ? 'selected' : ''; ?>>支出</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="form-group">
                        <label>账户：</label>
                        <select name="account" class="form-control">
                            <option value="">全部账户</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>" 
                                        <?php echo $account_id == $account['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($account['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="form-group">
                        <label>日期范围：</label>
                        <div class="input-group">
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?php echo $start_date; ?>">
                            <div class="input-group-text">至</div>
                            <input type="date" name="end_date" class="form-control" 
                                   value="<?php echo $end_date; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block">筛选</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 交易记录列表 -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>日期</th>
                        <th>类型</th>
                        <th>分类</th>
                        <th>账户</th>
                        <th>金额</th>
                        <th>备注</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo $transaction['date']; ?></td>
                            <td><?php echo $transaction['type'] == 'income' ? '收入' : '支出'; ?></td>
                            <td>
                                <i class="fas fa-<?php echo $transaction['icon']; ?>" 
                                   style="color: <?php echo $transaction['color']; ?>"></i>
                                <?php echo htmlspecialchars($transaction['category_name']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['account_name']); ?></td>
                            <td class="amount <?php echo $transaction['type']; ?>">
                                <?php echo $transaction['type'] == 'income' ? '+' : '-'; ?>
                                <?php echo formatAmount($transaction['amount']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 