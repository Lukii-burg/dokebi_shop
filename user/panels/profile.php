<?php
require_once __DIR__ . '/../../db/functions.php';
require_login();

$conn = db();
$userId = current_user_id();

$stmt = $conn->prepare("SELECT id, name, email, phone, address, profile_image FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$avatar = $user['profile_image'] ?: 'default_user.png';
$avatarUrl = "../uploads/users/" . $avatar;
$welcomePromo = get_welcome_promo($conn, $userId);
?>

<div class="panel-card">
  <div class="panel-head">
    <div>
      <h3>Profile Information</h3>
      <p>Update your personal details and how people contact you</p>
    </div>
  </div>

  <form id="profileForm" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <div class="profile-top">
      <div class="profile-avatar">
        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Profile photo">
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;">
        <label class="btn-primary" style="text-align:center; cursor:pointer;">
          Change Photo
          <input type="file" name="profile_image" accept="image/*" style="display:none;">
        </label>
        <span style="color:var(--muted);font-size:13px;">JPG, GIF, PNG, WEBP. Max size 2MB</span>
      </div>
    </div>

    <div class="input-grid">
      <div class="input-block">
        <label for="name">Full Name</label>
        <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
      </div>
      <div class="input-block">
        <label for="email">Email Address</label>
        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
      </div>
      <div class="input-block">
        <label for="phone">Phone Number</label>
        <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+95...">
      </div>
      <div class="input-block">
        <label for="address">Address</label>
        <input id="address" name="address" type="text" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" placeholder="City, Country">
      </div>
    </div>

    <div class="form-actions">
      <button class="btn-primary" type="submit">Save Changes</button>
      <button class="btn-secondary" type="reset">Cancel</button>
    </div>
  </form>

  <?php if (!empty($welcomePromo)): ?>
    <div class="promo-banner">
      Welcome promo: <strong><?php echo htmlspecialchars($welcomePromo['code']); ?></strong> &middot; <?php echo (int)$welcomePromo['discount']; ?>% off your first order
    </div>
  <?php endif; ?>
</div>
