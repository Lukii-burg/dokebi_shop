<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$msg = '';
$error = '';

$mainCategories = [];
if ($conn->query("SHOW TABLES LIKE 'main_categories'")->fetch()) {
    $mainCategories = $conn->query("SELECT id, name FROM main_categories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
}

// Create category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name       = trim($_POST['category_name'] ?? '');
    $slug       = trim($_POST['slug'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $mainId     = isset($_POST['main_category_id']) ? (int)$_POST['main_category_id'] : null;
    $isActive   = isset($_POST['is_active']) ? 1 : 0;

    if ($name !== '' && $slug !== '') {
        $stmt = $conn->prepare("
            INSERT INTO categories (category_name, slug, description, main_category_id, is_active, created_at)
            VALUES (:name, :slug, :desc, :main, :active, NOW())
        ");
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':desc' => $desc,
            ':main' => $mainId ?: null,
            ':active' => $isActive
        ]);
        $msg = 'Category created.';
    } else {
        $msg = 'Name and slug are required.';
    }
}

// Delete category (prevent delete if products exist)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $deleteId = (int)($_POST['id'] ?? 0);
    if ($deleteId > 0) {
        $prodCount = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $prodCount->execute([$deleteId]);
        if ((int)$prodCount->fetchColumn() > 0) {
            $error = 'Cannot delete category with existing products.';
        } else {
            try {
                $del = $conn->prepare("DELETE FROM categories WHERE id = :id LIMIT 1");
                $del->execute([':id' => $deleteId]);
                $msg = 'Category deleted.';
            } catch (Exception $e) {
                $error = 'Unable to delete this category.';
            }
        }
    }
}

// Toggle active
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->prepare("UPDATE categories SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    header("Location: categories_management.php");
    exit;
}

$rows = $conn->query("
    SELECT c.*, mc.name AS main_name
    FROM categories c
    LEFT JOIN main_categories mc ON mc.id = c.main_category_id
    ORDER BY c.category_name
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Categories";
$pageSubtitle = "Create and manage sub-categories";
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

<div class="main cat-main">
    <div class="cat-container">
        <div class="header cat-header">
            <div>
                <h1><?= $pageTitle ?></h1>
                <p class="muted"><?= $pageSubtitle ?></p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="notice"><?= htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice" style="background:#fee2e2; color:#991b1b; border-color:#fecaca;"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="cat-topbar">
          <div>
            <h2>Categories</h2>
            <p class="muted tiny">Manage and link categories to main categories</p>
          </div>
          <a class="btn" href="categories_add.php" style="background:#f97316;color:#fff;">Add New Category</a>
        </div>

        <div class="card cat-table-card cat-table-only">
              <h3>All Categories</h3>
              <table class="table">
                  <thead>
                      <tr>
                          <th>ID</th>
                          <th>Name</th>
                          <th>Slug</th>
                          <th>Main</th>
                          <th>Status</th>
                          <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php foreach ($rows as $row): ?>
                          <tr>
                              <td><?= (int)$row['id']; ?></td>
                              <td><?= htmlspecialchars($row['category_name']); ?></td>
                              <td><?= htmlspecialchars($row['slug']); ?></td>
                              <td><?= htmlspecialchars($row['main_name'] ?? '-'); ?></td>
                              <td><?= $row['is_active'] ? 'Active' : 'Inactive'; ?></td>
                              <td style="min-width:160px;">
                                  <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                      <a class="btn" href="category_edit.php?id=<?= (int)$row['id']; ?>" style="min-width:72px; text-align:center;">Edit</a>
                                      <a class="btn <?= $row['is_active'] ? 'primary' : '' ?>" href="?toggle=<?= (int)$row['id']; ?>" style="min-width:96px; text-align:center;">
                                          <?= $row['is_active'] ? 'Active' : 'Inactive'; ?>
                                      </a>
                                      <form method="post" onsubmit="return confirm('Delete this category?');" style="margin:0;">
                                          <input type="hidden" name="action" value="delete">
                                          <input type="hidden" name="id" value="<?= (int)$row['id']; ?>">
                                          <button class="btn" type="submit" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;min-width:72px;">Delete</button>
                                      </form>
                                  </div>
                              </td>
                          </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
    </div>
</div>
</div>
</div>
</body>
</html>
