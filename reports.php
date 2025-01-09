<?php
require_once 'includes/config.php';
checkLogin();

$page_title = '报表统计';

// 获取统计周期
$period = $_GET['period'] ?? 'month';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// 根据周期设置日期范围
switch ($period) {
    case 'year':
        $start_date = "$year-01-01";
        $end_date = "$year-12-31";
        break;
    case 'month':
        $start_date = "$year-$month-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
}

$db = Database::getInstance()->getConnection();

// 获取收入统计
$stmt = $db->prepare("
    SELECT 
        ic.name as category_name,
        ic.color,
        SUM(i.amount) as total_amount,
        COUNT(*) as count
    FROM incomes i
    JOIN income_categories ic ON i.category_id = ic.id
    WHERE i.user_id = ? AND i.income_date BETWEEN ? AND ?
    GROUP BY i.category_id
    ORDER BY total_amount DESC
");
$stmt->execute([getCurrentUserId(), $start_date, $end_date]);
$income_stats = $stmt->fetchAll();

// 获取支出统计
$stmt = $db->prepare("
    SELECT 
        ec.name as category_name,
        ec.color,
        SUM(e.amount) as total_amount,
        COUNT(*) as count
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
    GROUP BY e.category_id
    ORDER BY total_amount DESC
");
$stmt->execute([getCurrentUserId(), $start_date, $end_date]);
$expense_stats = $stmt->fetchAll();

// 计算总收支
$total_income = array_sum(array_column($income_stats, 'total_amount'));
$total_expense = array_sum(array_column($expense_stats, 'total_amount'));
$net_income = $total_income - $total_expense;

// 获取每日收支趋势
$stmt = $db->prepare("
    SELECT 
        date,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income_amount,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense_amount
    FROM (
        SELECT 'income' as type, income_date as date, amount 
        FROM incomes 
        WHERE user_id = ? AND income_date BETWEEN ? AND ?
        UNION ALL
        SELECT 'expense' as type, expense_date as date, amount 
        FROM expenses 
        WHERE user_id = ? AND expense_date BETWEEN ? AND ?
    ) t
    GROUP BY date
    ORDER BY date
");
$stmt->execute([getCurrentUserId(), $start_date, $end_date, getCurrentUserId(), $start_date, $end_date]);
$daily_trends = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h2>报表统计</h2>
        
        <!-- 周期选择 -->
        <form method="GET" class="period-selector">
            <select name="period" class="form-control" onchange="this.form.submit()">
                <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>月度报表</option>
                <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>年度报表</option>
            </select>
            
            <?php if ($period == 'year'): ?>
                <select name="year" class="form-control" onchange="this.form.submit()">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>年
                        </option>
                    <?php endfor; ?>
                </select>
            <?php else: ?>
                <select name="year" class="form-control" onchange="this.form.submit()">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>年
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="month" class="form-control" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo sprintf('%02d', $m); ?>" 
                                <?php echo $month == sprintf('%02d', $m) ? 'selected' : ''; ?>>
                            <?php echo $m; ?>月
                        </option>
                    <?php endfor; ?>
                </select>
            <?php endif; ?>
        </form>
    </div>

    <!-- 收支概览 -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>总收入</h3>
            <div class="amount income"><?php echo formatAmount($total_income); ?></div>
        </div>
        <div class="stat-card">
            <h3>总支出</h3>
            <div class="amount expense"><?php echo formatAmount($total_expense); ?></div>
        </div>
        <div class="stat-card">
            <h3>净收入</h3>
            <div class="amount <?php echo $net_income >= 0 ? 'income' : 'expense'; ?>">
                <?php echo formatAmount($net_income); ?>
            </div>
        </div>
    </div>

    <!-- 收支趋势图 -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>收支趋势</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- 收入分类统计 -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>收入分类统计</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="incomePieChart"></canvas>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>分类</th>
                                    <th>金额</th>
                                    <th>占比</th>
                                    <th>笔数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($income_stats as $stat): ?>
                                    <tr>
                                        <td>
                                            <span class="color-dot" 
                                                  style="background-color: <?php echo $stat['color']; ?>"></span>
                                            <?php echo htmlspecialchars($stat['category_name']); ?>
                                        </td>
                                        <td><?php echo formatAmount($stat['total_amount']); ?></td>
                                        <td><?php echo round($stat['total_amount'] / $total_income * 100, 1); ?>%</td>
                                        <td><?php echo $stat['count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 支出分类统计 -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>支出分类统计</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="expensePieChart"></canvas>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>分类</th>
                                    <th>金额</th>
                                    <th>占比</th>
                                    <th>笔数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expense_stats as $stat): ?>
                                    <tr>
                                        <td>
                                            <span class="color-dot" 
                                                  style="background-color: <?php echo $stat['color']; ?>"></span>
                                            <?php echo htmlspecialchars($stat['category_name']); ?>
                                        </td>
                                        <td><?php echo formatAmount($stat['total_amount']); ?></td>
                                        <td><?php echo round($stat['total_amount'] / $total_expense * 100, 1); ?>%</td>
                                        <td><?php echo $stat['count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// 收支趋势图
const trendData = <?php echo json_encode($daily_trends); ?>;
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trendData.map(item => item.date),
        datasets: [{
            label: '收入',
            data: trendData.map(item => item.income_amount),
            borderColor: 'rgb(46, 204, 113)',
            tension: 0.1
        }, {
            label: '支出',
            data: trendData.map(item => item.expense_amount),
            borderColor: 'rgb(231, 76, 60)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// 收入饼图
const incomeData = <?php echo json_encode($income_stats); ?>;
new Chart(document.getElementById('incomePieChart'), {
    type: 'pie',
    data: {
        labels: incomeData.map(item => item.category_name),
        datasets: [{
            data: incomeData.map(item => item.total_amount),
            backgroundColor: incomeData.map(item => item.color)
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// 支出饼图
const expenseData = <?php echo json_encode($expense_stats); ?>;
new Chart(document.getElementById('expensePieChart'), {
    type: 'pie',
    data: {
        labels: expenseData.map(item => item.category_name),
        datasets: [{
            data: expenseData.map(item => item.total_amount),
            backgroundColor: expenseData.map(item => item.color)
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>

<?php require_once 'includes/footer.php'; ?> 