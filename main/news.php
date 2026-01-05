<?php require_once __DIR__ . '/../db/functions.php'; ?>
<?php

$newsCards = [
    [
        'title'     => 'Weekend Special: Up to 20% OFF on All Game Top-Ups!',
        'category'  => 'Promotion',
        'date'      => date('M d, Y'),
        'desc'      => "This weekend only! Get incredible discounts on Mobile Legends, PUBG Mobile, Free Fire, and more. Don't miss out on these limited-time offers!",
        'cta_text'  => 'Shop Now',
        'cta_link'  => 'shop.php',
        'featured'  => true,
        'gradient'  => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'image_url' => '../uploads\categories\4a79f0d9cd9de763ce4ef01820337b3f-692f5abf70f62.jpg'
    ],
    [
        'title'     => 'Mobile Legends: New Season Launch',
        'category'  => 'Game Update',
        'date'      => date('M d, Y', strtotime('-2 days')),
        'desc'      => 'The new MLBB season is here! Stock up on diamonds and unlock exclusive skins and battle passes.',
        'cta_text'  => 'View Diamonds',
        'cta_link'  => 'shop.php#mlbb',
        'gradient'  => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'image_url' => '../uploads\categories\71d2141b6bedf4841528fce2c181597e-692f56fe1fdd6.jpg'
    ],
    [
        'title'     => 'Steam Winter Sale Coming Soon',
        'category'  => 'Platform News',
        'date'      => date('M d, Y', strtotime('-5 days')),
        'desc'      => 'Prepare your Steam Wallet! The annual Winter Sale is approaching with massive discounts on thousands of games.',
        'cta_text'  => 'Get Steam Wallet',
        'cta_link'  => 'shop.php#steam',
        'gradient'  => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'image_url' => '../uploads/categories/winter.avif'
    ],
    [
        'title'     => 'Genshin Impact Genesis Crystals Now Available',
        'category'  => 'New Product',
        'date'      => date('M d, Y', strtotime('-7 days')),
        'desc'      => 'Top up your Genesis Crystals instantly! Support for all regions with fast delivery and competitive prices.',
        'cta_text'  => 'Top Up Now',
        'cta_link'  => 'shop.php#genshin',
        'gradient'  => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'image_url' => '../uploads/categories/4a79f0d9cd9de763ce4ef01820337b3f-692f5abf70f62.jpg'
    ],
    [
        'title'     => 'How to Redeem Your Gift Cards',
        'category'  => 'Tips & Guides',
        'date'      => date('M d, Y', strtotime('-10 days')),
        'desc'      => 'Step-by-step guide on redeeming PlayStation, Xbox, Nintendo, and other gift cards. Quick and easy!',
        'cta_text'  => 'Read Guide',
        'cta_link'  => 'help.php',
        'gradient'  => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
        'image_url' => '../uploads/categories/46558fe0-d348-11ea-b9f3-34a01b18f5f2-693093bb5e9ae.png'
    ],
    [
        'title'     => '24/7 Customer Support Now Live',
        'category'  => 'Announcement',
        'date'      => date('M d, Y', strtotime('-14 days')),
        'desc'      => "We're excited to announce round-the-clock customer support! Get help anytime you need it.",
        'cta_text'  => 'Contact Support',
        'cta_link'  => 'help.php',
        'gradient'  => 'linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%)',
        'image_url' => '../uploads/categories/customersup.jpg'
    ],
    [
        'title'     => 'Refer a Friend & Get Rewards',
        'category'  => 'Event',
        'date'      => date('M d, Y', strtotime('-20 days')),
        'desc'      => 'Invite your friends to Dokebi Family and both of you get exclusive bonuses on your next purchase!',
        'cta_text'  => 'Get Referral Link',
        'cta_link'  => url_for('user/account.php'),
        'gradient'  => 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)',
        'image_url' => '../uploads/categories/referal.avif'
    ],
    [
        'title'     => 'Enhanced Security Features',
        'category'  => 'Security',
        'date'      => date('M d, Y', strtotime('-25 days')),
        'desc'      => 'Your safety is our priority. We have implemented additional security measures to protect your transactions.',
        'cta_text'  => 'Learn More',
        'cta_link'  => 'help.php',
        'gradient'  => 'linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%)',
        'image_url' => '../uploads/categories/secu.webp'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Promotions - Dokebi Family</title>
    <link rel="stylesheet" href="../maincss/style.css">
    <link rel="stylesheet" href="../maincss/dark-mode.css">
    <style>
      .news-image { position: relative; overflow: hidden; }
      .news-image img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
      .news-image .news-category {
        position: relative;
        z-index: 2;
        display: inline-flex;
        align-items: center;
        padding: 8px 12px;
        border-radius: 12px;
        background: rgba(255,255,255,0.92);
        color: #0f172a;
        font-weight: 700;
        font-size: 0.95rem;
        box-shadow: 0 8px 20px rgba(0,0,0,0.18);
      }
      .news-image::after {
        content:'';
        position:absolute;
        inset:0;
        background: linear-gradient(180deg, rgba(0,0,0,0.02), rgba(0,0,0,0.30));
        opacity: 0.9;
        z-index:1;
      }
      .news-image img + .news-category { position:absolute; left:12px; bottom:12px; }
      [data-theme="dark"] .news-image .news-category {
        background: rgba(15,23,42,0.92);
        color: #e2e8f0;
      }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a class="back-icon" href="index.php" aria-label="Home">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <a class="logo" href="index.php"><img src="../logo/original.png" alt="Dokebi Family" class="logo-img"><span class="sitename">Dokebi Family</span></a>
            <nav class="main-nav">
                <a href="shop.php">Shop</a>
                <a href="help.php">Help</a>
                <?php if(!is_logged_in()): ?>
                    <a href="../auth/login.php">Login</a>
                <?php else: ?>
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
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="news-page">
            <h1>News & Promotions</h1>
            <p class="muted">Stay updated with the latest deals, game updates, and platform news</p>

            <div class="news-grid">
                <?php foreach ($newsCards as $card): 
                    $hasImage = !empty($card['image_url']);
                    $bgStyle = $hasImage ? '' : ('background: ' . ($card['gradient'] ?? '#0ea5e9') . ';');
                    $isFeatured = !empty($card['featured']);
                    $ctaClass = $isFeatured ? 'btn primary' : 'btn';
                ?>
                    <article class="news-card<?php echo $isFeatured ? ' featured' : ''; ?>">
                        <?php if ($isFeatured): ?><div class="news-badge">Featured</div><?php endif; ?>
                        <div class="news-image" style="<?php echo htmlspecialchars($bgStyle); ?>">
                            <?php if ($hasImage): ?>
                                <img src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="<?php echo htmlspecialchars($card['title']); ?>">
                            <?php endif; ?>
                            <div class="news-category"><?php echo htmlspecialchars($card['category']); ?></div>
                        </div>
                        <div class="news-content">
                            <?php if ($isFeatured): ?>
                                <h2><?php echo htmlspecialchars($card['title']); ?></h2>
                            <?php else: ?>
                                <h3><?php echo htmlspecialchars($card['title']); ?></h3>
                            <?php endif; ?>
                            <p class="news-meta">Posted on <?php echo htmlspecialchars($card['date']); ?></p>
                            <p><?php echo htmlspecialchars($card['desc']); ?></p>
                            <a href="<?php echo htmlspecialchars($card['cta_link']); ?>" class="<?php echo $ctaClass; ?>"><?php echo htmlspecialchars($card['cta_text']); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <?php include __DIR__ . "/../partials/footer.php"; ?>
    <script src="../js/theme-toggle.js"></script>
</body>
</html>
