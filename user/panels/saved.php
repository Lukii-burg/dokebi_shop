<?php
require_once __DIR__ . '/../../db/functions.php';
require_login();

$conn = db();
$userId = current_user_id();
$saved = [];
$error = '';

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS category_wishlists (
            user_id INT NOT NULL,
            category_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(user_id, category_id)
        )
    ");
    $stmt = $conn->prepare("
        SELECT cw.category_id,
               c.category_name,
               c.slug,
               c.card_image,
               mc.name AS main_name
        FROM category_wishlists cw
        JOIN categories c ON cw.category_id = c.id
        LEFT JOIN main_categories mc ON mc.id = c.main_category_id
        WHERE cw.user_id = :uid
        ORDER BY cw.created_at DESC
    ");
    $stmt->execute([':uid' => $userId]);
    $saved = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>

<div class="panel-card">
  <div class="panel-head">
    <div>
      <h3>Saved Categories</h3>
      <p>Jump back into the sections you browse most</p>
    </div>
  </div>

  <?php if ($error && !$saved): ?>
    <div class="notice error">Unable to load your saved categories: <?php echo htmlspecialchars($error); ?></div>
  <?php elseif (!$saved): ?>
    <div class="notice">No saved categories yet. Save them from the shop sidebar.</div>
    <a class="btn-primary" href="../main/shop.php">Browse categories</a>
  <?php else: ?>
    <div class="category-grid">
      <?php foreach ($saved as $cat):
        $imgFile = $cat['card_image'] ?: 'default_category.jpg';
        $imgPath = $imgFile ? "../uploads/categories/" . $imgFile : "../logo/original.png";
        $catLink = "../main/shop.php?category=" . urlencode($cat['slug'] ?? '');
      ?>
        <div class="category-card">
          <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($cat['category_name']); ?>">
          <div class="category-body">
            <div style="font-weight:700;"><?php echo htmlspecialchars($cat['category_name']); ?></div>
            <div style="color:var(--muted); font-size:14px;"><?php echo htmlspecialchars($cat['main_name'] ?? ''); ?></div>
            <div class="card-actions">
              <a class="btn-primary" href="<?php echo htmlspecialchars($catLink); ?>">Browse</a>
              <form method="post" action="category_wishlist_toggle.php" style="margin:0;">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="category_id" value="<?php echo (int)$cat['category_id']; ?>">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars(url_for('user/account.php?panel=saved')); ?>">
                <button class="remove-btn" type="submit" aria-label="Remove category">Remove</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
