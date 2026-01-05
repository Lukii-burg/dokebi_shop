<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* ----------------------------------------
   FETCH USER
---------------------------------------- */
$id = (int) $_GET["id"];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

/* ----------------------------------------
   UPDATE USER
---------------------------------------- */
if (isset($_POST["update"])) {

    $name     = $_POST["name"];
    $username = $_POST["username"];
    $email    = $_POST["email"];
    $password = $_POST["password"]; // not hashed (as you requested)
    $role     = $_POST["role"];
    $status   = $_POST["status"];

    $current_img = $_POST["current_image"];

    /* Ensure upload folder exists */
    $uploadDir = realpath(__DIR__ . '/../uploads/users');
    if (!$uploadDir) {
        mkdir(__DIR__ . '/../uploads/users', 0777, true);
        $uploadDir = realpath(__DIR__ . '/../uploads/users');
    }
    $uploadDir .= "/";

    /* Handle new uploaded image */
    $finalImage = $current_img;

    if (!empty($_FILES["profile_image"]["name"])) {

        $newName = time() . "_" . basename($_FILES["profile_image"]["name"]);

        move_uploaded_file($_FILES["profile_image"]["tmp_name"], $uploadDir . $newName);

        // delete old image
        if ($current_img !== "default_user.png") {
            @unlink($uploadDir . $current_img);
        }

        $finalImage = $newName;
    }

    /* Update database */
    $stmt = $conn->prepare("
        UPDATE users SET
            name = :n,
            username = :u,
            email = :e,
            password = :p,
            role = :r,
            profile_image = :img,
            status = :s
        WHERE id = :id
    ");

    $stmt->execute([
        ':n'   => $name,
        ':u'   => $username,
        ':e'   => $email,
        ':p'   => $password,
        ':r'   => $role,
        ':img' => $finalImage,
        ':s'   => $status,
        ':id'  => $id
    ]);

    header("Location: user_edit.php?id=$id&msg=updated");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit User | Dokebi Tekoku</title>
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
        <strong style="font-size:20px;">Edit User</strong>
        <p style="margin:0; opacity:0.8;">Modify user details</p>
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

        <h2>Edit User</h2>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="current_image" value="<?= htmlspecialchars($user['profile_image']) ?>">

            <label>Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>

            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>

            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

            <label>Password</label>
            <input type="text" name="password" value="<?= htmlspecialchars($user['password']) ?>" required>

            <label>Role</label>
            <select name="role">
                <option value="user"  <?= $user['role'] == "user" ? "selected" : "" ?>>User</option>
                <option value="admin" <?= $user['role'] == "admin" ? "selected" : "" ?>>Admin</option>
            </select>

            <label>Status</label>
            <select name="status">
                <option value="active"  <?= $user['status'] == "active" ? "selected" : "" ?>>Active</option>
                <option value="blocked" <?= $user['status'] == "blocked" ? "selected" : "" ?>>Blocked</option>
            </select>

            <label>Current Avatar</label><br>
            <img src="../uploads/users/<?= htmlspecialchars($user['profile_image']) ?>"
                width="120" style="border-radius:10px; margin-bottom:10px;">
            <br>

            <label>Upload New Avatar</label>
            <input type="file" name="profile_image">

            <button type="submit" name="update" class="btn-orange" style="margin-top:20px;">
                Update User
            </button>

        </form>

    </div>
</div>

<?php
if (isset($_GET["msg"]) && $_GET["msg"] === "updated") {
    echo "<script>showAlert('User Updated Successfully');</script>";
}
?>

</body>
</html>
