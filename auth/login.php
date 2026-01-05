<?php
require_once __DIR__ . '/../db/functions.php';

if (is_logged_in()) {
  header('Location: ../user/account.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $login_input = trim($_POST['email'] ?? '');
  $password    = trim($_POST['password'] ?? '');

  if ($login_input === '' || $password === '') {
    $error = 'Email/username and password are required.';
  } else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :login OR username = :login LIMIT 1");
    $stmt->execute([':login' => $login_input]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_matches($password, $user['password'])) {
      if (isset($user['status']) && strtolower($user['status']) === 'blocked') {
        $error = 'Your account has been blocked by the administrator.';
      } else {
        rehash_password_if_needed($conn, (int)$user['id'], $password, $user['password']);
        set_user_session($user);
        $_SESSION['flash'] = 'Signed in successfully.';
        if ($user['role'] === 'admin') {
          header('Location: ../admin/dashboard.php');
        } else {
          header('Location: ../main/index.php');
        }
        exit;
      }
    } else {
      $error = 'Invalid email/username or password.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign In - Dokebi Family</title>
  <link rel="stylesheet" href="../maincss/style.css">
  <link rel="stylesheet" href="../maincss/dark-mode.css">
</head>
<body>
  <div id="pageLoader" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:9999;background:rgba(15,23,42,0.55);backdrop-filter:blur(2px);">
    <div style="width:82px;height:82px;border-radius:999px;border:6px solid rgba(255,255,255,0.35);border-top-color:#60a5fa;animation:spin 0.9s linear infinite;"></div>
  </div>
  <style>
    @keyframes spin { to { transform:rotate(360deg); } }
  </style>
  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="<?php echo url_for('main/index.php'); ?>"><img src="../logo/original.png" alt="Dokebi Family" class="logo-img"><span class="sitename">Dokebi Family</span></a>
      <div class="header-left-links">
        <a href="<?php echo url_for('main/index.php'); ?>">Home</a>
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
        <h2>Sign In</h2>
        <?php if(!empty($_SESSION['flash'])): ?><div class="notice"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div><?php endif; ?>
        <?php if($error): ?><div class="notice error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form id="loginForm" action="login.php" method="post">
          <label class="muted">Email or Username</label>
          <input id="loginEmail" name="email" type="text" required>
          <label class="muted">Password</label>
          <div class="password-field">
            <input id="loginPass" name="password" type="password" required>
            <button type="button" class="password-toggle" data-target="loginPass" aria-label="Show password" aria-pressed="false">
              <svg class="eye eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
              <svg class="eye eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M10.58 10.58A3 3 0 0013.4 13.4M9.88 4.14A9.55 9.55 0 0112 4c6.5 0 10 6 10 6a18.6 18.6 0 01-3.06 3.63m-3.2 2.08A10.4 10.4 0 0112 18c-6.5 0-10-6-10-6a19.8 19.8 0 013.47-3.93" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
          </div>
          <label class="remember-row">
            <input type="checkbox" id="rememberLogin">
            <span>Save my login</span>
          </label>
          <div class="form-actions">
            <button class="btn primary">Sign In</button>
            <a class="btn primary" href="recover.php">Forgot password?</a>
          </div>
          <div class="below-action">
            <a class="btn" href="register.php">Create account</a>
          </div>
        </form>
      </div>
    </section>
  </main>
  <script src="../js/theme-toggle.js"></script>
  <script src="../js/password-toggle.js"></script>
  <script src="../js/app.js"></script>
  <script>
    (function(){
      const form = document.getElementById('loginForm');
      const email = document.getElementById('loginEmail');
      const pass = document.getElementById('loginPass');
      const remember = document.getElementById('rememberLogin');
      if(!form || !email || !pass || !remember) return;

      // Load stored values
      try{
        const saved = localStorage.getItem('dokebi_login');
        if(saved){
          const data = JSON.parse(saved);
          email.value = data.email || '';
          pass.value = data.password || '';
          remember.checked = true;
        }
      }catch(e){}

      form.addEventListener('submit', () => {
        const loader = document.getElementById('pageLoader');
        if (loader) loader.style.display = 'flex';
        if(remember.checked){
          const payload = { email: email.value || '', password: pass.value || '' };
          localStorage.setItem('dokebi_login', JSON.stringify(payload));
        } else {
          localStorage.removeItem('dokebi_login');
        }
      });
    })();
  </script>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
