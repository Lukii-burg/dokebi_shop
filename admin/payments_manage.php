<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

// Filters
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$method = trim($_GET['method'] ?? '');

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

/* ----------------------------------------
   DELETE PAYMENT (optional)
---------------------------------------- */
if (isset($_GET["delete"])) {
    $id = (int) $_GET["delete"];

    $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: payments_manage.php?msg=deleted");
    exit();
}

/* ----------------------------------------
   QUICK UPDATE PAYMENT STATUS
---------------------------------------- */
if (isset($_POST['update_payment'])) {
    $pid       = (int)$_POST['payment_id'];
    $statusNew = $_POST['status'] ?? '';
    $valid     = ['pending','success','failed'];
    if ($pid > 0 && in_array($statusNew, $valid, true)) {
        $paidAt = ($statusNew === 'success') ? date('Y-m-d H:i:s') : null;
        $upd = $conn->prepare("UPDATE payments SET status = :st, paid_at = COALESCE(paid_at, :paidAt) WHERE id = :id");
        $upd->execute([':st'=>$statusNew, ':paidAt'=>$paidAt, ':id'=>$pid]);

        // Sync order payment_status to keep consistency
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId > 0) {
            $map = ['pending'=>'pending', 'success'=>'paid', 'failed'=>'failed'];
            $mapped = $map[$statusNew] ?? 'pending';
            $conn->prepare("UPDATE orders SET payment_status = :ps WHERE id = :oid")
                 ->execute([':ps'=>$mapped, ':oid'=>$orderId]);
        }
        header("Location: payments_manage.php?msg=updated");
        exit();
    }
}

/* ----------------------------------------
   FETCH PAYMENTS + ORDER + USER INFO
---------------------------------------- */
$where  = [];
$params = [];
if ($search !== '') {
    $where[] = "(o.order_code LIKE :q OR u.name LIKE :q OR u.email LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($status !== '' && in_array($status, ['pending','success','failed'], true)) {
    $where[] = "p.status = :status";
    $params[':status'] = $status;
}
if ($method !== '') {
    $where[] = "p.method LIKE :method";
    $params[':method'] = '%' . $method . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countSql = "
    SELECT COUNT(*)
    FROM payments p
    LEFT JOIN orders o ON p.order_id = o.id
    LEFT JOIN users  u ON o.user_id = u.id
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
    SELECT 
        p.*,
        o.order_code,
        o.user_id,
        u.name  AS customer_name,
        u.email AS customer_email
    FROM payments p
    LEFT JOIN orders o ON p.order_id = o.id
    LEFT JOIN users  u ON o.user_id = u.id
    $whereSql
    ORDER BY p.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle    = "Payments Management";
$pageSubtitle = "View and manage payment records for Dokebi Tekoku";
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
    <a href="orders_manage.php">Orders</a>
    <a href="payments_manage.php" class="active-link">Payments</a>
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
    <select class="filter-select" name="method">
        <option value="">All Methods</option>
        <?php
            $methods = ['KBZPay','Wave Pay','Aya Pay','Visa/Mastercard','MPU'];
            foreach ($methods as $m):
        ?>
            <option value="<?= htmlspecialchars($m) ?>" <?= $method===$m?'selected':''; ?>><?= htmlspecialchars($m) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" name="status">
        <option value="">All Status</option>
        <option value="pending" <?= $status==='pending'?'selected':''; ?>>Pending</option>
        <option value="success" <?= $status==='success'?'selected':''; ?>>Success</option>
        <option value="failed"  <?= $status==='failed'?'selected':''; ?>>Failed</option>
    </select>
    <button class="btn-orange" type="submit">Apply</button>
    <?php if ($search !== '' || $status !== '' || $method !== ''): ?>
        <a href="payments_manage.php" class="btn-ghost">Clear Filters</a>
    <?php endif; ?>
</form>

    <table>
        <tr>
            <th>ID</th>
            <th>Order Code</th>
            <th>Customer</th>
            <th>Email</th>
            <th>Amount (MMK)</th>
            <th>Method</th>
            <th>Status</th>
            <th>Paid At</th>
            <th>Actions</th>
        </tr>

        <?php if (!$payments): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:12px 0;">No payment records yet.</td></tr>
        <?php endif; ?>

        <?php foreach ($payments as $p): ?>

        <?php
            // Status color
            $status = $p["status"];
            $color  = ($status == "success") ? "#4ef0c2" :
                      (($status == "pending") ? "#ffbe42" :
                      (($status == "failed") ? "#ff4c4c" : "#888"));
        ?>

        <tr>
            <td><?= $p["id"] ?></td>

            <td><strong><?= htmlspecialchars($p["order_code"]) ?></strong></td>

            <td><?= htmlspecialchars($p["customer_name"]) ?></td>

            <td><?= htmlspecialchars($p["customer_email"]) ?></td>

            <td><?= number_format($p["amount"], 2) ?></td>

            <td><?= htmlspecialchars($p["method"]) ?></td>

            <td>
                <span style="color:<?= $color ?>;font-weight:600;">
                    <?= ucfirst($status) ?>
                </span>
            </td>

            <td>
                <?= $p["paid_at"] ? htmlspecialchars($p["paid_at"]) : "-" ?>
            </td>

            <td>
                <form method="post" class="payment-actions">
                    <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="order_id" value="<?= $p['order_id'] ?>">
                    <div class="pay-col">
                        <select name="status" class="filter-select select-compact">
                            <option value="pending" <?= $status==='pending'?'selected':''; ?>>Pending</option>
                            <option value="success" <?= $status==='success'?'selected':''; ?>>Success</option>
                            <option value="failed"  <?= $status==='failed'?'selected':''; ?>>Failed</option>
                        </select>
                        <a href="order_view.php?id=<?= $p['order_id'] ?>" 
                           class="action-btn btn-edit">
                            View
                        </a>
                    </div>
                    <div class="pay-col">
                        <button class="action-btn btn-edit" type="submit" name="update_payment">Save</button>
                        <a href="?delete=<?= $p['id'] ?>"
                           class="action-btn btn-delete"
                           onclick="return confirm('Delete this payment record?');">
                            Delete
                        </a>
                    </div>
                </form>
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
    echo "<script>showAlert('Payment Record Deleted Successfully');</script>";
}
if (isset($_GET["msg"]) && $_GET["msg"] === "updated") {
    echo "<script>showAlert('Payment status updated');</script>";
}
?>

</body>
</html>

