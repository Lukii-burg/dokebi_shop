<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {

    $name      = trim($_POST['name'] ?? '');
    $slug      = trim($_POST['slug'] ?? '');
    $accent    = trim($_POST['accent_color'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive  = isset($_POST['is_active']) ? 1 : 0;

    if ($name !== '' && $slug !== '') {

        $stmt = $conn->prepare("
            INSERT INTO main_categories (name, slug, accent_color, sort_order, is_active, created_at)
            VALUES (:name, :slug, :accent, :sort_order, :active, NOW())
        ");

        $stmt->execute([
            ':name'       => $name,
            ':slug'       => $slug,
            ':accent'     => $accent ?: null,
            ':sort_order' => $sortOrder,
            ':active'     => $isActive
        ]);

        $msg = "Main category created successfully.";

    } else {
        $msg = "Name and slug are required.";
    }
}

$pageTitle = "Add Main Category";
$pageSubtitle = "Create a new main category";
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?> | Dokebi Tekoku</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php include "theme.php"; ?>
</head>

<body class="cat-body">

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="dashboard.php">Dashboard</a>
    <a href="products_manage.php">Products</a>
    <a href="main_categories_management.php" class="active-link">Main Categories</a>
    <a href="categories_management.php">Categories</a>
    <a href="users_manage.php">Users</a>
    <a href="orders_manage.php">Orders</a>
    <a href="payments_manage.php">Payments</a>
    <a href="reviews_manage.php">Reviews</a>
    <a href="settings.php">Settings</a>
    <a href="../auth/logout.php" style="color:#f87171;">Logout</a>
</div>

<!-- MAIN CONTENT -->
<div class="cat-form-wrapper">

    <h1><?= $pageTitle ?></h1>
    <p><?= $pageSubtitle ?></p>

    <?php if ($msg): ?>
        <div class="notice"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="cat-form-card">

        <h3>Main Category Details</h3>

        <form method="post">
            <input type="hidden" name="action" value="create">

            <label>Name</label>
            <input type="text" name="name" required>

            <label>Slug</label>
            <input type="text" name="slug" required>

            <label>Accent Color (optional)</label>
            <input type="text" name="accent_color" placeholder="#0ea5e9">

            <label>Sort Order</label>
            <input type="number" name="sort_order" value="0">

            <div class="cat-checkbox">
                <input type="checkbox" name="is_active" checked>
                <span>Active</span>
            </div>

            <div class="cat-form-actions">
                <a class="cat-btn-back" href="main_categories_management.php">Back</a>
                <button class="cat-btn-create" type="submit">Create</button>
            </div>

        </form>

    </div>

</div>

</body>
</html>
