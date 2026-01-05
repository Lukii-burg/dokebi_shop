<?php require_once __DIR__ . '/../db/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms &amp; Conditions - Dokebi Family</title>
    <link rel="stylesheet" href="../maincss/style.css">
    <link rel="stylesheet" href="../maincss/dark-mode.css">
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
                <a href="news.php">News</a>
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
        <div class="terms-page">
            <h1>Terms &amp; Conditions</h1>
            <span class="muted">Last updated <?php echo date('F j, Y'); ?></span>
            <div style="margin:0.75rem 0 1.1rem;">
                <a class="btn" href="<?php echo url_for('auth/register.php'); ?>">&larr; Back to create account</a>
            </div>

            <div class="terms-section">
                <h2>Using Dokebi Family</h2>
                <p>By creating an account or completing a purchase you agree to follow these terms and all applicable laws.</p>
                <ul>
                    <li>Provide accurate information during registration and checkout.</li>
                    <li>Keep your login credentials secure and never share them.</li>
                    <li>Use the platform only for personal, lawful purposes.</li>
                </ul>
            </div>

            <div class="terms-section">
                <h2>Accounts &amp; Security</h2>
                <p>You are responsible for all activity under your account. Contact support immediately if you suspect unauthorized access.</p>
                <ul>
                    <li>We may suspend or remove accounts involved in fraud, abuse, or policy violations.</li>
                    <li>You must be able to receive transactional emails related to orders and security.</li>
                </ul>
            </div>

            <div class="terms-section">
                <h2>Payments &amp; Delivery</h2>
                <p>Digital items are delivered to your account after payment confirmation.</p>
                <ul>
                    <li>Prices and promotions may change at any time.</li>
                    <li>Ensure the game ID, region, or platform details you provide are correct before submitting an order.</li>
                </ul>
            </div>

            <div class="terms-section">
                <h2>Refunds &amp; Cancellations</h2>
                <p>Most digital purchases are final once delivery begins. We review refund requests when delivery fails or a product is unavailable.</p>
            </div>

            <div class="terms-section">
                <h2>Privacy</h2>
                <p>We collect only the data needed to operate your account and fulfill orders. We never sell your personal information.</p>
            </div>

            <div class="terms-section">
                <h2>Contact</h2>
                <p>Questions about these terms? Reach us at <a href="mailto:dobekisupport@gmail.com">dobekisupport@gmail.com</a>.</p>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
    <script src="../js/theme-toggle.js"></script>
</body>
</html>
