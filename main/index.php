<?php
require_once __DIR__ . '/../db/functions.php';

// Helpers so we can gracefully handle older DBs without the new main_categories table.
$tableExists = function(PDO $conn, string $table): bool {
    try {
        $stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
};
$columnExists = function(PDO $conn, string $table, string $col): bool {
    try {
        $stmt = $conn->prepare("SELECT
         1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
        $stmt->execute([$table, $col]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
};

$hasMainTable  = $tableExists($conn, 'main_categories');
$hasMainLink   = $columnExists($conn, 'categories', 'main_category_id');
$hasCardImage  = $columnExists($conn, 'categories', 'card_image');
$hasMainAccent = $columnExists($conn, 'main_categories', 'accent_color');

$mainCategoryCards = [];
if ($hasMainTable && $hasMainLink) {
    try {
        $mainCategoryCards = $conn->query("
            SELECT mc.id, mc.name, mc.slug, mc.cover_image, mc.accent_color,
                   COUNT(DISTINCT c.id) AS sub_count,
                   COUNT(DISTINCT p.id) AS product_count
            FROM main_categories mc
            LEFT JOIN categories c ON c.main_category_id = mc.id AND c.is_active = 1
            LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
            WHERE mc.is_active = 1
            GROUP BY mc.id, mc.name, mc.slug, mc.cover_image, mc.accent_color
            ORDER BY mc.sort_order, mc.name
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mainCategoryCards = [];
    }
}

if ($hasMainLink && $hasCardImage) {
    $featuredCategoriesStmt = $conn->query("
        SELECT c.id, c.category_name, c.slug, c.card_image, mc.slug AS main_slug, mc.name AS main_name, mc.accent_color,
               COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN main_categories mc ON mc.id = c.main_category_id
        LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
        WHERE c.is_active = 1
        GROUP BY c.id, c.category_name, c.slug, c.card_image, mc.slug, mc.name, mc.accent_color
        ORDER BY product_count DESC, c.category_name
        LIMIT 12
    ");
    $featuredCategories = $featuredCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Fallback for older schema: show the basics without images or main linkage.
    $featuredCategories = $conn->query("
        SELECT c.id, c.category_name, c.slug, '' AS card_image, '' AS main_slug, '' AS main_name, '' AS accent_color,
               COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        WHERE c.is_active = 1
        GROUP BY c.id, c.category_name, c.slug
        ORDER BY product_count DESC, c.category_name
        LIMIT 12
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Popular items from DB (top sellers)
$popularItems = [];
try {
    $popularStmt = $conn->query("
        SELECT 
            p.id,
            p.product_name,
            p.price,
            p.description,
            p.product_image,
            c.category_name,
            c.slug AS category_slug,
            mc.slug AS main_slug,
            SUM(COALESCE(oi.quantity,0)) AS qty_sold
        FROM products p
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN main_categories mc ON mc.id = c.main_category_id
        LEFT JOIN order_items oi ON oi.product_id = p.id
        WHERE p.status = 'active'
        GROUP BY p.id, p.product_name, p.price, p.description, p.product_image, c.category_name, c.slug, mc.slug
        ORDER BY qty_sold DESC, p.id DESC
        LIMIT 8
    ");
    $popularItems = $popularStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $popularItems = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokebi Family</title>
    <link rel="stylesheet" href="../maincss/style.css">
    <link rel="stylesheet" href="../maincss/dark-mode.css">
    <style>
      /* Hero slider inspired by SEAGM promo */
      .hero-slider {
        position: relative;
        width: 100%;
        max-width: 1180px;
        margin: 1.5rem auto 1rem auto;
        padding: 0 1rem;
      }
      .hero-slider__viewport {
        position: relative;
        overflow: visible;
        height: 340px;
      }
      .hero-slide {
        position: absolute;
        inset: 0;
        border-radius: 18px;
        background-size: cover;
        background-position: center;
        transition: opacity .5s ease, transform .5s ease, filter .5s ease;
        opacity: 0;
        filter: blur(2px) saturate(0.6);
        transform: scale(0.92);
        box-shadow: 0 18px 40px rgba(15,23,42,0.28);
      }
      .hero-slide.active {
        opacity: 1;
        filter: none;
        transform: scale(1);
        z-index: 2;
      }
      .hero-slider__dots {
        display: flex;
        gap: 8px;
        justify-content: center;
        margin-top: 12px;
      }
      .hero-slider__dot {
        width: 42px;
        height: 6px;
        border-radius: 999px;
        border: none;
        background: rgba(148,163,184,0.4);
        cursor: pointer;
        transition: background .2s ease, transform .2s ease;
      }
      .hero-slider__dot.active {
        background: var(--accent);
        transform: translateY(-2px);
      }
      @media(max-width: 720px){
        .hero-slider__viewport { height: 220px; }
      }
      .hero-slider__arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 38px;
        height: 38px;
        border-radius: 50%;
        border: 1px solid rgba(255,255,255,0.35);
        background: rgba(15,23,42,0.45);
        color: #fff;
        display: grid;
        place-items: center;
        cursor: pointer;
        z-index: 3;
        transition: background .2s ease, transform .2s ease;
      }
      .hero-slider__arrow:hover {
        background: rgba(15,23,42,0.7);
        transform: translateY(-50%) scale(1.05);
      }
      .hero-slider__arrow--prev { left: 10px; }
      .hero-slider__arrow--next { right: 10px; }
      .hero-slider__arrow svg { width: 16px; height: 16px; }
    </style>
</head>

<body>
    <header class="site-header">
        <div class="container header-inner">
            <a class="logo" href="index.php"><img src="../logo/original.png" alt="Dokebi Family" class="logo-img"><span class="sitename">Dokebi Family</span></a>
            <nav class="main-nav">
                <a href="shop.php">Shop</a>
                <a href="news.php">News</a>
                <a href="help.php">Help</a>
                <?php if(!is_logged_in()): ?>
                    <a href="../auth/login.php">Login</a>
                <?php else:
                    if (is_admin()): ?>
                        <a href="../admin/dashboard.php">Admin Dashboard</a>
                    <?php else: ?>
                        <a href="../user/account.php">My Account</a>
                    <?php endif; ?>
                    <a href="../auth/logout.php">Logout</a>
                    <?php
                        $avatar = $_SESSION['user_image'] ?? 'default_user.png';
                        $avatarPath = "../uploads/users/" . $avatar;
                    ?>
                    <a class="profile-chip" href="../user/account.php" title="Edit profile">
                        <img src="<?= htmlspecialchars($avatarPath) ?>" alt="Profile" class="chip-avatar">
                        <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Me') ?></span>
                    </a>
                <?php endif; ?>
                <div class="theme-toggle">
                    <span>Dark / Light</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </nav>
        </div>
        <div class="promo-marquee" role="status" aria-label="Current promotion">
            <div class="container">
                <div class="promo-marquee__track">
                    <span class="promo-marquee__item">December 20% promotion on all products | 01.12.2025 - 30.12.2025</span>
                    <span class="promo-marquee__item">Fast digital delivery | Pay with KPay, Wave Pay, Aya Pay, Visa</span>
                    <span class="promo-marquee__item">December 20% promotion on all products | 01.12.2025 - 30.12.2025</span>
                    <span class="promo-marquee__item">Fast digital delivery | Pay with KPay, Wave Pay, Aya Pay, Visa</span>
                </div>
            </div>
        </div>
    </header>

    <main>
        <?php if(!empty($_SESSION['flash'])): ?>
            <div class="notice" style="margin:0.5rem 1rem;"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
        <?php endif; ?>
        <?php if(!is_logged_in()): ?>
            <div class="welcome-offer">
                <div class="container welcome-offer__inner">
                    <div class="welcome-offer__text">
                        New here? Register today and grab a promo code for <strong>50% off</strong> your first purchase.
                    </div>
                    <a class="btn primary" href="../auth/register.php">Get my 50% off</a>
                </div>
            </div>
        <?php endif; ?>
        <section class="hero">
            <div class="container">
                <h1>Buy Diamonds, UC, Gift Cards & Premium Accounts</h1>
                                <p>Fast delivery, secure checkout, and multiple payment options (KPay, Wave Pay, Aya Pay, Visa).</p>
                                <a href="shop.php" class="btn primary">Shop Now</a>
        <div class="hero-slider">
          <div class="hero-slider__viewport">
            <div class="hero-slide active" style="background-image:url('../res/e682c-17132809203578-1920.webp');"></div>
            <div class="hero-slide" style="background-image:url('../res/PUBG-tips.jpg');"></div>
            <div class="hero-slide" style="background-image:url('../res/spotify-doubles-apple-in-subscribers-and-builds-its-podcast_qkes.1200.webp');"></div>
            <div class="hero-slide" style="background-image:url('../res/7Cbpe8JbsPYc3ZfhzrP5ae.jpg');"></div>
            <button class="hero-slider__arrow hero-slider__arrow--prev" type="button" aria-label="Previous slide">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <button class="hero-slider__arrow hero-slider__arrow--next" type="button" aria-label="Next slide">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
            </button>
          </div>
          <div class="hero-slider__dots"></div>
        </div>
                <div class="deals-section promo-board">
            <div class="promo-head">
              <h2>📰 News & Promotions</h2>
              <a class="view-more" href="news.php">View more ➜</a>
            </div>
            <div class="promo-grid">
              <a class="promo-tile" href="shop.php">
                <img src="../res/mlbbadp.png" alt="Promo 1">
                <div class="promo-title">Don't miss December Promotions!</div>
              </a>
              <a class="promo-tile" href="shop.php">
                <img src="../res/pubgadp.png" alt="Promo 2">
                <div class="promo-title">Min spend 10000 MMK to get extra Uc for PUBG Mobile!</div>
              </a>
              <a class="promo-tile" href="shop.php">
                <img src="../res/spotify-doubles-apple-in-subscribers-and-builds-its-podcast_qkes.1200.webp" alt="Promo 3">
                <div class="promo-title">Streaming Deals & Bonus Pins</div>
              </a>
              <a class="promo-tile" href="shop.php">
                <img src="../res/7Cbpe8JbsPYc3ZfhzrP5ae.jpg" alt="Promo 4">
                <div class="promo-title">Grab Your Bonus Pin</div>
              </a>
            </div>
        </div>
        <div class="popular-section popular-panel">
            <div class="popular-header">
              <h2 style="margin:0;">Popular Game Card</h2>
              <a class="view-more" href="shop.php">View more ➜</a>
            </div>
            <div class="popular-list">
              <?php foreach ($popularItems as $p):
                $img = $p['product_image'] ?? '';
                $imgPath = $img ? '../uploads/products/'.$img : '../logo/original.png';
                $slug = $p['category_slug'] ?? '';
                $pageMap = [
                  'mlbb-diamonds'           => 'mlbb.php',
                  'pubg-uc'                 => 'pubg.php',
                  'freefire-diamonds'       => 'freefire.php',
                  'premium-accounts'        => 'premium.php',
                  'genshin-impact'          => 'genshin.php',
                  'valorant-points'         => 'valorant.php',
                  'mm-topup'                => 'mmtopup.php',
                  'gift-cards'              => 'giftcards.php',
                  'steam-wallet'            => 'giftcards.php',
                  'google-play-gift-cards'  => 'giftcards.php',
                  'app-store-gift-cards'    => 'giftcards.php',
                  'mpt-topup'               => 'topup.php',
                  'u9-topup'                => 'topup.php',
                  'atom-topup'              => 'topup.php',
                  'mytel-topup'             => 'topup.php',
                  'spotify-premium'         => 'topup.php',
                  'youtube-premium'         => 'topup.php',
                  'netflix-premium'         => 'topup.php',
                  'telegram-premium'        => 'topup.php',
                  'chatgpt-premium'         => 'topup.php',
                ];
                $page = $slug ? ($pageMap[$slug] ?? 'topup.php') : 'topup.php';
                $link = $slug ? $page . '?slug=' . urlencode($slug) : '#';
              ?>
              <a class="popular-item" href="<?php echo htmlspecialchars($link); ?>">
                <div class="popular-thumb" style="background-image:url('<?php echo htmlspecialchars($imgPath); ?>');"></div>
                <div class="popular-copy">
                  <div class="popular-title"><?php echo htmlspecialchars($p['product_name']); ?></div>
                  <div class="popular-sub"><?php echo htmlspecialchars($p['category_name'] ?? ''); ?></div>
                </div>
              </a>
              <?php endforeach; ?>
              <?php if (!$popularItems): ?>
                <p class="muted" style="grid-column:1/-1;text-align:center;">No data yet.</p>
              <?php endif; ?>
            </div>
        </div>
        <div class="categories-showcase">
            <h2>Browse Categories</h2>
            <p class="muted">Pick a game, gift card, or telco top-up.</p>
            <h3 style="margin-top:1.5rem;">Popular picks</h3>
            <?php if ($featuredCategories): ?>
            <div class="category-grid">
                <?php foreach ($featuredCategories as $cat):
                    $accent = $cat['accent_color'] ?? '#0ea5e9';
                    $imgPath = $cat['card_image'] ? '../uploads/categories/'.$cat['card_image'] : '';
                    $bgLayers = $imgPath
                      ? "url('".htmlspecialchars($imgPath, ENT_QUOTES)."')"
                      : "linear-gradient(135deg, #f3f4f6, #e5e7eb)";
                    $coverParts = [
                        "--accent:$accent",
                        "background-image:$bgLayers",
                        "background-color:#f3f4f6",
                        "background-size:cover",
                        "background-position:center"
                    ];
                    $coverStyle = implode(';', $coverParts) . ';';
                    $link = 'shop.php?category=' . urlencode($cat['slug']) . ($cat['main_slug'] ? '&main=' . urlencode($cat['main_slug']) : '');
                ?>
                <a class="category-card category-card--image" href="<?php echo htmlspecialchars($link); ?>">
                    <div class="category-card__cover" style="<?php echo $coverStyle; ?>"></div>
                    <div class="category-name"><?php echo htmlspecialchars($cat['category_name']); ?></div>
                    <div class="muted tiny"><?php echo (int)$cat['product_count']; ?> items &bull; <?php echo htmlspecialchars($cat['main_name'] ?? ''); ?></div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p class="muted">No categories found yet.</p>
            <?php endif; ?>
        </div>
        <div class="ad-video-section" style="margin-top:2rem;position:relative;">
            <video id="advVideo" class="adv-video"
                src="../res/The Abyss： Sovereign's Will ｜ New Hero Obsidia Cinematic Trailer ｜ Mobile Legends： Bang Bang.mp4"
                autoplay loop muted playsinline
                poster="">
            </video>
            <button id="advUnmuteBtn" class="adv-unmute" aria-label="Unmute advertisement" title="Unmute">🔊</button>
        </div>
            </div>
        </section>
                <section class="container features-section">
                    <h2>Why Choose Dokebi Family?</h2>
                    <p class="muted">We specialize in fast digital delivery and secure checkout. Visit our shop to browse products and buy instantly.</p>
                    <div class="features-grid">
                        <div class="feature-card">
                            <div class="feature-icon">⚡</div>
                            <h3>Instant Delivery</h3>
                            <p>Get your codes and top-ups delivered instantly to your account. No waiting required!</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🔒</div>
                            <h3>Secure Payment</h3>
                            <p>Industry-standard encryption protects your transactions. Your data is always safe with us.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">💳</div>
                            <h3>Multiple Payment Options</h3>
                            <p>Pay with KPay, Wave Pay, Aya Pay, Visa, or Mastercard. Choose what works best for you.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🌍</div>
                            <h3>Global Coverage</h3>
                            <p>Support for multiple regions and platforms. Get access to games and services worldwide.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">💬</div>
                            <h3>24/7 Support</h3>
                            <p>Our customer service team is available around the clock to help with any questions.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">💰</div>
                            <h3>Best Prices</h3>
                            <p>Competitive pricing with regular promotions and discounts. Get more value for your money.</p>
                        </div>
                    </div>
                </section>
        </main>

        <footer class="site-footer">
            <div class="container footer-content">
                <div class="footer-section">
                    <h4>Dokebi Family</h4>
                    <p class="muted">Your trusted source for game top-ups, gift cards, and digital entertainment.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <a href="shop.php">Shop</a>
                    <a href="news.php">News & Promotions</a>
                    <a href="help.php">Help & FAQ</a>
                    <a href="../user/account.php">My Account</a>
                </div>
                <div class="footer-section">
                    <h4>Popular Categories</h4>
                    <a href="shop.php#mlbb">Mobile Legends</a>
                    <a href="shop.php#pubg">PUBG Mobile</a>
                    <a href="shop.php#steam">Steam Wallet</a>
                    <a href="shop.php#psn">PlayStation</a>
                </div>
                <div class="footer-section">
                    <h4>Support</h4>
                    <p class="muted">24/7 Customer Service</p>
                    <p class="muted">📧 support@digitalbazaar.com</p>
                    <p class="muted">💬 Live Chat Available</p>
                </div>
            </div>
            <div class="container footer-bottom">
                <div class="muted">© <?php echo date('Y'); ?> Dokebi Family. All rights reserved.</div>
                <div class="muted">Powered by local Dokebi Tekoku</div>
            </div>
        </footer>
    </main>

    

    <script src="../js/theme-toggle.js"></script>
    <script src="../js/app.js" defer></script>
    <script>
    // Hero slider rotation
    (function(){
        const slides = Array.from(document.querySelectorAll('.hero-slide'));
        const dotsWrap = document.querySelector('.hero-slider__dots');
        const prevBtn = document.querySelector('.hero-slider__arrow--prev');
        const nextBtn = document.querySelector('.hero-slider__arrow--next');
        if(!slides.length || !dotsWrap) return;
        let idx = 0;
        slides.forEach((_,i)=>{
            const dot = document.createElement('button');
            dot.className = 'hero-slider__dot' + (i===0 ? ' active' : '');
            dot.dataset.index = i;
            dotsWrap.appendChild(dot);
        });
        const dots = Array.from(dotsWrap.querySelectorAll('.hero-slider__dot'));
        function setActive(next){
            slides[idx].classList.remove('active');
            dots[idx].classList.remove('active');
            idx = next;
            slides[idx].classList.add('active');
            dots[idx].classList.add('active');
        }
        dotsWrap.addEventListener('click', e => {
            const t = e.target.closest('.hero-slider__dot');
            if(!t) return;
            const next = parseInt(t.dataset.index, 10);
            if(!Number.isNaN(next)) setActive(next);
        });
        if (prevBtn) prevBtn.addEventListener('click', () => setActive((idx - 1 + slides.length) % slides.length));
        if (nextBtn) nextBtn.addEventListener('click', () => setActive((idx + 1) % slides.length));
        setInterval(()=> setActive((idx + 1) % slides.length), 4500);
    })();
    // No inline JS for deals/popular; now rendered from DB above.
    
    // Ad video unmute control
    (function(){
        const video = document.getElementById('advVideo');
        const btn = document.getElementById('advUnmuteBtn');
        if(!video || !btn) return;
        // If browser already allows autoplay with sound, hide button
        try{
            if(!video.muted){ btn.classList.add('hidden'); }
        }catch(e){}
        btn.addEventListener('click', async function(){
            try{
                video.muted = false;
                await video.play();
            }catch(err){
                // play might be blocked until user interaction; but this is a user click so should succeed
                console.log('Playback failed or blocked:', err);
            }
            btn.classList.add('hidden');
        });
        // When video ends/loops, ensure button remains hidden if unmuted
        video.addEventListener('volumechange', ()=>{
            if(!video.muted) btn.classList.add('hidden');
        });
    })();
    </script>
    
</body>
</html>

