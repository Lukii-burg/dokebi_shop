<?php require_once __DIR__ . '/../db/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & FAQ - Dokebi Family</title>
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
        <div class="help-page">
            <h1>Help & FAQ</h1>
            <p class="muted">Find answers to common questions about our service</p>

            <div class="faq-section">
                <h2>🛒 Ordering & Payment</h2>
                <div class="faq-item">
                    <h3>How do I place an order?</h3>
                    <p>Browse our shop, add items to your cart, and proceed to checkout. You'll need to create an account or sign in to complete your purchase.</p>
                </div>
                <div class="faq-item">
                    <h3>What payment methods do you accept?</h3>
                    <p>We accept KPay, Wave Pay, Aya Pay, and Visa/Mastercard. All payments are processed securely.</p>
                </div>
                <div class="faq-item">
                    <h3>Is it safe to buy from Dokebi Family?</h3>
                    <p>Yes! We use industry-standard encryption and secure payment gateways to protect your information.</p>
                </div>
            </div>

            <div class="faq-section">
                <h2>📦 Delivery & Codes</h2>
                <div class="faq-item">
                    <h3>How long does delivery take?</h3>
                    <p>Most digital products are delivered instantly to your account page. Some items may take up to 5-15 minutes during peak hours.</p>
                </div>
                <div class="faq-item">
                    <h3>Where can I find my purchase codes?</h3>
                    <p>After completing your order, visit your <a href="<?php echo url_for('user/account.php'); ?>">Account page</a> to view your order history and redemption codes.</p>
                </div>
                <div class="faq-item">
                    <h3>How do I redeem my code?</h3>
                    <p>Each product has specific redemption instructions. Generally, you'll need to enter the code in the respective game or platform's redemption page.</p>
                </div>
            </div>

            <div class="faq-section">
                <h2>🎮 Game Top-Ups</h2>
                <div class="faq-item">
                    <h3>Do I need to provide my password?</h3>
                    <p>No! We never ask for your game account password. You only need to provide your User ID or Player ID.</p>
                </div>
                <div class="faq-item">
                    <h3>Which regions are supported?</h3>
                    <p>Most of our products support Global regions. Check the product description for specific region availability.</p>
                </div>
                <div class="faq-item">
                    <h3>Can I get a refund?</h3>
                    <p>Digital products are non-refundable once delivered. However, if you experience technical issues, please contact our support team.</p>
                </div>
            </div>

            <div class="faq-section">
                <h2>👤 Account & Security</h2>
                <div class="faq-item">
                    <h3>How do I reset my password?</h3>
                    <p>Click on "Forgot Password" on the login page and follow the instructions sent to your email.</p>
                </div>
                <div class="faq-item">
                    <h3>Can I change my email address?</h3>
                    <p>Yes, you can update your email from your account settings page.</p>
                </div>
                <div class="faq-item">
                    <h3>Is my personal information secure?</h3>
                    <p>We take privacy seriously. Your data is encrypted and never shared with third parties without your consent.</p>
                </div>
            </div>

            <div class="contact-section">
                <h2>💬 Still Need Help?</h2>
                <p>Our support team is available 24/7 to assist you.</p>
                <div class="contact-options">
                    <div class="contact-card">
                        <div class="contact-icon">📧</div>
                        <h3>Email Support</h3>
                        <p>support@digitalbazaar.com</p>
                    </div>
                    <div class="contact-card">
                        <div class="contact-icon">💬</div>
                        <h3>Live Chat</h3>
                        <p>Available 24/7</p>
                    </div>
                    <div class="contact-card">
                        <div class="contact-icon">📱</div>
                        <h3>Social Media</h3>
                        <p>@digitalbazaar</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . "/../partials/footer.php"; ?>
    <script src="../js/theme-toggle.js"></script>
</body>
</html>
