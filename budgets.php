<?php
require_once 'includes/config.php';
checkLogin();

$page_title = '预算管理';
$error = '';
$success = '';

// 获取支出分类
$expense_categories = getExpenseCategories();

// 处理添加/编辑预算
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $budget_id = isset($_POST['id']) ? $_POST['id'] : null;
    $category_id = $_POST['category_id'];
    $amount = floatval($_POST['amount']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    if ($amount <= 0) {
        $error = '请输入有效的预算金额';
    } else {
        $db = Database::getInstance()->getConnection();
        try {
            if ($budget_id) {
                // 更新预算
                $stmt = $db->prepare("
                    UPDATE budgets 
                    SET category_id = ?, amount = ?, start_date = ?, end_date = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$category_id, $amount, $start_date, $end_date, $budget_id, getCurrentUserId()]);
                $success = '预算已更新';
            } else {
                // 检查是否已存在相同分类和日期范围的预算
                $stmt = $db->prepare("
                    SELECT id FROM budgets 
                    WHERE user_id = ? AND category_id = ?
                    AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?))
                ");
                $stmt->execute([getCurrentUserId(), $category_id, $start_date, $end_date, $start_date, $end_date]);
                
                if ($stmt->fetch()) {
                    $error = '该分类在所选时间范围内已存在预算';
                } else {
                    // 添加新预算
                    $stmt = $db->prepare("
                        INSERT INTO budgets (user_id, category_id, amount, start_date, end_date)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([getCurrentUserId(), $category_id, $amount, $start_date, $end_date]);
                    $success = '预算已添加';
                }
            }
        } catch (Exception $e) {
            $error = '操作失败，请重试';
        }
    }
}

// 处理删除预算
if (isset($_GET['delete'])) {
    $budget_id = $_GET['delete'];
    $db = Database::getInstance()->getConnection();
    try {
        $stmt = $db->prepare("DELETE FROM budgets WHERE id = ? AND user_id = ?");
        $stmt->execute([$budget_id, getCurrentUserId()]);
        $success = '预算已删除';
    } catch (Exception $e) {
        $error = '删除失败，请重试';
    }
}

// 获取当前预算列表
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT b.*, ec.name as category_name, ec.icon, ec.color,
           (SELECT SUM(amount) FROM expenses e 
            WHERE e.category_id = b.category_id 
            AND e.expense_date BETWEEN b.start_date AND b.end_date
            AND e.user_id = b.user_id) as used_amount
    FROM budgets b
    JOIN expense_categories ec ON b.category_id = ec.id
    WHERE b.user_id = ?
    ORDER BY b.start_date DESC
");
$stmt->execute([getCurrentUserId()]);
$budgets = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h2>预算管理</h2>
        <button class="btn btn-primary" onclick="showAddBudgetForm()">
            <i class="fas fa-plus"></i> 添加预算
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- 预算列表 -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>分类</th>
                        <th>预算金额</th>
                        <th>已使用</th>
                        <th>剩余</th>
                        <th>使用进度</th>
                        <th>有效期</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budgets as $budget): ?>
                        <?php
                        $used = floatval($budget['used_amount']) ?? 0;
                        $remaining = $budget['amount'] - $used;
                        $percentage = min(100, ($used / $budget['amount']) * 100);
                        $status_class = $percentage >= 100 ? 'danger' : 
                                      ($percentage >= 80 ? 'warning' : 'success');
                        ?>
                        <tr>
                            <td>
                                <i class="fas fa-<?php echo $budget['icon']; ?>" 
                                   style="color: <?php echo $budget['color']; ?>"></i>
                                <?php echo htmlspecialchars($budget['category_name']); ?>
                            </td>
                            <td><?php echo formatAmount($budget['amount']); ?></td>
                            <td><?php echo formatAmount($used); ?></td>
                            <td class="text-<?php echo $status_class; ?>">
                                <?php echo formatAmount($remaining); ?>
                            </td>
                            <td>
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $status_class; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%">
                                        <?php echo round($percentage); ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo $budget['start_date']; ?> 至 
                                <?php echo $budget['end_date']; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-secondary" 
                                        onclick="editBudget(<?php echo htmlspecialchars(json_encode($budget)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="deleteBudget(<?php echo $budget['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 添加/编辑预算表单 -->
<div class="modal" id="budgetModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" onsubmit="return validateForm(this);">
                <input type="hidden" name="id" id="budget_id">
                
                <div class="modal-header">
                    <h4 class="modal-title" id="modalTitle">添加预算</h4>
                    <button type="button" class="close" onclick="closeModal()">&times;</button>
                </div>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>支出分类：</label>
                        <select name="category_id" id="budget_category" class="form-control" required>
                            <option value="">请选择分类</option>
                            <?php foreach ($expense_categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>预算金额：</label>
                        <input type="number" name="amount" id="budget_amount" 
                               class="form-control" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label>开始日期：</label>
                        <input type="date" name="start_date" id="budget_start_date" 
                               class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>结束日期：</label>
                        <input type="date" name="end_date" id="budget_end_date" 
                               class="form-control" required>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAddBudgetForm() {
    document.getElementById('modalTitle').textContent = '添加预算';
    document.getElementById('budget_id').value = '';
    document.getElementById('budget_category').value = '';
    document.getElementById('budget_amount').value = '';
    document.getElementById('budget_start_date').value = '';
    document.getElementById('budget_end_date').value = '';
    document.getElementById('budgetModal').style.display = 'block';
}

function editBudget(budget) {
    document.getElementById('modalTitle').textContent = '编辑预算';
    document.getElementById('budget_id').value = budget.id;
    document.getElementById('budget_category').value = budget.category_id;
    document.getElementById('budget_amount').value = budget.amount;
    document.getElementById('budget_start_date').value = budget.start_date;
    document.getElementById('budget_end_date').value = budget.end_date;
    document.getElementById('budgetModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('budgetModal').style.display = 'none';
}

async function deleteBudget(id) {
    if (await confirmAction('确定要删除这个预算吗？')) {
        window.location.href = `?delete=${id}`;
    }
}

// 点击模态框外部时关闭
window.onclick = function(event) {
    if (event.target == document.getElementById('budgetModal')) {
        closeModal();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?> 