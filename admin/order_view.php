<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$order_id = (int)$_GET["id"];

/* ================================
   UPDATE ORDER (POST)
================================ */
if (isset($_POST["update_order"])) {
    $order_status   = $_POST["order_status"];
    $payment_status = $_POST["payment_status"];
    $notes          = $_POST["notes"] ?? "";

    $stmt = $conn->prepare("
        UPDATE orders SET
            order_status = :os,
            payment_status = :ps,
            notes = :n
        WHERE id = :id
    ");

    $stmt->execute([
        ':os' => $order_status,
        ':ps' => $payment_status,
        ':n'  => $notes,
        ':id' => $order_id
    ]);

    header("Location: order_view.php?id=$order_id&msg=updated");
    exit();
}

/* ================================
   FETCH ORDER DETAILS
================================ */
$stmt = $conn->prepare("
    SELECT o.*, 
           u.name AS customer_name, 
           u.email AS customer_email, 
           u.phone, 
           u.address
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = :id
");
$stmt->execute([':id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

/* ================================
   FETCH ORDER ITEMS
================================ */
$itemStmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = :id");
$itemStmt->execute([':id' => $order_id]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================================
   FETCH LATEST PAYMENT (for proof/status display)
=============================== */
$paymentStmt = $conn->prepare("SELECT method, status, transaction_ref, paid_at, amount FROM payments WHERE order_id = :id ORDER BY id DESC LIMIT 1");
$paymentStmt->execute([':id' => $order_id]);
$latestPayment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = "Order Details";
$pageSubtitle = "Order #" . htmlspecialchars($order['order_code']);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?> | Dokebi Tekoku</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php include "theme.php"; ?>

<style>
/* =============================
   UI BOXES
============================= */
.info-box, .product-box, .update-box {
    background: var(--panel);
    padding: 22px;
    border-radius: 14px;
    margin-bottom: 25px;
    box-shadow: 0 0 15px rgba(0,0,0,0.25);
}

.info-title {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 15px;
}

/* =============================
   STATUS COLORS
============================= */
.status-paid { color:#3cffb3; font-weight:600; }
.status-pending { color:#ffcc00; font-weight:600; }
.status-failed { color:#ff5c5c; font-weight:600; }
.status-refunded { color:#6bb6ff; font-weight:600; }

/* =============================
   BUTTONS
============================= */
.btn-back {
    display:inline-block;
    padding:10px 16px;
    background:#ff7a18;
    color:white;
    border-radius:8px;
    text-decoration:none;
    transition:0.25s;
}
.btn-back:hover {
    background:#8f57ff;
    padding-left:22px;
}

/* =============================
   TEXTAREA
============================= */
textarea {
    width:100%;
    height:120px;
    padding:12px;
    border-radius:10px;
    background:var(--panel-2);
    border:1px solid var(--border);
    color:var(--text);
    font-size:15px;
}

/* =============================
   PREMIUM SELECT BOX
============================= */
.select-box {
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    background: var(--panel-2);
    border: 1px solid var(--border);
    color: var(--text);
    cursor: pointer;
    font-size: 15px;
    transition: 0.3s ease;
    appearance: none;
    background-image: url('data:image/svg+xml;utf8,<svg fill="%23d2d3d7" width="22" height="22" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
    background-repeat: no-repeat;
    background-position: right 14px center;
}

body.light-mode .select-box {
    color: var(--light-text);
    background-image: url('data:image/svg+xml;utf8,<svg fill="%232c2f38" width="22" height="22" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
}

.select-box:hover {
    border-color: var(--accent-orange);
    transform: translateY(-2px);
    box-shadow: 0 0 10px rgba(255,122,24,0.4);
}

.select-box:focus {
    border-color: var(--accent-purple);
    box-shadow: 0 0 12px rgba(143,87,255,0.7);
    transform: translateY(-2px);
    outline: none;
}
</style>

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

    <a href="orders_manage.php" class="btn-back"> Back to Orders</a>

    <!-- ORDER INFORMATION -->
    <div class="info-box">
        <div class="info-title">Order Information</div>

        <p><b>Order Code:</b> <?= htmlspecialchars($order['order_code']) ?></p>
        <p><b>Customer:</b> <?= htmlspecialchars($order['customer_name']) ?></p>
        <p><b>Email:</b> <?= htmlspecialchars($order['customer_email']) ?></p>
        <p><b>Phone:</b> <?= htmlspecialchars($order['phone']) ?></p>
        <p><b>Address:</b> <?= htmlspecialchars($order['address']) ?></p>
        <p><b>Total Amount:</b> <?= number_format($order['total_amount'],2) ?> MMK</p>

        <p><b>Payment Status:</b>
            <span class="status-<?= strtolower($order['payment_status']) ?>">
                <?= ucfirst($order['payment_status']) ?>
            </span>
        </p>

        <p><b>Order Status:</b> <?= ucfirst($order['order_status']) ?></p>
        <p><b>Placed At:</b> <?= $order['created_at'] ?></p>

        <?php if ($latestPayment): ?>
            <div class="info-title" style="margin-top:1rem;">Latest Payment</div>
            <p><b>Method:</b> <?= htmlspecialchars($latestPayment['method'] ?? 'N/A') ?></p>
            <p><b>Status:</b> <?= htmlspecialchars(ucfirst($latestPayment['status'] ?? 'pending')) ?></p>
            <p><b>Amount:</b> <?= number_format($latestPayment['amount'] ?? 0,2) ?> MMK</p>
            <?php
              $proof = $latestPayment['transaction_ref'] ?? '';
              $isImage = preg_match('/\.(png|jpe?g|webp)$/i', $proof);
            ?>
            <?php if ($proof): ?>
                <p><b>Reference / Proof:</b>
                  <?php if ($isImage): ?>
                    <br>
                    <img src="<?= htmlspecialchars($proof) ?>" alt="Payment proof" style="max-width:260px;border:1px solid #1f2937;border-radius:6px;">
                  <?php else: ?>
                    <?= htmlspecialchars($proof) ?>
                  <?php endif; ?>
                </p>
            <?php else: ?>
                <p><b>Reference / Proof:</b> Not provided</p>
            <?php endif; ?>
            <p><b>Paid At:</b> <?= htmlspecialchars($latestPayment['paid_at'] ?? '') ?></p>
        <?php else: ?>
            <div class="notice" style="margin-top:1rem;">No payment records yet.</div>
        <?php endif; ?>
    </div>

    <!-- ORDERED PRODUCTS -->
    <div class="product-box">
        <div class="info-title">Ordered Products</div>

        <table>
            <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Unit Price (MMK)</th>
                <th>Subtotal (MMK)</th>
            </tr>

            <?php foreach ($items as $it): ?>
            <tr>
                <td><?= htmlspecialchars($it['product_name']) ?></td>
                <td><?= $it['quantity'] ?></td>
                <td><?= number_format($it['unit_price'], 2) ?></td>
                <td><?= number_format($it['subtotal'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- UPDATE ORDER -->
    <div class="update-box">
        <div class="info-title">Update Order</div>

        <form method="POST">

            <label>Order Status</label>
            <select name="order_status" class="select-box">
                <option value="pending"     <?= $order['order_status']=="pending" ? "selected" : "" ?>>Pending</option>
                <option value="processing"  <?= $order['order_status']=="processing" ? "selected" : "" ?>>Processing</option>
                <option value="completed"   <?= $order['order_status']=="completed" ? "selected" : "" ?>>Completed</option>
                <option value="cancelled"   <?= $order['order_status']=="cancelled" ? "selected" : "" ?>>Cancelled</option>
            </select>
            <br><br>

            <label>Payment Status</label>
            <select name="payment_status" class="select-box">
                <option value="pending"   <?= $order['payment_status']=="pending" ? "selected" : "" ?>>Pending</option>
                <option value="paid"      <?= $order['payment_status']=="paid" ? "selected" : "" ?>>Paid</option>
                <option value="failed"    <?= $order['payment_status']=="failed" ? "selected" : "" ?>>Failed</option>
                <option value="refunded"  <?= $order['payment_status']=="refunded" ? "selected" : "" ?>>Refunded</option>
            </select>
            <br><br>

            <label>Admin Notes</label>
            <textarea name="notes"><?= htmlspecialchars($order['notes'] ?? "") ?></textarea>
            <br><br>

            <button type="submit" name="update_order" class="btn-orange"> Update Order</button>

        </form>
    </div>

</div>

<?php if (isset($_GET["msg"]) && $_GET["msg"] === "updated"): ?>
<script>showAlert("Order updated successfully");</script>
<?php endif; ?>

</body>
</html>
