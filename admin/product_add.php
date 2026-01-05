<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* ---------------------------
   Slug generator function
--------------------------- */
function make_slug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9]+/', '-', $string);
    return trim($string, '-');
}

/* ---------------------------
   Fetch categories
--------------------------- */
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name ASC")
                   ->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------
   Handle Add Product
--------------------------- */
if (isset($_POST["add"])) {

    $name        = $_POST["name"];
    $category    = $_POST["category"];
    $price       = $_POST["price"];
    $old_price   = $_POST["old_price"];
    $stock       = max(0, (int)($_POST["stock"] ?? 0));
    $description = $_POST["description"];

    /* Create unique slug */
    $slug = make_slug($name) . "-" . time();

    /* Create correct upload folder if not exist */
    $uploadDir = realpath(__DIR__ . '/../uploads/products');

    if (!$uploadDir) {
        mkdir(__DIR__ . '/../uploads/products', 0777, true);
        $uploadDir = realpath(__DIR__ . '/../uploads/products');
    }

    $uploadDir .= "/";

    /* Handle image upload */
    $fileName = "default_product.png";

    if (!empty($_FILES["product_image"]["name"])) {
        $fileName = time() . "_" . basename($_FILES["product_image"]["name"]);
        $tmpPath  = $_FILES["product_image"]["tmp_name"];
        move_uploaded_file($tmpPath, $uploadDir . $fileName);
    }

    /* Insert product into DB */
    $stmt = $conn->prepare("
        INSERT INTO products (category_id, product_name, slug, description, price, old_price, stock, product_image)
        VALUES (:cat, :n, :slug, :d, :p, :op, :stock, :img)
    ");

    $stmt->execute([
        ':cat'  => $category,
        ':n'    => $name,
        ':slug' => $slug,
        ':d'    => $description,
        ':p'    => $price,
        ':op'   => $old_price,
        ':stock'=> $stock,
        ':img'  => $fileName
    ]);

    header("Location: product_add.php?msg=added");
    exit();
}

$pageTitle = "Add New Product";
$pageSubtitle = "Create a new product for Dokebi Tekoku store";
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php include "theme.php"; ?>
</head>

<body>

<div id="alertBox" class="alert-box"></div>

<!-- SIDEBAR -->
<div class="sidebar">
    <a href="dashboard.php">Dashboard</a>
    <a href="products_manage.php" class="active-link">Products</a>
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

    <a href="products_manage.php" class="btn-orange" style="margin-bottom:20px; display:inline-block;"> Back to Products</a>

    <div class="form-card">
        <h2>Add Product</h2>

        <form method="POST" enctype="multipart/form-data">

            <label>Product Name</label>
            <input type="text" name="name" required>

            <label>Category</label>
            <select name="category" required>
                <option disabled selected>Select category</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>">
                    <?= htmlspecialchars($c['category_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <label>Price</label>
            <input type="number" name="price" step="0.01" required>

            <label>Old Price (optional)</label>
            <input type="number" name="old_price" step="0.01">

            <label>Stock Quantity</label>
            <input type="number" name="stock" min="0" value="0">

            <label>Description</label>
            <textarea name="description" rows="4"></textarea>

            <label>Product Image</label>
            <input type="file" name="product_image">

            <br><br>

            <button type="submit" name="add" class="btn-orange">Add Product</button>
        </form>
    </div>
</div>

<?php
if (isset($_GET["msg"]) && $_GET["msg"] === "added") {
    echo "<script>showAlert('Product Added Successfully');</script>";
}
?>

</body>
</html>
