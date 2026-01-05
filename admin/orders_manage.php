<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

// Filters
$search         = trim($_GET['q'] ?? '');
$orderStatus    = $_GET['order_status'] ?? '';
$paymentStatus  = $_GET['payment_status'] ?? '';

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

/* ----------------------------------------
   DELETE ORDER
---------------------------------------- */
if (isset($_GET["delete"])) {
    $id = (int) $_GET["delete"];

    // Order Items auto-delete  ON DELETE CASCADE
    $conn->prepare("DELETE FROM orders WHERE id=?")->execute([$id]);

    header("Location: orders_manage.php?msg=deleted");
    exit();
}

/* ----------------------------------------
   QUICK UPDATE STATUSES
---------------------------------------- */
if (isset($_POST['quick_update'])) {
    $id            = (int)$_POST['order_id'];
    $newOrderStat  = $_POST['order_status'] ?? '';
    $newPayStat    = $_POST['payment_status'] ?? '';

    $validOrder = ['pending','processing','completed','cancelled','refunded'];
    $validPay   = ['pending','paid','failed','refunded'];

    if (in_array($newOrderStat, $validOrder, true) && in_array($newPayStat, $validPay, true)) {
        $upd = $conn->prepare("
            UPDATE orders
            SET order_status = :os, payment_status = :ps
            WHERE id = :id
        ");
        $upd->execute([':os'=>$newOrderStat, ':ps'=>$newPayStat, ':id'=>$id]);
        header("Location: orders_manage.php?msg=updated");
        exit();
    }
}

/* ----------------------------------------
   FETCH ALL ORDERS
---------------------------------------- */
$where  = [];
$params = [];
if ($search !== '') {
    $where[] = "(o.order_code LIKE :q OR u.name LIKE :q OR u.email LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($orderStatus !== '' && in_array($orderStatus, ['pending','processing','completed','cancelled','refunded'], true)) {
    $where[] = "o.order_status = :os";
    $params[':os'] = $orderStatus;
}
if ($paymentStatus !== '' && in_array($paymentStatus, ['pending','paid','failed','refunded'], true)) {
    $where[] = "o.payment_status = :ps";
    $params[':ps'] = $paymentStatus;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countSql = "
    SELECT COUNT(*)
    FROM orders o
    JOIN users u ON o.user_id = u.id
    $whereSql
";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "
    SELECT o.*, u.name AS customer_name, u.email AS customer_email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    $whereSql
    ORDER BY o.id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Orders Management";
$pageSubtitle = "Manage customer orders for Dokebi Tekoku";
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?> | Dokebi Tekoku</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php include "theme.php"; ?>
</head>

<body>

<div id="alertBox" class="alert-box"></div>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="dashboard.php">Dashboard</a>
    <a href="products_manage.php">Products</a>
    <a href="main_categories_management.php">Main Categories</a>
    <a href="categories_management.php">Categories</a>
    <a href="users_manage.php">Users</a>
    <a href="orders_manage.php" class="active-link">Orders</a>
    <a href="payments_manage.php">Payments</a>
    <a href="reviews_manage.php">Reviews</a>
    <a href="settings.php">Settings</a>
    <a href="../auth/logout.php" style="color:#ff3b3b;">Logout</a>
</div>

<!-- TOPBAR -->
<div class="topbar">
    <div>
        <strong style="font-size:20px;"><?= $pageTitle ?></strong>
        <p style="margin:0; opacity:0.8;"><?= $pageSubtitle ?></p>
    </div>

    <div class="switch-container">
        
        <div class="switch-toggle" onclick="toggleMode()"></div>
    </div>
</div>

<!-- CONTENT -->
<div class="content">

<form method="get" class="filter-bar">
    <input class="filter-input" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search order code, customer...">
    <select class="filter-select" name="order_status">
        <option value="">All Order Status</option>
        <?php foreach (['pending','processing','completed','cancelled','refunded'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $orderStatus===$opt?'selected':''; ?>><?= ucfirst($opt) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="payment_status">
        <option value="">All Payment Status</option>
        <?php foreach (['pending','paid','failed','refunded'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $paymentStatus===$opt?'selected':''; ?>><?= ucfirst($opt) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn-orange" type="submit">Apply</button>
    <?php if ($search !== '' || $orderStatus !== '' || $paymentStatus !== ''): ?>
        <a href="orders_manage.php" class="btn-ghost">Clear Filters</a>
    <?php endif; ?>
</form>

<table>
    <tr>
        <th>ID</th>
        <th>Order Code</th>
        <th>Customer</th>
        <th>Email</th>
        <th>Total (MMK)</th>
        <th>Payment Status</th>
        <th>Order Status</th>
        <th>Quick Update</th>
        <th>Actions</th>
    </tr>

    <?php if (!$orders): ?>
        <tr><td colspan="9" style="text-align:center;padding:14px;">No orders found.</td></tr>
    <?php endif; ?>

    <?php foreach ($orders as $o): ?>
    <tr>
        <td><?= $o['id'] ?></td>
        <td><?= htmlspecialchars($o['order_code']) ?></td>
        <td><?= htmlspecialchars($o['customer_name']) ?></td>
        <td><?= htmlspecialchars($o['customer_email']) ?></td>
        <td><?= number_format($o['total_amount'],2) ?></td>

        <td><?= ucfirst($o['payment_status']) ?></td>
        <td><?= ucfirst($o['order_status']) ?></td>

        <td>
            <form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <select name="order_status" class="filter-select select-compact">
                    <?php foreach (['pending','processing','completed','cancelled','refunded'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $o['order_status']===$opt?'selected':''; ?>><?= ucfirst($opt) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="payment_status" class="filter-select select-compact">
                    <?php foreach (['pending','paid','failed','refunded'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $o['payment_status']===$opt?'selected':''; ?>><?= ucfirst($opt) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="action-btn btn-edit" type="submit" name="quick_update">Save</button>
            </form>
        </td>

        <td>
            <div class="action-stack">
                <a href="order_view.php?id=<?= $o['id'] ?>" 
                   class="action-btn btn-edit">View</a>

                <a href="?delete=<?= $o['id'] ?>" 
                   class="action-btn btn-delete"
                   onclick="return confirm('Delete this order?');">
                   Delete
                </a>
            </div>
        </td>
    </tr>
        <?php endforeach; ?>
</table>

<?php if ($totalPages > 1): ?>
<div style="margin-top:14px; display:flex; gap:8px; align-items:center;">
    <?php if ($page > 1): ?>
        <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">Prev</a>
    <?php endif; ?>
    <span>Page <?= $page ?> of <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
        <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>

</div>

<?php
if (isset($_GET["msg"]) && $_GET["msg"] === "deleted") {
    echo "<script>showAlert('Order Deleted Successfully');</script>";
}
if (isset($_GET["msg"]) && $_GET["msg"] === "updated") {
    echo "<script>showAlert('Order statuses updated');</script>";
}
?>

</body>
</html>
