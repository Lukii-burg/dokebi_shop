<?php
require_once __DIR__ . '/../../db/functions.php';
require_login();

$conn = db();
$orders = [];

$stmt = $conn->prepare("
  SELECT id, order_code, total_amount, order_status, created_at
  FROM orders
  WHERE user_id = ?
  ORDER BY created_at DESC
  LIMIT 10
");
$stmt->execute([current_user_id()]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="panel-card">
  <div class="panel-head">
    <div>
      <h3>Order History</h3>
      <p>Recent orders and their status</p>
    </div>
  </div>

  <?php if (!$orders): ?>
    <div class="notice">No orders yet.</div>
  <?php else: ?>
    <div class="order-list">
      <?php foreach ($orders as $o):
        $created = $o['created_at'] ? date('M d, Y H:i', strtotime($o['created_at'])) : '';
        $receiptLink = url_for('user/order_success.php') . '?order_id=' . urlencode((string)$o['id']) . '&code=' . urlencode($o['order_code']);
      ?>
        <div class="order-card">
          <div class="order-card__top">
            <div>
              <div style="font-weight:700;">#<?php echo htmlspecialchars($o['order_code']); ?></div>
              <div class="order-meta">
                <span><?php echo htmlspecialchars($created); ?></span>
                <span><?php echo number_format((float)$o['total_amount'], 2); ?> MMK</span>
              </div>
            </div>
            <span class="pill"><?php echo htmlspecialchars(ucfirst($o['order_status'])); ?></span>
          </div>
          <div class="order-actions">
            <a class="btn" href="<?php echo htmlspecialchars($receiptLink); ?>">View details</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
