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

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {

    $name       = trim($_POST['category_name'] ?? '');
    $slug       = trim($_POST['slug'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $mainId     = isset($_POST['main_category_id']) ? (int)$_POST['main_category_id'] : null;
    $isActive   = isset($_POST['is_active']) ? 1 : 0;
    $cardImage  = null;

    $error = '';

    // Optional card image upload
    if (!empty($_FILES['card_image']['name']) && is_uploaded_file($_FILES['card_image']['tmp_name'])) {
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

    if ($name === '' || $slug === '') {
        $error = "Name and slug are required.";
    }

    if ($error === '') {
        $stmt = $conn->prepare("
            INSERT INTO categories (category_name, slug, description, main_category_id, is_active, card_image, created_at)
            VALUES (:name, :slug, :desc, :main, :active, :card_image, NOW())
        ");

        $stmt->execute([
            ':name'       => $name,
            ':slug'       => $slug,
            ':desc'       => $desc,
            ':main'       => $mainId ?: null,
            ':active'     => $isActive,
            ':card_image' => $cardImage
        ]);

        $msg = "Category created successfully.";
    } else {
        $msg = $error;
    }
}

$pageTitle = "Add Category";
$pageSubtitle = "Create a new category and link to a main category";
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
    <a href="main_categories_management.php">Main Categories</a>
    <a href="categories_management.php" class="active-link">Categories</a>
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

        <h3>Category Details</h3>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">

            <label>Name</label>
            <input type="text" name="category_name" required>

            <label>Slug</label>
            <input type="text" name="slug" required>

            <label>Description</label>
            <input type="text" name="description">

            <label>Card Image (optional)</label>
            <input type="file" name="card_image" accept=".jpg,.jpeg,.png,.webp">
            <p class="muted tiny">Shown on shop tiles and top-up hero. Recommended 16:9 (e.g., 1200x675).</p>

            <label>Main Category</label>
            <select name="main_category_id">
                <option value="">None</option>
                <?php foreach ($mainCategories as $mc): ?>
                    <option value="<?= $mc['id'] ?>">
                        <?= htmlspecialchars($mc['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="cat-checkbox">
                <input type="checkbox" name="is_active" checked>
                <span>Active</span>
            </div>

            <div class="cat-form-actions">
                <a class="cat-btn-back" href="categories_management.php">Back</a>
                <button class="cat-btn-create" type="submit">Create</button>
            </div>

        </form>
    </div>

</div>

</body>
</html>
