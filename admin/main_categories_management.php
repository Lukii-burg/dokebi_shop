<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$msg = '';
$error = '';

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name       = trim($_POST['name'] ?? '');
    $slug       = trim($_POST['slug'] ?? '');
    $accent     = trim($_POST['accent_color'] ?? '');
    $sortOrder  = (int)($_POST['sort_order'] ?? 0);
    $isActive   = isset($_POST['is_active']) ? 1 : 0;

    if ($name !== '' && $slug !== '') {
        $stmt = $conn->prepare("
            INSERT INTO main_categories (name, slug, accent_color, sort_order, is_active, created_at)
            VALUES (:name, :slug, :accent, :sort_order, :is_active, NOW())
        ");
        $stmt->execute([
            ':name'       => $name,
            ':slug'       => $slug,
            ':accent'     => $accent ?: null,
            ':sort_order' => $sortOrder,
            ':is_active'  => $isActive
        ]);
        $msg = 'Main category created.';
    } else {
        $msg = 'Name and slug are required.';
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $deleteId = (int)($_POST['id'] ?? 0);
    if ($deleteId > 0) {
        try {
            $del = $conn->prepare("DELETE FROM main_categories WHERE id = :id LIMIT 1");
            $del->execute([':id' => $deleteId]);
            $msg = 'Main category deleted.';
        } catch (Exception $e) {
            $error = 'Unable to delete this main category (check linked data).';
        }
    }
}

// Toggle active
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->prepare("UPDATE main_categories SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    header("Location: main_categories_management.php");
    exit;
}

$rows = $conn->query("
    SELECT mc.*, COUNT(DISTINCT c.id) AS sub_count
    FROM main_categories mc
    LEFT JOIN categories c ON c.main_category_id = mc.id
    GROUP BY mc.id, mc.name, mc.slug, mc.accent_color, mc.sort_order, mc.is_active, mc.created_at
    ORDER BY mc.sort_order, mc.name
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Main Categories";
$pageSubtitle = "Create and manage main categories";
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
            <div class="notice" style="background:#fee2e2;color:#991b1b;border-color:#fecaca;"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="cat-topbar">
          <div>
            <h2>Main Categories</h2>
            <p class="muted tiny">Create and manage main categories</p>
          </div>
          <a class="btn" href="main_categories_add.php" style="background:#f97316;color:#fff;">Add New Main Category</a>
        </div>

        <div class="card cat-table-card cat-table-only">
            <h3>All Main Categories</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Accent</th>
                        <th>Sort</th>
                        <th>Subs</th>
                        <th>Status</th>
                        <th>Action</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= (int)$row['id']; ?></td>
                            <td><?= htmlspecialchars($row['name']); ?></td>
                            <td><?= htmlspecialchars($row['slug']); ?></td>
                            <td><span class="badge" style="background: <?= htmlspecialchars($row['accent_color'] ?? '#0ea5e9'); ?>;">&nbsp;</span></td>
                            <td><?= (int)$row['sort_order']; ?></td>
                            <td><?= (int)$row['sub_count']; ?></td>
                            <td><?= $row['is_active'] ? 'Active' : 'Inactive'; ?></td>
                            <td>
                                <a class="btn <?= $row['is_active'] ? 'primary' : '' ?>" href="?toggle=<?= (int)$row['id']; ?>" style="min-width:96px; text-align:center;">
                                    <?= $row['is_active'] ? 'Active' : 'Inactive'; ?>
                                </a>
                            </td>
                            <td style="display:flex; gap:6px; flex-wrap:wrap;">
                                <a class="btn" href="main_category_edit.php?id=<?= (int)$row['id']; ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this main category?');" style="margin:0;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$row['id']; ?>">
                                    <button class="btn" type="submit" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
