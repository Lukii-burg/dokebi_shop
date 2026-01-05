<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$pageTitle = "Add New User";
$pageSubtitle = "Create an account for Dokebi Tekoku";

/* ----------------------------------------
   HANDLE ADD USER
---------------------------------------- */
if (isset($_POST["add"])) {

    $name       = $_POST["name"];
    $username   = $_POST["username"];
    $email      = $_POST["email"];
    $password   = $_POST["password"];
    $role       = $_POST["role"];
    $status     = $_POST["status"];

    /* Password currently NOT hashed (you requested) */
    $plain_password = $password;

    /* Ensure upload folder exists */
    $uploadDir = realpath(__DIR__ . '/../uploads/users');
    if (!$uploadDir) {
        mkdir(__DIR__ . '/../uploads/users', 0777, true);
        $uploadDir = realpath(__DIR__ . '/../uploads/users');
    }
    $uploadDir .= "/";

    /* Handle avatar upload */
    $fileName = "default_user.png";

    if (!empty($_FILES["profile_image"]["name"])) {
        $fileName = time() . "_" . basename($_FILES["profile_image"]["name"]);
        move_uploaded_file($_FILES["profile_image"]["tmp_name"], $uploadDir . $fileName);
    }

    /* Insert user into DB */
    $stmt = $conn->prepare("
        INSERT INTO users (name, username, email, password, role, profile_image, status)
        VALUES (:n, :u, :e, :p, :r, :img, :s)
    ");

    $stmt->execute([
        ':n'   => $name,
        ':u'   => $username,
        ':e'   => $email,
        ':p'   => $plain_password,
        ':r'   => $role,
        ':img' => $fileName,
        ':s'   => $status
    ]);

    header("Location: user_add.php?msg=added");
    exit();
}
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

    <a href="users_manage.php" class="btn-orange" style="margin-bottom:20px; display:inline-block;">
         Back to Users
    </a>

    <div class="form-card" style="max-width:500px;">
        <h2>Add New User</h2>

        <form method="POST" enctype="multipart/form-data">

            <label>Name</label>
            <input type="text" name="name" required>

            <label>Username</label>
            <input type="text" name="username" required>

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="text" name="password" required>

            <label>Role</label>
            <select name="role" required>
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>

            <label>Status</label>
            <select name="status">
                <option value="active">Active</option>
                <option value="blocked">Blocked</option>
            </select>

            <label>Profile Image</label>
            <input type="file" name="profile_image">

            <button type="submit" name="add" class="btn-orange">
                 Create User
            </button>

        </form>
    </div>

</div>

<?php
if (isset($_GET["msg"]) && $_GET["msg"] === "added") {
    echo "<script>showAlert('User Added Successfully');</script>";
}
?>

</body>
</html>
