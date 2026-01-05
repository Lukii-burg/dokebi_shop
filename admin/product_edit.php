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
   Fetch product to edit
--------------------------- */
$id = (int) $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM products WHERE id=?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Product not found.");
}

/* Fetch categories */
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name ASC")
                   ->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------
   Handle Update Product
--------------------------- */
if (isset($_POST["update"])) {

    $name        = $_POST["name"];
    $category    = $_POST["category"];
    $price       = $_POST["price"];
    $old_price   = $_POST["old_price"];
    $stock       = max(0, (int)($_POST["stock"] ?? 0));
    $description = $_POST["description"];
    $current_image = $_POST["current_image"];

    /* Generate NEW slug (unique) */
    $slug = make_slug($name) . "-" . time();

    /* Ensure upload directory exists */
    $uploadDir = realpath(__DIR__ . '/../uploads/products');

    if (!$uploadDir) {
        mkdir(__DIR__ . '/../uploads/products', 0777, true);
        $uploadDir = realpath(__DIR__ . '/../uploads/products');
    }

    $uploadDir .= "/";

    /* Replace image if uploaded */
    $fileName = $current_image;

    if (!empty($_FILES["product_image"]["name"])) {

        $fileName = time() . "_" . basename($_FILES["product_image"]["name"]);
        $tmpPath  = $_FILES["product_image"]["tmp_name"];

        move_uploaded_file($tmpPath, $uploadDir . $fileName);

        /* remove old image (except default) */
        if ($current_image !== "default_product.png") {
            @unlink($uploadDir . $current_image);
        }
    }

    /* Update DB */
    $stmt = $conn->prepare("
        UPDATE products SET
            category_id = :cat,
            product_name = :n,
            slug = :slug,
            description = :d,
            price = :p,
            old_price = :op,
            stock = :stock,
            product_image = :img
        WHERE id = :id
    ");

    $stmt->execute([
        ':cat'  => $category,
        ':n'    => $name,
        ':slug' => $slug,
        ':d'    => $description,
        ':p'    => $price,
        ':op'   => $old_price,
        ':stock'=> $stock,
        ':img'  => $fileName,
        ':id'   => $id
    ]);

    header("Location: product_edit.php?id=$id&msg=updated");
    exit();
}

$pageTitle = "Edit Product";
$pageSubtitle = "Update this product information";
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

    <a href="products_manage.php" class="btn-orange" style="margin-bottom:20px; display:inline-block;">
         Back to Products
    </a>

    <div class="form-card">
        <h2>Edit Product</h2>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="current_image" value="<?= htmlspecialchars($product['product_image']) ?>">

            <label>Product Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($product['product_name']) ?>" required>

            <label>Category</label>
            <select name="category" required>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id'] == $product['category_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['category_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <label>Price</label>
            <input type="number" name="price" step="0.01" value="<?= $product['price'] ?>" required>

            <label>Old Price</label>
            <input type="number" name="old_price" step="0.01" value="<?= $product['old_price'] ?>">

            <label>Stock Quantity</label>
            <input type="number" name="stock" min="0" value="<?= (int)$product['stock'] ?>">

            <label>Description</label>
            <textarea name="description" rows="4"><?= htmlspecialchars($product['description']) ?></textarea>

            <label>Current Image</label><br>
            <img src="../uploads/products/<?= htmlspecialchars($product['product_image']) ?>"
                 width="130" style="border-radius:10px;"><br><br>

            <label>Upload New Image</label>
            <input type="file" name="product_image">

            <button type="submit" name="update" class="btn-orange">Update Product</button>
        </form>
    </div>
</div>

<?php
if (isset($_GET["msg"]) && $_GET["msg"] === "updated") {
    echo "<script>showAlert('Product Updated Successfully');</script>";
}
?>

</body>
</html>
