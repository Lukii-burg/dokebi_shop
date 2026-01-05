<?php
require_once __DIR__ . '/../db/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$error = '';
$debugOtp = '';

// Ensure reset table exists (demo-safe)
$conn->exec("
    CREATE TABLE IF NOT EXISTS password_resets (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL,
        otp VARCHAR(12) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT current_timestamp()
    )
");
$conn->exec("DELETE FROM password_resets WHERE expires_at <= NOW()");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'request') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
      $error = 'Email is required.';
    } else {
      $stmt = $conn->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
      $stmt->execute([':email' => $email]);
      if (!$stmt->fetchColumn()) {
        $error = 'No account found for that email.';
      } else {
        $otp = generate_otp();
        $ins = $conn->prepare('INSERT INTO password_resets (email, otp, expires_at) VALUES (:email, :otp, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
        $ins->execute([':email'=>$email, ':otp'=>$otp]);

        // Try to send email via PHPMailer; if mail isn't configured, show OTP in flash for demo.
        $html = "<p>Your Dokebi Family password reset code is <strong>{$otp}</strong>.</p><p>It's valid for 10 minutes.</p>";
        $sentResult = send_app_mail($email, 'Dokebi Family password reset code', $html, "Your OTP is: {$otp} (valid 10 minutes)");
        $_SESSION['flash'] = $sentResult['sent']
          ? 'We sent a 6-digit code to your email. Enter it below to reset.'
          : 'Email sending is not configured here. Use this code to reset: ' . $otp;
        header('Location: recover.php?step=reset&email=' . urlencode($email));
        exit;
      }
    }
  }

  if ($action === 'reset') {
    $email     = trim($_POST['email'] ?? '');
    $otp       = trim($_POST['otp'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $password2 = trim($_POST['password2'] ?? '');

    if ($otp === '') {
      $error = 'OTP code is required.';
    } elseif ($password === '' || $password2 === '') {
      $error = 'Password is required.';
    } elseif ($password !== $password2) {
      $error = 'Passwords do not match.';
    } else {
      $check = $conn->prepare('SELECT id FROM password_resets WHERE email = :email AND otp = :otp AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
      $check->execute([':email'=>$email, ':otp'=>$otp]);
      $token = $check->fetch(PDO::FETCH_ASSOC);
      if (!$token) {
        $error = 'Invalid or expired code.';
      } else {
        $stmt = $conn->prepare('UPDATE users SET password = :password WHERE email = :email');
        $stmt->execute([':password' => hash_password_value($password), ':email' => $email]);
        if ($stmt->rowCount()) {
          $conn->prepare('DELETE FROM password_resets WHERE email = :email')->execute([':email'=>$email]);
          $_SESSION['flash'] = 'Password updated. Please sign in.';
          header('Location: login.php');
          exit;
        } else {
          $error = 'Unable to update password for that email.';
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Recover Password - Dokebi Family</title>
  <link rel="stylesheet" href="../maincss/style.css">
  <link rel="stylesheet" href="../maincss/dark-mode.css">
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="back-icon" href="<?php echo url_for('main/index.php'); ?>" aria-label="Home">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
      <a class="logo" href="<?php echo url_for('main/index.php'); ?>"><img src="../logo/original.png" alt="Dokebi Family" class="logo-img"><span class="sitename">Dokebi Family</span></a>
      <div class="header-left-links">
        <a href="<?php echo url_for('main/index.php'); ?>">Home</a>
        <a href="register.php">Create account</a>
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
        <h2>Recover Password</h2>
        <?php if(!empty($_SESSION['flash'])): ?><div class="notice"><?php echo $_SESSION['flash']; unset($_SESSION['flash']); ?></div><?php endif; ?>
        <?php if($error): ?><div class="notice error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if (!empty($_GET['step']) && $_GET['step'] === 'reset' && !empty($_GET['email'])): ?>
          <?php $email = $_GET['email']; ?>
          <form action="recover.php" method="post">
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <label class="muted">6-digit OTP code</label>
            <input name="otp" type="text" maxlength="6" required>
            <label class="muted">New password</label>
            <input name="password" type="password" required>
            <label class="muted">Confirm new password</label>
            <input name="password2" type="password" required>
            <div class="form-actions">
              <button class="btn primary">Set New Password</button>
            </div>
          </form>
          <p class="muted-center"><a class="btn" href="login.php">Return to sign in</a></p>
        <?php else: ?>
          <form action="recover.php" method="post">
            <input type="hidden" name="action" value="request">
            <label class="muted">Enter your email address</label>
            <input name="email" type="email" required>
            <div class="form-actions">
              <button class="btn primary">Send OTP Code</button>
            </div>
          </form>
          <p class="muted-center"><a class="btn" href="login.php">Return to sign in</a></p>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <script src="../js/theme-toggle.js"></script>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
