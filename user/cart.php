<?php
require_once __DIR__ . '/../db/functions.php';
require_login();

$userId = current_user_id();

function ensure_cart_id(PDO $conn, int $userId): int {
    $stmt = $conn->prepare('SELECT id FROM carts WHERE user_id = :uid LIMIT 1');
    $stmt->execute([':uid' => $userId]);
    $cartId = $stmt->fetchColumn();
    if ($cartId) {
        return (int)$cartId;
    }
    $ins = $conn->prepare('INSERT INTO carts (user_id) VALUES (:uid)');
    $ins->execute([':uid' => $userId]);
    return (int)$conn->lastInsertId();
}

function normalize_redirect(string $redirect): string {
    if ($redirect === '') {
        return url_for('user/cart.php');
    }
    if (preg_match('#^https?://#i', $redirect)) {
        return $redirect;
    }
    if (strpos($redirect, '/') === 0 || strpos($redirect, '../') === 0) {
        return $redirect;
    }
    return url_for($redirect);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $redirect = normalize_redirect($_POST['redirect'] ?? '');

    try {
        if ($action === 'add') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $qty       = max(1, (int)($_POST['qty'] ?? 1));

            $conn->beginTransaction();
            $prod = $conn->prepare("SELECT id, product_name, price, stock, status FROM products WHERE id = :id FOR UPDATE");
            $prod->execute([':id' => $productId]);
            $product = $prod->fetch(PDO::FETCH_ASSOC);

            if (!$product || $product['status'] !== 'active') {
                $conn->rollBack();
                $_SESSION['flash'] = 'Product unavailable.';
                header('Location: ' . $redirect);
                exit;
            }

            if ((int)$product['stock'] <= 0) {
                $conn->rollBack();
                $_SESSION['flash'] = 'Out of stock.';
                header('Location: ' . $redirect);
                exit;
            }

            $qty = min($qty, (int)$product['stock'], 99);
            $cartId = ensure_cart_id($conn, $userId);

            // Lock existing row to merge quantities safely.
            $existingStmt = $conn->prepare('SELECT quantity FROM cart_items WHERE cart_id = :cid AND product_id = :pid FOR UPDATE');
            $existingStmt->execute([':cid' => $cartId, ':pid' => $productId]);
            $existingQty = (int)$existingStmt->fetchColumn();

            $newQty = min(99, min((int)$product['stock'], $existingQty + $qty));
            if ($existingQty > 0) {
                $up = $conn->prepare('UPDATE cart_items SET quantity = :qty, unit_price = :price WHERE cart_id = :cid AND product_id = :pid');
                $up->execute([
                    ':qty'   => $newQty,
                    ':price' => $product['price'],
                    ':cid'   => $cartId,
                    ':pid'   => $productId
                ]);
            } else {
                $ins = $conn->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, unit_price) VALUES (:cid, :pid, :qty, :price)');
                $ins->execute([
                    ':cid'   => $cartId,
                    ':pid'   => $productId,
                    ':qty'   => $newQty,
                    ':price' => $product['price']
                ]);
            }

            $conn->commit();
            $_SESSION['flash'] = 'Added to cart.';
        } elseif ($action === 'update') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $qty       = max(1, (int)($_POST['qty'] ?? 1));
            $cartId    = ensure_cart_id($conn, $userId);

            $conn->beginTransaction();
            $prod = $conn->prepare("SELECT id, stock FROM products WHERE id = :id FOR UPDATE");
            $prod->execute([':id' => $productId]);
            $product = $prod->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $conn->rollBack();
                $_SESSION['flash'] = 'Product not found.';
                header('Location: ' . $redirect);
                exit;
            }

            $qty = min($qty, max(1, (int)$product['stock']), 99);
            $up = $conn->prepare('UPDATE cart_items SET quantity = :qty WHERE cart_id = :cid AND product_id = :pid');
            $up->execute([':qty' => $qty, ':cid' => $cartId, ':pid' => $productId]);
            $conn->commit();
            $_SESSION['flash'] = 'Cart updated.';
        } elseif ($action === 'remove') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $cartId    = ensure_cart_id($conn, $userId);
            $del = $conn->prepare('DELETE FROM cart_items WHERE cart_id = :cid AND product_id = :pid');
            $del->execute([':cid' => $cartId, ':pid' => $productId]);
            $_SESSION['flash'] = 'Item removed.';
        } elseif ($action === 'clear') {
            $cartId = ensure_cart_id($conn, $userId);
            $conn->prepare('DELETE FROM cart_items WHERE cart_id = :cid')->execute([':cid' => $cartId]);
            $_SESSION['flash'] = 'Cart cleared.';
        } elseif ($action === 'checkout') {
            $paymentMethod = trim($_POST['payment_method'] ?? 'KBZPay');
            $cartId        = ensure_cart_id($conn, $userId);

            $conn->beginTransaction();
            $itemsStmt = $conn->prepare("
                SELECT ci.product_id, ci.quantity, p.product_name, p.price, p.stock, p.status
                FROM cart_items ci
                JOIN carts c ON ci.cart_id = c.id
                JOIN products p ON ci.product_id = p.id
                WHERE c.user_id = :uid
                FOR UPDATE
            ");
            $itemsStmt->execute([':uid' => $userId]);
            $cartItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$cartItems) {
                $conn->rollBack();
                $_SESSION['flash'] = 'Cart is empty.';
                header('Location: ' . $redirect);
                exit;
            }

            foreach ($cartItems as $ci) {
                if ($ci['status'] !== 'active' || (int)$ci['stock'] < (int)$ci['quantity']) {
                    $conn->rollBack();
                    $_SESSION['flash'] = 'One or more items are unavailable or out of stock.';
                    header('Location: ' . $redirect);
                    exit;
                }
            }

            $orderCode = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
            $total = 0;
            foreach ($cartItems as $ci) {
                $total += $ci['price'] * $ci['quantity'];
            }

            $orderStmt = $conn->prepare("
                INSERT INTO orders (user_id, order_code, total_amount, payment_method, payment_status, order_status, created_at)
                VALUES (:uid, :code, :total, :method, 'pending', 'pending', NOW())
            ");
            $orderStmt->execute([
                ':uid'   => $userId,
                ':code'  => $orderCode,
                ':total' => $total,
                ':method'=> $paymentMethod !== '' ? $paymentMethod : 'KBZPay'
            ]);
            $orderId = (int)$conn->lastInsertId();

            $itemIns = $conn->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal)
                VALUES (:oid, :pid, :name, :qty, :price, :subtotal)
            ");
            $stockUpd = $conn->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");

            foreach ($cartItems as $ci) {
                $itemIns->execute([
                    ':oid'      => $orderId,
                    ':pid'      => $ci['product_id'],
                    ':name'     => $ci['product_name'],
                    ':qty'      => $ci['quantity'],
                    ':price'    => $ci['price'],
                    ':subtotal' => $ci['price'] * $ci['quantity']
                ]);
                $stockUpd->execute([':qty' => $ci['quantity'], ':id' => $ci['product_id']]);
            }

            $payStmt = $conn->prepare("
                INSERT INTO payments (order_id, amount, method, status, transaction_ref, paid_at)
                VALUES (:oid, :amount, :method, 'pending', NULL, NULL)
            ");
            $payStmt->execute([
                ':oid'    => $orderId,
                ':amount' => $total,
                ':method' => $paymentMethod !== '' ? $paymentMethod : 'KBZPay'
            ]);

            $conn->prepare('DELETE FROM cart_items WHERE cart_id = :cid')->execute([':cid' => $cartId]);
            $conn->commit();

            $_SESSION['flash'] = "Order created: {$orderCode}. Total " . number_format($total,2) . " MMK via {$paymentMethod}.";
            $_SESSION['latest_order_id'] = $orderId;
            $_SESSION['latest_order_code'] = $orderCode;
            $_SESSION['latest_order_return'] = $redirect;

            $successUrl = url_for('user/order_success.php');
            $query = ['order_id' => $orderId, 'code' => $orderCode, 'return' => $redirect];
            header('Location: ' . $successUrl . '?' . http_build_query($query));
            exit;
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['flash'] = 'Unable to update cart right now.';
    }

    header('Location: ' . $redirect);
    exit;
}

