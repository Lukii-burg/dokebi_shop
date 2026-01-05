<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Filters
$search   = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$catSlug  = trim($_GET['category'] ?? '');

// Pagination basics
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 10;
$offset   = ($page - 1) * $perPage;

/* DELETE PRODUCT */
if (isset($_GET["delete"])) {
    $id = (int) $_GET["delete"];

    $stmt = $conn->prepare("SELECT product_image FROM products WHERE id=?");
    $stmt->execute([$id]);
    $img = $stmt->fetchColumn();

    try {
        $conn->beginTransaction();
        // Remove dependent rows to satisfy FK constraints
        $conn->prepare("DELETE FROM reviews WHERE product_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM cart_items WHERE product_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM wishlists WHERE product_id=?")->execute([$id]);
        $conn->prepare("DELETE FROM order_items WHERE product_id=?")->execute([$id]);
        // Digital delivery tables (optional)
        if ($conn->query("SHOW TABLES LIKE 'giftcard_codes'")->fetch()) {
            $conn->prepare("DELETE FROM giftcard_codes WHERE product_id=?")->execute([$id]);
        }
        if ($conn->query("SHOW TABLES LIKE 'premium_accounts_pool'")->fetch()) {
            $conn->prepare("DELETE FROM premium_accounts_pool WHERE product_id=?")->execute([$id]);
        }
        $conn->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        $conn->commit();
    } catch (PDOException $e) {
        $conn->rollBack();
        header("Location: products_manage.php?msg=delete_failed");
        exit();
    }

    if ($img && $img !== "default_product.png") {
        @unlink("../uploads/products/$img");
    }

    header("Location: products_manage.php?msg=deleted");
    exit();
}

/* FETCH PRODUCTS (with search + status filter + pagination) */
$where  = [];
$params = [];

if ($search !== '') {
    $where[] = "(p.product_name LIKE :q OR c.category_name LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($status !== '' && in_array($status, ['active', 'inactive'], true)) {
    $where[] = "p.status = :status";
    $params[':status'] = $status;
}
if ($catSlug !== '') {
    $where[] = "c.slug = :cslug";
    $params[':cslug'] = $catSlug;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Total count for pagination
$countSql = "
    SELECT COUNT(*)
    FROM products p
    JOIN categories c ON p.category_id = c.id
    $whereSql
";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "
    SELECT p.*, c.category_name, c.slug AS category_slug
    FROM products p
    JOIN categories c ON p.category_id = c.id
    $whereSql
    ORDER BY c.category_name, p.product_name
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Products Management";
$pageSubtitle = "Manage all products in Dokebi Tekoku";
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?> | Dokebi Tekoku</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php include "theme.php"; ?>
</head>

<body>

<div id="alertBox" class="alert-box"></div>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="dashboard.php">Dashboard</a>
    <a href="products_manage.php" class="active-link">Products</a>
    <a href="main_categories_management.php">Main Categories</a>
    <a href="categories_management.php">Categories</a>
    <a href="users_manage.php">Users</a>
    <a href="orders_manage.php">Orders</a>
    <a href="payments_manage.php">Payments</a>
    <a href="reviews_manage.php">Reviews</a>
    <a href="settings.php">Settings</a>
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

    <a href="product_add.php" class="btn-orange" style="margin-bottom:20px; display:inline-block;">
        Add New Product
    </a>

    <?php
        $catOptions = $conn->query("SELECT slug, category_name FROM categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <form method="get" class="filter-bar" style="gap:0.5rem;flex-wrap:wrap;">
        <input class="filter-input" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search product or category...">
        <select class="filter-select" name="category">
            <option value="">All Categories</option>
            <?php foreach($catOptions as $c): ?>
              <option value="<?= htmlspecialchars($c['slug']); ?>" <?= $catSlug===$c['slug']?'selected':''; ?>>
                <?= htmlspecialchars($c['category_name']); ?>
              </option>
            <?php endforeach; ?>
        </select>
        <select class="filter-select" name="status">
            <option value="">All Status</option>
            <option value="active"   <?= $status==='active'?'selected':''; ?>>Active</option>
            <option value="inactive" <?= $status==='inactive'?'selected':''; ?>>Inactive</option>
        </select>
        <button class="btn-orange" type="submit">Apply</button>
        <?php if ($search !== '' || $status !== '' || $catSlug !== ''): ?>
            <a href="products_manage.php" class="btn-ghost">Clear Filters</a>
        <?php endif; ?>
    </form>

    <table>
        <tr>
            <th>ID</th>
            <th>Thumbnail</th>
            <th>Product</th>
            <th>Category</th>
            <th>Price</th>
            <th>Old Price</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php if (!$products): ?>
            <tr><td colspan="8" style="text-align:center;padding:14px;">No products found.</td></tr>
        <?php endif; ?>

        <?php foreach ($products as $p): ?>
        <tr>
            <td><?= $p["id"] ?></td>
            <td>
                <img src="../uploads/products/<?= htmlspecialchars($p["product_image"]) ?>"
                     width="55" style="border-radius:6px;">
            </td>
            <td><?= htmlspecialchars($p["product_name"]) ?></td>
            <td><?= htmlspecialchars($p["category_name"]) ?></td>
            <td><?= number_format($p["price"], 2) ?> MMK</td>
            <td><?= number_format($p["old_price"], 2) ?></td>
            <td><?= htmlspecialchars(ucfirst($p["status"])) ?></td>
            <td>
                <div class="action-stack">
                    <a href="product_edit.php?id=<?= $p['id'] ?>" 
                    class="action-btn btn-edit">
                    Edit
                    </a>

                    <a href="?delete=<?= $p['id'] ?>"
                    class="action-btn btn-delete"
                    onclick="return confirm('Delete this product?')">
                    Delete
                    </a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>

    </table>

    <?php if ($totalPages > 1): ?>
    <div style="margin-top:14px; display:flex; gap:8px; align-items:center;">
        <?php if ($page > 1): ?>
            <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">Prev</a>
        <?php endif; ?>
        <span>Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">Next</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
if (isset($_GET["msg"])) {
    if ($_GET["msg"] === "deleted") {
        echo "<script>showAlert('Product Deleted Successfully');</script>";
    } elseif ($_GET["msg"] === "delete_failed") {
        echo "<script>showAlert('Unable to delete product. Please remove related data and try again.');</script>";
    }
}
?>

</body>
</html>
