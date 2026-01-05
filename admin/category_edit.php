<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$uploadDir = __DIR__ . '/../uploads/categories';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$mainCategories = [];
if ($conn->query("SHOW TABLES LIKE 'main_categories'")->fetch()) {
    $mainCategories = $conn->query("
        SELECT id, name
        FROM main_categories
        WHERE is_active = 1
        ORDER BY sort_order, name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: categories_management.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT c.*, mc.name AS main_name
    FROM categories c
    LEFT JOIN main_categories mc ON mc.id = c.main_category_id
    WHERE c.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$category) {
    header("Location: categories_management.php");
    exit();
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $name       = trim($_POST['category_name'] ?? '');
    $slug       = trim($_POST['slug'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $mainId     = isset($_POST['main_category_id']) ? (int)$_POST['main_category_id'] : null;
    $isActive   = isset($_POST['is_active']) ? 1 : 0;
    $cardImage  = $category['card_image'] ?? null;

    if ($name === '' || $slug === '') {
        $error = "Name and slug are required.";
    }

    $removeImage = isset($_POST['remove_image']);

    // Handle upload if provided
    if ($error === '' && !empty($_FILES['card_image']['name']) && is_uploaded_file($_FILES['card_image']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['card_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            $error = "Image must be JPG, JPEG, PNG, or WEBP.";
        } else {
            $base = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($_FILES['card_image']['name'], PATHINFO_FILENAME));
            $base = trim($base, '-') ?: 'category';
            $filename = $base . '-' . uniqid() . '.' . $ext;
            $target = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
            if (move_uploaded_file($_FILES['card_image']['tmp_name'], $target)) {
                $cardImage = $filename;
            } else {
                $error = "Failed to upload image. Please try again.";
            }
        }
    }

    if ($removeImage) {
        $cardImage = null;
    }

    if ($error === '') {
        $update = $conn->prepare("
            UPDATE categories
            SET category_name = :name,
                slug = :slug,
                description = :desc,
                main_category_id = :main,
                is_active = :active,
                card_image = :card_image
            WHERE id = :id
        ");
        $update->execute([
            ':name'       => $name,
            ':slug'       => $slug,
            ':desc'       => $desc,
            ':main'       => $mainId ?: null,
            ':active'     => $isActive,
            ':card_image' => $cardImage,
            ':id'         => $id
        ]);

        $msg = "Category updated.";

        // Refresh current data
        $stmt->execute([':id' => $id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$pageTitle = "Edit Category";
$pageSubtitle = "Update category details and card image";
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
    <a href="main_categories_management.php">Main Categories</a>
    <a href="categories_management.php" class="active-link">Categories</a>
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
        <div class="notice"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice" style="background:#fee2e2; color:#991b1b; border-color:#fecaca;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="cat-form-card">
        <h3>Category Details</h3>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">

            <label>Name</label>
            <input type="text" name="category_name" value="<?= htmlspecialchars($category['category_name']); ?>" required>

            <label>Slug</label>
            <input type="text" name="slug" value="<?= htmlspecialchars($category['slug']); ?>" required>

            <label>Description</label>
            <input type="text" name="description" value="<?= htmlspecialchars($category['description'] ?? ''); ?>">

            <label>Card Image</label>
            <?php if (!empty($category['card_image'])): ?>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <img src="../uploads/categories/<?= htmlspecialchars($category['card_image']); ?>" alt="Card image" style="height:60px; border-radius:8px; border:1px solid #e5e7eb;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:0.9rem;">
                        <input type="checkbox" name="remove_image"> Remove image
                    </label>
                </div>
            <?php endif; ?>
            <input type="file" name="card_image" accept=".jpg,.jpeg,.png,.webp">
            <p class="muted tiny" style="margin-top:6px;">Shown on shop tiles and top-up hero. Recommended 16:9 (e.g., 1200x675).</p>

            <label>Main Category</label>
            <select name="main_category_id">
                <option value="">None</option>
                <?php foreach ($mainCategories as $mc): ?>
                    <option value="<?= $mc['id'] ?>" <?= ($category['main_category_id'] ?? null) == $mc['id'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($mc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="cat-checkbox">
                <input type="checkbox" name="is_active" <?= $category['is_active'] ? 'checked' : ''; ?>>
                <span>Active</span>
            </div>

            <div class="cat-form-actions">
                <a class="cat-btn-back" href="categories_management.php">Back</a>
                <button class="cat-btn-create" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
