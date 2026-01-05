<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Filters
$search = trim($_GET['q'] ?? '');
$role   = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

/* ----------------------------------------
   DELETE USER (D of CRUD)
---------------------------------------- */
if (isset($_GET["delete"])) {
    $id = (int) $_GET["delete"];

    // Main admin (ID 1) cannot be deleted
    if ($id == 1) {
        header("Location: users_manage.php?msg=protect");
        exit();
    }

    // Fetch current profile image
    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id=?");
    $stmt->execute([$id]);
    $img = $stmt->fetchColumn();

    try {
        $conn->beginTransaction();

        // Clean dependent data to satisfy FKs
        // Orders (and linked payments/order_items)
        $conn->prepare("DELETE FROM payments WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)")->execute([$id]);
        $conn->prepare("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)")->execute([$id]);
        $conn->prepare("DELETE FROM orders WHERE user_id = ?")->execute([$id]);

        // Reviews
        $conn->prepare("DELETE FROM reviews WHERE user_id = ?")->execute([$id]);
        // Wishlists (already ON DELETE CASCADE but run just in case)
        $conn->prepare("DELETE FROM wishlists WHERE user_id = ?")->execute([$id]);

        // Carts & items
        $conn->prepare("DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id = ?)")->execute([$id]);
        $conn->prepare("DELETE FROM carts WHERE user_id = ?")->execute([$id]);

        // AI chat logs
        $conn->prepare("DELETE FROM ai_chat_logs WHERE user_id = ?")->execute([$id]);

        // Finally delete user
        $conn->prepare("DELETE FROM users WHERE id=?")->execute([$id]);

        $conn->commit();

        if ($img && $img !== "default_user.png") {
            @unlink("../uploads/users/" . $img);
        }

        header("Location: users_manage.php?msg=deleted");
        exit();
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        // Handle FK constraints (e.g., user has orders/payments)
        if ($e->getCode() === '23000') {
            header("Location: users_manage.php?msg=blocked_fk");
            exit();
        }
        throw $e; // bubble up unexpected errors
    }
}

/* ----------------------------------------
   FETCH USERS (R of CRUD)
---------------------------------------- */
$where  = [];
$params = [];
if ($search !== '') {
    $where[] = "(name LIKE :q OR username LIKE :q OR email LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
if ($role !== '' && in_array($role, ['user','admin'], true)) {
    $where[] = "role = :role";
    $params[':role'] = $role;
}
if ($status !== '' && in_array($status, ['active','blocked'], true)) {
    $where[] = "status = :status";
    $params[':status'] = $status;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countSql = "SELECT COUNT(*) FROM users $whereSql";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$sql = "
    SELECT * FROM users
    $whereSql
    ORDER BY id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Users Management";
$pageSubtitle = "Manage all Dokebi Tekoku users";
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
    <a href="products_manage.php">Products</a>
    <a href="main_categories_management.php">Main Categories</a>
    <a href="categories_management.php">Categories</a>
    <a href="users_manage.php" class="active-link">Users</a>
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

    <a href="user_add.php" class="btn-orange" style="margin-bottom:20px; display:inline-block;">
        Add New User
    </a>

    <form method="get" class="filter-bar">
        <input class="filter-input" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, username, email...">
        <select class="filter-select" name="role">
            <option value="">All Roles</option>
            <option value="user"  <?= $role==='user'?'selected':''; ?>>User</option>
            <option value="admin" <?= $role==='admin'?'selected':''; ?>>Admin</option>
        </select>
        <select class="filter-select" name="status">
            <option value="">All Status</option>
            <option value="active"  <?= $status==='active'?'selected':''; ?>>Active</option>
            <option value="blocked" <?= $status==='blocked'?'selected':''; ?>>Blocked</option>
        </select>
        <button class="btn-orange" type="submit">Apply</button>
        <?php if ($search !== '' || $role !== '' || $status !== ''): ?>
            <a href="users_manage.php" class="btn-ghost">Clear Filters</a>
        <?php endif; ?>
    </form>

    <table>
        <tr>
            <th>ID</th>
            <th>Avatar</th>
            <th>Name</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php if (!$users): ?>
            <tr><td colspan="8" style="text-align:center;padding:14px;">No users found.</td></tr>
        <?php endif; ?>

        <?php foreach ($users as $u): ?>

        <?php
            // SAFE avatar handling (will never cause warnings)
            $avatar = (isset($u['profile_image']) && $u['profile_image'] !== "")
                ? basename($u['profile_image'])
                : "default_user.png";
        ?>

        <tr>
            <td><?= $u['id'] ?></td>

            <td>
                <img src="../uploads/users/<?= htmlspecialchars($avatar) ?>"
                     width="50" height="50"
                     style="border-radius:50%; object-fit:cover;">
            </td>

            <td><?= htmlspecialchars($u['name']) ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>

            <td>
                <?php if ($u['role'] == "admin"): ?>
                    <span style="color:#8f57ff; font-weight:bold;">Admin</span>
                <?php else: ?>
                    <span style="color:#4ef0c2;">User</span>
                <?php endif; ?>
            </td>

            <td>
                <?php if ($u['status'] == "active"): ?>
                    <span style="color:#4ef0c2; font-weight:600;">Active</span>
                <?php else: ?>
                    <span style="color:#ff4c4c; font-weight:600;">Blocked</span>
                <?php endif; ?>
            </td>

            <td>
                <div class="action-stack">
                    <a href="user_edit.php?id=<?= $u['id'] ?>" class="action-btn btn-edit">
                        Edit
                    </a>

                    <?php if ($u['id'] != 1): ?>
                    <a href="?delete=<?= $u['id'] ?>"
                       class="action-btn btn-delete"
                       onclick="return confirm('Delete user?');">
                        Delete
                    </a>
                    <?php else: ?>
                        <span style="opacity:0.5;">Protected</span>
                    <?php endif; ?>
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
// Alert messages
if (isset($_GET["msg"])) {
    if ($_GET["msg"] == "deleted") {
        echo "<script>showAlert('User Deleted Successfully');</script>";
    }
    if ($_GET["msg"] == "protect") {
        echo "<script>showAlert('Main Admin Cannot Be Deleted');</script>";
    }
    if ($_GET["msg"] == "blocked_fk") {
        echo "<script>showAlert('Cannot delete this user because related orders or payments exist.');</script>";
    }
}
?>

</body>
</html>
