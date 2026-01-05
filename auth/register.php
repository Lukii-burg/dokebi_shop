<?php
require_once __DIR__ . '/../db/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (is_logged_in()) {
  header('Location: ../user/account.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name     = trim($_POST['name'] ?? '');
  $username = trim($_POST['username'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $password = trim($_POST['password'] ?? '');
  $termsAccepted = isset($_POST['terms']) && $_POST['terms'] === '1';

  if ($name === '' || $username === '' || $email === '' || $password === '') {
    $error = 'All fields are required.';
  } elseif (!$termsAccepted) {
    $error = 'Please accept the Terms & Conditions to continue.';
  } else {
    $exists = $conn->prepare('SELECT COUNT(*) FROM users WHERE email = :email OR username = :username');
    $exists->execute([':email' => $email, ':username' => $username]);

    if ($exists->fetchColumn() > 0) {
      $error = 'Email or username already exists.';
    } else {
      $stmt = $conn->prepare("
        INSERT INTO users (name, username, email, password, role)
        VALUES (:n, :u, :e, :p, :r)
      ");
      $stmt->execute([
        ':n' => $name,
        ':u' => $username,
        ':e' => $email,
        ':p' => hash_password_value($password),
        ':r' => ROLE_USER
      ]);
      $newUserId = (int)$conn->lastInsertId();
      if ($newUserId > 0 && ensure_welcome_promo_table($conn)) {
        try {
          $promo = welcome_promo_defaults();
          $seed = $conn->prepare("INSERT IGNORE INTO user_promotions (user_id, promo_code, discount_percent) VALUES (:uid, :code, :disc)");
          $seed->execute([':uid' => $newUserId, ':code' => $promo['code'], ':disc' => $promo['discount']]);
        } catch (Exception $e) {
        
        }
      }
      $welcomeBody = "
        <p>Hi " . htmlspecialchars($name) . ",</p>
        <p>Your account has been created on <strong>Dokebi Family</strong>.</p>
        <p>You can now sign in and shop: <a href='" . url_for('auth/login.php') . "'>Login</a></p>
      ";
      send_app_mail($email, 'Account created on Dokebi Family', $welcomeBody, strip_tags($welcomeBody));

      $_SESSION['flash'] = 'Account created. Please sign in.';
      header('Location: login.php');
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Register - Dokebi Family</title>
  <link rel="stylesheet" href="../maincss/style.css">
  <link rel="stylesheet" href="../maincss/dark-mode.css">
</head>
<body>
  <div id="pageLoader" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:9999;background:rgba(15,23,42,0.55);backdrop-filter:blur(2px);">
    <div style="width:82px;height:82px;border-radius:999px;border:6px solid rgba(255,255,255,0.35);border-top-color:#60a5fa;animation:spin 0.9s linear infinite;"></div>
  </div>
  <style>@keyframes spin { to { transform:rotate(360deg); } }</style>
  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="../main/index.php"><img src="../logo/original.png" alt="Dokebi Family" class="logo-img"><span class="sitename">Dokebi Family</span></a>
      <div class="header-left-links">
        <a href="../main/index.php">Home</a>
        <div class="theme-toggle">
          <span>Dark / Light</span>
          <label class="toggle-switch">
            <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>
    </div>
  </header>
  <main>
    <section class="container">
      <div class="form">
        <h2>Create Account</h2>
        <?php if(!empty($_SESSION['flash'])): ?><div class="notice"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div><?php endif; ?>
        <?php if($error): ?><div class="notice error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form id="registerForm" action="register.php" method="post">
          <label class="muted">Full name</label>
          <input id="regName" name="name" type="text" required>
          <label class="muted">Username</label>
          <input id="regUsername" name="username" type="text" required>
          <label class="muted">Email</label>
          <input id="regEmail" name="email" type="email" required>
          <label class="muted">Password</label>
          <div class="password-field">
            <input id="regPass" name="password" type="password" required>
            <button type="button" class="password-toggle" data-target="regPass" aria-label="Show password" aria-pressed="false">
              <svg class="eye eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
              <svg class="eye eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M10.58 10.58A3 3 0 0013.4 13.4M9.88 4.14A9.55 9.55 0 0112 4c6.5 0 10 6 10 6a18.6 18.6 0 01-3.06 3.63m-3.2 2.08A10.4 10.4 0 0112 18c-6.5 0-10-6-10-6a19.8 19.8 0 013.47-3.93" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="terms-inline">
            <label class="terms-checkbox">
              <input id="regTerms" name="terms" type="checkbox" value="1" required <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
              <span>I accept the Terms &amp; Conditions</span>
            </label>
            <a class="terms-link" href="../main/terms.php" target="_blank" rel="noopener">View</a>
          </div>
          <div style="display:flex;gap:0.5rem;margin-top:0.5rem;">
            <button class="btn primary">Create</button>
            <a class="btn" href="login.php">Have an account?</a>
          </div>
        </form>
        <p class="muted-center">By creating an account you must agree to our Terms &amp; Conditions.</p>
      </div>
    </section>
  </main>
  <script src="../js/theme-toggle.js"></script>
  <script src="../js/password-toggle.js"></script>
  <script src="../js/app.js"></script>
  <script>
    (function(){
      const form = document.getElementById('registerForm');
      if(!form) return;
      form.addEventListener('submit', () => {
        const loader = document.getElementById('pageLoader');
        if (loader) loader.style.display = 'flex';
      });
    })();
  </script>
  <?php include  '../partials/footer.php'; ?>
</body>
</html>
