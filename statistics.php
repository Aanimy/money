<?php
require_once 'includes/config.php';
checkLogin();

$page_title = '统计分析';
$user_id = getCurrentUserId();

try {
    $db = Database::getInstance()->getConnection();
    
    // 获取本月收支统计
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    
    // 收入统计
    $stmt = $db->prepare("
        SELECT SUM(amount) as total,
               COUNT(*) as count
        FROM incomes
        WHERE user_id = ? 
        AND income_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $month_start, $month_end]);
    $income_stats = $stmt->fetch();
    
    // 支出统计
    $stmt = $db->prepare("
        SELECT SUM(amount) as total,
               COUNT(*) as count
        FROM expenses
        WHERE user_id = ? 
        AND expense_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user_id, $month_start, $month_end]);
    $expense_stats = $stmt->fetch();
    
    // 分类统计
    $stmt = $db->prepare("
        SELECT c.name, c.color, SUM(e.amount) as total
        FROM expenses e
        JOIN expense_categories c ON e.category_id = c.id
        WHERE e.user_id = ? 
        AND e.expense_date BETWEEN ? AND ?
        GROUP BY e.category_id
        ORDER BY total DESC
    ");
    $stmt->execute([$user_id, $month_start, $month_end]);
    $category_stats = $stmt->fetchAll();
    
} catch (Exception $e) {
    handleError('获取统计数据失败');
}

require_once 'includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- 本月概览 -->
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3>本月概览</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stats-card text-success">
                                <h4>收入</h4>
                                <div class="amount">¥<?php echo number_format($income_stats['total'] ?? 0, 2); ?></div>
                                <small><?php echo $income_stats['count'] ?? 0; ?> 笔交易</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card text-danger">
                                <h4>支出</h4>
                                <div class="amount">¥<?php echo number_format($expense_stats['total'] ?? 0, 2); ?></div>
                                <small><?php echo $expense_stats['count'] ?? 0; ?> 笔交易</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card text-primary">
                                <h4>结余</h4>
                                <div class="amount">¥<?php echo number_format(($income_stats['total'] ?? 0) - ($expense_stats['total'] ?? 0), 2); ?></div>
                            </div>
                        </div>
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
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- 收支趋势 -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>收支趋势</h3>
                </div>
                <div class="card-body">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// 支出分类饼图
new Chart(document.getElementById('categoryChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($category_stats, 'name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($category_stats, 'total')); ?>,
            backgroundColor: <?php echo json_encode(array_column($category_stats, 'color')); ?>
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// 收支趋势图
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: ['1日', '7日', '14日', '21日', '28日'],
        datasets: [{
            label: '收入',
            data: [0, <?php echo $income_stats['total'] ?? 0; ?>, 0, 0, 0],
            borderColor: '#52C41A',
            fill: false
        }, {
            label: '支出',
            data: [0, <?php echo $expense_stats['total'] ?? 0; ?>, 0, 0, 0],
            borderColor: '#F5222D',
            fill: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>

<?php require_once 'includes/footer.php'; ?> 