$cartId = ensure_cart_id($conn, $userId);

$itemsStmt = $conn->prepare("
    SELECT ci.product_id,
           ci.quantity,
           ci.unit_price,
           p.product_name,
           p.product_image,
           p.stock,
           p.status,
           c.category_name
    FROM cart_items ci
    JOIN carts c2 ON ci.cart_id = c2.id
    JOIN products p ON ci.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE c2.user_id = :uid
    ORDER BY ci.id DESC
");
$itemsStmt->execute([':uid' => $userId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$cartTotal = 0;
foreach ($items as $it) {
    $cartTotal += $it['unit_price'] * $it['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Cart - Dokebi Family</title>
  <link rel="stylesheet" href="../maincss/style.css">
  <link rel="stylesheet" href="../maincss/dark-mode.css">
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="back-icon" href="<?php echo url_for('main/shop.php'); ?>" aria-label="Shop">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
      <a class="logo" href="<?php echo url_for('main/index.php'); ?>"><img src="../logo/original.png" alt="Dokebi Family" class="logo-img"><span class="sitename">Dokebi Family</span></a>
      <div class="header-left-links">
        <a href="<?php echo url_for('main/index.php'); ?>">Home</a>
        <a href="<?php echo url_for('main/shop.php'); ?>">Shop</a>
        <a href="<?php echo url_for('user/account.php'); ?>">Account</a>
        <div class="theme-toggle">
          <span>Dark / Light</span>
          <label class="toggle-switch">
            <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>
    </div>
  </header>
  <main>
    <section class="container">
      <div class="form">
        <h2>My Cart</h2>
        <?php if(!empty($_SESSION['flash'])): ?><div class="notice"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div><?php endif; ?>
        <?php if(!$items): ?>
          <div class="notice">Your cart is empty. Add items from the shop.</div>
          <a class="btn primary" href="<?php echo url_for('main/shop.php'); ?>">Go to Shop</a>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:0.75rem;">
            <?php foreach($items as $it): 
                $img = $it['product_image'] ?: 'default_product.png';
                $imgPath = '../uploads/products/' . $img;
                $isAvailable = ($it['status'] === 'active' && (int)$it['stock'] > 0);
                $maxQty = max(1, min(99, (int)$it['stock'] > 0 ? (int)$it['stock'] : $it['quantity']));
            ?>
            <div class="product-card" style="display:flex;gap:0.75rem;align-items:flex-start;">
              <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($it['product_name']); ?>" style="width:92px;height:92px;object-fit:cover;border-radius:12px;border:1px solid var(--glass);">
              <div style="flex:1;display:flex;flex-direction:column;gap:0.4rem;">
                <div style="display:flex;justify-content:space-between;gap:0.5rem;align-items:center;">
                  <div>
                    <strong><?php echo htmlspecialchars($it['product_name']); ?></strong>
                    <div class="muted tiny"><?php echo htmlspecialchars($it['category_name'] ?? ''); ?></div>
                  </div>
                  <form method="post" action="cart.php" style="margin:0;">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?php echo (int)$it['product_id']; ?>">
                    <input type="hidden" name="redirect" value="<?php echo url_for('user/cart.php'); ?>">
                    <button class="btn" type="submit" style="background:var(--panel-2);">Remove</button>
                  </form>
                </div>
                <?php if (!$isAvailable): ?>
                  <div class="notice error" style="margin:0;">Currently unavailable.</div>
                <?php elseif ((int)$it['stock'] < (int)$it['quantity']): ?>
                  <div class="notice" style="margin:0;">Only <?php echo (int)$it['stock']; ?> left in stock.</div>
                <?php endif; ?>
                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                  <form method="post" action="cart.php" style="display:flex;align-items:center;gap:0.5rem;margin:0;">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="product_id" value="<?php echo (int)$it['product_id']; ?>">
                    <input type="hidden" name="redirect" value="<?php echo url_for('user/cart.php'); ?>">
                    <label class="muted tiny" for="qty_<?php echo (int)$it['product_id']; ?>">Qty</label>
                    <select id="qty_<?php echo (int)$it['product_id']; ?>" name="qty">
                      <?php for($q=1; $q<=$maxQty; $q++): ?>
                        <option value="<?php echo $q; ?>" <?php echo $q==(int)$it['quantity']?'selected':''; ?>><?php echo $q; ?></option>
                      <?php endfor; ?>
                    </select>
                    <button class="btn" type="submit">Update</button>
                  </form>
                  <div><strong><?php echo number_format($it['unit_price'] * $it['quantity'], 2); ?> MMK</strong></div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="form-actions" style="justify-content:space-between;align-items:center;margin-top:1rem;gap:0.5rem;flex-wrap:wrap;">
            <div><strong>Total: <?php echo number_format($cartTotal,2); ?> MMK</strong></div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
              <form method="post" action="cart.php">
                <input type="hidden" name="action" value="clear">
                <input type="hidden" name="redirect" value="<?php echo url_for('user/cart.php'); ?>">
                <button class="btn" type="submit" style="background:var(--panel-2);">Clear Cart</button>
              </form>
              <a class="btn primary" href="<?php echo url_for('main/shop.php'); ?>">Continue Shopping</a>
              <form method="post" action="cart.php" style="display:flex;gap:0.5rem;align-items:center;margin:0;">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="redirect" value="<?php echo url_for('user/cart.php'); ?>">
                <label class="muted tiny" for="pay_method">Pay with</label>
                <select id="pay_method" name="payment_method">
                  <option value="KBZPay">KBZPay</option>
                  <option value="Wave Pay">Wave Pay</option>
                  <option value="Aya Pay">Aya Pay</option>
                  <option value="Visa/Mastercard">Visa/Mastercard</option>
                  <option value="MPU">MPU</option>
                </select>
                <button class="btn primary" type="submit">Proceed to Checkout</button>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <script src="../js/theme-toggle.js"></script>
  <script src="../js/app.js" defer></script>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
