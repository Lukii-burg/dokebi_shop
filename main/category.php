<?php
require_once __DIR__ . '/../db/functions.php';

$slug = $_GET['slug'] ?? '';
if ($slug === '') {
    header('Location: shop.php');
    exit;
}

$catStmt = $conn->prepare("
    SELECT c.*, mc.name AS main_name, mc.slug AS main_slug, mc.accent_color
    FROM categories c
    LEFT JOIN main_categories mc ON c.main_category_id = mc.id
    WHERE c.slug = :slug AND c.is_active = 1
    LIMIT 1
");
$catStmt->execute([':slug' => $slug]);
$category = $catStmt->fetch(PDO::FETCH_ASSOC);
if (!$category) {
    header('HTTP/1.0 404 Not Found');
    echo "Category not found.";
    exit;
}

$prodStmt = $conn->prepare("
    SELECT p.*, c.category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE c.slug = :slug AND p.status = 'active'
    ORDER BY p.product_name
");
$prodStmt->execute([':slug' => $slug]);
$products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

$wishlistIds = [];
if (is_logged_in()) {
    $w = $conn->prepare("SELECT product_id FROM wishlists WHERE user_id = ?");
    $w->execute([current_user_id()]);
    $wishlistIds = $w->fetchAll(PDO::FETCH_COLUMN);
}
$welcomePromo = is_logged_in() ? get_welcome_promo($conn, current_user_id()) : null;
$currentCategoryName = $category['category_name'];
$heroImg = $category['card_image'] ? '../uploads/categories/'.$category['card_image'] : '../logo/original.png';
$accent = $category['accent_color'] ?? '#0ea5e9';
$currentQuery = $_SERVER['QUERY_STRING'] ?? '';
$currentUrl = url_for('main/category.php') . ($currentQuery ? '?' . $currentQuery : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($currentCategoryName); ?> - Dokebi Family</title>
  <link rel="stylesheet" href="../maincss/style.css">
  <link rel="stylesheet" href="../maincss/dark-mode.css">
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="index.php"><img src="../logo/original.png" alt="Dokebi Family" class="logo-img"><span class="sitename">Dokebi Family</span></a>
      <nav class="main-nav">
        <a href="index.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="news.php">News</a>
        <a href="help.php">Help</a>
        <?php if(!is_logged_in()): ?>
          <a href="../auth/login.php">Login</a>
        <?php else: ?>
          <a href="../user/account.php?panel=wishlist">Wishlist</a>
          <a href="../user/account.php">My Account</a>
          <a href="../auth/logout.php">Logout</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main class="shop-main">
    <div class="container" style="margin-top:1rem;">
      <div class="breadcrumb">
        <a href="shop.php">Shop</a>
        <span class="breadcrumb-sep">/</span>
        <?php if (!empty($category['main_name'])): ?>
          <a href="shop.php?main=<?php echo urlencode($category['main_slug']); ?>"><?php echo htmlspecialchars($category['main_name']); ?></a>
          <span class="breadcrumb-sep">/</span>
        <?php endif; ?>
        <span><?php echo htmlspecialchars($currentCategoryName); ?></span>
      </div>
    </div>

    <section class="container" style="margin-top:1rem;">
      <div class="category-hero" style="background: linear-gradient(120deg, rgba(0,0,0,0.5), rgba(0,0,0,0.2)), url('<?php echo htmlspecialchars($heroImg); ?>'); background-size:cover; background-position:center; border-radius:18px; padding:1.25rem; color:#fff; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <div class="pill-dot" style="background: <?php echo htmlspecialchars($accent); ?>;"></div>
        <h1 style="margin:4px 0;"><?php echo htmlspecialchars($currentCategoryName); ?></h1>
        <p class="muted" style="color: rgba(255,255,255,0.8);"><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>
      </div>
    </section>

    <section class="container" style="margin-top:1.25rem;">
      <?php if (!$products): ?>
        <div class="notice">No products found for this category yet.</div>
      <?php else: ?>
        <div class="product-grid-seagm">
          <?php foreach ($products as $p): 
            $img = $p['product_image'] ?? '';
            $imgPath = $img ? '../uploads/products/'.$img : '../logo/original.png';
            $inWishlist = in_array($p['id'], $wishlistIds, true);
          ?>
          <div class="product-card-seagm" style="min-height:100%;">
            <div class="product-image-seagm">
              <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($p['product_name']); ?>">
            </div>
            <div class="product-info-seagm">
              <div class="product-title-seagm"><?php echo htmlspecialchars($p['product_name']); ?></div>
              <div class="product-meta-seagm"><?php echo htmlspecialchars($p['category_name']); ?></div>
              <p class="muted" style="min-height:48px;"><?php echo htmlspecialchars($p['description'] ?? ''); ?></p>
              <div class="card-row" style="align-items:flex-end;gap:0.8rem;flex-wrap:wrap;">
                <div class="price">
                  <?php if (!empty($p['old_price'])): ?>
                    <span class="original-price"><?php echo number_format($p['old_price'],2); ?> MMK</span>
                  <?php endif; ?>
                  <?php echo number_format($p['price'],2); ?> MMK
                </div>
                <form method="post" action="../user/buy.php" class="buy-form" style="display:flex;align-items:flex-end;gap:0.35rem;flex-wrap:wrap;">
                  <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                  <input type="hidden" name="action" value="add">
                  <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currentUrl); ?>">
                  <label class="muted tiny" for="qty_<?php echo (int)$p['id']; ?>">Qty</label>
                  <select id="qty_<?php echo (int)$p['id']; ?>" name="qty">
                    <?php for($i=1;$i<=10;$i++): ?>
                      <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                  </select>
                  <select name="payment_method" class="payment-select">
                    <option value="KBZPay">KBZPay</option>
                    <option value="Wave Pay">Wave Pay</option>
                    <option value="Aya Pay">Aya Pay</option>
                    <option value="Visa/Mastercard">Visa/Mastercard</option>
                    <option value="MPU">MPU</option>
                  </select>
                  <?php if ($welcomePromo && !$welcomePromo['used']): ?>
                    <label class="muted tiny" for="promo_<?php echo (int)$p['id']; ?>">Promo code</label>
                    <input type="text" id="promo_<?php echo (int)$p['id']; ?>" name="promo_code" value="<?php echo htmlspecialchars($welcomePromo['code']); ?>" placeholder="Enter promo code">
                    <div class="muted tiny" style="width:100%;">Use your welcome code for <?php echo (int)$welcomePromo['discount']; ?>% off this purchase.</div>
                  <?php endif; ?>
                  <div style="display:flex;gap:0.35rem;flex-wrap:wrap;">
                    <button class="btn primary" type="submit">Buy</button>
                    <button class="btn" type="submit" formaction="<?php echo url_for('user/cart.php'); ?>" formmethod="post">Add to Cart</button>
                  </div>
                </form>
                <?php if (is_logged_in()): ?>
                  <form method="post" action="../user/wishlist_toggle.php" style="display:inline;">
                    <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currentUrl); ?>">
                    <input type="hidden" name="action" value="<?php echo $inWishlist ? 'remove' : 'add'; ?>">
                    <button class="btn" type="submit" style="background:var(--panel-2);">
                      <?php echo $inWishlist ? 'Saved ' : 'Add to Wishlist ?'; ?>
                    </button>
                  </form>
                <?php else: ?>
                  <a class="btn" href="../auth/login.php">Login to save</a>
                <?php endif; ?>
              </div>
              <div class="muted tiny">Stock: <?php echo (int)$p['stock']; ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
