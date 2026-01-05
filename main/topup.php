<?php
require_once __DIR__ . '/../db/functions.php';
require_once __DIR__ . '/../includes/catalog_queries.php';

$slug = $_GET['slug'] ?? '';
if ($slug === '') {
    header('Location: shop.php');
    exit;
}
$schema = catalog_schema($conn);

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
add_recent_view($slug);

$prodStmt = $conn->prepare("
    SELECT p.*, c.category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE c.slug = :slug AND p.status = 'active'
    ORDER BY p.price ASC
");
$prodStmt->execute([':slug' => $slug]);
$products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$products) {
    $_SESSION['flash'] = 'No products for this category yet.';
    header('Location: shop.php?category=' . urlencode($slug));
    exit;
}

$wishlistIds = [];
if (is_logged_in()) {
    $w = $conn->prepare("SELECT product_id FROM wishlists WHERE user_id = ?");
    $w->execute([current_user_id()]);
    $wishlistIds = $w->fetchAll(PDO::FETCH_COLUMN);
}
$welcomePromo = is_logged_in() ? get_welcome_promo($conn, current_user_id()) : null;
$cartCount = 0;
if (is_logged_in()) {
    $cartCountStmt = $conn->prepare("
        SELECT COALESCE(SUM(ci.quantity),0)
        FROM cart_items ci
        JOIN carts c ON ci.cart_id = c.id
        WHERE c.user_id = :uid
    ");
    $cartCountStmt->execute([':uid' => current_user_id()]);
    $cartCount = (int)$cartCountStmt->fetchColumn();
}

// Reviews
$reviewStats = [];
$approvedReviews = [];
$productIds = array_column($products, 'id');
if ($productIds) {
    $ph = implode(',', array_fill(0, count($productIds), '?'));
    $rs = $conn->prepare("
        SELECT product_id, ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS review_count
        FROM reviews
        WHERE status = 'approved' AND product_id IN ($ph)
        GROUP BY product_id
    ");
    $rs->execute($productIds);
    foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $reviewStats[(int)$row['product_id']] = ['avg' => (float)$row['avg_rating'], 'count' => (int)$row['review_count']];
    }

    $ap = $conn->prepare("
        SELECT r.product_id, r.rating, r.comment, u.name AS user_name
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.status = 'approved' AND r.product_id IN ($ph)
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $ap->execute($productIds);
    foreach ($ap->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $approvedReviews[(int)$row['product_id']][] = $row;
    }
}

$currentCategoryName = $category['category_name'];
$heroImg = $category['card_image'] ? '../uploads/categories/'.$category['card_image'] : '../logo/original.png';
$accent = $category['accent_color'] ?? '#0ea5e9';
$firstProduct = $products[0];
$currentUrl = url_for('main/topup.php') . '?slug=' . urlencode($slug);
$firstInWishlist = in_array($firstProduct['id'], $wishlistIds, true);
$catWishlist = false;
if (is_logged_in()) {
    ensure_category_wishlist_table($conn);
    try {
        $cw = $conn->prepare("SELECT 1 FROM category_wishlists WHERE user_id = :uid AND category_id = :cid LIMIT 1");
        $cw->execute([':uid' => current_user_id(), ':cid' => (int)$category['id']]);
        $catWishlist = (bool)$cw->fetchColumn();
    } catch (PDOException $e) {
        // Attempt to create the table and retry once
        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS category_wishlists (
                    user_id INT NOT NULL,
                    category_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY(user_id, category_id),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
                )
            ");
            $cw = $conn->prepare("SELECT 1 FROM category_wishlists WHERE user_id = :uid AND category_id = :cid LIMIT 1");
            $cw->execute([':uid' => current_user_id(), ':cid' => (int)$category['id']]);
            $catWishlist = (bool)$cw->fetchColumn();
        } catch (PDOException $e2) {
            // swallow to avoid breaking the page
            $catWishlist = false;
        }
    }
}
$catContent = fetch_category_content($conn, $category, $schema);
$recentViews = $_SESSION['recent_views'] ?? [];
$relatedCategories = [];
if (!empty($category['main_category_id'])) {
    $allCats = fetch_categories($conn, $schema, (int)$category['main_category_id']);
    foreach ($allCats as $rc) {
        if (($rc['slug'] ?? '') !== $slug) {
            $relatedCategories[] = $rc;
        }
    }
    $relatedCategories = array_slice($relatedCategories, 0, 6);
}

$overallAvg = 0; $overallCount = 0;
foreach ($reviewStats as $rs) {
    $overallAvg += $rs['avg'] * $rs['count'];
    $overallCount += $rs['count'];
}
$overallAvg = $overallCount > 0 ? round($overallAvg / $overallCount, 1) : 0;
$flash = $_SESSION['flash'] ?? '';
if ($flash) { unset($_SESSION['flash']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($currentCategoryName); ?> - Dokebi Family</title>
  <link rel="stylesheet" href="../maincss/style.css">
  <link rel="stylesheet" href="../maincss/dark-mode.css">
  <style>
    /* Page shell */
    body.topup-page {
      background: linear-gradient(180deg, #f4f6fb 0%, #fdf9f2 100%);
      color: #0f172a;
    }
    [data-theme="dark"] body.topup-page {
      background: var(--bg, #0f172a);
      color: var(--text, #f1f5f9);
    }
    .topup-page .site-header {
      backdrop-filter: blur(10px);
      background: rgba(255,255,255,0.82);
      border-bottom: 1px solid rgba(15,23,42,0.08);
    }
    [data-theme="dark"] .topup-page .site-header {
      background: rgba(15,23,42,0.9);
      border-color: rgba(255,255,255,0.06);
    }
    .breadcrumb a { text-decoration: none; color: inherit; }
    .breadcrumb a:hover { color: var(--accent); }

    .topup-shell { display:grid; grid-template-columns: 1.6fr 1fr; gap:1rem; align-items:start; }
    .topup-products { background: rgba(255,255,255,0.85); border-radius: 18px; padding: 1rem; border:1px solid rgba(15,23,42,0.06); box-shadow:0 20px 45px rgba(15,23,42,0.08); }
    .topup-form { background: rgba(255,255,255,0.9); border-radius: 18px; padding: 1rem; border:1px solid rgba(15,23,42,0.06); position:sticky; top:10px; box-shadow:0 18px 36px rgba(15,23,42,0.07); }
    [data-theme="dark"] .topup-products, [data-theme="dark"] .topup-form {
      background: #0f172a;
      border-color: rgba(255,255,255,0.06);
      box-shadow: 0 18px 36px rgba(0,0,0,0.45);
    }
    .topup-hero { display:flex; gap:1rem; align-items:center; padding:1.4rem; border-radius:22px; background-size:cover; background-position:center; color:#fff; position:relative; overflow:hidden; box-shadow:0 20px 45px rgba(15,23,42,0.12); }
    .topup-hero::after { content:''; position:absolute; inset:0; border-radius:22px; background:linear-gradient(120deg, rgba(0,0,0,0.45), rgba(0,0,0,0.2)); z-index:0; }
    .topup-hero-content { position:relative; z-index:1; }
    .topup-list { display:grid; gap:0.85rem; }
    .topup-card { display:flex; justify-content:space-between; align-items:center; padding:0.9rem 1.05rem; border-radius:16px; border:1px solid rgba(15,23,42,0.08); cursor:pointer; background: linear-gradient(180deg, rgba(255,255,255,0.85), rgba(255,255,255,0.65)); transition: all .2s ease; }
    [data-theme="dark"] .topup-card { background: linear-gradient(180deg, #111827, #0b1221); border-color: rgba(255,255,255,0.06); }
    .topup-card:hover { border-color: var(--accent); box-shadow:0 12px 26px rgba(14,165,233,0.12); transform: translateY(-1px); }
    .topup-card.selected { border-color: var(--accent); box-shadow:0 14px 30px rgba(14,165,233,0.18); }
    .topup-price { font-weight:700; color:var(--accent); }
    .order-total { font-size:1.4rem; font-weight:700; }
    .topup-form input,
    .topup-form select { width:100%; padding:0.65rem 0.75rem; border-radius:12px; border:1px solid rgba(15,23,42,0.08); background:#f8fafc; color:#0f172a; transition:border-color .15s ease, box-shadow .15s ease; }
    .topup-form input:focus,
    .topup-form select:focus { outline:none; border-color: var(--accent); box-shadow:0 0 0 3px rgba(14,165,233,0.15); }
    [data-theme="dark"] .topup-form input,
    [data-theme="dark"] .topup-form select { background:#0b1221; border-color: rgba(255,255,255,0.08); color:#e2e8f0; }
    .topup-form label { display:flex; flex-direction:column; gap:6px; font-weight:600; color:inherit; }
    .topup-form .btn { width:100%; }
    .pill-outline { border:1px solid var(--glass); border-radius:12px; padding:0.35rem 0.55rem; display:inline-flex; align-items:center; gap:6px; }
    .rating-bar{display:flex;align-items:center;gap:6px;margin-top:0.35rem;}
    .btn.primary { background: linear-gradient(90deg, #2563eb, #ec4899, #fb923c); color:#fff; border:none; box-shadow:0 12px 30px rgba(59,130,246,0.2); }
    .btn { border-radius:12px; padding:0.65rem 0.9rem; }
    .notice { background: rgba(14,165,233,0.1); color:#0f172a; border:1px solid rgba(14,165,233,0.3); }
    [data-theme="dark"] .notice { background: rgba(14,165,233,0.15); color:#e2e8f0; border-color: rgba(14,165,233,0.25); }
    .topup-form .tiny, .topup-products .tiny { color: var(--muted); }
    /* Review form enhancements */
    .rating-stars { display:flex; gap:0.65rem; align-items:center; flex-wrap:wrap; }
    .star-radio { position:relative; cursor:pointer; display:inline-flex; align-items:center; gap:0.25rem; }
    .star-radio input { position:absolute; opacity:0; inset:0; cursor:pointer; }
    .star-icon { font-size:1.1rem; color:#cbd5e1; transition: transform .15s ease, color .15s ease, text-shadow .15s ease; }
    .star-radio:hover .star-icon { transform: translateY(-1px) scale(1.05); color:#fbbf24; text-shadow:0 0 8px rgba(251,191,36,0.6); }
    .star-radio input:checked + .star-icon { color:#f59e0b; text-shadow:0 0 8px rgba(245,158,11,0.65); }
    .fancy-textarea { background:#f8fafc; border:1px solid rgba(15,23,42,0.08); border-radius:12px; padding:0.75rem; color:#0f172a; transition:border-color .15s ease, box-shadow .15s ease, transform .12s ease; }
    .fancy-textarea:focus { outline:none; border-color: var(--accent); box-shadow:0 0 0 3px rgba(14,165,233,0.18); transform: translateY(-1px); }
    [data-theme="dark"] .fancy-textarea { background:#0b1221; color:#e2e8f0; border-color: rgba(255,255,255,0.08); }
    [data-theme="dark"] .fancy-textarea:focus { box-shadow:0 0 0 3px rgba(14,165,233,0.28); }
    .ghost-pill { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:0.45rem 0.7rem; border-radius:999px; border:1px solid rgba(15,23,42,0.12); background: linear-gradient(90deg, rgba(37,99,235,0.12), rgba(236,72,153,0.12)); color:#0f172a; text-decoration:none; font-weight:600; font-size:0.9rem; }
    .ghost-pill:hover { border-color: var(--accent); box-shadow:0 6px 16px rgba(14,165,233,0.18); transform: translateY(-1px); }
    [data-theme="dark"] .ghost-pill { border-color: rgba(255,255,255,0.08); color:#e2e8f0; background: linear-gradient(90deg, rgba(14,165,233,0.18), rgba(236,72,153,0.15)); }

    @media(max-width: 900px){ .topup-shell { grid-template-columns: 1fr; } .topup-form { position:relative; top:auto; } }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body class="topup-page">
  <div id="pageLoader" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:9999;background:rgba(15,23,42,0.55);backdrop-filter:blur(2px);">
    <div style="width:82px;height:82px;border-radius:999px;border:6px solid rgba(255,255,255,0.35);border-top-color:var(--accent, #60a5fa);animation:spin 0.9s linear infinite;"></div>
  </div>
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
        <div class="theme-toggle">
          <span class="muted tiny">Light / Dark</span>
          <label class="toggle-switch">
            <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
            <span class="toggle-slider"></span>
          </label>
        </div>
        <a class="cart-btn" href="<?php echo url_for('user/cart.php'); ?>" aria-label="Cart">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 4h2l1.4 10.1a1 1 0 0 0 .99.9h9.82a1 1 0 0 0 .94-.65L20 8H6.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="9" cy="19" r="1.3" fill="currentColor"/>
            <circle cx="16" cy="19" r="1.3" fill="currentColor"/>
          </svg>
          <?php if($cartCount>0): ?><span class="cart-badge"><?php echo $cartCount; ?></span><?php endif; ?>
        </a>
      </nav>
    </div>
  </header>

  <main class="container" style="margin-top:1rem; margin-bottom:2rem;">
    <?php if($flash): ?>
      <div class="notice" style="margin-bottom:0.75rem;"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>
    <div class="breadcrumb">
      <a href="shop.php">Shop</a>
      <span class="breadcrumb-sep">/</span>
      <?php if (!empty($category['main_name'])): ?>
        <a href="shop.php?main=<?php echo urlencode($category['main_slug']); ?>"><?php echo htmlspecialchars($category['main_name']); ?></a>
        <span class="breadcrumb-sep">/</span>
      <?php endif; ?>
      <span><?php echo htmlspecialchars($currentCategoryName); ?></span>
    </div>

    <div class="topup-hero" style="position:relative; background-image:linear-gradient(120deg, rgba(0,0,0,0.5), rgba(0,0,0,0.2)), url('<?php echo htmlspecialchars($heroImg); ?>');">
      <div class="topup-hero-content">
        <div class="pill-dot" style="background: <?php echo htmlspecialchars($accent); ?>;"></div>
        <h1 style="margin:6px 0;"><?php echo htmlspecialchars($currentCategoryName); ?></h1>
        <p class="muted" style="color:rgba(255,255,255,0.85);"><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>
        <?php if (is_logged_in()): ?>
          <form method="post" action="../user/category_wishlist_toggle.php" style="margin-top:0.6rem;display:inline-flex;gap:8px;align-items:center;">
            <input type="hidden" name="category_id" value="<?php echo (int)$category['id']; ?>">
            <input type="hidden" name="action" value="<?php echo $catWishlist ? 'remove' : 'add'; ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currentUrl); ?>">
            <button class="btn" type="submit" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
              <?php echo $catWishlist ? 'Remove from Favorites' : 'Save Category'; ?>
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="topup-shell" style="margin-top:1rem;">
      <div class="topup-products">
        <div style="display:flex;justify-content:space-between;gap:0.5rem;align-items:center;flex-wrap:wrap;margin-bottom:0.5rem;">
          <h3 style="margin:0;">Select Top-Up Amount</h3>
          <input id="productSearch" type="search" placeholder="Search packages..." style="max-width:260px;width:100%;padding:0.55rem 0.7rem;border-radius:10px;border:1px solid var(--glass);">
        </div>
        <div class="topup-list" id="topupList">
          <?php foreach ($products as $p): 
              $saved = in_array($p['id'], $wishlistIds, true);
              $isPopular = array_key_exists('is_popular', $p) ? (bool)$p['is_popular'] : false;
              $isBest = array_key_exists('is_best_value', $p) ? (bool)$p['is_best_value'] : false;
          ?>
            <label class="topup-card" data-price="<?php echo (float)$p['price']; ?>" data-id="<?php echo (int)$p['id']; ?>" data-saved="<?php echo $saved ? '1' : '0'; ?>" data-name="<?php echo htmlspecialchars($p['product_name']); ?>">
              <div>
                <div class="product-title-seagm" style="margin:0;"><?php echo htmlspecialchars($p['product_name']); ?></div>
                <div class="muted tiny"><?php echo htmlspecialchars($p['description'] ?? ''); ?></div>
              </div>
              <div class="topup-price"><?php echo number_format($p['price'], 2); ?> MMK</div>
              <?php if ($isBest): ?>
                <span class="pill-outline" style="color:var(--accent);border-color:var(--accent);">Best Value</span>
              <?php elseif ($isPopular): ?>
                <span class="pill-outline">Popular</span>
              <?php endif; ?>
              <input type="radio" name="product_pick" value="<?php echo (int)$p['id']; ?>" style="display:none;">
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="topup-form">
        <h3>Order Information</h3>
        <form method="post" action="../user/buy.php" id="buyForm" style="display:flex;flex-direction:column;gap:0.75rem;">
          <input type="hidden" name="product_id" id="product_id" value="<?php echo (int)$firstProduct['id']; ?>">
          <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currentUrl); ?>">
          <input type="hidden" name="action" value="add" id="actionInput">
          <?php
            $phoneOnlySlugs = ['mm-topup','mpt-topup','u9-topup','atom-topup','mytel-topup'];
            $gmailOnlySlugs = ['gift-cards','steam-wallet','google-play-gift-cards','app-store-gift-cards'];
            $premiumSlugs   = ['premium-accounts','spotify-premium','youtube-premium','netflix-premium','telegram-premium','chatgpt-premium'];
            $fieldLayout = 'id_zone';
            if ($slug === 'genshin-impact') { $fieldLayout = 'genshin'; }
            elseif ($slug === 'pubg-uc' || $slug === 'freefire-diamonds') { $fieldLayout = 'id_only'; }
            elseif (in_array($slug, $premiumSlugs, true)) { $fieldLayout = 'premium'; }
            elseif (in_array($slug, $gmailOnlySlugs, true)) { $fieldLayout = 'gmail_only'; }
            elseif (in_array($slug, $phoneOnlySlugs, true)) { $fieldLayout = 'phone_only'; }
          ?>
          <?php if ($fieldLayout === 'genshin'): ?>
          <label>
            <span class="muted tiny">Genshin User ID</span>
            <input type="text" name="user_game_id" required placeholder="Enter your UID (e.g. 800123456)">
            <span class="muted tiny">Find it in-game at the bottom-right of your profile.</span>
          </label>
          <label>
            <span class="muted tiny">Server</span>
            <select name="user_zone_id" required>
              <option value="" selected disabled>Please select</option>
              <option value="America">America</option>
              <option value="Europe">Europe</option>
              <option value="Asia">Asia</option>
              <option value="TW, HK, MO">TW, HK, MO</option>
            </select>
          </label>
          <?php elseif ($fieldLayout === 'id_zone'): ?>
          <label>
            <span class="muted tiny">User ID</span>
            <input type="text" name="user_game_id" required placeholder="Enter User ID">
          </label>
          <label>
            <span class="muted tiny">Zone/Server ID</span>
            <input type="text" name="user_zone_id" required placeholder="Enter Zone/Server ID">
          </label>
          <?php elseif ($fieldLayout === 'id_only'): ?>
          <label>
            <span class="muted tiny"><?php echo $slug === 'freefire-diamonds' ? 'Free Fire Player ID' : 'Player ID'; ?></span>
            <input type="text" name="user_game_id" required placeholder="Enter Player ID">
          </label>
          <?php elseif ($fieldLayout === 'gmail_only'): ?>
          <label>
            <span class="muted tiny">Gmail for delivery</span>
            <input type="email" name="delivery_email" required placeholder="example@gmail.com" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>" pattern="^[A-Za-z0-9._%+-]+@gmail\.com$">
            <span class="muted tiny">Redeem code will be emailed and shown after purchase.</span>
          </label>
          <?php elseif ($fieldLayout === 'premium'): ?>
          <input type="hidden" name="account_option" id="accountOption" value="new">
          <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <label class="pill-outline" style="gap:8px;">
              <input type="radio" name="premium_choice" value="new" data-premium-choice checked>
              <span>Create new account</span>
            </label>
            <label class="pill-outline" style="gap:8px;">
              <input type="radio" name="premium_choice" value="existing" data-premium-choice>
              <span>Extend existing</span>
            </label>
          </div>
          <div id="premiumNewFields" style="display:grid;gap:0.4rem;">
            <label>
              <span class="muted tiny">Delivery email</span>
              <input type="email" name="delivery_email" placeholder="Enter email to receive credentials" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>" required>
              <span class="muted tiny">We will generate a fresh account (no duplicates) and email the credentials.</span>
            </label>
          </div>
          <div id="premiumExistingFields" style="display:none;gap:0.4rem;">
            <?php if ($slug === 'telegram-premium'): ?>
              <label>
                <span class="muted tiny">Telegram phone number</span>
                <input type="tel" name="existing_phone" placeholder="Enter phone number" value="<?php echo htmlspecialchars($_SESSION['user_phone'] ?? ''); ?>">
              </label>
              <label>
                <span class="muted tiny">Account email</span>
                <input type="email" name="existing_email" placeholder="Enter Telegram email" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>">
              </label>
              <label>
                <span class="muted tiny">Telegram username</span>
                <input type="text" name="existing_username" placeholder="@username">
              </label>
              <span class="muted tiny">We will extend this Telegram Premium account.</span>
            <?php else: ?>
              <label>
                <span class="muted tiny">Account email</span>
                <input type="email" name="existing_email" placeholder="Enter account email" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>">
              </label>
              <span class="muted tiny">We will extend this subscription and confirm by email.</span>
            <?php endif; ?>
          </div>
          <?php elseif ($fieldLayout === 'phone_only'): ?>
          <label>
            <span class="muted tiny">Phone Number</span>
            <input type="tel" name="user_phone" required placeholder="Enter phone number">
          </label>
          <?php endif; ?>
          <input type="hidden" name="qty" id="qtySelect" value="1">
          <label>
            <span class="muted tiny">Payment Method</span>
            <select name="payment_method">
              <option value="KBZPay">KBZPay</option>
              <option value="Wave Pay">Wave Pay</option>
              <option value="Aya Pay">Aya Pay</option>
              <option value="Visa/Mastercard">Visa/Mastercard</option>
              <option value="MPU">MPU</option>
            </select>
          </label>
          <?php if ($welcomePromo && !$welcomePromo['used']): ?>
          <label>
            <span class="muted tiny">Promo code</span>
            <input type="text" name="promo_code" value="<?php echo htmlspecialchars($welcomePromo['code']); ?>" placeholder="Enter promo code">
            <span class="muted tiny">One-time <?php echo (int)$welcomePromo['discount']; ?>% off.</span>
          </label>
          <?php endif; ?>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span class="muted">Total</span>
            <span class="order-total" id="orderTotal"><?php echo number_format($firstProduct['price'],2); ?> MMK</span>
          </div>
          <?php if (is_logged_in()): ?>
            <button class="btn primary" type="submit">Buy Now</button>
          <?php else: ?>
            <a class="btn primary" href="../auth/login.php">Login to purchase</a>
          <?php endif; ?>
        </form>
        <?php if (is_logged_in()): ?>
        <form id="cartForm" method="post" action="../user/cart.php" style="display:flex;flex-direction:column;gap:0.4rem;margin-top:0.35rem;">
          <input type="hidden" name="product_id" id="cartProductId" value="<?php echo (int)$firstProduct['id']; ?>">
          <input type="hidden" name="qty" value="1">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currentUrl); ?>">
          <button class="btn" type="submit">Add to Cart</button>
        </form>
        <form id="wishForm" method="post" action="../user/wishlist_toggle.php" style="margin-top:0.25rem;">
          <input type="hidden" name="product_id" id="wishProductId" value="<?php echo (int)$firstProduct['id']; ?>">
          <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currentUrl); ?>">
          <input type="hidden" name="action" id="wishAction" value="<?php echo $firstInWishlist ? 'remove' : 'add'; ?>">
          <button class="btn" type="submit" id="wishBtn" style="width:100%;background:var(--panel);border:1px solid var(--glass);">
            <?php echo $firstInWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>
          </button>
          <div style="margin-top:6px;display:flex;justify-content:center;">
            <a class="ghost-pill" href="../user/account.php?panel=wishlist">View my wishlist</a>
          </div>
        </form>
        <?php endif; ?>
        <div class="muted tiny" style="margin-top:0.5rem;">Orders will appear in your account with status "pending" after payment confirmation.</div>
      </div>
    </div>

    <?php
      $hasContent = trim($catContent['long_description'] ?? '') !== '' ||
                    trim($catContent['faq'] ?? '') !== '' ||
                    trim($catContent['guide'] ?? '') !== '' ||
                    trim($catContent['video_url'] ?? '') !== '';
    ?>
    <?php if ($hasContent): ?>
    <section class="container" style="margin-top:1.25rem;">
      <style>
        .gi-wrap { display:grid; gap:1rem; }
        .gi-panel {
          background: linear-gradient(180deg, #0f172a, #0c1428);
          color: #e2e8f0;
          border:1px solid rgba(255,255,255,0.06);
          border-radius:18px;
          padding:1.25rem;
          box-shadow:0 18px 36px rgba(15,23,42,0.28);
        }
        [data-theme="dark"] .gi-panel {
          background: linear-gradient(180deg, #0b1221, #0a1120);
          border-color: rgba(255,255,255,0.05);
        }
        .gi-panel h3, .gi-panel h4 { color:#e2e8f0; margin:0; }
        .gi-panel p { margin:0.35rem 0 0; color:#cbd5e1; }
        .gi-panel ul, .gi-panel ol { margin:0.4rem 0 0 1rem; color:#cbd5e1; display:grid; gap:4px; }
        .gi-muted { color:#94a3b8; }
        .gi-uid-card {
          margin-top:0.65rem;
          border-radius:14px;
          padding:0.9rem;
          background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(236,72,153,0.08));
          border:1px solid rgba(255,255,255,0.08);
        }
        .gi-uid-row { display:flex; gap:10px; align-items:center; }
        .gi-uid-avatar {
          width:52px; height:52px; border-radius:12px;
          background: radial-gradient(circle at 30% 30%, #fef3c7, #f59e0b);
          display:flex; align-items:center; justify-content:center;
          font-weight:700; color:#0f172a;
          box-shadow:0 6px 14px rgba(0,0,0,0.25);
        }
        .gi-chip { display:inline-flex; align-items:center; gap:6px; padding:0.35rem 0.6rem; border-radius:999px; background:rgba(255,255,255,0.08); color:#e2e8f0; font-size:0.85rem; }
        .gi-guides { display:grid; gap:0.75rem; }
      </style>
      <div class="gi-wrap">
        <div class="gi-panel">
          <h3>Description</h3>
          <div class="gi-guides">
            <?php if (trim($catContent['long_description'] ?? '') !== ''): ?>
              <div>
                <h4>About <?php echo htmlspecialchars($currentCategoryName); ?></h4>
                <p><?php echo nl2br(htmlspecialchars($catContent['long_description'] ?? '')); ?></p>
              </div>
            <?php endif; ?>
            <?php if (trim($catContent['faq'] ?? '') !== ''): ?>
              <div>
                <h4>FAQ</h4>
                <p><?php echo nl2br(htmlspecialchars($catContent['faq'] ?? '')); ?></p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="gi-panel">
          <h3>Guide</h3>
          <div class="gi-guides">
            <?php if (trim($catContent['guide'] ?? '') !== ''): ?>
              <div>
                <h4>How to top-up</h4>
                <p><?php echo nl2br(htmlspecialchars($catContent['guide'] ?? '')); ?></p>
              </div>
            <?php endif; ?>
            <?php if (trim($catContent['video_url'] ?? '') !== ''): ?>
              <?php
                $video = trim($catContent['video_url']);
                $embed = $video;
                if (strpos($video, 'youtu.be/') !== false) {
                    $embed = 'https://www.youtube.com/embed/' . basename(parse_url($video, PHP_URL_PATH));
                } elseif (strpos($video, 'watch?v=') !== false) {
                    $embed = str_replace('watch?v=', 'embed/', $video);
                }
              ?>
              <div>
                <h4>Watch</h4>
                <div style="position:relative;padding-top:56.25%;border-radius:12px;overflow:hidden;border:1px solid var(--glass);box-shadow:0 10px 28px rgba(0,0,0,0.12);">
                  <iframe src="<?php echo htmlspecialchars($embed); ?>" title="Preview" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%;border:0;"></iframe>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($relatedCategories): ?>
    <section class="container" style="margin-top:1rem;">
      <h3>More in <?php echo htmlspecialchars($category['main_name'] ?? ''); ?></h3>
      <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
        <?php foreach ($relatedCategories as $rc):
          $rcLink = 'topup.php?slug=' . urlencode($rc['slug'] ?? '');
        ?>
          <a class="ghost-pill" href="<?php echo htmlspecialchars($rcLink); ?>">
            <?php echo htmlspecialchars($rc['category_name'] ?? ''); ?> (<?php echo (int)($rc['product_count'] ?? 0); ?>)
          </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($recentViews)): ?>
    <section class="container" style="margin-top:1rem;">
      <h3>Recently viewed</h3>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <?php
          $ph = implode(',', array_fill(0, count($recentViews), '?'));
          $rvStmt = $conn->prepare("SELECT category_name, slug FROM categories WHERE slug IN ($ph)");
          $rvStmt->execute($recentViews);
          $rv = $rvStmt->fetchAll(PDO::FETCH_ASSOC);
          foreach ($rv as $r):
            $link = ($r['slug'] ?? '') ? 'topup.php?slug=' . urlencode($r['slug']) : '#';
        ?>
          <a class="ghost-pill" href="<?php echo htmlspecialchars($link); ?>"><?php echo htmlspecialchars($r['category_name'] ?? ''); ?></a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="container" style="margin-top:1.25rem;">
      <h3>Customer Reviews</h3>
      <?php if ($overallCount === 0): ?>
        <p class="muted">No reviews yet.</p>
      <?php else: ?>
        <div class="rating-bar">
          <strong><?php echo $overallAvg; ?>*</strong>
          <span class="muted tiny">(<?php echo $overallCount; ?> total)</span>
        </div>
        <div style="display:grid;gap:0.75rem;margin-top:0.75rem;">
          <?php foreach ($approvedReviews as $pid => $rows): ?>
            <?php foreach ($rows as $row): ?>
              <div style="border:1px solid var(--glass);border-radius:12px;padding:0.6rem 0.75rem;background:var(--panel);">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <div class="product-title-seagm tiny"><?php
                    foreach ($products as $pp) { if ($pp['id'] == $pid) { echo htmlspecialchars($pp['product_name']); break; } }
                  ?></div>
                  <span class="pill-outline"><?php echo (int)$row['rating']; ?>*</span>
                </div>
                <div class="muted tiny" style="margin-top:4px;"><?php echo htmlspecialchars($row['comment'] ?? ''); ?></div>
                <div class="muted tiny" style="margin-top:4px;"><?php echo htmlspecialchars($row['user_name'] ?? 'User'); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <section class="container" style="margin-top:1rem;">
      <h3>Write a Review</h3>
      <?php if (!is_logged_in()): ?>
        <p class="muted">Login to rate and review.</p>
        <a class="btn primary" href="../auth/login.php">Login</a>
      <?php else: ?>
        <form method="post" action="../user/review_submit.php" id="reviewForm" style="display:flex;flex-direction:column;gap:0.5rem;max-width:520px;">
          <input type="hidden" name="product_id" id="review_product_id" value="<?php echo (int)$firstProduct['id']; ?>">
          <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currentUrl); ?>">
          <label class="muted tiny">Your rating</label>
          <div class="rating-stars">
            <?php for($r=5;$r>=1;$r--): ?>
              <label class="star-radio">
                <input type="radio" name="rating" value="<?php echo $r; ?>" <?php echo $r===5?'checked':''; ?>>
                <span class="star-icon">*</span>
                <span class="muted tiny"><?php echo $r; ?></span>
              </label>
            <?php endfor; ?>
          </div>
          <label class="muted tiny" for="rv_comment">Comment (optional)</label>
          <textarea id="rv_comment" name="comment" rows="3" maxlength="500" placeholder="Share your experience..." class="fancy-textarea" style="width:100%;"></textarea>
          <button class="btn primary" type="submit">Submit Review</button>
        </form>
      <?php endif; ?>
    </section>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>

  <script src="../js/theme-toggle.js"></script>
  <script src="../js/app.js"></script>
  <script>
    const list = document.getElementById('topupList');
    const productIdField = document.getElementById('product_id');
    const cartProductId = document.getElementById('cartProductId');
    const wishProductId = document.getElementById('wishProductId');
    const wishAction = document.getElementById('wishAction');
    const wishBtn = document.getElementById('wishBtn');
    const qtySelect = document.getElementById('qtySelect');
    const orderTotal = document.getElementById('orderTotal');
    const reviewProductId = document.getElementById('review_product_id');
    const loaderEl = document.getElementById('pageLoader');

    function bindLoader(formId) {
      const f = document.getElementById(formId);
      if (!f) return;
      f.addEventListener('submit', () => {
        if (loaderEl) loaderEl.style.display = 'flex';
      });
    }
    bindLoader('buyForm');
    bindLoader('cartForm');
    bindLoader('wishForm');
    bindLoader('reviewForm');

    function updateTotal(price) {
      const qty = parseInt(qtySelect ? qtySelect.value : '1', 10) || 1;
      const total = price * qty;
      if (orderTotal) orderTotal.textContent = total.toFixed(2) + ' MMK';
    }

    function syncWishlist(card) {
      if (!wishProductId || !wishAction || !wishBtn) return;
      const pid = card.dataset.id || '';
      const saved = card.dataset.saved === '1';
      if (wishProductId) wishProductId.value = pid;
      if (wishAction) wishAction.value = saved ? 'remove' : 'add';
      if (wishBtn) wishBtn.textContent = saved ? 'Remove from Wishlist' : 'Add to Wishlist';
      if (cartProductId) cartProductId.value = pid;
      if (reviewProductId) reviewProductId.value = pid;
    }

    if (list && productIdField) {
      const cards = list.querySelectorAll('.topup-card');
      cards.forEach((card, idx) => {
        const price = parseFloat(card.dataset.price || '0');
        if (idx === 0) {
          card.classList.add('selected');
          updateTotal(price);
          syncWishlist(card);
        }
        card.addEventListener('click', () => {
          cards.forEach(c => c.classList.remove('selected'));
          card.classList.add('selected');
          productIdField.value = card.dataset.id || '';
          updateTotal(price);
          syncWishlist(card);
        });
      });
    }

    // Wishlist button: normal submit; keep label in sync
    // Wishlist: before submit ensure product id synced
    const wishForm = document.getElementById('wishForm');
    if (wishForm && wishBtn) {
      wishForm.addEventListener('submit', () => {
        const currentCard = document.querySelector('.topup-card.selected');
        if (currentCard && wishProductId) {
          wishProductId.value = currentCard.dataset.id || '';
        }
      });
    }

    // Client-side search filter
    const productSearch = document.getElementById('productSearch');
    if (productSearch && list) {
      productSearch.addEventListener('input', () => {
        const term = productSearch.value.toLowerCase();
        list.querySelectorAll('.topup-card').forEach(card => {
          const name = (card.dataset.name || '').toLowerCase();
          const desc = (card.querySelector('.muted')?.textContent || '').toLowerCase();
          const match = name.includes(term) || desc.includes(term);
          card.style.display = match ? 'flex' : 'none';
        });
      });
    }

    // Premium new vs existing toggle
    const premiumChoices = document.querySelectorAll('[data-premium-choice]');
    const premiumNew = document.getElementById('premiumNewFields');
    const premiumExisting = document.getElementById('premiumExistingFields');
    const accountOptionInput = document.getElementById('accountOption');
    function syncPremium(val) {
      if (accountOptionInput) accountOptionInput.value = val;
      if (premiumNew) {
        premiumNew.style.display = val === 'new' ? 'grid' : 'none';
        const newEmail = premiumNew.querySelector('input[name="delivery_email"]');
        if (newEmail) newEmail.required = val === 'new';
      }
      if (premiumExisting) {
        premiumExisting.style.display = val === 'existing' ? 'grid' : 'none';
        premiumExisting.querySelectorAll('input').forEach(inp => inp.required = val === 'existing');
      }
    }
    if (premiumChoices.length) {
      premiumChoices.forEach(radio => {
        radio.addEventListener('change', () => syncPremium(radio.value));
      });
      syncPremium(accountOptionInput ? accountOptionInput.value || 'new' : 'new');
    }

    // Success alert if flash present
    <?php if ($flash): ?>
      setTimeout(() => {
        const flashMessage = <?php echo json_encode($flash); ?>;
        if (window.showFlashModal) {
          const lower = flashMessage.toLowerCase();
          const opts = {
            title: 'Success',
            subtitle: flashMessage,
            ordersLink: '<?php echo url_for("user/account.php"); ?>'
          };

          if (lower.includes('added to cart')) {
            opts.title = 'Added to cart';
            opts.subtitle = 'Item added to your cart.';
            opts.primaryText = 'Go to cart';
            opts.primaryLink = '<?php echo url_for("user/cart.php"); ?>';
            opts.secondaryText = 'Continue shopping';
            opts.secondaryLink = '';
          } else if (lower.includes('wishlist')) {
            const removed = lower.includes('removed');
            opts.title = removed ? 'Wishlist updated' : 'Saved to wishlist';
            opts.subtitle = removed ? 'Item removed from your wishlist.' : 'Item saved to your wishlist.';
            opts.primaryText = 'Go to wishlist';
            opts.primaryLink = '<?php echo url_for("user/account.php?panel=wishlist"); ?>';
            opts.secondaryText = 'Continue shopping';
            opts.secondaryLink = '';
          } else if (lower.includes('favorite') || lower.includes('favourite')) {
            const removed = lower.includes('remove');
            opts.title = removed ? 'Favorites updated' : 'Saved to favorites';
            opts.subtitle = flashMessage;
            opts.primaryText = 'Go to saved categories';
            opts.primaryLink = '<?php echo url_for("user/account.php?panel=saved"); ?>';
            opts.secondaryText = 'Continue shopping';
            opts.secondaryLink = '';
          } else if (lower.includes('order')) {
            opts.title = 'Payment Complete';
            opts.subtitle = 'Your order has been created successfully.';
          }

          window.showFlashModal(flashMessage, opts);
        } else {
          alert(flashMessage);
        }
      }, 120);
    <?php endif; ?>
  </script>
</body>
</html>
