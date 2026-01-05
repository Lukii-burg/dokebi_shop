<?php
require_once __DIR__ . '/../../db/functions.php';
require_login();
?>

<div class="security-card">
  <div class="panel-head">
    <div>
      <h3>Security Settings</h3>
      <p>Update your password and verification</p>
    </div>
  </div>

  <form id="securityForm" class="input-grid" style="margin-top:12px; gap:10px;">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <div class="input-block">
      <label for="current_password">Current Password</label>
      <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
    </div>
    <div class="input-block">
      <label for="new_password">New Password</label>
      <input id="new_password" name="new_password" type="password" autocomplete="new-password" required>
    </div>
    <div class="input-block">
      <label for="confirm_password">Confirm New Password</label>
      <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
    </div>
    <div class="form-actions">
      <button class="btn-primary" type="submit">Update Password</button>
    </div>
  </form>

  <div style="margin-top:16px;">
    <div style="font-weight:700; margin-bottom:8px;">Active Session</div>
    <div class="session">
      <div>
        <div style="font-weight:700;">Current device</div>
        <div class="order-meta"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? ''); ?> &middot; Signed in</div>
      </div>
      <span class="pill pill--success">Active</span>
    </div>
    <form method="post" action="../auth/logout.php" style="margin-top:10px;">
      <button class="btn-ghost" type="submit" style="color:var(--danger); border-color: #fecdd3;">Logout</button>
    </form>
  </div>
</div>
