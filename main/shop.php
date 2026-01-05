<?php
require_once __DIR__ . '/../includes/catalog_queries.php';

$mainSlug     = $_GET['main'] ?? '';
$categorySlug = $_GET['category'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 12;
$offset       = ($page - 1) * $perPage;

$schema = catalog_schema($conn);
$hasMainTable   = !empty($schema['has_main_table']);
$hasMainLink    = !empty($schema['has_main_link']);
$hasCardImage   = !empty($schema['has_card_image']);
$hasSortOrder   = !empty($schema['has_sort_order']);
$hasMainAccent  = !empty($schema['has_main_accent']);

$wishlistIds = [];
$cartCount = 0;
if (is_logged_in()) {
    $w = $conn->prepare("SELECT product_id FROM wishlists WHERE user_id = ?");
    $w->execute([current_user_id()]);
    $wishlistIds = $w->fetchAll(PDO::FETCH_COLUMN);
    $cartCountStmt = $conn->prepare("
        SELECT COALESCE(SUM(ci.quantity),0)
        FROM cart_items ci
        JOIN carts c ON ci.cart_id = c.id
        WHERE c.user_id = :uid
    ");
    $cartCountStmt->execute([':uid' => current_user_id()]);
    $cartCount = (int)$cartCountStmt->fetchColumn();
}

$buildShopLink = function(array $overrides = []): string {
    $params = $_GET;
    foreach ($overrides as $key => $val) {
        if ($val === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $val;
        }
    }
    unset($params['page']);
    $qs = http_build_query($params);
    return 'shop.php' . ($qs ? '?' . $qs : '');
};


$mainCategories = fetch_main_categories($conn, $schema);

$selectedMainId   = null;
$currentMainName  = 'All Main Categories';
if ($mainSlug !== '') {
    foreach ($mainCategories as $mc) {
        if ($mc['slug'] === $mainSlug) {
            $selectedMainId  = (int)$mc['id'];
            $currentMainName = $mc['name'];
            break;
        }
    }
}


if ($hasMainLink && $selectedMainId === null && $categorySlug !== '') {
    $catLookup = $conn->prepare("
        SELECT c.main_category_id, mc.slug AS main_slug, mc.name AS main_name
        FROM categories c
        LEFT JOIN main_categories mc ON mc.id = c.main_category_id
        WHERE c.slug = :slug
        LIMIT 1
    ");
    $catLookup->execute([':slug' => $categorySlug]);
    if ($row = $catLookup->fetch(PDO::FETCH_ASSOC)) {
        $selectedMainId   = $row['main_category_id'] ? (int)$row['main_category_id'] : null;
        $mainSlug         = $mainSlug ?: ($row['main_slug'] ?? '');
        $currentMainName  = $row['main_name'] ?? $currentMainName;
    }
}

// Fetch categories for the sidebar / gallery.
$categories = fetch_categories($conn, $schema, $selectedMainId);

// Pull products with optional filters.
$whereParts = ["p.status = 'active'"];
$params = [];
$joinMain = "";
if ($hasMainTable && $hasMainLink) {
    $joinMain = "LEFT JOIN main_categories mc ON c.main_category_id = mc.id";
    if ($mainSlug !== '') {
        $whereParts[] = "mc.slug = :main_slug";
        $params[':main_slug'] = $mainSlug;
    }
}
if ($categorySlug !== '') {
    $whereParts[] = "c.slug = :slug";
    $params[':slug'] = $categorySlug;
}
if ($search !== '') {
    $searchParts = [
        "p.product_name LIKE :q",
        "p.description LIKE :q",
        "c.category_name LIKE :q"
    ];
    if ($hasMainTable && $hasMainLink) {
        $searchParts[] = "mc.name LIKE :q";
    }
    $whereParts[] = '(' . implode(' OR ', $searchParts) . ')';
    $params[':q'] = '%' . $search . '%';
}
$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

// Count for pagination
$countSql = "
    SELECT COUNT(*)
    FROM products p
    JOIN categories c ON p.category_id = c.id
    $joinMain
    $whereSql
";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalRows   = (int)$countStmt->fetchColumn();
$totalPages  = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "
        SELECT p.*, c.category_name, c.slug AS category_slug
        " . ($hasMainTable && $hasMainLink ? ", mc.name AS main_category_name, mc.slug AS main_category_slug" : "") . "
        FROM products p
        JOIN categories c ON p.category_id = c.id
        $joinMain
        $whereSql
        ORDER BY " . ($hasMainTable && $hasMainLink ? "COALESCE(mc.sort_order,0)," : "") . " c.category_name, p.product_name
        LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentCategoryName = 'All Products';
if ($categorySlug !== '') {
    foreach ($categories as $c) {
        if (($c['slug'] ?? '') === $categorySlug) {
            $currentCategoryName = $c['category_name'];
            break;
        }
    }
} elseif ($selectedMainId !== null && $currentMainName !== 'All Main Categories') {
    $currentCategoryName = $currentMainName;
}

$categoriesForTiles = $categories;
$searchActive = ($search !== '');
if ($searchActive) {
    $categoriesPool = fetch_categories($conn, $schema, null);
    $categoriesForTiles = [];
    $needle = $search;
    foreach ($categoriesPool as $cat) {
        if ($selectedMainId !== null && isset($cat['main_category_id']) && (int)$cat['main_category_id'] !== $selectedMainId) {
            continue;
        }
        $haystack = ($cat['category_name'] ?? '') . ' ' . ($cat['slug'] ?? '') . ' ' . ($cat['main_name'] ?? '');
        if (stripos($haystack, $needle) !== false) {
            $categoriesForTiles[] = $cat;
        }
    }
}
$searchCategoryCount = count($categoriesForTiles);
$searchProductCount = $totalRows;

// Recommended (top sellers)
$recommended = [];
$excludeIds = array_column($products, 'id');

$excludeClause = '';
$excludeParams = [];
if ($excludeIds) {
    $excludeIds = array_map('intval', $excludeIds);
    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
    $excludeClause = "AND p.id NOT IN ($placeholders)";
    $excludeParams = $excludeIds;
}

$recSql = "
    SELECT p.*, c.category_name, c.slug AS category_slug, COALESCE(SUM(oi.quantity),0) AS qty_sold
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    WHERE p.status = 'active' $excludeClause
    GROUP BY p.id, c.category_name
    ORDER BY qty_sold DESC, p.id DESC
    LIMIT 6
";
$recStmt = $conn->prepare($recSql);
$recStmt->execute($excludeParams);
$recommended = $recStmt->fetchAll(PDO::FETCH_ASSOC);

$allProductIds = [];
foreach ($products as $pr) { $allProductIds[] = (int)$pr['id']; }
foreach ($recommended as $rr) { $allProductIds[] = (int)$rr['id']; }
$allProductIds = array_values(array_unique(array_filter($allProductIds)));

$reviewStats = [];
$userReviews = [];
$approvedReviews = [];
if ($allProductIds) {
    $ph = implode(',', array_fill(0, count($allProductIds), '?'));
    $revStmt = $conn->prepare("
        SELECT product_id, ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS review_count
        FROM reviews
        WHERE status = 'approved' AND product_id IN ($ph)
        GROUP BY product_id
    ");
    $revStmt->execute($allProductIds);
    foreach ($revStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $reviewStats[(int)$row['product_id']] = [
            'avg' => (float)$row['avg_rating'],
            'count' => (int)$row['review_count']
        ];
    }

    if (is_logged_in()) {
        $ph = implode(',', array_fill(0, count($allProductIds), '?'));
        $my = $conn->prepare("
            SELECT product_id, rating, comment, status
            FROM reviews
            WHERE user_id = ? AND product_id IN ($ph)
        ");
        $my->execute(array_merge([current_user_id()], $allProductIds));
        foreach ($my->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $userReviews[(int)$row['product_id']] = $row;
        }
    }

    $approvedStmt = $conn->prepare("
        SELECT r.product_id, r.rating, r.comment, u.name AS user_name
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.status = 'approved' AND r.product_id IN ($ph)
        ORDER BY r.created_at DESC
    ");
    $approvedStmt->execute($allProductIds);
    foreach ($approvedStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pid = (int)$row['product_id'];
        if (!isset($approvedReviews[$pid])) $approvedReviews[$pid] = [];
        if (count($approvedReviews[$pid]) < 3) {
            $approvedReviews[$pid][] = $row;
        }
    }
}

$categoryPageMap = [
    'mlbb-diamonds'     => 'mlbb.php',
    'pubg-uc'           => 'pubg.php',
    'freefire-diamonds' => 'freefire.php',
    'premium-accounts'  => 'premium.php',
    'genshin-impact'    => 'genshin.php',
    'valorant-points'   => 'valorant.php',
    'mm-topup'          => 'mmtopup.php',
    'gift-cards'        => 'giftcards.php',
    'steam-wallet'             => 'giftcards.php',
    'google-play-gift-cards'   => 'giftcards.php',
    'app-store-gift-cards'     => 'giftcards.php',
    'mpt-topup'                => 'topup.php',
    'u9-topup'                 => 'topup.php',
    'atom-topup'               => 'topup.php',
    'mytel-topup'              => 'topup.php',
    'spotify-premium'          => 'topup.php',
    'youtube-premium'          => 'topup.php',
    'netflix-premium'          => 'topup.php',
    'telegram-premium'         => 'topup.php',
    'chatgpt-premium'          => 'topup.php',
];

$visibleProductCount = 0;
foreach ($categories as $c) {
    $visibleProductCount += (int)($c['product_count'] ?? 0);
}
$allMainProductCount = 0;
foreach ($mainCategories as $mc) {
    $allMainProductCount += (int)($mc['product_count'] ?? 0);
}

$currentQuery = $_SERVER['QUERY_STRING'] ?? '';
$currentShopUrl = url_for('main/shop.php') . ($currentQuery ? '?' . $currentQuery : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Shop - Dokebi Family</title>
  <script>
    (function() {
      try {
        const saved = localStorage.getItem('dokebi_theme');
        const preset = document.documentElement.getAttribute('data-theme');
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (!preset) {
          const initial = (saved === 'dark' || saved === 'light') ? saved : (prefersDark ? 'dark' : 'light');
          document.documentElement.setAttribute('data-theme', initial);
        }
      } catch (err) {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    })();
  </script>
  <link rel="stylesheet" href="../maincss/style.css">
  <link rel="stylesheet" href="../maincss/dark-mode.css">
  <link rel="stylesheet" href="../maincss/shop.css">
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <button id="menuToggle" class="menu-btn" aria-label="Toggle menu">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 12h18M3 6h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
      <a class="logo" href="index.php"><img src="../logo/original.png" alt="Dokebi Family" class="logo-img"><span class="sitename">Dokebi Family</span></a>
      <nav class="main-nav">
        <a href="index.php">Home</a>
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
          <span>Dark / Light</span>
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

  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  
  <main class="shop-main">
    <div class="container" style="margin-bottom: 1rem;">
      <section class="shop-hero">
        <div>
          <div class="pill-dot" style="background:var(--accent);"></div>
          <h1 style="margin:0;">Shop</h1>
          <p class="muted">Search and jump into the right top-up or gift card.</p>
        </div>
        <form class="shop-search" method="get">
          <input id="searchBox" name="q" type="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
          <?php if ($mainSlug): ?>
            <input type="hidden" name="main" value="<?php echo htmlspecialchars($mainSlug); ?>">
          <?php endif; ?>
          <?php if ($categorySlug): ?>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($categorySlug); ?>">
          <?php endif; ?>
          <button type="submit">Search</button>
        </form>
      </section>
    </div>
    <div class="shop-container">
      <aside class="shop-sidebar" id="sidebar">
        <div class="sidebar-block">
          <h3 class="sidebar-title">MAIN CATEGORY</h3>
          <div class="maincat-list">
            <a class="maincat-pill <?php echo $mainSlug===''?'active':''; ?>" href="<?php echo htmlspecialchars($buildShopLink(['main'=>null, 'category'=>null])); ?>" style="--accent: var(--accent);">
              <span class="pill-icon pill-icon--ghost">All</span>
              <div class="pill-copy">
                <div class="pill-title">All</div>
                <div class="pill-sub muted tiny"><?php echo $allMainProductCount; ?> items</div>
              </div>
            </a>
            <?php foreach ($mainCategories as $mc): 
              $pillLink = $buildShopLink(['main'=>$mc['slug'], 'category'=>null]);
              $isActiveMain = $mainSlug === $mc['slug'];
              $accent = $mc['accent_color'] ?? '#0ea5e9';
            ?>
              <a class="maincat-pill <?php echo $isActiveMain?'active':''; ?>" href="<?php echo htmlspecialchars($pillLink); ?>" style="--accent: <?php echo htmlspecialchars($accent); ?>;">
                <span class="pill-icon" style="background: <?php echo htmlspecialchars($accent); ?>;"></span>
                <div class="pill-copy">
                  <div class="pill-title"><?php echo htmlspecialchars($mc['name']); ?></div>
                  <div class="pill-sub muted tiny"><?php echo (int)($mc['sub_count'] ?? 0); ?> sub &bull; <?php echo (int)($mc['product_count'] ?? 0); ?> items</div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="sidebar-block">
          <h4 class="sidebar-title">Sub Categories</h4>
          <div class="category-list category-list--stacked">
            <a class="category-item <?php echo $categorySlug===''?'active':''; ?>" href="<?php echo htmlspecialchars($buildShopLink(['category'=>null])); ?>">
              <?php echo $selectedMainId ? 'All in ' . htmlspecialchars($currentMainName) : 'All Products'; ?>
              <span class="muted tiny">(<?php echo $visibleProductCount; ?>)</span>
            </a>
            <?php foreach ($categories as $cat): 
              $catLink = $buildShopLink([
                'category' => $cat['slug'] ?? '',
                'main'     => $cat['main_slug'] ?? null,
                'page'     => 1
              ]);
              $isActiveCat = ($cat['slug'] ?? '') === $categorySlug;
            ?>
              <a class="category-item <?php echo $isActiveCat?'active':''; ?>" href="<?php echo htmlspecialchars($catLink); ?>">
                <?php echo htmlspecialchars($cat['category_name'] ?? ''); ?>
                <span class="muted tiny">(<?php echo (int)($cat['product_count'] ?? 0); ?>)</span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </aside>

      <div class="shop-content">
        <div class="breadcrumb">
          <span>Dokebi Family</span>
          <span class="breadcrumb-sep">/</span>
          <?php if ($currentMainName && $currentMainName !== 'All Main Categories'): ?>
            <span><?php echo htmlspecialchars($currentMainName); ?></span>
            <span class="breadcrumb-sep">/</span>
          <?php endif; ?>
          <span id="currentCategoryName"><?php echo htmlspecialchars($currentCategoryName); ?></span>
        </div>

        <?php if ($searchActive): ?>
          <section class="container" style="margin-top:12px; margin-bottom:8px;">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
              <div>
                <h3 style="margin:0;">Search results for "<?php echo htmlspecialchars($search); ?>"</h3>
                <p class="muted tiny"><?php echo $searchCategoryCount; ?> categories &bull; <?php echo $searchProductCount; ?> products</p>
              </div>
              <a class="btn" href="shop.php" style="white-space:nowrap;">Clear search</a>
            </div>
          </section>
        <?php endif; ?>

        <?php
          $categoriesForTilesData = $searchActive ? $categoriesForTiles : $categories;
          if (!empty($categoriesForTilesData)):
            $categoriesForTiles = $categoriesForTilesData;
            $categorySlug = $categorySlug ?? '';
            $buildShopLink = $buildShopLink ?? function(array $overrides = []): string { return 'shop.php'; };
            include __DIR__ . '/../partials/category_tiles.php';
          elseif ($searchActive):
        ?>
          <p class="muted" style="margin:0 1rem;">No categories matched your search.</p>
        <?php endif; ?>

        <?php if ($searchActive): ?>
          <section class="container" style="margin-top:24px;">
            <h3 style="margin-bottom:0.75rem;">Products</h3>
            <?php if ($products): ?>
              <div class="product-grid-seagm">
                <?php foreach ($products as $prod):
                  $img = $prod['product_image'] ?? '';
                  $imgPath = $img ? '../uploads/products/'.$img : '../logo/original.png';
                  $slug = $prod['category_slug'] ?? '';
                  $targetPage = $categoryPageMap[$slug] ?? 'topup.php';
                  $link = $slug ? $targetPage . '?slug=' . urlencode($slug) : '#';
                  $excerpt = (string)($prod['description'] ?? '');
                  if (strlen($excerpt) > 120) { $excerpt = substr($excerpt, 0, 117) . '...'; }
                ?>
                <a class="product-card-seagm" href="<?php echo htmlspecialchars($link); ?>" style="text-decoration:none;">
                  <div class="product-image-seagm">
                    <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($prod['product_name']); ?>">
                  </div>
                  <div class="product-info-seagm">
                    <div class="product-title-seagm"><?php echo htmlspecialchars($prod['product_name']); ?></div>
                    <div class="product-meta-seagm"><?php echo htmlspecialchars($prod['category_name'] ?? ''); ?></div>
                    <?php if ($excerpt): ?><div class="muted tiny" style="min-height:36px;"><?php echo htmlspecialchars($excerpt); ?></div><?php endif; ?>
                    <div class="price"><?php echo number_format((float)$prod['price'],2); ?> MMK</div>
                  </div>
                </a>
                <?php endforeach; ?>
              </div>
              <?php if ($totalPages > 1): ?>
                <div class="pagination" style="display:flex;align-items:center;gap:0.5rem; margin-top:0.75rem; flex-wrap:wrap;">
                  <span class="muted tiny">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                  <?php if ($page > 1): ?>
                    <a class="btn" href="<?php echo htmlspecialchars($buildShopLink(['page' => $page - 1])); ?>">Previous</a>
                  <?php endif; ?>
                  <?php if ($page < $totalPages): ?>
                    <a class="btn" href="<?php echo htmlspecialchars($buildShopLink(['page' => $page + 1])); ?>">Next</a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <p class="muted">No products matched your search.</p>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <section class="container" style="margin-top:32px;">
          <h3>Recommended for you</h3>
          <?php if (!$recommended): ?>
            <p class="muted">Browse a game page to see recommendations.</p>
          <?php else: ?>
            <div class="product-grid-seagm">
              <?php foreach ($recommended as $rec):
                $img = $rec['product_image'] ?? '';
                $imgPath = $img ? '../uploads/products/'.$img : '../logo/original.png';
                $slug = $rec['category_slug'] ?? '';
                $targetPage = $categoryPageMap[$slug] ?? 'topup.php';
                $link = $slug ? $targetPage . '?slug=' . urlencode($slug) : '#';
              ?>
              <a class="product-card-seagm" href="<?php echo htmlspecialchars($link); ?>" style="text-decoration:none;">
                <div class="product-image-seagm">
                  <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($rec['product_name']); ?>">
                </div>
                <div class="product-info-seagm">
                  <div class="product-title-seagm"><?php echo htmlspecialchars($rec['product_name']); ?></div>
                  <div class="product-meta-seagm"><?php echo htmlspecialchars($rec['category_name'] ?? ''); ?></div>
                  <div class="price"><?php echo number_format($rec['price'],2); ?> MMK</div>
                  <div class="muted tiny">Top purchased</div>
                </div>
              </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </main>

  <?php include __DIR__ . '/../partials/footer.php'; ?>
  <script src="../js/theme-toggle.js"></script>
  <script>
    // Clear search when switching category
    document.querySelectorAll('.category-item').forEach(link => {
      link.addEventListener('click', () => {
        const sb = document.getElementById('searchBox');
        if (sb) sb.value = '';
      });
    });
    // Mobile menu toggle
    (function(){
      const toggle = document.getElementById('menuToggle');
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebarOverlay');
      if(!toggle || !sidebar || !overlay) return;
      function openMenu(){ sidebar.classList.add('open'); overlay.classList.add('active'); }
      function closeMenu(){ sidebar.classList.remove('open'); overlay.classList.remove('active'); }
      toggle.addEventListener('click', () => {
        if (sidebar.classList.contains('open')) { closeMenu(); } else { openMenu(); }
      });
      overlay.addEventListener('click', closeMenu);
    })();
  </script>
</body>
</html>



