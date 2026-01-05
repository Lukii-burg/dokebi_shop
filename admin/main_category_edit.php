<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: main_categories_management.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM main_categories WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$main = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$main) {
    header("Location: main_categories_management.php");
    exit();
}

$msg = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $name       = trim($_POST['name'] ?? '');
    $slug       = trim($_POST['slug'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $accent     = trim($_POST['accent_color'] ?? '');
    $sortOrder  = (int)($_POST['sort_order'] ?? 0);
    $isActive   = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '' || $slug === '') {
        $error = "Name and slug are required.";
    } else {
        $up = $conn->prepare("
            UPDATE main_categories
            SET name = :name,
                slug = :slug,
                description = :description,
                accent_color = :accent,
                sort_order = :sort_order,
                is_active = :is_active
            WHERE id = :id
        ");
        $up->execute([
            ':name'        => $name,
            ':slug'        => $slug,
            ':description' => $desc !== '' ? $desc : null,
            ':accent'      => $accent !== '' ? $accent : null,
            ':sort_order'  => $sortOrder,
            ':is_active'   => $isActive,
            ':id'          => $id
        ]);
        $msg = "Main category updated.";

        $stmt->execute([':id' => $id]);
        $main = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$pageTitle = "Edit Main Category";
$pageSubtitle = "Update main category details";
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?> | Dokebi Tekoku</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php include "theme.php"; ?>
</head>
<body class="cat-body">
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

<div class="cat-form-wrapper">
    <h1><?= $pageTitle ?></h1>
    <p><?= $pageSubtitle ?></p>

    <?php if ($msg): ?>
        <div class="notice"><?= htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice" style="background:#fee2e2;color:#991b1b;border-color:#fecaca;"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="cat-form-card">
        <h3>Main Category Details</h3>
        <form method="post">
            <input type="hidden" name="action" value="update">

            <label>Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($main['name']); ?>" required>

            <label>Slug</label>
            <input type="text" name="slug" value="<?= htmlspecialchars($main['slug']); ?>" required>

            <label>Description (optional)</label>
            <textarea name="description" rows="3" style="width:100%;"><?= htmlspecialchars($main['description'] ?? ''); ?></textarea>

            <label>Accent Color (optional)</label>
            <input type="text" name="accent_color" value="<?= htmlspecialchars($main['accent_color'] ?? ''); ?>" placeholder="#0ea5e9">

            <label>Sort Order</label>
            <input type="number" name="sort_order" value="<?= (int)($main['sort_order'] ?? 0); ?>">

            <div class="cat-checkbox">
                <input type="checkbox" name="is_active" <?= !empty($main['is_active']) ? 'checked' : ''; ?>>
                <span>Active</span>
            </div>

            <div class="cat-form-actions">
                <a class="cat-btn-back" href="main_categories_management.php">Back</a>
                <button class="cat-btn-create" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
