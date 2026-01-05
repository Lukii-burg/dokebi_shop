<?php
require_once __DIR__ . '/../db/functions.php';
require_login();

$initialPanel = preg_replace('/[^a-z]/', '', $_GET['panel'] ?? 'profile') ?: 'profile';
$flashMessage = $_SESSION['flash'] ?? '';
if ($flashMessage !== '') {
    unset($_SESSION['flash']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Account - Dokebi Family</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../maincss/style.css">
  <link rel="stylesheet" href="../maincss/dark-mode.css">
  <link rel="stylesheet" href="assets/account.css">
</head>
<body class="account-body">
  <div class="page-loader" id="accountPageLoader" role="status" aria-live="polite">
    <div class="page-loader__spinner" aria-hidden="true"></div>
    <div class="page-loader__text">Loading your account...</div>
  </div>

  <header class="account-topbar">
    <div class="account-container topbar-inner">
      <a class="ghost-btn" href="../main/shop.php" aria-label="Back to Shop">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M15 18l-6-6 6-6"></path></svg>
        Back to Shop
      </a>
      <a class="account-brand" href="../main/index.php">
        <img class="brand-logo" src="../logo/original.png" alt="Dokebi Family logo">
        <span class="brand-copy">
          <span class="brand-title">Dokebi Family</span>
          <span class="brand-sub">My Account</span>
        </span>
      </a>
      <div class="topbar-actions">
        <div class="theme-toggle" style="display:flex;align-items:center;gap:8px;">
          <span style="color:var(--muted);font-size:13px;">Dark / Light</span>
          <label class="toggle-switch">
            <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>
    </div>
  </header>

  <main class="account-shell">
    <?php if ($flashMessage): ?>
      <div class="account-container"><div class="notice"><?php echo htmlspecialchars($flashMessage); ?></div></div>
    <?php endif; ?>
    <div class="account-container">
      <div class="account-grid">
        <aside class="account-sidebar">
          <div class="sidebar-heading">
            <h3>My Account</h3>
            <p>Manage your account, orders, and preferences</p>
          </div>
          <div class="account-nav">
            <button class="nav-btn<?php echo $initialPanel === 'profile' ? ' active' : ''; ?>" data-panel="profile">
              <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm-7 8a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
              Edit Profile
            </button>
            <button class="nav-btn<?php echo $initialPanel === 'orders' ? ' active' : ''; ?>" data-panel="orders">
              <svg viewBox="0 0 24 24"><path d="M3 5h18M3 12h18M3 19h18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
              Order History
            </button>
            <button class="nav-btn<?php echo $initialPanel === 'wishlist' ? ' active' : ''; ?>" data-panel="wishlist">
              <svg viewBox="0 0 24 24"><path d="M12 21s-7-4.35-7-10a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 5.65-7 10-7 10z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
              Wishlist
            </button>
            <button class="nav-btn<?php echo $initialPanel === 'saved' ? ' active' : ''; ?>" data-panel="saved">
              <svg viewBox="0 0 24 24"><path d="M6 4h12a2 2 0 0 1 2 2v13l-8-4-8 4V6a2 2 0 0 1 2-2z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
              Saved Categories
            </button>
            <button class="nav-btn<?php echo $initialPanel === 'chat' ? ' active' : ''; ?>" data-panel="chat">
              <svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-1.9 5.4A8.5 8.5 0 0 1 12 21a8.38 8.38 0 0 1-5.4-1.9L3 21l1.9-3.6A8.38 8.38 0 0 1 3 12a8.5 8.5 0 0 1 4.1-7.3A8.38 8.38 0 0 1 12 3a8.5 8.5 0 0 1 8.5 8.5z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
              Live Chat
            </button>
            <button class="nav-btn<?php echo $initialPanel === 'security' ? ' active' : ''; ?>" data-panel="security">
              <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V6l-8-4-8 4v6c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
              Security
            </button>
          </div>
        </aside>

        <section class="account-panels">
          <div id="accountPanel" aria-live="polite">
            <!-- AJAX loads panel here -->
          </div>
        </section>
      </div>
    </div>
  </main>

  <script>
    window.__CSRF__ = "<?php echo htmlspecialchars(csrf_token()); ?>";
    window.__INITIAL_PANEL__ = "<?php echo htmlspecialchars($initialPanel); ?>";
  </script>
  <script src="../js/theme-toggle.js"></script>
  <script src="../js/app.js"></script>
  <script src="assets/account.js"></script>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
