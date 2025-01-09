<?php
$error_message = $_SESSION['error_message'] ?? '未知错误';
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>错误 - <?php echo SITE_NAME; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="error-container">
        <h1>出错了！</h1>
        <p><?php echo htmlspecialchars($error_message); ?></p>
        <a href="javascript:history.back()" class="btn btn-primary">返回上一页</a>
    </div>
</body>
</html> 