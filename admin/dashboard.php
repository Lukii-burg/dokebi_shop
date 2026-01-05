<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Core metrics
$totalProducts  = (int)$conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalUsers     = (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalOrders    = (int)$conn->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders  = (int)$conn->query("SELECT COUNT(*) FROM orders WHERE order_status='pending'")->fetchColumn();
$completedOrders= (int)$conn->query("SELECT COUNT(*) FROM orders WHERE order_status='completed'")->fetchColumn();

// Sales analytics
$salesToday = (float)$conn->query("
    SELECT COALESCE(SUM(total_amount),0) FROM orders 
    WHERE DATE(created_at) = CURDATE() AND payment_status='paid'
")->fetchColumn();
$salesMonth = (float)$conn->query("
    SELECT COALESCE(SUM(total_amount),0) FROM orders
    WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE()) AND payment_status='paid'
")->fetchColumn();
$salesYear = (float)$conn->query("
    SELECT COALESCE(SUM(total_amount),0) FROM orders
    WHERE YEAR(created_at)=YEAR(CURDATE()) AND payment_status='paid'
")->fetchColumn();

// Order status breakdown
$statusCounts = $conn->query("
    SELECT order_status, COUNT(*) AS cnt
    FROM orders
    GROUP BY order_status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Top-selling products
$topProducts = $conn->query("
    SELECT 
        oi.product_name,
        SUM(oi.quantity) AS qty_sold,
        SUM(oi.subtotal) AS revenue
    FROM order_items oi
    GROUP BY oi.product_id, oi.product_name
    ORDER BY qty_sold DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Loyal users (by spend)
$topUsersStmt = $conn->query("
    SELECT 
        u.name,
        u.email,
        COUNT(o.id) AS orders_count,
        SUM(o.total_amount) AS total_spent
    FROM orders o
    JOIN users u ON o.user_id = u.id
    GROUP BY u.id, u.name, u.email
    ORDER BY total_spent DESC
    LIMIT 10
");
$topUsers = $topUsersStmt->fetchAll(PDO::FETCH_ASSOC);

// Recent orders (admin can view receipts here, not only email)
$recentOrdersStmt = $conn->query("
    SELECT 
        o.id,
        o.order_code,
        o.total_amount,
        o.payment_status,
        o.order_status,
        o.created_at,
        u.name AS user_name,
        u.email AS user_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 8
");
$recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard | Dokebi Tekoku</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php include "theme.php"; ?>
</head>
<body>

<div id="alertBox" class="alert-box"></div>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="dashboard.php" class="active-link">Dashboard</a>
    <a href="products_manage.php">Products</a>
    <a href="main_categories_management.php">Main Categories</a>
    <a href="categories_management.php">Categories</a>
    <a href="users_manage.php">Users</a>
    <a href="orders_manage.php">Orders</a>
    <a href="payments_manage.php">Payments</a>
    <a href="reviews_manage.php">Reviews</a>
    <a href="settings.php">Settings</a>
    <a href="../auth/logout.php" style="color:#ff3b3b;">Logout</a>
</div>

<!-- TOPBAR -->
<div class="topbar">
    <div>
        <strong style="font-size:20px;">Admin Dashboard</strong>
        <p style="margin:0; opacity:0.8;">Welcome, <?= htmlspecialchars($_SESSION['username']) ?> </p>
    </div>

    <div class="switch-container">
        
        <div class="switch-toggle" onclick="toggleMode()"></div>
    </div>
</div>

<!-- CONTENT -->
<div class="content">
    <h2 style="margin-bottom:20px;">Overview</h2>

    <div style="display:flex; flex-wrap:wrap; gap:18px;">
        <div class="card"><h3>Total Products</h3><p style="font-size:26px; margin:0;"><?= $totalProducts ?></p></div>
        <div class="card"><h3>Total Users</h3><p style="font-size:26px; margin:0;"><?= $totalUsers ?></p></div>
        <div class="card"><h3>Total Orders</h3><p style="font-size:26px; margin:0;"><?= $totalOrders ?></p></div>
        <div class="card"><h3>Pending Orders</h3><p style="font-size:26px; margin:0; color:#ff7a18;"><?= $pendingOrders ?></p></div>
        <div class="card"><h3>Completed Orders</h3><p style="font-size:26px; margin:0; color:#4ef0c2;"><?= $completedOrders ?></p></div>
    </div>

    <h2 style="margin:26px 0 12px;">Sales Analytics</h2>
    <div style="display:flex; flex-wrap:wrap; gap:18px;">
        <div class="card"><h4>Today (paid)</h4><p style="font-size:22px; margin:0;"><?= number_format($salesToday,2) ?> MMK</p></div>
        <div class="card"><h4>This Month (paid)</h4><p style="font-size:22px; margin:0;"><?= number_format($salesMonth,2) ?> MMK</p></div>
        <div class="card"><h4>This Year (paid)</h4><p style="font-size:22px; margin:0;"><?= number_format($salesYear,2) ?> MMK</p></div>
    </div>

    <h2 style="margin:26px 0 12px;">Order Status Breakdown</h2>
    <div style="display:flex; flex-wrap:wrap; gap:10px;">
        <?php
        $allStatuses = ['pending','processing','completed','cancelled','refunded'];
        $maxCount = max(1, max($statusCounts ?: [1]));
        foreach ($allStatuses as $st):
            $cnt = (int)($statusCounts[$st] ?? 0);
            $width = round(($cnt / $maxCount) * 100);
        ?>
        <div class="card" style="min-width:160px; flex:1 1 160px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <strong><?= ucfirst($st) ?></strong>
                <span><?= $cnt ?></span>
            </div>
            <div style="height:8px; background:var(--panel-2); border-radius:6px; overflow:hidden; margin-top:8px;">
                <div style="width:<?= $width ?>%; height:100%; background:#8f57ff;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="display:flex; flex-wrap:wrap; gap:18px; margin-top:26px;">
        <div class="card" style="flex:1 1 320px;">
            <h3>Top Selling Products</h3>
            <?php if (!$topProducts): ?>
                <p class="muted">No sales data yet.</p>
            <?php else: ?>
            <table>
                <tr><th>Product</th><th>Qty Sold</th><th>Revenue (MMK)</th></tr>
                <?php foreach ($topProducts as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['product_name']) ?></td>
                        <td><?= (int)$p['qty_sold'] ?></td>
                        <td><?= number_format($p['revenue'],2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>

        <div class="card" style="flex:1 1 320px;">
            <h3>Top Loyal Users</h3>
            <?php if (!$topUsers): ?>
                <p class="muted">No orders yet.</p>
            <?php else: ?>
            <table>
                <tr><th>User</th><th>Orders</th><th>Spent (MMK)</th></tr>
                <?php foreach ($topUsers as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['name']) ?><br><span class="muted tiny"><?= htmlspecialchars($u['email']) ?></span></td>
                        <td><?= (int)$u['orders_count'] ?></td>
                        <td><?= number_format($u['total_spent'],2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <h2 style="margin:26px 0 12px;">Recent Orders (view receipts here)</h2>
    <div class="card" style="padding:0.75rem;">
      <?php if (!$recentOrders): ?>
        <p class="muted">No orders yet.</p>
      <?php else: ?>
        <table>
          <tr>
            <th>Order</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Created</th>
            <th></th>
          </tr>
          <?php foreach ($recentOrders as $ro): ?>
            <tr>
              <td><?= htmlspecialchars($ro['order_code']); ?></td>
              <td>
                <?= htmlspecialchars($ro['user_name'] ?? 'Guest'); ?><br>
                <span class="muted tiny"><?= htmlspecialchars($ro['user_email'] ?? ''); ?></span>
              </td>
              <td><?= number_format($ro['total_amount'],2); ?> MMK</td>
              <td><?= htmlspecialchars($ro['payment_status']); ?></td>
              <td><?= htmlspecialchars($ro['order_status']); ?></td>
              <td><?= htmlspecialchars($ro['created_at']); ?></td>
              <td><a class="btn" href="order_view.php?id=<?= (int)$ro['id']; ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
</div>

</body>
</html>
