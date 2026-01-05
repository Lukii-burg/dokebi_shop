<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* ----------------------------
   FILTER LOGIC
---------------------------- */
$statusFilter = $_GET['status'] ?? '';
$allowed = ['pending','approved','rejected'];
$where = '';
$params = [];

if (in_array($statusFilter, $allowed, true)) {
    $where = "WHERE r.status = :status";
    $params[':status'] = $statusFilter;
}

$stmt = $conn->prepare("
    SELECT r.id, r.product_id, r.rating, r.comment, r.status, r.created_at,
           u.name AS user_name, u.email AS user_email,
           p.product_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN products p ON r.product_id = p.id
    $where
    ORDER BY r.created_at DESC
");
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Product Reviews | Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php include "theme.php"; ?>
</head>

<body class="cat-body">

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="dashboard.php">Dashboard</a>
    <a href="products_manage.php">Products</a>
    <a href="main_categories_management.php">Main Categories</a>
    <a href="categories_management.php">Categories</a>
    <a href="users_manage.php">Users</a>
    <a href="orders_manage.php">Orders</a>
    <a href="payments_manage.php">Payments</a>
    <a href="reviews_manage.php" class="active-link">Reviews</a>
    <a href="settings.php">Settings</a>
    <a href="../auth/logout.php" style="color:#ff4c4c;">Logout</a>
</div>

<!-- MAIN AREA -->
<div class="content">

    <!-- TOP BAR -->
    <div class="page-title-bar">
        <h2>Product Reviews</h2>
    </div>


    <!-- CARD -->
    <div class="card wide-card" style="padding:25px; border-radius:18px;">
        <h3 style="margin-bottom:18px;">Recent Reviews</h3>

        <!-- FILTER BAR -->
        <form method="get" class="filter-bar" style="margin-bottom:18px;">
            <label>Status:</label>

            <select name="status" class="filter-select select-compact" style="min-width:130px;">
                <option value="">All</option>
                <option value="pending"  <?= $statusFilter==='pending'?'selected':''; ?>>Pending</option>
                <option value="approved" <?= $statusFilter==='approved'?'selected':''; ?>>Approved</option>
                <option value="rejected" <?= $statusFilter==='rejected'?'selected':''; ?>>Rejected</option>
            </select>

            <button class="btn-orange" type="submit">Filter</button>

            <?php if ($statusFilter): ?>
            <a class="btn-ghost" href="reviews_manage.php">Clear</a>
            <?php endif; ?>
        </form>

        <!-- REVIEWS TABLE -->
        <?php if (!$reviews): ?>
            <div class="notice">No reviews found.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Action</th>
                        <th>Created</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($reviews as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['product_name'] ?? '') ?></td>

                        <td><span class="rating-badge"><?= $r['rating'] ?>★</span></td>

                        <td class="comment-cell"><?= nl2br(htmlspecialchars($r['comment'])) ?></td>

                        <td>
                            <?= htmlspecialchars($r['user_name']) ?><br>
                            <span style="opacity:.7; font-size:13px;">
                                <?= htmlspecialchars($r['user_email']) ?>
                            </span>
                        </td>

                        <td>
                            <span class="status-pill <?= $r['status'] ?>">
                                <?= ucfirst($r['status']) ?>
                            </span>
                        </td>

                        <td>
                            <form method="post" action="review_status.php" class="action-grid">
                                <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="redirect" value="reviews_manage.php<?= $statusFilter ? '?status='.$statusFilter:'' ?>">

                                <select name="status" class="filter-select select-compact" style="min-width:130px;">
                                    <option value="pending"  <?= $r['status']==='pending'?'selected':''; ?>>Pending</option>
                                    <option value="approved" <?= $r['status']==='approved'?'selected':''; ?>>Approved</option>
                                    <option value="rejected" <?= $r['status']==='rejected'?'selected':''; ?>>Rejected</option>
                                </select>

                                <button class="btn-orange" type="submit">Update</button>
                            </form>
                        </td>

                        <td><?= $r['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>

            </table>
        </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>
