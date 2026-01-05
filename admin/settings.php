<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* ---------------------------------------
   LOAD SETTINGS (SAFE)
--------------------------------------- */
$settings = [];

// Load all rows safely into array
$stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
foreach ($stmt as $row) {
    $settings[$row["setting_key"]] = $row["setting_value"];
}

// Ensure keys exist (avoid undefined array key)
$settings["site_name"]        = $settings["site_name"]        ?? "";
$settings["support_email"]    = $settings["support_email"]    ?? "";
$settings["default_currency"] = $settings["default_currency"] ?? "";
$settings["payment_methods"]  = $settings["payment_methods"]  ?? "";

/* ---------------------------------------
   SAVE SETTINGS
--------------------------------------- */
if (isset($_POST["save_settings"])) {

    $update = $conn->prepare("
        UPDATE settings 
        SET setting_value = :value 
        WHERE setting_key = :key
    ");

    $update->execute([':value' => $_POST["site_name"], ':key' => "site_name"]);
    $update->execute([':value' => $_POST["support_email"], ':key' => "support_email"]);
    $update->execute([':value' => $_POST["default_currency"], ':key' => "default_currency"]);
    $update->execute([':value' => $_POST["payment_methods"], ':key' => "payment_methods"]);

    header("Location: settings.php?msg=saved");
    exit();
}

$pageTitle = "Settings";
$pageSubtitle = "Manage system preferences for Dokebi Tekoku";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Settings | Dokebi Tekoku</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php include "theme.php"; ?>

<style>
/* Settings Card */
.settings-card {
    background: var(--panel);
    padding: 25px;
    border-radius: 14px;
    width: 550px;
    box-shadow: 0 0 15px rgba(0,0,0,0.25);
    transition: 0.3s;
    margin-bottom: 30px;
}

.settings-card label {
    font-weight: 600;
    display: block;
    margin-bottom: 5px;
}

.settings-card input,
.settings-card textarea {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    background: var(--panel-2);
    border: 1px solid var(--border);
    color: var(--text);
    margin-bottom: 15px;
    font-size: 15px;
}

/* Save Button */
.btn-save {
    padding: 12px 22px;
    background: var(--accent-orange);
    color: white;
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    font-size: 16px;
    border: none;
    transition: 0.25s;
}
.btn-save:hover {
    background: var(--accent-purple);
    padding-left: 30px;
}

/* Page Width Fix */
.content {
    max-width: 900px;
}
</style>

</head>

<body>

<div id="alertBox" class="alert-box"></div>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="dashboard.php">Dashboard</a>
    <a href="products_manage.php">Products</a>
    <a href="main_categories_management.php">Main Categories</a>
    <a href="categories_management.php">Categories</a>
    <a href="users_manage.php">Users</a>
    <a href="orders_manage.php">Orders</a>
    <a href="payments_manage.php">Payments</a>
    <a href="reviews_manage.php">Reviews</a>
    <a href="settings.php" class="active-link">Settings</a>
    <a href="../auth/logout.php" style="color:#ff3b3b;">Logout</a>
</div>

<!-- TOPBAR -->
<div class="topbar">
    <div>
        <strong style="font-size:20px;"><?= $pageTitle ?></strong>
        <p style="margin:0; opacity:0.8;"><?= $pageSubtitle ?></p>
    </div>

    <div class="switch-container">
        
        <div class="switch-toggle" onclick="toggleMode()"></div>
    </div>
</div>

<!-- CONTENT -->
<div class="content">

    <div class="settings-card">
        <h2>General Settings</h2>

        <form method="POST">

            <label>Website Name</label>
            <input type="text" name="site_name" 
                   value="<?= htmlspecialchars($settings["site_name"]) ?>" required>

            <label>Support Email</label>
            <input type="email" name="support_email"
                   value="<?= htmlspecialchars($settings["support_email"]) ?>" required>

            <label>Default Currency</label>
            <input type="text" name="default_currency"
                   value="<?= htmlspecialchars($settings["default_currency"]) ?>" required>

            <label>Payment Methods (comma separated)</label>
            <textarea name="payment_methods" rows="3"><?= htmlspecialchars($settings["payment_methods"]) ?></textarea>

            <button type="submit" name="save_settings" class="btn-save">
                 Save Settings
            </button>
        </form>

    </div>

</div>

<?php if (isset($_GET["msg"]) && $_GET["msg"] === "saved"): ?>
<script>showAlert("Settings saved successfully");</script>
<?php endif; ?>

</body>
</html>
