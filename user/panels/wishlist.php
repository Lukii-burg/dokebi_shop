<?php
require_once __DIR__ . '/../../db/functions.php';
require_login();

$conn = db();
$userId = current_user_id();
$items = [];
$error = '';

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS wishlists (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_product (user_id, product_id)
        )
    ");
    $stmt = $conn->prepare("
        SELECT w.product_id,
               p.product_name,
               p.price,
               p.old_price,
               p.product_image,
               c.category_name
        FROM wishlists w
        JOIN products p ON w.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        WHERE w.user_id = :uid
        ORDER BY w.id DESC
    ");
    $stmt->execute([':uid' => $userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>

<div class="panel-card">
  <div class="panel-head">
    <div>
      <h3>Wishlist</h3>
      <p>Keep items you love close at hand</p>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="notice error">Unable to load wishlist: <?php echo htmlspecialchars($error); ?></div>
  <?php elseif (!$items): ?>
    <div class="notice">No favorites yet. Tap the heart icon on a product to save it.</div>
  <?php else: ?>
    <div class="wishlist-grid">
      <?php foreach ($items as $wish):
        $imgFile = $wish['product_image'] ?: 'default_product.png';
        $imgPath = "../uploads/products/" . $imgFile;
      ?>
        <div class="wish-card">
          <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($wish['product_name']); ?>">
          <div class="wish-body">
            <div style="font-weight:700;"><?php echo htmlspecialchars($wish['product_name']); ?></div>
            <div class="order-meta">
              <span><?php echo htmlspecialchars($wish['category_name'] ?? ''); ?></span>
              <span><?php echo number_format((float)$wish['price'], 2); ?> MMK</span>
            </div>
            <div class="card-actions">
              <form method="post" action="wishlist_toggle.php" style="margin:0;">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?php echo (int)$wish['product_id']; ?>">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars(url_for('user/account.php?panel=wishlist')); ?>">
                <button class="remove-btn" type="submit" aria-label="Remove item">Remove</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
